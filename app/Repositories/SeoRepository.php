<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class SeoRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByPage(int $pageId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM seo_settings WHERE page_id = ? LIMIT 1');
        $stmt->execute([$pageId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function upsert(int $pageId, array $data): void
    {
        $existing = $this->findByPage($pageId);
        $cols = [
            'meta_title','meta_description','meta_keywords','canonical_url',
            'robots_index','robots_follow',
            'og_title','og_description','og_image_id',
            'twitter_title','twitter_description','twitter_image_id',
            'schema_type','schema_json',
        ];
        if ($existing) {
            $sets = [];
            $vals = [];
            foreach ($cols as $col) {
                if (array_key_exists($col, $data)) {
                    $sets[] = "`{$col}` = ?";
                    $vals[] = $data[$col] !== '' ? $data[$col] : null;
                }
            }
            if (!$sets) return;
            $vals[] = $pageId;
            $this->db->prepare('UPDATE seo_settings SET ' . implode(', ', $sets) . ' WHERE page_id = ?')->execute($vals);
        } else {
            $data['page_id'] = $pageId;
            $fields = ['page_id'];
            $placeholders = ['?'];
            $vals = [$pageId];
            foreach ($cols as $col) {
                if (array_key_exists($col, $data)) {
                    $fields[] = "`{$col}`";
                    $placeholders[] = '?';
                    $vals[] = $data[$col] !== '' ? $data[$col] : null;
                }
            }
            $sql = 'INSERT INTO seo_settings (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $this->db->prepare($sql)->execute($vals);
        }
    }
}
