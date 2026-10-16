<?php

declare(strict_types=1);

use App\Services\Render\MarkupNormalizer;
use App\Services\SeoRenderer;

/**
 * Pins how the shared frontend assets reach the page.
 *
 * Goldens are regenerated snapshots, so they accept whatever the code currently emits -
 * including a double injection or a silently dropped script. These assertions pin the
 * behaviour itself, especially the degradation paths that only fire when a file is
 * missing, which is exactly the situation nobody reproduces locally.
 */
function register_frontend_asset_delivery_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);

    $page = static fn (): string =>
        '<html><head><link rel="stylesheet" href="/assets/fonts/fonts.css"></head><body></body></html>';

    $test('the font faces are inlined in place of their blocking link', function () use ($assert, $page): void {
        $html = MarkupNormalizer::injectSharedFrontendAssets($page());

        $assert(!str_contains($html, 'href="/assets/fonts/fonts.css"'), 'The blocking link to fonts.css must be gone.');
        $assert(substr_count($html, 'data-cms-fonts-css') === 1, 'The inlined font block must appear exactly once.');
        $assert(str_contains($html, '@font-face'), 'The inlined block must carry the actual font faces.');
    });

    $test('running the normalizer twice does not inject anything twice', function () use ($assert, $page): void {
        // Live and publish both normalize; a second pass must be a no-op.
        $once = MarkupNormalizer::injectSharedFrontendAssets($page());
        $twice = MarkupNormalizer::injectSharedFrontendAssets($once);

        $assert(substr_count($twice, 'data-cms-fonts-css') === 1, 'Font faces were inlined twice.');
        $assert(substr_count($twice, 'data-cms-frontend-runtime-js') === 1, 'The frontend runtime was injected twice.');
        $assert(substr_count($twice, 'data-cookie-consent-js') === 1, 'The consent script was injected twice.');
    });

    $test('a page without the font link is left alone', function () use ($assert): void {
        $html = MarkupNormalizer::injectSharedFrontendAssets('<html><head></head><body></body></html>');

        $assert(!str_contains($html, 'data-cms-fonts-css'), 'Nothing to replace means nothing to inline.');
    });

    $test('runtime scripts are content hashed so they can be cached for a year', function () use ($assert, $page): void {
        $html = MarkupNormalizer::injectSharedFrontendAssets($page());

        foreach (['frontend-runtime', 'cookie-consent'] as $name) {
            $assert(
                preg_match('~/assets/generated/' . $name . '-[a-f0-9]{16}\.js~', $html) === 1,
                $name . ' must be served under a content-hashed name; the plain path is only cached for an hour.'
            );
        }
        $assert(!str_contains($html, 'src="/assets/frontend-runtime.js"'), 'The unhashed runtime path must not remain.');
        $assert(!str_contains($html, 'src="/assets/cookie-consent.js"'), 'The unhashed consent path must not remain.');
    });

    $test('a missing runtime file costs one script its caching, not the whole page', function () use ($assert, $page, $root): void {
        // tryServeDatabasePage() catches every Throwable and returns false, so an exception
        // raised here would not surface as an error - it would serve the 404 page for every
        // route. Degrading to the plain path keeps the site up when a deploy lands the PHP
        // before the JavaScript.
        $moved = $root . '/public/assets/frontend-runtime.js';
        $backup = $moved . '.test-backup';
        $assert(is_file($moved), 'The runtime script must exist before this test moves it.');

        rename($moved, $backup);
        try {
            $html = MarkupNormalizer::injectSharedFrontendAssets($page());
            $assert(
                str_contains($html, 'src="/assets/frontend-runtime.js"'),
                'With the file missing the plain path must still be emitted.'
            );
            $assert(str_contains($html, 'data-cookie-consent-js'), 'The other scripts must still be injected.');
        } finally {
            rename($backup, $moved);
        }
        $assert(is_file($moved), 'The runtime script must be restored.');
    });

    $test('the tracking and consent runtimes fall back to their plain paths too', function () use ($assert, $root): void {
        $renderer = new SeoRenderer();
        $method = new ReflectionMethod(SeoRenderer::class, 'hashedAssetSrc');
        $method->setAccessible(true);

        $hashed = $method->invoke(null, 'tracking.js', 'tracking');
        $assert(
            preg_match('~^/assets/generated/tracking-[a-f0-9]{16}\.js$~', $hashed) === 1,
            'tracking.js must be content hashed, got: ' . $hashed
        );

        $missing = $method->invoke(null, 'does-not-exist.js', 'nope');
        $assert($missing === '/assets/does-not-exist.js', 'An unreadable source must degrade to its plain path.');
    });
}
