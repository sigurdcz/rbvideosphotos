<?php

declare(strict_types=1);

/*
 * Post-refactor: activation of the theme override cascade for content
 * archetypes (faq, sluzby, cenik, kontakt, blog, portfolio, homepage
 * partials, reviews). Before this change every tryServe*Page()/PublishService
 * archetype call hardcoded ViewFactory::forTheme(null, ...), which forced
 * core-only resolution and made Templates/<theme>/views/archetypes/*.phtml
 * unreachable even though the ViewResolver cascade (Phase 1) already
 * supported it. These tests prove the cascade is now actually exercised:
 * a theme-level override file wins over the core default, and a theme
 * without one falls back to core exactly as before. DB-free.
 *
 * A throwaway fixture theme is written under the REAL Templates/ directory
 * (the only place ViewFactory resolves theme views from) and removed in a
 * finally block, same pattern as ManifestV2Test.php.
 */

use App\View\ViewFactory;

function register_archetype_theme_override_tests(callable $test, callable $assert): void
{
    $themesRoot = ViewFactory::base() . '/Templates';
    $key = 'ato-fixture';
    $dir = $themesRoot . '/' . $key;

    $faqData = [
        'hero' => ['title' => 'T', 'sub' => 'S', 'titleKey' => 'hero_h1', 'titleType' => 'text', 'subKey' => 'hero_sub', 'subType' => 'text'],
        'items' => [['id' => 1, 'question' => 'Q1', 'answer' => 'A1']],
    ];

    $writeFixture = static function () use ($dir): void {
        @mkdir($dir . '/views/archetypes', 0775, true);
        file_put_contents(
            $dir . '/views/archetypes/faq.phtml',
            '<div class="ato-fixture-override">OVERRIDE:<?= $this->e($hero["title"]) ?></div>'
        );
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

    $test('archetype theme override: theme-level archetype file wins over core', function () use ($assert, $writeFixture, $removeFixture, $key, $faqData): void {
        $writeFixture();
        try {
            $html = ViewFactory::forTheme($key)->render('archetypes/faq', $faqData);
            $assert(str_contains($html, 'ato-fixture-override'), 'theme override file was not used: ' . substr($html, 0, 200));
            $assert(str_contains($html, 'OVERRIDE:T'), 'override data contract did not receive hero title');
        } finally {
            $removeFixture();
        }
    });

    $test('archetype theme override: theme without an override file falls back to core', function () use ($assert, $faqData): void {
        // A syntactically valid theme key that has no Templates/<key>/ directory
        // at all must still resolve — ViewResolver drops the missing theme root
        // and falls back to app/Views/archetypes/faq.phtml (Phase 1 behaviour).
        $html = ViewFactory::forTheme('no-such-theme-at-all')->render('archetypes/faq', $faqData);
        $assert(!str_contains($html, 'ato-fixture-override'), 'unrelated theme leaked a stale override');
        $assert(str_contains($html, 'Q1'), 'core faq archetype did not render: ' . substr($html, 0, 200));
    });

    $test('archetype theme override: null theme key resolves core-only (pre-refactor behaviour preserved)', function () use ($assert, $faqData): void {
        $html = ViewFactory::forTheme(null)->render('archetypes/faq', $faqData);
        $assert(str_contains($html, 'Q1'), 'core faq archetype did not render with null theme key');
    });
}
