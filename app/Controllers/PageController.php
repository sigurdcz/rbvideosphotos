<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\BlockRepository;
use App\Repositories\PageRepository;
use App\Repositories\SeoRepository;
use App\Repositories\SiteRepository;
use App\Repositories\TemplateRepository;
use App\Services\TemplateParser;

class PageController extends Controller
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $sites = new SiteRepository();
        $site  = $sites->findFirst();
        $repo  = new PageRepository();

        $this->view('pages.index', [
            'pages'   => $site ? $repo->allBySite($site['id']) : [],
            'site'    => $site,
            'success' => $this->getFlash('success'),
            'error'   => $this->getFlash('error'),
        ]);
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $sites     = new SiteRepository();
        $site      = $sites->findFirst();
        $templates = $site ? (new TemplateRepository())->allBySite($site['id']) : [];

        $this->view('pages.form', [
            'page'      => null,
            'site'      => $site,
            'templates' => $templates,
        ]);
    }

    public function store(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $sites = new SiteRepository();
        $site  = $sites->findFirst();
        if (!$site) {
            $this->flash('error', 'Nejprve nastavte web.');
            $this->redirect('/admin/pages');
        }

        $slug    = $this->makeSlug($_POST['slug'] ?? $_POST['title'] ?? '');
        $rawUrl  = trim($_POST['url_path'] ?? '');
        $urlPath = $rawUrl !== '' ? $this->makeSlug($rawUrl) : $slug;
        $repo    = new PageRepository();

        if ($repo->slugExists($site['id'], $slug)) {
            $this->flash('error', 'Slug již existuje.');
            $this->redirect('/admin/pages/create');
        }

        $pageId = $repo->create([
            'site_id'     => $site['id'],
            'template_id' => (int)($_POST['template_id'] ?? 0) ?: null,
            'title'       => trim($_POST['title'] ?? ''),
            'slug'        => $slug,
            'url_path'    => $urlPath,
            'language'    => $_POST['language'] ?? 'cs',
            'status'      => 'draft',
        ]);

        // Parse blocks from template
        $templateId = (int)($_POST['template_id'] ?? 0);
        if ($templateId) {
            $this->initBlocksFromTemplate($pageId, $templateId);
        }

        $this->flash('success', 'Stránka vytvořena.');
        $this->redirect('/admin/pages/edit?id=' . $pageId);
    }

    public function edit(array $params): void
    {
        $this->requireAuth();
        $id   = (int)($_GET['id'] ?? 0);
        $repo = new PageRepository();
        $page = $repo->findById($id);
        if (!$page) {
            $this->flash('error', 'Stránka nenalezena.');
            $this->redirect('/admin/pages');
        }

        $sites     = new SiteRepository();
        $blocks    = (new BlockRepository())->allByPage($id);
        $templates = (new TemplateRepository())->allBySite($page['site_id']);

        $this->view('pages.form', [
            'page'      => $page,
            'blocks'    => $blocks,
            'templates' => $templates,
            'site'      => $sites->findById($page['site_id']),
            'success'   => $this->getFlash('success'),
            'error'     => $this->getFlash('error'),
        ]);
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $id   = (int)($_POST['id'] ?? 0);
        $repo = new PageRepository();
        $page = $repo->findById($id);
        if (!$page) {
            $this->redirect('/admin/pages');
        }

        $slug    = $this->makeSlug($_POST['slug'] ?? '');
        $rawUrl  = trim($_POST['url_path'] ?? '');
        $urlPath = $rawUrl !== '' ? $this->makeSlug($rawUrl) : '';

        if ($repo->slugExists($page['site_id'], $slug, $id)) {
            $this->flash('error', 'Slug již existuje.');
            $this->redirect('/admin/pages/edit?id=' . $id);
        }

        $repo->update($id, [
            'title'       => trim($_POST['title'] ?? ''),
            'slug'        => $slug,
            'url_path'    => $urlPath,
            'language'    => $_POST['language'] ?? 'cs',
            'template_id' => (int)($_POST['template_id'] ?? 0) ?: null,
        ]);

        // Save blocks
        $blockRepo = new BlockRepository();
        $keys      = $_POST['block_key']     ?? [];
        $contents  = $_POST['block_content'] ?? [];
        foreach ($keys as $i => $key) {
            $blockRepo->updateContent($id, $key, $contents[$i] ?? '');
        }

        $this->flash('success', 'Stránka uložena.');
        $this->redirect('/admin/pages/edit?id=' . $id);
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();
        $id = (int)($_POST['id'] ?? 0);
        (new PageRepository())->delete($id);
        $this->flash('success', 'Stránka smazána.');
        $this->redirect('/admin/pages');
    }

    private function makeSlug(string $value): string
    {
        $value = mb_strtolower(trim($value));
        // transliterate Czech diacritics
        $value = $this->removeDiacritics($value);
        $value = preg_replace('/[^a-z0-9\-\/]/', '-', $value);
        $value = preg_replace('/-+/', '-', $value);
        return trim($value, '-/');
    }

    private function removeDiacritics(string $str): string
    {
        $map = [
            'á'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','í'=>'i','ň'=>'n',
            'ó'=>'o','ř'=>'r','š'=>'s','ť'=>'t','ú'=>'u','ů'=>'u','ý'=>'y','ž'=>'z',
            'Á'=>'a','Č'=>'c','Ď'=>'d','É'=>'e','Ě'=>'e','Í'=>'i','Ň'=>'n',
            'Ó'=>'o','Ř'=>'r','Š'=>'s','Ť'=>'t','Ú'=>'u','Ů'=>'u','Ý'=>'y','Ž'=>'z',
        ];
        return strtr($str, $map);
    }

    private function initBlocksFromTemplate(int $pageId, int $templateId): void
    {
        $tmpl = (new TemplateRepository())->findById($templateId);
        if (!$tmpl) return;

        $parser = new TemplateParser();
        $blocks = $parser->parseBlocks($tmpl['html_source']);
        $repo   = new BlockRepository();
        foreach ($blocks as $block) {
            $repo->upsert($pageId, $block['block_key'], $block);
        }
    }
}
