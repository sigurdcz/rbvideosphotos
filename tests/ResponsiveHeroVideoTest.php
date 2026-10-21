<?php

declare(strict_types=1);

use App\Services\Render\HeroMediaRenderer;

/**
 * The hero video has an independent source per screen width: desktop, tablet, phone.
 *
 * The contract these tests protect: an empty field means no video at that width - nothing
 * is inherited from another width or from a site-wide default - a width is emitted only
 * when it resolves to the same kind of source as the first filled one, and the width is
 * decided in the browser, never on the server, because one published HTML file is served
 * to every visitor.
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

    $test('a desktop-only video plays on desktop only', function () use ($assert, $vimeo, $youtube): void {
        $embed = HeroMediaRenderer::buildHeroVideoHtml($vimeo, '/media/poster.webp', true, '', '');
        $assert(str_contains($embed, 'data-embed-url="https://player.vimeo.com/video/1133996815'), 'Desktop embed must render.');
        $assert(!str_contains($embed, 'data-embed-url-tablet') && !str_contains($embed, 'data-embed-url-mobile'), 'Empty widths must not be emitted.');

        $native = HeroMediaRenderer::buildHeroVideoHtml('https://example.com/hero.mp4', '/media/poster.webp', true, '', '');
        $assert(str_contains($native, '<source media="(min-width: 1050.02px)" src="https://example.com/hero.mp4">'), 'A file video is limited to desktop widths.');
        $assert(!str_contains($native, 'max-width: 700px') && !str_contains($native, 'max-width: 1050px'), 'No source may cover the widths whose field is empty.');
        $assert(str_contains($native, 'poster="/media/poster.webp"'), 'The poster stays for widths without a video.');
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
        $assert(str_contains($native, '<source media="(min-width: 700.02px) and (max-width: 1050px)" src="https://example.com/b.mp4">'), 'The tablet source covers only the tablet widths.');
        $assert(str_contains($native, '<source media="(min-width: 1050.02px)" src="https://example.com/a.mp4">'), 'The desktop source covers only the desktop widths.');
        $assert(
            strpos($native, 'max-width: 700px') < strpos($native, 'min-width: 700.02px')
                && strpos($native, 'min-width: 700.02px') < strpos($native, 'min-width: 1050.02px'),
            'Sources are ordered mobile, tablet, desktop.'
        );
    });

    $test('a Vimeo/YouTube width and a file width can live in one set', function () use ($assert, $vimeo): void {
        // The real case: Vimeo on desktop and tablet, an uploaded file on the phone.
        $mixed = HeroMediaRenderer::buildHeroVideoHtml($vimeo, '', false, $vimeo, '/media/2026/10/m.webm');
        $assert(str_contains($mixed, 'data-embed-url="https://player.vimeo.com/video/1133996815'), 'The embed keeps its widths.');
        $assert(str_contains($mixed, '<source media="(max-width: 700px)" src="/media/2026/10/m.webm">'), 'The file keeps the phone width.');
        $assert(str_contains($mixed, 'data-hero-widths="desktop tablet"') && str_contains($mixed, 'data-hero-widths="mobile"'), 'Each player is tagged with the widths it serves.');
        $assert(str_contains($mixed, '<style data-hero-video-widths>'), 'A mixed set needs the stylesheet that shows one player per width.');
        $assert(!str_contains($mixed, 'data-embed-url-mobile'), 'The phone must not also get an embed.');

        $reverse = HeroMediaRenderer::buildHeroVideoHtml('https://example.com/hero.mp4', '', false, $vimeo, '');
        $assert(str_contains($reverse, 'data-embed-url-tablet=') && str_contains($reverse, '<source media="(min-width: 1050.02px)" src="https://example.com/hero.mp4">'), 'A file on desktop and a player on tablet coexist.');

        $single = HeroMediaRenderer::buildHeroVideoHtml($vimeo, '', false, $vimeo, '');
        $assert(!str_contains($single, 'data-hero-widths') && !str_contains($single, '<style'), 'A set of one kind keeps the plain markup.');

        $junk = HeroMediaRenderer::buildHeroVideoHtml($vimeo, '', false, '', 'https://example.com/nonsense');
        $assert(!str_contains($junk, 'data-embed-url-mobile') && !str_contains($junk, '<source'), 'An unrecognised address must be dropped, not passed through.');

        $assert(HeroMediaRenderer::buildResponsiveHeroVideoHtml(['desktop' => '', 'tablet' => '', 'mobile' => '']) === '', 'Without any video nothing renders.');
        $tabletOnly = HeroMediaRenderer::buildResponsiveHeroVideoHtml(['desktop' => '', 'tablet' => $vimeo]);
        $assert(
            str_contains($tabletOnly, 'data-embed-url-tablet=') && !str_contains($tabletOnly, 'data-embed-url="'),
            'A tablet-only video must not appear on desktop.'
        );
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

    $test('nothing is inherited between widths inside a set', function () use ($assert, $root): void {
        $own = HeroMediaRenderer::pickHeroVideoUrls('page.mp4', 'page-t.mp4', '');
        $assert($own === ['desktop' => 'page.mp4', 'tablet' => 'page-t.mp4', 'mobile' => ''], 'An empty mobile field stays empty.');

        $mobileOnly = HeroMediaRenderer::pickHeroVideoUrls('', '', 'page-m.mp4');
        $assert($mobileOnly['desktop'] === '' && $mobileOnly['tablet'] === '', 'A mobile video must not fill the other widths.');

        $blank = HeroMediaRenderer::pickHeroVideoUrls('   ', "\t", '');
        $assert($blank === ['desktop' => '', 'tablet' => '', 'mobile' => ''], 'Whitespace is not a video.');
        $assert(HeroMediaRenderer::hasHeroVideo($blank) === false, 'Empty fields mean there is no hero video.');
        $assert(HeroMediaRenderer::hasHeroVideo($mobileOnly) === true, 'A single filled width is a hero video.');
        $assert(HeroMediaRenderer::hasHeroVideo(['desktop' => 'https://example.com/nonsense']) === false, 'An unrecognised address is not a video.');

        // Both injectors resolve the page's own set and the Settings set through that one function,
        // page first; a set is taken whole, so a width of one set never fills a gap in the other.
        foreach (['/public/index.php', '/app/Services/PublishService.php'] as $file) {
            $source = (string) file_get_contents($root . $file);
            $assert(substr_count($source, 'pickHeroVideoUrls(') >= 2, "{$file} must resolve both sets through the shared rule.");
            $assert(str_contains($source, 'default_hero_video_url_tablet') && str_contains($source, 'default_hero_video_url_mobile'), "{$file} must read the Settings set.");
            $assert(str_contains($source, 'hasHeroVideo($pageUrls)') && str_contains($source, 'hasHeroVideo($siteUrls)'), "{$file} must pick a whole set, never mix widths of two sets.");
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

    // The browser never falls back to another width's video either.
    $test('the browser has no fallback between widths, and seeds and migration 100 still hold', function () use ($assert, $root): void {
        $desktop = 'https://vimeo.com/1229273025';
        $mobile = 'https://vimeo.com/1228952734';

        $runtime = (string) file_get_contents($root . '/public/assets/frontend-runtime.js');
        $assert(str_contains($runtime, "return facade.getAttribute('data-embed-url-tablet') || '';"), 'Without a tablet attribute pickEmbedUrl() must return nothing.');
        $assert(str_contains($runtime, "return facade.getAttribute('data-embed-url-mobile') || '';"), 'Without a mobile attribute pickEmbedUrl() must return nothing.');
        $assert(!str_contains($runtime, "|| desktop;"), 'No width may fall back to the desktop video.');

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
