<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

use App\Core\Env;

Env::load(BASE_PATH . '/.env');

$host = Env::get('DB_HOST', '127.0.0.1');
$port = Env::get('DB_PORT', '3306');
$db   = Env::get('DB_NAME', 'seo_editor_cms');
$user = Env::get('DB_USER', 'seo_editor_app');
$pass = Env::get('DB_PASS', '');

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db),
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        migration VARCHAR(255) NOT NULL UNIQUE,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$migrationAlreadySatisfied = require BASE_PATH . '/database/migration-schema-state.php';

$applied = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN) ?: [];
$files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
sort($files, SORT_NATURAL);

foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        echo "SKIP {$name}\n";
        continue;
    }

    if ($migrationAlreadySatisfied($pdo, $name)) {
        $stmt = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)');
        $stmt->execute([$name]);
        echo "SKIP {$name} (schema already matches)\n";
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException('Unable to read migration: ' . $name);
    }

    $transactionalDdl = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql';
    if ($transactionalDdl) {
        $pdo->beginTransaction();
    }
    try {
        $pdo->exec($sql);
        $stmt = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)');
        $stmt->execute([$name]);
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        echo "OK   {$name}\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

echo "Done.\n";
