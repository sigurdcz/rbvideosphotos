<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\SiteRepository;
use PDO;
use RuntimeException;
use Throwable;

/** Browser-driven, persistent Demo seed for hosts without CLI or workers. */
final class DemoContentBatchService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    /** @return array<string,mixed> */
    public function start(int $userId): array
    {
        $site = (new SiteRepository())->findFirst();
        if (!$site) {
            throw new RuntimeException('Web nebyl nalezen. Nejprve spusťte základní seed.');
        }
        $siteId = (int)$site['id'];
        $existing = $this->findBySite($siteId);
        if ($existing && in_array($existing['status'], ['running', 'failed'], true)) {
            return $this->present($existing);
        }

        $token = bin2hex(random_bytes(32));
        $total = $this->totalUnits();
        $statement = $this->db->prepare(
            'INSERT INTO demo_seed_jobs
             (site_id, user_id, job_token, status, phase, phase_cursor, completed_units, total_units, message, last_error, result_json, lock_token, lock_expires_at, started_at, updated_at, finished_at)
             VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?, NULL, ?, NULL, NULL, NOW(), NOW(), NULL)
             ON DUPLICATE KEY UPDATE
               user_id = VALUES(user_id), job_token = VALUES(job_token), status = VALUES(status),
               phase = VALUES(phase), phase_cursor = 0, completed_units = 0,
               total_units = VALUES(total_units), message = VALUES(message), last_error = NULL,
               result_json = VALUES(result_json), lock_token = NULL, lock_expires_at = NULL,
               started_at = NOW(), updated_at = NOW(), finished_at = NULL'
        );
        $statement->execute([$siteId, $userId, $token, 'running', 'validate', $total, 'Kontrola zdrojových dat', '{}']);
        return $this->present($this->requireBySite($siteId));
    }

    /** @return array<string,mixed>|null */
    public function status(): ?array
    {
        $site = (new SiteRepository())->findFirst();
        if (!$site) {
            return null;
        }
        $row = $this->findBySite((int)$site['id']);
        return $row ? $this->present($row) : null;
    }

    /** @return array<string,mixed> */
    public function retry(string $jobToken, int $userId): array
    {
        $row = $this->requireByToken($jobToken);
        if ($row['status'] !== 'failed') {
            return $this->present($row);
        }
        $statement = $this->db->prepare(
            "UPDATE demo_seed_jobs SET status = 'running', user_id = ?, last_error = NULL,
             message = 'Opakování poslední dávky', lock_token = NULL, lock_expires_at = NULL,
             updated_at = NOW() WHERE id = ?"
        );
        $statement->execute([$userId, $row['id']]);
        return $this->present($this->requireByToken($jobToken));
    }

    /** @return array<string,mixed> */
    public function step(string $jobToken, int $userId): array
    {
        $job = $this->requireByToken($jobToken);
        if ($job['status'] !== 'running') {
            return $this->present($job);
        }

        $lockToken = bin2hex(random_bytes(32));
        $lock = $this->db->prepare(
            'UPDATE demo_seed_jobs SET lock_token = ?, lock_expires_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE), updated_at = NOW()
             WHERE id = ? AND status = ? AND (lock_token IS NULL OR lock_expires_at < NOW())'
        );
        $lock->execute([$lockToken, $job['id'], 'running']);
        if ($lock->rowCount() !== 1) {
            $current = $this->requireByToken($jobToken);
            $result = $this->present($current);
            $result['busy'] = true;
            return $result;
        }

        try {
            $job = $this->requireLocked((int)$job['id'], $lockToken);
            $outcome = $this->executeUnit($job, $userId);
            $this->checkpoint($job, $outcome, $lockToken);
        } catch (Throwable $error) {
            $this->markFailed((int)$job['id'], $lockToken, $error);
        }

        return $this->present($this->requireByToken($jobToken));
    }

    /** @param array<string,mixed> $job @return array<string,mixed> */
    private function executeUnit(array $job, int $userId): array
    {
        $siteId = (int)$job['site_id'];
        $cursor = (int)$job['phase_cursor'];
        $phase = (string)$job['phase'];
        $seeder = new DemoContentSeeder();

        switch ($phase) {
            case 'validate':
                if ((new ThemeTemplateRegistry())->find(DemoContentSeeder::DEFAULT_THEME_KEY) === null) {
                    throw new RuntimeException('Výchozí Demo motiv nebyl nalezen: ' . DemoContentSeeder::DEFAULT_THEME_KEY);
                }
                $seeder->demoAssetBatchCount();
                (new WeddingBlogDemoContentImporter())->preflight();
                (new PortfolioDemoContentImporter())->preflight();
                return $this->advance('base_media', 0, 'Import základních médií');

            case 'base_media':
                $count = $seeder->demoAssetBatchCount();
                $result = $seeder->seedDemoAssetBatchItem($siteId, $cursor);
                return $cursor + 1 < $count
                    ? $this->advance('base_media', $cursor + 1, 'Základní médium: ' . $result['label'])
                    : $this->advance('default_poster', 0, 'Základní média dokončena');

            case 'default_poster':
            case 'base_blog':
            case 'homepage':
            case 'business_content':
            case 'services':
            case 'seo_theme':
            case 'publish':
                $result = $seeder->runBatchContentPhase($phase, $siteId, $userId);
                return $this->advance($this->nextPhase($phase), 0, (string)$result['label'], $result);

            case 'wedding_prepare':
                (new WeddingBlogDemoContentImporter())->prepareBatch($siteId);
                return $this->advance('wedding_items', 0, 'Svatební blog připraven');

            case 'wedding_items':
                $importer = new WeddingBlogDemoContentImporter();
                $result = $importer->importBatchItem($siteId, $cursor);
                return $cursor + 1 < $importer->batchCount()
                    ? $this->advance('wedding_items', $cursor + 1, 'Článek: ' . $result['label'], $result)
                    : $this->advance('portfolio_prepare', 0, 'Svatební blog dokončen', $result);

            case 'portfolio_prepare':
                (new PortfolioDemoContentImporter())->prepareBatch($siteId);
                return $this->advance('portfolio_items', 0, 'Portfolio připraveno');

            case 'portfolio_items':
                $importer = new PortfolioDemoContentImporter();
                $result = $importer->importBatchItem($siteId, $cursor);
                return $cursor + 1 < $importer->batchCount()
                    ? $this->advance('portfolio_items', $cursor + 1, 'Galerie: ' . $result['label'], $result)
                    : $this->advance('homepage', 0, 'Portfolio dokončeno', $result);

            case 'fingerprint':
                $hash = (new DemoStateFingerprintService($this->db))->fingerprint();
                return $this->advance('complete', 0, 'Demo obsah byl úspěšně vytvořen', ['fingerprint' => $hash], true);
        }
        throw new RuntimeException('Neznámá fáze dávkového Demo seedu: ' . $phase);
    }

    private function nextPhase(string $phase): string
    {
        return match ($phase) {
            'default_poster' => 'base_blog',
            'base_blog' => 'wedding_prepare',
            'homepage' => 'business_content',
            'business_content' => 'services',
            'services' => 'seo_theme',
            'seo_theme' => 'publish',
            'publish' => 'fingerprint',
            default => throw new RuntimeException('Fáze nemá následníka: ' . $phase),
        };
    }

    /** @return array{phase:string,cursor:int,message:string,result:array<string,mixed>,complete:bool} */
    private function advance(string $phase, int $cursor, string $message, array $result = [], bool $complete = false): array
    {
        return compact('phase', 'cursor', 'message', 'result', 'complete');
    }

    /** @param array<string,mixed> $job @param array<string,mixed> $outcome */
    private function checkpoint(array $job, array $outcome, string $lockToken): void
    {
        $result = json_decode((string)($job['result_json'] ?? '{}'), true);
        if (!is_array($result)) {
            $result = [];
        }
        if ($outcome['result'] !== []) {
            $result[(string)$job['phase'] . ':' . (int)$job['phase_cursor']] = $outcome['result'];
        }
        $completed = min((int)$job['total_units'], (int)$job['completed_units'] + 1);
        $status = $outcome['complete'] ? 'completed' : 'running';
        $statement = $this->db->prepare(
            'UPDATE demo_seed_jobs SET status = ?, phase = ?, phase_cursor = ?, completed_units = ?,
             message = ?, last_error = NULL, result_json = ?, lock_token = NULL, lock_expires_at = NULL,
             updated_at = NOW(), finished_at = ? WHERE id = ? AND lock_token = ?'
        );
        $statement->execute([
            $status, $outcome['phase'], $outcome['cursor'], $completed, $outcome['message'],
            json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $outcome['complete'] ? date('Y-m-d H:i:s') : null, $job['id'], $lockToken,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Checkpoint dávky se nepodařilo uložit.');
        }
    }

    private function markFailed(int $jobId, string $lockToken, Throwable $error): void
    {
        $statement = $this->db->prepare(
            "UPDATE demo_seed_jobs SET status = 'failed', message = 'Dávka selhala', last_error = ?,
             lock_token = NULL, lock_expires_at = NULL, updated_at = NOW() WHERE id = ? AND lock_token = ?"
        );
        $statement->execute([$error->getMessage(), $jobId, $lockToken]);
    }

    private function totalUnits(): int
    {
        return 1 + (new DemoContentSeeder())->demoAssetBatchCount() + 2
            + 1 + (new WeddingBlogDemoContentImporter())->batchCount()
            + 1 + (new PortfolioDemoContentImporter())->batchCount()
            + 6;
    }

    /** @return array<string,mixed>|null */
    private function findBySite(int $siteId): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM demo_seed_jobs WHERE site_id = ? LIMIT 1');
        $statement->execute([$siteId]);
        return $statement->fetch() ?: null;
    }

    /** @return array<string,mixed> */
    private function requireBySite(int $siteId): array
    {
        $row = $this->findBySite($siteId);
        if (!$row) {
            throw new RuntimeException('Dávková úloha nebyla nalezena.');
        }
        return $row;
    }

    /** @return array<string,mixed> */
    private function requireByToken(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw new RuntimeException('Neplatný identifikátor Demo úlohy.');
        }
        $statement = $this->db->prepare('SELECT * FROM demo_seed_jobs WHERE job_token = ? LIMIT 1');
        $statement->execute([$token]);
        $row = $statement->fetch();
        if (!$row) {
            throw new RuntimeException('Demo úloha nebyla nalezena.');
        }
        return $row;
    }

    /** @return array<string,mixed> */
    private function requireLocked(int $id, string $lockToken): array
    {
        $statement = $this->db->prepare('SELECT * FROM demo_seed_jobs WHERE id = ? AND lock_token = ? FOR UPDATE');
        $statement->execute([$id, $lockToken]);
        $row = $statement->fetch();
        if (!$row) {
            throw new RuntimeException('Zámek Demo dávky byl ztracen.');
        }
        return $row;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function present(array $row): array
    {
        $completed = (int)$row['completed_units'];
        $total = max(1, (int)$row['total_units']);
        return [
            'job_token' => (string)$row['job_token'],
            'status' => (string)$row['status'],
            'phase' => (string)$row['phase'],
            'phase_cursor' => (int)$row['phase_cursor'],
            'completed_units' => $completed,
            'total_units' => $total,
            'percent' => min(100, (int)floor(($completed / $total) * 100)),
            'message' => (string)$row['message'],
            'error' => (string)($row['last_error'] ?? ''),
            'started_at' => (string)$row['started_at'],
            'updated_at' => (string)$row['updated_at'],
        ];
    }
}
