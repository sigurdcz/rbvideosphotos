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

$tableExists = static function (PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $stmt->execute([$table]);

    return (int) $stmt->fetchColumn() > 0;
};

$columnExists = static function (PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
};

$columnEnumContains = static function (PDO $pdo, string $table, string $column, string $value): bool {
    $stmt = $pdo->prepare(
        'SELECT COLUMN_TYPE
         FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $stmt->execute([$table, $column]);
    $columnType = (string) $stmt->fetchColumn();

    return $columnType !== '' && str_contains($columnType, "'" . $value . "'");
};

$migrationAlreadySatisfied = static function (PDO $pdo, string $name) use ($tableExists, $columnExists, $columnEnumContains): bool {
    return match ($name) {
        '002_add_autofill_disabled_to_content_blocks.sql' => $columnExists($pdo, 'content_blocks', 'autofill_disabled'),
        '004_create_service_items.sql' => $tableExists($pdo, 'service_items'),
        '005_add_active_theme_key_to_sites.sql' => $columnExists($pdo, 'sites', 'active_theme_key'),
        '006_expand_content_blocks_block_type_to_video.sql' => $columnEnumContains($pdo, 'content_blocks', 'block_type', 'video'),
        '019_add_media_image_quality_to_sites.sql' => $columnExists($pdo, 'sites', 'media_image_quality'),
        default => false,
    };
};

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

    $pdo->beginTransaction();
    try {
        $pdo->exec($sql);
        $stmt = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)');
        $stmt->execute([$name]);
        $pdo->commit();
        echo "OK   {$name}\n";
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

echo "Done.\n";
