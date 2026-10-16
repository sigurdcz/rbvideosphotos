<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;

final class SharedSectionRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allBySite(int $siteId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM shared_sections WHERE site_id = ? ORDER BY title ASC, id ASC');
        $stmt->execute([$siteId]);
        return $stmt->fetchAll();
    }

    public function findByKey(int $siteId, string $key): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM shared_sections WHERE site_id = ? AND section_key = ? LIMIT 1');
        $stmt->execute([$siteId, $key]);
        return $stmt->fetch() ?: null;
    }

    public function decodedByKey(int $siteId, string $key): ?array
    {
        $row = $this->findByKey($siteId, $key);
        if (!$row || !(bool)$row['is_active']) {
            return null;
        }
        $data = json_decode((string)$row['content_json'], true);
        if (!is_array($data)) {
            throw new RuntimeException('Sdilena sekce ' . $key . ' obsahuje neplatny JSON.');
        }
        return $data;
    }

    public function updateContent(int $siteId, string $key, string $title, array $content, bool $active): void
    {
        $json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $stmt = $this->db->prepare(
            'UPDATE shared_sections SET title = ?, content_json = ?, is_active = ? WHERE site_id = ? AND section_key = ?'
        );
        $stmt->execute([$title, $json, $active ? 1 : 0, $siteId, $key]);
        if ($stmt->rowCount() === 0 && !$this->findByKey($siteId, $key)) {
            throw new RuntimeException('Sdilena sekce ' . $key . ' nebyla nalezena. Spustte migrace.');
        }
    }
}
