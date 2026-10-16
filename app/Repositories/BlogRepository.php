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
        $stmt = $this->db->prepare('SELECT * FROM blog_posts WHERE site_id = ? ORDER BY (sort_order = 0) ASC, sort_order ASC, COALESCE(published_at, created_at) DESC, id DESC');
        $stmt->execute([$siteId]);
        return $stmt->fetchAll();
    }

    public function allPublished(int $siteId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM blog_posts WHERE site_id = ? AND status = 'published' ORDER BY (sort_order = 0) ASC, sort_order ASC, published_at DESC, id DESC");
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
            'INSERT INTO blog_posts (site_id, title, slug, excerpt, content, featured_image_id, status, published_at, sort_order, meta_title, meta_description)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
            PortfolioRepository::clampSortOrder($d['sort_order'] ?? 0),
            $d['meta_title']        ?? null,
            $d['meta_description']  ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $stmt = $this->db->prepare(
            'UPDATE blog_posts SET title=?, slug=?, excerpt=?, content=?, featured_image_id=?, status=?, published_at=?, sort_order=?, meta_title=?, meta_description=? WHERE id=?'
        );
        $stmt->execute([
            $d['title'],
            $d['slug'],
            $d['excerpt']           ?? '',
            $d['content']           ?? '',
            $d['featured_image_id'] ?? null,
            $d['status']            ?? 'draft',
            $d['published_at']      ?? null,
            PortfolioRepository::clampSortOrder($d['sort_order'] ?? 0),
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

    /**
     * Replace the whole gallery. $sortById optionally maps media.id => the number
     * typed in the admin; without it the selection order becomes the order, exactly
     * as before, so the seeds and the API keep working untouched.
     *
     * @param array<mixed> $mediaIds
     * @param array<int|string,mixed>|null $sortById
     */
    public function setGallery(int $postId, array $mediaIds, ?array $sortById = null): void
    {
        // The rewrite is a DELETE followed by INSERTs: without a transaction a single
        // rejected row would leave the article with no gallery at all.
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $this->db->prepare('DELETE FROM blog_media WHERE blog_post_id = ?')->execute([$postId]);
            $stmt = $this->db->prepare('INSERT INTO blog_media (blog_post_id, media_id, sort_order) VALUES (?, ?, ?)');
            $seen = [];
            foreach (array_values($mediaIds) as $i => $mid) {
                $mid = (int)$mid;
                if ($mid <= 0 || isset($seen[$mid])) {
                    continue;
                }
                $seen[$mid] = true;
                $sort = $sortById === null ? $i : PortfolioRepository::clampSortOrder($sortById[$mid] ?? 0);
                $stmt->execute([$postId, $mid, $sort]);
            }
            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
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
             ORDER BY bm.sort_order ASC, bm.id ASC'
        );
        $stmt->execute(['thumb', 'medium', $postId]);
        return $stmt->fetchAll();
    }
}
