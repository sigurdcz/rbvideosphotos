<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PublishHistoryRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allBySite(int $siteId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT h.*, u.email AS user_email
             FROM publish_history h
             LEFT JOIN users u ON u.id = h.user_id
             WHERE h.site_id = ?
             ORDER BY h.created_at DESC
             LIMIT ' . (int)$limit
        );
        $stmt->execute([$siteId]);
        return $stmt->fetchAll();
    }

    public function nextVersion(int $siteId): int
    {
        $stmt = $this->db->prepare('SELECT MAX(version) AS v FROM publish_history WHERE site_id = ?');
        $stmt->execute([$siteId]);
        return ((int)$stmt->fetchColumn()) + 1;
    }

    public function create(array $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO publish_history (site_id, user_id, version, status, notes) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['site_id'],
            $data['user_id'],
            $data['version'],
            $data['status'] ?? 'success',
            $data['notes']  ?? null,
        ]);
    }
}
