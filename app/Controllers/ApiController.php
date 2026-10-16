<?php

namespace App\Controllers;

use App\Core\Config;
use App\Core\Env;
use App\Core\SlugTrait;
use App\Repositories\BlogRepository;
use App\Repositories\MediaRepository;
use App\Repositories\PortfolioRepository;
use App\Repositories\SiteRepository;
use App\Services\BlogSyncService;
use App\Services\MediaIngestionService;
use App\Services\PortfolioPublishService;

class ApiController
{
    use SlugTrait;

    public function dispatch(string $uri): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Access-Control-Allow-Headers: X-Api-Key, Authorization, Content-Type');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        $this->applyCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        if (!$this->checkAuth()) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized. Send X-Api-Key or Authorization: Bearer {key} header.']);
            exit;
        }

        $method = $_SERVER['REQUEST_METHOD'];
        $path   = rtrim(substr($uri, strlen('/api/v1')), '/');

        // ── Articles ──────────────────────────────────────────────────────────
        if ($path === '/articles') {
            if ($method === 'GET')       $this->listArticles();
            elseif ($method === 'POST')  $this->createArticle();
            else                         $this->methodNotAllowed();
        } elseif (preg_match('#^/articles/([^/]+)$#', $path, $m)) {
            if ($method === 'GET')        $this->getArticle($m[1]);
            elseif ($method === 'PUT')    $this->updateArticle($m[1]);
            elseif ($method === 'DELETE') $this->deleteArticle($m[1]);
            else                          $this->methodNotAllowed();

        // ── Portfolio ─────────────────────────────────────────────────────────
        } elseif ($path === '/portfolio') {
            if ($method === 'GET')       $this->listPortfolio();
            elseif ($method === 'POST')  $this->createPortfolioEntry();
            else                         $this->methodNotAllowed();
        } elseif (preg_match('#^/portfolio/([^/]+)$#', $path, $m)) {
            if ($method === 'GET')        $this->getPortfolioEntry($m[1]);
            elseif ($method === 'PUT')    $this->updatePortfolioEntry($m[1]);
            elseif ($method === 'DELETE') $this->deletePortfolioEntry($m[1]);
            else                          $this->methodNotAllowed();

        // ── Media ─────────────────────────────────────────────────────────────
        } elseif ($path === '/media') {
            if ($method === 'POST') $this->uploadMedia();
            else                    $this->methodNotAllowed();
        } elseif (preg_match('#^/media/(\d+)$#', $path, $m)) {
            if ($method === 'DELETE') $this->deleteMedia((int)$m[1]);
            else                      $this->methodNotAllowed();

        // ── Key (convenience) ─────────────────────────────────────────────────
        } elseif ($path === '/key') {
            echo json_encode(['api_key' => $this->getApiKey()]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found.', 'available' => [
                'GET    /api/v1/articles',
                'POST   /api/v1/articles',
                'GET    /api/v1/articles/{slug}',
                'PUT    /api/v1/articles/{slug}',
                'DELETE /api/v1/articles/{slug}',
                'GET    /api/v1/portfolio',
                'POST   /api/v1/portfolio',
                'GET    /api/v1/portfolio/{slug}',
                'PUT    /api/v1/portfolio/{slug}',
                'DELETE /api/v1/portfolio/{slug}',
                'POST   /api/v1/media',
                'DELETE /api/v1/media/{id}',
            ]]);
        }
        exit;
    }

    // ── Articles - read ───────────────────────────────────────────────────────

    private function listArticles(): void
    {
        $site  = (new SiteRepository())->findFirst();
        $posts = $site ? (new BlogRepository())->allPublished($site['id']) : [];
        $site  = $site ?? [];

        $data = array_map(fn($p) => $this->formatPost($p, $site), $posts);
        echo json_encode(['posts' => $data, 'total' => count($data)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function getArticle(string $slug): void
    {
        $post = (new BlogRepository())->findBySlug($slug);
        if (!$post) {
            http_response_code(404);
            echo json_encode(['error' => 'Article not found.']);
            return;
        }
        $site = (new SiteRepository())->findFirst() ?? [];
        echo json_encode($this->formatPost($post, $site), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    // ── Articles - write ──────────────────────────────────────────────────────

    private function createArticle(): void
    {
        $data = $this->readJsonBody();
        if ($data === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON body.']);
            return;
        }

        $site = (new SiteRepository())->findFirst();
        if (!$site) {
            http_response_code(500);
            echo json_encode(['error' => 'Site not configured.']);
            return;
        }

        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Field "title" is required.']);
            return;
        }

        $slug   = $this->makeSlug((string)($data['slug'] ?? ''), $title);
        $status = ($data['status'] ?? 'draft') === 'published' ? 'published' : 'draft';

        $repo = new BlogRepository();
        try {
            $id = $repo->create([
                'site_id'           => $site['id'],
                'title'             => $title,
                'slug'              => $slug,
                'excerpt'           => trim((string)($data['excerpt'] ?? '')),
                'content'           => (string)($data['content'] ?? ''),
                'featured_image_id' => $this->intOrNull($data['featured_image_id'] ?? null),
                'status'            => $status,
                'published_at'      => $status === 'published' ? date('Y-m-d H:i:s') : null,
                'sort_order'        => PortfolioRepository::clampSortOrder($data['sort_order'] ?? 0),
                'meta_title'        => trim((string)($data['meta_title'] ?? '')) ?: null,
                'meta_description'  => trim((string)($data['meta_description'] ?? '')) ?: null,
            ]);
        } catch (\PDOException $e) {
            if ($this->isDuplicateEntry($e)) {
                http_response_code(409);
                echo json_encode(['error' => 'Slug already exists.']);
                return;
            }
            throw $e;
        }

        if (isset($data['gallery_ids']) && is_array($data['gallery_ids'])) {
            $repo->setGallery($id, $data['gallery_ids'], self::postedMediaSort($data, 'gallery_sort'));
        }

        (new BlogSyncService())->syncHomepageBlocks($repo, $site['id']);

        http_response_code(201);
        echo json_encode($this->formatPost($repo->findById($id), $site), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function updateArticle(string $slug): void
    {
        $data = $this->readJsonBody();
        if ($data === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON body.']);
            return;
        }

        $repo = new BlogRepository();
        $post = $repo->findBySlugAdmin($slug);
        if (!$post) {
            http_response_code(404);
            echo json_encode(['error' => 'Article not found.']);
            return;
        }

        $title  = isset($data['title']) ? trim((string)$data['title']) : (string)$post['title'];
        $newSlug = isset($data['slug'])
            ? $this->makeSlug((string)$data['slug'], $title)
            : (string)$post['slug'];
        $status = isset($data['status'])
            ? (($data['status'] === 'published') ? 'published' : 'draft')
            : (string)$post['status'];
        $publishedAt = $status === 'published'
            ? ($post['published_at'] ?? date('Y-m-d H:i:s'))
            : null;

        try {
            $repo->update($post['id'], [
                'title'             => $title,
                'slug'              => $newSlug,
                'excerpt'           => isset($data['excerpt'])           ? trim((string)$data['excerpt'])           : (string)($post['excerpt'] ?? ''),
                'content'           => isset($data['content'])           ? (string)$data['content']                : (string)($post['content'] ?? ''),
                'featured_image_id' => isset($data['featured_image_id']) ? $this->intOrNull($data['featured_image_id']) : ($post['featured_image_id'] ?? null),
                'status'            => $status,
                'published_at'      => $publishedAt,
                'sort_order'        => isset($data['sort_order']) ? PortfolioRepository::clampSortOrder($data['sort_order']) : (int)($post['sort_order'] ?? 0),
                'meta_title'        => isset($data['meta_title'])        ? (trim((string)$data['meta_title']) ?: null)        : $post['meta_title'],
                'meta_description'  => isset($data['meta_description'])  ? (trim((string)$data['meta_description']) ?: null)  : $post['meta_description'],
            ]);
        } catch (\PDOException $e) {
            if ($this->isDuplicateEntry($e)) {
                http_response_code(409);
                echo json_encode(['error' => 'Slug already exists.']);
                return;
            }
            throw $e;
        }

        if (isset($data['gallery_ids']) && is_array($data['gallery_ids'])) {
            $repo->setGallery((int)$post['id'], $data['gallery_ids'], self::postedMediaSort($data, 'gallery_sort'));
        }

        $site = (new SiteRepository())->findFirst() ?? [];
        if ($site) {
            (new BlogSyncService())->syncHomepageBlocks($repo, $site['id']);
        }

        echo json_encode($this->formatPost($repo->findById($post['id']), $site), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function deleteArticle(string $slug): void
    {
        $repo = new BlogRepository();
        $post = $repo->findBySlugAdmin($slug);
        if (!$post) {
            http_response_code(404);
            echo json_encode(['error' => 'Article not found.']);
            return;
        }

        $repo->delete((int)$post['id']);

        $site = (new SiteRepository())->findFirst();
        if ($site) {
            (new BlogSyncService())->syncHomepageBlocks($repo, $site['id']);
        }

        echo json_encode(['success' => true, 'deleted_slug' => $slug]);
    }

    // ── Portfolio - read ──────────────────────────────────────────────────────

    private function listPortfolio(): void
    {
        $site    = (new SiteRepository())->findFirst();
        $repo    = new PortfolioRepository();
        $entries = $site ? $repo->allPublished($site['id']) : [];
        $site    = $site ?? [];

        $data = array_map(fn($e) => $this->formatEntry($e, $repo, $site), $entries);
        echo json_encode(['entries' => $data, 'total' => count($data)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function getPortfolioEntry(string $slug): void
    {
        $repo  = new PortfolioRepository();
        $entry = $repo->findBySlug($slug);
        if (!$entry) {
            http_response_code(404);
            echo json_encode(['error' => 'Portfolio entry not found.']);
            return;
        }
        $site = (new SiteRepository())->findFirst() ?? [];
        echo json_encode($this->formatEntry($entry, $repo, $site), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    // ── Portfolio - write ─────────────────────────────────────────────────────

    private function createPortfolioEntry(): void
    {
        $data = $this->readJsonBody();
        if ($data === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON body.']);
            return;
        }

        $site = (new SiteRepository())->findFirst();
        if (!$site) {
            http_response_code(500);
            echo json_encode(['error' => 'Site not configured.']);
            return;
        }

        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Field "title" is required.']);
            return;
        }

        $slug   = $this->makeSlug((string)($data['slug'] ?? ''), $title);
        $status = ($data['status'] ?? 'draft') === 'published' ? 'published' : 'draft';

        $repo = new PortfolioRepository();
        try {
            $id = $repo->create([
                'site_id'          => $site['id'],
                'title'            => $title,
                'slug'             => $slug,
                'description'      => (string)($data['description'] ?? ''),
                'video_url'        => trim((string)($data['video_url'] ?? '')) ?: null,
                'status'           => $status,
                'published_at'     => $status === 'published' ? date('Y-m-d H:i:s') : null,
                'sort_order'       => PortfolioRepository::clampSortOrder($data['sort_order'] ?? 0),
                'meta_title'       => trim((string)($data['meta_title'] ?? '')) ?: null,
                'meta_description' => trim((string)($data['meta_description'] ?? '')) ?: null,
            ]);
        } catch (\PDOException $e) {
            if ($this->isDuplicateEntry($e)) {
                http_response_code(409);
                echo json_encode(['error' => 'Slug already exists.']);
                return;
            }
            throw $e;
        }

        if (isset($data['media_ids']) && is_array($data['media_ids'])) {
            $repo->setMedia($id, $data['media_ids'], self::postedMediaSort($data, 'media_sort'));
        }
        // `video_urls` is the full ordered list; a lone `video_url` still means one video.
        // Sending neither leaves the entry's videos untouched.
        if (isset($data['video_urls']) && is_array($data['video_urls'])) {
            $repo->setVideos($id, $data['video_urls']);
        } elseif (trim((string)($data['video_url'] ?? '')) !== '') {
            $repo->setVideos($id, [$data['video_url']]);
        }

        $svc = new PortfolioPublishService();
        if ($status === 'published') {
            $svc->publishEntry($repo->findById($id), $repo, $site);
        }
        $svc->regenerateListing($site);

        http_response_code(201);
        echo json_encode($this->formatEntry($repo->findById($id), $repo, $site), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function updatePortfolioEntry(string $slug): void
    {
        $data = $this->readJsonBody();
        if ($data === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON body.']);
            return;
        }

        $repo  = new PortfolioRepository();
        $entry = $repo->findBySlugAdmin($slug);
        if (!$entry) {
            http_response_code(404);
            echo json_encode(['error' => 'Portfolio entry not found.']);
            return;
        }

        $title   = isset($data['title']) ? trim((string)$data['title']) : (string)$entry['title'];
        $newSlug = isset($data['slug'])
            ? $this->makeSlug((string)$data['slug'], $title)
            : (string)$entry['slug'];
        $status = isset($data['status'])
            ? (($data['status'] === 'published') ? 'published' : 'draft')
            : (string)$entry['status'];
        $publishedAt = $status === 'published'
            ? ($entry['published_at'] ?? date('Y-m-d H:i:s'))
            : null;

        try {
            $repo->update($entry['id'], [
                'title'            => $title,
                'slug'             => $newSlug,
                'description'      => isset($data['description']) ? (string)$data['description'] : (string)($entry['description'] ?? ''),
                'video_url'        => isset($data['video_url'])    ? (trim((string)$data['video_url']) ?: null) : $entry['video_url'],
                'status'           => $status,
                'published_at'     => $publishedAt,
                'sort_order'       => isset($data['sort_order']) ? PortfolioRepository::clampSortOrder($data['sort_order']) : (int)$entry['sort_order'],
                'meta_title'       => isset($data['meta_title'])       ? (trim((string)$data['meta_title']) ?: null)       : $entry['meta_title'],
                'meta_description' => isset($data['meta_description']) ? (trim((string)$data['meta_description']) ?: null) : $entry['meta_description'],
            ]);
        } catch (\PDOException $e) {
            if ($this->isDuplicateEntry($e)) {
                http_response_code(409);
                echo json_encode(['error' => 'Slug already exists.']);
                return;
            }
            throw $e;
        }

        if (isset($data['media_ids']) && is_array($data['media_ids'])) {
            $repo->setMedia((int)$entry['id'], $data['media_ids'], self::postedMediaSort($data, 'media_sort'));
        }
        if (isset($data['video_urls']) && is_array($data['video_urls'])) {
            $repo->setVideos((int)$entry['id'], $data['video_urls']);
        } elseif (isset($data['video_url'])) {
            $repo->setVideos((int)$entry['id'], [$data['video_url']]);
        }

        $site = (new SiteRepository())->findFirst();
        $svc  = new PortfolioPublishService();
        if ($status === 'published' && $site) {
            $svc->publishEntry($repo->findById($entry['id']), $repo, $site);
        }
        if ($site) {
            $svc->regenerateListing($site);
        }

        $site = $site ?? [];
        echo json_encode($this->formatEntry($repo->findById($entry['id']), $repo, $site), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function deletePortfolioEntry(string $slug): void
    {
        $repo  = new PortfolioRepository();
        $entry = $repo->findBySlugAdmin($slug);
        if (!$entry) {
            http_response_code(404);
            echo json_encode(['error' => 'Portfolio entry not found.']);
            return;
        }

        $repo->delete((int)$entry['id']);

        $svc = new PortfolioPublishService();
        $svc->removePublishedFiles($entry['slug']);

        $site = (new SiteRepository())->findFirst();
        if ($site) {
            $svc->regenerateListing($site);
        }

        echo json_encode(['success' => true, 'deleted_slug' => $slug]);
    }

    // ── Media ─────────────────────────────────────────────────────────────────

    private function uploadMedia(): void
    {
        $site = (new SiteRepository())->findFirst();
        if (!$site) {
            http_response_code(500);
            echo json_encode(['error' => 'Site not configured.']);
            return;
        }

        $altText = trim($_POST['alt_text'] ?? '');
        if ($altText === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Field "alt_text" is required.']);
            return;
        }
        $title = trim($_POST['title'] ?? '') ?: $altText;

        $files = $this->normalizeFiles($_FILES['files'] ?? $_FILES['file'] ?? null);
        if (empty($files)) {
            $phpLimit = ini_get('post_max_size');
            http_response_code(400);
            echo json_encode(['error' => 'No files received. Possible post_max_size limit (' . $phpLimit . ').']);
            return;
        }

        $results = [];
        $ingest  = new MediaIngestionService();

        foreach ($files as $file) {
            if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
                $results[] = ['error' => 'File too large (PHP limit): ' . $file['name']];
                continue;
            }
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $results[] = ['error' => 'Upload failed (code ' . $file['error'] . '): ' . $file['name']];
                continue;
            }

            try {
                $result    = $ingest->ingestUploadedFile($site['id'], $file['tmp_name'], $file['name'], $altText, $title);
                $media     = (new MediaRepository())->findById($result['media_id']);
                $results[] = [
                    'success'       => true,
                    'media_id'      => $result['media_id'],
                    'storage_path'  => $result['storage_path'],
                    'variant_count' => $result['variant_count'],
                    'name'          => $file['name'],
                    'url'           => $this->publicMediaUrl((string)$result['storage_path']),
                    'width'         => $media['width'] ?? null,
                    'height'        => $media['height'] ?? null,
                    'mime_type'     => $media['mime_type'] ?? null,
                ];
            } catch (\RuntimeException $e) {
                $results[] = ['error' => $e->getMessage(), 'name' => $file['name']];
            }
        }

        echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function deleteMedia(int $id): void
    {
        $repo = new MediaRepository();
        $m    = $repo->findById($id);
        if (!$m) {
            http_response_code(404);
            echo json_encode(['error' => 'Media not found.']);
            return;
        }

        $base = dirname(__DIR__, 2) . '/';
        @unlink($base . $m['storage_path']);
        foreach ($repo->variantsByMediaId($id) as $v) {
            @unlink($base . $v['path']);
        }
        $repo->delete($id);

        echo json_encode(['success' => true, 'deleted_id' => $id]);
    }

    // ── Formatters ────────────────────────────────────────────────────────────

    private function formatPost(array $p, array $site): array
    {
        $domain = rtrim($site['domain'] ?? '', '/');
        $imgUrl = null;
        if ($p['featured_image_id']) {
            $m = (new MediaRepository())->findById((int)$p['featured_image_id']);
            if ($m) $imgUrl = $domain . $this->publicMediaUrl((string)$m['storage_path']);
        }

        return [
            'id'                 => (int)$p['id'],
            'title'              => $p['title'],
            'slug'               => $p['slug'],
            'excerpt'            => $p['excerpt'] ?? '',
            'content'            => $p['content'] ?? '',
            'featured_image_url' => $imgUrl,
            'meta_title'         => $p['meta_title'],
            'meta_description'   => $p['meta_description'],
            'status'             => $p['status'],
            'sort_order'         => (int)($p['sort_order'] ?? 0),
            'published_at'       => $p['published_at'],
            'created_at'         => $p['created_at'],
            'updated_at'         => $p['updated_at'],
        ];
    }

    /**
     * Optional per-photo order numbers, sent as {"<media id>": n}. Absent or
     * unusable input means null, which leaves the repository ordering the gallery by
     * the position of each id - the behaviour every older client relies on.
     *
     * @param array<string,mixed> $data
     * @return array<int,int>|null
     */
    private static function postedMediaSort(array $data, string $key): ?array
    {
        if (!isset($data[$key]) || !is_array($data[$key])) {
            return null;
        }

        $map = [];
        foreach ($data[$key] as $mediaId => $sort) {
            // Only a plain digit string is a media id; "5abc" would otherwise cast to
            // 5 and silently renumber a different photo.
            if (!is_scalar($sort) || !ctype_digit((string)$mediaId) || (int)$mediaId <= 0) {
                continue;
            }
            $map[(int)$mediaId] = PortfolioRepository::clampSortOrder($sort);
        }

        return $map;
    }

    private function formatEntry(array $e, PortfolioRepository $repo, array $site): array
    {
        $domain = rtrim($site['domain'] ?? '', '/');
        $media  = $repo->getMedia($e['id']);

        $photos = array_map(fn($m) => [
            'url'    => $domain . $this->publicMediaUrl((string)$m['storage_path']),
            'alt'    => $m['alt_text'] ?? '',
            'width'  => $m['width'],
            'height' => $m['height'],
        ], $media);

        return [
            'id'               => (int)$e['id'],
            'title'            => $e['title'],
            'slug'             => $e['slug'],
            'description'      => $e['description'] ?? '',
            'video_url'        => $e['video_url'],
            'video_embed_url'  => $e['video_url'] ? PortfolioRepository::embedUrl($e['video_url']) : null,
            'video_thumbnail'  => $e['video_url'] ? PortfolioRepository::youtubeThumbnail($e['video_url']) : null,
            // video_url/video_embed_url/video_thumbnail stay the FIRST video; `videos`
            // is the whole ordered list.
            'videos'           => $repo->getVideos((int)$e['id']),
            'photos'           => $photos,
            'meta_title'       => $e['meta_title'],
            'meta_description' => $e['meta_description'],
            'status'           => $e['status'],
            'sort_order'       => (int)$e['sort_order'],
            'published_at'     => $e['published_at'],
            'created_at'       => $e['created_at'],
            'updated_at'       => $e['updated_at'],
        ];
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    private function checkAuth(): bool
    {
        $key = $this->getApiKey();

        $headerKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
        if ($headerKey && hash_equals($key, $headerKey)) return true;

        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($auth, 'Bearer ')) {
            $bearer = substr($auth, 7);
            if (hash_equals($key, $bearer)) return true;
        }

        return false;
    }

    private function getApiKey(): string
    {
        return (string) Env::get('API_KEY', '');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function readJsonBody(): ?array
    {
        $raw = (string) file_get_contents('php://input');
        if ($raw === '') return [];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private function intOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '' || $v === false) return null;
        $i = (int)$v;
        return $i > 0 ? $i : null;
    }

    private function isDuplicateEntry(\PDOException $e): bool
    {
        return str_contains($e->getMessage(), '1062') || str_contains($e->getMessage(), 'Duplicate entry');
    }

    private function methodNotAllowed(): void
    {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed.']);
    }

    private function normalizeFiles(?array $raw): array
    {
        if (!$raw) return [];
        if (is_string($raw['name'])) return [$raw];
        $out = [];
        foreach ($raw['name'] as $i => $_) {
            $out[] = [
                'name'     => $raw['name'][$i],
                'tmp_name' => $raw['tmp_name'][$i],
                'error'    => $raw['error'][$i],
                'size'     => $raw['size'][$i],
            ];
        }
        return $out;
    }

    private function applyCorsHeaders(): void
    {
        $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        if ($origin === '') {
            return;
        }

        $allowedOrigins = [];
        $appOrigin = $this->originFromUrl((string) Config::get('app', 'url', ''));
        if ($appOrigin !== null) {
            $allowedOrigins[] = $appOrigin;
        }

        if (in_array($origin, $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }
    }

    private function originFromUrl(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin;
    }

    private function publicMediaUrl(string $storagePath): string
    {
        $normalized = str_replace('\\', '/', trim($storagePath));
        if ($normalized === '') return '';
        if (str_starts_with($normalized, '/media/')) return $normalized;
        if (str_starts_with($normalized, '/storage/media/')) return '/media/' . ltrim(substr($normalized, strlen('/storage/media/')), '/');
        if (str_starts_with($normalized, 'storage/media/')) return '/media/' . ltrim(substr($normalized, strlen('storage/media/')), '/');
        return '/' . ltrim($normalized, '/');
    }
}
