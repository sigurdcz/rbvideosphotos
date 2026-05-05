<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class BlogRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS blog_posts (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                site_id             INTEGER NOT NULL,
                title               TEXT    NOT NULL,
                slug                TEXT    NOT NULL,
                excerpt             TEXT    DEFAULT '',
                content             TEXT    DEFAULT '',
                featured_image_id   INTEGER DEFAULT NULL,
                status              TEXT    NOT NULL DEFAULT 'draft',
                published_at        TEXT    DEFAULT NULL,
                meta_title          TEXT    DEFAULT NULL,
                meta_description    TEXT    DEFAULT NULL,
                created_at          TEXT    NOT NULL DEFAULT (datetime('now')),
                updated_at          TEXT    NOT NULL DEFAULT (datetime('now')),
                UNIQUE(site_id, slug)
            )
        ");
    }

    public function allBySite(int $siteId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM blog_posts WHERE site_id = ? ORDER BY created_at DESC');
        $stmt->execute([$siteId]);
        return $stmt->fetchAll();
    }

    public function allPublished(int $siteId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM blog_posts WHERE site_id = ? AND status = 'published' ORDER BY published_at DESC");
        $stmt->execute([$siteId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM blog_posts WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM blog_posts WHERE slug = ? AND status = 'published' LIMIT 1");
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $d): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO blog_posts (site_id, title, slug, excerpt, content, featured_image_id, status, published_at, meta_title, meta_description)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $d['site_id'],
            $d['title'],
            $d['slug'],
            $d['excerpt']           ?? '',
            $d['content']           ?? '',
            $d['featured_image_id'] ?? null,
            $d['status']            ?? 'draft',
            $d['published_at']      ?? null,
            $d['meta_title']        ?? null,
            $d['meta_description']  ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $stmt = $this->db->prepare(
            'UPDATE blog_posts SET title=?, slug=?, excerpt=?, content=?, featured_image_id=?, status=?, published_at=?, meta_title=?, meta_description=?, updated_at=datetime(\'now\') WHERE id=?'
        );
        $stmt->execute([
            $d['title'],
            $d['slug'],
            $d['excerpt']           ?? '',
            $d['content']           ?? '',
            $d['featured_image_id'] ?? null,
            $d['status']            ?? 'draft',
            $d['published_at']      ?? null,
            $d['meta_title']        ?? null,
            $d['meta_description']  ?? null,
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM blog_posts WHERE id = ?')->execute([$id]);
    }
}
