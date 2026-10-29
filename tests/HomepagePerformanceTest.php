<?php

declare(strict_types=1);

use App\Services\Render\GoldenBashAboutRenderer;
use App\Services\Render\ResponsiveImageMarkupService;

function register_homepage_performance_tests(callable $test, callable $assert): void
{
    $test('gallery JSON and module scripts remain inert or typed during asset extraction', static function () use ($assert): void {
        $data = '<script type="application/json" class="pt-photos-data">[{"src":"/one.webp"}]</script>';
        $ld = '<script type="application/ld+json">{"@type":"WebPage"}</script>';
        $module = '<script type="module">export const value = 1;</script>';
        $unquoted = '<script type=application/json>[]</script>';
        $html = '<html><head></head><body>' . $data . $ld . $module . $unquoted . '</body></html>';
        $assert(\App\Services\Render\MarkupNormalizer::extractInlineBodyScripts($html) === $html, 'Data and modules must retain their exact HTML and must never become classic JS.');
        $mixed = str_replace('</body>', '<script>window.performanceFixture = 1;</script></body>', $html);
        $result = \App\Services\Render\MarkupNormalizer::extractInlineBodyScripts($mixed);
        foreach ([$data, $ld, $module, $unquoted] as $tag) $assert(str_contains($result, $tag));
        $assert((bool)preg_match('~src="(/assets/generated/inline-body-scripts-[a-f0-9]+\.js)"~', $result, $match), 'Actual JavaScript must still be externalized.');
        $bundle = file_get_contents(dirname(__DIR__) . '/public' . $match[1]);
        $assert(str_contains($bundle, 'window.performanceFixture') && !str_contains($bundle, '/one.webp') && !str_contains($bundle, 'export const'), 'Only classic JS belongs in the generated asset.');
    });

    $page = static fn(): string => '<html><head></head><body><main>'
        . '<video autoplay muted preload="metadata"><source src="/clip.mp4"></video>'
        . '<img class="hero-bg" src="/poster.webp">'
        . '<img class="jb-about-visual__source" data-src="/team.webp" src="/team.webp" hidden alt="Tým" srcset="/team-sm.webp 480w, /team.webp 720w" sizes="100vw">'
        . '<section id="sluzby" class="section"><div class="container"><p class="section-label">DB label</p>'
        . '<h2 class="section-title">DB title</h2><p class="section-intro">DB description</p>'
        . '<div class="hp-services-grid"><article><a href="/foto">DB card</a></article></div>'
        . '<div class="hp-section-cta"><a href="/o-nas">DB CTA</a></div></div></section>'
        . '</main></body></html>';

    $test('homepage photograph is visible in its final server layout before JavaScript', static function () use ($page, $assert): void {
        foreach (['golden-bash', 'golden-bash-diagonal'] as $theme) {
            $html = GoldenBashAboutRenderer::compose($page(), true, $theme);
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);
            $assert($xpath->query('//section[@id="sluzby"]//figure/img')->length === 1, 'There must be exactly one photograph already inside its final figure.');
            $assert($xpath->query('//section[@id="sluzby"]//figure/img[@hidden]')->length === 0, 'The LCP photograph must not start hidden.');
            $assert($xpath->query('//section[@id="sluzby"]//div[@class="gb-services-copy"]//article/a[@href="/foto"]')->length === 1, 'DB cards and links must be preserved in the copy column.');
            foreach (['DB label', 'DB title', 'DB description', 'DB CTA'] as $text) $assert(str_contains($html, $text), 'DB content must not be replaced by template copy.');
            $assert(GoldenBashAboutRenderer::compose($html, true, $theme) === $html, 'Composition must be idempotent.');
            preg_match('~<video.*?</video>~s', $page(), $before);
            preg_match('~<video.*?</video>~s', $html, $after);
            $assert($before[0] === $after[0], 'Video markup must stay byte-identical.');
        }
    });
    $test('homepage performance composition respects theme page and editor boundaries', static function () use ($page, $assert): void {
        $assert(GoldenBashAboutRenderer::compose($page(), false, 'golden-bash') === $page());
        $assert(GoldenBashAboutRenderer::compose($page(), true, 'etherna') === $page());
        $assert(GoldenBashAboutRenderer::compose($page(), true, 'golden-bash', true) === $page());
        $invalid = str_replace('class="container"', 'class="missing"', $page());
        $threw = false;
        try { GoldenBashAboutRenderer::compose($invalid, true, 'golden-bash'); }
        catch (RuntimeException) { $threw = true; }
        $assert($threw, 'A malformed known layout must fail visibly instead of silently dropping data.');
    });
    $test('mobile image preload matches responsive candidates and leaves desktop hero priority intact', static function () use ($page, $assert): void {
        $html = ResponsiveImageMarkupService::optimize(GoldenBashAboutRenderer::compose($page(), true, 'golden-bash-diagonal'), 0);
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        $image = $xpath->query('//img[@data-mobile-lcp]')->item(0);
        $preload = $xpath->query('//head/link[@data-mobile-lcp-preload]')->item(0);
        $assert($image instanceof DOMElement && $image->getAttribute('loading') === 'eager', 'Photograph must not wait for lazy loading.');
        $assert($preload instanceof DOMElement && $preload->getAttribute('media') === '(max-width: 700px)', 'High-priority preload must be mobile-only.');
        $assert($preload->getAttribute('fetchpriority') === 'high');
        $assert($preload->getAttribute('href') === $image->getAttribute('src'));
        $assert($preload->getAttribute('imagesrcset') === $image->getAttribute('srcset'));
        $assert($preload->getAttribute('imagesizes') === $image->getAttribute('sizes'));
        $assert($xpath->query('//img[@class="hero-bg"][@fetchpriority="high"]')->length === 1, 'Desktop hero keeps its own priority.');
        $twice = ResponsiveImageMarkupService::optimize($html, 0);
        $assert(substr_count($twice, 'data-mobile-lcp-preload') === 1, 'Repeated normalization must not duplicate preloads.');
        $ordinary = ResponsiveImageMarkupService::optimize($page(), 0);
        $assert(!str_contains($ordinary, 'data-mobile-lcp-preload'), 'Unmarked pages must not get an unrelated preload.');
    });
}
