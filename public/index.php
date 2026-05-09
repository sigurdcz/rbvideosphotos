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
use App\Repositories\PortfolioRepository;
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
        if (!empty($site['layout_html'])) {
            $isHome = ltrim((string)($page['url_path'] ?? ''), '/') === '';
            $nav = '';
            if ($isHome && !empty($site['layout_nav_home'])) {
                $nav = (string)$site['layout_nav_home'];
            } elseif (!$isHome && !empty($site['layout_nav_sub'])) {
                $nav = (string)$site['layout_nav_sub'];
            }
            $html = $renderer->applyLayout($site['layout_html'], $html, $site['name'] ?? '', $nav);
            $css  = ($site['layout_css'] ?? '') . "\n" . ($template['css_source'] ?? '');
            $js   = ($site['layout_js']  ?? '') . "\n" . ($template['js_source']  ?? '');
        } else {
            $css = $template['css_source'] ?? '';
            $js  = $template['js_source']  ?? '';
        }
        $html = $renderer->injectAssets($html, $css, $js);
        $html = $renderer->injectSeo(
            $html,
            $seoRenderer->renderHead($seo, $site, $pageUrl, $ogImageUrl, $twImageUrl),
            $seoRenderer->renderBodyScripts($site)
        );
        $html = sanitizeFrontendHtml($html);

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
    <span class="cms-ab-mode-badge cms-ab-mode-badge--preview">NAHLED</span>
  </div>
  <div class="cms-ab-right">
    <span class="cms-ab-ver">{$appVersion}</span>
    <a class="cms-ab-btn cms-ab-btn--active" href="{$editHref}">&#9998; Editace</a>
    <a class="cms-ab-btn" href="/admin">Administrace</a>
    <form method="POST" action="/admin/logout" style="margin:0;padding:0;display:inline">
      <input type="hidden" name="_csrf" value="{$csrfToken}">
      <button type="submit" class="cms-ab-btn cms-ab-logout">Odhlasit</button>
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
    <a class="cms-ab-btn" href="{$previewHref}">&#128065; Nahled</a>
    <a class="cms-ab-btn" href="/admin">Administrace</a>
    <form method="POST" action="/admin/logout" style="margin:0;padding:0;display:inline">
      <input type="hidden" name="_csrf" value="{$csrfToken}">
      <button type="submit" class="cms-ab-btn cms-ab-logout">Odhlasit</button>
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

function sanitizeFrontendHtml(string $html): string
{
    return preg_replace('~https?://localhost(?::\d+)?/storage/media/~i', '/storage/media/', $html) ?? $html;
}

function publicMediaUrl(string $storagePath): string
{
    $normalized = str_replace('\\', '/', trim($storagePath));
    if ($normalized === '') {
        return '';
    }

    if (preg_match('~https?://[^/]+(/storage/media/.*)$~i', $normalized, $m)) {
        return $m[1];
    }

    if (str_starts_with($normalized, '/storage/media/')) {
        return $normalized;
    }

    if (str_starts_with($normalized, 'storage/media/')) {
        return '/' . $normalized;
    }

    return '/' . ltrim($normalized, '/');
}

function renderPortfolioNav(string $siteName, string $active = 'portfolio'): string
{
    $h = fn($v) => htmlspecialchars($v, ENT_QUOTES);
    $brand = $h($siteName);
    $portfolioClass = $active === 'portfolio' ? ' class="nav-active"' : '';

    return <<<HTML
<nav class="nav" id="sitenav">
  <div class="container nav-inner">
    <a href="/" class="brand">{$brand}</a>
    <div class="nav-links">
      <a href="/#sluzby">Sluzby</a>
      <a href="/#nase-prace"{$portfolioClass}>Nase prace</a>
      <a href="/#reference">Reference</a>
      <a href="/#kontakt" class="nav-cta">Kontakt</a>
    </div>
  </div>
</nav>
HTML;
}

function tryRedirectLegacyContentPath(string $uri): bool
{
    if (str_starts_with($uri, '/blog/')) {
        $slug = trim(substr($uri, strlen('/blog/')), '/');
        if ($slug !== '') {
            header('Location: /' . rawurlencode($slug), true, 301);
            return true;
        }
    }

    if (str_starts_with($uri, '/nase-prace/')) {
        $slug = trim(substr($uri, strlen('/nase-prace/')), '/');
        if ($slug !== '') {
            header('Location: /' . rawurlencode($slug), true, 301);
            return true;
        }
    }

    return false;
}

function tryServePortfolioPage(string $uri, bool $detailOnly = false): bool
{
    $site = (new SiteRepository())->findFirst();
    if (!$site) {
        return false;
    }

    $repo = new PortfolioRepository();
    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
    $siteName = (string)($site['name'] ?? 'Muj web');

    if (!$detailOnly && $uri === '/nase-prace') {
        $entries = $repo->allPublished((int)$site['id']);
        $tiles = '';

        foreach ($entries as $entry) {
            $photos = $repo->getMedia((int)$entry['id']);
            $thumb = !empty($entry['video_url']) ? PortfolioRepository::youtubeThumbnail((string)$entry['video_url']) : '';
            if ($thumb === '' && !empty($photos)) {
                $thumb = publicMediaUrl((string)($photos[0]['storage_path'] ?? ''));
            }

            $extraHtml = '';
            foreach (array_slice($photos, 0, 3) as $photo) {
                $extraHtml .= '<img src="' . $h(publicMediaUrl((string)($photo['storage_path'] ?? ''))) . '" alt="' . $h($photo['alt_text'] ?? $entry['title']) . '" loading="lazy">';
            }

            $thumbHtml = $thumb !== ''
                ? '<img src="' . $h($thumb) . '" alt="' . $h($entry['title']) . '" loading="lazy">'
                : '<div class="pt-thumb-empty">&#9654;</div>';
            $playIcon = !empty($entry['video_url']) ? '<span class="pt-play">&#9654;</span>' : '';

            $tiles .= <<<HTML
<a class="pt-tile" href="/{$h($entry['slug'])}">
  <div class="pt-thumb">{$thumbHtml}{$playIcon}</div>
  <div class="pt-photos">{$extraHtml}</div>
  <div class="pt-caption">{$h($entry['title'])}</div>
</a>
HTML;
        }

        $tiles = $tiles !== '' ? $tiles : '<p class="pt-empty">Zat&#237;m &#382;&#225;dn&#233; publikovan&#233; pr&#225;ce.</p>';
        $siteNameEsc = $h($siteName);

        // CSS specific to portfolio pages — nav/footer/base reset comes from layout_css when layout is active
        $portfolioListingCss = <<<'CSS'
body{background:#111;color:#fff}
.pf-hero{background:#1a1a1a;color:#fff;padding:72px 0 46px;text-align:center}
.pf-hero h1{font-size:clamp(2.4rem,5vw,4rem);color:#fff;margin-bottom:10px}
.pf-hero p{color:rgba(255,255,255,.7);font-size:1.1rem;max-width:720px;margin:0 auto}
.grid-wrap{padding:12px 0 72px}
.pt-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:28px}
.pt-tile{display:block;text-decoration:none;color:inherit;background:#1b1b1f;border-radius:14px;overflow:hidden;box-shadow:0 14px 30px rgba(0,0,0,.25);transition:transform .2s,box-shadow .2s}
.pt-tile:hover{transform:translateY(-3px);box-shadow:0 20px 40px rgba(0,0,0,.4)}
.pt-thumb{position:relative;height:220px;background:#0e0e11;overflow:hidden}
.pt-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.pt-thumb-empty{height:100%;display:flex;align-items:center;justify-content:center;font-size:3rem;color:rgba(255,255,255,.25)}
.pt-play{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:68px;height:68px;border-radius:999px;background:rgba(196,150,60,.92);display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#fff;box-shadow:0 12px 28px rgba(0,0,0,.32)}
.pt-photos{display:flex;gap:4px;height:84px;padding:4px;background:#17171a}
.pt-photos img{flex:1;min-width:0;height:100%;object-fit:cover}
.pt-caption{padding:16px 18px 18px;font-weight:700;font-size:1rem;color:#fff;background:#17171a}
.pt-empty{color:rgba(255,255,255,.72);padding:32px 0;text-align:center}
@media(max-width:720px){.pt-grid{grid-template-columns:1fr}}
CSS;

        if (!empty($site['layout_html'])) {
            $renderer   = new PageRenderer();
            $nav = (string)($site['layout_nav_sub'] ?? '');
            $pageContent = <<<HTML
<section class="pf-hero">
  <div class="container">
    <h1>Na&#353;e pr&#225;ce</h1>
    <p>Uk&#225;zka vide&#237; a galer&#237; z na&#353;&#237; pr&#225;ce.</p>
  </div>
</section>
<section class="grid-wrap">
  <div class="container">
    <div class="pt-grid">{$tiles}</div>
  </div>
</section>
HTML;
            $html = $renderer->applyLayout($site['layout_html'], $pageContent, $siteName, $nav);
            $html = str_replace('</head>', "\n<title>" . $h("Na\u{161}e pr\u{E1}ce | {$siteName}") . "</title>\n</head>", $html);
            $html = $renderer->injectAssets($html, ($site['layout_css'] ?? '') . "\n" . $portfolioListingCss, $site['layout_js'] ?? '');
        } else {
            $nav = renderPortfolioNav($siteName, 'portfolio');
            $html = <<<HTML
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nase prace | {$siteNameEsc}</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:system-ui,-apple-system,sans-serif;background:#111;color:#fff}
    .container{max-width:1180px;margin:0 auto;padding:0 24px}
    .nav{position:sticky;top:0;z-index:30;background:#111;border-bottom:1px solid rgba(255,255,255,.08)}
    .nav-inner{display:flex;align-items:center;justify-content:space-between;min-height:78px;gap:20px}
    .brand,.nav a{color:#fff;text-decoration:none}
    .brand{font-weight:800;letter-spacing:.08em;text-transform:uppercase}
    .nav-links{display:flex;gap:28px;align-items:center}
    .nav-links a{opacity:.76;font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:.08em}
    .nav-links a:hover,.nav-links .nav-active{opacity:1}
    .nav-cta{background:#c4963c;padding:12px 18px;border-radius:2px;opacity:1!important}
    {$portfolioListingCss}
    footer{padding:28px 24px;border-top:1px solid rgba(255,255,255,.08);text-align:center;color:rgba(255,255,255,.46)}
  </style>
</head>
<body>
{$nav}
<section class="pf-hero">
  <div class="container">
    <h1>Nase prace</h1>
    <p>Ukazka videi a galerii, ktere jste vybrali v administraci.</p>
  </div>
</section>
<section class="grid-wrap">
  <div class="container">
    <div class="pt-grid">{$tiles}</div>
  </div>
</section>
<footer>{$siteNameEsc}</footer>
</body>
</html>
HTML;
        }

        header('Content-Type: text/html; charset=UTF-8');
        echo sanitizeFrontendHtml($html);
        return true;
    }

    $slug = trim($uri, '/');
    if ($slug !== '' && $slug !== 'nase-prace') {
        $entry = $repo->findBySlug($slug);
        if (!$entry) {
            return false;
        }

        $mediaItems = $repo->getMedia((int)$entry['id']);
        $embedUrl = !empty($entry['video_url']) ? PortfolioRepository::embedUrl((string)$entry['video_url']) : null;
        $galleryHtml = '';
        foreach ($mediaItems as $media) {
            $galleryHtml .= '<div class="pf-photo"><img src="' . $h(publicMediaUrl((string)($media['storage_path'] ?? ''))) . '" alt="' . $h($media['alt_text'] ?? $entry['title']) . '" loading="lazy"></div>';
        }

        $videoHtml = '';
        if ($embedUrl) {
            $videoHtml = '<div class="pf-video-wrap"><iframe src="' . $h($embedUrl) . '" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="lazy" title="' . $h($entry['title']) . '"></iframe></div>';
        }

        $title = $h($entry['title']);
        $siteNameEsc = $h($siteName);
        $description = (string)($entry['description'] ?? '');
        $metaDescription = $h($entry['meta_description'] ?? strip_tags(substr($description, 0, 160)));

        // CSS specific to portfolio detail — nav/footer/base reset comes from layout_css when layout is active
        $portfolioDetailCss = <<<'CSS'
body{background:#111;color:#fff}
.pf-detail-hero{background:#111;color:#fff;padding:72px 0 46px}
.pf-detail-hero h1{font-size:clamp(2rem,4.2vw,3.4rem);color:#fff;margin-bottom:12px}
.pf-detail-hero p{color:rgba(255,255,255,.72)}
.pf-main{padding:42px 0 72px}
.pf-video-wrap{position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:14px;margin-bottom:34px;background:#000}
.pf-video-wrap iframe{position:absolute;top:0;left:0;width:100%;height:100%}
.pf-description{max-width:760px;color:#aaa;margin-bottom:34px}
.pf-gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px}
.pf-photo img{width:100%;height:240px;object-fit:cover;border-radius:10px;display:block}
.pf-back{margin-top:28px}
.pf-back a{display:inline-flex;background:#c4963c;color:#fff;text-decoration:none;padding:12px 18px;border-radius:6px;font-weight:700}
CSS;

        if (!empty($site['layout_html'])) {
            $renderer = new PageRenderer();
            $nav = (string)($site['layout_nav_sub'] ?? '');
            $pageContent = <<<HTML
<section class="pf-detail-hero">
  <div class="container">
    <h1>{$title}</h1>
  </div>
</section>
<main class="pf-main">
  <div class="container">
    {$videoHtml}
    <div class="pf-description">{$description}</div>
    <div class="pf-gallery">{$galleryHtml}</div>
    <div class="pf-back"><a href="/nase-prace">&larr; Zp&#283;t na v&#353;echny pr&#225;ce</a></div>
  </div>
</main>
HTML;
            $html = $renderer->applyLayout($site['layout_html'], $pageContent, $siteName, $nav);
            $titleTag = '<title>' . $h("{$entry['title']} | {$siteName}") . '</title>';
            $metaDesc = '<meta name="description" content="' . $metaDescription . '">';
            $html = str_replace('</head>', "\n{$titleTag}\n{$metaDesc}\n</head>", $html);
            $html = $renderer->injectAssets($html, ($site['layout_css'] ?? '') . "\n" . $portfolioDetailCss, $site['layout_js'] ?? '');
        } else {
            $nav = renderPortfolioNav($siteName, 'portfolio');
            $html = <<<HTML
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$title} | {$siteNameEsc}</title>
  <meta name="description" content="{$metaDescription}">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:system-ui,-apple-system,sans-serif;background:#111;color:#fff;line-height:1.6}
    .container{max-width:1180px;margin:0 auto;padding:0 24px}
    .nav{position:sticky;top:0;z-index:30;background:#111;border-bottom:1px solid rgba(255,255,255,.08)}
    .nav-inner{display:flex;align-items:center;justify-content:space-between;min-height:78px;gap:20px}
    .brand,.nav a{color:#fff;text-decoration:none}
    .brand{font-weight:800;letter-spacing:.08em;text-transform:uppercase}
    .nav-links{display:flex;gap:28px;align-items:center}
    .nav-links a{opacity:.76;font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:.08em}
    .nav-links a:hover,.nav-links .nav-active{opacity:1}
    .nav-cta{background:#c4963c;padding:12px 18px;border-radius:2px;opacity:1!important}
    {$portfolioDetailCss}
    footer{padding:28px 24px;background:#0e0e0e;text-align:center;color:rgba(255,255,255,.46)}
  </style>
</head>
<body>
{$nav}
<section class="pf-detail-hero">
  <div class="container">
    <h1>{$title}</h1>
    <p>Detail vybrane prace a galerie z administrace.</p>
  </div>
</section>
<main class="pf-main">
  <div class="container">
    {$videoHtml}
    <div class="pf-description">{$description}</div>
    <div class="pf-gallery">{$galleryHtml}</div>
    <div class="pf-back"><a href="/nase-prace">&larr; Zpet na vsechny prace</a></div>
  </div>
</main>
<footer>{$siteNameEsc}</footer>
</body>
</html>
HTML;
        }

        header('Content-Type: text/html; charset=UTF-8');
        echo sanitizeFrontendHtml($html);
        return true;
    }

    return false;
}
try {
    $publishedDir = dirname(__DIR__) . '/storage/published';
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uri = rtrim($uri, '/') ?: '/';

    if (str_starts_with($uri, '/api/v1/') || $uri === '/api/v1') {
        (new \App\Controllers\ApiController())->dispatch($uri);
    }

    if (str_starts_with($uri, '/assets/')) {
        $assetsBase = realpath(__DIR__ . '/assets');
        $file       = realpath(__DIR__ . $uri);
        if ($assetsBase !== false && $file !== false && str_starts_with($file, $assetsBase . DIRECTORY_SEPARATOR)) {
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
        $mediaBase = realpath(dirname(__DIR__) . '/storage/media');
        $file      = realpath(dirname(__DIR__) . $uri);
        if ($mediaBase !== false && $file !== false && str_starts_with($file, $mediaBase . DIRECTORY_SEPARATOR)) {
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

    if (tryRedirectLegacyContentPath($uri)) {
        exit;
    }

    if ($uri === '/nase-prace') {
        if (tryServePortfolioPage($uri)) {
            exit;
        }
    }

    $previewMode = Auth::check() && isset($_GET['cms_preview']);
    if (tryServeDatabasePage($uri, Auth::check(), $previewMode)) {
        exit;
    }

    if (tryServePortfolioPage($uri, true)) {
        exit;
    }

    $path = ltrim($uri, '/');
    if ($path !== '' && pathinfo($path, PATHINFO_EXTENSION) !== '') {
        $publishedBase = realpath($publishedDir);
        $rootFile      = realpath($publishedDir . '/' . $path);
        if ($publishedBase !== false && $rootFile !== false && str_starts_with($rootFile, $publishedBase . DIRECTORY_SEPARATOR)) {
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
    }

    http_response_code(404);
    $notFoundFile = $publishedDir . '/404.html';
    header('Content-Type: text/html; charset=UTF-8');
    if (is_file($notFoundFile)) {
        readfile($notFoundFile);
    } else {
        echo '<!DOCTYPE html><html><body><h1>404 Stranka nenalezena</h1></body></html>';
    }
} catch (\Throwable $e) {
    Runtime::handleException($e);
}

