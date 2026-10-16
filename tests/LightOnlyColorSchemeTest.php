<?php

declare(strict_types=1);

use App\Services\Render\MarkupNormalizer;

/**
 * The public site is light-only. Phone browsers (Chrome Android Auto Dark, Samsung
 * Internet, Opera) may otherwise repaint it dark, and a prefers-color-scheme: dark rule
 * would switch it on any device with a dark system theme.
 *
 * Contract: every public page - live and publish, whatever the theme layout - carries
 * the color-scheme meta exactly once (injected centrally, not per template), the
 * critical CSS declares color-scheme light from the first paint, and no public CSS/JS
 * source contains a prefers-color-scheme: dark branch. The admin has its own look and
 * is deliberately out of scope.
 */
function register_light_only_color_scheme_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);
    $countMeta = static fn(string $html): int => preg_match_all('~<meta\b[^>]*\bname=(["\']?)color-scheme\1[\s>/]~i', $html);

    $test('light-only meta goes right after charset, once, idempotently', function () use ($assert, $countMeta): void {
        $html = "<!doctype html><html><head>\n<meta charset=\"utf-8\">\n<title>x</title></head><body></body></html>";
        $once = MarkupNormalizer::injectLightOnlyColorScheme($html);
        $twice = MarkupNormalizer::injectLightOnlyColorScheme($once);
        $assert($once === $twice, 'A second pass must not change the markup.');
        $assert($countMeta($twice) === 1, 'color-scheme meta must appear exactly once.');
        $assert(
            str_contains($once, "<meta charset=\"utf-8\">\n" . MarkupNormalizer::LIGHT_ONLY_META),
            'The meta must follow the charset meta so it is parsed before any stylesheet.'
        );
        $assert(str_contains($once, 'content="light only"'), 'The value must be "light only".');
        $assert(str_contains($once, '<meta name="supported-color-schemes" content="light">'), 'Legacy supported-color-schemes meta is missing.');

        $noCharset = MarkupNormalizer::injectLightOnlyColorScheme('<html><head><title>x</title></head></html>');
        $assert(str_contains($noCharset, '<head>' . "\n" . MarkupNormalizer::LIGHT_ONLY_META), 'Without charset the meta goes right after <head>.');

        $existing = '<html><head><meta charset="utf-8"><meta name="color-scheme" content="light"></head></html>';
        $assert(MarkupNormalizer::injectLightOnlyColorScheme($existing) === $existing, 'An existing color-scheme meta must not be duplicated.');
    });

    $test('shared frontend assets (live + publish path) carry the meta once', function () use ($assert, $countMeta): void {
        $html = "<!doctype html><html><head><meta charset=\"utf-8\"><title>x</title></head><body><main></main></body></html>";
        $out = MarkupNormalizer::injectSharedFrontendAssets(MarkupNormalizer::injectSharedFrontendAssets($html));
        $assert($countMeta($out) === 1, 'injectSharedFrontendAssets must add exactly one color-scheme meta.');
    });

    $test('portfolio detail and 404 publish pages get the meta too', function () use ($assert, $root): void {
        foreach (['/app/Services/PortfolioPublishService.php', '/app/Services/PublishService.php'] as $file) {
            $assert(
                str_contains((string) file_get_contents($root . $file), 'MarkupNormalizer::injectLightOnlyColorScheme('),
                "{$file} writes its own <head> and must inject the light-only meta."
            );
        }
    });

    $test('critical CSS declares color-scheme light from the first paint', function () use ($assert, $root): void {
        $css = (string) file_get_contents($root . '/public/assets/critical-above-fold.css');
        $assert(str_contains($css, ':root{color-scheme:light only}'), 'critical-above-fold.css must declare :root{color-scheme:light only}.');
        $assert(str_contains($css, 'html{color-scheme:light}'), 'critical-above-fold.css must keep the html{color-scheme:light} fallback.');
    });

    $test('no public CSS/JS source switches to a dark scheme', function () use ($assert, $root): void {
        $files = array_merge(
            glob($root . '/public/assets/*.{css,js}', GLOB_BRACE) ?: [],
            glob($root . '/Templates/*/{layout.html,assets/*.css,assets/*.js}', GLOB_BRACE) ?: [],
            [$root . '/public/index.php']
        );
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app/Views', FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $files[] = $file->getPathname();
        }
        foreach (glob($root . '/Templates/*/views', GLOB_ONLYDIR) ?: [] as $viewsDir) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsDir, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $files[] = $file->getPathname();
            }
        }
        $assert(count($files) > 10, 'The public source scan found suspiciously few files.');
        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            $assert(
                !preg_match('~prefers-color-scheme\s*:\s*dark~i', $source),
                'Public source must not contain prefers-color-scheme: dark - ' . substr($file, strlen($root))
            );
        }
    });

    $test('every golden page carries the color-scheme meta exactly once', function () use ($assert, $root, $countMeta): void {
        $goldens = glob($root . '/tests/goldens/{live,publish}/*.html', GLOB_BRACE) ?: [];
        foreach ($goldens as $file) {
            $assert($countMeta((string) file_get_contents($file)) === 1, 'Golden must contain the color-scheme meta once: ' . substr($file, strlen($root)));
        }
    });
}
