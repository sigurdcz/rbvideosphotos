<?php

declare(strict_types=1);

use App\Services\DemoContentSeeder;

function register_hero_image_quality_tests(callable $test, callable $assert): void
{
    $test('the homepage goldens still contain the hero video and its priority poster', function () use ($assert): void {
        // Regenerating goldens is how the missing hero slipped through: the snapshots
        // faithfully recorded the regression and the suite went green. These assertions
        // fail on re-baselining instead of blessing it.
        foreach (['live', 'publish'] as $path) {
            $golden = (string) file_get_contents(dirname(__DIR__) . '/tests/goldens/' . $path . '/home.normalized.html');
            $assert($golden !== '', 'Missing home golden for ' . $path);
            $assert(str_contains($golden, 'data-hero-video-facade'), 'The homepage lost its hero video facade in the ' . $path . ' golden.');
            $assert(str_contains($golden, 'fetchpriority="high"'), 'The homepage lost its priority LCP poster in the ' . $path . ' golden.');
        }
    });

    $test('an empty hero video element does not pass for a real one', function () use ($assert): void {
        //  matched inside "theme-hero-video--sourceless", so an empty placeholder was
        // reported as an existing hero: the facade was never injected and the homepage
        // shipped without its video and without its fetchpriority=high LCP poster.
        $sourceless = '<video class="hero-video hero-video--sourceless" autoplay muted></video>';
        $assert(!\App\Services\Render\HeroMediaRenderer::hasHeroVideoElement($sourceless), 'A sourceless placeholder must not count as an existing hero video.');

        foreach (['theme-hero-video--sourceless', 'theme-hero-video--facade', 'theme-hero-video-x'] as $lookalike) {
            $assert(
                !\App\Services\Render\HeroMediaRenderer::hasHeroVideoElement('<div class="' . $lookalike . '"></div>'),
                'Only the whole class token counts, not a prefix of another one: ' . $lookalike
            );
        }
        foreach (['theme-hero-video', 'x theme-hero-video', 'theme-hero-video theme-hero-video--embed'] as $real) {
            $assert(
                \App\Services\Render\HeroMediaRenderer::hasHeroVideoElement('<div class="' . $real . '"></div>'),
                'A real hero video must still be recognised: ' . $real
            );
        }
    });

    $root = dirname(__DIR__);

    $test('inner-page hero migration replaces all posterized PNG sources', function () use ($assert, $root): void {
        $migration = (string)file_get_contents(
            $root . '/database/migrations/034_replace_posterized_hero_images.sql'
        );

        foreach (['sluzby', 'cenik', 'faq', 'kontakt', 'nase-prace', 'blog', 'podminky-uziti'] as $route) {
            $assert(str_contains($migration, "WHEN '{$route}' THEN"), 'Missing quality hero mapping for ' . $route);
        }
        $assert(substr_count($migration, '.webp') === 7);
        $assert(!str_contains($migration, '.png'));
    });

    $test('demo seeders keep the high-quality hero mapping after reset', function () use ($assert, $root): void {
        $service = (string)file_get_contents($root . '/app/Services/DemoContentSeeder.php');
        $seed = (string)file_get_contents($root . '/database/seeds/seed.php');
        $legacyNames = [
            "'sluzby' => 'sluzby.png'",
            "'cenik' => 'cenik.png'",
            "'faq' => 'faq.png'",
            "'nase-prace' => 'nase-prace.png'",
            "'blog' => 'blog.png'",
            "'kontakt' => 'kontakt.png'",
            "'podminky-uziti' => 'podminky-uziti.png'",
        ];

        foreach ($legacyNames as $legacy) {
            $assert(!str_contains($service, $legacy), 'Legacy posterized mapping remains: ' . $legacy);
        }
        foreach ([
            'svatba-nevesta-zenich-polibek-kamenna-zed.webp',
            'snubni-prsteny-listy-zapad-slunce-detail.webp',
            'svatba-nevesta-kytice-zapad-slunce-u-vody.webp',
            'zamilovany-par-objeti-zapad-slunce-podzim.webp',
            'par-v-prirode-romanticky-portret-zapad-slunce.webp',
            'koncert-zpevak-mikrofon-cernobila-fotografie.webp',
            'rodinne-foceni-rodina-dite-louka.webp',
        ] as $asset) {
            $assert(str_contains($service, $asset));
            $assert(str_contains($seed, $asset));
            $assert(is_file($root . '/database/seeds/demo_assets/' . $asset));
        }
        $assert(DemoContentSeeder::WORK_LISTING_HERO_ASSET === 'Snímek obrazovky 2026-10-06 132148.png');
        $assert(is_file($root . '/database/seeds/demo_assets/' . DemoContentSeeder::WORK_LISTING_HERO_ASSET));
        $assert(str_contains($service, "'nase-prace' => self::WORK_LISTING_HERO_ASSET"));
        $assert(str_contains($seed, "\$pidPortfolio => 'Snímek obrazovky 2026-10-06 132148.png'"));
    });

    $test('cream hero set is ingested and mapped to all seven requested routes', function () use ($assert, $root): void {
        $service = (string)file_get_contents($root . '/app/Services/DemoContentSeeder.php');
        $seed = (string)file_get_contents($root . '/database/seeds/seed.php');
        $mapping = [
            'faq' => 'hero-faq-cream.png',
            'cenik' => 'hero-cenik-cream.png',
            'kontakt' => 'hero-kontakt-cream.png',
            'blog' => 'hero-blog-cream.png',
            'sluzby' => 'hero-sluzby-cream.png',
            'o-nas' => 'hero-o-nas-cream.png',
            'hodnoceni' => 'hero-reference-cream.png',
        ];

        foreach ($mapping as $route => $asset) {
            $assert(is_file($root . '/database/seeds/demo_assets/' . $asset), 'Missing cream hero source ' . $asset);
            $assert(str_contains($service, "'{$route}' => '{$asset}'"), 'DemoContentSeeder mapping is missing for ' . $route);
            $assert(str_contains($seed, "'{$asset}'"), 'Base seed does not ingest ' . $asset);
        }
        $assert(str_contains($seed, "\$seedMedia['hero-o-nas-cream.png']['url']"));
        $assert(str_contains($seed, "\$seedMedia['hero-reference-cream.png']['url']"));
    });

    $test('supported favicon and logo PNG assets use the demo media ingestion flow', function () use ($assert, $root): void {
        $seed = (string)file_get_contents($root . '/database/seeds/seed.php');
        $assets = [
            'android-chrome-192x192.png',
            'android-chrome-512x512.png',
            'apple-touch-icon.png',
            'favicon-16x16.png',
            'favicon-32x32.png',
            'rb-logo-beige.png',
            'rb-logo-black.png',
            'rb-logo-orange.png',
        ];

        foreach ($assets as $asset) {
            $assert(is_file($root . '/database/seeds/demo_assets/' . $asset), 'Missing demo media source ' . $asset);
            $assert(str_contains($seed, "'{$asset}'"), 'Base seed does not ingest ' . $asset);
        }
        $assert(!str_contains($seed, "seedMediaAsset(\$pdo, \$siteId, 'favicon.ico')"), 'Unsupported ICO must not be presented as a successful MediaIngestionService upload.');
        $assert(!str_contains($seed, "seedMediaAsset(\$pdo, \$siteId, 'site.webmanifest')"), 'Web manifest is not a Media Library image.');
    });

    $test('default hero poster uses a bounded medium derivative in seeds and production repair', function () use ($assert, $root): void {
        $service = (string)file_get_contents($root . '/app/Services/DemoContentSeeder.php');
        $seed = (string)file_get_contents($root . '/database/seeds/seed.php');
        $migration = (string)file_get_contents(
            $root . '/database/migrations/053_use_medium_variant_for_default_hero_poster.sql'
        );

        $assert(str_contains($service, "requiredMediaVariantPathById(\$mediaId, 'medium')"));
        $assert(str_contains($seed, "['medium_url'] ?? ''"));
        $assert(!str_contains($seed, "['svatba-nevesta-zenich-polibek-kamenna-zed.webp']['url'] ?? ''"));
        $assert(str_contains($migration, "mv.format = 'medium'"));
        $assert(str_contains($migration, 'SET s.default_hero_poster_url'));
        $assert(!preg_match('/\b(?:ALTER|CREATE|DROP|TRUNCATE)\b/i', $migration), 'Production hero repair must remain data-only.');
    });

    $test('homepage team visual is HTML-discoverable but never claims the LCP slot', function () use ($assert, $root): void {
        $renderer = (string)file_get_contents($root . '/app/Services/Render/HomepageSectionRenderer.php');
        $assert(str_contains($renderer, '<img class="jb-about-visual__source"'));
        $assert(str_contains($renderer, 'alt="RB Videos &amp; Photos při práci"'));
        $assert(!str_contains($renderer, '<span class="jb-about-visual__source"'));
        // The visual belongs to a section that sits below the fold on desktop. Marking it
        // as the explicit LCP candidate took the single priority slot away from the hero
        // poster, which measurement identifies as the real LCP element on both breakpoints.
        $assert(!str_contains($renderer, 'data-lcp-candidate'), 'Team visual must not claim the priority slot that belongs to the hero poster.');
    });

    $test('header logo loads eagerly without competing for the LCP priority slot', function () use ($assert): void {
        $optimized = \App\Services\Render\ResponsiveImageMarkupService::optimize(
            '<header><img class="site-logo-img" src="/assets/logo.png"></header>'
            . '<main><img class="hero-bg" src="/assets/hero.png"><img class="card" src="/assets/card.png"></main>',
            0
        );
        $assert((bool)preg_match('~<img[^>]*site-logo-img[^>]*loading="eager"~', $optimized), 'Above-the-fold header logo must not be deferred.');
        $assert(!preg_match('~<img[^>]*site-logo-img[^>]*fetchpriority~', $optimized), 'Logo must leave fetchpriority to the LCP image.');
        $assert((bool)preg_match('~<img[^>]*hero-bg[^>]*loading="eager"[^>]*fetchpriority="high"~', $optimized), 'Hero image must remain the priority image.');
        $assert((bool)preg_match('~<img[^>]*class="card"[^>]*loading="lazy"~', $optimized), 'Ordinary below-the-fold images must stay lazy.');
    });
}
