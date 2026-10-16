<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Repositories\PortfolioRepository;
use App\Services\Render\MediaUrlResolver;
use App\Services\Render\PortfolioCaptionFormatter;

/**
 * Pure data provider for the `portfolio-listing` archetype
 * (app/Views/archetypes/portfolio-listing.phtml).
 *
 * Phase 4b of the templating refactor: replaces the ad-hoc HTML built inline in
 * public/index.php::tryServePortfolioPage() (the /nase-prace listing branch).
 * Returns scalars/arrays only - no HTML, no escaping (the view escapes).
 *
 * The cover thumbnail is a YouTube thumbnail when the entry has a video, else
 * the first gallery photo resolved through MediaUrlResolver::publicMediaUrl()
 * (the same function the live heredoc used). The excerpt is derived + truncated
 * here (data prep), byte for byte the same as the old inline logic.
 */
final class PortfolioListingData
{
    /**
     * @return array{entries: list<array{id:int,slug:string,title:string,thumb:string,has_video:bool,video_embed_url:string,excerpt:string,type_label:string,date:string,photos:list<array{url:string,alt:string}>}>}
     */
    public static function forSite(int $siteId): array
    {
        $repo = new PortfolioRepository();
        $entries = [];

        foreach ($repo->allPublished($siteId) as $entry) {
            $photos = $repo->getMedia((int)$entry['id']);
            $hasVideo = !empty($entry['video_url']);
            $thumb = $hasVideo ? PortfolioRepository::youtubeThumbnail((string)$entry['video_url']) : '';
            if ($thumb === '' && !empty($photos)) {
                $thumbFile = $photos[0]['storage_path'] ?? $photos[0]['medium_path'] ?? $photos[0]['thumb_path'] ?? '';
                $thumb = MediaUrlResolver::publicMediaUrl((string)$thumbFile);
            }

            // Short excerpt from the work's copy (shown under the title in the grid).
            $caption = PortfolioCaptionFormatter::fromHtml((string)($entry['description'] ?? ''), 220);
            $excerptSrc = trim((string)($entry['meta_description'] ?? ''));
            if ($excerptSrc === '' || str_starts_with($excerptSrc, 'Ukázková práce fotografa')) {
                $excerptSrc = $caption['description'];
            }
            if ($excerptSrc !== '' && mb_strlen($excerptSrc) > 150) {
                $cut = mb_substr($excerptSrc, 0, 150);
                $sp  = mb_strrpos($cut, ' ');
                $excerptSrc = rtrim($sp !== false ? mb_substr($cut, 0, $sp) : $cut, " ,.;:") . '…';
            }

            $entries[] = [
                'id' => (int)($entry['id'] ?? 0),
                'slug' => (string)($entry['slug'] ?? ''),
                'title' => (string)($entry['title'] ?? ''),
                // The hand-typed order; interleaveByContentType() groups by it.
                'sort_order' => (int)($entry['sort_order'] ?? 0),
                'thumb' => $thumb,
                'has_video' => $hasVideo,
                'video_embed_url' => $hasVideo ? PortfolioRepository::embedUrl((string)$entry['video_url']) : '',
                'content_type' => ($entry['content_type'] ?? '') === 'video' ? 'video' : 'photo',
                'excerpt' => $excerptSrc,
                'gallery_description' => $caption['description'],
                'gallery_author' => $caption['author'],
                'type_label' => $hasVideo ? 'Video & galerie' : 'Fotogalerie',
                'date' => !empty($entry['published_at'])
                    ? date('j. n. Y', (int)strtotime((string)$entry['published_at']))
                    : '',
                'photos' => array_values(array_filter(array_map(static function (array $photo) use ($entry): array {
                    return [
                        'url' => MediaUrlResolver::publicMediaUrl((string)($photo['storage_path'] ?? '')),
                        'alt' => (string)($photo['alt_text'] ?? $entry['title'] ?? ''),
                    ];
                }, $photos), static fn(array $photo): bool => $photo['url'] !== '')),
            ];
        }

        return [
            'entries' => $entries,
            // A handful of photos followed by a video, repeating - the single
            // "Vše" grid the theme filters client-side, not grouped by type.
            'mixedEntries' => PortfolioRepository::interleaveByContentType($entries),
            'photoEntries' => array_values(array_filter($entries, static fn(array $entry): bool => $entry['content_type'] === 'photo')),
            'videoEntries' => array_values(array_filter($entries, static fn(array $entry): bool => $entry['content_type'] === 'video')),
        ];
    }
}
