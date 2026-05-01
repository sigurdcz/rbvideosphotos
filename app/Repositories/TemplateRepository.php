<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TemplateRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allBySite(int $siteId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM templates WHERE site_id = ? ORDER BY name ASC');
        $stmt->execute([$siteId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM templates WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO templates (site_id, name, html_source, css_source, js_source) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['site_id'],
            $data['name'],
            $data['html_source'] ?? '',
            $data['css_source']  ?? '',
            $data['js_source']   ?? '',
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE templates SET name = ?, html_source = ?, css_source = ?, js_source = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['name'],
            $data['html_source'] ?? '',
            $data['css_source']  ?? '',
            $data['js_source']   ?? '',
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM templates WHERE id = ?')->execute([$id]);
    }
}
