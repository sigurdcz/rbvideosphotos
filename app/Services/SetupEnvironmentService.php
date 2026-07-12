<?php

namespace App\Services;

use App\Core\Database;
use App\Repositories\SiteRepository;
use PDO;

final class SetupEnvironmentService
{
    public const BASE_SEED = 'database/seeds/seed.php';

    private PDO $db;
    private string $basePath;

    public function __construct(?PDO $db = null, ?string $basePath = null)
    {
        $this->db = $db ?: Database::getInstance();
        $this->basePath = rtrim($basePath ?: dirname(__DIR__, 2), '/\\');
    }

    public function resetBaseState(): array
    {
        $cleared = (new SetupFilesystemResetService($this->basePath))->reset();
        $this->ensurePublishedFallbackIndex();
        $this->resetDatabase();
        $migrations = $this->runMigrations();
        $seedOutput = $this->runBaseSeed();

        return [
            'cleared' => $cleared,
            'migrations' => $migrations,
            'seed_output' => $seedOutput,
        ];
    }

    public function resetToDemo(bool $publish = true): array
    {
        $base = $this->resetBaseState();
        $site = (new SiteRepository())->findFirst();
        $userId = (int)($this->db->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
        $demo = (new DemoContentSeeder())->run(
            $site ? (int)$site['id'] : null,
            $userId > 0 ? $userId : null,
            $publish && $userId > 0
        );

        return ['base' => $base, 'demo' => $demo];
    }

    public function runBaseSeed(): string
    {
        $seedFile = $this->basePath . '/' . self::BASE_SEED;
        if (!is_file($seedFile)) {
            throw new \RuntimeException('Zakladni seed nebyl nalezen: ' . self::BASE_SEED);
        }

        $argv = [$seedFile];
        ob_start();
        try {
            include $seedFile;
            return trim((string)ob_get_clean());
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    private function resetDatabase(): void
    {
        $databaseName = (string)$this->db->query('SELECT DATABASE()')->fetchColumn();
        if ($databaseName === '') {
            throw new \RuntimeException('Nepodarilo se zjistit aktivni databazi.');
        }

        $statement = $this->db->prepare(
            'SELECT TABLE_NAME FROM information_schema.tables
             WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME'
        );
        $statement->execute([$databaseName, 'BASE TABLE']);
        $tables = $statement->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $this->db->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($tables as $table) {
                $safeTable = str_replace('`', '``', (string)$table);
                $this->db->exec("DROP TABLE IF EXISTS `{$safeTable}`");
            }
        } finally {
            $this->db->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function runMigrations(): array
    {
        $migrationsDirectory = $this->basePath . '/database/migrations';
        $files = glob($migrationsDirectory . '/*.sql') ?: [];
        sort($files, SORT_NATURAL);

        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                migration VARCHAR(255) NOT NULL UNIQUE,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $appliedNow = [];
        foreach ($files as $file) {
            $migrationName = basename($file);
            $applied = $this->db->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN) ?: [];
            if (in_array($migrationName, $applied, true)) {
                continue;
            }

            if ($this->isMigrationAlreadySatisfied($migrationName)) {
                $this->markMigrationApplied($migrationName);
                $appliedNow[] = $migrationName . ':satisfied';
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \RuntimeException('Nepodarilo se nacist migraci: ' . $migrationName);
            }
            $this->db->exec($sql);
            $this->markMigrationApplied($migrationName);
            $appliedNow[] = $migrationName;
        }

        return $appliedNow;
    }

    private function isMigrationAlreadySatisfied(string $migrationName): bool
    {
        return match ($migrationName) {
            '002_add_autofill_disabled_to_content_blocks.sql' => $this->columnExists('content_blocks', 'autofill_disabled'),
            '004_create_service_items.sql' => $this->tableExists('service_items'),
            '005_add_active_theme_key_to_sites.sql' => $this->columnExists('sites', 'active_theme_key'),
            '006_expand_content_blocks_block_type_to_video.sql' => $this->columnEnumContains('content_blocks', 'block_type', 'video'),
            default => false,
        };
    }

    private function markMigrationApplied(string $migrationName): void
    {
        $this->db->prepare('INSERT INTO schema_migrations (migration) VALUES (?)')->execute([$migrationName]);
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $statement->execute([$table]);
        return (int)$statement->fetchColumn() > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $statement->execute([$table, $column]);
        return (int)$statement->fetchColumn() > 0;
    }

    private function columnEnumContains(string $table, string $column, string $value): bool
    {
        $statement = $this->db->prepare(
            'SELECT COLUMN_TYPE FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $statement->execute([$table, $column]);
        $columnType = (string)$statement->fetchColumn();
        return $columnType !== '' && str_contains($columnType, "'{$value}'");
    }

    private function ensurePublishedFallbackIndex(): void
    {
        $directory = $this->basePath . '/storage/published';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Nepodarilo se vytvorit storage/published.');
        }

        $fallback = <<<'PHP'
<?php
require dirname(__DIR__, 2) . '/public/index.php';
PHP;
        if (file_put_contents($directory . '/index.php', $fallback) === false) {
            throw new \RuntimeException('Nepodarilo se vytvorit fallback storage/published/index.php.');
        }
    }
}
