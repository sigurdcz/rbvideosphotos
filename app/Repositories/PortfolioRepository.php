<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PortfolioRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allBySite(int $siteId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM portfolio_entries WHERE site_id = ? ORDER BY sort_order ASC, created_at DESC');
        $stmt->execute([$siteId]);
        return $stmt->fetchAll();
    }

    public function allPublished(int $siteId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM portfolio_entries WHERE site_id = ? AND status = 'published' ORDER BY sort_order ASC, published_at DESC");
        $stmt->execute([$siteId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM portfolio_entries WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM portfolio_entries WHERE slug = ? AND status = 'published' LIMIT 1");
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public function findBySlugAdmin(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM portfolio_entries WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $d): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO portfolio_entries (site_id, title, slug, description, video_url, status, published_at, sort_order, meta_title, meta_description)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $d['site_id'],
            $d['title'],
            $d['slug'],
            $d['description']      ?? '',
            $d['video_url']        ?? null,
            $d['status']           ?? 'draft',
            $d['published_at']     ?? null,
            $d['sort_order']       ?? 0,
            $d['meta_title']       ?? null,
            $d['meta_description'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $stmt = $this->db->prepare(
            'UPDATE portfolio_entries SET title=?, slug=?, description=?, video_url=?, status=?, published_at=?, sort_order=?, meta_title=?, meta_description=? WHERE id=?'
        );
        $stmt->execute([
            $d['title'],
            $d['slug'],
            $d['description']      ?? '',
            $d['video_url']        ?? null,
            $d['status']           ?? 'draft',
            $d['published_at']     ?? null,
            $d['sort_order']       ?? 0,
            $d['meta_title']       ?? null,
            $d['meta_description'] ?? null,
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM portfolio_media  WHERE portfolio_id = ?')->execute([$id]);
        $this->db->prepare('DELETE FROM portfolio_entries WHERE id = ?')->execute([$id]);
    }

    /** Replace all media for a portfolio entry. $mediaIds is ordered array of media.id values. */
    public function setMedia(int $portfolioId, array $mediaIds): void
    {
        $this->db->prepare('DELETE FROM portfolio_media WHERE portfolio_id = ?')->execute([$portfolioId]);
        $stmt = $this->db->prepare('INSERT INTO portfolio_media (portfolio_id, media_id, sort_order) VALUES (?, ?, ?)');
        foreach ($mediaIds as $i => $mid) {
            $stmt->execute([$portfolioId, (int)$mid, $i]);
        }
    }

    /** Return media rows joined with media table, ordered by sort_order.
     *  Includes thumb_path (≤300 px) and medium_path (≤900 px) variant paths when available. */
    public function getMedia(int $portfolioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*, pm.sort_order AS pm_sort,
                    (SELECT mv.path FROM media_variants mv WHERE mv.media_id = m.id AND mv.format = ? LIMIT 1) AS thumb_path,
                    (SELECT mv.path FROM media_variants mv WHERE mv.media_id = m.id AND mv.format = ? LIMIT 1) AS medium_path
             FROM portfolio_media pm
             JOIN media m ON m.id = pm.media_id
             WHERE pm.portfolio_id = ?
             ORDER BY pm.sort_order ASC'
        );
        $stmt->execute(['thumb', 'medium', $portfolioId]);
        return $stmt->fetchAll();
    }

    /** Return the first media item (for thumbnail use). */
    public function getFirstMedia(int $portfolioId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT m.* FROM portfolio_media pm JOIN media m ON m.id = pm.media_id
             WHERE pm.portfolio_id = ? ORDER BY pm.sort_order ASC LIMIT 1'
        );
        $stmt->execute([$portfolioId]);
        return $stmt->fetch() ?: null;
    }

    /** Extract embeddable URL from a YouTube or Vimeo watch URL. */
    public static function embedUrl(string $url): string
    {
        if (preg_match('~(?:youtube\.com/watch\?.*v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1] . '?dnt=1';
        }
        return $url;
    }

    /** Return YouTube hqdefault thumbnail URL, or empty string. */
    public static function youtubeThumbnail(string $url): string
    {
        if (preg_match('~(?:youtube\.com/watch\?.*v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return 'https://img.youtube.com/vi/' . $m[1] . '/hqdefault.jpg';
        }
        return '';
    }
}
