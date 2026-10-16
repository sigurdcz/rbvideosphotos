<?php

declare(strict_types=1);

// Known historical schema overlaps; shared by CLI and authenticated web migration runners.
$tableExists = static function (PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $stmt->execute([$table]);

    return (int) $stmt->fetchColumn() > 0;
};

$columnExists = static function (PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
};

$columnEnumContains = static function (PDO $pdo, string $table, string $column, string $value): bool {
    $stmt = $pdo->prepare(
        'SELECT COLUMN_TYPE
         FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
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
        default => false,
    };
};

return $migrationAlreadySatisfied;
