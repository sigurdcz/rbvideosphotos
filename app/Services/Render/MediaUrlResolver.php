<?php

declare(strict_types=1);

namespace App\Services\Render;

use App\Core\Logger;
use App\Repositories\MediaRepository;

/**
 * Canonical media/asset URL normalization (root-relative /media/ URLs).
 *
 * Extracted in phase 2 of the templating refactor as the single source of truth
 * shared by the live render path (public/index.php) and the publish path
 * (App\Services\PublishService). Do not fork these implementations.
 */
final class MediaUrlResolver
{
    public static function normalizePublicAssetUrl(string $value): string
{
    $normalized = str_replace('\\', '/', trim($value));
    if ($normalized === '') {
        return '';
    }

    if (preg_match('~^https?://~i', $normalized)) {
        return $normalized;
    }

    if (str_starts_with($normalized, '/')) {
        return $normalized;
    }

    return '/' . ltrim($normalized, '/');
}

    public static function publicMediaUrl(string $storagePath): string
{
    $normalized = str_replace('\\', '/', trim($storagePath));
    if ($normalized === '') {
        return '';
    }

    if (preg_match('~https?://[^/]+(/storage/media/.*)$~i', $normalized, $m)) {
        return '/media/' . ltrim(substr($m[1], strlen('/storage/media/')), '/');
    }

    if (str_starts_with($normalized, '/media/')) {
        return $normalized;
    }

    if (str_starts_with($normalized, '/storage/media/')) {
        return '/media/' . ltrim(substr($normalized, strlen('/storage/media/')), '/');
    }

    if (str_starts_with($normalized, 'storage/media/')) {
        return '/media/' . ltrim(substr($normalized, strlen('storage/media/')), '/');
    }

    return '/' . ltrim($normalized, '/');
}

    public static function resolveDemoMediaUrlByFilename(int $siteId, string $filename): string
{
    try {
        foreach ((new MediaRepository())->allBySiteWithVariants($siteId) as $media) {
            if ((string)($media['original_filename'] ?? '') !== $filename) {
                continue;
            }

            $path = '';
            foreach (($media['variants'] ?? []) as $variant) {
                if (($variant['format'] ?? '') === 'medium' && trim((string)($variant['path'] ?? '')) !== '') {
                    $path = (string)$variant['path'];
                    break;
                }
            }
            if ($path === '') {
                $path = (string)($media['storage_path'] ?? '');
            }

            return \App\Services\Render\MediaUrlResolver::normalizePublicAssetUrl($path);
        }
    } catch (\Throwable $e) {
        Logger::infoTo('theme-debug', 'theme.demo.media.error', 'Failed to resolve demo media', [
            'site_id' => $siteId,
            'filename' => $filename,
            'error' => $e->getMessage(),
        ]);
    }

    return '';
}
}
