<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Version;
use App\Repositories\PageRepository;
use App\Repositories\SiteRepository;
use PDO;

class SetupController extends Controller
{
    public function index(array $params): void
    {
        $this->requireAuth();

        $pages = new PageRepository();
        $sites = new SiteRepository();
        $site = $sites->findFirst();
        $pageCount = $site ? count($pages->allBySite($site['id'])) : 0;

        $diag = ['version' => Version::APP, 'db_ok' => false, 'db_error' => '', 'pages_total' => 0, 'homepage' => null];
        try {
            $db = Database::getInstance();
            $diag['db_ok'] = true;
            $diag['pages_total'] = (int) $db->query('SELECT COUNT(*) FROM pages')->fetchColumn();
            $diag['homepage'] = $pages->findByUrlPath('');
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
        $this->requireAuth();
        $this->csrfCheck();

        try {
            $output = $this->runSeed();
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
        $this->requireAuth();
        $this->csrfCheck();

        try {
            $this->purgeDirectoryContents(dirname(__DIR__, 2) . '/storage/media');
            $this->resetDatabase();
            $this->runMigrations();
            $output = $this->runSeed();

            $message = 'Armagedon dokončen. Média byla smazána, databáze resetována a seed znovu nahrán.';
            if ($output !== '') {
                $message .= ' ' . $output;
            }
            $this->flash('success', $message);
        } catch (\Throwable $e) {
            $this->flash('error', 'Armagedon selhal: ' . $e->getMessage());
        }

        $this->redirect('/admin/setup');
    }

    private function runSeed(): string
    {
        $seedFile = dirname(__DIR__, 2) . '/database/seeds/seed_photographer.php';
        if (!file_exists($seedFile)) {
            throw new \RuntimeException('Seed soubor nenalezen.');
        }

        $argv = ['seed_photographer.php'];
        ob_start();
        try {
            include $seedFile;
            return trim((string) ob_get_clean());
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
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
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \RuntimeException('Nepodařilo se načíst migraci: ' . basename($file));
            }

            $pdo->exec($sql);
            $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)')
                ->execute([basename($file)]);
        }
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
}
