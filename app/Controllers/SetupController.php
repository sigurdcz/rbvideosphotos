<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Auth;
use App\Core\Version;
use App\Repositories\PageRepository;
use App\Repositories\SiteRepository;
use App\Services\DemoContentSeeder;
use App\Services\DemoStateFingerprintService;
use App\Services\SetupEnvironmentService;
use PDO;

class SetupController extends Controller
{
    public function index(array $params): void
    {
        $this->requireAdmin();

        $pages = new PageRepository();
        $sites = new SiteRepository();
        $site = $sites->findFirst();
        $pageCount = $site ? count($pages->allBySite($site['id'])) : 0;

        $diag = [
            'version' => Version::APP,
            'db_ok' => false,
            'db_error' => '',
            'pages_total' => 0,
            'homepage' => null,
            'demo_fingerprint' => '',
            'fingerprint_error' => '',
        ];
        try {
            $db = Database::getInstance();
            $diag['db_ok'] = true;
            $diag['pages_total'] = (int) $db->query('SELECT COUNT(*) FROM pages')->fetchColumn();
            $diag['homepage'] = $pages->findByUrlPath('');
            try {
                $diag['demo_fingerprint'] = (new DemoStateFingerprintService($db))->fingerprint();
            } catch (\Throwable $e) {
                $diag['fingerprint_error'] = $e->getMessage();
            }
        } catch (\Throwable $e) {
            $diag['db_error'] = $e->getMessage();
        }

        $this->view('setup.index', [
            'pageCount' => $pageCount,
            'site' => $site,
            'diag' => $diag,
        ]);
    }

    public function run(array $params): void
    {
        $this->requireAdmin();
        $this->csrfCheck();

        try {
            $output = (new SetupEnvironmentService())->runBaseSeed();
            $message = 'Data byla úspěšně načtena.';
            if ($output !== '') {
                $message .= ' ' . $output;
            }
            $this->flash('success', $message);
        } catch (\Throwable $e) {
            $this->flash('error', 'Chyba při seedování: ' . $e->getMessage());
        }

        $this->redirect('/admin/setup');
    }

    public function armageddon(array $params): void
    {
        $this->requireAdmin();
        $this->csrfCheck();

        try {
            $result = (new SetupEnvironmentService())->resetBaseState();
            $output = (string)($result['seed_output'] ?? '');

            $message = 'Armagedon dokončen. Runtime stav byl vyčištěn, databáze resetována a základní seed znovu nahrán.';
            if ($output !== '') {
                $message .= ' ' . $output;
            }
            $this->flash('success', $message);
        } catch (\Throwable $e) {
            $this->flash('error', 'Armagedon selhal: ' . $e->getMessage());
        }

        $this->redirect('/admin/setup');
    }

    public function demoContent(array $params): void
    {
        $this->requireAdmin();
        $this->csrfCheck();

        try {
            $site = (new SiteRepository())->findFirst();
            $user = Auth::user();
            $result = (new DemoContentSeeder())->run(
                $site ? (int)$site['id'] : null,
                $user ? (int)$user['id'] : null,
                true
            );

            $message = 'Demo obsah byl načten: '
                . (int)$result['blog_posts'] . ' článků, '
                . (int)$result['portfolio_entries'] . ' prací, '
                . (int)$result['reviews'] . ' recenzí.';
            $message .= ' Aktivní motiv: ' . (string)($result['active_theme'] ?? 'nenastaveno') . '.';
            if (!empty($result['published'])) {
                $message .= ' Web byl znovu publikován.';
            }
            $this->flash('success', $message);
        } catch (\Throwable $e) {
            $this->flash('error', 'Demo content seed selhal: ' . $e->getMessage());
        }

        $this->redirect('/admin/setup');
    }

    private function resetDatabase(): void
    {
        $pdo = Database::getInstance();
        $databaseName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($databaseName === '') {
            throw new \RuntimeException('Nepodařilo se zjistit aktivní databázi.');
        }

        $stmt = $pdo->prepare(
            'SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME'
        );
        $stmt->execute([$databaseName, 'BASE TABLE']);
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($tables as $table) {
                $safeTable = str_replace('`', '``', (string) $table);
                $pdo->exec("DROP TABLE IF EXISTS `{$safeTable}`");
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function runMigrations(): void
    {
        $pdo = Database::getInstance();
        $migrationsDir = dirname(__DIR__, 2) . '/database/migrations';
        $files = glob($migrationsDir . '/*.sql') ?: [];
        sort($files, SORT_NATURAL);

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                migration VARCHAR(255) NOT NULL UNIQUE,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        foreach ($files as $file) {
            $migrationName = basename($file);
            $applied = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN) ?: [];
            if (in_array($migrationName, $applied, true)) {
                continue;
            }

            if ($this->isMigrationAlreadySatisfied($pdo, $migrationName)) {
                $this->markMigrationApplied($pdo, $migrationName);
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \RuntimeException('Nepodařilo se načíst migraci: ' . $migrationName);
            }

            $pdo->exec($sql);
            $this->markMigrationApplied($pdo, $migrationName);
        }
    }

    private function isMigrationAlreadySatisfied(PDO $pdo, string $migrationName): bool
    {
        return match ($migrationName) {
            '002_add_autofill_disabled_to_content_blocks.sql' => $this->columnExists($pdo, 'content_blocks', 'autofill_disabled'),
            '004_create_service_items.sql' => $this->tableExists($pdo, 'service_items'),
            '005_add_active_theme_key_to_sites.sql' => $this->columnExists($pdo, 'sites', 'active_theme_key'),
            '006_expand_content_blocks_block_type_to_video.sql' => $this->columnEnumContains($pdo, 'content_blocks', 'block_type', 'video'),
            default => false,
        };
    }

    private function markMigrationApplied(PDO $pdo, string $migrationName): void
    {
        $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)')
            ->execute([$migrationName]);
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([$table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $stmt->execute([$table, $column]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function columnEnumContains(PDO $pdo, string $table, string $column, string $value): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COLUMN_TYPE
             FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $stmt->execute([$table, $column]);
        $columnType = (string) $stmt->fetchColumn();

        return $columnType !== '' && str_contains($columnType, "'" . $value . "'");
    }

    private function purgeDirectoryContents(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            throw new \RuntimeException('Nepodařilo se načíst obsah adresáře: ' . $dir);
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->purgeDirectoryContents($path);
                if (!rmdir($path)) {
                    throw new \RuntimeException('Nepodařilo se smazat adresář: ' . $path);
                }
                continue;
            }

            if (!unlink($path)) {
                throw new \RuntimeException('Nepodařilo se smazat soubor: ' . $path);
            }
        }
    }

    private function ensurePublishedFallbackIndex(): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/published';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Nepodařilo se vytvořit storage/published.');
        }

        $fallback = <<<PHP
<?php
require dirname(__DIR__, 2) . '/public/index.php';
PHP;

        if (file_put_contents($dir . '/index.php', $fallback) === false) {
            throw new \RuntimeException('Nepodařilo se vytvořit fallback index.php v storage/published.');
        }
    }
}
