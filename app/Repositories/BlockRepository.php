<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class BlockRepository
{
    private PDO $db;

    private const ALLOWED_BLOCK_TYPES = ['text', 'textarea', 'richtext', 'image', 'link', 'html'];

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
        $blockType = $this->normalizeBlockType((string)($data['block_type'] ?? ($existing['block_type'] ?? 'text')));
        $autofillDisabled = array_key_exists('autofill_disabled', $data)
            ? (int)!empty($data['autofill_disabled'])
            : (int)($existing['autofill_disabled'] ?? 0);
        if ($existing) {
            $stmt = $this->db->prepare(
                'UPDATE content_blocks SET block_type = ?, label = ?, content = ?, sort_order = ?, autofill_disabled = ? WHERE id = ?'
            );
            $stmt->execute([
                $blockType,
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
                $blockType,
                $data['label']      ?? $key,
                $data['content']    ?? '',
                $data['sort_order'] ?? 0,
                $autofillDisabled,
            ]);
        }
    }

    private function normalizeBlockType(string $blockType): string
    {
        $blockType = trim($blockType);
        if ($blockType === 'video') {
            return 'link';
        }

        return in_array($blockType, self::ALLOWED_BLOCK_TYPES, true) ? $blockType : 'text';
    }

    /**
     * Write new content into an existing block row.
     *
     * Returns FALSE when the page has no row for $key yet - the UPDATE then
     * touches nothing and the caller (inline editor) must decide whether to
     * create the row instead. Before this returned a bool, a missing row made
     * every inline save silently succeed while discarding the text.
     */
    public function updateContent(int $pageId, string $key, string $content): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE content_blocks SET content = ? WHERE page_id = ? AND block_key = ?'
        );
        $stmt->execute([$content, $pageId, $key]);

        if ($stmt->rowCount() > 0) {
            return true;
        }

        // rowCount() is 0 both for "no such row" and for "same value written
        // again", so an existing row must still count as a successful update.
        return $this->findByKey($pageId, $key) !== null;
    }

    public function deleteByPage(int $pageId): void
    {
        $this->db->prepare('DELETE FROM content_blocks WHERE page_id = ?')->execute([$pageId]);
    }
}
