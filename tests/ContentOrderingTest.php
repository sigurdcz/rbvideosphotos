<?php

declare(strict_types=1);

use App\Repositories\BlogRepository;
use App\Repositories\PortfolioRepository;
use App\Repositories\SiteRepository;

/**
 * Hand-typed order numbers decide what the visitor sees first.
 *
 * The contract: a number always wins, and the old automatic behaviour survives only
 * where no number distinguishes two items - photos and videos are mixed inside one
 * sort_order, a gallery falls back to the order its photos were picked in, and blog
 * posts sharing a number stay newest-first. 0 means "not numbered" and sorts behind
 * everything that is, so typing 1 really does put an item first.
 */
function register_content_ordering_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);

    $test('migration 072 adds blog_posts.sort_order and both runners know it', function () use ($assert, $root): void {
        $migration = (string) file_get_contents($root . '/database/migrations/072_add_blog_posts_sort_order.sql');
        $assert(str_contains($migration, 'ALTER TABLE `blog_posts`'), 'Migration 072 must alter blog_posts.');
        $assert(str_contains($migration, 'ADD COLUMN `sort_order`'), 'Migration 072 must add the sort_order column.');
        // The expression in ORDER BY always costs a filesort; the index is here for the
        // site_id/status lookup the listing does first.
        $assert(str_contains($migration, 'ADD KEY `idx_blog_posts_site_status_order`'), 'The listing lookup needs its index.');

        // Without these arms a database that already has the column dies on
        // "Duplicate column name" and blocks every later migration.
        foreach (['/database/migrate-mysql.php', '/app/Services/SetupEnvironmentService.php'] as $runner) {
            $assert(
                str_contains((string) file_get_contents($root . $runner)
                    . ($runner === '/database/migrate-mysql.php' ? (string) file_get_contents($root . '/database/migration-schema-state.php') : ''), '072_add_blog_posts_sort_order.sql'),
                "{$runner} must treat migration 072 as satisfied when the column exists."
            );
        }
        $assert(
            str_contains((string) file_get_contents($root . '/database/schema.sql'), 'idx_blog_posts_site_status_order'),
            'schema.sql must document the new column and index.'
        );
    });

    $test('the listings order by the typed number', function () use ($assert, $root): void {
        $portfolio = (string) file_get_contents($root . '/app/Repositories/PortfolioRepository.php');
        $assert(
            str_contains($portfolio, 'ORDER BY (sort_order = 0) ASC, sort_order ASC, id ASC'),
            'Portfolio entries must order by the typed number, with 0 (not numbered) last.'
        );
        $assert(!str_contains($portfolio, 'content_type ASC'), 'Content type must no longer outrank the typed number.');
        $assert(str_contains($portfolio, 'ORDER BY pm.sort_order ASC, pm.id ASC'), 'Portfolio photos need the number first, pick order second.');

        $blog = (string) file_get_contents($root . '/app/Repositories/BlogRepository.php');
        $assert(
            str_contains($blog, 'ORDER BY (sort_order = 0) ASC, sort_order ASC, published_at DESC, id DESC'),
            'Published articles must order by the number first and the date second, with 0 (not numbered) last.'
        );
        $assert(str_contains($blog, 'ORDER BY bm.sort_order ASC, bm.id ASC'), 'Article galleries need the number first, pick order second.');
    });

    $test('photos and videos are only mixed inside one order number', function () use ($assert): void {
        $entry = static fn(string $type, int $sort): array => ['content_type' => $type, 'sort_order' => $sort];
        $shape = static fn(array $result): string => implode(' ', array_map(
            static fn(array $e): string => strtoupper($e['content_type'][0]) . ($e['sort_order'] ?? 0),
            $result
        ));

        // Nobody numbered anything: the old mixed grid, unchanged.
        $assert(
            $shape(PortfolioRepository::interleaveByContentType([
                $entry('photo', 0), $entry('photo', 0), $entry('video', 0),
                $entry('photo', 0), $entry('photo', 0), $entry('video', 0),
            ])) === 'P0 P0 V0 P0 P0 V0',
            'Entries left on the same number must still be interleaved.'
        );

        // Every entry numbered: the numbers win outright.
        $assert(
            $shape(PortfolioRepository::interleaveByContentType([
                $entry('photo', 1), $entry('photo', 2), $entry('video', 3), $entry('video', 4),
            ])) === 'P1 P2 V3 V4',
            'Distinct numbers must be followed exactly, videos included.'
        );

        // Mixed: groups follow the numbers, mixing happens inside a group.
        $assert(
            $shape(PortfolioRepository::interleaveByContentType([
                $entry('photo', 1), $entry('video', 1), $entry('photo', 1), $entry('photo', 2), $entry('video', 2),
            ])) === 'P1 P1 V1 P2 V2',
            'Each number forms its own group, interleaved within.'
        );

        $withoutKey = PortfolioRepository::interleaveByContentType([['content_type' => 'photo'], ['content_type' => 'video']]);
        $assert(count($withoutKey) === 2, 'An entry without a sort_order key must not be dropped.');
    });

    $test('the homepage sampler zips types by count ratio', function () use ($assert): void {
        $entry = static fn(string $type, int $sort): array => ['content_type' => $type, 'sort_order' => $sort];
        $shape = static fn(array $result): string => implode(' ', array_map(
            static fn(array $e): string => strtoupper($e['content_type'][0]) . ($e['sort_order'] ?? 0),
            $result
        ));

        // The homepage sampler zips the two types by their count ratio and ignores the
        // numbers between types - 4 photos to 2 videos means a video after every two
        // photos, whatever number each of them carries.
        $assert(
            $shape(PortfolioRepository::interleaveTypes([
                $entry('photo', 1), $entry('photo', 2), $entry('photo', 3), $entry('photo', 4),
                $entry('video', 25), $entry('video', 26),
            ])) === 'P1 P2 V25 P3 P4 V26',
            'interleaveTypes() must zip photos and videos by ratio, ignoring numbers across types.'
        );

        // A one-sided list has nothing to zip and must come back untouched.
        $assert(
            $shape(PortfolioRepository::interleaveTypes([
                $entry('photo', 1), $entry('photo', 2), $entry('photo', 3),
            ])) === 'P1 P2 P3',
            'Photos without a single video must keep their order.'
        );
        $assert(
            $shape(PortfolioRepository::interleaveTypes([
                $entry('video', 25), $entry('video', 26),
            ])) === 'V25 V26',
            'Videos without a single photo must keep their order.'
        );

        // More videos than photos: one video after each photo, and the surplus trails
        // at the end - there is no photo left to put between them.
        $assert(
            $shape(PortfolioRepository::interleaveTypes([
                $entry('photo', 1), $entry('photo', 2),
                $entry('video', 7), $entry('video', 8), $entry('video', 9),
            ])) === 'P1 V7 P2 V8 V9',
            'A 2:3 ratio must leave the extra video at the end.'
        );

        // More photos than videos: the group size rounds 3/2 up to two photos, so the
        // last photo ends up alone in front of the closing video.
        $assert(
            $shape(PortfolioRepository::interleaveTypes([
                $entry('photo', 1), $entry('photo', 2), $entry('photo', 3),
                $entry('video', 7), $entry('video', 8),
            ])) === 'P1 P2 V7 P3 V8',
            'A 3:2 ratio must group two photos first, then the remaining one.'
        );
    });

    $test('video items carry their own number and the lowest one becomes the mirror', function () use ($assert): void {
        $items = PortfolioRepository::normalizeVideoItems([
            ['url' => 'https://youtu.be/aaaaaaaaaaa', 'sort_order' => 5],
            ['url' => 'https://vimeo.com/2', 'sort_order' => 0],
            ['url' => 'https://youtu.be/ccccccccccc', 'sort_order' => 5],
            'https://example.com/x',
        ]);
        $assert(count($items) === 3, 'An unsupported address must still be dropped.');

        $ordered = array_column(PortfolioRepository::orderVideoItems($items), 'url');
        $assert(
            $ordered === ['https://vimeo.com/2', 'https://youtu.be/aaaaaaaaaaa', 'https://youtu.be/ccccccccccc'],
            'Videos must follow the number, and keep their entry order when the number is equal.'
        );

        // Plain strings mean "no number typed" and must behave exactly as before.
        $assert(
            PortfolioRepository::normalizeVideoUrls(['https://vimeo.com/1', ' https://youtu.be/bbbbbbbbbbb ']) ===
                ['https://vimeo.com/1', 'https://youtu.be/bbbbbbbbbbb'],
            'A list of plain URLs must keep its own order.'
        );
    });

    $test('the admin forms collect a number for photos, videos and articles', function () use ($assert, $root): void {
        $adminJs = (string) file_get_contents($root . '/admin/assets/admin.js');
        $assert(str_contains($adminJs, 'window.AdminMediaSelection'), 'The shared selection renderer must exist.');

        $portfolioForm = (string) file_get_contents($root . '/admin/views/portfolio/form.php');
        $assert(str_contains($portfolioForm, "name=\"video_sort[]\""), 'Each video row needs its order number.');
        $assert(str_contains($portfolioForm, "sortName: 'media_sort'"), 'The portfolio gallery needs per-photo numbers.');
        $assert(str_contains($portfolioForm, 'name="video_urls[]"'), 'The video URL field must stay.');

        $blogForm = (string) file_get_contents($root . '/admin/views/blog/form.php');
        $assert(str_contains($blogForm, 'name="sort_order"'), 'An article needs its own order field.');
        $assert(str_contains($blogForm, "sortName: 'gallery_sort'"), 'The article gallery needs per-photo numbers.');

        $portfolioController = (string) file_get_contents($root . '/app/Controllers/PortfolioController.php');
        $assert(str_contains($portfolioController, "postedSortMap('media_sort')"), 'The portfolio save must read the photo numbers.');
        $blogController = (string) file_get_contents($root . '/app/Controllers/BlogController.php');
        $assert(str_contains($blogController, "postedSortMap('gallery_sort')"), 'The article save must read the photo numbers.');
    });

    $test('a saved number survives the round trip, and a save without numbers keeps pick order', function () use ($assert): void {
        $site = (new SiteRepository())->findFirst();
        if (!$site) {
            return; // No database fixture here - the contracts above still ran.
        }

        $portfolio = new PortfolioRepository();
        $id = $portfolio->create([
            'site_id' => $site['id'],
            'title' => 'Test poradi',
            'slug' => 'test-poradi-' . bin2hex(random_bytes(4)),
            'description' => '',
            'video_url' => null,
            'content_type' => 'video',
            'status' => 'draft',
        ]);

        try {
            $portfolio->setVideos($id, [
                ['url' => 'https://youtu.be/aaaaaaaaaaa', 'sort_order' => 5],
                ['url' => 'https://vimeo.com/2', 'sort_order' => 1],
            ]);
            $assert(
                $portfolio->getVideoUrls($id) === ['https://vimeo.com/2', 'https://youtu.be/aaaaaaaaaaa'],
                'The stored videos must come back in the numbered order.'
            );
            $assert(
                ($portfolio->findById($id)['video_url'] ?? null) === 'https://vimeo.com/2',
                'The mirror must follow the lowest number, not the row order.'
            );
            $assert(
                array_column($portfolio->getVideos($id), 'sort_order') === [1, 5],
                'getVideos() must expose the numbers so the form can show them.'
            );

            // Photo numbers: with a map the numbers decide, without one the position does.
            $mediaIds = array_column($portfolio->getMedia(2), 'id');
            if (count($mediaIds) >= 2) {
                [$first, $second] = $mediaIds;
                $portfolio->setMedia($id, [$first, $second], [$first => 2, $second => 1]);
                $assert(
                    array_column($portfolio->getMedia($id), 'id') === [$second, $first],
                    'A photo map must reorder the gallery.'
                );
                $portfolio->setMedia($id, [$first, $second]);
                $assert(
                    array_column($portfolio->getMedia($id), 'pm_sort') === [0, 1],
                    'Without a map the pick order must still become the order.'
                );
                $portfolio->setMedia($id, [$first], [$first => PHP_INT_MAX]);
                $assert(
                    (int)($portfolio->getMedia($id)[0]['pm_sort'] ?? -1) === PortfolioRepository::MAX_SORT_ORDER,
                    'An out-of-range number must be clamped instead of aborting the rewrite.'
                );
            }
        } finally {
            $portfolio->delete($id);
        }
    });
}
