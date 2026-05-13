<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class MediaRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allBySite(int $siteId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM media WHERE site_id = ? ORDER BY created_at DESC');
        $stmt->execute([$siteId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM media WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByStoragePath(int $siteId, string $storagePath): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM media WHERE site_id = ? AND storage_path = ? LIMIT 1');
        $stmt->execute([$siteId, $storagePath]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByIdWithThumb(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*,
                    (SELECT mv.path FROM media_variants mv WHERE mv.media_id = m.id AND mv.format = ? LIMIT 1) AS thumb_path,
                    (SELECT mv.path FROM media_variants mv WHERE mv.media_id = m.id AND mv.format = ? LIMIT 1) AS medium_path
             FROM media m WHERE m.id = ? LIMIT 1'
        );
        $stmt->execute(['thumb', 'medium', $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO media (site_id, original_filename, storage_path, alt_text, title, width, height, mime_type)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['site_id'],
            $data['original_filename'],
            $data['storage_path'],
            $data['alt_text']  ?? null,
            $data['title']     ?? null,
            $data['width']     ?? null,
            $data['height']    ?? null,
            $data['mime_type'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare('UPDATE media SET alt_text = ?, title = ? WHERE id = ?');
        $stmt->execute([$data['alt_text'] ?? null, $data['title'] ?? null, $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM media WHERE id = ?')->execute([$id]);
    }

    public function addVariant(int $mediaId, array $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO media_variants (media_id, format, width, height, path, filesize) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $mediaId,
            $data['format'],
            $data['width']    ?? null,
            $data['height']   ?? null,
            $data['path'],
            $data['filesize'] ?? null,
        ]);
    }

    public function allBySiteWithVariants(int $siteId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM media WHERE site_id = ? ORDER BY created_at DESC');
        $stmt->execute([$siteId]);
        $rows = $stmt->fetchAll();

        if (empty($rows)) return [];

        $ids          = array_column($rows, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $vstmt        = $this->db->prepare("SELECT * FROM media_variants WHERE media_id IN ($placeholders) ORDER BY id");
        $vstmt->execute($ids);
        $variants     = $vstmt->fetchAll();

        $vmap = [];
        foreach ($variants as $v) {
            $vmap[$v['media_id']][] = $v;
        }
        foreach ($rows as &$row) {
            $row['variants'] = $vmap[$row['id']] ?? [];
        }

        return $rows;
    }

    public function variantsByMediaId(int $mediaId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM media_variants WHERE media_id = ?');
        $stmt->execute([$mediaId]);
        return $stmt->fetchAll();
    }
}
