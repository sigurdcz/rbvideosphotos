<?php

declare(strict_types=1);

use App\Services\PageRenderer;
use App\Services\Render\HeroMediaRenderer;
use App\Services\Render\ThemeLayoutRenderer;
use App\View\ViewFactory;

/**
 * Phase 3 unit tests: layout "chrome" moved to PHTML partials.
 *
 * These run without a database (they render partials from explicit data and
 * compare the PHTML layout against PageRenderer::applyLayout for the active
 * theme), so they count in the base `php tests/run.php` suite alongside the
 * DB-backed golden checks.
 *
 * Register into the runner:
 *     require __DIR__ . '/Phase3PartialsTest.php';
 *     register_phase3_partials_tests($test, $assert);
 */
function register_phase3_partials_tests(callable $test, callable $assert): void
{
    $engine = ViewFactory::forTheme(null);

    // hero.phtml — image markup, empty when no URL, escaping.
    $test('phase3 partial: hero image markup + empty + escaping', function () use ($assert, $engine): void {
        $assert(
            $engine->render('partials/hero', ['imageUrl' => '/media/a.webp'])
                === '<div class="theme-hero-video theme-hero-video--image" aria-hidden="true" style="background-image:url(\'/media/a.webp\')"></div>',
            'hero image markup mismatch'
        );
        $assert($engine->render('partials/hero', ['imageUrl' => '']) === '', 'empty hero must render nothing');
        // Byte-identical to the canonical builder, including a hostile URL.
        $u = "x'\"<>&";
        $assert(
            $engine->render('partials/hero', ['imageUrl' => $u]) === HeroMediaRenderer::buildHeroImageHtml($u),
            'hero partial diverged from HeroMediaRenderer::buildHeroImageHtml'
        );
    });

    // hero-video.phtml — embed present/absent + poster.
    $test('phase3 partial: hero video markup matches builder', function () use ($assert, $engine): void {
        foreach ([['https://youtu.be/abcdef', ''], ['https://vimeo.com/12345', '/media/p.jpg'], ['', '']] as [$v, $p]) {
            $embed = HeroMediaRenderer::buildVideoEmbedUrl($v);
            $act = $engine->render('partials/hero-video', ['embedUrl' => $embed, 'directUrl' => '', 'posterUrl' => $p]);
            $assert($act === HeroMediaRenderer::buildHeroVideoHtml($v, $p), 'hero-video partial diverged for video=' . $v);
        }

        $youtube = HeroMediaRenderer::buildHeroVideoHtml('https://youtu.be/abcdef', '/media/p.webp');
        $assert(!str_contains($youtube, '<iframe'), 'Initial hero markup must not load a third-party player.');
        $assert(str_contains($youtube, 'data-hero-video-facade'));
        $assert(str_contains($youtube, 'youtube-nocookie.com/embed/abcdef'));
        $assert(str_contains($youtube, 'type="button"') && str_contains($youtube, 'aria-label="Přehrát úvodní video"'));
        $assert(!preg_match('/<div[^>]*data-hero-video-facade[^>]*aria-hidden="true"/i', $youtube), 'Focusable facade control must not be inside aria-hidden content.');
        $native = HeroMediaRenderer::buildHeroVideoHtml('/media/hero.mp4', '/media/p.webp');
        foreach (['<video', 'autoplay', 'muted', 'playsinline', 'loop', 'poster="/media/p.webp"'] as $attribute) {
            $assert(str_contains($native, $attribute), 'Native hero video is missing: ' . $attribute);
        }
    });

    $test('hero video facade autoplays immediately, exempt from marketing consent', function () use ($assert): void {
        $runtime = (string)file_get_contents(dirname(__DIR__) . '/public/assets/frontend-runtime.js');
        $assert(str_contains($runtime, "querySelectorAll('[data-hero-video-facade]')"));
        $assert(str_contains($runtime, "document.createElement('iframe')"));
        $assert(!str_contains($runtime, 'canLoadMarketing()'), 'Hero video autoplay is a deliberate exemption from marketing cookie consent and must not gate on it.');
        $assert(!str_contains($runtime, 'cms-cookie-consent:marketing'), 'Hero video must not wait for a deferred consent event - it always plays on load.');
        $assert(str_contains($runtime, 'facades.forEach(function(facade) {') && str_contains($runtime, 'play(facade);'), 'Every hero video facade must call play() unconditionally on init.');
        $css = (string)file_get_contents(dirname(__DIR__) . '/public/assets/frontend-runtime.css');
        $assert(str_contains($css, '.hero-content{position:relative;z-index:3;pointer-events:none}'));
        $assert(str_contains($css, '.hp-hero>.container a') && str_contains($css, '.hero-content button{pointer-events:auto}'));
    });

    // breadcrumb.phtml — last item is aria-current, others are links; escaping.
    $test('phase3 partial: breadcrumb markup', function () use ($assert, $engine): void {
        $items = [
            ['label' => 'Uvod', 'url' => '/'],
            ['label' => 'Ceník & "x"', 'url' => '/cenik'],
        ];
        $expected = '<nav class="breadcrumbs" aria-label="Drobeckova navigace" data-cms-breadcrumbs><ol class="breadcrumbs__inner">'
            . '<li class="breadcrumbs__item"><a href="/">Uvod</a></li>'
            . '<li class="breadcrumbs__item"><span aria-current="page">Ceník &amp; &quot;x&quot;</span></li>'
            . '</ol></nav>';
        $assert($engine->render('partials/breadcrumb', ['items' => $items]) === $expected, 'breadcrumb markup mismatch');
    });

    // nav.phtml — raw passthrough with legacy {site_name} placeholder resolved (escaped).
    $test('phase3 partial: nav passthrough resolves {site_name}', function () use ($assert, $engine): void {
        $nav = '<nav><a class="brand">{site_name}</a></nav>';
        $out = $engine->render('partials/nav', ['nav' => $nav, 'siteName' => 'A & B']);
        $assert($out === '<nav><a class="brand">A &amp; B</a></nav>', 'nav placeholder substitution mismatch: ' . $out);
        $assert($engine->render('partials/nav', ['nav' => '', 'siteName' => 'X']) === '', 'empty nav must render nothing');
    });

    // logo.phtml — brand text is escaped.
    $test('phase3 partial: logo brand text escaped', function () use ($assert, $engine): void {
        $assert($engine->render('partials/logo', ['siteName' => 'A & <b>']) === 'A &amp; &lt;b&gt;', 'logo escaping mismatch');
    });

    // footer-socials.phtml — items rendered, fallback when empty (icon SVG is raw).
    $test('phase3 partial: footer-socials items + empty fallback', function () use ($assert, $engine): void {
        $socials = [['type' => 'instagram', 'url' => 'https://ig/x', 'label' => 'Instagram', 'iconSvg' => '<svg></svg>']];
        $out = $engine->render('partials/footer-socials', ['socials' => $socials]);
        $assert(
            $out === '<a class="site-footer-social site-footer-social--instagram" href="https://ig/x" target="_blank" rel="noopener" aria-label="Instagram" title="Instagram"><span class="site-footer-social__icon"><svg></svg></span></a>',
            'footer-socials item markup mismatch: ' . $out
        );
        $assert(
            $engine->render('partials/footer-socials', ['socials' => []]) === '<span class="site-footer-brand__note">Doplnte socialni site v kontaktu.</span>',
            'footer-socials empty fallback mismatch'
        );
    });

    // ThemeLayoutRenderer — detection + byte-equivalence with legacy applyLayout.
    $test('phase3 layout: hasPhtmlLayout detects the active theme only', function () use ($assert): void {
        $assert(ThemeLayoutRenderer::hasPhtmlLayout('elegant-white3') === true, 'elegant-white3 should have a PHTML layout');
        $assert(ThemeLayoutRenderer::hasPhtmlLayout('no-such-theme') === false, 'unknown theme must not resolve a PHTML layout');
        $assert(ThemeLayoutRenderer::hasPhtmlLayout('../evil') === false, 'invalid theme key must not resolve a layout');
    });

    $test('phase3 layout: PHTML layout is byte-equivalent to applyLayout(layout.html)', function () use ($assert): void {
        $layoutHtml = (string) file_get_contents(ViewFactory::base() . '/Templates/elegant-white3/layout.html');
        $renderer = new PageRenderer();
        $norm = static fn (string $s): string => str_replace(["\r\n", "\r"], "\n", $s);

        // Content carries no placeholders (DB HTML); nav carries the legacy
        // {site_name} placeholder that applyLayout resolves globally.
        $cases = [
            ['<section>C<style>.x{}</style></section>', 'RB Videos & Photos', "<nav><a class=\"brand\">{site_name}</a>\r\n</nav>"],
            ['', 'Ada & Bob "Q"', ''],
        ];
        foreach ($cases as [$content, $name, $nav]) {
            $expected = $renderer->applyLayout($layoutHtml, $content, $name, $nav);
            $actual = ThemeLayoutRenderer::render('elegant-white3', $content, $name, $nav);
            $assert($norm($expected) === $norm($actual), 'PHTML layout diverged from applyLayout for site="' . $name . '"');
        }
    });

    // ViewFactory — theme root wins over core root; invalid theme key drops the theme root.
    $test('phase3 view factory: root ordering and key validation', function () use ($assert): void {
        $roots = ViewFactory::roots('elegant-white3');
        $assert(count($roots) === 2, 'expected theme + core roots');
        $assert(str_contains($roots[0], 'Templates/elegant-white3/views'), 'theme root must come first');
        $assert(str_ends_with($roots[1], 'app/Views') || str_ends_with($roots[1], 'app\\Views') || str_contains($roots[1], 'app/Views'), 'core root must be app/Views');
        $assert(count(ViewFactory::roots('../evil')) === 1, 'invalid theme key must drop the theme root');
    });
}
