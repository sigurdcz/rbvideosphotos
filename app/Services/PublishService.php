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
use App\Services\LlmsTxtGenerator;
use App\Services\ThemeTemplateRegistry;
use App\Support\FrontendAssetManager;

class PublishService
{
    private const DEFAULT_FORM_EMAIL = ContactRepository::DEFAULT_FORM_EMAIL;
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
        $html = $this->renderer->render($this->normalizeLegacyTemplateSource((string)($template['html_source'] ?? '')), $blocks, $mediaMap);
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
        $html = $this->injectHomepageAboutVisualSource($html, (int)$page['id']);
        $html = $this->renderer->injectAssets($html, $css, $js);

        $seoHead     = $this->seoRenderer->renderHead($seo, $site, $pageUrl, $ogImageUrl, $twImageUrl);
        $bodyScripts = $this->seoRenderer->renderBodyScripts($site);
        $html        = $this->renderer->injectSeo($html, $seoHead, $bodyScripts);
        $html        = $this->injectRichFooter($html, $site);
        $html        = $this->injectSiteLogo($html, $site);
        $html        = $this->injectPageHeroImage($html, (int)$page['id']);
        $html = $this->injectPageHeroVideo($html, $site, (int)$page['id']);
        $html = $this->injectBodyPageKey($html, $pageKey);
        $html = $this->normalizeLegacyFrontendMarkup($html);
        $html = $this->injectSharedFrontendAssets($html);
        $html = $this->externalizeBodyCssVariables($html);
        $html = $this->injectGlobalVideoOverlayStyles($html, $site);
        $html = $this->injectEditorialGoldOverrides($html);
        $html = $this->extractInlineStyleBlocks($html);
        $html = $this->extractInlineBodyScripts($html);

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
        FrontendAssetManager::clearGeneratedAssets();

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

        $llmsTxt = (new LlmsTxtGenerator())->generate($site, $pages);
        file_put_contents($this->publishedDir . '/llms.txt', $llmsTxt);
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

        // Preferuj OG variantu (1200x630), jinak original.
        $path = (string) ($m['storage_path'] ?? '');
        foreach ($this->media->variantsByMediaId($mediaId) as $variant) {
            if (($variant['format'] ?? '') === 'og' && !empty($variant['path'])) {
                $path = (string) $variant['path'];
                break;
            }
        }
        if ($path === '') return null;

        return rtrim($site['domain'], '/') . '/' . ltrim($path, '/');
    }

    private function injectPageHeroImage(string $html, int $pageId): string
    {
        $block = $this->blocks->findByKey($pageId, 'hero_bg');
        $url = $block ? $this->normalizePublicAssetUrl((string)($block['content'] ?? '')) : '';
        if ($url === '') {
            return $html;
        }

        return $this->injectBodyCssVariableToken($html, 'theme-page-hero-image', "url('" . str_replace("'", '%27', $url) . "')");
    }

    private function injectHomepageAboutVisualSource(string $html, int $pageId): string
    {
        $page = $this->pages->findById($pageId);
        if (!$page || trim((string)($page['url_path'] ?? ''), '/') !== '') {
            return $html;
        }

        $block = $this->blocks->findByKey($pageId, 'jb-about-visual__media');
        $url = $block ? $this->normalizePublicAssetUrl((string)($block['content'] ?? '')) : '';
        $sourcePattern = '~<(?:img|span)\b[^>]*\bclass=(["\'])[^"\']*\bjb-about-visual__source\b[^"\']*\1[^>]*(?:>.*?</span>|>)~is';

        if ($url === '') {
            return preg_replace($sourcePattern, '', $html) ?? $html;
        }

        $source = '<span class="jb-about-visual__source" data-src="' . htmlspecialchars($url, ENT_QUOTES) . '" hidden></span>';
        $updated = preg_replace($sourcePattern, $source, $html, 1, $count);
        if ($count > 0 && is_string($updated)) {
            return $updated;
        }

        $count = 0;
        $html = preg_replace('/(<main\b[^>]*>)/i', '$1' . $source, $html, 1, $count);
        if ($count > 0 && is_string($html)) {
            return $html;
        }

        return preg_replace('/(<body\b[^>]*>)/i', '$1' . $source, $html, 1) ?? $html;
    }

    private function injectPageHeroVideo(string $html, array $site, int $pageId): string
    {
        if ($this->hasHeroVideoElement($html)) {
            return $this->ensureHeroVideoStyle($html);
        }

        $posterUrl = $this->normalizePublicAssetUrl((string)($site['default_hero_poster_url'] ?? ''));

        $page = $this->pages->findById($pageId);
        $isHomepage = trim((string)($page['url_path'] ?? '')) === '';
        // Priorita: 1) page hero_video, 2) homepage site default video, 3) page hero_bg foto, 4) site default video.
        $videoBlock = $this->blocks->findByKey($pageId, 'hero_video');
        $pageVideo = $videoBlock ? $this->normalizePublicAssetUrl((string)($videoBlock['content'] ?? '')) : '';

        $videoHtml = '';
        if ($pageVideo !== '') {
            $videoHtml = $this->buildHeroVideoHtml($pageVideo, $posterUrl);
        } else {
            $defaultVideo = $this->normalizePublicAssetUrl((string)($site['default_hero_video_url'] ?? ''));
            if ($isHomepage && $defaultVideo !== '') {
                $videoHtml = $this->buildHeroVideoHtml($defaultVideo, $posterUrl);
            }

            $photoBlock = $this->blocks->findByKey($pageId, 'hero_bg');
            $pagePhoto = $photoBlock ? $this->normalizePublicAssetUrl((string)($photoBlock['content'] ?? '')) : '';
            if ($videoHtml === '' && $pagePhoto !== '') {
                $videoHtml = $this->buildHeroImageHtml($pagePhoto);
            }

            if ($videoHtml === '' && $defaultVideo !== '') {
                $videoHtml = $this->buildHeroVideoHtml($defaultVideo, $posterUrl);
            }
        }

        if ($videoHtml === '') {
            return $html;
        }

        $html = $this->ensureHeroVideoStyle($html);

        $count = 0;
        $html = preg_replace_callback(
            '/(<section\b(?=[^>]*\bclass=(["\'])(?=[^"\']*(?:hp-hero|hero|page-hero|pf-hero|bl-hero|kt-hero|fq-hero|bp-hero|pf-detail-hero|bp-detail-hero))[^"\']*\2)[^>]*>)/i',
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

    private function ensureHeroVideoStyle(string $html): string
    {
        if (str_contains($html, 'data-cms-hero-video')) {
            return $html;
        }

        $style = <<<'HTML'
<style data-cms-hero-video>
.hp-hero,.hero,.page-hero,.pf-hero,.bl-hero,.kt-hero,.fq-hero,.bp-hero,.pf-detail-hero,.bp-detail-hero{position:relative;overflow:hidden}
.hp-hero::before,.hero::before,.page-hero::before,.pf-hero::before,.bl-hero::before,.kt-hero::before,.fq-hero::before,.bp-hero::before,.pf-detail-hero::before,.bp-detail-hero::before{z-index:0!important}
.theme-hero-video{position:absolute;inset:0;z-index:1;width:100%;height:100%;object-fit:cover;pointer-events:none;background:#050505;background-size:cover;background-position:center}
.theme-hero-video--embed{overflow:hidden;background-size:cover;background-position:center}
.theme-hero-video--embed iframe{position:absolute;top:50%;left:50%;width:120vw;height:67.5vw;min-width:213.33vh;min-height:120vh;transform:translate(-50%,-50%);border:0;pointer-events:none}
.theme-hero-video::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(0,0,0,.62),rgba(0,0,0,.22),rgba(0,0,0,.58))}
.hp-hero>.container,.hero>.container,.page-hero>.container,.pf-hero>.container,.bl-hero>.container,.kt-hero>.container,.fq-hero>.container,.bp-hero>.container,.pf-detail-hero>.container,.bp-detail-hero>.container,.hero-content{position:relative;z-index:3}
</style>
HTML;

        if (str_contains($html, '</head>')) {
            return str_replace('</head>', "\n" . $style . "\n</head>", $html);
        }

        return $style . $html;
    }

    private function buildHeroVideoHtml(string $videoUrl, string $posterUrl = ''): string
    {
        $embedUrl = $this->buildVideoEmbedUrl($videoUrl);
        $posterStyle = $posterUrl !== '' ? ' style="background-image:url(\'' . htmlspecialchars($posterUrl, ENT_QUOTES) . '\')"' : '';
        if ($embedUrl !== '') {
            return '<div class="theme-hero-video theme-hero-video--embed" aria-hidden="true"' . $posterStyle . '><iframe src="' . htmlspecialchars($embedUrl, ENT_QUOTES) . '" title="Hero video" allow="autoplay; fullscreen; picture-in-picture" loading="eager"></iframe></div>';
        }

        return '';
    }

    private function buildHeroImageHtml(string $imageUrl): string
    {
        if ($imageUrl === '') {
            return '';
        }

        return '<div class="theme-hero-video theme-hero-video--image" aria-hidden="true" style="background-image:url(\'' . htmlspecialchars($imageUrl, ENT_QUOTES) . '\')"></div>';
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

    private function injectEditorialGoldOverrides(string $html): string
    {
        if (!str_contains($html, 'theme-editorial-gold') || str_contains($html, 'data-cms-editorial-gold-fixes')) {
            return $html;
        }

        $style = <<<'HTML'
<style data-cms-editorial-gold-fixes>
.theme-editorial-gold [data-block="reviews_cta"]{display:flex;justify-content:flex-start;margin-top:22px!important}
.theme-editorial-gold .eg-reviews__cta,
.theme-editorial-gold .hp-section-cta,
.theme-editorial-gold .hp-contact-cta{margin-top:22px!important}
.theme-editorial-gold .hp-section-cta .btn-secondary,
.theme-editorial-gold [data-block="reviews_cta"] > .btn-secondary,
.theme-editorial-gold .eg-reviews__cta .btn-secondary,
.theme-editorial-gold .hp-contact-cta .btn-secondary{
  background:rgba(5,5,5,.72)!important;
  border-color:rgba(224,184,102,.68)!important;
  color:#f7f1e8!important;
}
.theme-editorial-gold .hp-section-cta .btn-secondary:hover,
.theme-editorial-gold [data-block="reviews_cta"] > .btn-secondary:hover,
.theme-editorial-gold .eg-reviews__cta .btn-secondary:hover,
.theme-editorial-gold .hp-contact-cta .btn-secondary:hover{
  background:rgba(5,5,5,.88)!important;
  border-color:var(--eg-gold,#c6943d)!important;
  color:var(--eg-gold,#c6943d)!important;
}
</style>
HTML;

        if (str_contains($html, '</head>')) {
            return str_replace('</head>', "\n" . $style . "\n</head>", $html);
        }

        return $style . $html;
    }

    private function injectGlobalVideoOverlayStyles(string $html, array $site): string
    {
        $overlay = max(0.0, min(1.0, (float)($site['video_overlay_opacity'] ?? 0.62)));
        $soft = round($overlay * 0.35, 3);
        $overlayCss = number_format($overlay, 3, '.', '');
        $softCss = number_format($soft, 3, '.', '');

        $css = <<<CSS
:root{--cms-video-overlay-opacity:{$overlayCss};--cms-video-overlay-soft-opacity:{$softCss}}
.theme-hero-video::after,
.pf-video-wrap::after{
  content:"";
  position:absolute;
  inset:0;
  pointer-events:none;
  background:linear-gradient(90deg,rgba(0,0,0,var(--cms-video-overlay-opacity)),rgba(0,0,0,var(--cms-video-overlay-soft-opacity)),rgba(0,0,0,var(--cms-video-overlay-opacity)));
}
CSS;

        return FrontendAssetManager::injectGeneratedCss($html, $css, 'video-overlay', 'data-cms-video-overlay');
    }

    private function injectSharedFrontendAssets(string $html): string
    {
        $html = FrontendAssetManager::injectStylesheet($html, '/assets/frontend-runtime.css', 'data-cms-frontend-runtime-css');
        $html = FrontendAssetManager::injectStylesheet($html, '/assets/cookie-consent.css', 'data-cookie-consent-css');
        $html = FrontendAssetManager::injectScript($html, '/assets/frontend-runtime.js', 'data-cms-frontend-runtime-js');
        return FrontendAssetManager::injectScript($html, '/assets/cookie-consent.js', 'data-cookie-consent-js');
    }

    private function normalizeLegacyFrontendMarkup(string $html): string
    {
        $html = preg_replace(
            '~<div>\s*(<a\b(?=[^>]*\bclass=(["\'])[^"\']*\bbtn-secondary\b[^"\']*\2)[^>]*>.*?</a>)\s*</div>~is',
            '<div class="hp-section-cta">$1</div>',
            $html
        ) ?? $html;
        $html = str_replace(' style="color:inherit;text-decoration:none"', ' class="blog-title-link"', $html);
        $html = str_replace('<strong style="display:block;color:#fff;margin-bottom:8px">', '<strong class="hp-faq-question">', $html);
        $html = str_replace('<p style="color:rgba(255,255,255,.72);line-height:1.6;margin:0">', '<p class="hp-faq-answer">', $html);
        $html = str_replace('<p style="color:rgba(255,255,255,.45)">', '<p class="frontend-note">', $html);
        $html = str_replace('<p style="color:rgba(255,255,255,.45);text-align:center;padding:32px 0">', '<p class="frontend-note frontend-note--center">', $html);
        $html = str_replace(' style="display:none" tabindex="-1" autocomplete="off"', ' class="hp-honeypot" style="display:none" tabindex="-1" autocomplete="off"', $html);
        return str_replace(
            '<div style="height:100%;background:#222;display:flex;align-items:center;justify-content:center;font-size:2rem;color:rgba(255,255,255,.3)">&#9654;</div>',
            '<div class="pt-thumb__placeholder">&#9654;</div>',
            $html
        );
    }

    private function normalizeLegacyTemplateSource(string $html): string
    {
        return preg_replace(
            '~<header\s+class=(["\'])hero\1>\s*(<div\s+class=(["\'])container\3>\s*<h1\s+data-block=(["\'])hero_heading\4></h1>\s*<p\s+data-block=(["\'])hero_intro\5)(?:\s+class=(["\'])hero-sub\6)?(></p>\s*</div>)\s*</header>~i',
            '<section class="page-hero">$2 class="hero-sub"$7</section>',
            $html
        ) ?? $html;
    }

    private function externalizeBodyCssVariables(string $html): string
    {
        if (!preg_match('/<body([^>]*)>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        $bodyTag = $m[0][0];
        $bodyAttrs = $m[1][0];
        if (!preg_match_all('/\sdata-cms-css-var-([a-z0-9-]+)=(["\'])(.*?)\2/i', $bodyAttrs, $matches, PREG_SET_ORDER)) {
            return $html;
        }

        $vars = [];
        foreach ($matches as $match) {
            $vars['--' . strtolower($match[1])] = html_entity_decode($match[3], ENT_QUOTES, 'UTF-8');
        }

        $cleanAttrs = preg_replace('/\sdata-cms-css-var-[a-z0-9-]+=(["\']).*?\1/i', '', $bodyAttrs) ?? $bodyAttrs;
        $cleanTag = '<body' . $cleanAttrs . '>';
        $html = substr_replace($html, $cleanTag, $m[0][1], strlen($bodyTag));

        $cssParts = [];
        foreach ($vars as $name => $value) {
            $cssParts[] = $name . ':' . $value;
        }

        if ($cssParts === []) {
            return $html;
        }

        return FrontendAssetManager::injectGeneratedCss($html, 'body{' . implode(';', $cssParts) . ';}', 'body-vars', 'data-cms-body-vars');
    }

    private function extractInlineStyleBlocks(string $html): string
    {
        if (!preg_match_all('~<style\b[^>]*>(.*?)</style>~is', $html, $matches, PREG_SET_ORDER)) {
            return $html;
        }

        $styles = [];
        foreach ($matches as $match) {
            $css = trim($match[1]);
            if ($css === '') {
                continue;
            }
            if (
                str_contains($css, '.site-logo-img') ||
                str_contains($css, '.site-footer-rich') ||
                str_contains($css, '.theme-hero-video') ||
                str_contains($css, '.theme-editorial-gold [data-block="reviews_cta"]') ||
                str_contains($css, '.cookie-consent')
            ) {
                continue;
            }
            $styles[] = $css;
        }

        $html = preg_replace('~<style\b[^>]*>.*?</style>~is', '', $html) ?? $html;
        $styles = array_values(array_filter($styles, static fn(string $css): bool => $css !== ''));
        if ($styles === []) {
            return $html;
        }

        return FrontendAssetManager::injectGeneratedCss($html, implode("\n", $styles), 'inline-style-blocks', 'data-cms-inline-styles');
    }

    private function extractInlineBodyScripts(string $html): string
    {
        $headClosePos = stripos($html, '</head>');
        if ($headClosePos === false) {
            return $html;
        }

        $headPart = substr($html, 0, $headClosePos + 7);
        $bodyPart = substr($html, $headClosePos + 7);
        if ($bodyPart === false || !preg_match_all('~<script\b(?![^>]*\bsrc=)(?![^>]*\btype=(["\'])application/ld\+json\1)[^>]*>(.*?)</script>~is', $bodyPart, $matches, PREG_SET_ORDER)) {
            return $html;
        }

        $scripts = [];
        foreach ($matches as $match) {
            if (
                str_contains($match[2], 'window.cmsCookieConsentSettings') ||
                str_contains($match[2], 'function syncAdminBarOffset') ||
                str_contains($match[2], 'function enhanceHero()') ||
                str_contains($match[2], 'IntersectionObserver') ||
                str_contains($match[2], "document.querySelector('[data-cenik-modal]')")
            ) {
                continue;
            }
            $scripts[] = trim($match[2]);
        }

        $bodyPart = preg_replace_callback(
        '~<script\b(?![^>]*\bsrc=)(?![^>]*\btype=(["\'])application/ld\+json\1)[^>]*>(.*?)</script>~is',
        static function (array $match): string {
            $script = $match[2];
            return str_contains($script, 'window.cmsCookieConsentSettings') ? $match[0] : '';
        },
        $bodyPart
    ) ?? $bodyPart;

        $scripts = array_values(array_filter($scripts, static fn(string $js): bool => $js !== ''));
        if ($scripts === []) {
            return $headPart . $bodyPart;
        }

        return FrontendAssetManager::injectGeneratedJs($headPart . $bodyPart, implode("\n", $scripts), 'inline-body-scripts', 'data-cms-inline-body-scripts');
    }

    private function normalizeSocialType(string $type, string $url = ''): string
    {
        $value = strtolower(trim($type));
        $value = preg_replace('~[^a-z0-9]+~', '', $value) ?? '';
        $host = strtolower((string)(parse_url(trim($url), PHP_URL_HOST) ?: trim($url)));

        $map = [
            'ig' => 'instagram',
            'in' => 'instagram',
            'insta' => 'instagram',
            'instagram' => 'instagram',
            'fb' => 'facebook',
            'fa' => 'facebook',
            'facebook' => 'facebook',
            'yt' => 'youtube',
            'yo' => 'youtube',
            'youtube' => 'youtube',
            'tt' => 'tiktok',
            'ti' => 'tiktok',
            'tiktok' => 'tiktok',
            'li' => 'linkedin',
            'linkedin' => 'linkedin',
            'x' => 'twitter',
            'tw' => 'twitter',
            'twitter' => 'twitter',
            'pinterest' => 'pinterest',
            'wa' => 'whatsapp',
            'whatsapp' => 'whatsapp',
            'telegram' => 'telegram',
        ];

        if (isset($map[$value])) {
            return $map[$value];
        }

        foreach ($map as $needle => $canonical) {
            if ($needle !== '' && str_contains($host, $needle)) {
                return $canonical;
            }
        }

        if (str_contains($host, 'youtu')) {
            return 'youtube';
        }

        return $value !== '' ? $value : 'link';
    }

    private function socialLabel(string $type): string
    {
        return [
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'youtube' => 'YouTube',
            'tiktok' => 'TikTok',
            'linkedin' => 'LinkedIn',
            'twitter' => 'X',
            'pinterest' => 'Pinterest',
            'whatsapp' => 'WhatsApp',
            'telegram' => 'Telegram',
        ][$type] ?? 'Odkaz';
    }

    private function socialIconSvg(string $type): string
    {
        $icons = [
            'instagram' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="4"/><circle cx="12" cy="12" r="3.6"/><circle cx="17" cy="7" r=".8"/></svg>',
            'facebook' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M13.5 19v-5h1.9l.4-2.3h-2.3v-1.5c0-.9.3-1.6 1.5-1.6h1V6.6c-.2 0-.9-.1-1.8-.1-2.7 0-4.2 1.4-4.2 4v1.2h-1.7V14H10V19"/></svg>',
            'youtube' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="6.5" width="17" height="11" rx="3.2"/><path d="M10 9.4 15.3 12 10 14.6z"/></svg>',
            'tiktok' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.5 4c.6 1.7 1.8 2.8 3.6 3.1v2.5c-1.2 0-2.4-.4-3.5-1.1v5.1a4.4 4.4 0 1 1-4.4-4.4c.3 0 .7 0 1 .1v2.5c-.2-.1-.5-.1-.7-.1a1.9 1.9 0 1 0 1.8 1.9V4z"/></svg>',
            'linkedin' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="9" width="3" height="10"/><circle cx="5.5" cy="5.8" r="1.4"/><path d="M10 9v10"/><path d="M10 13.2c0-2.2 1.4-4.2 4-4.2 2.4 0 3.5 1.6 3.5 4.3V19"/><path d="M17.5 19v-5.1"/></svg>',
            'twitter' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5 19.5 19"/><path d="M19.5 5 4 19"/></svg>',
            'pinterest' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M10.7 18.5c.3-1.2.7-2.6 1-3.9"/><path d="M9.8 10.2c0-2.2 1.7-3.8 4-3.8 2 0 3.5 1.3 3.5 3.2 0 2.4-1 5.4-3.2 5.4-.9 0-1.6-.8-1.4-1.7.3-1 1-2.2 1-3.4 0-2-2.5-1.7-2.5.8 0 .6.1 1.1.3 1.5-.2 1-.6 2.4-1 3.6"/></svg>',
            'whatsapp' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19.7 18.1A8.9 8.9 0 1 0 6 19.6L3.5 20.5l1-2.4"/><path d="M9.2 8.8c-.2-.4-.4-.4-.7-.4h-.6c-.2 0-.5.1-.7.4-.3.3-.9.9-.9 2.1s1 2.4 1.1 2.6c.1.1 1.9 3 4.8 4.1 2.4.9 2.9.7 3.4.6.5-.1 1.6-.7 1.8-1.3.2-.6.2-1.1.2-1.2s-.2-.2-.5-.3l-1.7-.8c-.3-.1-.5-.1-.7.1l-.8 1c-.1.2-.3.2-.6.1-.3-.1-1.2-.4-2.2-1.3-.8-.7-1.4-1.6-1.6-1.9-.2-.3 0-.4.1-.6l.4-.5.3-.4c.1-.2.1-.4 0-.6z"/></svg>',
            'telegram' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 4 3.8 10.7l5.4 1.8 1.9 5.6 2.6-2.5 4.9 3.6z"/><path d="M9.2 12.5 20.2 5.2"/><path d="M11.1 18.1 12 14.2"/></svg>',
            'link' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 14 8.2 15.8a3.2 3.2 0 1 1-4.5-4.5L5.6 9.5"/><path d="M14 10 15.8 8.2a3.2 3.2 0 1 1 4.5 4.5L18.4 14.5"/><path d="M8.8 15.2 15.2 8.8"/></svg>',
        ];

        return $icons[$type] ?? $icons['link'];
    }

    private function enrichContactWithLegacyBlocks(int $siteId, array $contact): array
    {
        $hasPrimaryContact = false;
        foreach (['phone_1', 'phone_2', 'phone_3', 'email'] as $key) {
            if (trim((string)($contact[$key] ?? '')) !== '') {
                $hasPrimaryContact = true;
                break;
            }
        }

        if ($hasPrimaryContact) {
            return $contact;
        }

        $htmlChunks = [];
        foreach (['kontakt', ''] as $path) {
            $page = $this->pages->findByUrlPath($path);
            if (!$page || (int)($page['site_id'] ?? 0) !== $siteId) {
                continue;
            }
            foreach (['kontakt_info', 'contact_info', 'contact_card'] as $blockKey) {
                $block = $this->blocks->findByKey((int)$page['id'], $blockKey);
                $content = trim((string)($block['content'] ?? ''));
                if ($content !== '') {
                    $htmlChunks[] = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }
        }

        if ($htmlChunks === []) {
            return $contact;
        }

        $html = implode("\n", $htmlChunks);
        $plain = preg_replace('/\x{00A0}/u', ' ', strip_tags($html)) ?? '';

        if (trim((string)($contact['email'] ?? '')) === '' && preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $plain, $m)) {
            $contact['email'] = $m[0];
        }

        $phones = [];
        if (preg_match_all('/(?:\+?\d[\d\s().\-]{7,}\d)/u', $plain, $matches)) {
            foreach ($matches[0] as $candidate) {
                $normalized = preg_replace('/[^\d+]/', '', $candidate) ?? '';
                if (strlen(ltrim($normalized, '+')) < 9) {
                    continue;
                }
                if (!in_array($normalized, $phones, true)) {
                    $phones[] = $normalized;
                }
            }
        }

        foreach (['phone_1', 'phone_2', 'phone_3'] as $index => $key) {
            if (trim((string)($contact[$key] ?? '')) === '' && isset($phones[$index])) {
                $contact[$key] = $phones[$index];
            }
        }

        if (preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $linkMatches)) {
            $socialSlot = 1;
            foreach ($linkMatches[1] as $href) {
                $type = $this->normalizeSocialType('', $href);
                if ($type === 'link' || !preg_match('~^(?:https?:)?//~i', $href)) {
                    continue;
                }

                $alreadyPresent = false;
                for ($i = 1; $i <= 5; $i++) {
                    if (trim((string)($contact['social_' . $i . '_url'] ?? '')) === trim((string)$href)) {
                        $alreadyPresent = true;
                        break;
                    }
                }
                if ($alreadyPresent) {
                    continue;
                }

                while ($socialSlot <= 5 && trim((string)($contact['social_' . $socialSlot . '_url'] ?? '')) !== '') {
                    $socialSlot++;
                }
                if ($socialSlot > 5) {
                    break;
                }

                $contact['social_' . $socialSlot . '_type'] = $type;
                $contact['social_' . $socialSlot . '_url'] = $href;
                $socialSlot++;
            }
        }

        if (trim((string)($contact['form_email'] ?? '')) === '') {
            $contact['form_email'] = trim((string)($contact['email'] ?? '')) !== ''
                ? trim((string)($contact['email'] ?? ''))
                : self::DEFAULT_FORM_EMAIL;
        }

        return $contact;
    }

    private function buildRichFooterHtml(array $site): string
    {
        $siteId = (int)($site['id'] ?? 0);
        $contact = $siteId > 0 ? $this->enrichContactWithLegacyBlocks($siteId, (new ContactRepository())->findBySiteSafe($siteId)) : [];
        $siteName = (string)($site['name'] ?? 'RB Videos & Photos');
        $logoUrl = $this->normalizePublicAssetUrl((string)($site['logo_url'] ?? ''));
        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);

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
            $canonicalType = $this->normalizeSocialType($type, $url);
            $label = $h($this->socialLabel($canonicalType));
            $socialItems[] = '<a class="site-footer-social site-footer-social--' . $h($canonicalType) . '" href="' . $h($url) . '" target="_blank" rel="noopener" aria-label="' . $label . '" title="' . $label . '"><span class="site-footer-social__icon">' . $this->socialIconSvg($canonicalType) . '</span></a>';
        }

        $brand = $logoUrl !== ''
            ? '<img class="site-footer-brand__logo" src="' . $h($logoUrl) . '" alt="' . $h($siteName) . '">'
            : '<span class="site-footer-brand__mark">' . $h(mb_substr($siteName, 0, 2)) . '</span>';

        return '<style data-cms-rich-footer>'
            . 'html,body{min-height:100%}body{min-height:100vh;display:flex;flex-direction:column}.theme-shell{width:100%;flex:1 0 auto;display:flex;flex-direction:column}.theme-shell,.theme-shell__frame{min-height:100vh}.theme-shell__frame{display:flex;flex-direction:column;flex:1 0 auto}.theme-main,main,#page-content{flex:1 0 auto}footer,.theme-footer,.site-footer{margin-top:auto}'
            . 'footer .site-footer-rich,.theme-footer .site-footer-rich,.site-footer .site-footer-rich{width:100%;max-width:1180px;margin:0 auto;padding:0;display:grid;grid-template-columns:minmax(220px,.85fr) minmax(260px,1fr) minmax(220px,.75fr);gap:34px;align-items:start;text-align:left}'
            . '.site-footer-brand{display:flex;flex-direction:column;gap:12px;align-items:flex-start}.site-footer-brand__logo{max-height:52px;max-width:260px;width:auto;height:auto;object-fit:contain}.site-footer-brand__mark{display:inline-grid;place-items:center;width:54px;height:54px;border:1px solid rgba(198,148,61,.45);color:#c6943d;font-family:Georgia,serif;font-weight:800;font-size:20px;letter-spacing:.08em;text-transform:uppercase}.site-footer-brand__name{color:#f3efe7;font-family:Georgia,serif;font-size:22px;line-height:1.1}.site-footer-brand__note{color:rgba(255,255,255,.52);font-size:13px;line-height:1.65}'
            . '.site-footer-contact{display:grid;gap:12px}.site-footer-title{margin:0 0 12px;color:#c6943d;font-size:11px;font-weight:900;letter-spacing:.18em;text-transform:uppercase}.site-footer-contact__item{display:grid;gap:4px;color:#f3efe7;text-decoration:none}.site-footer-contact__item span{color:rgba(255,255,255,.44);font-size:11px;text-transform:uppercase;letter-spacing:.12em}.site-footer-contact__item strong,.site-footer-contact__item a{color:#f3efe7;font-size:14px;line-height:1.5;font-weight:700}'
            . '.site-footer-socials{display:flex;flex-direction:column;gap:14px;align-items:flex-start}.site-footer-socials__list{display:flex;flex-wrap:wrap;gap:10px}.site-footer-social{--social:#d4b06f;display:inline-flex;align-items:center;justify-content:center;width:50px;height:40px;padding:0;border:1px solid rgba(212,176,111,.24);border-radius:0;background:transparent;color:#d8d1c6;text-decoration:none;transition:transform .2s,border-color .2s,color .2s,background-color .2s}.site-footer-social:hover{transform:translateY(-1px);border-color:rgba(212,176,111,.62);background:rgba(212,176,111,.06);color:#f2e7d5}.site-footer-social__icon{display:grid;place-items:center;line-height:1;transition:color .2s,transform .2s}.site-footer-social:hover .site-footer-social__icon{transform:scale(1.06)}.site-footer-social__icon svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.75;stroke-linecap:round;stroke-linejoin:round}.site-footer-bottom{grid-column:1/-1;margin-top:4px;padding-top:20px;border-top:1px solid rgba(255,255,255,.09);color:rgba(255,255,255,.36);font-size:12px;text-align:left}'
            . '@media(max-width:820px){footer .site-footer-rich,.theme-footer .site-footer-rich,.site-footer .site-footer-rich{grid-template-columns:1fr;gap:24px}.site-footer-bottom{text-align:left}}'
            . '</style>'
            . '<div class="site-footer-rich">'
            . '<div class="site-footer-brand">' . $brand . '<div class="site-footer-brand__name">' . $h($siteName) . '</div><div class="site-footer-brand__note">Svatebni fotografie a video. Olomouc, Morava a cela CR.</div></div>'
            . '<div class="site-footer-contact"><div class="site-footer-title">Kontakt</div>' . ($contactItems !== [] ? implode('', $contactItems) : '<div class="site-footer-contact__item"><span>Kontakt</span><strong>Doplnte kontaktni udaje v administraci.</strong></div>') . '</div>'
            . '<div class="site-footer-socials"><div class="site-footer-title">Socialni site</div><div class="site-footer-socials__list">' . ($socialItems !== [] ? implode('', $socialItems) : '<span class="site-footer-brand__note">Doplnte socialni site v kontaktu.</span>') . '</div></div>'
            . '<div class="site-footer-bottom">&copy; ' . date('Y') . ' ' . $h($siteName) . ' · <a href="/podminky-uziti">Podmínky užití</a></div>'
            . '</div>';
    }

    private function injectBodyCssVariableToken(string $html, string $name, string $value): string
    {
        if (!preg_match('/<body([^>]*)>/i', $html, $m)) {
            return $html;
        }

        $attrs = $m[1];
        $attrName = 'data-cms-css-var-' . strtolower($name);
        $attrValue = htmlspecialchars($value, ENT_QUOTES);
        if (preg_match('/\s' . preg_quote($attrName, '/') . '=(["\']).*?\1/i', $attrs)) {
            $attrs = preg_replace('/\s' . preg_quote($attrName, '/') . '=(["\']).*?\1/i', ' ' . $attrName . '="' . $attrValue . '"', $attrs, 1) ?? $attrs;
        } else {
            $attrs .= ' ' . $attrName . '="' . $attrValue . '"';
        }

        return preg_replace('/<body([^>]*)>/i', '<body' . $attrs . '>', $html, 1) ?? $html;
    }

    private function injectBodyPageKey(string $html, string $pageKey): string
    {
        if ($pageKey === '' || !preg_match('/<body([^>]*)>/i', $html, $m)) {
            return $html;
        }

        $attrs = $m[1];
        $attrValue = htmlspecialchars($pageKey, ENT_QUOTES);
        if (preg_match('/\sdata-cms-page-key=(["\']).*?\1/i', $attrs)) {
            $attrs = preg_replace('/\sdata-cms-page-key=(["\']).*?\1/i', ' data-cms-page-key="' . $attrValue . '"', $attrs, 1) ?? $attrs;
        } else {
            $attrs .= ' data-cms-page-key="' . $attrValue . '"';
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

    private function hasHeroVideoElement(string $html): bool
    {
        return (bool) preg_match('/<[^>]+\bclass=(["\'])[^"\']*\btheme-hero-video\b[^"\']*\1/i', $html);
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
