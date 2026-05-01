<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class RedirectRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allBySite(int $siteId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM redirects WHERE site_id = ? ORDER BY id DESC');
        $stmt->execute([$siteId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM redirects WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO redirects (site_id, from_path, to_path, status_code) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$data['site_id'], $data['from_path'], $data['to_path'], $data['status_code'] ?? 301]);
        return (int)$this->db->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM redirects WHERE id = ?')->execute([$id]);
    }
}
