<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\MediaRepository;

/**
 * What the media library knows about the files a hero video points at.
 *
 * Both lookups only ever make the page lighter or steadier and never decide whether a video
 * shows, so any failure (no DB, unknown file, external URL) quietly falls back to "no extra
 * information" and the hero renders exactly as it would without them.
 */
final class HeroVideoMediaLookup
{
    /** "/media/2026/10/x.mp4" (or an absolute URL on this site) -> "storage/media/2026/10/x.mp4"; anything else -> "". */
    public static function storagePath(string $url): string
    {
        $path = (string)(parse_url(trim($url), PHP_URL_PATH) ?? '');
        if (!str_starts_with($path, '/media/') || str_contains($path, '..')) {
            return '';
        }

        return 'storage/media/' . ltrim(substr($path, strlen('/media/')), '/');
    }

    /**
     * "/media/x.mp4" -> "/media/x.mp4?rev=<file mtime>". Media answers are cached for a year
     * ("immutable"), also by a cache in front of PHP, so a file that was once answered the old
     * way (no byte ranges, no video type) would be answered that way forever. A version in the
     * URL makes the cache treat it as a new address, and changes with the file when it is replaced.
     */
    public static function versionedUrl(string $url): string
    {
        $path = self::storagePath($url);
        if ($path === '' || str_contains($url, '?')) {
            return $url;
        }
        $file = dirname(__DIR__, 2) . '/' . $path;
        $mtime = is_file($file) ? (int)filemtime($file) : 0;

        // Not "v": YouTube links carry ?v=<id>, and a version parameter must never look like one.
        return $mtime > 0 ? $url . '?rev=' . $mtime : $url;
    }

    /**
     * Aspect ratio per width, taken from the dimensions stored when the video was uploaded, so
     * the page can reserve the right box before the video's own metadata arrives (that late
     * arrival used to resize the element and shift the whole hero).
     *
     * @param array{desktop?:string,tablet?:string,mobile?:string} $urls
     * @return array<string,string> width => "W / H"
     */
    public static function ratios(int $siteId, array $urls): array
    {
        $ratios = [];
        try {
            $repo = new MediaRepository();
            foreach (['desktop', 'tablet', 'mobile'] as $width) {
                $path = self::storagePath((string)($urls[$width] ?? ''));
                $media = $path !== '' ? $repo->findByStoragePath($siteId, $path) : null;
                $w = (int)($media['width'] ?? 0);
                $h = (int)($media['height'] ?? 0);
                if ($w > 0 && $h > 0) {
                    $ratios[$width] = $w . ' / ' . $h;
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return $ratios;
    }

    /**
     * The poster of a native <video> is one plain URL the browser downloads whole, so a
     * multi-megabyte original poster is the page's LCP. Use the library's medium WebP variant
     * of the same picture when there is one.
     */
    public static function lightPoster(int $siteId, string $posterUrl): string
    {
        $path = self::storagePath($posterUrl);
        if ($path === '') {
            return $posterUrl;
        }
        try {
            $repo = new MediaRepository();
            $media = $repo->findByStoragePath($siteId, $path);
            foreach (['medium', 'small'] as $format) {
                $variant = $media ? $repo->variantByFormat((int)$media['id'], $format) : null;
                if ($variant && (string)$variant['path'] !== '') {
                    return '/media/' . ltrim((string)preg_replace('~^/?storage/media/~', '', str_replace('\\', '/', (string)$variant['path'])), '/');
                }
            }
        } catch (\Throwable) {
            return $posterUrl;
        }

        return $posterUrl;
    }
}
