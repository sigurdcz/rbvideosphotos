<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\SlugTrait;
use App\Repositories\MediaRepository;
use App\Repositories\PortfolioRepository;
use App\Repositories\SiteRepository;
use App\Services\PortfolioPublishService;

class PortfolioController extends Controller
{
    use SlugTrait;

    public function index(array $params): void
    {
        $this->requireAuth();
        $site    = (new SiteRepository())->findFirst();
        $repo    = new PortfolioRepository();
        $entries = $site ? $repo->allBySite($site['id']) : [];

        foreach ($entries as &$e) {
            $e['_first_media'] = $repo->getFirstMedia($e['id']);
        }
        unset($e);

        $this->view('portfolio.index', [
            'entries' => $entries,
            'site'    => $site,
            'success' => $this->getFlash('success'),
            'error'   => $this->getFlash('error'),
        ]);
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->view('portfolio.form', [
            'entry'    => null,
            'media'    => $this->allMedia(),
            'selected' => [],
        ]);
    }

    public function store(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $site = (new SiteRepository())->findFirst();
        if (!$site) {
            $this->flash('error', 'Web nenalezen.');
            $this->redirect('/admin/portfolio');
        }

        $slug   = $this->makeSlug($_POST['slug'] ?? '', $_POST['title'] ?? '');
        $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';

        $repo = new PortfolioRepository();
        $id   = $repo->create([
            'site_id'          => $site['id'],
            'title'            => trim($_POST['title'] ?? ''),
            'slug'             => $slug,
            'description'      => $_POST['description'] ?? '',
            'video_url'        => trim($_POST['video_url'] ?? '') ?: null,
            'status'           => $status,
            'published_at'     => $status === 'published' ? date('Y-m-d H:i:s') : null,
            'sort_order'       => (int)($_POST['sort_order'] ?? 0),
            'meta_title'       => trim($_POST['meta_title']       ?? '') ?: null,
            'meta_description' => trim($_POST['meta_description'] ?? '') ?: null,
        ]);

        $repo->setMedia($id, $_POST['media_ids'] ?? []);

        $svc = new PortfolioPublishService();
        if ($status === 'published') {
            $svc->publishEntry($repo->findById($id), $repo, $site);
        }
        $svc->regenerateListing($site, $this->currentUserId());

        $this->flash('success', 'Práce byla uložena.');
        $this->redirect('/admin/portfolio/edit?id=' . $id);
    }

    public function edit(array $params): void
    {
        $this->requireAuth();
        $id    = (int)($_GET['id'] ?? 0);
        $repo  = new PortfolioRepository();
        $entry = $repo->findById($id);
        if (!$entry) {
            $this->flash('error', 'Práce nenalezena.');
            $this->redirect('/admin/portfolio');
        }

        $selected = array_column($repo->getMedia($id), 'id');

        $this->view('portfolio.form', [
            'entry'    => $entry,
            'media'    => $this->allMedia(),
            'selected' => $selected,
            'success'  => $this->getFlash('success'),
            'error'    => $this->getFlash('error'),
        ]);
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $id    = (int)($_POST['id'] ?? 0);
        $repo  = new PortfolioRepository();
        $entry = $repo->findById($id);
        if (!$entry) {
            $this->flash('error', 'Práce nenalezena.');
            $this->redirect('/admin/portfolio');
        }

        $slug        = $this->makeSlug($_POST['slug'] ?? '', $_POST['title'] ?? '');
        $status      = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
        $publishedAt = $status === 'published'
            ? ($entry['published_at'] ?? date('Y-m-d H:i:s'))
            : null;

        $repo->update($id, [
            'title'            => trim($_POST['title'] ?? ''),
            'slug'             => $slug,
            'description'      => $_POST['description'] ?? '',
            'video_url'        => trim($_POST['video_url'] ?? '') ?: null,
            'status'           => $status,
            'published_at'     => $publishedAt,
            'sort_order'       => (int)($_POST['sort_order'] ?? 0),
            'meta_title'       => trim($_POST['meta_title']       ?? '') ?: null,
            'meta_description' => trim($_POST['meta_description'] ?? '') ?: null,
        ]);

        $repo->setMedia($id, $_POST['media_ids'] ?? []);

        $site = (new SiteRepository())->findFirst();
        $svc  = new PortfolioPublishService();
        if ($status === 'published' && $site) {
            $svc->publishEntry($repo->findById($id), $repo, $site);
        }
        if ($site) {
            $svc->regenerateListing($site, $this->currentUserId());
        }

        $this->flash('success', 'Práce byla uložena.');
        $this->redirect('/admin/portfolio/edit?id=' . $id);
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $id   = (int)($_POST['id'] ?? 0);
        $repo = new PortfolioRepository();
        $e    = $repo->findById($id);
        $repo->delete($id);

        $svc = new PortfolioPublishService();
        if ($e) {
            $svc->removePublishedFiles($e['slug']);
        }

        $site = (new SiteRepository())->findFirst();
        if ($site) {
            $svc->regenerateListing($site, $this->currentUserId());
        }

        $this->flash('success', 'Práce smazána.');
        $this->redirect('/admin/portfolio');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function currentUserId(): int
    {
        return (int)(\App\Core\Auth::user()['id'] ?? 0);
    }

    private function allMedia(): array
    {
        $site = (new SiteRepository())->findFirst();
        return $site ? (new MediaRepository())->allBySite($site['id']) : [];
    }
}
