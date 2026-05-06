<?php
/*
 * Public front-end entry point.
 *
 * This project now serves page content directly from MySQL/MariaDB.
 * Static files under storage/published are only used for explicit assets
 * such as sitemap.xml, robots.txt, favicon files or generated exports.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Auth;
use App\Core\Config;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Runtime;
use App\Core\Version;
use App\Repositories\BlockRepository;
use App\Repositories\MediaRepository;
use App\Repositories\PageRepository;
use App\Repositories\SeoRepository;
use App\Repositories\SiteRepository;
use App\Repositories\TemplateRepository;
use App\Services\PageRenderer;
use App\Services\SeoRenderer;

Env::load(dirname(__DIR__) . '/.env');

date_default_timezone_set(Config::get('app', 'timezone', 'Europe/Prague'));
Runtime::boot('public');
Runtime::startRequest();
Auth::start();

function tryServeDatabasePage(string $uri, bool $authenticated = false, bool $previewMode = false): bool
{
    $slug = ltrim($uri, '/');

    try {
        $pages = new PageRepository();
        $page = $authenticated
            ? $pages->findByUrlPath($slug)
            : $pages->findPublishedByUrlPath($slug);
        if (!$page) {
            return false;
        }

        $site = (new SiteRepository())->findById((int)$page['site_id']);
        if (!$site) {
            return false;
        }

        $template = (new TemplateRepository())->findById((int)($page['template_id'] ?? 0));
        if (!$template) {
            return false;
        }

        $blocks = (new BlockRepository())->allByPage((int)$page['id']);
        $seo = (new SeoRepository())->findByPage((int)$page['id']) ?? [];
        $mediaRepo = new MediaRepository();
        $renderer = new PageRenderer();
        $seoRenderer = new SeoRenderer();
        $mediaMap = [];

        foreach ($blocks as $block) {
            if (($block['block_type'] ?? '') === 'image' && !empty($block['content'])) {
                $mediaMap[$block['block_key']] = [
                    'url' => $block['content'],
                    'alt' => $block['label'] ?? '',
                ];
            }
        }

        $pagePath = trim((string)($page['url_path'] ?? ''), '/');
        $pageUrl = rtrim((string)$site['domain'], '/') . ($pagePath === '' ? '/' : '/' . $pagePath);

        $ogImageUrl = null;
        if (!empty($seo['og_image_id'])) {
            $media = $mediaRepo->findById((int)$seo['og_image_id']);
            if ($media) {
                $ogImageUrl = rtrim((string)$site['domain'], '/') . '/' . ltrim((string)$media['storage_path'], '/');
            }
        }

        $twImageUrl = null;
        if (!empty($seo['twitter_image_id'])) {
            $media = $mediaRepo->findById((int)$seo['twitter_image_id']);
            if ($media) {
                $twImageUrl = rtrim((string)$site['domain'], '/') . '/' . ltrim((string)$media['storage_path'], '/');
            }
        }

        $html = $renderer->render(
            $template['html_source'],
            $blocks,
            $mediaMap,
            $authenticated && !$previewMode
        );
        $html = $renderer->injectAssets($html, $template['css_source'] ?? '', $template['js_source'] ?? '');
        $html = $renderer->injectSeo(
            $html,
            $seoRenderer->renderHead($seo, $site, $pageUrl, $ogImageUrl, $twImageUrl),
            $seoRenderer->renderBodyScripts($site)
        );

        if ($authenticated) {
            $csrfToken  = Auth::csrfToken();
            $user       = Auth::user();
            $userEmail  = htmlspecialchars($user['email'] ?? '', ENT_QUOTES);
            $pageTitle  = htmlspecialchars($page['title'] ?? '', ENT_QUOTES);
            $pageId     = (int) $page['id'];
            $appVersion = Version::APP;
            $previewHref = htmlspecialchars($uri . '?cms_preview=1', ENT_QUOTES);
            $editHref    = htmlspecialchars($uri, ENT_QUOTES);

            if ($previewMode) {
                $editorMeta = '<meta name="app-version" content="' . $appVersion . '">'
                    . '<link rel="stylesheet" href="/assets/inline-editor.css">';

                $adminBar = <<<HTML
<div id="cms-adminbar">
  <div class="cms-ab-left">
    <a class="cms-ab-logo" href="/admin">&#9998;&nbsp;CMS</a>
    <span class="cms-ab-sep"></span>
    <span class="cms-ab-page">{$pageTitle}</span>
    <span class="cms-ab-mode-badge cms-ab-mode-badge--preview">NÁHLED</span>
  </div>
  <div class="cms-ab-right">
    <span class="cms-ab-ver">{$appVersion}</span>
    <a class="cms-ab-btn cms-ab-btn--active" href="{$editHref}">&#9998; Editace</a>
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
            } else {
                $editorMeta = '<meta name="inline-page-id" content="' . $pageId . '">'
                    . '<meta name="inline-csrf" content="' . htmlspecialchars($csrfToken, ENT_QUOTES) . '">'
                    . '<meta name="app-version" content="' . $appVersion . '">'
                    . '<link rel="stylesheet" href="/assets/inline-editor.css">';

                $adminBar = <<<HTML
<div id="cms-adminbar">
  <div class="cms-ab-left">
    <a class="cms-ab-logo" href="/admin">&#9998;&nbsp;CMS</a>
    <span class="cms-ab-sep"></span>
    <span class="cms-ab-page">{$pageTitle}</span>
    <span class="cms-ab-mode-badge cms-ab-mode-badge--edit">EDITACE</span>
  </div>
  <div class="cms-ab-right">
    <span class="cms-ab-ver">{$appVersion}</span>
    <span class="cms-ab-email">{$userEmail}</span>
    <a class="cms-ab-btn" href="{$previewHref}">&#128065; Náhled</a>
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
            }
        }

        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        return true;
    } catch (\Throwable $e) {
        Logger::error('frontend-render', $e->getMessage(), [
            'uri' => $uri,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        return false;
    }
}

try {
    $publishedDir = dirname(__DIR__) . '/storage/published';
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uri = rtrim($uri, '/') ?: '/';

    if (str_starts_with($uri, '/api/v1/') || $uri === '/api/v1') {
        (new \App\Controllers\ApiController())->dispatch($uri);
    }

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

    $previewMode = Auth::check() && isset($_GET['cms_preview']);
    if (tryServeDatabasePage($uri, Auth::check(), $previewMode)) {
        exit;
    }

    $path = ltrim($uri, '/');
    $rootFile = $publishedDir . '/' . $path;
    if ($path !== '' && pathinfo($path, PATHINFO_EXTENSION) !== '' && is_file($rootFile)) {
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
    $notFoundFile = $publishedDir . '/404.html';
    header('Content-Type: text/html; charset=UTF-8');
    if (is_file($notFoundFile)) {
        readfile($notFoundFile);
    } else {
        echo '<!DOCTYPE html><html><body><h1>404 Stránka nenalezena</h1></body></html>';
    }
} catch (\Throwable $e) {
    Runtime::handleException($e);
}
