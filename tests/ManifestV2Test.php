<?php

declare(strict_types=1);

/*
 * Phase 6 — theme manifest v2 tests (TEMPLATE_REFACTOR_PLAN.md 2.6).
 *
 * Exercises the opt-in PHTML views cascade: template.json "views_dir" activation
 * plus per-page content overrides ("overrides.<pageKey>.view"), through
 * App\Services\ThemeTemplateRegistry and App\View\ViewFactory. DB-free.
 *
 * A throwaway fixture theme is written under the REAL Templates/ directory (the
 * only place ViewFactory resolves theme views from) and removed in a finally
 * block. Its key starts with a letter so it passes the strict theme-key pattern;
 * its version keeps it out of the default/active-theme resolution path.
 */

use App\Services\ThemeTemplateRegistry;
use App\View\ViewFactory;

function register_manifest_v2_tests(callable $test, callable $assert): void
{
    $themesRoot = ViewFactory::base() . '/Templates';
    $key = 'mv2-fixture';
    $dir = $themesRoot . '/' . $key;

    $writeFixture = static function () use ($dir): void {
        @mkdir($dir . '/views/pages', 0775, true);
        file_put_contents($dir . '/template.json', json_encode([
            'key' => 'mv2-fixture',
            'name' => 'Manifest V2 Fixture',
            'version' => '9.9.9',
            'views_dir' => 'views',
            'overrides' => [
                // explicit logical-name override
                'cenik' => ['view' => 'pages/custom-cenik'],
                // manifest path-form override (must be normalized to a logical name)
                'sluzby' => ['view' => 'views/pages/custom-cenik.phtml'],
                // empty override → falls back to <views_dir>/pages/<pageKey>.phtml
                'podminky-uziti' => ['view' => ''],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        file_put_contents(
            $dir . '/views/pages/custom-cenik.phtml',
            'MV2-CENIK:<?= $this->e($pageKey) ?>:<?= $this->raw($fragment) ?>'
        );
        file_put_contents($dir . '/views/pages/podminky-uziti.phtml', 'MV2-PODMINKY');
    };

    $removeFixture = static function () use ($dir): void {
        if (!is_dir($dir)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    };

    $test('manifest v2: views_dir + per-page content overrides resolve', function () use ($assert, $writeFixture, $removeFixture, $key): void {
        $writeFixture();
        try {
            $registry = new ThemeTemplateRegistry();
            $theme = $registry->find($key);
            $assert($theme !== null, 'fixture theme not discovered');

            // Parsing + accessors.
            $assert($registry->getViewsDir($theme) === 'views', 'views_dir not parsed');
            $assert($registry->hasViewsCascade($theme) === true, 'views cascade should be active');
            $assert($registry->getPageViewOverride($theme, 'cenik') === 'pages/custom-cenik', 'cenik view override not parsed');

            // Explicit logical-name override resolves.
            $cenik = $registry->pageContentView($theme, 'cenik');
            $assert($cenik !== null, 'cenik content view not resolved');
            $assert($cenik['themeKey'] === $key && $cenik['view'] === 'pages/custom-cenik' && $cenik['viewsDir'] === 'views', 'cenik content view payload wrong: ' . json_encode($cenik));

            // Manifest path-form override is normalized to a logical name.
            $sluzby = $registry->pageContentView($theme, 'sluzby');
            $assert($sluzby !== null && $sluzby['view'] === 'pages/custom-cenik', 'path-form override not normalized: ' . json_encode($sluzby));

            // Empty override → implicit <views_dir>/pages/<pageKey>.phtml.
            $podminky = $registry->pageContentView($theme, 'podminky-uziti');
            $assert($podminky !== null && $podminky['view'] === 'pages/podminky-uziti', 'implicit page view not resolved: ' . json_encode($podminky));

            // No override and no file → null (falls back to core archetype).
            $assert($registry->pageContentView($theme, 'no-such-page') === null, 'missing page must resolve to null');

            // The resolved view actually renders through the theme cascade.
            $out = ViewFactory::forTheme($cenik['themeKey'], [], $cenik['viewsDir'])
                ->render($cenik['view'], ['fragment' => '<b>F</b>', 'pageKey' => 'cenik']);
            $assert($out === 'MV2-CENIK:cenik:<b>F</b>', 'override view render mismatch: ' . $out);
        } finally {
            $removeFixture();
        }
    });

    $test('manifest v2: themeViewPath containment + custom views_dir root', function () use ($assert, $writeFixture, $removeFixture, $key): void {
        $writeFixture();
        try {
            $assert(ViewFactory::themeViewPath($key, 'pages/custom-cenik', 'views') !== null, 'existing view must resolve');
            $assert(ViewFactory::themeViewPath($key, 'pages/no-such', 'views') === null, 'missing view must be null');
            $assert(ViewFactory::themeViewPath($key, '../../evil', 'views') === null, 'traversal must be rejected');
            $assert(ViewFactory::themeViewPath('../evil', 'pages/custom-cenik', 'views') === null, 'invalid theme key must be null');

            $roots = ViewFactory::roots($key, 'custom');
            $assert(str_contains($roots[0], 'Templates/mv2-fixture/custom') || str_contains($roots[0], 'Templates\\mv2-fixture\\custom'), 'custom views_dir root not honored: ' . $roots[0]);
            // Invalid views_dir falls back to the default "views".
            $rootsBad = ViewFactory::roots($key, '../evil');
            $assert(str_contains($rootsBad[0], 'mv2-fixture/views') || str_contains($rootsBad[0], 'mv2-fixture\\views'), 'invalid views_dir must fall back to default: ' . $rootsBad[0]);
        } finally {
            $removeFixture();
        }
    });

    $test('manifest v2: backward compatibility — legacy theme has no cascade', function () use ($assert): void {
        $registry = new ThemeTemplateRegistry();
        // elegant-white3 is the active theme and has NOT adopted manifest v2.
        $theme = $registry->find('elegant-white3');
        $assert($theme !== null, 'elegant-white3 must exist');
        $assert($registry->getViewsDir($theme) === '', 'legacy theme must report no views_dir');
        $assert($registry->hasViewsCascade($theme) === false, 'legacy theme must not activate the cascade');
        $assert($registry->pageContentView($theme, 'cenik') === null, 'legacy theme must resolve to null (core archetype)');
        $assert($registry->pageContentView(null, 'cenik') === null, 'null theme must resolve to null');
    });
}
