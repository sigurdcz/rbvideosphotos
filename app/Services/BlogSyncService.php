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
                $content = '<h3 style="margin:0 0 10px;font-size:1rem;color:#fff;line-height:1.35">'
                    . '<a href="/' . $h($post['slug']) . '" style="color:inherit;text-decoration:none">'
                    . $h($post['title']) . '</a></h3>'
                    . '<p style="color:rgba(255,255,255,.65);font-size:.875rem;line-height:1.55;margin:0 0 16px">'
                    . $h($excerpt) . '</p>'
                    . '<a href="/' . $h($post['slug']) . '" style="color:#c4963c;font-weight:700;font-size:.875rem">&#268;&#237;st d&#225;l &rarr;</a>';
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
