<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Repositories\BlogRepository;
use App\Repositories\MediaRepository;
use App\Services\Render\MediaUrlResolver;

/**
 * Pure data provider for the `blog-detail` archetype
 * (app/Views/archetypes/blog-detail.phtml).
 *
 * Phase 4b of the templating refactor: replaces the ad-hoc HTML built inline in
 * public/index.php::tryServeBlogPostPage(). Returns scalars/arrays only — no
 * HTML, no escaping (the view calls ViewContext::e()/raw()).
 *
 * `post.content` is the raw stored article HTML (the view emits it via raw();
 * the live pipeline sanitizes it downstream, exactly as the old heredoc did).
 * Media URLs use MediaUrlResolver::publicMediaUrl() — the same function the live
 * heredoc used — so the render stays byte-identical.
 */
final class BlogPostData
{
    /**
     * @param array $post a blog_posts row (already fetched by the caller)
     * @return array{post: array{date:string,title:string,excerpt:string,content:string,image_url:string}, gallery: list<array{thumb:string,full:string,alt:string,label:string}>}
     */
    public static function forPost(array $post): array
    {
        $imageUrl = '';
        if (!empty($post['featured_image_id'])) {
            $media = (new MediaRepository())->findByIdWithThumb((int)$post['featured_image_id']);
            if ($media) {
                $imageUrl = MediaUrlResolver::publicMediaUrl(
                    (string)($media['medium_path'] ?? $media['storage_path'] ?? '')
                );
            }
        }

        try {
            $galleryItems = (new BlogRepository())->getGallery((int)($post['id'] ?? 0));
        } catch (\Throwable $e) {
            $galleryItems = [];
        }

        $gallery = [];
        foreach ($galleryItems as $idx => $g) {
            $label = trim((string)($g['alt_text'] ?? '')) !== ''
                ? trim((string)$g['alt_text'])
                : ('Fotka ' . ($idx + 1));
            $gallery[] = [
                'thumb' => MediaUrlResolver::publicMediaUrl(
                    (string)($g['thumb_path'] ?? $g['medium_path'] ?? $g['storage_path'] ?? '')
                ),
                'full' => MediaUrlResolver::publicMediaUrl((string)($g['storage_path'] ?? '')),
                'alt' => (string)($g['alt_text'] ?? ''),
                'label' => $label,
            ];
        }

        return [
            'post' => [
                'date' => !empty($post['published_at'])
                    ? date('j. n. Y', (int)strtotime((string)$post['published_at']))
                    : '',
                'title' => (string)($post['title'] ?? ''),
                'excerpt' => (string)($post['excerpt'] ?? ''),
                'content' => (string)($post['content'] ?? ''),
                'image_url' => $imageUrl,
            ],
            'gallery' => $gallery,
        ];
    }
}
