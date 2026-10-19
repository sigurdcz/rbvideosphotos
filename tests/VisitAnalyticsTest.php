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
        $assert(VisitAnalyticsService::sanitizePath("/a!$&'()*+,;=:@~b") === "/a!$&'()*+,;=:@~b", 'RFC 3986 pchar must be kept.');
        $assert(VisitAnalyticsService::sanitizePath('/admin') === null);
        $assert(VisitAnalyticsService::sanitizePath('/Admin/settings') === null);
        $assert(VisitAnalyticsService::sanitizePath('/admin/') === null);
        $assert(VisitAnalyticsService::sanitizePath('/admin?x=1') === null);
        $assert(VisitAnalyticsService::sanitizePath('/%61dmin/pages') === null, 'Encoded admin path must stay blocked.');
        $assert(VisitAnalyticsService::sanitizePath('/administrativa') === '/administrativa', '/administrativa is a public page.');
        $assert(VisitAnalyticsService::sanitizePath('/admins-guide') === '/admins-guide');
        $assert(VisitAnalyticsService::sanitizePath(null) === null);
        $assert(VisitAnalyticsService::sanitizePath(['/']) === null);
        // Invalid pathnames are normalized instead of dropping the whole beacon.
        $assert(VisitAnalyticsService::sanitizePath('relative') === '/');
        $assert(VisitAnalyticsService::sanitizePath('') === '/');
        $assert(VisitAnalyticsService::sanitizePath('/a?b=c') === '/a', 'Query must be cut off.');
        $assert(VisitAnalyticsService::sanitizePath('/a#frag') === '/a');
        $assert(VisitAnalyticsService::sanitizePath('/<script>') === '/%3Cscript%3E');
        $assert(VisitAnalyticsService::sanitizePath('/a b|c') === '/a%20b%7Cc');
        $assert(VisitAnalyticsService::sanitizePath('/žena') === '/%C5%BEena', 'Raw UTF-8 must be percent-encoded.');
        $assert(VisitAnalyticsService::sanitizePath('/100%') === '/100%25', 'A lone % must be escaped.');
        $assert(VisitAnalyticsService::sanitizePath("/a\x00b") === '/a%00b');
        $assert(VisitAnalyticsService::sanitizePath('/' . str_repeat('a', 400)) === '/' . str_repeat('a', 254), 'Long path must be cut to 255 bytes.');
        $cut = (string) VisitAnalyticsService::sanitizePath('/' . str_repeat('a', 252) . 'ž');
        $assert($cut === '/' . str_repeat('a', 252), 'Truncation must not split a %XX escape: ' . $cut);
        foreach (['/<script>', '/a b|c', '/žena', '/100%', '/' . str_repeat('ž', 200)] as $raw) {
            $clean = (string) VisitAnalyticsService::sanitizePath($raw);
            $assert(strlen($clean) <= 255 && preg_match(VisitAnalyticsService::PATH_PATTERN, $clean) === 1, 'Normalized path must be storable: ' . $raw);
        }

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

    $test('visit beacon same-origin matrix covers ports, null origin, fetch metadata and www', function () use ($assert): void {
        // [Host, Origin, Referer, Sec-Fetch-Site, expected]
        $matrix = [
            ['example.com:443', 'https://example.com', null, null, true],
            ['example.com', 'https://example.com:443', null, null, true],
            ['example.com:80', 'http://example.com', null, null, true],
            ['example.com', 'http://example.com:80', null, null, true],
            ['EXAMPLE.com', 'https://Example.COM', null, 'same-origin', true],
            ['example.com.', 'https://example.com', null, null, true],
            ['localhost:8000', 'http://localhost:8000', null, 'same-origin', true],
            ['[::1]:8000', 'http://[::1]:8000', null, null, true],
            ['example.com', 'null', null, 'same-origin', true],
            ['example.com', null, 'https://example.com:443/blog?x=1', null, true],
            ['example.com:8443', 'https://example.com', null, null, false],
            ['example.com', 'https://example.com:8443', null, null, false],
            ['localhost:8000', 'http://localhost:8001', null, null, false],
            ['localhost:8000', 'http://localhost', null, null, false],
            ['example.com', 'null', null, null, false],
            ['example.com', 'null', 'https://example.com/', null, false],
            ['example.com', 'null', null, 'same-site', false],
            ['example.com', 'https://example.com', null, 'same-site', false],
            ['example.com', 'https://example.com', null, 'none', false],
            ['example.com', 'https://example.com', null, 'cross-site', false],
            ['example.com', null, null, 'same-site', false],
            ['www.example.com', 'https://example.com', null, null, false],
            ['example.com', 'https://www.example.com', null, null, false],
            ['staging.example.com', 'https://example.com', null, 'same-site', false],
            ['example.com', 'ftp://example.com', null, null, false],
            ['example.com', 'garbage', null, null, false],
            ['example.com', 'https://evil.test', 'https://example.com/', null, false],
            ['example.com', null, 'https://example.com.evil.test/', null, false],
            ['example.com', null, 'https://example.com@evil.test/', null, false],
            ['', 'https://example.com', null, null, false],
            ['exa mple.com', 'https://exa mple.com', null, null, false],
        ];
        foreach ($matrix as [$host, $origin, $referer, $fetchSite, $expected]) {
            $server = ['HTTP_HOST' => $host];
            if ($origin !== null) {
                $server['HTTP_ORIGIN'] = $origin;
            }
            if ($referer !== null) {
                $server['HTTP_REFERER'] = $referer;
            }
            if ($fetchSite !== null) {
                $server['HTTP_SEC_FETCH_SITE'] = $fetchSite;
            }
            $assert(
                VisitAnalyticsService::isSameOrigin($server) === $expected,
                'isSameOrigin(' . json_encode($server) . ') should be ' . ($expected ? 'true' : 'false')
            );
        }
    });

    $test('visit secret has no public default and the hash refuses an empty key', function () use ($assert): void {
        $names = ['VISIT_ANALYTICS_SALT', 'API_KEY'];
        $saved = [];
        foreach ($names as $name) {
            $saved[$name] = [
                'env' => array_key_exists($name, $_ENV) ? $_ENV[$name] : null,
                'server' => array_key_exists($name, $_SERVER) ? $_SERVER[$name] : null,
                'getenv' => getenv($name),
            ];
        }
        $set = static function (array $values) use ($names): void {
            foreach ($names as $name) {
                unset($_ENV[$name], $_SERVER[$name]);
                putenv($name);
                if (isset($values[$name])) {
                    $_ENV[$name] = $values[$name];
                }
            }
        };

        try {
            $set([]);
            $assert(VisitAnalyticsService::secret() === null, 'Without salt and API key there must be no secret.');
            $set(['VISIT_ANALYTICS_SALT' => '   ', 'API_KEY' => '']);
            $assert(VisitAnalyticsService::secret() === null, 'A blank salt is not a secret.');
            $set(['API_KEY' => 'api-key-value']);
            $assert(VisitAnalyticsService::secret() === 'api-key-value', 'API_KEY is the fallback.');
            $set(['VISIT_ANALYTICS_SALT' => 'salt-value', 'API_KEY' => 'api-key-value']);
            $assert(VisitAnalyticsService::secret() === 'salt-value', 'VISIT_ANALYTICS_SALT wins.');
        } finally {
            foreach ($names as $name) {
                unset($_ENV[$name], $_SERVER[$name]);
                putenv($name);
                if ($saved[$name]['env'] !== null) {
                    $_ENV[$name] = $saved[$name]['env'];
                }
                if ($saved[$name]['server'] !== null) {
                    $_SERVER[$name] = $saved[$name]['server'];
                }
                if ($saved[$name]['getenv'] !== false) {
                    putenv($name . '=' . $saved[$name]['getenv']);
                }
            }
        }

        foreach (['', '  '] as $empty) {
            $threw = false;
            try {
                VisitAnalyticsService::visitorHash('203.0.113.42', 'UA', '2026-10-19', $empty);
            } catch (\InvalidArgumentException) {
                $threw = true;
            }
            $assert($threw, 'visitorHash() must refuse an empty secret.');
        }

        $index = (string) file_get_contents(dirname(__DIR__) . '/public/index.php');
        $secretCheck = strpos($index, 'if ($secret === null) {');
        $assert($secretCheck !== false && $secretCheck < strpos($index, 'RequestThrottle::hit('), 'Missing secret must stop the beacon before throttle and insert.');
    });

    $test('visit throttle key buckets IPv4 by /24 and IPv6 by /48', function () use ($assert): void {
        $key = static fn(string $ip): string => VisitAnalyticsService::throttleKey($ip);
        $assert($key('203.0.113.42') === 'v4:203.0.113.0/24', 'IPv4 key: ' . $key('203.0.113.42'));
        $assert($key('203.0.113.42') === $key('203.0.113.7') && $key(' 203.0.113.255 ') === $key('203.0.113.1'));
        $assert($key('203.0.113.42') !== $key('203.0.114.42'), 'Different /24 must not share a bucket.');
        $assert($key('2001:db8:abcd:12::1') === 'v6:2001:db8:abcd::/48', 'IPv6 key: ' . $key('2001:db8:abcd:12::1'));
        $assert($key('2001:db8:abcd:12::1') === $key('2001:DB8:ABCD:ffff:1:2:3:4'), 'One /48 (any /64 inside) must share a bucket.');
        $assert($key('2001:db8:abcd::1') !== $key('2001:db8:abce::1'), 'Different /48 must not share a bucket.');
        $assert($key('::ffff:203.0.113.5') === 'v4:203.0.113.0/24', 'IPv4-mapped IPv6 must bucket as IPv4: ' . $key('::ffff:203.0.113.5'));
        $assert($key('::ffff:198.51.100.5') !== $key('::ffff:203.0.113.5'));
        $assert($key('unknown') === 'unknown' && $key('') === 'unknown' && $key('1.2.3') === 'unknown');
    });

    $test('visit throttle purge removes only stale visit_beacon files and respects the limit', function () use ($assert): void {
        $dir = sys_get_temp_dir() . '/visit-throttle-test-' . bin2hex(random_bytes(6));
        mkdir($dir, 0775, true);
        $now = 1_800_000_000;
        $old = $now - 7200;
        $file = static function (string $name, int $mtime) use ($dir): string {
            $path = $dir . '/' . $name;
            file_put_contents($path, '{}');
            touch($path, $mtime);
            return $path;
        };

        try {
            $staleBeacon = [];
            for ($i = 0; $i < 5; $i++) {
                $staleBeacon[] = $file('visit_beacon-' . sha1('stale' . $i) . '.json', $old);
            }
            $freshBeacon = $file('visit_beacon-' . sha1('fresh') . '.json', $now - 60);
            $login = $file('login-' . sha1('login') . '.json', $old);
            $reject = $file('visit_reject-foreign_origin.json', $old);
            $odd = $file('visit_beacon-notahash.json', $old);

            $assert(\App\Security\RequestThrottle::purgeStale('visit_beacon', 3600, 2, $dir, $now) === 2, 'Limit per call must hold.');
            $remaining = count(array_filter($staleBeacon, 'is_file'));
            $assert($remaining === 3, 'Expected 3 stale files left after a limited run, got ' . $remaining);
            $assert(\App\Security\RequestThrottle::purgeStale('visit_beacon', 3600, 100, $dir, $now) === 3);
            $assert(count(array_filter($staleBeacon, 'is_file')) === 0, 'All stale beacon files must be gone.');
            $assert(is_file($freshBeacon), 'A recently written beacon file must stay.');
            $assert(is_file($login), 'Other throttle scopes (login) must never be purged.');
            $assert(is_file($reject), 'Rejection counters must not be purged.');
            $assert(is_file($odd), 'Only "<scope>-<sha1>.json" names may be purged.');
            $assert(\App\Security\RequestThrottle::purgeStale('../visit_beacon', 0, 100, $dir, $now) === 0, 'Unsafe scope must be refused.');
            $assert(\App\Security\RequestThrottle::purgeStale('visit_beacon', 0, 0, $dir, $now) === 0, 'Zero limit deletes nothing.');
            $assert(\App\Security\RequestThrottle::purgeStale('visit_beacon', 0, 10, $dir . '/missing', $now) === 0);
        } finally {
            foreach ((array) glob($dir . '/*') as $path) {
                if (is_file((string) $path)) {
                    unlink((string) $path);
                }
            }
            rmdir($dir);
        }
    });

    $test('visit rejection logging is aggregated per reason and interval', function () use ($assert): void {
        $dir = sys_get_temp_dir() . '/visit-reject-test-' . bin2hex(random_bytes(6));
        $now = 1_800_000_000;

        try {
            $assert(VisitAnalyticsService::countRejection('foreign_origin', $dir, $now) === 1, 'First rejection is logged at once.');
            $assert(VisitAnalyticsService::countRejection('foreign_origin', $dir, $now + 10) === null, 'Within the interval nothing is logged.');
            $assert(VisitAnalyticsService::countRejection('foreign_origin', $dir, $now + 20) === null);
            $assert(VisitAnalyticsService::countRejection('missing_secret', $dir, $now + 30) === 1, 'Reasons are counted separately.');
            $assert(VisitAnalyticsService::countRejection('foreign_origin', $dir, $now + 3600) === 3, 'After the interval the accumulated count is logged.');
            $assert(VisitAnalyticsService::countRejection('foreign_origin', $dir, $now + 3610) === null);
            $assert(VisitAnalyticsService::countRejection('../../etc', $dir, $now) === 1, 'Unknown reasons fold into "other".');
            $files = array_map('basename', (array) glob($dir . '/*'));
            sort($files);
            $assert($files === ['visit_reject-foreign_origin.json', 'visit_reject-missing_secret.json', 'visit_reject-other.json'], 'Fixed file set: ' . implode(',', $files));
            foreach ($files as $name) {
                $state = (string) file_get_contents($dir . '/' . $name);
                $assert(!preg_match('~\d+\.\d+\.\d+\.\d+~', $state), 'Rejection state must not contain an IP.');
            }
        } finally {
            foreach ((array) glob($dir . '/*') as $path) {
                if (is_file((string) $path)) {
                    unlink((string) $path);
                }
            }
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
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
        $normalized = VisitAnalyticsService::parsePayload($validBody(['p' => '/<bad path>?q=1']));
        $assert(is_array($normalized) && $normalized['p'] === '/%3Cbad%20path%3E', 'An odd pathname must not drop the beacon.');
        $assert(VisitAnalyticsService::parsePayload($validBody(['p' => 42])) === null, 'A non-string path is an invalid payload.');
        $assert(VisitAnalyticsService::parsePayload($validBody(['w' => '390'])) === null);
        $assert(VisitAnalyticsService::parsePayload($validBody(['a' => 'yes'])) === null);
        $assert(VisitAnalyticsService::parsePayload($validBody(['s' => str_repeat('a', 101)])) === null);
        $assert(VisitAnalyticsService::parsePayload('not json') === null);
        $assert(VisitAnalyticsService::parsePayload('[1,2]') === null);
        $assert(VisitAnalyticsService::parsePayload('') === null);
    });

    $test('visit retention and admin range helpers are bounded', function () use ($assert): void {
        $cutoffs = [
            '2026-10-19' => '2025-08-01',
            '2026-10-01' => '2025-08-01',
            '2026-12-31' => '2025-10-01',
            '2027-04-30' => '2026-02-01', // a plain "-14 months" overflows to 2026-03-02
            '2027-05-31' => '2026-03-01',
            '2028-02-29' => '2026-12-01',
            '2026-01-15' => '2024-11-01',
        ];
        foreach ($cutoffs as $today => $cutoff) {
            $actual = VisitAnalyticsService::retentionCutoff(new DateTimeImmutable($today . ' 23:59:59'));
            $assert($actual === $cutoff, 'retentionCutoff(' . $today . ') = ' . $actual . ', expected ' . $cutoff);
        }
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
        $assert(str_contains($index, 'VisitAnalyticsService::throttleKey($ip),'), 'Beacon throttle must bucket by network.');
        $assert(str_contains($index, 'RequestThrottle::purgeStale(VisitAnalyticsService::THROTTLE_SCOPE'), 'Beacon must lazily purge throttle files.');
        $purge = (string) file_get_contents($root . '/bin/purge-visits.php');
        $assert(str_contains($purge, 'RequestThrottle::purgeStale('), 'purge-visits.php must purge throttle files too.');
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
