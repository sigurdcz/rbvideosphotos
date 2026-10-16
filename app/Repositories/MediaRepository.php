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

    public function pickerPage(int $siteId, string $query = '', int $page = 1, int $perPage = 24, bool $imagesOnly = false): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(60, $perPage));
        $where = ['m.site_id = ?'];
        $params = [$siteId];
        if ($imagesOnly) {
            $where[] = "m.mime_type LIKE 'image/%'";
        }
        if ($query !== '') {
            $where[] = '(m.original_filename LIKE ? OR m.alt_text LIKE ? OR m.title LIKE ?)';
            $like = '%' . $query . '%';
            array_push($params, $like, $like, $like);
        }
        $whereSql = implode(' AND ', $where);
        $count = $this->db->prepare("SELECT COUNT(*) FROM media m WHERE $whereSql");
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        $sql = "SELECT m.*,
                       (SELECT mv.path FROM media_variants mv WHERE mv.media_id = m.id AND mv.format = 'thumb' ORDER BY mv.id DESC LIMIT 1) AS thumb_path
                FROM media m WHERE $whereSql ORDER BY m.created_at DESC, m.id DESC LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);
        $position = 1;
        foreach ($params as $value) {
            $stmt->bindValue($position++, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue($position++, $perPage, PDO::PARAM_INT);
        $stmt->bindValue($position, ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();
        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => $perPage];
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

    /**
     * Keep one current derivative per semantic format. Regeneration replaces
     * the database row; the caller removes an obsolete file after commit.
     */
    public function replaceVariant(int $mediaId, array $data): ?string
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'SELECT path FROM media_variants WHERE media_id = ? AND format = ? ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute([$mediaId, $data['format']]);
            $oldPath = $stmt->fetchColumn();

            $this->db->prepare('DELETE FROM media_variants WHERE media_id = ? AND format = ?')
                ->execute([$mediaId, $data['format']]);
            $this->addVariant($mediaId, $data);
            $this->db->commit();

            return is_string($oldPath) && $oldPath !== '' ? $oldPath : null;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function variantByFormat(int $mediaId, string $format): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM media_variants WHERE media_id = ? AND format = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$mediaId, $format]);
        $row = $stmt->fetch();
        return $row ?: null;
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

    /** @return array<string,array{original:array,variants:array<string,array>}>
     * Keys include both the original and every derivative path so a page that
     * already points at `medium` still receives the complete responsive set.
     */
    public function responsiveVariantMapBySite(int $siteId): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.id AS media_id, m.storage_path, m.width AS original_width, m.height AS original_height,
                    mv.format, mv.width, mv.height, mv.path, mv.filesize
             FROM media m
             LEFT JOIN media_variants mv ON mv.media_id = m.id
             WHERE m.site_id = ?
             ORDER BY m.id, mv.id"
        );
        $stmt->execute([$siteId]);

        $byId = [];
        foreach ($stmt->fetchAll() as $row) {
            $id = (int)$row['media_id'];
            $byId[$id] ??= [
                'original' => [
                    'path' => (string)$row['storage_path'],
                    'width' => (int)$row['original_width'],
                    'height' => (int)$row['original_height'],
                ],
                'variants' => [],
            ];
            $format = (string)($row['format'] ?? '');
            if ($format !== '') {
                $byId[$id]['variants'][$format] = [
                    'path' => (string)$row['path'],
                    'width' => (int)$row['width'],
                    'height' => (int)$row['height'],
                    'filesize' => (int)$row['filesize'],
                ];
            }
        }

        $map = [];
        foreach ($byId as $media) {
            $paths = [(string)$media['original']['path']];
            foreach ($media['variants'] as $variant) {
                $paths[] = (string)$variant['path'];
            }
            foreach ($paths as $path) {
                if ($path !== '') $map[ltrim($path, '/')] = $media;
            }
        }
        return $map;
    }
}
