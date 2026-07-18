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
 * Returns scalars/arrays only — no HTML, no escaping (the view escapes).
 *
 * `entry.description` is the raw stored portfolio copy (the view emits it via
 * raw(); the live pipeline sanitizes it downstream, exactly as the old heredoc
 * did). `entry.video_embed_url` is the YouTube/Vimeo embed URL (or '' when the
 * entry has no video). Gallery/media URLs use MediaUrlResolver::publicMediaUrl()
 * — the same function the live heredoc used — so the render stays byte-identical.
 *
 * Detail is served live-only (portfolio entries are not `pages` rows and there is
 * no PublishService golden target for them); PortfolioPublishService generates a
 * separate standalone static export — see the UKOL.md "PortfolioPublishService"
 * note. This model is not wired into PublishService::renderArchetypeContent().
 */
final class PortfolioDetailData
{
    /**
     * @param array $entry a portfolio_entries row (already fetched by the caller)
     * @return array{entry: array{title:string,description:string,video_embed_url:string,meta_description:string}, gallery: list<array{thumb:string,full:string,alt:string}>}
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
                'full' => MediaUrlResolver::publicMediaUrl((string)($media['storage_path'] ?? '')),
                'alt' => (string)($media['alt_text'] ?? $entry['title'] ?? ''),
            ];
        }

        $embedUrl = !empty($entry['video_url'])
            ? PortfolioRepository::embedUrl((string)$entry['video_url'])
            : '';

        $description = (string)($entry['description'] ?? '');
        // Byte-for-byte the old inline logic: `?? strip_tags(...)` only falls back
        // on a NULL/absent meta_description (an empty string is kept as-is).
        $metaDescription = (string)($entry['meta_description'] ?? strip_tags(substr($description, 0, 160)));

        return [
            'entry' => [
                'title' => (string)($entry['title'] ?? ''),
                'description' => $description,
                'video_embed_url' => $embedUrl,
                'meta_description' => $metaDescription,
            ],
            'gallery' => $gallery,
        ];
    }
}
