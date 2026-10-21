<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Repositories\HealthCheckRepository;
use App\Services\HealthCheckFallbackStore;
use App\Services\HealthCheckService;

$root = dirname(__DIR__);
Env::load($root . '/.env');
date_default_timezone_set(Config::get('app', 'timezone', 'Europe/Prague'));
$fallback = new HealthCheckFallbackStore($root . '/storage/logs/health-check-pending.jsonl');

try {
    $target = HealthCheckService::targetUrl(Env::get('HEALTH_CHECK_URL'), Env::get('APP_URL'));
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(2);
}

$record = HealthCheckService::recordFromResponse(HealthCheckService::request($target));
$stored = false;
$replayed = 0;
try {
    $repository = new HealthCheckRepository(Database::createConnection());
    $siteId = $repository->firstSiteId();
    $replayed = $fallback->replay(static function (array $pending) use ($repository, $siteId): void {
        $repository->insert($siteId, $pending);
    });
    $repository->insert($siteId, $record);
    $repository->purgeBefore(HealthCheckService::retentionCutoff());
    $stored = true;
} catch (Throwable $e) {
    try {
        $fallback->append($record);
    } catch (Throwable $fallbackError) {
        Logger::critical('health-check', 'Health result could not be persisted', [
            'database_error_type' => $e::class,
            'fallback_error_type' => $fallbackError::class,
        ]);
        fwrite(STDERR, "Health result was not persisted: database and fallback storage failed.\n");
        exit(2);
    }
    Logger::error('health-check', 'Health result stored in pending fallback', ['error_type' => $e::class]);
}

$message = sprintf(
    "%s status=%s http=%s db=%s duration_ms=%d storage=%s replayed=%d\n",
    (string) $record['checked_at'],
    (string) $record['status'],
    $record['http_status'] === null ? 'none' : (string) $record['http_status'],
    (string) $record['database_status'],
    (int) $record['response_time_ms'],
    $stored ? 'database' : 'pending-jsonl',
    $replayed
);
fwrite($record['status'] === 'healthy' ? STDOUT : STDERR, $message);
exit($record['status'] === 'healthy' ? 0 : 1);
