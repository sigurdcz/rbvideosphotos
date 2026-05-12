<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class BlockRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allByPage(int $pageId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM content_blocks WHERE page_id = ? ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([$pageId]);
        return $stmt->fetchAll();
    }

    public function findByKey(int $pageId, string $key): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM content_blocks WHERE page_id = ? AND block_key = ? LIMIT 1'
        );
        $stmt->execute([$pageId, $key]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function upsert(int $pageId, string $key, array $data): void
    {
        $existing = $this->findByKey($pageId, $key);
        $autofillDisabled = array_key_exists('autofill_disabled', $data)
            ? (int)!empty($data['autofill_disabled'])
            : (int)($existing['autofill_disabled'] ?? 0);
        if ($existing) {
            $stmt = $this->db->prepare(
                'UPDATE content_blocks SET block_type = ?, label = ?, content = ?, sort_order = ?, autofill_disabled = ? WHERE id = ?'
            );
            $stmt->execute([
                $data['block_type'] ?? 'text',
                $data['label']      ?? $key,
                $data['content']    ?? '',
                $data['sort_order'] ?? 0,
                $autofillDisabled,
                $existing['id'],
            ]);
        } else {
            $stmt = $this->db->prepare(
                'INSERT INTO content_blocks (page_id, block_key, block_type, label, content, sort_order, autofill_disabled)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $pageId,
                $key,
                $data['block_type'] ?? 'text',
                $data['label']      ?? $key,
                $data['content']    ?? '',
                $data['sort_order'] ?? 0,
                $autofillDisabled,
            ]);
        }
    }

    public function updateContent(int $pageId, string $key, string $content): void
    {
        $this->db->prepare(
            'UPDATE content_blocks SET content = ? WHERE page_id = ? AND block_key = ?'
        )->execute([$content, $pageId, $key]);
    }

    public function deleteByPage(int $pageId): void
    {
        $this->db->prepare('DELETE FROM content_blocks WHERE page_id = ?')->execute([$pageId]);
    }
}
