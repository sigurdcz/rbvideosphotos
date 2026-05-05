<?php

namespace App\Controllers;

use App\Repositories\BlogRepository;
use App\Repositories\MediaRepository;
use App\Repositories\PortfolioRepository;
use App\Repositories\SiteRepository;

class ApiController
{
    private string $apiKeyFile;

    public function __construct()
    {
        $this->apiKeyFile = dirname(__DIR__, 2) . '/storage/api_key.txt';
    }

    public function dispatch(string $uri): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: X-Api-Key, Authorization, Content-Type');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        if (!$this->checkAuth()) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized. Send X-Api-Key or Authorization: Bearer {key} header.']);
            exit;
        }

        $path = rtrim(substr($uri, strlen('/api/v1')), '/');

        if ($path === '/articles') {
            $this->listArticles();
        } elseif (preg_match('#^/articles/([^/]+)$#', $path, $m)) {
            $this->getArticle($m[1]);
        } elseif ($path === '/portfolio') {
            $this->listPortfolio();
        } elseif (preg_match('#^/portfolio/([^/]+)$#', $path, $m)) {
            $this->getPortfolioEntry($m[1]);
        } elseif ($path === '/key') {
            // Show the current key (admin convenience)
            echo json_encode(['api_key' => $this->getApiKey()]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found.', 'available' => [
                'GET /api/v1/articles',
                'GET /api/v1/articles/{slug}',
                'GET /api/v1/portfolio',
                'GET /api/v1/portfolio/{slug}',
            ]]);
        }
        exit;
    }

    // ── Articles ──────────────────────────────────────────────────────────────

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

    private function formatPost(array $p, array $site): array
    {
        $domain = rtrim($site['domain'] ?? '', '/');
        $imgUrl = null;
        if ($p['featured_image_id']) {
            $m = (new MediaRepository())->findById((int)$p['featured_image_id']);
            if ($m) $imgUrl = $domain . '/' . ltrim($m['storage_path'], '/');
        }

        return [
            'id'                => (int)$p['id'],
            'title'             => $p['title'],
            'slug'              => $p['slug'],
            'excerpt'           => $p['excerpt'] ?? '',
            'content'           => $p['content'] ?? '',
            'featured_image_url'=> $imgUrl,
            'meta_title'        => $p['meta_title'],
            'meta_description'  => $p['meta_description'],
            'status'            => $p['status'],
            'published_at'      => $p['published_at'],
            'created_at'        => $p['created_at'],
            'updated_at'        => $p['updated_at'],
        ];
    }

    // ── Portfolio ─────────────────────────────────────────────────────────────

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

    private function formatEntry(array $e, PortfolioRepository $repo, array $site): array
    {
        $domain = rtrim($site['domain'] ?? '', '/');
        $media  = $repo->getMedia($e['id']);

        $photos = array_map(fn($m) => [
            'url'    => $domain . '/' . ltrim($m['storage_path'], '/'),
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

        // Check X-Api-Key header
        $headerKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
        if ($headerKey && hash_equals($key, $headerKey)) return true;

        // Check Authorization: Bearer {key}
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($auth, 'Bearer ')) {
            $bearer = substr($auth, 7);
            if (hash_equals($key, $bearer)) return true;
        }

        // Check ?api_key= query param (convenience, not for production)
        $qkey = $_GET['api_key'] ?? '';
        if ($qkey && hash_equals($key, $qkey)) return true;

        return false;
    }

    private function getApiKey(): string
    {
        if (file_exists($this->apiKeyFile)) {
            return trim(file_get_contents($this->apiKeyFile));
        }
        $key = bin2hex(random_bytes(32));
        file_put_contents($this->apiKeyFile, $key);
        return $key;
    }
}
