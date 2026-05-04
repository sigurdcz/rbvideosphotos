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

class PublishService
{
    private PageRenderer $renderer;
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

    private string $publishedDir;

    public function __construct()
    {
        $this->renderer   = new PageRenderer();
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
        $seo      = $this->seo->findByPage($pageId) ?? [];
        $mediaMap = $this->buildMediaMap($blocks, $site);

        // Build page URL
        $pageUrl = rtrim($site['domain'], '/') . '/' . ltrim($page['url_path'], '/');

        // Resolve OG/Twitter image URLs
        $ogImageUrl = $this->resolveImageUrl($seo['og_image_id'] ?? null, $site);
        $twImageUrl = $this->resolveImageUrl($seo['twitter_image_id'] ?? null, $site);

        // Render
        $html = $this->renderer->render($template['html_source'], $blocks, $mediaMap);
        $html = $this->renderer->injectAssets($html, $template['css_source'] ?? '', $template['js_source'] ?? '');

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
        $lines = ["Options -Indexes", "RewriteEngine On", ""];
        foreach ($redirects as $r) {
            $lines[] = "Redirect {$r['status_code']} {$r['from_path']} {$r['to_path']}";
        }
        $lines[] = "";
        file_put_contents($this->publishedDir . '/.htaccess', implode("\n", $lines));
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
