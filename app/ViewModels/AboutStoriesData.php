<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Repositories\BlogRepository;
use App\Repositories\MediaRepository;
use App\Services\Render\MediaUrlResolver;

/**
 * Stable pseudo-random sample of real published posts for the About page.
 */
final class AboutStoriesData
{
    /**
     * @return list<array{id:int,slug:string,title:string,excerpt:string,image_url:string,date:string}>
     */
    public static function forSite(int $siteId, int $limit = 3): array
    {
        $posts = BlogListingData::forSite($siteId)['posts'];
        $posts = array_values(array_filter(
            $posts,
            static fn(array $post): bool => trim((string)($post['slug'] ?? '')) !== ''
                && trim((string)($post['title'] ?? '')) !== ''
        ));

        usort($posts, static function (array $left, array $right) use ($siteId): int {
            $leftKey = hash('sha256', $siteId . ':' . (string)$left['slug']);
            $rightKey = hash('sha256', $siteId . ':' . (string)$right['slug']);
            return strcmp($leftKey, $rightKey);
        });

        $selected = array_slice($posts, 0, max(0, $limit));
        $blog = new BlogRepository();
        $media = new MediaRepository();
        foreach ($selected as &$post) {
            $source = $blog->findById((int)$post['id']);
            $image = !empty($source['featured_image_id'])
                ? $media->findByIdWithThumb((int)$source['featured_image_id'])
                : null;
            if ($image) {
                $post['image_url'] = MediaUrlResolver::publicMediaUrl(
                    (string)($image['medium_path'] ?? $image['thumb_path'] ?? $image['storage_path'] ?? '')
                );
            }
        }
        unset($post);

        return $selected;
    }
}
