<?php

namespace App\Services;

use App\Core\Config;
use App\Core\Version;
use App\Repositories\BlockRepository;
use App\Repositories\MediaRepository;
use App\Repositories\PageRepository;
use App\Repositories\PublishHistoryRepository;
use App\Repositories\RedirectRepository;
use App\Repositories\SeoRepository;
use App\Repositories\SiteRepository;
use App\Repositories\TemplateRepository;
use App\Services\ThemeTemplateRegistry;

class PublishService
{
    private PageRenderer $renderer;
    private HomepageContentService $homepageContent;
    private SeoRenderer $seoRenderer;
    private SitemapGenerator $sitemap;
    private RobotsGenerator $robots;
    private PageRepository $pages;
    private TemplateRepository $templates;
    private BlockRepository $blocks;
    private SeoRepository $seo;
    private SiteRepository $sites;
    private MediaRepository $media;
    private RedirectRepository $redirects;
    private PublishHistoryRepository $history;
    private ThemeTemplateRegistry $themes;

    private string $publishedDir;

    public function __construct()
    {
        $this->renderer   = new PageRenderer();
        $this->homepageContent = new HomepageContentService();
        $this->seoRenderer= new SeoRenderer();
        $this->sitemap    = new SitemapGenerator();
        $this->robots     = new RobotsGenerator();
        $this->pages      = new PageRepository();
        $this->templates  = new TemplateRepository();
        $this->blocks     = new BlockRepository();
        $this->seo        = new SeoRepository();
        $this->sites      = new SiteRepository();
        $this->media      = new MediaRepository();
        $this->redirects  = new RedirectRepository();
        $this->history    = new PublishHistoryRepository();
        $this->themes     = new ThemeTemplateRegistry();
        $this->publishedDir = dirname(__DIR__, 2) . '/storage/published';
    }

    public function publishPage(int $pageId, int $userId): array
    {
        $page = $this->pages->findById($pageId);
        if (!$page) {
            return ['success' => false, 'message' => 'Stránka nenalezena.'];
        }

        $site = $this->sites->findById($page['site_id']);
        if (!$site) {
            return ['success' => false, 'message' => 'Web nenalezen.'];
        }

        $template = $page['template_id'] ? $this->templates->findById((int)$page['template_id']) : null;
        if (!$template) {
            return ['success' => false, 'message' => 'Šablona nenalezena.'];
        }

        $blocks   = $this->blocks->allByPage($pageId);
        $blocks   = $this->homepageContent->applyFallbacks($page, $blocks);
        $seo      = $this->seo->findByPage($pageId) ?? [];
        $mediaMap = $this->buildMediaMap($blocks, $site);
        $pageKey  = $this->themes->normalizePageKey((string)($page['url_path'] ?? ''));

        // Build page URL
        $pageUrl = rtrim($site['domain'], '/') . '/' . ltrim($page['url_path'], '/');

        // Resolve OG/Twitter image URLs
        $ogImageUrl = $this->resolveImageUrl($seo['og_image_id'] ?? null, $site);
        $twImageUrl = $this->resolveImageUrl($seo['twitter_image_id'] ?? null, $site);

        // Render
        $html = $this->renderer->render($template['html_source'], $blocks, $mediaMap);
        $theme = $this->themes->resolveForSite($site);
        $themeCss = '';
        $themeJs = '';
        $isHome = trim((string)($page['url_path'] ?? ''), '/') === '';
        $themeNav = $isHome && !empty($site['layout_nav_home'])
            ? (string)$site['layout_nav_home']
            : (!empty($site['layout_nav_sub']) ? (string)$site['layout_nav_sub'] : '');
        if ($theme) {
            $themeCss = $this->themes->getCssForPage($theme, $pageKey);
            $themeJs = $this->themes->getJsForPage($theme, $pageKey);
            $themeLayout = $this->themes->getLayoutHtmlForPage($theme, $pageKey);
            if ($themeLayout !== '') {
                $html = $this->renderer->applyLayout($themeLayout, $html, $site['name'] ?? '', $themeNav);
            }
            $html = $this->themes->injectBodyClass($html, (string)($theme['key'] ?? ''));
            $css = ($template['css_source'] ?? '') . "\n" . $themeCss;
            $js  = ($template['js_source']  ?? '') . "\n" . $themeJs;
        } elseif (!empty($site['layout_html'])) {
            $html = $this->renderer->applyLayout($site['layout_html'], $html, $site['name'] ?? '', $themeNav);
            $css  = ($site['layout_css'] ?? '') . "\n" . ($template['css_source'] ?? '') . "\n" . $themeCss;
            $js   = ($site['layout_js']  ?? '') . "\n" . ($template['js_source']  ?? '') . "\n" . $themeJs;
        } else {
            $css = ($template['css_source'] ?? '') . "\n" . $themeCss;
            $js  = ($template['js_source']  ?? '') . "\n" . $themeJs;
        }
        $html = $this->renderer->injectAssets($html, $css, $js);

        $seoHead     = $this->seoRenderer->renderHead($seo, $site, $pageUrl, $ogImageUrl, $twImageUrl);
        $bodyScripts = $this->seoRenderer->renderBodyScripts($site);
        $html        = $this->renderer->injectSeo($html, $seoHead, $bodyScripts);

        // Write HTML file
        $filePath = $this->resolveFilePath($page['url_path']);
        $this->ensureDir(dirname($filePath));
        $stamp = '<!-- cms: ' . Version::APP . ' published: ' . date('Y-m-d H:i:s') . " -->\n";
        file_put_contents($filePath, $stamp . $html);

        // Update page status
        $this->pages->update($pageId, [
            'status'       => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ]);

        // Regenerate sitemap and robots
        $this->regenerateSitemapAndRobots($page['site_id'], $site);

        // Write history
        $version = $this->history->nextVersion($page['site_id']);
        $this->history->create([
            'site_id' => $page['site_id'],
            'user_id' => $userId,
            'version' => $version,
            'status'  => 'success',
            'notes'   => 'Published: ' . $page['title'],
        ]);

        return ['success' => true, 'message' => 'Stránka byla publikována.', 'file' => $filePath];
    }

    public function publishSite(int $siteId, int $userId): array
    {
        $site  = $this->sites->findById($siteId);
        $pages = $this->pages->findPublishedBySite($siteId);
        $errors = [];

        foreach ($pages as $page) {
            $result = $this->publishPage($page['id'], $userId);
            if (!$result['success']) {
                $errors[] = $result['message'];
            }
        }

        $this->regenerateSitemapAndRobots($siteId, $site);
        $this->generateHtaccess($siteId);
        $this->generate404($site);

        return ['success' => empty($errors), 'errors' => $errors];
    }

    private function regenerateSitemapAndRobots(int $siteId, array $site): void
    {
        $pages = $this->pages->findPublishedBySite($siteId);

        $seoCfg  = Config::get('seo');
        $sitemapXml = $this->sitemap->generate($pages, $site['domain'], $seoCfg['sitemap_changefreq'], $seoCfg['sitemap_priority']);
        file_put_contents($this->publishedDir . '/sitemap.xml', $sitemapXml);

        $robotsTxt = $this->robots->generate($site['domain']);
        file_put_contents($this->publishedDir . '/robots.txt', $robotsTxt);
    }

    public function generateHtaccess(int $siteId): void
    {
        $redirects = $this->redirects->allBySite($siteId);
        $lines = ["Options -Indexes", "RewriteEngine On", "ErrorDocument 404 /404.html", ""];
        foreach ($redirects as $r) {
            $lines[] = "Redirect {$r['status_code']} {$r['from_path']} {$r['to_path']}";
        }
        $lines[] = "";
        file_put_contents($this->publishedDir . '/.htaccess', implode("\n", $lines));
    }

    public function generate404(array $site): void
    {
        $name   = htmlspecialchars($site['name'] ?? 'RB Photos & Videos', ENT_QUOTES);
        $domain = rtrim($site['domain'] ?? '', '/');
        $stamp  = '<!-- cms: ' . Version::APP . ' published: ' . date('Y-m-d H:i:s') . " -->\n";

        $html = <<<HTML
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>404 &#8212; St&#225;nka nenalezena | {$name}</title>
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,-apple-system,sans-serif;color:#222;background:#0f0f0f;line-height:1.6;font-size:16px}
.container{max-width:1100px;margin:0 auto;padding:0 24px}
a{color:#c4963c;text-decoration:none}
.nav{position:fixed;top:0;left:0;right:0;z-index:500;background:rgba(17,17,17,.97)}
.nav-inner{display:flex;align-items:center;height:60px}
.brand{font-size:13px;font-weight:800;letter-spacing:.1em;color:#fff;text-transform:uppercase;margin-right:auto}
.nav-links{display:flex;gap:28px;align-items:center}
.nav-links a{color:rgba(255,255,255,.6);font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;transition:color .2s}
.nav-links a:hover{color:#fff}
.nav-cta{background:#c4963c !important;color:#fff !important;padding:8px 18px}
.hero-404{min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;background:#0f0f0f;padding:80px 24px 60px}
.err-num{display:block;font-size:clamp(7rem,20vw,13rem);font-weight:900;color:#c4963c;line-height:1;letter-spacing:-.04em;margin-bottom:0}
.err-line{display:block;width:60px;height:3px;background:#c4963c;margin:16px auto 28px}
.hero-404 h1{font-size:clamp(1.6rem,4vw,2.4rem);font-weight:900;color:#fff;margin-bottom:14px}
.hero-404 p{color:rgba(255,255,255,.5);font-size:1rem;max-width:440px;margin:0 auto 36px}
.btn-h{display:inline-block;padding:14px 28px;font-size:12px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;transition:all .2s;border:2px solid transparent}
.btn-h--gold{background:#c4963c;color:#fff;border-color:#c4963c}
.btn-h--gold:hover{background:#b5853a;border-color:#b5853a;color:#fff}
.btn-h--outline{background:transparent;color:rgba(255,255,255,.7);border-color:rgba(255,255,255,.35);margin-left:12px}
.btn-h--outline:hover{background:rgba(255,255,255,.08);color:#fff;border-color:rgba(255,255,255,.6)}
.footer{background:#111;color:rgba(255,255,255,.3);padding:24px;text-align:center;font-size:12px}
@media(max-width:480px){.btn-h--outline{margin-left:0;margin-top:10px}}
  </style>
</head>
<body>
<nav class="nav">
  <div class="container nav-inner">
    <a href="/" class="brand">{$name}</a>
    <div class="nav-links">
      <a href="/#sluzby">Slu&#382;by</a>
      <a href="/#videa">Videa</a>
      <a href="/#reference">Reference</a>
      <a href="/#kontakt" class="nav-cta">Kontakt</a>
    </div>
  </div>
</nav>
<div class="hero-404">
  <div>
    <span class="err-num">404</span>
    <span class="err-line"></span>
    <h1>St&#225;nka nenalezena</h1>
    <p>Tato str&#225;nka neexistuje nebo byla p&#345;esunuta. Zkuste to od za&#269;&#225;tku.</p>
    <a href="/" class="btn-h btn-h--gold">&#8592; Zp&#283;t na &#250;vodn&#237; str&#225;nku</a>
    <a href="javascript:history.back()" class="btn-h btn-h--outline">&#8592; P&#345;edchoz&#237; str&#225;nka</a>
  </div>
</div>
<footer class="footer">
  <p>&copy; 2025 {$name}</p>
</footer>
</body>
</html>
HTML;

        file_put_contents($this->publishedDir . '/404.html', $stamp . $html);
    }

    private function resolveFilePath(string $urlPath): string
    {
        $path = trim($urlPath, '/');
        if ($path === '' || $path === 'index') {
            return $this->publishedDir . '/index.html';
        }
        return $this->publishedDir . '/' . $path . '/index.html';
    }

    private function buildMediaMap(array $blocks, array $site): array
    {
        $map = [];
        foreach ($blocks as $block) {
            if ($block['block_type'] === 'image' && !empty($block['content'])) {
                $map[$block['block_key']] = [
                    'url' => $block['content'],
                    'alt' => $block['label'] ?? '',
                ];
            }
        }
        return $map;
    }

    private function resolveImageUrl(?int $mediaId, array $site): ?string
    {
        if (!$mediaId) return null;
        $m = $this->media->findById($mediaId);
        if (!$m) return null;
        return rtrim($site['domain'], '/') . '/' . ltrim($m['storage_path'], '/');
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
