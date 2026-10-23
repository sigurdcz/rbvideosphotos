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

        $stmt = $this->db->query('SHOW COLUMNS FROM `sites`');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $this->columnSupport[$row['Field']] = true;
        }

        return $this->columnSupport[$column] ?? false;
    }

    private function writableColumns(): array
    {
        $columns = [
            'name',
            'domain',
            'company_registration_id',
            'default_language',
            'google_analytics_id',
            'google_tag_manager_id',
            'cookie_consent_enabled',
            'cookie_consent_analytics_enabled',
            'cookie_consent_marketing_enabled',
            'head_scripts',
            'body_scripts',
            'analytics_head_scripts',
            'analytics_body_scripts',
            'marketing_head_scripts',
            'marketing_body_scripts',
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
            'default_og_image_id',
            'default_instagram_image_id',
            'default_twitter_image_id',
            'default_hero_video_url',
            'default_hero_video_url_tablet',
            'default_hero_video_url_mobile',
            'video_autoplay_enabled',
            'default_hero_poster_url',
            'media_image_quality',
            'video_overlay_opacity',
            'tracking_enabled',
            'tracking_require_consent',
            'tracking_debug',
            'clarity_id',
            'meta_pixel_id',
            'tracking_events_json',
            'visit_analytics_enabled',
            'contact_spam_config_json',
        ];

        return array_values(array_filter($columns, fn(string $col): bool => $this->supportsColumn($col)));
    }
}
