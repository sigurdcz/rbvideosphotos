<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class SiteRepository
{
    private PDO $db;
    private ?bool $hasActiveThemeKeyColumn = null;

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
        $allowed = ['name','domain','default_language','google_analytics_id','google_tag_manager_id','head_scripts','body_scripts','layout_html','layout_css','layout_js','layout_nav_home','layout_nav_sub','active_theme_key'];
        if (!$this->supportsActiveThemeKey()) {
            $allowed = array_values(array_filter($allowed, static fn(string $col): bool => $col !== 'active_theme_key'));
        }
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
        if ($this->supportsActiveThemeKey()) {
            $stmt = $this->db->prepare(
                'INSERT INTO sites (name, domain, default_language, active_theme_key) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$data['name'], $data['domain'], $data['default_language'] ?? 'cs', $data['active_theme_key'] ?? null]);
        } else {
            $stmt = $this->db->prepare(
                'INSERT INTO sites (name, domain, default_language) VALUES (?, ?, ?)'
            );
            $stmt->execute([$data['name'], $data['domain'], $data['default_language'] ?? 'cs']);
        }
        return (int)$this->db->lastInsertId();
    }

    public function supportsActiveThemeKey(): bool
    {
        if ($this->hasActiveThemeKeyColumn !== null) {
            return $this->hasActiveThemeKeyColumn;
        }

        $stmt = $this->db->prepare("SHOW COLUMNS FROM sites LIKE 'active_theme_key'");
        $stmt->execute();
        $this->hasActiveThemeKeyColumn = (bool)$stmt->fetch();
        return $this->hasActiveThemeKeyColumn;
    }
}
