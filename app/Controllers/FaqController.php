<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\BlockRepository;
use App\Repositories\FaqRepository;
use App\Repositories\PageRepository;
use App\Repositories\SiteRepository;

class FaqController extends Controller
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $site  = (new SiteRepository())->findFirst();
        $items = $site ? $this->repo()->allBySite($site['id']) : [];

        $this->view('faq.index', [
            'items'   => $items,
            'site'    => $site,
            'success' => $this->getFlash('success'),
            'error'   => $this->getFlash('error'),
        ]);
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->view('faq.form', ['item' => null]);
    }

    public function store(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $site = (new SiteRepository())->findFirst();
        if (!$site) {
            $this->flash('error', 'Web nenalezen.');
            $this->redirect('/admin/faq');
        }

        $repo = $this->repo();
        $repo->create([
            'site_id'    => $site['id'],
            'question'   => trim($_POST['question'] ?? ''),
            'answer'     => trim($_POST['answer']   ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active'  => isset($_POST['is_active']) ? 1 : 0,
        ]);

        $this->syncHomepageFaqBlocks($repo, $site['id']);
        $this->flash('success', 'Otázka přidána.');
        $this->redirect('/admin/faq');
    }

    public function edit(array $params): void
    {
        $this->requireAuth();
        $id   = (int)($_GET['id'] ?? 0);
        $item = $this->repo()->findById($id);
        if (!$item) {
            $this->flash('error', 'Položka nenalezena.');
            $this->redirect('/admin/faq');
        }

        $this->view('faq.form', [
            'item'    => $item,
            'success' => $this->getFlash('success'),
            'error'   => $this->getFlash('error'),
        ]);
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $id   = (int)($_POST['id'] ?? 0);
        $repo = $this->repo();
        if (!$repo->findById($id)) {
            $this->flash('error', 'Položka nenalezena.');
            $this->redirect('/admin/faq');
        }

        $repo->update($id, [
            'question'   => trim($_POST['question'] ?? ''),
            'answer'     => trim($_POST['answer']   ?? ''),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active'  => isset($_POST['is_active']) ? 1 : 0,
        ]);

        $site = (new SiteRepository())->findFirst();
        if ($site) {
            $this->syncHomepageFaqBlocks($repo, $site['id']);
        }

        $this->flash('success', 'Otázka uložena.');
        $this->redirect('/admin/faq/edit?id=' . $id);
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $id   = (int)($_POST['id'] ?? 0);
        $repo = $this->repo();
        $repo->delete($id);

        $site = (new SiteRepository())->findFirst();
        if ($site) {
            $this->syncHomepageFaqBlocks($repo, $site['id']);
        }

        $this->flash('success', 'Otázka smazána.');
        $this->redirect('/admin/faq');
    }

    // ── Homepage sync ─────────────────────────────────────────────────────────

    private function syncHomepageFaqBlocks(FaqRepository $repo, int $siteId): void
    {
        $page = (new PageRepository())->findByUrlPath('');
        if (!$page) return;

        $items  = array_slice($repo->allActive($siteId), 0, 3);
        $blocks = new BlockRepository();
        $h      = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);

        for ($i = 0; $i < 3; $i++) {
            $key = 'faq_' . ($i + 1);
            if (isset($items[$i])) {
                $item    = $items[$i];
                $content = '<strong style="display:block;color:#fff;margin-bottom:8px">'
                    . $h($item['question']) . '</strong>'
                    . '<p style="color:rgba(255,255,255,.72);line-height:1.6;margin:0">'
                    . nl2br($h($item['answer'])) . '</p>';
            } else {
                $content = '';
            }
            $blocks->upsert((int)$page['id'], $key, [
                'block_type' => 'html',
                'label'      => 'FAQ ' . ($i + 1),
                'content'    => $content,
                'sort_order' => 51 + $i,
            ]);
        }
    }

    private function repo(): FaqRepository
    {
        return new FaqRepository();
    }
}
