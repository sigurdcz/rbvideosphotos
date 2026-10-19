<?php

declare(strict_types=1);

use App\Services\SeoRenderer;
use App\Services\VisitAnalyticsService;

function register_visit_analytics_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);
    $validBody = static fn(array $override = []): string => (string) json_encode(array_merge([
        'p' => '/svatebni-foto-video',
        'r' => 'https://www.google.com/search?q=x',
        's' => 'newsletter',
        'm' => 'email',
        'c' => 'jaro',
        'w' => 390,
        'v' => null,
        'a' => 0,
    ], $override));

    $test('visit hash is deterministic, rotates daily and never contains the IP', function () use ($assert): void {
        $ip = '203.0.113.42';
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
        $first = VisitAnalyticsService::visitorHash($ip, $ua, '2026-10-19', 'secret');
        $assert($first === VisitAnalyticsService::visitorHash($ip, $ua, '2026-10-19', 'secret'), 'Hash must be deterministic.');
        $assert(preg_match('~^[a-f0-9]{32}$~', $first) === 1, 'Hash must be 32 lowercase hex chars.');
        $assert($first !== VisitAnalyticsService::visitorHash($ip, $ua, '2026-10-20', 'secret'), 'Hash must rotate daily.');
        $assert($first !== VisitAnalyticsService::visitorHash($ip, $ua, '2026-10-19', 'other'), 'Hash must depend on the secret.');
        $assert(!str_contains($first, bin2hex($ip)), 'Hash must not embed the hex IP.');

        $record = VisitAnalyticsService::buildRecord(
            1,
            (array) VisitAnalyticsService::parsePayload('{"p":"/","a":0}'),
            $ip,
            $ua,
            ['example.com'],
            'secret',
            new DateTimeImmutable('2026-10-19 10:00:00')
        );
        $assert(!in_array($ip, array_map('strval', $record), true), 'The stored row must not contain the IP.');
        $assert($record['visited_on'] === '2026-10-19' && $record['visitor_id'] === null && $record['consent_analytics'] === 0);
    });

    $test('visit device classification covers desktop, mobile, tablet and unknown', function () use ($assert): void {
        $assert(VisitAnalyticsService::deviceType('Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130') === 'desktop');
        $assert(VisitAnalyticsService::deviceType('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148') === 'mobile');
        $assert(VisitAnalyticsService::deviceType('Mozilla/5.0 (Linux; Android 14; Pixel 8) Chrome/130 Mobile Safari/537.36') === 'mobile');
        $assert(VisitAnalyticsService::deviceType('Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)') === 'tablet');
        $assert(VisitAnalyticsService::deviceType('Mozilla/5.0 (Linux; Android 13; SM-X200) Chrome/130 Safari/537.36') === 'tablet');
        $assert(VisitAnalyticsService::deviceType('') === 'unknown');
        $assert(VisitAnalyticsService::deviceType('', 390) === 'mobile');
    });

    $test('visit bot detection flags crawlers, tools and empty user agents', function () use ($assert): void {
        foreach (['Googlebot/2.1', 'Mozilla/5.0 HeadlessChrome/120', 'curl/8.4', 'python-requests/2.31', 'facebookexternalhit/1.1', 'Chrome-Lighthouse', ''] as $ua) {
            $assert(VisitAnalyticsService::isBot($ua), 'Expected bot: ' . $ua);
        }
        $assert(!VisitAnalyticsService::isBot('Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130 Safari/537.36'));
    });

    $test('visit path and referrer sanitizing drops admin, foreign schemes and own host', function () use ($assert): void {
        $assert(VisitAnalyticsService::sanitizePath('/blog/svatba-v-lese') === '/blog/svatba-v-lese');
        $assert(VisitAnalyticsService::sanitizePath('/') === '/');
        $assert(VisitAnalyticsService::sanitizePath('/%C5%BEena') === '/%C5%BEena');
        $assert(VisitAnalyticsService::sanitizePath('/admin') === null);
        $assert(VisitAnalyticsService::sanitizePath('/admin/settings') === null);
        $assert(VisitAnalyticsService::sanitizePath('relative') === null);
        $assert(VisitAnalyticsService::sanitizePath('/a?b=c') === null);
        $assert(VisitAnalyticsService::sanitizePath('/<script>') === null);
        $assert(VisitAnalyticsService::sanitizePath('/' . str_repeat('a', 255)) === null);

        $own = ['example.com', 'https://example.com'];
        $assert(VisitAnalyticsService::referrerHost('https://www.Google.com/search?q=x', $own) === 'www.google.com');
        $assert(VisitAnalyticsService::referrerHost('https://example.com/kontakt', $own) === null, 'Own host must be NULL.');
        $assert(VisitAnalyticsService::referrerHost('https://www.example.com/', $own) === null, 'Own www host must be NULL.');
        $assert(VisitAnalyticsService::referrerHost('android-app://com.google', $own) === null);
        $assert(VisitAnalyticsService::referrerHost('', $own) === null);
        $assert(VisitAnalyticsService::referrerHost('https://x.test/' . str_repeat('a', 2050), $own) === null);

        $assert(VisitAnalyticsService::sanitizeUtm(" jaro\n2026 ") === 'jaro2026');
        $assert(VisitAnalyticsService::sanitizeUtm(str_repeat('a', 101)) === null);
        $assert(VisitAnalyticsService::sanitizeUtm('') === null);
    });

    $test('visit beacon accepts same-origin requests and rejects foreign origins', function () use ($assert): void {
        $assert(VisitAnalyticsService::isSameOrigin(['HTTP_HOST' => 'example.com', 'HTTP_ORIGIN' => 'https://example.com']));
        $assert(VisitAnalyticsService::isSameOrigin(['HTTP_HOST' => 'localhost:8000', 'HTTP_ORIGIN' => 'http://localhost:8000']));
        $assert(VisitAnalyticsService::isSameOrigin(['HTTP_HOST' => 'example.com', 'HTTP_REFERER' => 'https://example.com/blog']));
        $assert(VisitAnalyticsService::isSameOrigin(['HTTP_HOST' => 'example.com', 'HTTP_SEC_FETCH_SITE' => 'same-origin']));
        $assert(!VisitAnalyticsService::isSameOrigin(['HTTP_HOST' => 'example.com', 'HTTP_ORIGIN' => 'https://evil.test']), 'Foreign Origin must be rejected.');
        $assert(!VisitAnalyticsService::isSameOrigin(['HTTP_HOST' => 'example.com', 'HTTP_ORIGIN' => 'https://example.com.evil.test']));
        $assert(!VisitAnalyticsService::isSameOrigin(['HTTP_HOST' => 'example.com', 'HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_ORIGIN' => 'https://example.com']));
        $assert(!VisitAnalyticsService::isSameOrigin(['HTTP_HOST' => 'example.com']), 'No origin information must be rejected.');
    });

    $test('visit payload validation rejects oversized bodies, bad ids and wrong types', function () use ($assert, $validBody): void {
        $payload = VisitAnalyticsService::parsePayload($validBody());
        $assert(is_array($payload) && $payload['p'] === '/svatebni-foto-video' && $payload['a'] === false && $payload['v'] === null);

        $consented = VisitAnalyticsService::parsePayload($validBody(['v' => str_repeat('ab', 16), 'a' => 1]));
        $assert(is_array($consented) && $consented['v'] === str_repeat('ab', 16) && $consented['a'] === true);

        $withoutConsent = VisitAnalyticsService::parsePayload($validBody(['v' => str_repeat('ab', 16), 'a' => 0]));
        $assert(is_array($withoutConsent) && $withoutConsent['v'] === null, 'A cookie id must never be kept without consent.');

        $assert(VisitAnalyticsService::parsePayload($validBody(['r' => str_repeat('x', 5000)])) === null, 'Body over 4 KB must be rejected.');
        $assert(strlen(str_pad('{"p":"/"}', 4097)) > VisitAnalyticsService::MAX_BODY_BYTES);
        $assert(VisitAnalyticsService::parsePayload(str_pad('{"p":"/"}', 4097)) === null, 'Exactly 4097 bytes must be rejected.');
        $assert(VisitAnalyticsService::parsePayload($validBody(['v' => 'ABCDEF'])) === null, 'Malformed v must be rejected.');
        $assert(VisitAnalyticsService::parsePayload($validBody(['v' => str_repeat('A', 32)])) === null, 'Uppercase v must be rejected.');
        $assert(VisitAnalyticsService::parsePayload($validBody(['p' => '/admin/pages'])) === null);
        $assert(VisitAnalyticsService::parsePayload($validBody(['w' => '390'])) === null);
        $assert(VisitAnalyticsService::parsePayload($validBody(['a' => 'yes'])) === null);
        $assert(VisitAnalyticsService::parsePayload($validBody(['s' => str_repeat('a', 101)])) === null);
        $assert(VisitAnalyticsService::parsePayload('not json') === null);
        $assert(VisitAnalyticsService::parsePayload('[1,2]') === null);
        $assert(VisitAnalyticsService::parsePayload('') === null);
    });

    $test('visit retention and admin range helpers are bounded', function () use ($assert): void {
        $assert(VisitAnalyticsService::retentionCutoff(new DateTimeImmutable('2026-10-19')) === '2025-08-19');
        $assert(VisitAnalyticsService::normalizeRangeDays('7') === 7);
        $assert(VisitAnalyticsService::normalizeRangeDays('365') === 365);
        $assert(VisitAnalyticsService::normalizeRangeDays('14') === 30);
        $assert(VisitAnalyticsService::normalizeRangeDays('1; DROP') === 30);
    });

    $test('visit analytics script honours explicit consent and never blocks', function () use ($assert, $root): void {
        $js = (string) file_get_contents($root . '/public/assets/visit-analytics.js');
        foreach (['canLoadAnalytics', 'cc.enabled === true', 'cms-cookie-consent:updated', 'Max-Age=0', 'sendBeacon',
                  'keepalive', 'getRandomValues', 'navigator.webdriver', 'cms-adminbar', "'/api/visit'", 'SameSite=Lax'] as $needle) {
            $assert(str_contains($js, $needle), 'visit-analytics.js is missing: ' . $needle);
        }
        $assert(!str_contains($js, 'localStorage'), 'The visit script must never use localStorage.');
        $assert(!str_contains($js, 'gtag') && !str_contains($js, 'dataLayer'), 'The visit script must stay independent of GA/GTM.');
    });

    $test('consent runtime embeds the visit script only when the site flag is on', function () use ($assert): void {
        $renderer = new SeoRenderer();
        $on = $renderer->renderConsentRuntime(['cookie_consent_enabled' => 1]);
        $assert(str_contains($on, 'data-cms-visit-analytics-js'), 'Default (missing column) must embed the visit script.');
        $assert(preg_match('~src="/assets/generated/visit-analytics-[a-f0-9]{16}\.js" defer data-cms-visit-analytics-js~', $on) === 1, 'Visit script must be content hashed and deferred.');
        $assert(strpos($on, 'cookie-consent-runtime') < strpos($on, 'visit-analytics'), 'Visit script must follow the consent runtime.');

        $explicitOn = $renderer->renderConsentRuntime(['visit_analytics_enabled' => 1]);
        $assert(str_contains($explicitOn, 'data-cms-visit-analytics-js'));

        $off = $renderer->renderConsentRuntime(['cookie_consent_enabled' => 1, 'visit_analytics_enabled' => 0]);
        $assert(!str_contains($off, 'visit-analytics'), 'Disabled flag must not embed the visit script.');
        $assert(str_contains($off, 'cookie-consent-runtime'), 'Consent runtime must stay untouched.');
    });

    $test('visit beacon endpoint runs before the session and outside the API key area', function () use ($assert, $root): void {
        $index = (string) file_get_contents($root . '/public/index.php');
        $beacon = strpos($index, 'if (tryRecordVisitBeacon($earlyUri))');
        $assert($beacon !== false, 'Beacon dispatch missing.');
        $assert($beacon < strpos($index, "Auth::start();\n"), 'Beacon must run before Auth::start().');
        $assert(str_contains($index, "RequestThrottle::hit('visit_beacon', \$ip, 60, 60, 300)"));
        $assert(str_contains($index, "\$uri !== '/api/visit'"));

        $migration = (string) file_get_contents($root . '/database/migrations/109_create_site_visits.sql');
        $assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS site_visits'));
        $assert(str_contains($migration, "column_name = 'visit_analytics_enabled'"), 'sites column must be guarded.');
        $assert(preg_match('~^\s*`?ip(?:_address)?`?\s+\w~mi', $migration) !== 1, 'site_visits must not have an IP column.');

        $admin = (string) file_get_contents($root . '/admin/index.php');
        $assert(str_contains($admin, "\$router->get('/admin/analytics', 'VisitAnalyticsController', 'index')"));
        $controller = (string) file_get_contents($root . '/app/Controllers/VisitAnalyticsController.php');
        $assert(str_contains($controller, '$this->requireAdmin()'), 'Analytics overview must be admin-only.');
    });
}
