<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class SiteRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findFirst(): ?array
    {
        $stmt = $this->db->query('SELECT * FROM sites ORDER BY id ASC LIMIT 1');
        $row  = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM sites WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $id, array $data): void
    {
        $allowed = ['name','domain','default_language','google_analytics_id','google_tag_manager_id','head_scripts','body_scripts','layout_html','layout_css','layout_js'];
        $sets = [];
        $vals = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "`{$col}` = ?";
                $vals[] = $data[$col];
            }
        }
        if (!$sets) return;
        $vals[] = $id;
        $this->db->prepare('UPDATE sites SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO sites (name, domain, default_language) VALUES (?, ?, ?)'
        );
        $stmt->execute([$data['name'], $data['domain'], $data['default_language'] ?? 'cs']);
        return (int)$this->db->lastInsertId();
    }
}
