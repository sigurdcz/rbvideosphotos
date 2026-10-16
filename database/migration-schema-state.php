<?php

declare(strict_types=1);

// Shared explicit checks for known historical schema overlaps.
$tableExists = static function (PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE() AND CONVERT(table_name USING utf8mb4) = ?'
    );
    $stmt->execute([$table]);

    return (int) $stmt->fetchColumn() > 0;
};

$columnExists = static function (PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE() AND CONVERT(table_name USING utf8mb4) = ? AND CONVERT(column_name USING utf8mb4) = ?'
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
};

$columnEnumContains = static function (PDO $pdo, string $table, string $column, string $value): bool {
    $stmt = $pdo->prepare(
        'SELECT COLUMN_TYPE
         FROM information_schema.columns
         WHERE table_schema = DATABASE() AND CONVERT(table_name USING utf8mb4) = ? AND CONVERT(column_name USING utf8mb4) = ?'
    );
    $stmt->execute([$table, $column]);
    $columnType = (string) $stmt->fetchColumn();

    return $columnType !== '' && str_contains($columnType, "'" . $value . "'");
};

$migrationAlreadySatisfied = static function (PDO $pdo, string $name) use ($tableExists, $columnExists, $columnEnumContains): bool {
    return match ($name) {
        '002_add_autofill_disabled_to_content_blocks.sql' => $columnExists($pdo, 'content_blocks', 'autofill_disabled'),
        '004_create_service_items.sql' => $tableExists($pdo, 'service_items'),
        '005_add_active_theme_key_to_sites.sql' => $columnExists($pdo, 'sites', 'active_theme_key'),
        '006_expand_content_blocks_block_type_to_video.sql' => $columnEnumContains($pdo, 'content_blocks', 'block_type', 'video'),
        '019_add_media_image_quality_to_sites.sql' => $columnExists($pdo, 'sites', 'media_image_quality'),
        '070_add_video_autoplay_setting.sql' => $columnExists($pdo, 'sites', 'video_autoplay_enabled'),
        '072_add_blog_posts_sort_order.sql' => $columnExists($pdo, 'blog_posts', 'sort_order'),
        '073_add_responsive_hero_video_urls.sql' => $columnExists($pdo, 'sites', 'default_hero_video_url_tablet')
            && $columnExists($pdo, 'sites', 'default_hero_video_url_mobile'),
        '079_structured_schema_org_fields.sql' => $columnExists($pdo, 'seo_settings', 'schema_contact_auto'),
        default => false,
    };
};

return $migrationAlreadySatisfied;
