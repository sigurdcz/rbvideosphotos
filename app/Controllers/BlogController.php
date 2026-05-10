<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\SlugTrait;
use App\Repositories\BlockRepository;
use App\Repositories\BlogRepository;
use App\Repositories\MediaRepository;
use App\Repositories\PageRepository;
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
        $this->view('blog.form', ['post' => null, 'media' => $this->allMedia(), 'gallery' => []]);
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

        $repo->setGallery($id, $_POST['gallery_ids'] ?? []);
        $this->syncHomepageBlogBlocks($repo, $site['id']);

        $this->flash('success', 'Článek byl uložen.');
        $this->redirect('/admin/blog/edit?id=' . $id);
    }

    public function edit(array $params): void
    {
        $this->requireAuth();
        $id   = (int)($_GET['id'] ?? 0);
        $repo = new BlogRepository();
        $post = $repo->findById($id);
        if (!$post) {
            $this->flash('error', 'Článek nenalezen.');
            $this->redirect('/admin/blog');
        }

        $this->view('blog.form', [
            'post'    => $post,
            'media'   => $this->allMedia(),
            'gallery' => array_column($repo->getGallery($id), 'id'),
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

        $repo->setGallery($id, $_POST['gallery_ids'] ?? []);

        $site = (new SiteRepository())->findFirst();
        if ($site) {
            $this->syncHomepageBlogBlocks($repo, $site['id']);
        }

        $this->flash('success', 'Článek byl uložen.');
        $this->redirect('/admin/blog/edit?id=' . $id);
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $id   = (int)($_POST['id'] ?? 0);
        $repo = new BlogRepository();
        $repo->delete($id);

        $site = (new SiteRepository())->findFirst();
        if ($site) {
            $this->syncHomepageBlogBlocks($repo, $site['id']);
        }

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

    private function syncHomepageBlogBlocks(BlogRepository $repo, int $siteId): void
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
