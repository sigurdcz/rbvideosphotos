<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Services\DemoStateFingerprintService;

Env::load(BASE_PATH . '/.env');

try {
    $service = new DemoStateFingerprintService(Database::getInstance(), BASE_PATH);
    $report = $service->report();
    $hashOnly = in_array('--hash', $argv, true);

    if ($hashOnly) {
        fwrite(STDOUT, $report['fingerprint'] . PHP_EOL);
        exit(0);
    }

    $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Fingerprint report nelze serializovat.');
    }
    fwrite(STDOUT, $json . PHP_EOL);
} catch (Throwable $e) {
    fwrite(STDERR, 'Fingerprint selhal: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
