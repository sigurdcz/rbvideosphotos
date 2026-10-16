<?php

declare(strict_types=1);

use App\Services\Render\HeroMediaRenderer;

/**
 * The hero video can differ per screen width: desktop, tablet, phone.
 *
 * The contract these tests protect: desktop is the only required one and stays the
 * fallback, a variant is emitted only when it resolves to the same kind of source as
 * the desktop video, and the width is decided in the browser, never on the server,
 * because one published HTML file is served to every visitor. That an unused feature
 * changes nothing is proven by the golden suite passing without regeneration, not here.
 */
function register_responsive_hero_video_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);
    $vimeo = 'https://vimeo.com/1133996815';
    $youtube = 'https://www.youtube.com/watch?v=5ZlVVcIu10U';

    $test('migration 073 adds both variant columns and both runners know it', function () use ($assert, $root): void {
        $migration = (string) file_get_contents($root . '/database/migrations/073_add_responsive_hero_video_urls.sql');
        $assert(str_contains($migration, 'ALTER TABLE `sites`'), 'Migration 073 must alter sites.');
        foreach (['default_hero_video_url_tablet', 'default_hero_video_url_mobile'] as $column) {
            $assert(str_contains($migration, 'ADD COLUMN `' . $column . '`'), "Migration 073 must add {$column}.");
            $assert(
                str_contains((string) file_get_contents($root . '/database/schema.sql'), $column),
                "schema.sql must document {$column}."
            );
            $assert(
                str_contains((string) file_get_contents($root . '/app/Repositories/SiteRepository.php'), "'" . $column . "'"),
                "{$column} must be writable, otherwise the settings form saves nothing."
            );
        }

        // Both runners need the arm, and each must check BOTH columns - a half-applied
        // database would otherwise skip the second one forever.
        foreach (['/database/migrate-mysql.php', '/app/Services/SetupEnvironmentService.php'] as $runner) {
            $source = (string) file_get_contents($root . $runner);
            if ($runner === '/database/migrate-mysql.php') {
                $source .= (string) file_get_contents($root . '/database/migration-schema-state.php');
            }
            $assert(str_contains($source, '073_add_responsive_hero_video_urls.sql'), "{$runner} must know migration 073.");
            $assert(
                (bool) preg_match('~073_add_responsive_hero_video_urls\\.sql.{0,400}?default_hero_video_url_tablet.{0,200}?default_hero_video_url_mobile~s', $source),
                "{$runner} must check both variant columns in the migration 073 arm."
            );
        }
    });

    $test('without variants the markup is exactly what it was before', function () use ($assert, $vimeo, $youtube): void {
        foreach ([$vimeo, $youtube, 'https://example.com/hero.mp4'] as $url) {
            $before = HeroMediaRenderer::buildHeroVideoHtml($url, '/media/poster.webp', true);
            $after = HeroMediaRenderer::buildHeroVideoHtml($url, '/media/poster.webp', true, '', '');
            $assert($before === $after, 'Empty variants must not change the markup for ' . $url);
            $assert(
                !str_contains($after, 'data-embed-url-tablet') && !str_contains($after, '<source media'),
                'No variant may appear when none is set.'
            );
        }
    });

    $test('a filled variant is emitted as its own attribute', function () use ($assert, $vimeo, $youtube): void {
        $html = HeroMediaRenderer::buildHeroVideoHtml($vimeo, '', true, $youtube, $vimeo);
        $assert(str_contains($html, 'data-embed-url="https://player.vimeo.com/video/1133996815'), 'Desktop stays the plain attribute.');
        $assert(str_contains($html, 'data-embed-url-tablet="https://www.youtube-nocookie.com/embed/5ZlVVcIu10U'), 'The tablet variant must be emitted.');
        $assert(str_contains($html, 'data-embed-url-mobile="https://player.vimeo.com/video/1133996815'), 'The mobile variant must be emitted.');

        // A plain file lets the browser pick the source itself: no JavaScript, and no
        // second download after a swap.
        $native = HeroMediaRenderer::buildHeroVideoHtml('https://example.com/a.mp4', '', false, 'https://example.com/b.mp4', 'https://example.com/c.webm');
        $assert(str_contains($native, '<source media="(max-width: 700px)" src="https://example.com/c.webm">'), 'The phone source must come first.');
        $assert(str_contains($native, '<source media="(max-width: 1050px)" src="https://example.com/b.mp4">'), 'The tablet source must follow it.');
        $assert(
            strpos($native, 'max-width: 700px') < strpos($native, 'max-width: 1050px')
                && strpos($native, 'max-width: 1050px') < strpos($native, 'src="https://example.com/a.mp4"'),
            'The browser takes the first matching source, so narrowest must come first.'
        );
    });

    $test('a variant of a different kind than the desktop video is dropped', function () use ($assert, $vimeo): void {
        $mixed = HeroMediaRenderer::buildHeroVideoHtml($vimeo, '', false, 'https://example.com/hero.mp4', 'https://example.com/nonsense');
        $assert(!str_contains($mixed, 'data-embed-url-tablet'), 'A file cannot be a variant of an embedded player.');
        $assert(!str_contains($mixed, 'data-embed-url-mobile'), 'An unrecognised address must be dropped, not passed through.');
        $assert(!str_contains($mixed, '<source media'), 'An embed must not grow file sources.');

        $reverse = HeroMediaRenderer::buildHeroVideoHtml('https://example.com/hero.mp4', '', false, $vimeo, '');
        $assert(!str_contains($reverse, '<source media') && !str_contains($reverse, 'data-embed-url-tablet'), 'A player cannot be a variant of a file.');

        $assert(HeroMediaRenderer::buildResponsiveHeroVideoHtml(['desktop' => '', 'tablet' => $vimeo]) === '', 'Without a desktop video nothing renders.');
    });

    $test('the browser picks the width, and its breakpoints match the theme', function () use ($assert, $root): void {
        $runtime = (string) file_get_contents($root . '/public/assets/frontend-runtime.js');
        $assert(str_contains($runtime, 'function pickEmbedUrl(facade)'), 'The runtime must choose the variant.');
        $assert(str_contains($runtime, 'data-embed-url-mobile'), 'The runtime must read the mobile variant.');
        $assert(str_contains($runtime, 'data-embed-url-tablet'), 'The runtime must read the tablet variant.');
        $assert(str_contains($runtime, 'var embedUrl = pickEmbedUrl(facade);'), 'play() must use the picked URL, not the raw attribute.');

        // The numbers live in CSS as media queries and in JS as plain numbers; nothing can
        // share them without a build step, so this keeps the two copies equal.
        $critical = (string) file_get_contents($root . '/public/assets/critical-above-fold.css');
        preg_match_all('~@media\(max-width:(\d+)px\)~', $critical, $matches);
        $widths = array_map('intval', $matches[1] ?? []);
        foreach ([700, 1050] as $width) {
            $assert(in_array($width, $widths, true), "The theme's {$width}px breakpoint disappeared - update the runtime too.");
        }
        $assert(str_contains($runtime, 'HERO_VIDEO_MOBILE_MAX = 700'), 'The runtime mobile breakpoint must match the theme.');
        $assert(str_contains($runtime, 'HERO_VIDEO_TABLET_MAX = 1050'), 'The runtime tablet breakpoint must match the theme.');
    });

    $test('the variants follow whichever desktop video won', function () use ($assert, $root): void {
        // A page with its own desktop video must not inherit a mobile video belonging to
        // a different one - that is the whole rule, and it lives in one function so the
        // live page and the static export cannot implement it differently.
        $ownVideo = HeroMediaRenderer::pickHeroVideoUrls('page.mp4', 'page-t.mp4', '', 'site.mp4', 'site-t.mp4', 'site-m.mp4');
        $assert(
            $ownVideo === ['desktop' => 'page.mp4', 'tablet' => 'page-t.mp4', 'mobile' => ''],
            "A page's own video must bring only the page's own variants."
        );

        $inherited = HeroMediaRenderer::pickHeroVideoUrls('', 'page-t.mp4', 'page-m.mp4', 'site.mp4', 'site-t.mp4', '');
        $assert(
            $inherited === ['desktop' => 'site.mp4', 'tablet' => 'site-t.mp4', 'mobile' => ''],
            'Falling back to the site video must take the site variants, not stale page ones.'
        );

        $blank = HeroMediaRenderer::pickHeroVideoUrls('   ', 't', 'm', 'site.mp4', '', '');
        $assert($blank['desktop'] === 'site.mp4', 'Whitespace is not a video.');

        // Both injectors must go through that one function.
        foreach (['/public/index.php', '/app/Services/PublishService.php'] as $file) {
            $assert(
                str_contains((string) file_get_contents($root . $file), 'pickHeroVideoUrls('),
                "{$file} must resolve the trio through the shared rule."
            );
        }
    });

    $test('the admin offers both fields and saves them', function () use ($assert, $root): void {
        $settings = (string) file_get_contents($root . '/admin/views/settings/index.php');
        $assert(str_contains($settings, 'name="default_hero_video_url_tablet"'), 'Settings need the tablet field.');
        $assert(str_contains($settings, 'name="default_hero_video_url_mobile"'), 'Settings need the mobile field.');

        $controller = (string) file_get_contents($root . '/app/Controllers/SettingsController.php');
        $assert(str_contains($controller, "'default_hero_video_url_tablet' => trim("), 'Settings must save the tablet URL.');
        $assert(str_contains($controller, "'default_hero_video_url_mobile' => trim("), 'Settings must save the mobile URL.');

        $pageForm = (string) file_get_contents($root . '/admin/views/pages/form.php');
        $assert(str_contains($pageForm, "\$renderBlockField('hero_video_tablet'"), 'A page needs its own tablet field.');
        $assert(str_contains($pageForm, "\$renderBlockField('hero_video_mobile'"), 'A page needs its own mobile field.');

        $pageController = (string) file_get_contents($root . '/app/Controllers/PageController.php');
        $assert(str_contains($pageController, "'hero_video_tablet' =>"), 'The page blocks must be created for the tablet video.');
        $assert(str_contains($pageController, "'hero_video_mobile' =>"), 'The page blocks must be created for the mobile video.');
    });

    // Production config: the tablet plays the desktop video, the phone keeps its own one.
    $test('the tablet falls back to the desktop video in seeds, migration 100 and the browser', function () use ($assert, $root): void {
        $desktop = 'https://vimeo.com/1229273025';
        $mobile = 'https://vimeo.com/1228952734';

        $html = HeroMediaRenderer::buildHeroVideoHtml($desktop, '/media/poster.webp', true, '', $mobile);
        $assert(str_contains($html, 'data-embed-url="https://player.vimeo.com/video/1229273025?'), 'Desktop embed must render.');
        $assert(!str_contains($html, 'data-embed-url-tablet'), 'An empty tablet variant must not be emitted.');
        $assert(str_contains($html, 'data-embed-url-mobile="https://player.vimeo.com/video/1228952734?'), 'The mobile variant stays.');

        $runtime = (string) file_get_contents($root . '/public/assets/frontend-runtime.js');
        $assert(
            str_contains($runtime, "return facade.getAttribute('data-embed-url-tablet') || desktop;"),
            'Without a tablet attribute pickEmbedUrl() must return the desktop URL.'
        );

        $demoSeeder = (string) file_get_contents($root . '/app/Services/DemoContentSeeder.php');
        $assert(str_contains($demoSeeder, "'default_hero_video_url_tablet' => null,"), 'Demo seed must leave the tablet variant empty.');
        $assert(str_contains($demoSeeder, "'default_hero_video_url' => '{$desktop}'"), 'Demo seed keeps the desktop video.');
        $assert(str_contains($demoSeeder, "'default_hero_video_url_mobile' => '{$mobile}'"), 'Demo seed keeps the mobile video.');

        $seed = (string) file_get_contents($root . '/database/seeds/seed.php');
        $assert(
            (bool) preg_match("~'" . preg_quote($desktop, '~') . "',\\s*(?://[^\\n]*\\s*)?null,\\s*'" . preg_quote($mobile, '~') . "',~", $seed),
            'Main seed must bind desktop, NULL tablet and mobile in this order.'
        );

        $migration = (string) file_get_contents($root . '/database/migrations/100_clear_tablet_hero_video_variant.sql');
        $assert(str_contains($migration, 'SET default_hero_video_url_tablet = NULL'), 'Migration 100 must clear the site tablet URL.');
        $assert(str_contains($migration, 'WHERE default_hero_video_url_tablet IS NOT NULL'), 'Migration 100 must be idempotent.');
        $assert(str_contains($migration, "cb.block_key = 'hero_video_tablet'"), 'Migration 100 must clear the homepage tablet block.');
        $assert(!str_contains($migration, 'default_hero_video_url_mobile =') && !str_contains($migration, 'default_hero_video_url ='), 'Migration 100 must not touch desktop or mobile.');
    });
}
