<?php

declare(strict_types=1);

namespace App\Services\Render;

use App\Repositories\MediaRepository;

final class ArticleVisual
{
    public static function focusY(array $record, string $surface): int
    {
        $value = $record[$surface . '_focus_y'] ?? null;
        return $value === null || $value === '' ? 50 : max(0, min(100, (int)$value));
    }

    public static function media(array $record, string $surface, ?array $fallback = null): ?array
    {
        $id = (int)($record[$surface . '_image_id'] ?? 0);
        if ($id <= 0) {
            return $fallback;
        }

        return (new MediaRepository())->findByIdWithThumb($id);
    }

    public static function url(?array $media, bool $full = false): string
    {
        if (!$media) {
            return '';
        }

        $path = $full
            ? (string)($media['storage_path'] ?? '')
            : (string)($media['medium_path'] ?? $media['storage_path'] ?? $media['thumb_path'] ?? '');

        return MediaUrlResolver::publicMediaUrl($path);
    }
}
