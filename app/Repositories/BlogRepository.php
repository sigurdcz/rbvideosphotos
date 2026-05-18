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

    public function findBySlugAdmin(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM blog_posts WHERE slug = ? LIMIT 1');
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
            'UPDATE blog_posts SET title=?, slug=?, excerpt=?, content=?, featured_image_id=?, status=?, published_at=?, meta_title=?, meta_description=? WHERE id=?'
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
        $this->db->prepare('DELETE FROM blog_media WHERE blog_post_id = ?')->execute([$id]);
        $this->db->prepare('DELETE FROM blog_posts WHERE id = ?')->execute([$id]);
    }

    public function setGallery(int $postId, array $mediaIds): void
    {
        $this->db->prepare('DELETE FROM blog_media WHERE blog_post_id = ?')->execute([$postId]);
        $stmt = $this->db->prepare('INSERT INTO blog_media (blog_post_id, media_id, sort_order) VALUES (?, ?, ?)');
        foreach ($mediaIds as $i => $mid) {
            $stmt->execute([$postId, (int)$mid, $i]);
        }
    }

    public function getGallery(int $postId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*, bm.sort_order AS bm_sort,
                    (SELECT mv.path FROM media_variants mv WHERE mv.media_id = m.id AND mv.format = ? LIMIT 1) AS thumb_path,
                    (SELECT mv.path FROM media_variants mv WHERE mv.media_id = m.id AND mv.format = ? LIMIT 1) AS medium_path
             FROM blog_media bm
             JOIN media m ON m.id = bm.media_id
             WHERE bm.blog_post_id = ?
             ORDER BY bm.sort_order ASC'
        );
        $stmt->execute(['thumb', 'medium', $postId]);
        return $stmt->fetchAll();
    }
}
