<?php
declare(strict_types=1);

namespace App\Services {
    // Substitute content effects only. The web runner and all historical SQL
    // execute unchanged on a disposable real MariaDB database.
    final class ApplicationCompatibleMigrationService
    {
        public static int $calls = 0;
        public function supports(string $name): bool
        {
            $source = file_get_contents(dirname(__DIR__) . '/app/Services/ApplicationCompatibleMigrationService.php');
            preg_match('/SUPPORTED_MIGRATIONS = \[(.*?)\];/s', $source, $matches);
            return str_contains($matches[1], "'" . $name . "'");
        }
        public function apply(string $name): string
        {
            $db = \App\Core\Database::getInstance();
            // These are dependencies of current handlers that older migrations
            // invoke. The previous single-pass runner fails this assertion.
            $db->query('SELECT schema_name FROM seo_settings LIMIT 0');
            $db->query('SELECT sort_order FROM blog_posts LIMIT 0');
            $db->query('SELECT id FROM shared_sections LIMIT 0');
            self::$calls++;
            return 'verified-schema-before-content';
        }
    }
}

namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    $root = dirname(__DIR__);
    require $root . '/vendor/autoload.php';
    \App\Core\Env::load($root . '/.env');
    $dbName = 'rbvp_order_test_' . bin2hex(random_bytes(6));
    $admin = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', \App\Core\Env::get('DB_HOST'), \App\Core\Env::get('DB_PORT', '3306')), \App\Core\Env::get('DB_USER'), \App\Core\Env::get('DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $admin->exec('CREATE DATABASE `' . $dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $admin->exec('USE `' . $dbName . '`');
    $admin->exec(file_get_contents($root . '/database/migrations/001_schema.sql'));
    $admin->exec("INSERT INTO sites (name, domain) VALUES ('Upgrade fixture', 'https://example.invalid')");
    $admin->exec('CREATE TABLE schema_migrations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(255) NOT NULL UNIQUE, applied_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
    $admin->exec("INSERT INTO schema_migrations (migration) VALUES ('001_schema.sql')");
    $_ENV['DB_NAME'] = $dbName;
    $_SERVER['DB_NAME'] = $dbName;
    $_ENV['MIGRATE_TOKEN'] = 'disposable-upgrade-test';
    $_GET['token'] = 'disposable-upgrade-test';
    ob_start();
    register_shutdown_function(function () use ($admin, $dbName, $root): void {
        $output = ob_get_clean();
        $failed = false;
        try {
            $expected = count(glob($root . '/database/migrations/*.sql'));
            $applied = (int) $admin->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
            if ($applied !== $expected || \App\Services\ApplicationCompatibleMigrationService::$calls !== 16 || str_contains($output, 'Migrace selhala')) {
                fwrite(STDERR, "FAIL: SQL/content upgrade order, applied {$applied}/{$expected}\n" . strip_tags($output));
                $failed = true;
            } else {
                echo "PASS: all {$applied} migrations complete; 16 content handlers ran after schema dependencies existed.\n";
            }
        } finally {
            // Only this process's random, freshly-created database is removed.
            $admin->exec('DROP DATABASE `' . $dbName . '`');
        }
        if ($failed) { exit(1); }
    });
    require $root . '/migrate-util.php';
}
