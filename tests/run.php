<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\SecurityTxtCache;
use App\Services\DemoContentSeeder;
use App\Services\DemoStateFingerprintService;
use App\Services\SeoRenderer;
use App\Services\SecurityTxtGenerator;
use App\Services\SecurityTxtHttpResponder;
use App\Services\SecurityTxtValidator;
use App\Services\SetupFilesystemResetService;
use App\Services\ThemeTemplateRegistry;

require __DIR__ . '/GoldenMasterRunner.php';

// Buffer all output for the whole run. GoldenMasterRunner::bootstrap() require()s
// public/index.php, which calls session_start()/header() — those fail with
// "headers already sent" if any earlier test in this same process (e.g. the
// PASS/FAIL lines below) already flushed real output to stdout first. Keeping
// everything inside one outer buffer means nothing is actually sent to the SAPI
// until the script ends, so that never happens regardless of run order.
ob_start();

$cliArgs = array_slice((array) ($_SERVER['argv'] ?? []), 1);

if (in_array('--update-goldens', $cliArgs, true)) {
    $result = GoldenMasterRunner::update();
    foreach ($result['lines'] as $line) {
        echo $line . "\n";
    }
    echo sprintf("\n%d golden target(s), %d error(s)\n", count($result['lines']), $result['failures']);
    exit($result['failures'] === 0 ? 0 : 1);
}

if (in_array('--test-goldens', $cliArgs, true)) {
    $result = GoldenMasterRunner::test();
    foreach ($result['lines'] as $line) {
        echo $line . "\n";
    }
    echo sprintf("\n%d golden check(s), %d failure(s)\n", count($result['lines']), $result['failures']);
    exit($result['failures'] === 0 ? 0 : 1);
}

$validator = new SecurityTxtValidator();
$generator = new SecurityTxtGenerator();
$responder = new SecurityTxtHttpResponder();
$now = new DateTimeImmutable('2026-07-10T00:00:00Z');
$validInput = [
    'enabled' => 1,
    'contacts' => ['mailto:security@example.com'],
    'expires' => '2026-12-01T12:00:00Z',
    'canonical_url' => 'https://example.com/.well-known/security.txt',
    'preferred_languages' => 'cs, en',
];

$tests = [];
$test = static function (string $name, callable $callback) use (&$tests): void {
    $tests[] = [$name, $callback];
};
$assert = static function (bool $condition, string $message = 'Assertion failed'): void {
    if (!$condition) throw new RuntimeException($message);
};
$validPayload = static function () use ($validator, $generator, $validInput, $now, $assert): array {
    $result = $validator->validate($validInput, true, $now);
    $content = $generator->generate($result['config']);
    $assert(!str_starts_with($content, "\xEF\xBB\xBF"), 'Output contains a UTF-8 BOM.');
    $assert(str_ends_with($content, "\n"), 'Output must end with LF.');
    return [
        'enabled' => true,
        'valid' => true,
        'content' => $content,
        'etag' => '"' . hash('sha256', $content) . '"',
        'last_modified' => 'Fri, 10 Jul 2026 00:00:00 GMT',
    ];
};

$test('valid minimal configuration', function () use ($validator, $validInput, $now, $assert): void {
    $result = $validator->validate($validInput, true, $now);
    $assert($result['valid'] === true);
    $assert($result['config']['expires_at'] === '2026-12-01T12:00:00Z');
});

$test('multiple contacts preserve preference order', function () use ($validator, $generator, $validInput, $now, $assert): void {
    $input = $validInput;
    $input['contacts'] = ['mailto:first@example.com', 'https://example.com/report', 'tel:+420123456789'];
    $result = $validator->validate($input, true, $now);
    $content = $generator->generate($result['config']);
    $assert(strpos($content, 'Contact: mailto:first@example.com') < strpos($content, 'Contact: https://example.com/report'));
    $assert(strpos($content, 'Contact: https://example.com/report') < strpos($content, 'Contact: tel:+420123456789'));
});

$test('missing contact is rejected', function () use ($validator, $validInput, $now, $assert): void {
    $input = $validInput; $input['contacts'] = [];
    $result = $validator->validate($input, true, $now);
    $assert(isset($result['errors']['contacts']));
});

$test('missing expires is rejected', function () use ($validator, $validInput, $now, $assert): void {
    $input = $validInput; unset($input['expires']);
    $result = $validator->validate($input, true, $now);
    $assert(isset($result['errors']['expires']));
});

$test('multiple expires values are rejected', function () use ($validator, $validInput, $now, $assert): void {
    $input = $validInput; $input['expires'] = ['2026-12-01T12:00:00Z', '2026-12-02T12:00:00Z'];
    $result = $validator->validate($input, true, $now);
    $assert(isset($result['errors']['expires']));
});

$test('expired date is rejected', function () use ($validator, $validInput, $now, $assert): void {
    $input = $validInput; $input['expires'] = '2026-07-09T23:59:59Z';
    $result = $validator->validate($input, true, $now);
    $assert(isset($result['errors']['expires']));
});

$test('invalid date format is rejected', function () use ($validator, $validInput, $now, $assert): void {
    $input = $validInput; $input['expires'] = '2026-12-01 12:00:00';
    $result = $validator->validate($input, true, $now);
    $assert(isset($result['errors']['expires']));
});

$test('http URL is rejected', function () use ($validator, $validInput, $now, $assert): void {
    $input = $validInput; $input['policy_url'] = 'http://example.com/policy';
    $result = $validator->validate($input, true, $now);
    $assert(isset($result['errors']['policy_url']));
});

$test('newline and encoded header injection are rejected', function () use ($validator, $validInput, $now, $assert): void {
    $input = $validInput; $input['contacts'] = ["mailto:security@example.com\nExpires: 2099-01-01T00:00:00Z"];
    $result = $validator->validate($input, true, $now);
    $assert(isset($result['errors']['contacts.0']));
    $input['contacts'] = ['https://example.com/report%0aPolicy:https://attacker.example'];
    $result = $validator->validate($input, true, $now);
    $assert(isset($result['errors']['contacts.0']));
});

$test('invalid language tag is rejected', function () use ($validator, $validInput, $now, $assert): void {
    $input = $validInput; $input['preferred_languages'] = 'cs, bad_tag';
    $result = $validator->validate($input, true, $now);
    $assert(isset($result['errors']['preferred_languages']));
});

$test('disabled generator returns plain 404', function () use ($responder, $assert): void {
    $response = $responder->build('/.well-known/security.txt', 'GET', ['enabled' => false, 'valid' => false]);
    $assert($response['status'] === 404);
    $assert($response['headers']['Content-Type'] === 'text/plain; charset=utf-8');
});

$test('primary endpoint returns content', function () use ($responder, $validPayload, $assert): void {
    $response = $responder->build('/.well-known/security.txt', 'GET', $validPayload());
    $assert($response['status'] === 200);
    $assert(str_starts_with($response['body'], '# Security contact information'));
});

$test('legacy endpoint redirects permanently', function () use ($responder, $validPayload, $assert): void {
    $response = $responder->build('/security.txt', 'GET', $validPayload());
    $assert($response['status'] === 308);
    $assert($response['headers']['Location'] === '/.well-known/security.txt');
    $assert($response['headers']['Content-Type'] === 'text/plain; charset=utf-8');
});

$test('content type is RFC compliant', function () use ($responder, $validPayload, $assert): void {
    $response = $responder->build('/.well-known/security.txt', 'GET', $validPayload());
    $assert($response['headers']['Content-Type'] === 'text/plain; charset=utf-8');
});

$test('HEAD returns headers without body', function () use ($responder, $validPayload, $assert): void {
    $response = $responder->build('/.well-known/security.txt', 'HEAD', $validPayload());
    $assert($response['status'] === 200);
    $assert($response['body'] === '');
    $assert($response['headers']['ETag'] !== '');
});

$test('cache invalidation publishes next payload', function () use ($assert): void {
    $directory = sys_get_temp_dir() . '/seo-cms-security-test-' . bin2hex(random_bytes(5));
    $cache = new SecurityTxtCache($directory);
    try {
        $cache->put(1, ['etag' => 'one']);
        $assert(($cache->get(1)['etag'] ?? '') === 'one');
        $cache->forget(1);
        $assert($cache->get(1) === null);
        $cache->put(1, ['etag' => 'two']);
        $assert(($cache->get(1)['etag'] ?? '') === 'two');
        file_put_contents($directory . '/site-1.json', '{broken');
        $thrown = false;
        try { $cache->get(1); } catch (RuntimeException) { $thrown = true; }
        $assert($thrown, 'Corrupt cache must be surfaced instead of becoming a silent miss.');
    } finally {
        foreach (glob($directory . '/*') ?: [] as $file) @unlink($file);
        @rmdir($directory);
    }
});

$test('response never contains HTML layout', function () use ($responder, $validPayload, $assert): void {
    $response = $responder->build('/.well-known/security.txt', 'GET', $validPayload());
    $assert(strip_tags($response['body']) === $response['body']);
    $assert(!str_contains(strtolower($response['body']), '<!doctype'));
});

$test('conditional request returns 304', function () use ($responder, $validPayload, $assert): void {
    $payload = $validPayload();
    $response = $responder->build('/.well-known/security.txt', 'GET', $payload, ['If-None-Match' => $payload['etag']]);
    $assert($response['status'] === 304);
    $assert($response['body'] === '');
    $assert($response['headers']['Content-Type'] === 'text/plain; charset=utf-8');
});

$test('setup filesystem reset removes every render-affecting artifact', function () use ($assert): void {
    $root = sys_get_temp_dir() . '/seo-cms-reset-test-' . bin2hex(random_bytes(5));
    $targets = [
        'storage/media/demo/image.webp',
        'storage/published/index.html',
        'storage/cache/security_txt/site-1.json',
        'public/assets/generated/page-css-test.css',
        'storage/active-theme-key.txt',
    ];
    try {
        foreach ($targets as $relativePath) {
            $path = $root . '/' . $relativePath;
            @mkdir(dirname($path), 0775, true);
            file_put_contents($path, 'stale');
        }

        (new SetupFilesystemResetService($root))->reset();
        foreach ($targets as $relativePath) {
            $assert(!file_exists($root . '/' . $relativePath), 'Reset left stale artifact: ' . $relativePath);
        }
    } finally {
        if (is_dir($root)) {
            $items = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($items as $item) {
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }
            @rmdir($root);
        }
    }
});

$test('active theme state is explicit and removable', function () use ($assert): void {
    $root = sys_get_temp_dir() . '/seo-cms-theme-state-test-' . bin2hex(random_bytes(5));
    $stateFile = $root . '/storage/active-theme-key.txt';
    $registry = new ThemeTemplateRegistry($root . '/Templates', $stateFile);
    try {
        $registry->persistActiveThemeKey(DemoContentSeeder::DEFAULT_THEME_KEY);
        $assert($registry->getPersistedActiveThemeKey() === DemoContentSeeder::DEFAULT_THEME_KEY);
        $registry->persistActiveThemeKey(null);
        $assert(!is_file($stateFile), 'Theme state file was not removed.');
    } finally {
        @unlink($stateFile);
        @rmdir(dirname($stateFile));
        @rmdir($root);
    }
});

$test('default demo theme is present in the repository', function () use ($assert): void {
    $theme = (new ThemeTemplateRegistry(dirname(__DIR__) . '/Templates'))->find(DemoContentSeeder::DEFAULT_THEME_KEY);
    $assert($theme !== null, 'Missing default demo theme: ' . DemoContentSeeder::DEFAULT_THEME_KEY);
    $assert(DemoContentSeeder::DEFAULT_THEME_KEY === 'golden-bash-diagonal', 'Unexpected default demo theme key.');
    $assert(($theme['version'] ?? null) === ThemeTemplateRegistry::DEFAULT_THEME_VERSION, 'Unexpected default theme version.');
});

$test('theme registry resolves Golden Bash Diagonal when no explicit theme is active', function () use ($assert): void {
    $stateFile = sys_get_temp_dir() . '/seo-cms-missing-theme-state-' . bin2hex(random_bytes(5)) . '.txt';
    $registry = new ThemeTemplateRegistry(dirname(__DIR__) . '/Templates', $stateFile);
    $theme = $registry->resolveForSite(['id' => 1, 'active_theme_key' => null]);

    $assert(($theme['key'] ?? null) === ThemeTemplateRegistry::DEFAULT_THEME_KEY, 'Registry did not resolve the default theme.');
    $assert(($theme['version'] ?? null) === ThemeTemplateRegistry::DEFAULT_THEME_VERSION, 'Registry resolved an unexpected default theme version.');
});

$test('demo fingerprint is stable for reordered object keys', function () use ($assert): void {
    $left = ['theme' => 'bezova-lens-tale', 'content' => ['title' => 'Demo', 'items' => [1, 2]]];
    $right = ['content' => ['items' => [1, 2], 'title' => 'Demo'], 'theme' => 'bezova-lens-tale'];
    $assert(
        DemoStateFingerprintService::fingerprintPayload($left) === DemoStateFingerprintService::fingerprintPayload($right),
        'Equivalent canonical payloads produced different fingerprints.'
    );
    $right['content']['title'] = 'Changed';
    $assert(
        DemoStateFingerprintService::fingerprintPayload($left) !== DemoStateFingerprintService::fingerprintPayload($right),
        'Content change did not change the fingerprint.'
    );
});

$test('main seed enables the non-blocking cookie consent summary for inserts and updates', function () use ($assert): void {
    $source = file_get_contents(dirname(__DIR__) . '/database/seeds/seed.php');
    $assert($source !== false, 'Main seed cannot be read.');
    $assert(
        str_contains($source, 'UPDATE sites SET name=?, active_theme_key=?, cookie_consent_enabled=1 WHERE id=?'),
        'Existing seeded sites do not receive cookie_consent_enabled=1.'
    );
    $assert(
        str_contains($source, 'active_theme_key, cookie_consent_enabled) VALUES (?, ?, ?, ?)'),
        'New seeded sites do not declare the cookie consent default explicitly.'
    );
    $assert(
        str_contains($source, '->execute([$name, $domain, ThemeTemplateRegistry::DEFAULT_THEME_KEY, 1])'),
        'New seeded sites do not write the explicit theme and cookie consent defaults.'
    );
});

$test('disabled consent keeps analytics categories available without a banner', function () use ($assert): void {
    $html = (new SeoRenderer())->renderConsentRuntime([
        'cookie_consent_enabled' => 0,
        'cookie_consent_analytics_enabled' => 1,
        'cookie_consent_marketing_enabled' => 1,
    ]);
    $decoded = html_entity_decode($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $assert(str_contains($decoded, '"cookieConsentEnabled":false'));
    $assert(str_contains($decoded, '"analyticsCategoryEnabled":true'));
    $assert(str_contains($decoded, '"marketingCategoryEnabled":true'));
});

$test('tracking bootstrap chooses one interaction-deferred primary loader and defers Clarity', function () use ($assert): void {
    $html = (new SeoRenderer())->renderConsentRuntime([
        'cookie_consent_enabled' => 0,
        'google_analytics_id' => 'G-TEST123',
        'google_tag_manager_id' => 'GTM-TEST123',
        'clarity_id' => 'clarity-test',
    ]);
    $decoded = html_entity_decode($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $runtime = (string)file_get_contents(dirname(__DIR__) . '/public/assets/cookie-consent-runtime.js');

    $assert(str_contains($decoded, '"trackingLoaderMode":"direct-ga"'));
    $assert(str_contains($runtime, "trackingLoaderMode !== 'gtm'"));
    $assert(str_contains($runtime, "window.addEventListener('pointerdown', activate"));
    $assert(str_contains($runtime, 'function schedulePrimaryTracking'));
    $assert(str_contains($runtime, "schedulePrimaryTracking(loadGoogleAnalytics, '__analyticsLoaded')"));
    $assert(str_contains($runtime, "schedulePrimaryTracking(loadGoogleTagManager, '__gtmLoaded')"));
    $assert(str_contains($runtime, 'window.setTimeout(activate, 8000)'));
    $assert(str_contains($runtime, 'loadAnalyticsScripts(true)') && str_contains($runtime, 'loadMarketingScripts(true)'));
    $assert(!str_contains($runtime, "loadGoogleAnalytics();\n    loadClarity();"), 'GA4 and Clarity still start in parallel.');
});

$test('main and demo seed share an existing default hero poster asset', function () use ($assert): void {
    $asset = DemoContentSeeder::DEFAULT_HERO_POSTER_ASSET;
    $assert(is_file(dirname(__DIR__) . '/database/seeds/demo_assets/' . $asset), 'Default hero poster asset is missing.');

    $seedSource = file_get_contents(dirname(__DIR__) . '/database/seeds/seed.php');
    $assert($seedSource !== false, 'Main seed cannot be read.');
    $assert(str_contains($seedSource, "'$asset'"), 'Main seed does not ingest the default hero poster asset.');
    $assert(str_contains($seedSource, 'SET default_hero_poster_url = ?, favicon_ico_url = ?'), 'Main seed does not persist the default hero poster with brand icons.');
});

$test('hero poster changes the environment fingerprint', function () use ($assert): void {
    $left = ['site' => ['default_hero_poster_url' => 'media://hero-a.webp']];
    $right = ['site' => ['default_hero_poster_url' => 'media://hero-b.webp']];
    $assert(
        DemoStateFingerprintService::fingerprintPayload($left) !== DemoStateFingerprintService::fingerprintPayload($right),
        'Changing the default hero poster did not change the environment fingerprint.'
    );
});

// PHTML view engine core tests (Phase 1). Registers into the same $tests suite
// so they run and count with `php tests/run.php`. The engine is not yet wired
// into any render path, so these exercise App\View\* in isolation.
require __DIR__ . '/ViewEngineTest.php';
register_view_engine_tests($test, $assert);

// Phase 3: layout chrome moved to PHTML partials. DB-free — renders partials
// from explicit data and checks the PHTML layout against applyLayout().
require __DIR__ . '/Phase3PartialsTest.php';
register_phase3_partials_tests($test, $assert);

// Phase 6: theme manifest v2 (views_dir activation + per-page content overrides).
// DB-free — writes a throwaway fixture theme under Templates/ and removes it.
require __DIR__ . '/ManifestV2Test.php';
register_manifest_v2_tests($test, $assert);

// Post-refactor: content archetypes (faq, sluzby, kontakt, blog, portfolio,
// homepage partials, reviews) now resolve through the active theme instead of
// a hardcoded forTheme(null, ...) — Templates/<theme>/views/archetypes/*.phtml
// can override any archetype's content. DB-free.
require __DIR__ . '/ArchetypeThemeOverrideTest.php';
register_archetype_theme_override_tests($test, $assert);

// Premium Photo must own presentation only. Database fragments and the shared
// PageComposer injection targets are its explicit content contract.
require __DIR__ . '/PremiumPhotoDataContractTest.php';
register_premium_photo_data_contract_tests($test, $assert);

require __DIR__ . '/GoldenBashThemeTest.php';
register_golden_bash_theme_tests($test, $assert);

require __DIR__ . '/DemoContentBatchContractTest.php';
register_demo_content_batch_contract_tests($test, $assert);

require __DIR__ . '/CookieConsentPresentationTest.php';
register_cookie_consent_presentation_tests($test, $assert);

require __DIR__ . '/ThemeSwitchPublishIntegrationTest.php';
register_theme_switch_publish_integration_tests($test, $assert);

require __DIR__ . '/SharedProcessSectionTest.php';
register_shared_process_section_tests($test, $assert);

require __DIR__ . '/PageCssSubsetTest.php';
register_page_css_subset_tests($test, $assert);

require __DIR__ . '/RbsClientIterationTest.php';
register_rbs_client_iteration_tests($test, $assert);

require __DIR__ . '/PortfolioCaptionFormatterTest.php';
register_portfolio_caption_formatter_tests($test, $assert);

require __DIR__ . '/CompanyRegistrationIdTest.php';
register_company_registration_id_tests($test, $assert);

require __DIR__ . '/DatabaseCharsetGuardTest.php';
register_database_charset_guard_tests($test, $assert);

require __DIR__ . '/PortfolioVideosTest.php';
register_portfolio_videos_tests($test, $assert);

require __DIR__ . '/ContentOrderingTest.php';
register_content_ordering_tests($test, $assert);

require __DIR__ . '/ResponsiveHeroVideoTest.php';
register_responsive_hero_video_tests($test, $assert);

require __DIR__ . '/HeroVideoMediaTest.php';
register_hero_video_media_tests($test, $assert);

require __DIR__ . '/LightOnlyColorSchemeTest.php';
register_light_only_color_scheme_tests($test, $assert);

require __DIR__ . '/PricingPackageCatalogTest.php';
register_pricing_package_catalog_tests($test, $assert);

require __DIR__ . '/HomepagePricingTest.php';
register_homepage_pricing_tests($test, $assert);

require __DIR__ . '/SetupEnvironmentMigrationTest.php';
register_setup_environment_migration_tests($test, $assert);

require __DIR__ . '/OgImageSettingsTest.php';
register_og_image_settings_tests($test, $assert);

require __DIR__ . '/HtaccessWwwRedirectTest.php';
register_htaccess_www_redirect_tests($test, $assert);

require __DIR__ . '/CustomerReviewDatasetTest.php';
register_customer_review_dataset_tests($test, $assert);

require __DIR__ . '/CzechCopyQualityTest.php';
register_czech_copy_quality_tests($test, $assert);

require __DIR__ . '/NavigationAndReviewsLinkTest.php';
register_navigation_and_reviews_link_tests($test, $assert);

require __DIR__ . '/SeoAuditRegressionTest.php';
register_seo_audit_regression_tests($test, $assert);

require __DIR__ . '/SeoSmokeCheckerTest.php';
register_seo_smoke_checker_tests($test, $assert);

require __DIR__ . '/HeroImageQualityTest.php';
register_hero_image_quality_tests($test, $assert);

require __DIR__ . '/ImageVariantServiceTest.php';
register_image_variant_service_tests($test, $assert);

require __DIR__ . '/AboutPageSeedPersistenceTest.php';
register_about_page_seed_persistence_tests($test, $assert);

require __DIR__ . '/FirmyAddressContractTest.php';
register_firmy_address_contract_tests($test, $assert);

require __DIR__ . '/WeddingBlogDemoContentTest.php';
require __DIR__ . '/PortfolioDemoContentTest.php';
register_wedding_blog_demo_content_tests($test, $assert);

require __DIR__ . '/ContentSeoRemediationTest.php';
register_content_seo_remediation_tests($test, $assert);

require __DIR__ . '/FrontendAssetDeliveryTest.php';
register_frontend_asset_delivery_tests($test, $assert);

require __DIR__ . '/SchemaOrgStructuredFieldsTest.php';
register_schema_org_structured_fields_tests($test, $assert);

require __DIR__ . '/SmtpMailerTest.php';
register_smtp_mailer_tests($test, $assert);

require __DIR__ . '/VisitAnalyticsTest.php';
register_visit_analytics_tests($test, $assert);

require __DIR__ . '/HealthCheckTest.php';
register_health_check_tests($test, $assert);

require __DIR__ . '/ContactSpamFilterTest.php';
register_contact_spam_filter_tests($test, $assert);

require __DIR__ . '/PhpRuntimeInfoTest.php';
register_php_runtime_info_tests($test, $assert);

$failures = 0;
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

// Golden-master checks run only if baselines already exist (tests/goldens/ is
// populated via `php tests/run.php --update-goldens`); their absence must not
// fail the base suite (e.g. on a fresh checkout before the first update run).
if (is_dir(__DIR__ . '/goldens/live') || is_dir(__DIR__ . '/goldens/publish')) {
    echo "\n--- Golden-master checks ---\n";
    try {
        $goldenResult = GoldenMasterRunner::test();
        foreach ($goldenResult['lines'] as $line) {
            echo $line . "\n";
        }
        $failures += $goldenResult['failures'];
        echo sprintf("%d golden check(s), %d failure(s)\n", count($goldenResult['lines']), $goldenResult['failures']);
    } catch (Throwable $e) {
        $failures++;
        echo "FAIL golden-master checks: {$e->getMessage()}\n";
    }
}

exit($failures === 0 ? 0 : 1);
