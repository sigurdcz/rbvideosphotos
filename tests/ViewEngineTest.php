<?php

declare(strict_types=1);

use App\Services\PageRenderer;
use App\View\LegacyLayoutView;
use App\View\ViewEngine;
use App\View\ViewResolver;

/**
 * Phase 1 unit tests for the PHTML view engine core (App\View\*).
 *
 * Register into an existing runner:
 *     require __DIR__ . '/ViewEngineTest.php';
 *     register_view_engine_tests($test, $assert);
 *
 * Or run standalone:
 *     php tests/ViewEngineTest.php
 *
 * Fixtures live in tests/fixtures/views/{core,theme}/… so that app/Views/ stays
 * empty in Phase 1 (real archetypes arrive in phases 4–5).
 */
function register_view_engine_tests(callable $test, callable $assert): void
{
    $coreRoot = __DIR__ . '/fixtures/views/core';
    $themeRoot = __DIR__ . '/fixtures/views/theme';

    $engine = static function (array $options = [], ?array $roots = null) use ($coreRoot, $themeRoot): ViewEngine {
        return new ViewEngine(new ViewResolver($roots ?? [$themeRoot, $coreRoot]), $options);
    };

    // 1. Escaping: e() escapes <, >, ", ', &; raw() emits verbatim.
    $test('view engine: e() escapes and raw() does not', function () use ($assert, $engine): void {
        $out = $engine()->render('partials/escape', ['v' => '<script>"x"&\'y\'']);
        $assert(
            str_contains($out, 'E:&lt;script&gt;&quot;x&quot;&amp;&#039;y&#039;'),
            'e() failed to escape special characters: ' . $out
        );
        $assert(
            str_contains($out, 'R:<script>"x"&\'y\''),
            'raw() must emit the value unescaped: ' . $out
        );
    });

    // 2. Cascade resolution: theme override wins; core fallback when theme lacks it.
    $test('view engine: theme override wins, core fallback works', function () use ($assert, $engine, $coreRoot, $themeRoot): void {
        $both = $engine([], [$themeRoot, $coreRoot]);
        $assert($both->render('archetypes/fixture', ['msg' => 'hi']) === 'THEME:hi', 'Theme override did not win.');

        $coreOnly = $engine([], [$coreRoot]);
        $assert($coreOnly->render('archetypes/fixture', ['msg' => 'hi']) === 'CORE:hi', 'Core fallback did not render.');

        // A partial that exists only in core still resolves through the theme→core cascade.
        $assert(str_contains($both->render('partials/inner', ['name' => 'z']), '(inner:z)'), 'Core-only partial did not resolve via cascade.');
    });

    // 3. Path traversal / invalid names must be rejected — never resolve outside a root.
    $test('view engine: resolver rejects traversal and invalid names', function () use ($assert, $coreRoot, $themeRoot): void {
        $resolver = new ViewResolver([$themeRoot, $coreRoot]);
        $malicious = [
            '../../../etc/passwd',              // parent traversal
            '..\\..\\..\\windows\\win.ini',     // backslash traversal
            '/etc/passwd',                      // absolute (leading slash)
            'C:/Windows/win.ini',               // absolute windows (uppercase + colon)
            'partials/../../secret',            // mid-path traversal
            "partials/inner\0.phtml",           // null byte
            "abc\ndef",                         // newline injection
            'UpperCase',                        // uppercase not allowed
            'foo bar',                          // space not allowed
            'foo%2e%2e/bar',                    // percent-encoded, % not allowed
            '.hidden',                          // leading dot
            'partials/inner.phtml',             // extension embedded (dot not allowed)
            '',                                 // empty
        ];
        foreach ($malicious as $name) {
            $threw = false;
            try {
                $resolver->resolve($name);
            } catch (\InvalidArgumentException) {
                $threw = true;
            }
            $assert($threw, 'Malicious/invalid view name was NOT rejected: ' . var_export($name, true));
        }

        // A valid but non-existent view resolves to null (not an exception, not a wrong file).
        $assert($resolver->resolve('archetypes/does-not-exist') === null, 'Missing view should resolve to null.');
        // resolveOrFail throws on the same missing view.
        $threw = false;
        try {
            $resolver->resolveOrFail('archetypes/does-not-exist');
        } catch (\InvalidArgumentException) {
            $threw = true;
        }
        $assert($threw, 'resolveOrFail() must throw for a missing view.');

        // A valid, existing view resolves to a path CONTAINED in an allowed root.
        $resolved = $resolver->resolve('partials/inner');
        $assert($resolved !== null, 'Valid view failed to resolve.');
        $realCore = str_replace('\\', '/', (string) realpath($coreRoot));
        $resolvedFs = str_replace('\\', '/', (string) $resolved);
        if (DIRECTORY_SEPARATOR === '\\') {
            $realCore = strtolower($realCore);
            $resolvedFs = strtolower($resolvedFs);
        }
        $assert(str_starts_with($resolvedFs, $realCore . '/'), 'Resolved path escaped the allowed root: ' . $resolved);
    });

    // 4. Nested partial rendering (partial inside a partial).
    $test('view engine: partials render nested', function () use ($assert, $engine): void {
        $out = $engine()->render('partials/outer', ['name' => 'Bob']);
        $assert($out === '[outer:(inner:Bob):end]', 'Nested partial output mismatch: ' . $out);
    });

    // 5. inlineAttr(): empty when editMode is off, correct attributes when on.
    $test('view engine: inlineAttr honours editMode', function () use ($assert, $engine): void {
        $off = $engine(['editMode' => false])->render('partials/inline');
        $assert($off === '<span>x</span>', 'inlineAttr must emit nothing when editMode is off: ' . $off);

        $on = $engine(['editMode' => true])->render('partials/inline');
        $assert(
            $on === '<span data-inline-key="faq:1:question" data-inline-type="text">x</span>',
            'inlineAttr emitted wrong attributes when editMode is on: ' . $on
        );

        // Explicit type is honoured; an empty key emits nothing even in edit mode.
        $typed = $engine(['editMode' => true])->render('partials/inline-typed');
        $assert(
            $typed === '<i data-inline-key="contact:phone_1" data-inline-type="tel"></i>',
            'inlineAttr type/empty-key handling wrong: ' . $typed
        );
    });

    // 6. LegacyLayoutView must match PageRenderer::applyLayout() byte-for-byte.
    $test('view engine: LegacyLayoutView matches PageRenderer::applyLayout', function () use ($assert): void {
        $legacy = new LegacyLayoutView();
        $renderer = new PageRenderer();

        $cases = [
            ['<html>{nav}<main>{page_content}</main><footer>{site_name}</footer></html>', '<p>Body &amp; "stuff"</p>', 'Ateliér "Foto" & <Co>', '<nav>N</nav>'],
            ['{site_name}{site_name}', 'C', 'A & B', ''],
            ['no placeholders at all', 'X', 'Y', 'Z'],
            // Content containing {nav}: applyLayout replaces page_content first, then {nav},
            // so the token inside content is also substituted — the adapter must match that.
            ['{page_content}', "line1\n{nav}\nline2", 'Name', 'NAVHTML'],
        ];
        foreach ($cases as $i => [$layout, $content, $name, $nav]) {
            $a = $legacy->render($layout, $content, $name, $nav);
            $b = $renderer->applyLayout($layout, $content, $name, $nav);
            $assert($a === $b, "LegacyLayoutView diverged from applyLayout() in case #{$i}.");
        }

        // Default $nav parameter parity. NB: applyLayout() runs str_replace('{nav}', $nav)
        // unconditionally, so an empty default $nav REMOVES the {nav} placeholder (the
        // "pass '' to leave unreplaced" doc comment is inaccurate) — the adapter matches this.
        $a = $legacy->render('{page_content}|{nav}', 'c', 'n');
        $b = $renderer->applyLayout('{page_content}|{nav}', 'c', 'n');
        $assert($a === $b, 'Default $nav parameter behaviour diverged.');
        $assert($a === 'c|', 'Default $nav should replace {nav} with empty string, matching applyLayout(): ' . $a);
    });

    // 7. block(): reads content_blocks map with fallback, skips empty entries.
    $test('view engine: block() reads content_blocks with fallback', function () use ($assert, $engine): void {
        $e = $engine(['contentBlocks' => [
            'hero_title' => ['content' => 'Hello', 'type' => 'text'],
            'empty' => ['content' => ''],
        ]]);
        $out = $e->render('partials/block');
        $assert($out === '[Hello]', 'block() fallback resolution wrong: ' . $out);

        // No matching / no non-empty block => empty string.
        $none = $engine(['contentBlocks' => []])->render('partials/block');
        $assert($none === '[]', 'block() should yield empty string when nothing matches: ' . $none);
    });

    // 8. mediaUrl(): normalizes stored paths to root-relative /media/... URLs.
    $test('view engine: mediaUrl normalizes to root-relative /media/', function () use ($assert, $engine): void {
        $e = $engine();
        $assert($e->render('partials/media', ['p' => 'storage/media/a/b.webp']) === '/media/a/b.webp', 'relative storage path');
        $assert($e->render('partials/media', ['p' => '/storage/media/x.jpg']) === '/media/x.jpg', 'absolute storage path');
        $assert($e->render('partials/media', ['p' => '/media/y.png']) === '/media/y.png', 'already-media path');
        $assert($e->render('partials/media', ['p' => 'https://cdn.example.com/storage/media/z.webp']) === '/media/z.webp', 'domain stripped to root-relative');
    });

    // 9. Layout + slots compose (page declares layout, defines a slot; body => content slot).
    $test('view engine: layout and slots compose', function () use ($assert, $engine): void {
        $out = $engine()->render('pages/withlayout', ['title' => 'T']);
        $assert($out === '<html><nav>NAV-T</nav><main>BODY-T</main></html>', 'Layout/slot composition mismatch: ' . $out);
    });
}

// ---------------------------------------------------------------------------
// Standalone runner: `php tests/ViewEngineTest.php`
// ---------------------------------------------------------------------------
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    require dirname(__DIR__) . '/vendor/autoload.php';

    $tests = [];
    $failures = 0;
    $test = static function (string $name, callable $callback) use (&$tests): void {
        $tests[] = [$name, $callback];
    };
    $assert = static function (bool $condition, string $message = 'Assertion failed'): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    register_view_engine_tests($test, $assert);

    foreach ($tests as [$name, $callback]) {
        try {
            $callback();
            echo "PASS {$name}\n";
        } catch (Throwable $e) {
            $failures++;
            echo "FAIL {$name}: {$e->getMessage()}\n";
        }
    }

    echo sprintf("\n%d tests, %d failures\n", count($tests), $failures);
    exit($failures === 0 ? 0 : 1);
}
