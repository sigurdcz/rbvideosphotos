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

    /**
     * Videos posted by the form: the URL of each row paired with the order number
     * typed next to it. `video_urls[]` is what the form sends today; a single
     * `video_url` is still accepted so a cached old form (or an external post) keeps
     * working. Unsupported addresses are dropped, not rejected - the caller reports
     * them, because losing the whole save over one typo is worse than saving the rest.
     *
     * @return array{items: list<array{url:string,sort_order:int}>, rejected: list<string>}
     */
    private function postedVideos(): array
    {
        $rawUrls = $_POST['video_urls'] ?? null;
        $rawSort = (array)($_POST['video_sort'] ?? []);
        if ($rawUrls === null && isset($_POST['video_url'])) {
            $rawUrls = [$_POST['video_url']];
            $rawSort = [];
        }

        $raw = [];
        foreach (array_values((array)($rawUrls ?? [])) as $i => $url) {
            $url = is_scalar($url) ? trim((string)$url) : '';
            if ($url === '') {
                continue;
            }
            $sort = $rawSort[$i] ?? 0;
            $raw[] = ['url' => $url, 'sort_order' => is_scalar($sort) ? max(0, (int)$sort) : 0];
        }

        $items = PortfolioRepository::orderVideoItems(PortfolioRepository::normalizeVideoItems($raw));
        $rejected = array_values(array_diff(array_column($raw, 'url'), array_column($items, 'url')));

        return ['items' => $items, 'rejected' => $rejected];
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

    /** Warn about addresses the save silently dropped, and about a video entry left without one. */
    private function flashVideoNotices(array $posted, string $contentType): void
    {
        $notices = [];
        if ($posted['rejected'] !== []) {
            $notices[] = 'Tyto adresy nejsou YouTube ani Vimeo a nebyly ulozeny: ' . implode(', ', $posted['rejected']);
        }
        if ($contentType === 'video' && $posted['items'] === []) {
            $notices[] = 'Prace je oznacena jako videogalerie, ale nema zadne video.';
        }
        if ($notices !== []) {
            $this->flash('error', implode(' ', $notices));
        }
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->view('portfolio.form', [
            'entry'    => null,
            'initialContentType' => ($_GET['content_type'] ?? '') === 'video' ? 'video' : 'photo',
            'selectedMedia' => [],
            'videos' => [],
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
        $contentType = ($_POST['content_type'] ?? 'photo') === 'video' ? 'video' : 'photo';
        $postedVideos = $this->postedVideos();

        $repo = new PortfolioRepository();
        $id   = $repo->create([
            'site_id'          => $site['id'],
            'title'            => trim($_POST['title'] ?? ''),
            'slug'             => $slug,
            'description'      => $_POST['description'] ?? '',
            'video_url'        => $postedVideos['items'][0]['url'] ?? null,
            'content_type'     => $contentType,
            'status'           => $status,
            'published_at'     => $status === 'published' ? date('Y-m-d H:i:s') : null,
            'sort_order'       => PortfolioRepository::clampSortOrder($_POST['sort_order'] ?? 0),
            'meta_title'       => trim($_POST['meta_title']       ?? '') ?: null,
            'meta_description' => trim($_POST['meta_description'] ?? '') ?: null,
        ]);

        $repo->setMedia($id, $_POST['media_ids'] ?? [], $this->postedSortMap('media_sort'));
        // Videos must be stored before the entry is published - the static export and
        // the listing both read them back from the database.
        $repo->setVideos($id, $postedVideos['items']);
        $this->flashVideoNotices($postedVideos, $contentType);

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

        $selectedMedia = $repo->getMedia($id);

        $this->view('portfolio.form', [
            'entry'    => $entry,
            'selectedMedia' => $selectedMedia,
            'videos' => $repo->getVideos($id),
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
        $contentType = ($_POST['content_type'] ?? 'photo') === 'video' ? 'video' : 'photo';
        $postedVideos = $this->postedVideos();
        $publishedAt = $status === 'published'
            ? ($entry['published_at'] ?? date('Y-m-d H:i:s'))
            : null;

        $repo->update($id, [
            'title'            => trim($_POST['title'] ?? ''),
            'slug'             => $slug,
            'description'      => $_POST['description'] ?? '',
            'video_url'        => $postedVideos['items'][0]['url'] ?? null,
            'content_type'     => $contentType,
            'status'           => $status,
            'published_at'     => $publishedAt,
            'sort_order'       => PortfolioRepository::clampSortOrder($_POST['sort_order'] ?? 0),
            'meta_title'       => trim($_POST['meta_title']       ?? '') ?: null,
            'meta_description' => trim($_POST['meta_description'] ?? '') ?: null,
        ]);

        $repo->setMedia($id, $_POST['media_ids'] ?? [], $this->postedSortMap('media_sort'));
        $repo->setVideos($id, $postedVideos['items']);
        $this->flashVideoNotices($postedVideos, $contentType);

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

}
