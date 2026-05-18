<?php

namespace App\Services;

use App\Repositories\BlockRepository;
use App\Repositories\BlogRepository;
use App\Repositories\PageRepository;

class BlogSyncService
{
    public function syncHomepageBlocks(BlogRepository $repo, int $siteId): void
    {
        $page = (new PageRepository())->findByUrlPath('');
        if (!$page) return;

        $posts  = array_slice($repo->allPublished($siteId), 0, 3);
        $blocks = new BlockRepository();
        $h      = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);

        for ($i = 0; $i < 3; $i++) {
            $key = 'blog_' . ($i + 1);
            if (isset($posts[$i])) {
                $post    = $posts[$i];
                $excerpt = mb_strimwidth(strip_tags((string)($post['excerpt'] ?? '')), 0, 120, '…');
                $content = '<div class="hp-blog-body">'
                    . '<h3 class="hp-blog-title"><a href="/' . $h($post['slug']) . '" class="blog-title-link">'
                    . $h($post['title']) . '</a></h3>'
                    . '<p class="hp-blog-excerpt">' . $h($excerpt) . '</p>'
                    . '<a href="/' . $h($post['slug']) . '" class="hp-blog-link">&#268;&#237;st d&#225;l &rarr;</a>'
                    . '</div>';
            } else {
                $content = '';
            }

            $blocks->upsert((int)$page['id'], $key, [
                'block_type' => 'html',
                'label'      => 'Článek ' . ($i + 1),
                'content'    => $content,
                'sort_order' => 32 + $i,
            ]);
        }
    }
}
