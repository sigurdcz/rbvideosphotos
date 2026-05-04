<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PageRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allBySite(int $siteId): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, t.name AS template_name
             FROM pages p
             LEFT JOIN templates t ON t.id = p.template_id
             WHERE p.site_id = ?
             ORDER BY p.created_at DESC'
        );
        $stmt->execute([$siteId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM pages WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByUrlPath(string $urlPath): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM pages WHERE url_path = ? LIMIT 1');
        $stmt->execute([$urlPath]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findPublishedBySite(int $siteId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM pages WHERE site_id = ? AND status = 'published' ORDER BY url_path ASC"
        );
        $stmt->execute([$siteId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pages (site_id, template_id, title, slug, url_path, language, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['site_id'],
            $data['template_id'] ?: null,
            $data['title'],
            $data['slug'],
            $data['url_path'],
            $data['language'] ?? 'cs',
            $data['status']   ?? 'draft',
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $allowed = ['title','slug','url_path','language','status','template_id','published_at'];
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
        $this->db->prepare('UPDATE pages SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM pages WHERE id = ?')->execute([$id]);
    }

    public function slugExists(int $siteId, string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            $stmt = $this->db->prepare('SELECT id FROM pages WHERE site_id = ? AND slug = ? AND id != ?');
            $stmt->execute([$siteId, $slug, $excludeId]);
        } else {
            $stmt = $this->db->prepare('SELECT id FROM pages WHERE site_id = ? AND slug = ?');
            $stmt->execute([$siteId, $slug]);
        }
        return (bool)$stmt->fetch();
    }
}
