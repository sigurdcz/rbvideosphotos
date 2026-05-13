<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class SiteRepository
{
    private PDO $db;
    private ?bool $hasActiveThemeKeyColumn = null;
    private array $columnSupport = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findFirst(): ?array
    {
        $stmt = $this->db->query('SELECT * FROM sites ORDER BY id ASC LIMIT 1');
        $row  = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM sites WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $id, array $data): void
    {
        $allowed = $this->writableColumns();
        $sets = [];
        $vals = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "`{$col}` = ?";
                $vals[] = $data[$col];
            }
        }
        if (!$sets) return;
        $vals[] = $id;
        $this->db->prepare('UPDATE sites SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
    }

    public function create(array $data): int
    {
        $allowed = $this->writableColumns();
        $fields = [];
        $placeholders = [];
        $values = [];

        foreach ($allowed as $col) {
            if (!array_key_exists($col, $data)) {
                continue;
            }
            $fields[] = "`{$col}`";
            $placeholders[] = '?';
            $values[] = $data[$col];
        }

        if (!in_array('`name`', $fields, true)) {
            $fields[] = '`name`';
            $placeholders[] = '?';
            $values[] = $data['name'] ?? 'Muj web';
        }
        if (!in_array('`domain`', $fields, true)) {
            $fields[] = '`domain`';
            $placeholders[] = '?';
            $values[] = $data['domain'] ?? '';
        }
        if (!in_array('`default_language`', $fields, true)) {
            $fields[] = '`default_language`';
            $placeholders[] = '?';
            $values[] = $data['default_language'] ?? 'cs';
        }

        $sql = 'INSERT INTO sites (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
        return (int)$this->db->lastInsertId();
    }

    public function supportsActiveThemeKey(): bool
    {
        $this->hasActiveThemeKeyColumn = $this->supportsColumn('active_theme_key');
        return $this->hasActiveThemeKeyColumn;
    }

    public function supportsColumn(string $column): bool
    {
        if (array_key_exists($column, $this->columnSupport)) {
            return $this->columnSupport[$column];
        }

        $stmt = $this->db->prepare(
            'SELECT 1
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = ?
             LIMIT 1'
        );
        $stmt->execute(['sites', $column]);
        $this->columnSupport[$column] = (bool)$stmt->fetch();
        return $this->columnSupport[$column];
    }

    private function writableColumns(): array
    {
        $columns = [
            'name',
            'domain',
            'default_language',
            'google_analytics_id',
            'google_tag_manager_id',
            'head_scripts',
            'body_scripts',
            'layout_html',
            'layout_css',
            'layout_js',
            'layout_nav_home',
            'layout_nav_sub',
            'active_theme_key',
            'logo_url',
            'favicon_ico_url',
            'favicon_png_url',
            'apple_touch_icon_url',
        ];

        return array_values(array_filter($columns, fn(string $col): bool => $this->supportsColumn($col)));
    }
}
