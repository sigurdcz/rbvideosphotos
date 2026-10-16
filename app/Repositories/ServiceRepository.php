<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ServiceRepository
{
    private PDO $db;
    private array $columnSupport = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allBySite(int $siteId): array
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM service_items WHERE site_id = ? ORDER BY sort_order ASC, id ASC');
            $stmt->execute([$siteId]);
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function allActive(int $siteId): array
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM service_items WHERE site_id = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC');
            $stmt->execute([$siteId]);
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function upsertMany(int $siteId, array $items): void
    {
        try {
            $this->db->beginTransaction();
            $this->db->prepare('DELETE FROM service_items WHERE site_id = ?')->execute([$siteId]);
            $hasIconUrl = $this->supportsColumn('icon_url');
            $stmt = $hasIconUrl
                ? $this->db->prepare(
                    'INSERT INTO service_items
                        (site_id, title, description, image_url, icon_url, button_text, button_url, sort_order, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )
                : $this->db->prepare(
                    'INSERT INTO service_items
                        (site_id, title, description, image_url, button_text, button_url, sort_order, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );

            foreach ($items as $item) {
                $title = trim((string)($item['title'] ?? ''));
                $description = trim((string)($item['description'] ?? ''));
                if ($title === '' && $description === '') {
                    continue;
                }

                $values = [
                    $siteId,
                    $title,
                    $description,
                    trim((string)($item['image_url'] ?? '')),
                ];
                if ($hasIconUrl) {
                    $values[] = trim((string)($item['icon_url'] ?? ''));
                }
                $values = array_merge($values, [
                    trim((string)($item['button_text'] ?? '')),
                    trim((string)($item['button_url'] ?? '')),
                    (int)($item['sort_order'] ?? 0),
                    !empty($item['is_active']) ? 1 : 0,
                ]);

                $stmt->execute($values);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
        }
    }

    private function supportsColumn(string $column): bool
    {
        if (array_key_exists($column, $this->columnSupport)) {
            return $this->columnSupport[$column];
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND CONVERT(table_name USING utf8mb4) = ?
                   AND CONVERT(column_name USING utf8mb4) = ?'
            );
            $stmt->execute(['service_items', $column]);
            return $this->columnSupport[$column] = ((int)$stmt->fetchColumn()) > 0;
        } catch (\Throwable $e) {
            return $this->columnSupport[$column] = false;
        }
    }
}
