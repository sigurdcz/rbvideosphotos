<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Repositories\PortfolioRepository;

/** Shared metadata for the primary video in live pages and standalone exports. */
final class PortfolioVideoSchema
{
    public static function build(array $site, array $entry, string $canonical, ?string $imageUrl = null): array
    {
        $videoUrl = trim((string)($entry['video_url'] ?? ''));
        if ($videoUrl === '') {
            return [];
        }

        // Explicit sources: editor-owned portfolio image, then the actual YouTube
        // video's thumbnail. Never substitute a site logo or unrelated OG image.
        $thumbnail = trim((string)$imageUrl);
        if ($thumbnail === '') {
            $thumbnail = PortfolioRepository::youtubeThumbnail($videoUrl);
        }
        if (str_starts_with($thumbnail, '//')) {
            $thumbnail = 'https:' . $thumbnail;
        } elseif ($thumbnail !== '' && !preg_match('~^https?://~i', $thumbnail)) {
            $thumbnail = rtrim((string)($site['domain'] ?? ''), '/') . '/' . ltrim($thumbnail, '/');
        }
        if (filter_var($thumbnail, FILTER_VALIDATE_URL) === false || !preg_match('~^https?://~i', $thumbnail)) {
            Logger::warning('portfolio-video-schema', 'VideoObject omitted: no public video thumbnail is available.', [
                'portfolio_id' => $entry['id'] ?? null,
                'canonical' => $canonical,
            ]);
            return [];
        }

        $payload = [
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'name' => trim((string)($entry['title'] ?? 'Ukázka videa')),
            'description' => trim(strip_tags((string)($entry['meta_description'] ?? $entry['description'] ?? ''))),
            'url' => $canonical,
            'embedUrl' => PortfolioRepository::embedUrl($videoUrl),
            'thumbnailUrl' => $thumbnail,
        ];
        if (!empty($entry['published_at'])) {
            $payload['uploadDate'] = date(DATE_ATOM, strtotime((string)$entry['published_at']));
        }
        return $payload;
    }
}
