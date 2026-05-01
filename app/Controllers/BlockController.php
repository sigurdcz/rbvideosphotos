<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\BlockRepository;
use App\Repositories\PageRepository;
use App\Services\TemplateParser;
use App\Repositories\TemplateRepository;

class BlockController extends Controller
{
    /** Re-parse template blocks for a given page and upsert them. */
    public function syncFromTemplate(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $pageId = (int)($_POST['page_id'] ?? 0);
        $page   = (new PageRepository())->findById($pageId);
        if (!$page || !$page['template_id']) {
            $this->json(['error' => 'Stránka nebo šablona nenalezena.'], 400);
        }

        $tmpl   = (new TemplateRepository())->findById((int)$page['template_id']);
        if (!$tmpl) {
            $this->json(['error' => 'Šablona nenalezena.'], 400);
        }

        $parser = new TemplateParser();
        $blocks = $parser->parseBlocks($tmpl['html_source']);
        $repo   = new BlockRepository();

        foreach ($blocks as $block) {
            // upsert without overwriting existing content
            $existing = $repo->findByKey($pageId, $block['block_key']);
            if (!$existing) {
                $repo->upsert($pageId, $block['block_key'], $block);
            }
        }

        $this->flash('success', 'Bloky synchronizovány ze šablony.');
        $this->redirect('/admin/pages/edit?id=' . $pageId);
    }
}
