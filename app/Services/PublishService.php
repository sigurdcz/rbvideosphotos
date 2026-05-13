<?php

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use App\Core\Version;
use App\Repositories\BlockRepository;
use App\Repositories\ContactRepository;
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
        Logger::infoTo('theme-debug', 'theme.publish.request', 'Publish theme resolution requested', [
            'site_id' => $site['id'] ?? null,
            'site_name' => $site['name'] ?? null,
            'page_id' => $page['id'] ?? null,
            'page_key' => $pageKey,
            'resolved_theme_key' => $theme['key'] ?? null,
            'theme_found' => $theme !== null,
        ]);
        $themeCss = '';
        $themeJs = '';
        $sharedCss = '';
        $isHome = trim((string)($page['url_path'] ?? ''), '/') === '';
        $themeNav = $isHome && !empty($site['layout_nav_home'])
            ? (string)$site['layout_nav_home']
            : (!empty($site['layout_nav_sub']) ? (string)$site['layout_nav_sub'] : '');
        if ($theme) {
            $themeCss = $this->themes->getCssForPage($theme, $pageKey);
            $themeJs = $this->themes->getJsForPage($theme, $pageKey);
            $sharedCss = $this->themes->getSharedComponentCss();
            $themeLayout = $this->themes->getLayoutHtmlForPage($theme, $pageKey);
            if ($themeLayout !== '') {
                $html = $this->renderer->applyLayout($themeLayout, $html, $site['name'] ?? '', $themeNav);
            }
            $html = $this->renderer->stripInlineStyles($html);
            $html = $this->themes->injectBodyClass($html, (string)($theme['key'] ?? ''));
            $css = ($template['css_source'] ?? '') . "\n" . $sharedCss . "\n" . $themeCss;
            $js  = ($template['js_source']  ?? '') . "\n" . $themeJs;
            Logger::infoTo('theme-debug', 'theme.publish.applied', 'Publish theme applied', [
                'site_id' => $site['id'] ?? null,
                'site_name' => $site['name'] ?? null,
                'page_id' => $page['id'] ?? null,
                'page_key' => $pageKey,
                'theme_key' => $theme['key'] ?? null,
                'layout_applied' => $themeLayout !== '',
                'css_len' => strlen($css),
                'js_len' => strlen($js),
                'html_len' => strlen($html),
            ]);
        } elseif (!empty($site['layout_html'])) {
            $html = $this->renderer->applyLayout($site['layout_html'], $html, $site['name'] ?? '', $themeNav);
            $css  = ($site['layout_css'] ?? '') . "\n" . ($template['css_source'] ?? '') . "\n" . $themeCss;
            $js   = ($site['layout_js']  ?? '') . "\n" . ($template['js_source']  ?? '') . "\n" . $themeJs;
            Logger::infoTo('theme-debug', 'theme.publish.default_layout', 'Publish used site default layout', [
                'site_id' => $site['id'] ?? null,
                'site_name' => $site['name'] ?? null,
                'page_id' => $page['id'] ?? null,
                'page_key' => $pageKey,
            ]);
        } else {
            $css = ($template['css_source'] ?? '') . "\n" . $themeCss;
            $js  = ($template['js_source']  ?? '') . "\n" . $themeJs;
            Logger::infoTo('theme-debug', 'theme.publish.raw', 'Publish used raw template output', [
                'site_id' => $site['id'] ?? null,
                'site_name' => $site['name'] ?? null,
                'page_id' => $page['id'] ?? null,
                'page_key' => $pageKey,
            ]);
        }
        $html = $this->renderer->injectAssets($html, $css, $js);

        $seoHead     = $this->seoRenderer->renderHead($seo, $site, $pageUrl, $ogImageUrl, $twImageUrl);
        $bodyScripts = $this->seoRenderer->renderBodyScripts($site);
        $html        = $this->renderer->injectSeo($html, $seoHead, $bodyScripts);
        $html        = $this->injectRichFooter($html, $site);
        $html        = $this->injectSiteLogo($html, $site);
        $html        = $this->injectPageHeroImage($html, (int)$page['id']);
        if ($isHome) {
            $html = $this->injectHomepageHeroVideo($html, (int)$page['id']);
        }

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

    private function injectPageHeroImage(string $html, int $pageId): string
    {
        $block = $this->blocks->findByKey($pageId, 'hero_bg');
        $url = $block ? $this->normalizePublicAssetUrl((string)($block['content'] ?? '')) : '';
        if ($url === '') {
            return $html;
        }

        return $this->injectBodyCssVariable($html, '--theme-page-hero-image', "url('" . str_replace("'", '%27', $url) . "')");
    }

    private function injectHomepageHeroVideo(string $html, int $pageId): string
    {
        if (str_contains($html, 'theme-hero-video')) {
            return $html;
        }

        $block = $this->blocks->findByKey($pageId, 'hero_video');
        $url = $block ? $this->normalizePublicAssetUrl((string)($block['content'] ?? '')) : '';
        if ($url === '') {
            return $html;
        }

        $videoHtml = $this->buildHomepageHeroVideoHtml($url);
        if ($videoHtml === '') {
            return $html;
        }

        $style = <<<'HTML'
<style data-cms-hero-video>
.hp-hero,.hero{position:relative;overflow:hidden}
.hp-hero::before,.hero::before{z-index:0!important}
.theme-hero-video{position:absolute;inset:0;z-index:1;width:100%;height:100%;object-fit:cover;pointer-events:none;background:#050505}
.theme-hero-video--embed{overflow:hidden}
.theme-hero-video--embed iframe{position:absolute;top:50%;left:50%;width:120vw;height:67.5vw;min-width:213.33vh;min-height:120vh;transform:translate(-50%,-50%);border:0;pointer-events:none}
.hp-hero>.container,.hero>.container,.hero-content{position:relative;z-index:3}
</style>
HTML;
        if (!str_contains($html, 'data-cms-hero-video')) {
            $html = str_replace('</head>', "\n" . $style . "\n</head>", $html);
        }

        $count = 0;
        $html = preg_replace_callback(
            '/(<section\b(?=[^>]*\bclass=(["\'])(?=[^"\']*(?:hp-hero|hero))[^"\']*\2)[^>]*>)/i',
            static fn(array $m): string => $m[1] . $videoHtml,
            $html,
            1,
            $count
        ) ?? $html;

        if ($count > 0) {
            return $html;
        }

        return preg_replace_callback('/(<main\b[^>]*>)/i', static fn(array $m): string => $m[1] . $videoHtml, $html, 1) ?? $html;
    }

    private function buildHomepageHeroVideoHtml(string $videoUrl): string
    {
        $embedUrl = $this->buildVideoEmbedUrl($videoUrl);
        if ($embedUrl !== '') {
            return '<div class="theme-hero-video theme-hero-video--embed" aria-hidden="true"><iframe src="' . htmlspecialchars($embedUrl, ENT_QUOTES) . '" title="Homepage hero video" allow="autoplay; fullscreen; picture-in-picture" loading="eager"></iframe></div>';
        }

        return '<video class="theme-hero-video" src="' . htmlspecialchars($videoUrl, ENT_QUOTES) . '" autoplay muted loop playsinline preload="auto" aria-hidden="true"></video>';
    }

    private function buildVideoEmbedUrl(string $videoUrl): string
    {
        $url = trim($videoUrl);
        if (preg_match('~vimeo\.com/(?:video/)?([0-9]+)~i', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1] . '?background=1&autoplay=1&loop=1&muted=1&playsinline=1';
        }

        $youtubeId = '';
        if (preg_match('~youtu\.be/([A-Za-z0-9_-]{6,})~i', $url, $m)) {
            $youtubeId = $m[1];
        } elseif (preg_match('~youtube\.com/(?:embed|shorts)/([A-Za-z0-9_-]{6,})~i', $url, $m)) {
            $youtubeId = $m[1];
        } elseif (preg_match('~[?&]v=([A-Za-z0-9_-]{6,})~i', $url, $m)) {
            $youtubeId = $m[1];
        }

        return $youtubeId !== ''
            ? 'https://www.youtube.com/embed/' . $youtubeId . '?autoplay=1&mute=1&controls=0&loop=1&playlist=' . $youtubeId . '&playsinline=1&modestbranding=1&rel=0'
            : '';
    }

    private function injectSiteLogo(string $html, array $site): string
    {
        $url = $this->normalizePublicAssetUrl((string)($site['logo_url'] ?? ''));
        if ($url === '') {
            return $html;
        }

        $safeUrl = htmlspecialchars($url, ENT_QUOTES);
        $safeName = htmlspecialchars((string)($site['name'] ?? 'Web'), ENT_QUOTES);
        $brandHtml = '<img class="site-logo-img" src="' . $safeUrl . '" alt="' . $safeName . '"><span class="site-logo-text">' . $safeName . '</span>';
        $html = preg_replace_callback(
            '~(<a\b(?=[^>]*\bclass=(["\'])(?=[^"\']*(?:theme-brand|brand|pf-nav-logo|pl-nav-logo))[^"\']*\2)[^>]*>).*?(</a>)~is',
            static fn(array $m): string => $m[1] . $brandHtml . $m[3],
            $html
        ) ?? $html;

        if (!str_contains($html, 'data-cms-site-logo')) {
            $html = str_replace('</head>', "\n<style data-cms-site-logo>.site-logo-img{display:block;max-height:44px;max-width:120px;width:auto;height:auto;object-fit:contain}.site-logo-text{display:inline-block;margin-left:10px;color:inherit;font:inherit;letter-spacing:inherit;text-transform:inherit;line-height:1}</style>\n</head>", $html);
        }

        return $html;
    }

    private function injectRichFooter(string $html, array $site): string
    {
        $footer = $this->buildRichFooterHtml($site);
        if (preg_match('~<footer\b([^>]*)>.*?</footer>~is', $html)) {
            return preg_replace('~<footer\b([^>]*)>.*?</footer>~is', '<footer$1>' . $footer . '</footer>', $html, 1) ?? $html;
        }

        if (str_contains($html, '</body>')) {
            return str_replace('</body>', '<footer>' . $footer . '</footer></body>', $html);
        }

        return $html . '<footer>' . $footer . '</footer>';
    }

    private function buildRichFooterHtml(array $site): string
    {
        $siteId = (int)($site['id'] ?? 0);
        $contact = $siteId > 0 ? (new ContactRepository())->findBySiteSafe($siteId) : [];
        $siteName = (string)($site['name'] ?? 'Muj web');
        $logoUrl = $this->normalizePublicAssetUrl((string)($site['logo_url'] ?? ''));
        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
        $socialIcons = [
            'instagram' => 'IG',
            'facebook' => 'FB',
            'youtube' => 'YT',
            'tiktok' => 'TT',
            'linkedin' => 'IN',
            'twitter' => 'X',
            'pinterest' => 'PI',
            'whatsapp' => 'WA',
            'telegram' => 'TG',
        ];

        $contactItems = [];
        foreach (['phone_1', 'phone_2', 'phone_3'] as $phoneKey) {
            $phone = trim((string)($contact[$phoneKey] ?? ''));
            if ($phone === '') {
                continue;
            }
            $tel = preg_replace('/\s+/', '', $phone);
            $contactItems[] = '<a class="site-footer-contact__item" href="tel:' . $h($tel) . '"><span>Telefon</span><strong>' . $h($phone) . '</strong></a>';
        }

        $email = trim((string)($contact['email'] ?? ''));
        if ($email !== '') {
            $contactItems[] = '<a class="site-footer-contact__item" href="mailto:' . $h($email) . '"><span>E-mail</span><strong>' . $h($email) . '</strong></a>';
        }

        $address = trim((string)($contact['address_1'] ?? '')) ?: trim((string)($contact['address_2'] ?? ''));
        if ($address !== '') {
            $contactItems[] = '<div class="site-footer-contact__item"><span>Adresa</span><strong>' . nl2br($h($address)) . '</strong></div>';
        }

        $socialItems = [];
        for ($i = 1; $i <= 5; $i++) {
            $type = trim((string)($contact['social_' . $i . '_type'] ?? ''));
            $url = trim((string)($contact['social_' . $i . '_url'] ?? ''));
            if ($type === '' || $url === '') {
                continue;
            }
            $icon = $socialIcons[$type] ?? 'URL';
            $socialItems[] = '<a class="site-footer-social" href="' . $h($url) . '" target="_blank" rel="noopener" aria-label="' . $h(ucfirst($type)) . '" title="' . $h(ucfirst($type)) . '"><span class="site-footer-social__icon">' . $icon . '</span></a>';
        }

        $brand = $logoUrl !== ''
            ? '<img class="site-footer-brand__logo" src="' . $h($logoUrl) . '" alt="' . $h($siteName) . '">'
            : '<span class="site-footer-brand__mark">' . $h(mb_substr($siteName, 0, 2)) . '</span>';

        return '<style data-cms-rich-footer>'
            . 'html,body{min-height:100%}body{min-height:100vh;display:flex;flex-direction:column}.theme-shell{width:100%;flex:1 0 auto;display:flex;flex-direction:column}.theme-shell,.theme-shell__frame{min-height:100vh}.theme-shell__frame{display:flex;flex-direction:column;flex:1 0 auto}.theme-main,main,#page-content{flex:1 0 auto}footer,.theme-footer,.site-footer{margin-top:auto}'
            . 'footer .site-footer-rich,.theme-footer .site-footer-rich,.site-footer .site-footer-rich{width:100%;max-width:1180px;margin:0 auto;padding:0;display:grid;grid-template-columns:minmax(220px,.85fr) minmax(260px,1fr) minmax(220px,.75fr);gap:34px;align-items:start;text-align:left}'
            . '.site-footer-brand{display:flex;flex-direction:column;gap:12px;align-items:flex-start}.site-footer-brand__logo{max-height:52px;max-width:260px;width:auto;height:auto;object-fit:contain}.site-footer-brand__mark{display:inline-grid;place-items:center;width:54px;height:54px;border:1px solid rgba(198,148,61,.45);color:#c6943d;font-family:Georgia,serif;font-weight:800;font-size:20px;letter-spacing:.08em;text-transform:uppercase}.site-footer-brand__name{color:#f3efe7;font-family:Georgia,serif;font-size:22px;line-height:1.1}.site-footer-brand__note{color:rgba(255,255,255,.52);font-size:13px;line-height:1.65}'
            . '.site-footer-contact{display:grid;gap:12px}.site-footer-title{margin:0 0 12px;color:#c6943d;font-size:11px;font-weight:900;letter-spacing:.18em;text-transform:uppercase}.site-footer-contact__item{display:grid;gap:4px;color:#f3efe7;text-decoration:none}.site-footer-contact__item span{color:rgba(255,255,255,.44);font-size:11px;text-transform:uppercase;letter-spacing:.12em}.site-footer-contact__item strong{font-size:14px;line-height:1.5;font-weight:700}'
            . '.site-footer-socials{display:flex;flex-direction:column;gap:14px;align-items:flex-start}.site-footer-socials__list{display:flex;flex-wrap:wrap;gap:10px}.site-footer-social{display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;padding:0;border:1px solid rgba(255,255,255,.14);border-radius:999px;background:rgba(255,255,255,.03);color:#fff;text-decoration:none;font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;transition:transform .2s,border-color .2s,background .2s,color .2s}.site-footer-social:hover{transform:translateY(-1px);border-color:#c6943d;background:#c6943d;color:#080808}.site-footer-social__icon{display:grid;place-items:center;width:26px;height:26px;border:1px solid currentColor;border-radius:50%;font-size:10px;line-height:1}.site-footer-bottom{grid-column:1/-1;margin-top:4px;padding-top:20px;border-top:1px solid rgba(255,255,255,.09);color:rgba(255,255,255,.36);font-size:12px;text-align:left}'
            . '@media(max-width:820px){footer .site-footer-rich,.theme-footer .site-footer-rich,.site-footer .site-footer-rich{grid-template-columns:1fr;gap:24px}.site-footer-bottom{text-align:left}}'
            . '</style>'
            . '<div class="site-footer-rich">'
            . '<div class="site-footer-brand">' . $brand . '<div class="site-footer-brand__name">' . $h($siteName) . '</div><div class="site-footer-brand__note">Svatebni fotografie a video. Olomouc, Morava a cela CR.</div></div>'
            . '<div class="site-footer-contact"><div class="site-footer-title">Kontakt</div>' . ($contactItems !== [] ? implode('', $contactItems) : '<div class="site-footer-contact__item"><span>Kontakt</span><strong>Doplnte kontaktni udaje v administraci.</strong></div>') . '</div>'
            . '<div class="site-footer-socials"><div class="site-footer-title">Socialni site</div><div class="site-footer-socials__list">' . ($socialItems !== [] ? implode('', $socialItems) : '<span class="site-footer-brand__note">Doplnte socialni site v kontaktu.</span>') . '</div></div>'
            . '<div class="site-footer-bottom">&copy; ' . date('Y') . ' ' . $h($siteName) . '</div>'
            . '</div>';
    }

    private function injectBodyCssVariable(string $html, string $name, string $value): string
    {
        $styleValue = $name . ':' . $value;
        if (!preg_match('/<body([^>]*)>/i', $html, $m)) {
            return $html;
        }

        $attrs = $m[1];
        $pattern = '/' . preg_quote($name, '/') . '\s*:\s*[^;]+;?/i';
        if (preg_match('/\sstyle=(["\'])(.*?)\1/i', $attrs, $styleMatch)) {
            $existing = html_entity_decode($styleMatch[2], ENT_QUOTES, 'UTF-8');
            $existing = preg_match($pattern, $existing)
                ? (preg_replace($pattern, $styleValue . ';', $existing) ?? $existing)
                : rtrim($existing, '; ') . ';' . $styleValue . ';';
            $attrs = preg_replace('/\sstyle=(["\'])(.*?)\1/i', ' style="' . htmlspecialchars($existing, ENT_QUOTES) . '"', $attrs, 1) ?? $attrs;
        } else {
            $attrs .= ' style="' . htmlspecialchars($styleValue . ';', ENT_QUOTES) . '"';
        }

        return preg_replace('/<body([^>]*)>/i', '<body' . $attrs . '>', $html, 1) ?? $html;
    }

    private function normalizePublicAssetUrl(string $value): string
    {
        $normalized = str_replace('\\', '/', trim($value));
        if ($normalized === '') {
            return '';
        }
        if (preg_match('~^https?://~i', $normalized) || str_starts_with($normalized, '/')) {
            return $normalized;
        }
        return '/' . ltrim($normalized, '/');
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
