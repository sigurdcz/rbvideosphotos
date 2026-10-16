<?php

declare(strict_types=1);

use App\Repositories\PortfolioRepository;

/**
 * A portfolio entry can carry any number of YouTube / Vimeo videos.
 *
 * The contract these tests protect: `portfolio_videos` holds the ordered list and
 * `portfolio_entries.video_url` stays a mirror of the FIRST video, because the
 * listing tiles, the homepage, the lightbox, the JSON-LD and the API all keep
 * reading that single column. Everything downstream of an entry with exactly one
 * video must therefore render exactly as it did before the table existed.
 */
function register_portfolio_videos_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);

    $test('migration 071 creates portfolio_videos and backfills the existing single video', function () use ($assert, $root): void {
        $migration = (string) file_get_contents($root . '/database/migrations/071_create_portfolio_videos.sql');

        $assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS `portfolio_videos`'), 'Migration 071 must create portfolio_videos.');
        $assert(str_contains($migration, 'CHARSET=utf8mb4'), 'portfolio_videos must declare an explicit utf8mb4 charset.');
        $assert(str_contains($migration, 'ON DELETE CASCADE'), 'Deleting an entry must take its videos with it.');
        $assert(
            str_contains($migration, 'INSERT INTO `portfolio_videos`') && str_contains($migration, 'FROM `portfolio_entries` p'),
            'Migration 071 must backfill portfolio_videos from the existing video_url column.'
        );
    });

    $test('only YouTube and Vimeo URLs survive normalisation, in order and without duplicates', function () use ($assert): void {
        $normalized = PortfolioRepository::normalizeVideoUrls([
            ' https://youtu.be/abc123def45 ',
            '',
            'https://example.com/x',
            'https://vimeo.com/123',
            'https://youtu.be/abc123def45',
        ]);

        $assert(
            $normalized === ['https://youtu.be/abc123def45', 'https://vimeo.com/123'],
            'normalizeVideoUrls must trim, drop unsupported and empty entries, and keep the first of a duplicate.'
        );
        $assert(PortfolioRepository::normalizeVideoUrls([]) === [], 'An empty list must normalise to an empty list.');
        $assert(!PortfolioRepository::isSupportedVideoUrl(''), 'An empty string is not a supported video URL.');
        $assert(!PortfolioRepository::isSupportedVideoUrl('https://example.com/movie.mp4'), 'Only YouTube and Vimeo are supported.');
        $assert(PortfolioRepository::isSupportedVideoUrl('https://vimeo.com/video/987654'), 'A vimeo.com/video/<id> URL is supported.');
    });

    $test('the admin form posts an ordered list and the controller stores it before publishing', function () use ($assert, $root): void {
        $form = (string) file_get_contents($root . '/admin/views/portfolio/form.php');
        $assert(str_contains($form, 'name="video_urls[]"'), 'The portfolio form must post video_urls[].');
        $assert(!str_contains($form, 'name="video_url"'), 'The single video_url input was replaced by the repeatable list.');

        $controller = (string) file_get_contents($root . '/app/Controllers/PortfolioController.php');
        foreach (['store', 'update'] as $action) {
            $start = strpos($controller, 'public function ' . $action . '(');
            $assert($start !== false, "PortfolioController::{$action}() must exist.");
            $next = strpos($controller, "\n    public function ", $start + 1);
            $body = substr($controller, $start, ($next === false ? strlen($controller) : $next) - $start);

            $setVideos = strpos($body, '$repo->setVideos(');
            $publish   = strpos($body, 'publishEntry(');
            $assert($setVideos !== false, "PortfolioController::{$action}() must persist the posted videos.");
            $assert(
                $publish === false || $setVideos < $publish,
                "PortfolioController::{$action}() must store the videos before publishing the entry."
            );
        }
    });

    $test('an entry with one video renders exactly as before, further videos follow it', function () use ($assert, $root): void {
        $templates = [
            '/app/Views/archetypes/portfolio-detail.phtml',
            '/Templates/golden-bash/views/archetypes/portfolio-detail.phtml',
            '/Templates/golden-bash-diagonal/views/archetypes/portfolio-detail.phtml',
            '/Templates/premium-photo/views/archetypes/portfolio-detail.phtml',
        ];
        foreach ($templates as $template) {
            $markup = (string) file_get_contents($root . $template);
            $assert(
                str_contains($markup, "array_slice(\$entry['videos'] ?? [], 1)"),
                "{$template} must render the extra videos after the first one, never instead of it."
            );
        }

        $publishService = (string) file_get_contents($root . '/app/Services/PortfolioPublishService.php');
        $assert(
            str_contains($publishService, '$repo->getVideos((int)$entry[\'id\'])') && str_contains($publishService, 'array_slice($videos, 1)'),
            'The static portfolio export must append the extra videos too.'
        );

        $api = (string) file_get_contents($root . '/app/Controllers/ApiController.php');
        $assert(str_contains($api, "'videos'           => \$repo->getVideos((int)\$e['id']),"), 'The API must expose the full video list.');
        $assert(str_contains($api, "\$repo->setVideos(\$id, \$data['video_urls'])"), 'The API must accept a video_urls list.');
    });

    $test('the mirrored video_url follows the first video through every write', function () use ($assert): void {
        $repo = new PortfolioRepository();
        $site = (new App\Repositories\SiteRepository())->findFirst();
        if (!$site) {
            return; // No database fixture here - the file-level contracts above still ran.
        }

        $id = $repo->create([
            'site_id' => $site['id'],
            'title' => 'Test videa',
            'slug' => 'test-videa-' . bin2hex(random_bytes(4)),
            'description' => '',
            'video_url' => null,
            'content_type' => 'video',
            'status' => 'draft',
        ]);

        try {
            $repo->setVideos($id, ['https://youtu.be/abc123def45', 'https://vimeo.com/123', 'https://example.com/x']);
            $assert(
                $repo->getVideoUrls($id) === ['https://youtu.be/abc123def45', 'https://vimeo.com/123'],
                'setVideos must store the supported URLs in the posted order.'
            );
            $assert(
                ($repo->findById($id)['video_url'] ?? null) === 'https://youtu.be/abc123def45',
                'video_url must mirror the first video.'
            );

            $repo->setVideos($id, ['https://vimeo.com/123', 'https://youtu.be/abc123def45']);
            $assert(
                ($repo->findById($id)['video_url'] ?? null) === 'https://vimeo.com/123',
                'Reordering the list must move the mirror with it.'
            );

            $repo->setVideos($id, []);
            $assert($repo->getVideoUrls($id) === [], 'Clearing the list must leave no videos.');
            $assert(($repo->findById($id)['video_url'] ?? null) === null, 'Clearing the list must null the mirror.');
        } finally {
            $repo->delete($id);
        }
    });
}
