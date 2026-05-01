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
}
