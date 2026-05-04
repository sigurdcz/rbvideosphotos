<?php
/*
 * Public front-end entry point.
 *
 * In production, serve static files from /storage/published directly via
 * the web server. This PHP file is only used for local development routing
 * or when you want to serve the published static files through PHP.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Auth;
use App\Core\Config;
use App\Core\Runtime;
use App\Repositories\BlockRepository;
use App\Repositories\PageRepository;
use App\Repositories\SiteRepository;
use App\Repositories\TemplateRepository;
use App\Services\PageRenderer;

date_default_timezone_set(Config::get('app', 'timezone', 'Europe/Prague'));
Runtime::boot('public');
Runtime::startRequest();
Auth::start();

/**
 * Render the page from the database in edit mode and echo it.
 * Returns true on success, false if any lookup fails (caller falls back to static file).
 */
function tryServeEditMode(string $uri): bool
{
    $slug = ltrim($uri, '/');

    try {
        $site = (new SiteRepository())->findFirst();
        if (!$site) return false;

        $page = null;
        foreach ((new PageRepository())->allBySite($site['id']) as $p) {
            if (($p['url_path'] ?? $p['slug']) === $slug) { $page = $p; break; }
        }
        if (!$page) return false;

        $template = (new TemplateRepository())->findById($page['template_id'] ?? 0);
        if (!$template) return false;

        $blocks   = (new BlockRepository())->allByPage($page['id']);
        $renderer = new PageRenderer();
        $html = $renderer->render($template['html_source'], $blocks, [], true);
        $html = $renderer->injectAssets($html, $template['css_source'] ?? '', $template['js_source'] ?? '');

        $csrfToken = Auth::csrfToken();
        $user      = Auth::user();
        $userEmail = htmlspecialchars($user['email'] ?? '', ENT_QUOTES);
        $pageTitle = htmlspecialchars($page['title'] ?? '', ENT_QUOTES);
        $pageId    = (int) $page['id'];

        $editorMeta = '<meta name="inline-page-id" content="' . $pageId . '">'
            . '<meta name="inline-csrf" content="' . htmlspecialchars($csrfToken, ENT_QUOTES) . '">'
            . '<link rel="stylesheet" href="/assets/inline-editor.css">';

        $adminBar = <<<HTML
<div id="cms-adminbar">
  <div class="cms-ab-left">
    <a class="cms-ab-logo" href="/admin">&#9998;&nbsp;CMS</a>
    <span class="cms-ab-sep"></span>
    <span class="cms-ab-page">{$pageTitle}</span>
  </div>
  <div class="cms-ab-right">
    <span class="cms-ab-email">{$userEmail}</span>
    <a class="cms-ab-btn" href="/admin">Administrace</a>
    <form method="POST" action="/admin/logout" style="margin:0;padding:0;display:inline">
      <input type="hidden" name="_csrf" value="{$csrfToken}">
      <button type="submit" class="cms-ab-btn cms-ab-logout">Odhlásit</button>
    </form>
  </div>
</div>
HTML;

        $html = str_replace('</head>', "\n" . $editorMeta . "\n</head>", $html);
        $html = str_replace('<body', $adminBar . "\n<body", $html);
        $html = str_replace('</body>', "\n<script src=\"/assets/inline-editor.js\"></script>\n</body>", $html);

        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        return true;

    } catch (\Throwable $e) {
        // DB or render error — fall back silently to static file
        return false;
    }
}

try {
    $publishedDir = dirname(__DIR__) . '/storage/published';
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uri = rtrim($uri, '/') ?: '/';

    // Serve static assets (CSS, JS, images) from /public/assets
    if (str_starts_with($uri, '/assets/')) {
        $file = __DIR__ . $uri;
        if (is_file($file)) {
            $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'css'         => 'text/css',
                'js'          => 'application/javascript',
                'png'         => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'webp'        => 'image/webp',
                'svg'         => 'image/svg+xml',
                default       => 'application/octet-stream',
            };
            header('Content-Type: ' . $mime);
            readfile($file);
            exit;
        }
    }

    // Serve uploaded media files
    if (str_starts_with($uri, '/storage/media/')) {
        $file = dirname(__DIR__) . $uri;
        if (is_file($file)) {
            $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png'         => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'webp'        => 'image/webp',
                'gif'         => 'image/gif',
                'svg'         => 'image/svg+xml',
                default       => 'application/octet-stream',
            };
            header('Content-Type: ' . $mime);
            readfile($file);
            exit;
        }
    }

    // Logged-in users: try live edit-mode render from DB first
    if (Auth::check() && tryServeEditMode($uri)) {
        exit;
    }

    // Resolve to published HTML file
    $path = ltrim($uri, '/');
    if ($path === '') {
        $candidate = $publishedDir . '/index.html';
    } else {
        $candidate = $publishedDir . '/' . $path . '/index.html';
        if (!file_exists($candidate)) {
            $candidate = $publishedDir . '/' . $path . '.html';
        }
    }

    if (file_exists($candidate)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($candidate);
        exit;
    }

    // Serve any root-level static file directly from storage/published
    $rootFile = $publishedDir . '/' . $path;
    if ($path !== '' && is_file($rootFile)) {
        $ext  = strtolower(pathinfo($rootFile, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'ico'         => 'image/x-icon',
            'png'         => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp'        => 'image/webp',
            'svg'         => 'image/svg+xml',
            'webmanifest' => 'application/manifest+json',
            'json'        => 'application/json',
            'xml'         => 'application/xml',
            'txt'         => 'text/plain',
            default       => 'application/octet-stream',
        };
        header('Content-Type: ' . $mime);
        readfile($rootFile);
        exit;
    }

    http_response_code(404);
    echo '<!DOCTYPE html><html><body><h1>404 Stránka nenalezena</h1></body></html>';
} catch (\Throwable $e) {
    Runtime::handleException($e);
}
