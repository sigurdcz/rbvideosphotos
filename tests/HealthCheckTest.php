<?php

declare(strict_types=1);

use App\Services\HealthCheckFallbackStore;
use App\Services\HealthCheckHttpResponder;
use App\Services\HealthCheckService;

function register_health_check_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);

    $test('health response parser accepts only a complete healthy contract', function () use ($assert): void {
        $now = new DateTimeImmutable('2026-10-21T10:00:00+02:00');
        $healthy = HealthCheckService::recordFromResponse([
            'status' => 200,
            'body' => '{"status":"ok","checks":{"application":"ok","database":"ok"}}',
            'duration_ms' => 123,
            'error' => null,
        ], $now);
        $assert($healthy['status'] === 'healthy' && $healthy['database_status'] === 'ok');
        $assert($healthy['checked_at'] === '2026-10-21 08:00:00');
        $assert($healthy['response_time_ms'] === 123 && $healthy['error_code'] === null);
        $assert(preg_match('~^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$~', $healthy['check_uuid']) === 1);

        $badStatus = HealthCheckService::recordFromResponse([
            'status' => 503, 'body' => '{"status":"error","checks":{"database":"error"}}',
            'duration_ms' => 10, 'error' => null,
        ], $now);
        $assert($badStatus['status'] === 'unhealthy' && $badStatus['error_code'] === 'http_503');
        $assert($badStatus['database_status'] === 'error');

        $badJson = HealthCheckService::recordFromResponse([
            'status' => 200, 'body' => '<html>proxy error</html>', 'duration_ms' => 9, 'error' => null,
        ], $now);
        $assert($badJson['status'] === 'unhealthy' && $badJson['error_code'] === 'invalid_json');

        $network = HealthCheckService::recordFromResponse([
            'status' => null, 'body' => '', 'duration_ms' => 15000, 'error' => 'network_error',
        ], $now);
        $assert($network['status'] === 'unhealthy' && $network['error_code'] === 'network_error');
    });

    $test('health target URL is explicit and limited to HTTP(S)', function () use ($assert): void {
        $assert(HealthCheckService::targetUrl(null, 'https://example.test/') === 'https://example.test/api/health');
        $assert(HealthCheckService::targetUrl('https://status.example.test/custom', 'https://ignored.test') === 'https://status.example.test/custom');
        foreach (['', 'ftp://example.test/api/health', 'not-a-url'] as $url) {
            $threw = false;
            try { HealthCheckService::targetUrl($url, null); } catch (InvalidArgumentException) { $threw = true; }
            $assert($threw, 'Invalid target must fail visibly: ' . $url);
        }
    });

    $test('health responder returns safe JSON and correct status without a session', function () use ($assert): void {
        $previousMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            ob_start();
            $handled = (new HealthCheckHttpResponder(static fn(): bool => true))->serve('/api/health');
            $body = (string) ob_get_clean();
            $payload = json_decode($body, true);
            $assert($handled && http_response_code() === 200);
            $assert($payload === ['status' => 'ok', 'checked_at' => $payload['checked_at'], 'checks' => ['application' => 'ok', 'database' => 'ok']]);
            $assert(!str_contains($body, 'password') && !str_contains($body, 'host'));

            ob_start();
            (new HealthCheckHttpResponder(static fn(): bool => false))->serve('/api/health');
            $unhealthy = json_decode((string) ob_get_clean(), true);
            $assert(http_response_code() === 503 && $unhealthy['status'] === 'error' && $unhealthy['checks']['database'] === 'error');

            $assert((new HealthCheckHttpResponder(static fn(): bool => true))->serve('/other') === false);
        } finally {
            if ($previousMethod === null) unset($_SERVER['REQUEST_METHOD']); else $_SERVER['REQUEST_METHOD'] = $previousMethod;
            http_response_code(200);
        }
    });

    $test('health fallback replays records once and preserves malformed data', function () use ($assert): void {
        $dir = sys_get_temp_dir() . '/health-fallback-' . bin2hex(random_bytes(6));
        $path = $dir . '/pending.jsonl';
        $store = new HealthCheckFallbackStore($path);
        $records = [];
        $first = ['check_uuid' => '11111111-1111-4111-8111-111111111111', 'status' => 'unhealthy'];
        $second = ['check_uuid' => '22222222-2222-4222-8222-222222222222', 'status' => 'healthy'];
        try {
            $store->append($first);
            $store->append($second);
            $count = $store->replay(static function (array $record) use (&$records): void { $records[] = $record; });
            $assert($count === 2 && count($records) === 2);
            $assert($records[0]['source'] === 'recovered' && $records[1]['source'] === 'recovered');
            $assert((string) file_get_contents($path) === '' && $store->replay(static function (): void {}) === 0);

            file_put_contents($path, "not-json\n", LOCK_EX);
            $threw = false;
            try { $store->replay(static function (): void {}); } catch (RuntimeException) { $threw = true; }
            $assert($threw && (string) file_get_contents($path) === "not-json\n", 'Malformed fallback must remain visible and preserved.');
        } finally {
            if (is_file($path)) unlink($path);
            if (is_dir($dir)) rmdir($dir);
        }
    });

    $test('health endpoint, migration, cron and admin wiring stay connected', function () use ($assert, $root): void {
        $index = (string) file_get_contents($root . '/public/index.php');
        $health = strpos($index, 'HealthCheckHttpResponder())->serve');
        $visit = strpos($index, 'if (tryRecordVisitBeacon($earlyUri))');
        $auth = strpos($index, "Auth::start();\n");
        $assert($health !== false && $health < $visit && $visit < $auth, 'Health must run before visit analytics and session start.');
        $assert(!str_contains((string) file_get_contents($root . '/app/Services/SitemapGenerator.php'), '/api/health'));

        $migration = (string) file_get_contents($root . '/database/migrations/110_create_health_checks.sql');
        $assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS health_checks'));
        $assert(str_contains($migration, 'UNIQUE KEY health_checks_uuid'));
        $assert(str_contains((string) file_get_contents($root . '/database/schema.sql'), 'health_checks (migrace 110)'));

        $cron = (string) file_get_contents($root . '/bin/health-check.php');
        $assert(str_contains($cron, 'health-check-pending.jsonl') && str_contains($cron, 'exit($record[\'status\'] === \'healthy\' ? 0 : 1)'));
        $admin = (string) file_get_contents($root . '/admin/views/analytics/index.php');
        foreach (['Zdraví webu', 'Monitoring neběží', 'Denní zdraví', 'Návštěvnost (vlastní měření)'] as $text) {
            $assert(str_contains($admin, $text), 'Admin analytics missing: ' . $text);
        }
    });
}
