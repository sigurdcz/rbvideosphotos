<?php

declare(strict_types=1);

namespace App\Services\Render;

use App\Repositories\MediaRepository;

final class ResponsiveImageMarkupService
{
    public static function optimize(string $html, int $siteId): string
    {
        $mediaMap = $siteId > 0 ? (new MediaRepository())->responsiveVariantMapBySite($siteId) : [];
        $hasExplicitPriorityCandidate = (bool)preg_match('~<img\b[^>]*\bdata-lcp-candidate(?:\s|=|>)~i', $html);
        $hasHeroImage = (bool)preg_match(
            '~<img\b(?=[^>]*\bclass=(["\'])[^"\']*(?:hero-bg|pf-detail-hero)[^"\']*\1)[^>]*>~i',
            $html
        );
        $priorityAssigned = false;

        return preg_replace_callback('~<img\b([^>]*)>~i', static function (array $match) use ($mediaMap, $hasExplicitPriorityCandidate, $hasHeroImage, &$priorityAssigned): string {
            $attrs = (string)$match[1];
            $src = self::attribute($attrs, 'src');
            $class = self::attribute($attrs, 'class');
            $responsiveProfile = self::attribute($attrs, 'data-responsive-profile');
            $isHero = (bool)preg_match('~(?:^|\s)(?:hero-bg|pf-detail-hero)(?:\s|$)~i', $class);
            $isLogo = (bool)preg_match('~(?:^|\s)site-logo-img(?:\s|$)~i', $class);
            $isExplicitPriorityCandidate = (bool)preg_match('~\bdata-lcp-candidate(?:\s|=|$)~i', $attrs);
            $isPriority = !$priorityAssigned && (
                $hasExplicitPriorityCandidate
                    ? $isExplicitPriorityCandidate
                    : ($hasHeroImage ? $isHero : $isLogo)
            );
            if ($isPriority) $priorityAssigned = true;

            $attrs = self::withoutAttributes($attrs, ['loading', 'fetchpriority', 'decoding']);
            // The header logo always sits above the fold, so it must never be deferred.
            // It stays off fetchpriority though - that slot belongs to the LCP image.
            $attrs .= ' decoding="async" loading="' . ($isPriority || $isLogo ? 'eager' : 'lazy') . '"';
            if ($isPriority) $attrs .= ' fetchpriority="high"';

            $storageKey = self::storageKey($src);
            $media = $storageKey !== '' ? ($mediaMap[$storageKey] ?? null) : null;
            if (is_array($media)) {
                $responsive = [];
                $formats = $responsiveProfile === 'portfolio-hero'
                    ? ['small', 'medium', 'large']
                    : (in_array($responsiveProfile, ['homepage-card', 'homepage-hero'], true) || $isHero
                        ? ['small', 'medium']
                        : ['small', 'medium', 'large']);
                foreach ($formats as $format) {
                    $variant = $media['variants'][$format] ?? null;
                    if (!is_array($variant) || (int)$variant['width'] <= 0 || (string)$variant['path'] === '') continue;
                    $responsive[(int)$variant['width']] = self::publicUrl((string)$variant['path']);
                }
                if ($responsive !== []) {
                    ksort($responsive);
                    $attrs = self::withoutAttributes($attrs, ['srcset', 'sizes']);
                    $parts = [];
                    foreach ($responsive as $width => $url) {
                        $parts[] = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' ' . $width . 'w';
                    }
                    $sizes = ($isHero || $responsiveProfile === 'homepage-hero')
                        ? '(max-width: 700px) calc(100vw - 32px), 62vw'
                        : ($isLogo
                            ? '(max-width: 720px) 120px, 160px'
                            : ($responsiveProfile === 'homepage-card'
                                ? '(max-width: 700px) calc(100vw - 36px), (max-width: 1050px) calc((100vw - 64px) / 2), 20vw'
                                : '(max-width: 720px) 100vw, 50vw'));
                    $attrs .= ' srcset="' . implode(', ', $parts) . '" sizes="' . $sizes . '"';

                    if ($formats === ['small', 'medium']) {
                        $preferredUrl = (string)end($responsive);
                        $attrs = self::withoutAttributes($attrs, ['src']);
                        $attrs .= ' src="' . htmlspecialchars($preferredUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
                    }
                }

                if (!preg_match('~\bwidth=~i', $attrs) || !preg_match('~\bheight=~i', $attrs)) {
                    $dimensions = self::dimensionsForPath($storageKey, $media);
                    if ($dimensions[0] > 0 && $dimensions[1] > 0) {
                        if (!preg_match('~\bwidth=~i', $attrs)) $attrs .= ' width="' . $dimensions[0] . '"';
                        if (!preg_match('~\bheight=~i', $attrs)) $attrs .= ' height="' . $dimensions[1] . '"';
                    }
                }
            } elseif (!preg_match('~\bwidth=~i', $attrs) || !preg_match('~\bheight=~i', $attrs)) {
                $dimensions = self::localDimensions($src);
                if ($dimensions[0] > 0 && $dimensions[1] > 0) {
                    if (!preg_match('~\bwidth=~i', $attrs)) $attrs .= ' width="' . $dimensions[0] . '"';
                    if (!preg_match('~\bheight=~i', $attrs)) $attrs .= ' height="' . $dimensions[1] . '"';
                }
            }

            return '<img' . $attrs . '>';
        }, $html) ?? $html;
    }

    private static function attribute(string $attrs, string $name): string
    {
        if (!preg_match('~\b' . preg_quote($name, '~') . '=(["\'])(.*?)\1~i', $attrs, $match)) return '';
        return html_entity_decode((string)$match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function withoutAttributes(string $attrs, array $names): string
    {
        foreach ($names as $name) {
            $attrs = preg_replace('~\s+\b' . preg_quote($name, '~') . '=(["\']).*?\1~i', '', $attrs) ?? $attrs;
        }
        return $attrs;
    }

    private static function storageKey(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') return '';
        if (str_starts_with($path, '/media/')) return 'storage/media/' . ltrim(substr($path, 7), '/');
        return ltrim($path, '/');
    }

    private static function publicUrl(string $storagePath): string
    {
        $path = '/' . ltrim($storagePath, '/');
        return str_starts_with($path, '/storage/media/') ? '/media/' . substr($path, 15) : $path;
    }

    /** @return array{0:int,1:int} */
    private static function dimensionsForPath(string $storageKey, array $media): array
    {
        if ((string)$media['original']['path'] === $storageKey) {
            return [(int)$media['original']['width'], (int)$media['original']['height']];
        }
        foreach ($media['variants'] as $variant) {
            if ((string)($variant['path'] ?? '') === $storageKey) {
                return [(int)($variant['width'] ?? 0), (int)($variant['height'] ?? 0)];
            }
        }
        return [(int)$media['original']['width'], (int)$media['original']['height']];
    }

    /** @return array{0:int,1:int} */
    private static function localDimensions(string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path)) return [0, 0];
        $root = dirname(__DIR__, 3);
        $local = str_starts_with($path, '/media/')
            ? $root . '/storage/media/' . ltrim(substr($path, 7), '/')
            : (str_starts_with($path, '/assets/') ? $root . '/public' . $path : '');
        if ($local === '' || !is_file($local)) return [0, 0];
        $size = @getimagesize($local);
        return is_array($size) ? [(int)$size[0], (int)$size[1]] : [0, 0];
    }
}
