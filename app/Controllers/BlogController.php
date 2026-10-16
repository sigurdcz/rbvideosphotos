<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\SlugTrait;
use App\Repositories\BlogRepository;
use App\Repositories\PortfolioRepository;
use App\Repositories\MediaRepository;
use App\Repositories\SiteRepository;
use App\Services\BlogSyncService;

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
        $this->view('blog.form', ['post' => null, 'featuredMedia' => null, 'galleryMedia' => []]);
    }

    /**
     * The per-photo order numbers posted as `<key>[<media id>]`. Keyed by id rather
     * than by position, so a reordered selection can never shift the numbers onto the
     * wrong photos; anything missing or unparsable counts as 0.
     *
     * @return array<int,int>
     */
    private function postedSortMap(string $key): array
    {
        $map = [];
        foreach ((array)($_POST[$key] ?? []) as $mediaId => $sort) {
            // Only a plain digit string is a media id; "5abc" would otherwise cast to
            // 5 and silently renumber a different photo.
            if (!is_scalar($sort) || !ctype_digit((string)$mediaId) || (int)$mediaId <= 0) {
                continue;
            }
            $map[(int)$mediaId] = PortfolioRepository::clampSortOrder($sort);
        }

        return $map;
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
            'sort_order'        => PortfolioRepository::clampSortOrder($_POST['sort_order'] ?? 0),
            'meta_title'        => trim($_POST['meta_title']       ?? '') ?: null,
            'meta_description'  => trim($_POST['meta_description'] ?? '') ?: null,
        ]);

        $repo->setGallery($id, $_POST['gallery_ids'] ?? [], $this->postedSortMap('gallery_sort'));
        (new BlogSyncService())->syncHomepageBlocks($repo, $site['id']);

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

        $featuredMedia = !empty($post['featured_image_id']) ? (new MediaRepository())->findByIdWithThumb((int)$post['featured_image_id']) : null;
        $this->view('blog.form', [
            'post'    => $post,
            'featuredMedia' => $featuredMedia,
            'galleryMedia' => $repo->getGallery($id),
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
            'sort_order'        => PortfolioRepository::clampSortOrder($_POST['sort_order'] ?? 0),
            'meta_title'        => trim($_POST['meta_title']       ?? '') ?: null,
            'meta_description'  => trim($_POST['meta_description'] ?? '') ?: null,
        ]);

        $repo->setGallery($id, $_POST['gallery_ids'] ?? [], $this->postedSortMap('gallery_sort'));

        $site = (new SiteRepository())->findFirst();
        if ($site) {
            (new BlogSyncService())->syncHomepageBlocks($repo, $site['id']);
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
            (new BlogSyncService())->syncHomepageBlocks($repo, $site['id']);
        }

        $this->flash('success', 'Článek smazán.');
        $this->redirect('/admin/blog');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function intOrNull(string $v): ?int
    {
        $v = trim($v);
        return $v !== '' ? (int)$v : null;
    }
}
