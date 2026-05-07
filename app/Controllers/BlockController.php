<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\BlockRepository;
use App\Repositories\PageRepository;
use App\Repositories\TemplateRepository;
use App\Services\TemplateParser;

class BlockController extends Controller
{
    /** Re-parse layout blocks for a given page and upsert them. */
    public function syncFromTemplate(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $pageId = (int) ($_POST['page_id'] ?? 0);
        $page = (new PageRepository())->findById($pageId);
        if (!$page || !$page['template_id']) {
            $this->json(['error' => 'Stranka nebo layout nebyl nalezen.'], 400);
        }

        $layout = (new TemplateRepository())->findById((int) $page['template_id']);
        if (!$layout) {
            $this->json(['error' => 'Layout nebyl nalezen.'], 400);
        }

        $parser = new TemplateParser();
        $blocks = $parser->parseBlocks($layout['html_source']);
        $repo = new BlockRepository();

        foreach ($blocks as $block) {
            $existing = $repo->findByKey($pageId, $block['block_key']);
            if (!$existing) {
                $repo->upsert($pageId, $block['block_key'], $block);
            }
        }

        $this->flash('success', 'Bloky byly synchronizovany z layoutu.');
        $this->redirect('/admin/pages/edit?id=' . $pageId);
    }
}
