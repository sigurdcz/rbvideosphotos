<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Repositories\BlogRepository;
use App\Repositories\MediaRepository;
use App\Services\Render\MediaUrlResolver;

/**
 * Pure data provider for the `blog-listing` archetype
 * (app/Views/archetypes/blog-listing.phtml).
 *
 * Phase 4b of the templating refactor: replaces the ad-hoc HTML built inline in
 * public/index.php::tryServeBlogListingPage(). Returns scalars/arrays only - no
 * HTML, no escaping (the view calls ViewContext::e()/inlineAttr()).
 *
 * Media URLs are resolved through MediaUrlResolver::publicMediaUrl() - byte for
 * byte the same function the live heredoc used (public/index.php::publicMediaUrl
 * delegates to it), so the live render stays identical and the publish render
 * gains the same cover thumbnails.
 */
final class BlogListingData
{
    /**
     * @return array{posts: list<array{id:int,slug:string,title:string,excerpt:string,image_url:string,date:string}>}
     */
    public static function forSite(int $siteId): array
    {
        $mediaRepo = new MediaRepository();
        $posts = [];

        foreach ((new BlogRepository())->allPublished($siteId) as $post) {
            $imageUrl = '';
            if (!empty($post['featured_image_id'])) {
                $media = $mediaRepo->findByIdWithThumb((int)$post['featured_image_id']);
                if ($media) {
                    $imageUrl = MediaUrlResolver::publicMediaUrl(
                        (string)($media['storage_path'] ?? $media['medium_path'] ?? $media['thumb_path'] ?? '')
                    );
                }
            }

            $posts[] = [
                'id' => (int)($post['id'] ?? 0),
                'slug' => (string)($post['slug'] ?? ''),
                'title' => (string)($post['title'] ?? ''),
                'excerpt' => (string)($post['excerpt'] ?? ''),
                'image_url' => $imageUrl,
                'date' => !empty($post['published_at'])
                    ? date('j. n. Y', (int)strtotime((string)$post['published_at']))
                    : '',
            ];
        }

        return ['posts' => $posts];
    }
}
