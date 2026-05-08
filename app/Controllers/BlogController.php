<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\SlugTrait;
use App\Repositories\BlogRepository;
use App\Repositories\MediaRepository;
use App\Repositories\SiteRepository;

class BlogController extends Controller
{
    use SlugTrait;
    public function index(array $params): void
    {
        $this->requireAuth();
        $site  = (new SiteRepository())->findFirst();
        $posts = $site ? (new BlogRepository())->allBySite($site['id']) : [];

        $this->view('blog.index', [
            'posts'   => $posts,
            'site'    => $site,
            'success' => $this->getFlash('success'),
            'error'   => $this->getFlash('error'),
        ]);
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $media = $this->allMedia();
        $this->view('blog.form', ['post' => null, 'media' => $media]);
    }

    public function store(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $site = (new SiteRepository())->findFirst();
        if (!$site) {
            $this->flash('error', 'Web nenalezen.');
            $this->redirect('/admin/blog');
        }

        $slug   = $this->makeSlug($_POST['slug'] ?? '', $_POST['title'] ?? '');
        $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';

        $repo = new BlogRepository();
        $id   = $repo->create([
            'site_id'           => $site['id'],
            'title'             => trim($_POST['title']   ?? ''),
            'slug'              => $slug,
            'excerpt'           => trim($_POST['excerpt'] ?? ''),
            'content'           => $_POST['content']      ?? '',
            'featured_image_id' => $this->intOrNull($_POST['featured_image_id'] ?? ''),
            'status'            => $status,
            'published_at'      => $status === 'published' ? date('Y-m-d H:i:s') : null,
            'meta_title'        => trim($_POST['meta_title']       ?? '') ?: null,
            'meta_description'  => trim($_POST['meta_description'] ?? '') ?: null,
        ]);

        $this->flash('success', 'Článek byl uložen.');
        $this->redirect('/admin/blog/edit?id=' . $id);
    }

    public function edit(array $params): void
    {
        $this->requireAuth();
        $id   = (int)($_GET['id'] ?? 0);
        $post = (new BlogRepository())->findById($id);
        if (!$post) {
            $this->flash('error', 'Článek nenalezen.');
            $this->redirect('/admin/blog');
        }

        $this->view('blog.form', [
            'post'    => $post,
            'media'   => $this->allMedia(),
            'success' => $this->getFlash('success'),
            'error'   => $this->getFlash('error'),
        ]);
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $id   = (int)($_POST['id'] ?? 0);
        $repo = new BlogRepository();
        $post = $repo->findById($id);
        if (!$post) {
            $this->flash('error', 'Článek nenalezen.');
            $this->redirect('/admin/blog');
        }

        $slug   = $this->makeSlug($_POST['slug'] ?? '', $_POST['title'] ?? '');
        $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
        $publishedAt = $status === 'published'
            ? ($post['published_at'] ?? date('Y-m-d H:i:s'))
            : null;

        $repo->update($id, [
            'title'             => trim($_POST['title']   ?? ''),
            'slug'              => $slug,
            'excerpt'           => trim($_POST['excerpt'] ?? ''),
            'content'           => $_POST['content']      ?? '',
            'featured_image_id' => $this->intOrNull($_POST['featured_image_id'] ?? ''),
            'status'            => $status,
            'published_at'      => $publishedAt,
            'meta_title'        => trim($_POST['meta_title']       ?? '') ?: null,
            'meta_description'  => trim($_POST['meta_description'] ?? '') ?: null,
        ]);

        $this->flash('success', 'Článek byl uložen.');
        $this->redirect('/admin/blog/edit?id=' . $id);
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $id = (int)($_POST['id'] ?? 0);
        (new BlogRepository())->delete($id);
        $this->flash('success', 'Článek smazán.');
        $this->redirect('/admin/blog');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function allMedia(): array
    {
        $site = (new SiteRepository())->findFirst();
        return $site ? (new MediaRepository())->allBySite($site['id']) : [];
    }


    private function intOrNull(string $v): ?int
    {
        $v = trim($v);
        return $v !== '' ? (int)$v : null;
    }
}
