<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Repositories\PortfolioRepository;
use App\Services\Render\MediaUrlResolver;

/**
 * Pure data provider for the `portfolio-detail` archetype
 * (app/Views/archetypes/portfolio-detail.phtml).
 *
 * Phase 4b of the templating refactor: replaces the ad-hoc HTML built inline in
 * public/index.php::tryServePortfolioPage() (the single-entry detail branch).
 * Returns scalars/arrays only - no HTML, no escaping (the view escapes).
 *
 * `entry.description` is the raw stored portfolio copy (the view emits it via
 * raw(); the live pipeline sanitizes it downstream, exactly as the old heredoc
 * did). `entry.video_embed_url` is the embed URL of the FIRST
 * video (or '' when the entry has none) and `entry.videos` carries all of them. Gallery/media URLs use MediaUrlResolver::publicMediaUrl()
 * - the same function the live heredoc used - so the render stays byte-identical.
 *
 * Detail is served live-only (portfolio entries are not `pages` rows and there is
 * no PublishService golden target for them); PortfolioPublishService generates a
 * separate standalone static export - see the UKOL.md "PortfolioPublishService"
 * note. This model is not wired into PublishService::renderArchetypeContent().
 */
final class PortfolioDetailData
{
    /**
     * @param array $entry a portfolio_entries row (already fetched by the caller)
     * @return array{entry: array{title:string,meta_title:string,description:string,video_embed_url:string,videos:list<array{url:string,embed_url:string,thumbnail:string}>,meta_description:string,hero_image:string}, gallery: list<array{thumb:string,medium:string,full:string,alt:string}>}
     */
    public static function forEntry(array $entry): array
    {
        $mediaItems = (new PortfolioRepository())->getMedia((int)($entry['id'] ?? 0));

        $gallery = [];
        foreach ($mediaItems as $media) {
            $gallery[] = [
                'thumb' => MediaUrlResolver::publicMediaUrl(
                    (string)($media['thumb_path'] ?? $media['medium_path'] ?? $media['storage_path'] ?? '')
                ),
                'medium' => MediaUrlResolver::publicMediaUrl(
                    (string)($media['medium_path'] ?? $media['storage_path'] ?? $media['thumb_path'] ?? '')
                ),
                'full' => MediaUrlResolver::publicMediaUrl((string)($media['storage_path'] ?? '')),
                'alt' => (string)($media['alt_text'] ?? $entry['title'] ?? ''),
            ];
        }

        // Every video of the entry, in admin order. `video_embed_url` stays the first
        // one, so an entry with a single video renders exactly as it did before.
        $videos = (new PortfolioRepository())->getVideos((int)($entry['id'] ?? 0));
        $embedUrl = (string)($videos[0]['embed_url'] ?? '');

        $description = (string)($entry['description'] ?? '');
        // Byte-for-byte the old inline logic: `?? strip_tags(...)` only falls back
        // on a NULL/absent meta_description (an empty string is kept as-is).
        $metaDescription = (string)($entry['meta_description'] ?? strip_tags(substr($description, 0, 160)));

        return [
            'entry' => [
                'title' => (string)($entry['title'] ?? ''),
                'meta_title' => trim((string)($entry['meta_title'] ?? '')) ?: (string)($entry['title'] ?? ''),
                'description' => $description,
                'video_embed_url' => $embedUrl,
                'videos' => $videos,
                'meta_description' => $metaDescription,
                'hero_image' => $gallery[0]['medium'] ?? '',
            ],
            'gallery' => $gallery,
        ];
    }
}
