<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class FaqRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allBySite(int $siteId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM faq_items WHERE site_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$siteId]);
        return $stmt->fetchAll();
    }

    public function allActive(int $siteId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM faq_items WHERE site_id = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$siteId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM faq_items WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $d): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO faq_items (site_id, question, answer, sort_order, is_active) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $d['site_id'],
            $d['question']   ?? '',
            $d['answer']     ?? '',
            $d['sort_order'] ?? 0,
            $d['is_active']  ?? 1,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $stmt = $this->db->prepare(
            'UPDATE faq_items SET question=?, answer=?, sort_order=?, is_active=? WHERE id=?'
        );
        $stmt->execute([
            $d['question']   ?? '',
            $d['answer']     ?? '',
            $d['sort_order'] ?? 0,
            $d['is_active']  ?? 1,
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM faq_items WHERE id = ?')->execute([$id]);
    }
}
