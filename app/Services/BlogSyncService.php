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

        $posts = array_slice($repo->allPublished($siteId), 0, 3);
        $blocks = new BlockRepository();
        // The same builder the homepage autofill uses, so a saved article keeps the
        // card it had - with its photo - instead of a plainer copy of it.
        $homepage = new HomepageContentService();

        for ($i = 0; $i < 3; $i++) {
            $key = 'blog_' . ($i + 1);
            $content = isset($posts[$i]) ? $homepage->buildBlogCardHtml($siteId, $i) : '';

            $blocks->upsert((int)$page['id'], $key, [
                'block_type' => 'html',
                'label'      => 'Článek ' . ($i + 1),
                'content'    => $content,
                'sort_order' => 32 + $i,
            ]);
        }
    }
}
