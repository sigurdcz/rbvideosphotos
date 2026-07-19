<?php

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use App\Core\Version;
use App\Repositories\BlogRepository;
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
use App\Services\Render\PageComposer;
use App\Services\Render\PublishPageChrome;
use App\Services\ThemeTemplateRegistry;
use App\Support\FrontendAssetManager;

class PublishService implements PublishPageChrome
{
    private const DEFAULT_FORM_EMAIL = ContactRepository::DEFAULT_FORM_EMAIL;
    private PageRenderer $renderer;
    private HomepageContentService $homepageContent;
    private SeoRenderer $seoRenderer;
    private SitemapGenerator $sitemap;
    private RobotsGenerator $robots;
    private SecurityTxtService $securityTxt;
    private PageRepository $pages;
    private TemplateRepository $templates;
    private BlockRepository $blocks;
    private SeoRepository $seo;
    private SiteRepository $sites;
    private MediaRepository $media;
    private BlogRepository $blog;
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
        $this->securityTxt = new SecurityTxtService();
        $this->pages      = new PageRepository();
        $this->templates  = new TemplateRepository();
        $this->blocks     = new BlockRepository();
        $this->seo        = new SeoRepository();
        $this->sites      = new SiteRepository();
        $this->media      = new MediaRepository();
        $this->blog       = new BlogRepository();
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

        $theme = $this->themes->resolveForSite($site);

        // Render
        $archetypeCss = '';
        [$archetypeHtml, $archetypeCss] = $this->renderArchetypeContent($pageKey, $page, $blocks);
        // Phase 5: generic CMS pages (and the homepage) flow their legacy
        // PageRenderer fragment through the generic `page` archetype — the same
        // byte-for-byte passthrough wrapper the live path uses in
        // tryServeDatabasePage(). Dedicated archetypes (faq, sluzby, …) above take
        // precedence via $archetypeHtml.
        // Phase 6 / manifest v2: a theme that opts into the PHTML views cascade
        // (declares views_dir) may override a generic page's content view via
        // overrides.<pageKey>.view or <views_dir>/pages/<pageKey>.phtml. Themes
        // without manifest v2 (incl. the active theme) resolve to null → the core
        // wrapper below, unchanged.
        if ($archetypeHtml !== null) {
            $html = $archetypeHtml;
        } else {
            $fragment = $this->renderer->render($this->normalizeLegacyTemplateSource((string)($template['html_source'] ?? '')), $blocks, $mediaMap);
            $contentView = $this->themes->pageContentView($theme, $pageKey);
            $html = $contentView !== null
                ? \App\View\ViewFactory::forTheme($contentView['themeKey'], [], $contentView['viewsDir'])
                    ->render($contentView['view'], ['fragment' => $fragment, 'pageKey' => $pageKey])
                : \App\View\ViewFactory::forTheme(null)->render('archetypes/page', ['fragment' => $fragment]);
        }
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
            $themeKeyForLayout = (string)($theme['key'] ?? '');
            if (\App\Services\Render\ThemeLayoutRenderer::hasPhtmlLayout($themeKeyForLayout)) {
                // Phase 3: active theme renders its layout chrome through a PHTML
                // view; byte-equivalent to applyLayout() of the legacy layout.html.
                $html = \App\Services\Render\ThemeLayoutRenderer::render($themeKeyForLayout, $html, $site['name'] ?? '', $themeNav);
            } elseif ($themeLayout !== '') {
                $html = $this->renderer->applyLayout($themeLayout, $html, $site['name'] ?? '', $themeNav);
            }
            $html = $this->renderer->stripInlineStyles($html);
            $html = $this->themes->injectBodyClass($html, $themeKeyForLayout);
            $css = ($template['css_source'] ?? '') . "\n" . $sharedCss . "\n" . $themeCss;
            $js  = (($theme['key'] ?? '') === 'bezova-lens-tale')
                ? $themeJs . "\n" . ($template['js_source'] ?? '')
                : ($template['js_source'] ?? '') . "\n" . $themeJs;
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
        if ($archetypeCss !== '') {
            // Archetype pages (faq, …) carry their own page-specific CSS that used
            // to live in a public/index.php heredoc; it never was part of
            // $template['css_source'], so it is layered on top here rather than
            // folded into the branch-specific $css formulas above.
            $css = $archetypeCss . "\n" . $css;
        }
        $seoHead     = $this->seoRenderer->renderHead($seo, $site, $pageUrl, $ogImageUrl, $twImageUrl);
        $bodyScripts = $this->seoRenderer->renderBodyScripts($site);
        $html = PageComposer::compose([
            'mode'        => PageComposer::MODE_PUBLISH,
            'chrome'      => $this,
            'renderer'    => $this->renderer,
            'html'        => $html,
            'css'         => $css,
            'js'          => $js,
            'seoHead'     => $seoHead,
            'bodyScripts' => $bodyScripts,
            'blocks'      => $blocks,
            'site'        => $site,
            'page'        => $page,
            'isHome'      => $isHome,
            'pageKey'     => $pageKey,
        ]);

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
        $sitemapItems = array_merge(
            $pages,
            $this->builtInPublicRouteSitemapItems($pages),
            $this->blogPostsAsSitemapItems($siteId)
        );

        $seoCfg  = Config::get('seo');
        $sitemapXml = $this->sitemap->generate($sitemapItems, $site['domain'], $seoCfg['sitemap_changefreq'], $seoCfg['sitemap_priority']);
        file_put_contents($this->publishedDir . '/sitemap.xml', $sitemapXml);

        $robotsTxt = $this->robots->generate($site['domain']);
        file_put_contents($this->publishedDir . '/robots.txt', $robotsTxt);

        $llmsTxt = (new LlmsTxtGenerator())->generate($site, $pages);
        file_put_contents($this->publishedDir . '/llms.txt', $llmsTxt);

        $this->generateSecurityTxt($siteId);
    }

    private function builtInPublicRouteSitemapItems(array $pages): array
    {
        $existingPaths = [];
        foreach ($pages as $page) {
            $existingPaths[] = trim((string)($page['url_path'] ?? ''), '/');
        }

        $items = [];
        foreach (['kontakt', 'nase-prace'] as $path) {
            if (in_array($path, $existingPaths, true)) {
                continue;
            }

            $items[] = [
                'url_path' => $path,
                'published_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }

        return $items;
    }

    private function blogPostsAsSitemapItems(int $siteId): array
    {
        $items = [];
        foreach ($this->blog->allPublished($siteId) as $post) {
            $slug = trim((string)($post['slug'] ?? ''), '/');
            if ($slug === '') {
                continue;
            }

            $items[] = [
                'url_path' => $slug,
                'published_at' => $post['published_at'] ?? null,
                'updated_at' => $post['updated_at'] ?? null,
            ];
        }

        return $items;
    }

    public function generateHtaccess(int $siteId): void
    {
        $redirects = $this->redirects->allBySite($siteId);
        $lines = ["Options -Indexes", "RewriteEngine On", "ErrorDocument 404 /404.html", ""];
        $site = $this->sites->findById($siteId);
        if ($site) {
            $payload = $this->securityTxt->publicPayload($site);
            if (!empty($payload['enabled']) && !empty($payload['valid'])) {
                $lines[] = 'Redirect 301 /security.txt /.well-known/security.txt';
            }
        }
        foreach ($redirects as $r) {
            $lines[] = "Redirect {$r['status_code']} {$r['from_path']} {$r['to_path']}";
        }
        $lines[] = "";
        file_put_contents($this->publishedDir . '/.htaccess', implode("\n", $lines));
    }

    public function generateSecurityTxt(int $siteId): void
    {
        $site = $this->sites->findById($siteId);
        if (!$site) {
            return;
        }

        $payload = $this->securityTxt->publicPayload($site);
        $directory = $this->publishedDir . '/.well-known';
        $target = $directory . '/security.txt';
        $legacyFile = $this->publishedDir . '/security.txt';

        if (empty($payload['enabled']) || empty($payload['valid'])) {
            $this->removeSecurityTxtFile($target);
            $this->removeSecurityTxtFile($legacyFile);
            return;
        }

        $this->ensureDir($directory);
        if (file_put_contents($target, (string) $payload['content'], LOCK_EX) === false) {
            throw new \RuntimeException('Nepodarilo se vygenerovat .well-known/security.txt.');
        }
        $this->removeSecurityTxtFile($legacyFile);
    }

    private function removeSecurityTxtFile(string $path): void
    {
        if (is_file($path) && !@unlink($path)) {
            throw new \RuntimeException('Nepodarilo se odstranit zastaraly security.txt vystup: ' . $path);
        }
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

    /**
     * Phase 4 of the templating refactor: pages that have their own PHTML
     * archetype (app/Views/archetypes/<key>.phtml) render their page content
     * through the same ViewModel + ViewFactory call the live route uses,
     * instead of the generic $this->renderer->render(html_source, blocks, …)
     * DOM-block-injection path. This is what makes live and publish share one
     * source of truth for these pages (previously the archetype's content
     * logic lived only in public/index.php, unreachable from PublishService).
     *
     * @return array{0: ?string, 1: string} [html fragment or null when $pageKey
     *         is not an archetype page, page-specific CSS to layer on top]
     */
    private function renderArchetypeContent(string $pageKey, array $page, array $blocks): array
    {
        if ($pageKey === 'faq') {
            $siteId = (int)($page['site_id'] ?? 0);
            $faqData = \App\ViewModels\FaqPageData::forSite($siteId);
            [$titleKey, $title, $titleType] = \App\Services\Render\HomepageSectionRenderer::firstBlockValue(
                $blocks,
                ['hero_h1', 'hero_heading'],
                'Časté dotazy'
            );
            [$subKey, $sub, $subType] = \App\Services\Render\HomepageSectionRenderer::firstBlockValue(
                $blocks,
                ['hero_sub', 'hero_intro'],
                'Odpovědi na nejčastější dotazy.'
            );

            $html = \App\View\ViewFactory::forTheme(null)->render('archetypes/faq', [
                'hero' => [
                    'title' => $title,
                    'sub' => $sub,
                    'titleKey' => $titleKey,
                    'titleType' => $titleType,
                    'subKey' => $subKey,
                    'subType' => $subType,
                ],
                'items' => $faqData['items'],
            ]);
            $css = rtrim((string)file_get_contents(\App\View\ViewFactory::base() . '/app/Views/assets/faq.css'), "\n");

            return [$html, $css];
        }

        if ($pageKey === 'sluzby') {
            $siteId = (int)($page['site_id'] ?? 0);
            $sluzbyData = \App\ViewModels\SluzbyPageData::forSite($siteId);
            [$titleKey, $title, $titleType] = \App\Services\Render\HomepageSectionRenderer::firstBlockValue(
                $blocks,
                ['hero_h1', 'hero_heading'],
                'Naše služby'
            );
            [$subKey, $sub, $subType] = \App\Services\Render\HomepageSectionRenderer::firstBlockValue(
                $blocks,
                ['hero_sub', 'hero_intro'],
                'Od svateb přes portréty až po firemní vizuál. Každou zakázku stavíme na emocích, detailu a profesionálním výsledku.'
            );

            $html = \App\View\ViewFactory::forTheme(null)->render('archetypes/sluzby', [
                'hero' => [
                    'title' => $title,
                    'sub' => $sub,
                    'titleKey' => $titleKey,
                    'titleType' => $titleType,
                    'subKey' => $subKey,
                    'subType' => $subType,
                ],
                'items' => $sluzbyData['items'],
                'heroImage' => $sluzbyData['heroImage'],
            ]);
            $css = rtrim((string)file_get_contents(\App\View\ViewFactory::base() . '/app/Views/assets/sluzby.css'), "\n");

            return [$html, $css];
        }

        if ($pageKey === 'blog') {
            $siteId = (int)($page['site_id'] ?? 0);
            $blogData = \App\ViewModels\BlogListingData::forSite($siteId);
            [$titleKey, $title, $titleType] = \App\Services\Render\HomepageSectionRenderer::firstBlockValue(
                $blocks,
                ['hero_h1', 'hero_heading'],
                'Blog & Inspirace'
            );
            [$subKey, $sub, $subType] = \App\Services\Render\HomepageSectionRenderer::firstBlockValue(
                $blocks,
                ['hero_sub', 'hero_intro'],
                'Tipy a rady od profesionálního fotografa z Olomouce.'
            );

            $html = \App\View\ViewFactory::forTheme(null)->render('archetypes/blog-listing', [
                'hero' => [
                    'title' => $title,
                    'sub' => $sub,
                    'titleKey' => $titleKey,
                    'titleType' => $titleType,
                    'subKey' => $subKey,
                    'subType' => $subType,
                ],
                'posts' => $blogData['posts'],
            ]);
            $css = rtrim((string)file_get_contents(\App\View\ViewFactory::base() . '/app/Views/assets/blog-listing.css'), "\n");

            return [$html, $css];
        }

        if ($pageKey === 'nase-prace') {
            $siteId = (int)($page['site_id'] ?? 0);
            $portfolioData = \App\ViewModels\PortfolioListingData::forSite($siteId);
            [$titleKey, $title, $titleType] = \App\Services\Render\HomepageSectionRenderer::firstBlockValue(
                $blocks,
                ['hero_h1', 'hero_heading'],
                'Naše práce'
            );
            [$subKey, $sub, $subType] = \App\Services\Render\HomepageSectionRenderer::firstBlockValue(
                $blocks,
                ['hero_sub', 'hero_intro'],
                'Ukázka videí a galerií z naší práce.'
            );

            $html = \App\View\ViewFactory::forTheme(null)->render('archetypes/portfolio-listing', [
                'hero' => [
                    'title' => $title,
                    'sub' => $sub,
                    'titleKey' => $titleKey,
                    'titleType' => $titleType,
                    'subKey' => $subKey,
                    'subType' => $subType,
                ],
                'entries' => $portfolioData['entries'],
            ]);
            $css = rtrim((string)file_get_contents(\App\View\ViewFactory::base() . '/app/Views/assets/portfolio-listing.css'), "\n");

            return [$html, $css];
        }

        return [null, ''];
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

    public function injectPageHeroImage(string $html, int $pageId, array $site = []): string
    {
        $block = $this->blocks->findByKey($pageId, 'hero_bg');
        $url = $block ? $this->normalizePublicAssetUrl((string)($block['content'] ?? '')) : '';
        if ($url === '') {
            $url = $this->normalizePublicAssetUrl((string)($site['default_hero_poster_url'] ?? ''));
        }
        if ($url === '') {
            return $html;
        }

        return $this->injectBodyCssVariableToken($html, 'theme-page-hero-image', "url('" . str_replace("'", '%27', $url) . "')");
    }

    private function injectHomepageAboutVisualSource(string $html, int $pageId): string
    {
        $page = $this->pages->findById($pageId);
        if (!$page) {
            return $html;
        }
        $isHome = trim((string)($page['url_path'] ?? ''), '/') === '';
        return \App\Services\Render\HomepageSectionRenderer::injectHomepageAboutVisualSource($html, $pageId, (int)($page['site_id'] ?? 0), $isHome);
    }

    private function blockContent(array $blocks, string $key): string
    {
        return \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, $key);
    }

    private function injectHomepageMissingSections(string $html, array $blocks, bool $isHome): string
    {
        return \App\Services\Render\HomepageSectionRenderer::injectHomepageMissingSections($html, $blocks, $isHome);
    }

    private function injectHomepageThemeDataSources(string $html, array $blocks, bool $isHome): string
    {
        return \App\Services\Render\HomepageSectionRenderer::injectHomepageThemeDataSources($html, $blocks, $isHome);
    }

    public function injectPageHeroVideo(string $html, array $site, int $pageId): string
    {
        if ($this->hasHeroVideoElement($html)) {
            return $this->ensureHeroVideoStyle($html);
        }

        $posterUrl = $this->normalizePublicAssetUrl((string)($site['default_hero_poster_url'] ?? ''));

        $page = $this->pages->findById($pageId);
        $isHomepage = trim((string)($page['url_path'] ?? '')) === '';
        // Priorita: 1) page hero_video, 2) homepage site default video, 3) page hero_bg foto, 4) site default poster foto, 5) site default video.
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

            if ($videoHtml === '' && $posterUrl !== '') {
                $videoHtml = $this->buildHeroImageHtml($posterUrl);
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
        return \App\Services\Render\HeroMediaRenderer::ensureHeroVideoStyle($html);
    }

    private function buildHeroVideoHtml(string $videoUrl, string $posterUrl = ''): string
    {
        return \App\Services\Render\HeroMediaRenderer::buildHeroVideoHtml($videoUrl, $posterUrl);
    }

    private function buildHeroImageHtml(string $imageUrl): string
    {
        return \App\Services\Render\HeroMediaRenderer::buildHeroImageHtml($imageUrl);
    }

    private function buildVideoEmbedUrl(string $videoUrl): string
    {
        return \App\Services\Render\HeroMediaRenderer::buildVideoEmbedUrl($videoUrl);
    }

    public function injectSiteLogo(string $html, array $site): string
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
        return \App\Services\Render\FooterRenderer::injectFooterSocials($html, (int)($site['id'] ?? 0));
    }

    public function injectGlobalVideoOverlayStyles(string $html, array $site): string
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
        return \App\Services\Render\MarkupNormalizer::injectSharedFrontendAssets($html);
    }

    private function normalizeLegacyFrontendMarkup(string $html): string
    {
        return \App\Services\Render\MarkupNormalizer::normalizeLegacyFrontendMarkup($html);
    }

    private function normalizeLegacyTemplateSource(string $html): string
    {
        return \App\Services\Render\MarkupNormalizer::normalizeLegacyTemplateSource($html);
    }

    private function externalizeBodyCssVariables(string $html): string
    {
        return \App\Services\Render\MarkupNormalizer::externalizeBodyCssVariables($html);
    }

    private function extractInlineStyleBlocks(string $html): string
    {
        return \App\Services\Render\MarkupNormalizer::extractInlineStyleBlocks($html);
    }

    private function extractInlineBodyScripts(string $html): string
    {
        return \App\Services\Render\MarkupNormalizer::extractInlineBodyScripts($html);
    }

    private function normalizeSocialType(string $type, string $url = ''): string
    {
        return \App\Services\Render\SocialLinkRenderer::normalizeSocialType($type, $url);
    }

    private function socialLabel(string $type): string
    {
        return \App\Services\Render\SocialLinkRenderer::socialLabel($type);
    }

    private function socialIconSvg(string $type): string
    {
        return \App\Services\Render\SocialLinkRenderer::socialIconSvg($type);
    }

    private function normalizePhoneForHref(string $phone): string
    {
        return \App\Services\Render\ContactNormalizer::normalizePhoneForHref($phone);
    }

    private function formatPhoneForDisplay(string $phone): string
    {
        return \App\Services\Render\ContactNormalizer::formatPhoneForDisplay($phone);
    }

    private function phoneDedupKey(string $phone): string
    {
        return \App\Services\Render\ContactNormalizer::phoneDedupKey($phone);
    }

    private function extractPhoneNumbersFromText(string $plain): array
    {
        return \App\Services\Render\ContactNormalizer::extractPhoneNumbersFromText($plain);
    }

    private function ensureRbDemoContactAddress(array $contact): array
    {
        return \App\Services\Render\ContactNormalizer::ensureRbDemoContactAddress($contact);
    }

    private function enrichContactWithLegacyBlocks(int $siteId, array $contact): array
    {
        return \App\Services\Render\ContactNormalizer::enrichContactWithLegacyBlocks($siteId, $contact);
    }

    private function buildRichFooterHtml(array $site): string
    {
        return \App\Services\Render\FooterRenderer::buildRichFooterHtml((int)($site['id'] ?? 0));
    }

    private function injectBodyCssVariableToken(string $html, string $name, string $value): string
    {
        return \App\Services\Render\MarkupNormalizer::injectBodyCssVariableToken($html, $name, $value);
    }

    private function injectBodyPageKey(string $html, string $pageKey): string
    {
        return \App\Services\Render\MarkupNormalizer::injectBodyPageKey($html, $pageKey);
    }

    private function normalizePublicAssetUrl(string $value): string
    {
        return \App\Services\Render\MediaUrlResolver::normalizePublicAssetUrl($value);
    }

    private function resolveDemoMediaUrlByFilename(int $siteId, string $filename): string
    {
        return \App\Services\Render\MediaUrlResolver::resolveDemoMediaUrlByFilename($siteId, $filename);
    }

    private function hasHeroVideoElement(string $html): bool
    {
        return \App\Services\Render\HeroMediaRenderer::hasHeroVideoElement($html);
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
