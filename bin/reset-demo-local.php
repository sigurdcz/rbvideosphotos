<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Services\DemoStateFingerprintService;
use App\Services\SetupEnvironmentService;

Env::load(BASE_PATH . '/.env');

if (!in_array('--confirm-local', $argv, true)) {
    fwrite(STDERR, "Odmítnuto: destruktivní reset vyžaduje přepínač --confirm-local.\n");
    exit(2);
}

$host = trim((string)Env::get('DB_HOST', ''));
if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
    fwrite(STDERR, 'Odmítnuto: DB_HOST není lokální (' . ($host !== '' ? $host : 'prázdný') . ").\n");
    exit(2);
}

try {
    $db = Database::getInstance();
    $result = (new SetupEnvironmentService($db, BASE_PATH))->resetToDemo(true);
    $fingerprint = (new DemoStateFingerprintService($db, BASE_PATH))->fingerprint();

    fwrite(STDOUT, "Lokální Armagedon -> Demo obsah dokončen.\n");
    fwrite(STDOUT, 'Migrace: ' . count($result['base']['migrations'] ?? []) . "\n");
    fwrite(STDOUT, 'Motiv: ' . (string)($result['demo']['active_theme'] ?? '') . "\n");
    fwrite(STDOUT, 'Fingerprint: ' . $fingerprint . "\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'Lokální reset selhal: ' . $e->getMessage() . "\n");
    exit(1);
}
