<?php
/*
 * Public front-end entry point.
 *
 * This project now serves page content directly from MySQL/MariaDB.
 * Static files under storage/published are only used for explicit assets
 * such as sitemap.xml, robots.txt, favicon files or generated exports.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Domain\PricingPackageCatalog;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Runtime;
use App\Core\Version;
use App\Repositories\BlockRepository;
use App\Repositories\BlogRepository;
use App\Repositories\ContactRepository;
use App\Repositories\FaqRepository;
use App\Repositories\MediaRepository;
use App\Repositories\PageRepository;
use App\Repositories\PortfolioRepository;
use App\Repositories\RedirectRepository;
use App\Repositories\SeoRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\SiteRepository;
use App\Repositories\TemplateRepository;
use App\Repositories\VisitAnalyticsRepository;
use App\Services\HomepageContentService;
use App\Services\PageRenderer;
use App\Services\SeoRenderer;
use App\Services\SecurityTxtHttpResponder;
use App\Services\SecurityTxtService;
use App\Services\ThemeTemplateRegistry;
use App\Services\VisitAnalyticsService;
use App\Services\HealthCheckHttpResponder;
use App\Services\PhpRuntimeInfoHttpResponder;
use App\Security\RequestThrottle;
use App\Support\FrontendAssetManager;
use App\View\ViewFactory;
use App\ViewModels\BlogListingData;
use App\ViewModels\BlogPostData;
use App\ViewModels\FaqPageData;
use App\ViewModels\KontaktPageData;
use App\ViewModels\PortfolioDetailData;
use App\ViewModels\PortfolioListingData;
use App\ViewModels\SluzbyPageData;

Env::load(dirname(__DIR__) . '/.env');

date_default_timezone_set(Config::get('app', 'timezone', 'Europe/Prague'));
Runtime::boot('public');
Runtime::startRequest();

function tryServeSecurityTxt(string $uri): bool
{
    if (!in_array($uri, ['/.well-known/security.txt', '/security.txt'], true)) {
        return false;
    }

    $site = (new SiteRepository())->findFirst();
    $payload = $site
        ? (new SecurityTxtService())->publicPayload($site)
        : ['enabled' => false, 'valid' => false, 'status' => 'site_missing'];
    $requestHeaders = [
        'If-None-Match' => (string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''),
        'If-Modified-Since' => (string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''),
    ];
    $response = (new SecurityTxtHttpResponder())->build(
        $uri,
        (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
        $payload,
        $requestHeaders
    );

    http_response_code((int) $response['status']);
    foreach ($response['headers'] as $name => $value) {
        if ($value !== '') {
            header($name . ': ' . $value);
        }
    }
    echo (string) $response['body'];
    return true;
}

$earlyUri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

// Public machine-readable liveness/readiness endpoint. It runs before sessions,
// visit analytics and page routing, so monitoring never creates a cookie or a
// site_visits row and the response cannot be mistaken for a CMS page.
if ((new HealthCheckHttpResponder())->serve((string) $earlyUri)) {
    exit;
}
// Restricted runtime diagnostics. Runtime::sanitizeUri() masks token query
// values in application traffic/error logs before this early route is served.
if ((new PhpRuntimeInfoHttpResponder())->serve((string) $earlyUri)) {
    exit;
}
$earlyUri = rtrim((string) $earlyUri, '/') ?: '/';
if (tryServeSecurityTxt($earlyUri)) {
    exit;
}

function tryServePublicAssetWithoutSession(string $uri): bool
{
    $isMedia = str_starts_with($uri, '/media/');
    $isAsset = str_starts_with($uri, '/assets/');
    if (!$isMedia && !$isAsset) return false;

    $base = realpath($isMedia ? dirname(__DIR__) . '/storage/media' : __DIR__ . '/assets');
    $relative = ltrim(substr($uri, strlen($isMedia ? '/media/' : '/assets/')), '/');
    $file = realpath(($isMedia ? dirname(__DIR__) . '/storage/media/' : __DIR__ . '/assets/') . $relative);
    if ($base === false || $file === false || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) {
        return false;
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'mp4', 'm4v' => 'video/mp4',
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
        'ogv' => 'video/ogg',
        default => 'application/octet-stream',
    };
    $mtime = (int)(filemtime($file) ?: 0);
    $size = (int)(filesize($file) ?: 0);
    $etag = '"' . hash('sha256', $mtime . ':' . $size . ':' . basename($file)) . '"';

    header_remove('Set-Cookie');
    header_remove('Pragma');
    header_remove('Expires');
    header('Content-Type: ' . $mime);
    // Fonts are versioned by filename, so they can be cached like a hashed asset. Without
    // this they expired after an hour, and a font that still needs revalidating counts as
    // available to font-display:optional only if the conditional request finishes inside
    // its ~100ms window, which over a real network it usually does not - so a visitor
    // returning later usually saw the fallback face no matter how early it was preloaded.
    // The consequence of caching by name: a changed face needs a NEW filename.
    $isHashedAsset = (bool)preg_match('~^/assets/generated/[a-z0-9-]+-[a-f0-9]{16}\.(?:css|js)$~i', $uri)
        || (bool)preg_match('~^/assets/fonts/[a-z0-9-]+\.woff2$~i', $uri);
    header('Cache-Control: public, max-age=' . (($isMedia || $isHashedAsset) ? '31536000, immutable' : '3600, must-revalidate'));
    header('ETag: ' . $etag);
    if ($mtime > 0) header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

    $ifNoneMatch = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    if ($ifNoneMatch !== '' && $ifNoneMatch === $etag) {
        http_response_code(304);
        return true;
    }

    // Byte ranges: browsers fetch video in pieces, and iOS Safari refuses to play a video
    // from a server that cannot answer a Range request. Without this the whole file was
    // sent every time, nothing could start before it had fully arrived, and the page
    // 'load' event waited for it.
    header('Accept-Ranges: bytes');
    $start = 0;
    $end = max(0, $size - 1);
    $partial = false;
    $rangeHeader = trim((string)($_SERVER['HTTP_RANGE'] ?? ''));
    if ($rangeHeader !== '' && $size > 0 && preg_match('~^bytes=(\d*)-(\d*)$~', $rangeHeader, $rangeMatch)
        && ($rangeMatch[1] !== '' || $rangeMatch[2] !== '')) {
        // If-Range carries the validator the client got earlier: our ETag, or (Safari does this
        // when a proxy turned the ETag weak) the Last-Modified date. Answering a date we do not
        // recognise with the whole file would break video playback and seeking on iOS.
        $ifRange = trim((string)($_SERVER['HTTP_IF_RANGE'] ?? ''));
        $lastModifiedHttp = $mtime > 0 ? gmdate('D, d M Y H:i:s', $mtime) . ' GMT' : '';
        if ($ifRange === '' || $ifRange === $etag || ($lastModifiedHttp !== '' && $ifRange === $lastModifiedHttp)) {
            if ($rangeMatch[1] === '') {
                // "bytes=-N": the last N bytes.
                $start = max(0, $size - (int)$rangeMatch[2]);
            } else {
                $start = (int)$rangeMatch[1];
                if ($rangeMatch[2] !== '') $end = min($end, (int)$rangeMatch[2]);
            }
            if ($start > $end || $start >= $size || ($rangeMatch[1] === '' && (int)$rangeMatch[2] <= 0)) {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                return true;
            }
            $partial = true;
        }
    }
    if ($partial) {
        http_response_code(206);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    }
    $length = $size > 0 ? $end - $start + 1 : 0;
    header('Content-Length: ' . $length);
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'HEAD') return true;

    if (!$partial && $size <= 4 * 1048576) {
        readfile($file);
        return true;
    }
    // Large or partial: stream in chunks, so a video never sits in memory and a visitor who
    // leaves stops the transfer.
    @set_time_limit(0);
    while (ob_get_level() > 0) @ob_end_clean();
    $handle = fopen($file, 'rb');
    if ($handle === false) return true;
    fseek($handle, $start);
    $left = $length;
    while ($left > 0 && !connection_aborted()) {
        $chunk = fread($handle, (int)min(262144, $left));
        if ($chunk === false || $chunk === '') break;
        echo $chunk;
        $left -= strlen($chunk);
        flush();
    }
    fclose($handle);
    return true;
}

if (tryServePublicAssetWithoutSession($earlyUri)) {
    exit;
}

/**
 * POST /api/visit - first-party visit beacon (public/assets/visit-analytics.js).
 *
 * Runs before Auth::start() so it never creates a session cookie, and outside
 * /api/v1/* so it needs no API key. Always answers without a body: 204 for
 * accepted and dropped beacons alike (so probing learns nothing; drops are logged
 * aggregated per reason, see logRejectedVisitBeacon()), 405 for other methods and
 * 429 when one network (IPv4 /24, IPv6 /48) floods the endpoint. Without
 * VISIT_ANALYTICS_SALT / API_KEY nothing is recorded. Validation and hashing live
 * in VisitAnalyticsService; the IP is used for the hash and the throttle bucket
 * only and is never stored in site_visits (the generic traffic log still sees it,
 * see ARCHITECTURE.md).
 */
function tryRecordVisitBeacon(string $uri): bool
{
    if ($uri !== '/api/visit') {
        return false;
    }

    header_remove('Set-Cookie');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');

    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        return true;
    }

    if (!VisitAnalyticsService::isSameOrigin($_SERVER)) {
        logRejectedVisitBeacon('foreign_origin');
        http_response_code(204);
        return true;
    }

    // Without a server-side secret the visitor hash could be recomputed by anyone,
    // so nothing is measured (and no throttle state is written) until one is set.
    $secret = VisitAnalyticsService::secret();
    if ($secret === null) {
        logRejectedVisitBeacon('missing_secret');
        http_response_code(204);
        return true;
    }

    $ip = RequestThrottle::currentIp();
    $throttle = RequestThrottle::hit(
        VisitAnalyticsService::THROTTLE_SCOPE,
        VisitAnalyticsService::throttleKey($ip),
        60,
        60,
        300
    );
    // Lazy cleanup: roughly one beacon in a hundred removes up to 200 stale visit throttle files.
    if (random_int(1, 100) === 1) {
        RequestThrottle::purgeStale(VisitAnalyticsService::THROTTLE_SCOPE, VisitAnalyticsService::THROTTLE_STALE_SECONDS, 200);
    }
    if ($throttle['locked']) {
        logRejectedVisitBeacon('throttled');
        http_response_code(429);
        header('Retry-After: ' . max(1, (int) $throttle['retry_after']));
        return true;
    }

    $body = file_get_contents('php://input', false, null, 0, VisitAnalyticsService::MAX_BODY_BYTES + 1);
    $payload = is_string($body) ? VisitAnalyticsService::parsePayload($body) : null;
    if ($payload === null) {
        logRejectedVisitBeacon('invalid_payload');
        http_response_code(204);
        return true;
    }

    try {
        $site = (new SiteRepository())->findFirst();
        if ($site && (int) ($site['visit_analytics_enabled'] ?? 1) === 1) {
            $now = new DateTimeImmutable('now');
            $ownHosts = [(string) ($_SERVER['HTTP_HOST'] ?? ''), (string) ($site['domain'] ?? '')];
            $repository = new VisitAnalyticsRepository();
            $repository->insert(VisitAnalyticsService::buildRecord(
                (int) $site['id'],
                $payload,
                $ip,
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
                $ownHosts,
                $secret,
                $now
            ));
            // Lazy retention: roughly one beacon in a hundred trims rows older than 14 months.
            if (random_int(1, 100) === 1) {
                $repository->purgeBefore(VisitAnalyticsService::retentionCutoff($now));
            }
        }
    } catch (\Throwable $e) {
        Logger::error('visit-analytics', $e->getMessage());
    }

    http_response_code(204);
    return true;
}

/**
 * Dropped beacons stay invisible to the client (204) but not to the operator: one
 * aggregated log line per reason and hour, with a count and without IP or payload.
 */
function logRejectedVisitBeacon(string $reason): void
{
    try {
        $count = VisitAnalyticsService::countRejection($reason, dirname(__DIR__) . '/storage/cache/throttle', time());
        if ($count !== null) {
            Logger::warning('visit-analytics', 'Visit beacon dropped', ['reason' => $reason, 'count_since_last_log' => $count]);
        }
    } catch (\Throwable) {
        // Logging must never break the beacon response.
    }
}

if (tryRecordVisitBeacon($earlyUri)) {
    exit;
}
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
        $blocks = (new HomepageContentService())->applyFallbacks($page, $blocks);
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

        $ogImageId = $seo['og_image_id'] ?? $site['default_og_image_id'] ?? null;
        $twImageId = $seo['twitter_image_id']
            ?? $site['default_twitter_image_id']
            ?? $seo['og_image_id']
            ?? $site['default_og_image_id']
            ?? null;
        $ogImageUrl = resolveSocialImageUrl($mediaRepo, $ogImageId, $site);
        $twImageUrl = resolveSocialImageUrl($mediaRepo, $twImageId, $site);
        $schemaImageUrl = resolveSocialImageUrl($mediaRepo, $seo['schema_image_id'] ?? null, $site);

        $fragment = $renderer->render(
            normalizeLegacyTemplateSource((string)($template['html_source'] ?? '')),
            $blocks,
            $mediaMap,
            $authenticated && !$previewMode
        );
        // Phase 5: generic CMS pages (and the homepage) flow their legacy
        // PageRenderer fragment through the generic `page` archetype. Byte-for-byte
        // passthrough - data-block/inline-editor mechanics are unchanged.
        // Phase 6 / manifest v2: a theme that opts into the PHTML views cascade
        // (declares views_dir) may override a generic page's content view via
        // overrides.<pageKey>.view or <views_dir>/pages/<pageKey>.phtml. Themes
        // without manifest v2 (incl. the active theme) resolve to null → the core
        // wrapper, unchanged.
        $themeKey = getThemeTemplateRegistry()->normalizePageKey((string)($page['url_path'] ?? ''));
        $themePreviewKey = isset($_GET['theme_preview']) ? trim((string)$_GET['theme_preview']) : null;
        $activeThemeKey = resolveActiveThemeKeyForSite($site, $themePreviewKey);
        $contentView = getThemeTemplateRegistry()->pageContentViewForSite($site, $themeKey, $themePreviewKey);
        $html = $contentView !== null
            ? \App\View\ViewFactory::forTheme($contentView['themeKey'], ['editMode' => $authenticated && !$previewMode], $contentView['viewsDir'])
                ->render($contentView['view'], [
                    'fragment' => $fragment,
                    'pageKey' => $themeKey,
                    'blocks' => $blocks,
                    'videoAutoplay' => (int)($site['video_autoplay_enabled'] ?? 1) === 1,
                    'reviews' => $themeKey === 'o-nas'
                        ? array_slice((new \App\Repositories\ReviewRepository())->allActive((int)$site['id']), 0, 3)
                        : [],
                    'stories' => $themeKey === 'o-nas'
                        ? \App\ViewModels\AboutStoriesData::forSite((int)$site['id'], 3)
                        : [],
                ])
            : \App\View\ViewFactory::forTheme(null)->render('archetypes/page', ['fragment' => $fragment]);
        $isHome = ltrim((string)($page['url_path'] ?? ''), '/') === '';
        $themeNav = resolveThemeNav($site, $isHome);
        [$html, $themeCss, $themeJs, $themeActive] = applyThemeTemplate(
            $renderer,
            $site,
            $themeKey,
            $site['name'] ?? '',
            $html,
            '',
            '',
            '',
            $themeNav,
            $themePreviewKey
        );
        if ($themeActive) {
            $css = ($template['css_source'] ?? '') . "\n" . $themeCss;
            $js = str_contains($html, 'theme-bezova-lens-tale')
                ? $themeJs . "\n" . ($template['js_source'] ?? '')
                : ($template['js_source'] ?? '') . "\n" . $themeJs;
        } elseif (!empty($site['layout_html'])) {
            $html = $renderer->applyLayout($site['layout_html'], $html, $site['name'] ?? '', $themeNav);
            $css  = ($site['layout_css'] ?? '') . "\n" . ($template['css_source'] ?? '') . "\n" . $themeCss;
            $js   = ($site['layout_js']  ?? '') . "\n" . ($template['js_source']  ?? '') . "\n" . $themeJs;
        } else {
            $css = ($template['css_source'] ?? '') . "\n" . $themeCss;
            $js  = ($template['js_source']  ?? '') . "\n" . $themeJs;
        }
        $html = \App\Services\Render\PageComposer::compose([
            'mode'        => \App\Services\Render\PageComposer::MODE_LIVE,
            'renderer'    => $renderer,
            'html'        => $html,
            'css'         => $css,
            'js'          => $js,
            'seoHead'     => $seoRenderer->renderHead($seo, $site, $pageUrl, $ogImageUrl, $twImageUrl, $schemaImageUrl),
            'bodyScripts' => $seoRenderer->renderBodyScripts($site),
            'blocks'      => $blocks,
            'site'        => $site,
            'page'        => $page,
            'isHome'      => $isHome,
            'pageKey'     => $themeKey,
            'themeKey'    => $activeThemeKey,
            'editMode'    => $authenticated,
            'previewMode' => $previewMode,
            'uri'         => $uri,
        ]);

        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        return true;
    } catch (\Throwable $e) {
        Logger::error('frontend-render', $e->getMessage(), [
            'uri' => $uri,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        throw $e;
    }
}

function injectCookieConsent(string $html): string
{
    if (str_contains($html, 'data-cookie-consent-modal')) {
        return $html;
    }

    $runtime = '';
    // Matches the content-hashed filename too, which no longer ends in the plain name.
    if (!str_contains($html, 'cookie-consent-runtime')) {
        try {
            $site = (new SiteRepository())->findFirst();
            if ($site) {
                $runtime = (new SeoRenderer())->renderConsentRuntime($site) . "\n";
            }
        } catch (\Throwable $e) {
            Logger::error('cookie-consent', $e->getMessage());
        }
    }

    // Cookie-consent markup lives in the PHTML partial (app/Views/partials/cookie-consent.phtml).
    $consent = \App\View\ViewFactory::forTheme(null)->render('partials/cookie-consent');

    if (str_contains($html, '</body>')) {
        return str_replace('</body>', $runtime . $consent . "\n</body>", $html);
    }

    return $html . $runtime . $consent;
}

function sanitizeFrontendHtml(string $html, ?array $site = null): string
{
    $html = \App\Services\Render\SharedProcessRenderer::inject(
        $html,
        str_contains($html, 'theme-golden-bash') ? 'golden-bash' : null
    );
    $socialImageTags = [];
    $structuredDataScripts = [];
    $html = preg_replace_callback(
        '~<script\b[^>]*type=(["\'])application/ld\+json\1[^>]*>.*?</script>~is',
        static function (array $match) use (&$structuredDataScripts): string {
            $token = '<!--cms-structured-data-' . count($structuredDataScripts) . '-->';
            $structuredDataScripts[$token] = $match[0];
            return $token;
        },
        $html
    ) ?? $html;
    $html = preg_replace_callback(
        '~<meta\b(?=[^>]*(?:property=(["\'])og:image\1|name=(["\'])twitter:image\2))[^>]*>~i',
        static function (array $match) use (&$socialImageTags): string {
            $token = '<!--cms-social-image-' . count($socialImageTags) . '-->';
            $socialImageTags[$token] = $match[0];
            return $token;
        },
        $html
    ) ?? $html;
    $html = preg_replace('~https?://localhost(?::\d+)?/storage/media/~i', '/media/', $html) ?? $html;
    $html = str_replace('/storage/media/', '/media/', $html);
    $html = normalizeLegacyFrontendMarkup($html);
    $html = convertInlineHeroBackgrounds($html);
    $html = injectImagePerformanceAttributes($html, (int)($site['id'] ?? 0));
    $html = ensureMainContentAnchor($html);
    $html = injectSkipLink($html);
    $html = normalizeExternalLinkRel($html);
    $html = normalizeGtmNoscriptInlineStyle($html);
    $html = externalizeBodyCssVariables($html);
    $html = injectGlobalVideoOverlayStyles($html, $site);
    $html = injectCookieConsent($html);
    $html = injectSharedFrontendAssets($html);
    $html = injectFaviconLinks($html, $site);
    $html = extractInlineStyleBlocks($html);
    $html = extractInlineBodyScripts($html);
    if ($socialImageTags !== []) {
        $html = strtr($html, $socialImageTags);
    }
    $html = $structuredDataScripts !== [] ? strtr($html, $structuredDataScripts) : $html;

    // Last step on purpose: the page-scoped stylesheet is chosen from the finished markup,
    // so anything injected above (footer, cookie bar, hero) counts towards what it keeps.
    return \App\Support\PageCssSubsetter::apply($html);
}

function injectImagePerformanceAttributes(string $html, int $siteId = 0): string
{
    return \App\Services\Render\ResponsiveImageMarkupService::optimize($html, $siteId);
}

function convertInlineHeroBackgrounds(string $html): string
{
    return preg_replace_callback(
        '~<div([^>]*\bclass=(["\'])(?=[^"\']*\btheme-hero-video\b)([^"\']*)\2[^>]*)\bstyle=(["\'])background-image\s*:\s*url\((["\']?)(.*?)\5\)\s*;?\4([^>]*)>\s*</div>~is',
        static function (array $match): string {
            $attrs = trim((string)($match[1] ?? '') . ' ' . (string)($match[7] ?? ''));
            $src = html_entity_decode((string)($match[6] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (trim($src) === '') {
                return $match[0];
            }
            $aria = preg_match('/\baria-hidden=/i', $attrs) ? '' : ' aria-hidden="true"';
            return '<img class="hero-bg" src="' . htmlspecialchars($src, ENT_QUOTES) . '" alt=""' . $aria . '>';
        },
        $html
    ) ?? $html;
}

function normalizeGtmNoscriptInlineStyle(string $html): string
{
    return preg_replace_callback(
        '~<iframe\b([^>]*googletagmanager\.com/ns\.html[^>]*)>~i',
        static function (array $match): string {
            $attrs = preg_replace('/\sstyle=(["\']).*?\1/i', '', (string)$match[1]) ?? (string)$match[1];
            if (preg_match('/\sclass=(["\'])(.*?)\1/i', $attrs, $classMatch)) {
                $classes = preg_split('/\s+/', trim((string)$classMatch[2])) ?: [];
                if (!in_array('gtm-noscript', $classes, true)) {
                    $classes[] = 'gtm-noscript';
                }
                $attrs = preg_replace('/\sclass=(["\'])(.*?)\1/i', ' class="' . implode(' ', array_filter($classes)) . '"', $attrs, 1) ?? $attrs;
            } else {
                $attrs .= ' class="gtm-noscript"';
            }
            return '<iframe' . $attrs . '>';
        },
        $html
    ) ?? $html;
}

function ensureMainContentAnchor(string $html): string
{
    return preg_replace_callback('/<main\b([^>]*)>/i', static function (array $match): string {
        $attrs = (string)($match[1] ?? '');
        if (preg_match('/\sid=(["\']).*?\1/i', $attrs)) {
            return $match[0];
        }
        return '<main' . $attrs . ' id="main-content">';
    }, $html, 1) ?? $html;
}

function injectSkipLink(string $html): string
{
    if (str_contains($html, 'class="skip-link"') || str_contains($html, "class='skip-link'")) {
        return $html;
    }
    if (!preg_match('/<main\b/i', $html)) {
        return $html;
    }

    $skipLink = '<a class="skip-link" href="#main-content">Přeskočit na hlavní obsah</a>';
    return preg_replace('/(<body\b[^>]*>)/i', '$1' . $skipLink, $html, 1) ?? $html;
}

function normalizeExternalLinkRel(string $html): string
{
    return preg_replace_callback('/<a\b[^>]*target=(["\'])_blank\1[^>]*>/i', static function (array $match): string {
        $tag = $match[0];
        if (preg_match('/\srel=(["\'])(.*?)\1/i', $tag, $relMatch)) {
            $rel = strtolower(trim((string)($relMatch[2] ?? '')));
            $parts = array_values(array_filter(preg_split('/\s+/', $rel) ?: []));
            foreach (['noopener', 'noreferrer'] as $needed) {
                if (!in_array($needed, $parts, true)) {
                    $parts[] = $needed;
                }
            }
            return preg_replace('/\srel=(["\'])(.*?)\1/i', ' rel="' . implode(' ', $parts) . '"', $tag, 1) ?? $tag;
        }

        return preg_replace('/>$/', ' rel="noopener noreferrer">', $tag, 1) ?? $tag;
    }, $html) ?? $html;
}

function breadcrumbItems(string $currentLabel, string $currentUrl, array $parents = []): array
{
    $items = [
        ['label' => 'Uvod', 'url' => '/'],
    ];
    foreach ($parents as $parent) {
        if (!is_array($parent) || empty($parent['label']) || empty($parent['url'])) {
            continue;
        }
        $items[] = [
            'label' => (string)$parent['label'],
            'url' => normalizeBreadcrumbUrl((string)$parent['url']),
        ];
    }
    $items[] = [
        'label' => trim($currentLabel) !== '' ? trim($currentLabel) : 'Stranka',
        'url' => normalizeBreadcrumbUrl($currentUrl),
    ];
    return $items;
}

function normalizeBreadcrumbUrl(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '/';
    }
    if (preg_match('~^https?://~i', $url)) {
        return $url;
    }
    return '/' . ltrim($url, '/');
}

function injectBreadcrumbTrail(string $html, array $site, array $items): string
{
    if (count($items) < 2 || str_contains($html, 'data-cms-breadcrumbs')) {
        return $html;
    }

    $nav = renderBreadcrumbNav($items);
    $html = preg_replace('/(<main\b[^>]*>)/i', '$1' . $nav, $html, 1, $count) ?? $html;
    if ($count === 0) {
        $html = preg_replace('/(<\/header>)/i', '$1' . $nav, $html, 1, $headerCount) ?? $html;
        if ($headerCount === 0) {
            $html = preg_replace('/(<body\b[^>]*>)/i', '$1' . $nav, $html, 1) ?? $html;
        }
    }

    $schema = renderBreadcrumbJsonLd($items, (string)($site['domain'] ?? ''));
    if ($schema !== '' && stripos($html, '</head>') !== false && !str_contains($html, 'BreadcrumbList')) {
        $html = str_replace('</head>', "\n" . $schema . "\n</head>", $html);
    }

    return $html;
}

function renderBreadcrumbNav(array $items): string
{
    // Normalize URLs here (logic); markup lives in app/Views/partials/breadcrumb.phtml.
    $normalized = [];
    foreach ($items as $item) {
        $normalized[] = [
            'label' => (string)($item['label'] ?? ''),
            'url' => normalizeBreadcrumbUrl((string)($item['url'] ?? '')),
        ];
    }

    return \App\View\ViewFactory::forTheme(null)->render('partials/breadcrumb', ['items' => $normalized]);
}

function renderBreadcrumbJsonLd(array $items, string $domain): string
{
    $domain = rtrim(trim($domain), '/');
    if ($domain === '') {
        return '';
    }

    $elements = [];
    foreach ($items as $index => $item) {
        $url = normalizeBreadcrumbUrl((string)($item['url'] ?? ''));
        $elements[] = [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => (string)($item['label'] ?? ''),
            'item' => preg_match('~^https?://~i', $url) ? $url : $domain . $url,
        ];
    }

    $json = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $elements,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return $json ? '<script type="application/ld+json" data-cms-breadcrumb-schema>' . $json . '</script>' : '';
}

function blockContent(array $blocks, string $key): string
{
    return \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, $key);
}

function injectHomepageMissingSections(string $html, array $blocks, bool $isHome): string
{
    return \App\Services\Render\HomepageSectionRenderer::injectHomepageMissingSections($html, $blocks, $isHome);
}

function injectHomepageThemeDataSources(string $html, array $blocks, bool $isHome): string
{
    return \App\Services\Render\HomepageSectionRenderer::injectHomepageThemeDataSources($html, $blocks, $isHome);
}

function absolutePublicUrl(array $site, string $path = '/'): string
{
    $domain = rtrim(trim((string)($site['domain'] ?? '')), '/');
    $normalizedPath = trim($path);
    if ($normalizedPath === '') {
        $normalizedPath = '/';
    }

    if ($domain === '') {
        return $normalizedPath === '/' ? '/' : '/' . ltrim($normalizedPath, '/');
    }

    if ($normalizedPath === '/') {
        return $domain . '/';
    }

    return $domain . '/' . ltrim($normalizedPath, '/');
}

function normalizeCanonicalUrl(string $candidate, array $site, string $fallbackPath): string
{
    $candidate = trim($candidate);
    if ($candidate === '') {
        return absolutePublicUrl($site, $fallbackPath);
    }

    if (preg_match('~^https?://~i', $candidate)) {
        return $candidate;
    }

    return absolutePublicUrl($site, $candidate);
}

function upsertHeadTag(string $html, string $pattern, string $tag): string
{
    if (preg_match($pattern, $html)) {
        $replacementCount = 0;
        return preg_replace_callback(
            $pattern,
            static function () use (&$replacementCount, $tag): string {
                return $replacementCount++ === 0 ? $tag : '';
            },
            $html
        ) ?? $html;
    }

    if (stripos($html, '</head>') === false) {
        return $html;
    }

    return str_replace('</head>', $tag . "\n</head>", $html);
}

function injectPageHeadMetadata(string $html, array $site, array $meta): string
{
    if (stripos($html, '</head>') === false) {
        return $html;
    }

    $path = (string)($meta['path'] ?? '/');
    $pageSeo = [];
    $page = (new PageRepository())->findPublishedByUrlPath(trim($path, '/'));
    if ($page && (int)($page['site_id'] ?? 0) === (int)($site['id'] ?? 0)) {
        $pageSeo = (new SeoRepository())->findByPage((int)$page['id']) ?? [];
    }

    $titleValue = trim((string)($pageSeo['meta_title'] ?? '')) ?: (string)($meta['title'] ?? ($site['name'] ?? 'Web'));
    $descriptionValue = trim((string)($pageSeo['meta_description'] ?? '')) ?: trim((string)($meta['description'] ?? ''));
    $canonicalValue = trim((string)($pageSeo['canonical_url'] ?? '')) ?: (string)($meta['canonical'] ?? '');
    $robotsValue = array_key_exists('robots_index', $pageSeo) || array_key_exists('robots_follow', $pageSeo)
        ? ((!empty($pageSeo['robots_index']) ? 'index' : 'noindex') . ',' . (!empty($pageSeo['robots_follow']) ? 'follow' : 'nofollow'))
        : (string)($meta['robots'] ?? 'index,follow');

    $title = htmlspecialchars($titleValue, ENT_QUOTES, 'UTF-8');
    $description = htmlspecialchars($descriptionValue, ENT_QUOTES, 'UTF-8');
    $canonical = htmlspecialchars(normalizeCanonicalUrl($canonicalValue, $site, $path), ENT_QUOTES, 'UTF-8');
    $robots = htmlspecialchars($robotsValue, ENT_QUOTES, 'UTF-8');
    $ogType = htmlspecialchars((string)($meta['og_type'] ?? 'website'), ENT_QUOTES, 'UTF-8');
    $siteName = htmlspecialchars((string)($site['name'] ?? 'Web'), ENT_QUOTES, 'UTF-8');
    $ogTitleValue = trim((string)($pageSeo['og_title'] ?? '')) ?: $titleValue;
    $ogDescriptionValue = trim((string)($pageSeo['og_description'] ?? '')) ?: $descriptionValue;
    $twitterTitleValue = trim((string)($pageSeo['twitter_title'] ?? '')) ?: $ogTitleValue;
    $twitterDescriptionValue = trim((string)($pageSeo['twitter_description'] ?? '')) ?: $ogDescriptionValue;
    $ogTitle = htmlspecialchars($ogTitleValue, ENT_QUOTES, 'UTF-8');
    $ogDescription = htmlspecialchars($ogDescriptionValue, ENT_QUOTES, 'UTF-8');
    $twitterTitle = htmlspecialchars($twitterTitleValue, ENT_QUOTES, 'UTF-8');
    $twitterDescription = htmlspecialchars($twitterDescriptionValue, ENT_QUOTES, 'UTF-8');
    $ogImage = trim((string)($meta['og_image'] ?? ''));
    $twitterImage = trim((string)($meta['twitter_image'] ?? ''));
    $mediaRepo = new MediaRepository();
    // An explicitly selected page image wins; otherwise prefer site settings
    // over route-supplied photos (e.g. portfolio or blog hero images).
    if (!empty($pageSeo['og_image_id'])) {
        $ogImage = (string)(resolveSocialImageUrl($mediaRepo, $pageSeo['og_image_id'], $site) ?? '');
    } elseif (!empty($site['default_og_image_id'])) {
        $ogImage = (string)(resolveSocialImageUrl($mediaRepo, $site['default_og_image_id'], $site) ?? '');
    }
    if (!empty($pageSeo['twitter_image_id'])) {
        $twitterImage = (string)(resolveSocialImageUrl($mediaRepo, $pageSeo['twitter_image_id'], $site) ?? '');
    } elseif (!empty($site['default_twitter_image_id'])) {
        $twitterImage = (string)(resolveSocialImageUrl($mediaRepo, $site['default_twitter_image_id'], $site) ?? '');
    } elseif (!empty($pageSeo['og_image_id']) || !empty($site['default_og_image_id']) || $twitterImage === '') {
        $twitterImage = $ogImage;
    }
    if ($ogImage !== '') {
        $ogImage = normalizeCanonicalUrl($ogImage, $site, $path);
    }
    if ($twitterImage !== '') {
        $twitterImage = normalizeCanonicalUrl($twitterImage, $site, $path);
    }

    if (!preg_match('~<meta\s+charset=~i', $html)) {
        $html = str_replace('<head>', "<head>\n<meta charset=\"UTF-8\">", $html);
    }
    if (!preg_match('~<meta\s+name=(["\'])viewport\1~i', $html)) {
        $html = str_replace('</head>', "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n</head>", $html);
    }

    $html = upsertHeadTag($html, '~<title\b[^>]*>.*?</title>~is', '<title>' . $title . '</title>');
    if ($description !== '') {
        $html = upsertHeadTag($html, '~<meta\s+name=(["\'])description\1[^>]*>~i', '<meta name="description" content="' . $description . '">');
    }
    $html = upsertHeadTag($html, '~<meta\s+name=(["\'])robots\1[^>]*>~i', '<meta name="robots" content="' . $robots . '">');
    $html = upsertHeadTag($html, '~<link\s+rel=(["\'])canonical\1[^>]*>~i', '<link rel="canonical" href="' . $canonical . '">');
    $html = upsertHeadTag($html, '~<meta\s+property=(["\'])og:title\1[^>]*>~i', '<meta property="og:title" content="' . $ogTitle . '">');
    if ($ogDescription !== '') {
        $html = upsertHeadTag($html, '~<meta\s+property=(["\'])og:description\1[^>]*>~i', '<meta property="og:description" content="' . $ogDescription . '">');
    }
    if ($twitterDescription !== '') {
        $html = upsertHeadTag($html, '~<meta\s+name=(["\'])twitter:description\1[^>]*>~i', '<meta name="twitter:description" content="' . $twitterDescription . '">');
    }
    $html = upsertHeadTag($html, '~<meta\s+property=(["\'])og:type\1[^>]*>~i', '<meta property="og:type" content="' . $ogType . '">');
    $html = upsertHeadTag($html, '~<meta\s+property=(["\'])og:url\1[^>]*>~i', '<meta property="og:url" content="' . $canonical . '">');
    $html = upsertHeadTag($html, '~<meta\s+property=(["\'])og:site_name\1[^>]*>~i', '<meta property="og:site_name" content="' . $siteName . '">');
    $html = upsertHeadTag($html, '~<meta\s+name=(["\'])twitter:card\1[^>]*>~i', '<meta name="twitter:card" content="summary_large_image">');
    $html = upsertHeadTag($html, '~<meta\s+name=(["\'])twitter:title\1[^>]*>~i', '<meta name="twitter:title" content="' . $twitterTitle . '">');
    if ($ogImage !== '') {
        $safeOgImage = htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8');
        $html = upsertHeadTag($html, '~<meta\s+property=(["\'])og:image\1[^>]*>~i', '<meta property="og:image" content="' . $safeOgImage . '">');
    }
    if ($twitterImage !== '') {
        $safeTwitterImage = htmlspecialchars($twitterImage, ENT_QUOTES, 'UTF-8');
        $html = upsertHeadTag($html, '~<meta\s+name=(["\'])twitter:image\1[^>]*>~i', '<meta name="twitter:image" content="' . $safeTwitterImage . '">');
    }

    foreach (($meta['json_ld'] ?? []) as $index => $jsonLd) {
        $jsonLd = trim((string)$jsonLd);
        if ($jsonLd === '') {
            continue;
        }
        $marker = 'data-cms-page-schema="' . $index . '"';
        if (str_contains($html, $marker)) {
            continue;
        }
        $html = str_replace('</head>', '<script type="application/ld+json" ' . $marker . '>' . $jsonLd . '</script>' . "\n</head>", $html);
    }

    $seoRenderer = new SeoRenderer();
    if (!str_contains($html, 'data-cms-organization-schema')) {
        $html = str_replace('</head>', $seoRenderer->renderOrganizationSchema($site, $ogImage !== '' ? $ogImage : null) . "\n</head>", $html);
    }
    if (!str_contains($html, 'cms-tracking-config')) {
        $html = str_replace(
            '</head>',
            $seoRenderer->renderConsentRuntime($site) . "\n"
                . $seoRenderer->renderTrackingRuntime($site) . "\n</head>",
            $html
        );
    }

    return injectFaviconLinks($html, $site);
}

function jsonLd(array $payload): string
{
    return (string)(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
}

function renderFaqPageJsonLd(array $site, array $items): string
{
    $questions = [];
    foreach ($items as $item) {
        $question = trim((string)($item['question'] ?? ''));
        $answer = trim((string)($item['answer'] ?? ''));
        if ($question === '' || $answer === '') {
            continue;
        }
        $questions[] = [
            '@type' => 'Question',
            'name' => $question,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $answer,
            ],
        ];
    }

    if ($questions === []) {
        return '';
    }

    return jsonLd([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $questions,
    ]);
}

function renderContactPageJsonLd(array $site, array $contact): string
{
    $graph = [[
        '@type' => 'ContactPage',
        'name' => 'Kontakt',
        'url' => absolutePublicUrl($site, '/kontakt'),
        'isPartOf' => absolutePublicUrl($site, '/'),
    ]];

    $contactPoint = ['@type' => 'ContactPoint'];
    $hasContactPoint = false;
    if (!empty($contact['email'])) {
        $contactPoint['email'] = trim((string)$contact['email']);
        $hasContactPoint = true;
    }
    if (!empty($contact['phone_1'])) {
        $contactPoint['telephone'] = trim((string)$contact['phone_1']);
        $hasContactPoint = true;
    }
    if ($hasContactPoint) {
        $contactPoint['contactType'] = 'customer support';
        $graph[] = $contactPoint;
    }

    return jsonLd([
        '@context' => 'https://schema.org',
        '@graph' => $graph,
    ]);
}

function renderBlogListingJsonLd(array $site, array $posts): string
{
    $blogPosts = [];
    foreach ($posts as $post) {
        $slug = trim((string)($post['slug'] ?? ''), '/');
        $title = trim((string)($post['title'] ?? ''));
        if ($slug === '' || $title === '') {
            continue;
        }
        $blogPost = [
            '@type' => 'BlogPosting',
            'headline' => $title,
            'url' => absolutePublicUrl($site, '/' . $slug),
            'author' => [
                '@type' => 'Organization',
                '@id' => absolutePublicUrl($site, '/#organization'),
                'name' => (string)($site['name'] ?? 'RB Videos & Photos'),
            ],
        ];
        if (!empty($post['published_at'])) {
            $blogPost['datePublished'] = date(DATE_ATOM, strtotime((string)$post['published_at']));
        }
        if (!empty($post['updated_at'])) {
            $blogPost['dateModified'] = date(DATE_ATOM, strtotime((string)$post['updated_at']));
        }
        $description = trim((string)($post['meta_description'] ?? $post['excerpt'] ?? ''));
        if ($description !== '') {
            $blogPost['description'] = $description;
        }
        $blogPosts[] = $blogPost;
    }

    return jsonLd([
        '@context' => 'https://schema.org',
        '@type' => 'Blog',
        'name' => 'Blog',
        'url' => absolutePublicUrl($site, '/blog'),
        'blogPost' => $blogPosts,
    ]);
}

function renderBlogPostJsonLd(array $site, array $post, string $canonical, ?string $imageUrl = null): string
{
    $payload = [
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => trim((string)($post['title'] ?? '')),
        'url' => $canonical,
        'mainEntityOfPage' => $canonical,
        'author' => [
            '@type' => 'Organization',
            '@id' => absolutePublicUrl($site, '/#organization'),
            'name' => (string)($site['name'] ?? 'RB Videos & Photos'),
        ],
    ];

    $description = trim((string)($post['meta_description'] ?? $post['excerpt'] ?? ''));
    if ($description !== '') {
        $payload['description'] = $description;
    }
    if (!empty($post['published_at'])) {
        $payload['datePublished'] = date(DATE_ATOM, strtotime((string)$post['published_at']));
    }
    if (!empty($post['updated_at'])) {
        $payload['dateModified'] = date(DATE_ATOM, strtotime((string)$post['updated_at']));
    }
    if ($imageUrl !== null && trim($imageUrl) !== '') {
        $payload['image'] = $imageUrl;
    }

    return jsonLd($payload);
}

function renderPortfolioVideoJsonLd(array $site, array $entry, string $canonical, ?string $imageUrl = null): string
{
    $videoUrl = trim((string)($entry['video_url'] ?? ''));
    if ($videoUrl === '') {
        return '';
    }

    $payload = [
        '@context' => 'https://schema.org',
        '@type' => 'VideoObject',
        'name' => trim((string)($entry['title'] ?? 'Ukázka videa')),
        'description' => trim(strip_tags((string)($entry['meta_description'] ?? $entry['description'] ?? ''))),
        'url' => $canonical,
        'embedUrl' => PortfolioRepository::embedUrl($videoUrl),
    ];
    if ($imageUrl !== null && trim($imageUrl) !== '') {
        $payload['thumbnailUrl'] = $imageUrl;
    }
    if (!empty($entry['published_at'])) {
        $payload['uploadDate'] = date(DATE_ATOM, strtotime((string)$entry['published_at']));
    }

    return jsonLd($payload);
}

/**
 * Centralni doplneni favicon odkazu do <head> pro vsechny stranky.
 * Specialni handlery (FAQ, Kontakt, portfolio, hodnoceni, blog) vkladaji do
 * <head> jen <title>, takze jim favicon chybel - tady ho doplnime jednotne.
 * Idempotentni: pokud uz <head> nejaky favicon obsahuje (bezne stranky pres
 * SeoRenderer::renderHead), neprida se nic.
 */
function injectFaviconLinks(string $html, ?array $site): string
{
    if (!is_array($site) || stripos($html, '</head>') === false) {
        return $html;
    }
    if (preg_match('~<link[^>]+rel=(["\'])(?:shortcut\s+)?icon\1~i', $html)) {
        return $html;
    }

    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
    $links = '';
    if (!empty($site['favicon_ico_url'])) {
        $links .= '<link rel="icon" type="image/x-icon" href="' . $h($site['favicon_ico_url']) . '">';
    }
    if (!empty($site['favicon_png_url'])) {
        $png = (string) $site['favicon_png_url'];
        $type = str_ends_with(strtolower(parse_url($png, PHP_URL_PATH) ?: $png), '.svg') ? 'image/svg+xml' : 'image/png';
        $links .= '<link rel="icon" type="' . $type . '" href="' . $h($png) . '">';
    }
    if (!empty($site['apple_touch_icon_url'])) {
        $links .= '<link rel="apple-touch-icon" href="' . $h($site['apple_touch_icon_url']) . '">';
    }

    if ($links === '') {
        return $html;
    }

    return str_replace('</head>', "\n" . $links . "\n</head>", $html);
}

function injectSharedFrontendAssets(string $html): string
{
    return \App\Services\Render\MarkupNormalizer::injectSharedFrontendAssets($html);
}

function normalizeLegacyFrontendMarkup(string $html): string
{
    return \App\Services\Render\MarkupNormalizer::normalizeLegacyFrontendMarkup($html, true);
}

function normalizeContactFormAccessibility(string $html): string
{
    return \App\Services\Render\MarkupNormalizer::normalizeContactFormAccessibility($html);
}

function normalizeLegacyTemplateSource(string $html): string
{
    return \App\Services\Render\MarkupNormalizer::normalizeLegacyTemplateSource($html);
}

function externalizeBodyCssVariables(string $html): string
{
    return \App\Services\Render\MarkupNormalizer::externalizeBodyCssVariables($html);
}

function resolveVideoOverlayOpacity(?array $site = null): float
{
    static $cached = null;

    if ($site === null) {
        if ($cached !== null) {
            return $cached;
        }
        $site = (new SiteRepository())->findFirst();
    }

    $value = (float)($site['video_overlay_opacity'] ?? 0.62);
    $value = max(0.0, min(1.0, $value));

    if ($site === null || !isset($site['id'])) {
        $cached = $value;
    }

    return $value;
}

function injectGlobalVideoOverlayStyles(string $html, ?array $site = null): string
{
    $overlay = resolveVideoOverlayOpacity($site);
    $soft = round($overlay * 0.35, 3);
    $overlayCss = number_format($overlay, 3, '.', '');
    $softCss = number_format($soft, 3, '.', '');
    $css = <<<CSS
:root{--cms-video-overlay-opacity:{$overlayCss};--cms-video-overlay-soft-opacity:{$softCss}}
.hp-hero,.hero,.page-hero,.pf-hero,.bl-hero,.kt-hero,.fq-hero,.bp-hero,.pf-detail-hero,.bp-detail-hero{
  position:relative;
  isolation:isolate;
  overflow:hidden;
}
.hero::before,.page-hero::before,.pf-hero::before,.bl-hero::before,.kt-hero::before,.fq-hero::before,.bp-hero::before,.pf-detail-hero::before,.bp-detail-hero::before{
  content:"";
  position:absolute;
  inset:0;
  z-index:1;
  pointer-events:none;
  background:linear-gradient(180deg,rgba(17,12,9,.16),rgba(17,12,9,.06) 32%,rgba(17,12,9,.34));
}
.theme-hero-video::after,
.pf-video-wrap::after{
  content:"";
  position:absolute;
  inset:0;
  z-index:2;
  pointer-events:none;
  background:linear-gradient(90deg,rgba(0,0,0,var(--cms-video-overlay-opacity)),rgba(0,0,0,var(--cms-video-overlay-soft-opacity)),rgba(0,0,0,var(--cms-video-overlay-opacity)));
}
/* Homepage hero: no darkening over the video. The theme already fades it into the
   paper background with a mask, so a second overlay only muddies it. */
.hp-hero .theme-hero-video::after{
  content:none;
}
.theme-hero-video{
  position:absolute;
  inset:0;
  z-index:0;
  width:100%;
  height:100%;
  object-fit:cover;
  pointer-events:none;
  background:#050505;
  background-size:cover;
  background-position:center center;
  transform:translate3d(0,var(--ew-hero-parallax-y,0px),0) scale(1.05);
  transform-origin:center;
  will-change:transform;
}
.theme-hero-video--embed{
  overflow:hidden;
  background-size:cover;
  background-position:center center;
}
.theme-hero-video--embed iframe{
  position:absolute;
  top:50%;
  left:50%;
  width:100vw;
  height:56.25vw;
  min-width:177.78vh;
  min-height:100vh;
  transform:translate(-50%,-50%);
  border:0;
  pointer-events:none;
}
.theme-hero-video--image{
  background-repeat:no-repeat;
  background-attachment:scroll;
}
.hp-hero>.container,.hero>.container,.page-hero>.container,.pf-hero>.container,.bl-hero>.container,.kt-hero>.container,.fq-hero>.container,.bp-hero>.container,.pf-detail-hero>.container,.bp-detail-hero>.container,.hero-content{
  position:relative;
  z-index:3;
}
.hp-hero h1,.hero h1,.page-hero h1,.pf-hero h1,.bl-hero h1,.kt-hero h1,.fq-hero h1,.bp-hero h1,.pf-detail-hero h1,.bp-detail-hero h1{
  color:#fff;
  text-shadow:0 2px 18px rgba(0,0,0,.28);
}
.hp-hero .section-label,.hero .section-label,.page-hero .section-label,.pf-hero .section-label,.bl-hero .section-label,.kt-hero .section-label,.fq-hero .section-label,.bp-hero .section-label,.pf-detail-hero .section-label,.bp-detail-hero .section-label,
.hp-hero .hero-sub,.hero .hero-sub,.page-hero .hero-sub,.pf-hero .hero-sub,.bl-hero .hero-sub,.kt-hero .hero-sub,.fq-hero .hero-sub,.bp-hero .hero-sub,.pf-detail-hero .hero-sub,.bp-detail-hero .hero-sub{
  color:rgba(255,255,255,.84);
}
CSS;

    return FrontendAssetManager::injectInlineStyle($html, $css, 'data-cms-video-overlay');
}

function extractInlineStyleBlocks(string $html): string
{
    return \App\Services\Render\MarkupNormalizer::extractInlineStyleBlocks($html);
}

function extractInlineBodyScripts(string $html): string
{
    return \App\Services\Render\MarkupNormalizer::extractInlineBodyScripts($html);
}

function injectCmsEditorChrome(string $html, string $uri, string $pageTitle, ?int $pageId, bool $previewMode = false): string
{
    if (!Auth::check()) {
        return $html;
    }

    $csrfToken = htmlspecialchars(Auth::csrfToken(), ENT_QUOTES);
    $user = Auth::user();
    $userEmail = htmlspecialchars($user['email'] ?? '', ENT_QUOTES);
    $safeTitle = htmlspecialchars($pageTitle, ENT_QUOTES);
    $appVersion = htmlspecialchars(Version::APP, ENT_QUOTES);
    $previewHref = htmlspecialchars($uri . '?cms_preview=1', ENT_QUOTES);
    $editHref = htmlspecialchars($uri, ENT_QUOTES);
    $modeClass = $previewMode ? 'cms-ab-mode-badge--preview' : 'cms-ab-mode-badge--edit';
    $modeText = $previewMode ? 'NAHLED' : 'EDITACE';
    $editorButton = $previewMode
        ? '<a class="cms-ab-btn cms-ab-btn--active" href="' . $editHref . '">&#9998; Editace</a>'
        : '<a class="cms-ab-btn" href="' . $previewHref . '">&#128065; Nahled</a>';

    $editorMeta = '<meta name="app-version" content="' . $appVersion . '">'
        . '<link rel="stylesheet" href="/assets/inline-editor.css">';
    if (!$previewMode && $pageId !== null) {
        $editorMeta = '<meta name="inline-page-id" content="' . (int)$pageId . '">'
            . '<meta name="inline-csrf" content="' . $csrfToken . '">'
            . $editorMeta;
    }

    $adminBar = <<<HTML
<div id="cms-adminbar">
  <div class="cms-ab-left">
    <a class="cms-ab-logo" href="/admin">&#9998;&nbsp;CMS</a>
    <span class="cms-ab-sep"></span>
    <span class="cms-ab-page">{$safeTitle}</span>
    <span class="cms-ab-mode-badge {$modeClass}">{$modeText}</span>
  </div>
  <div class="cms-ab-right">
    <span class="cms-ab-ver">{$appVersion}</span>
    <span class="cms-ab-email">{$userEmail}</span>
    {$editorButton}
    <a class="cms-ab-btn" href="/admin">Administrace</a>
    <form method="POST" action="/admin/logout" class="cms-ab-form">
      <input type="hidden" name="_csrf" value="{$csrfToken}">
      <button type="submit" class="cms-ab-btn cms-ab-logout">Odhlásit</button>
    </form>
  </div>
</div>
HTML;

    $html = str_replace('</head>', "\n" . $editorMeta . "\n</head>", $html);
    $html = preg_replace('/<body\b([^>]*)>/i', '<body$1>' . "\n" . $adminBar, $html, 1) ?? $html;
    if (!$previewMode && $pageId !== null) {
        $html = str_replace('</body>', "\n<script src=\"/assets/inline-editor.js\"></script>\n</body>", $html);
    }

    return $html;
}

function publicPageForPath(string $path): ?array
{
    $repo = new PageRepository();
    $path = trim($path, '/');
    return Auth::check() ? $repo->findByUrlPath($path) : $repo->findPublishedByUrlPath($path);
}

function pageBlockMap(?array $page): array
{
    if (!$page || empty($page['id'])) {
        return [];
    }

    $map = [];
    foreach ((new BlockRepository())->allByPage((int)$page['id']) as $block) {
        $map[(string)$block['block_key']] = $block;
    }

    return $map;
}

function firstBlockValue(array $blocks, array $keys, string $fallback): array
{
    // $blocks here is a block_key => row map (built by pageBlockMap()); the
    // shared implementation takes a plain row list so it also works with
    // PublishService's native format. array_values() is safe because every
    // row carries its own 'block_key' field, which the shared helper re-keys.
    return \App\Services\Render\HomepageSectionRenderer::firstBlockValue(array_values($blocks), $keys, $fallback);
}

function defaultContactFormEmail(): string
{
    return ContactRepository::DEFAULT_FORM_EMAIL;
}

function inlineAttrs(?array $page, string $key, string $type = 'text'): string
{
    if (!Auth::check() || isset($_GET['cms_preview']) || !$page || $key === '') {
        return '';
    }

    return ' data-inline-key="' . htmlspecialchars($key, ENT_QUOTES) . '" data-inline-type="' . htmlspecialchars($type, ENT_QUOTES) . '"';
}

function injectHomepageHeroImage(string $html, int $siteId, bool $isHome): string
{
    if (!$isHome) {
        return $html;
    }

    $heroImage = resolveHomepageHeroImageUrl($siteId);
    if ($heroImage === '') {
        return $html;
    }

    return injectBodyCssVariableToken($html, 'theme-hero-image', "url('" . str_replace("'", '%27', $heroImage) . "')");
}

function injectPageHeroImage(string $html, int $pageId, array $site = []): string
{
    $heroImage = resolvePageHeroImageUrl($pageId);
    if ($heroImage === '') {
        $heroImage = normalizePublicAssetUrl((string)($site['default_hero_poster_url'] ?? ''));
    }
    if ($heroImage === '') {
        return $html;
    }

    return injectBodyCssVariableToken($html, 'theme-page-hero-image', "url('" . str_replace("'", '%27', $heroImage) . "')");
}

function injectHomepageAboutVisualSource(string $html, int $pageId, int $siteId, bool $isHome): string
{
    return \App\Services\Render\HomepageSectionRenderer::injectHomepageAboutVisualSource($html, $pageId, $siteId, $isHome);
}

function resolvePageHeroImageUrl(int $pageId): string
{
    try {
        $block = (new BlockRepository())->findByKey($pageId, 'hero_bg');
        if (!$block) {
            return '';
        }

        return publicMediaUrl((string)($block['content'] ?? ''));
    } catch (\Throwable $e) {
        Logger::infoTo('theme-debug', 'theme.page.hero.image.error', 'Failed to resolve page hero image', [
            'page_id' => $pageId,
            'error' => $e->getMessage(),
        ]);
    }

    return '';
}

function injectBodyCssVariableToken(string $html, string $name, string $value): string
{
    return \App\Services\Render\MarkupNormalizer::injectBodyCssVariableToken($html, $name, $value);
}

function injectBodyPageKey(string $html, string $pageKey): string
{
    return \App\Services\Render\MarkupNormalizer::injectBodyPageKey($html, $pageKey);
}

function resolveHomepageHeroImageUrl(int $siteId): string
{
    $pages = new PageRepository();
    $blocks = new BlockRepository();
    $portfolioRepo = new PortfolioRepository();
    $blogRepo = new BlogRepository();
    $mediaRepo = new MediaRepository();

    try {
        $homePage = $pages->findByUrlPath('');
        if ($homePage) {
            foreach ($blocks->allByPage((int)$homePage['id']) as $block) {
                if (($block['block_key'] ?? '') !== 'hero_bg') {
                    continue;
                }

                $candidate = publicMediaUrl((string)($block['content'] ?? ''));
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        $entries = $portfolioRepo->allPublished($siteId);
        foreach (array_slice($entries, 0, 3) as $entry) {
            $mediaItems = $portfolioRepo->getMedia((int)$entry['id']);
            if (!empty($mediaItems)) {
                $candidate = (string)($mediaItems[0]['storage_path'] ?? $mediaItems[0]['medium_path'] ?? $mediaItems[0]['thumb_path'] ?? '');
                $url = publicMediaUrl($candidate);
                if ($url !== '') {
                    return $url;
                }
            }

            $thumb = !empty($entry['video_url']) ? PortfolioRepository::youtubeThumbnail((string)$entry['video_url']) : '';
            if ($thumb !== '') {
                return $thumb;
            }
        }

        $posts = $blogRepo->allPublished($siteId);
        foreach (array_slice($posts, 0, 3) as $post) {
            $imageId = (int)($post['featured_image_id'] ?? 0);
            if ($imageId <= 0) {
                continue;
            }

            $media = $mediaRepo->findById($imageId);
            if (!$media) {
                continue;
            }

            $candidate = (string)($media['storage_path'] ?? $media['medium_path'] ?? $media['thumb_path'] ?? '');
            $url = publicMediaUrl($candidate);
            if ($url !== '') {
                return $url;
            }
        }
    } catch (\Throwable $e) {
        Logger::infoTo('theme-debug', 'theme.hero.image', 'Failed to resolve homepage hero image', [
            'site_id' => $siteId,
            'error' => $e->getMessage(),
        ]);
    }

    return '';
}

function injectPageHeroVideo(string $html, array $site, ?int $pageId, string $pageKey): string
{
    $siteId = (int)($site['id'] ?? 0);
    $pageKey = getThemeTemplateRegistry()->normalizePageKey($pageKey);
    if (hasHeroVideoElement($html)) {
        return ensureHeroVideoStyle($html);
    }

    $posterUrl = normalizePublicAssetUrl((string)($site['default_hero_poster_url'] ?? ''));
    $autoplay = (int)($site['video_autoplay_enabled'] ?? 1) === 1;

    $isHomepage = $pageKey === 'homepage';
    // Priorita: 1) vlastni video stranky, 2) homepage: vychozi video webu z Nastaveni,
    // 3) page hero_bg foto, 4) site default poster foto, 5) vychozi video webu.
    // Uvnitr jedne sady (stranka / web) ma kazda sirka okna vlastni video a nic se nededi.
    $videoHtml = '';
    $pid = (int)($pageId ?? 0);
    $heroRenderer = \App\Services\Render\HeroMediaRenderer::class;
    $pageUrls = $heroRenderer::pickHeroVideoUrls(
        $pid > 0 ? resolvePageBlockUrl($pid, 'hero_video') : '',
        $pid > 0 ? resolvePageBlockUrl($pid, 'hero_video_tablet') : '',
        $pid > 0 ? resolvePageBlockUrl($pid, 'hero_video_mobile') : ''
    );
    $siteUrls = $heroRenderer::pickHeroVideoUrls(
        normalizePublicAssetUrl((string)($site['default_hero_video_url'] ?? '')),
        normalizePublicAssetUrl((string)($site['default_hero_video_url_tablet'] ?? '')),
        normalizePublicAssetUrl((string)($site['default_hero_video_url_mobile'] ?? ''))
    );
    $buildHero = static fn(array $urls): string => buildHeroVideoHtml(
        \App\Services\HeroVideoMediaLookup::versionedUrl($urls['desktop']), $posterUrl, $autoplay,
        \App\Services\HeroVideoMediaLookup::versionedUrl($urls['tablet']), \App\Services\HeroVideoMediaLookup::versionedUrl($urls['mobile']),
        \App\Services\HeroVideoMediaLookup::ratios($siteId, $urls),
        \App\Services\HeroVideoMediaLookup::lightPoster($siteId, $posterUrl)
    );
    if ($heroRenderer::hasHeroVideo($pageUrls)) {
        $videoHtml = $buildHero($pageUrls);
    } else {
        if ($isHomepage && $heroRenderer::hasHeroVideo($siteUrls)) {
            $videoHtml = $buildHero($siteUrls);
        }

        $pagePhoto = $pid > 0 ? resolvePageHeroImageUrl($pid) : '';
        if ($videoHtml === '' && $pagePhoto !== '') {
            $videoHtml = buildHeroImageHtml($pagePhoto);
        }

        if ($videoHtml === '' && $posterUrl !== '') {
            $videoHtml = buildHeroImageHtml($posterUrl);
        }

        if ($videoHtml === '' && $heroRenderer::hasHeroVideo($siteUrls)) {
            $videoHtml = $buildHero($siteUrls);
        }
    }

    if ($videoHtml === '') {
        return $html;
    }

    $html = ensureHeroVideoStyle($html);

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

    return preg_replace_callback(
        '/(<main\b[^>]*>)/i',
        static fn(array $m): string => $m[1] . $videoHtml,
        $html,
        1
    ) ?? $html;
}

function ensureHeroVideoStyle(string $html): string
{
    return \App\Services\Render\HeroMediaRenderer::ensureHeroVideoStyle($html);
}

function resolvePageBlockUrl(int $pageId, string $blockKey): string
{
    try {
        foreach ((new BlockRepository())->allByPage($pageId) as $block) {
            if (($block['block_key'] ?? '') !== $blockKey) {
                continue;
            }
            return normalizePublicAssetUrl((string)($block['content'] ?? ''));
        }
    } catch (\Throwable $e) {
        Logger::infoTo('theme-debug', 'theme.hero.block.error', 'Failed to resolve page hero block', [
            'page_id' => $pageId,
            'block_key' => $blockKey,
            'error' => $e->getMessage(),
        ]);
    }

    return '';
}

function buildHeroImageHtml(string $imageUrl): string
{
    return \App\Services\Render\HeroMediaRenderer::buildHeroImageHtml($imageUrl);
}

/**
 * URL pro OG/Twitter nahled - preferuje variantu 'og' (1200x630), jinak original.
 */
function resolveSocialImageUrl(MediaRepository $mediaRepo, $imageId, array $site): ?string
{
    if (empty($imageId)) {
        return null;
    }
    $media = $mediaRepo->findById((int) $imageId);
    if (!$media) {
        return null;
    }

    $path = (string) ($media['storage_path'] ?? '');
    foreach ($mediaRepo->variantsByMediaId((int) $imageId) as $variant) {
        if (($variant['format'] ?? '') === 'og' && !empty($variant['path'])) {
            $path = (string) $variant['path'];
            break;
        }
    }

    if ($path === '') {
        return null;
    }

    return \App\Services\Render\MediaUrlResolver::absolutePublicMediaUrl(
        $path,
        (string)($site['domain'] ?? '')
    );
}

function buildHeroVideoHtml(string $videoUrl, string $posterUrl = '', bool $autoplay = false, string $tabletUrl = '', string $mobileUrl = '', array $ratios = [], string $videoPosterUrl = ''): string
{
    return \App\Services\Render\HeroMediaRenderer::buildHeroVideoHtml($videoUrl, $posterUrl, $autoplay, $tabletUrl, $mobileUrl, $ratios, $videoPosterUrl);
}

function hasHeroVideoElement(string $html): bool
{
    return \App\Services\Render\HeroMediaRenderer::hasHeroVideoElement($html);
}

function buildVideoEmbedUrl(string $videoUrl): string
{
    return \App\Services\Render\HeroMediaRenderer::buildVideoEmbedUrl($videoUrl);
}

function injectSiteLogo(string $html, array $site): string
{
    $rawLogoUrl = (string)($site['logo_url'] ?? '');
    $logoUrl    = normalizePublicAssetUrl($rawLogoUrl);
    if ($logoUrl === '') {
        return $html;
    }

    $siteName = (string)($site['name'] ?? 'Web');
    $safeUrl  = htmlspecialchars($logoUrl, ENT_QUOTES);
    $safeName = htmlspecialchars($siteName, ENT_QUOTES);

    $webpUrl  = '';
    $siteId   = (int)($site['id'] ?? 0);
    if ($siteId > 0 && !preg_match('~^https?://~i', $rawLogoUrl)) {
        $storagePath = ltrim(str_replace('\\', '/', $rawLogoUrl), '/');
        $mrepo = new MediaRepository();
        $mediaRow = $mrepo->findByStoragePath($siteId, $storagePath);
        if ($mediaRow) {
            foreach ($mrepo->variantsByMediaId((int)$mediaRow['id']) as $v) {
                if ($v['format'] === 'webp' && !empty($v['path'])) {
                    $webpUrl = normalizePublicAssetUrl($v['path']);
                    break;
                }
            }
        }
    }

    $imgTag = '<img class="site-logo-img" src="' . $safeUrl . '" alt="' . $safeName . '" fetchpriority="high" decoding="async">';
    if ($webpUrl !== '') {
        $safeWebp  = htmlspecialchars($webpUrl, ENT_QUOTES);
        $brandHtml = '<picture class="site-logo-picture"><source srcset="' . $safeWebp . '" type="image/webp">' . $imgTag . '</picture>'
                   . '<span class="site-logo-text">' . $safeName . '</span>';
    } else {
        $brandHtml = $imgTag . '<span class="site-logo-text">' . $safeName . '</span>';
    }

    $html = preg_replace_callback(
        '~(<a\b(?=[^>]*\bclass=(["\'])(?=[^"\']*(?:theme-brand|brand|pf-nav-logo|pl-nav-logo))[^"\']*\2)[^>]*>).*?(</a>)~is',
        static fn(array $m): string => $m[1] . $brandHtml . $m[3],
        $html
    ) ?? $html;

    $style = <<<'HTML'
<style data-cms-site-logo>
.site-logo-picture{display:contents}
.site-logo-img{display:block;max-height:44px;max-width:120px;width:auto;height:auto;object-fit:contain}
.site-logo-text{display:inline-block;margin-left:10px;color:inherit;font:inherit;letter-spacing:inherit;text-transform:inherit;line-height:1}
</style>
HTML;

    if (!str_contains($html, 'data-cms-site-logo')) {
        $html = str_replace('</head>', "\n" . $style . "\n</head>", $html);
    }

    return $html;
}

function normalizePublicAssetUrl(string $value): string
{
    return \App\Services\Render\MediaUrlResolver::normalizePublicAssetUrl($value);
}

function resolveDemoMediaUrlByFilename(int $siteId, string $filename): string
{
    return \App\Services\Render\MediaUrlResolver::resolveDemoMediaUrlByFilename($siteId, $filename);
}

function normalizeSocialType(string $type, string $url = ''): string
{
    return \App\Services\Render\SocialLinkRenderer::normalizeSocialType($type, $url);
}

function socialLabel(string $type): string
{
    return \App\Services\Render\SocialLinkRenderer::socialLabel($type);
}

function socialIconSvg(string $type): string
{
    return \App\Services\Render\SocialLinkRenderer::socialIconSvg($type);
}

function normalizePhoneForHref(string $phone): string
{
    return \App\Services\Render\ContactNormalizer::normalizePhoneForHref($phone);
}

function formatPhoneForDisplay(string $phone): string
{
    return \App\Services\Render\ContactNormalizer::formatPhoneForDisplay($phone);
}

function phoneDedupKey(string $phone): string
{
    return \App\Services\Render\ContactNormalizer::phoneDedupKey($phone);
}

function extractPhoneNumbersFromText(string $plain): array
{
    return \App\Services\Render\ContactNormalizer::extractPhoneNumbersFromText($plain);
}

function ensureRbDemoContactAddress(array $contact): array
{
    return \App\Services\Render\ContactNormalizer::ensureRbDemoContactAddress($contact);
}

function enrichContactWithLegacyBlocks(int $siteId, array $contact): array
{
    return \App\Services\Render\ContactNormalizer::enrichContactWithLegacyBlocks($siteId, $contact);
}

function buildRichFooterHtml(int $siteId): string
{
    return \App\Services\Render\FooterRenderer::buildRichFooterHtml($siteId);
}

function injectFooterSocials(string $html, int $siteId): string
{
    return \App\Services\Render\FooterRenderer::injectFooterSocials($html, $siteId);
}

function getThemeTemplateRegistry(): ThemeTemplateRegistry
{
    static $registry = null;
    if ($registry === null) {
        $registry = new ThemeTemplateRegistry();
    }

    return $registry;
}

/**
 * Resolve the theme key that will actually render this request (active theme,
 * or the ?theme_preview= override), using the exact same resolution the
 * later applyThemeTemplate() call performs for CSS/layout. Callers use this
 * key with ViewFactory::forTheme() so content archetypes can be overridden
 * per-theme via Templates/<key>/views/archetypes/<name>.phtml, the same
 * cascade ViewResolver has supported since Phase 1. Returns null when no
 * theme resolves (ViewFactory::forTheme(null, ...) then falls back to core).
 */
function resolveActiveThemeKeyForSite(array $site, ?string $previewKey = null): ?string
{
    $theme = getThemeTemplateRegistry()->resolveForSite($site, $previewKey);
    $key = trim((string)($theme['key'] ?? ''));

    return $key !== '' ? $key : null;
}

function applyThemeTemplate(PageRenderer $renderer, array $site, string $pageKey, string $siteName, string $html, string $css, string $js, string $unused = '', string $nav = '', ?string $previewKey = null): array
{
    $registry = getThemeTemplateRegistry();
    Logger::infoTo('theme-debug', 'theme.frontend.apply.request', 'Frontend theme apply requested', [
        'site_id' => $site['id'] ?? null,
        'site_name' => $site['name'] ?? null,
        'page_key' => $pageKey,
        'preview_key' => $previewKey !== null && trim($previewKey) !== '' ? trim($previewKey) : null,
        'nav_len' => strlen($nav),
        'unused_len' => strlen($unused),
        'html_before_len' => strlen($html),
        'css_before_len' => strlen($css),
        'js_before_len' => strlen($js),
    ]);
    $theme = $registry->resolveForSite($site, $previewKey);
    if (!$theme) {
        Logger::infoTo('theme-debug', 'theme.frontend.apply.default', 'Frontend theme fell back to default', [
            'site_id' => $site['id'] ?? null,
            'site_name' => $site['name'] ?? null,
            'page_key' => $pageKey,
            'reason' => 'no resolved theme',
        ]);
        return [$html, $css, $js, false];
    }

    $themeCss = $registry->getCssForPage($theme, $pageKey);
    $themeJs = $registry->getJsForPage($theme, $pageKey);
    $themeLayout = $registry->getLayoutHtmlForPage($theme, $pageKey);
    $sharedCss = $registry->getSharedComponentCss();

    $themeKeyForLayout = (string)($theme['key'] ?? '');
    if (\App\Services\Render\ThemeLayoutRenderer::hasPhtmlLayout($themeKeyForLayout)) {
        // Phase 3: the active theme renders its layout chrome through a PHTML view
        // (Templates/<key>/views/layout.phtml). Byte-equivalent to applyLayout().
        $html = \App\Services\Render\ThemeLayoutRenderer::render($themeKeyForLayout, $html, $siteName, $nav);
    } elseif ($themeLayout !== '') {
        $html = $renderer->applyLayout($themeLayout, $html, $siteName, $nav);
    }
    $html = $renderer->stripInlineStyles($html);
    $html = $registry->injectBodyClass($html, $themeKeyForLayout);

    if ($themeCss !== '') {
        $css = trim($css . "\n" . $sharedCss . "\n" . $themeCss);
    } elseif ($sharedCss !== '') {
        $css = trim($css . "\n" . $sharedCss);
    }

    if ($themeJs !== '') {
        $js = trim($js . "\n" . $themeJs);
    }

    Logger::infoTo('theme-debug', 'theme.frontend.apply.result', 'Frontend theme apply resolved', [
        'site_id' => $site['id'] ?? null,
        'site_name' => $site['name'] ?? null,
        'page_key' => $pageKey,
        'resolved_theme_key' => $theme['key'] ?? null,
        'resolved_theme_name' => $theme['name'] ?? null,
        'layout_applied' => $themeLayout !== '',
        'css_len' => strlen($css),
        'js_len' => strlen($js),
        'html_len' => strlen($html),
    ]);

    return [$html, $css, $js, true];
}

function resolveThemeNav(array $site, bool $isHome): string
{
    $nav = '';
    if ($isHome && !empty($site['layout_nav_home'])) {
        $nav = (string)$site['layout_nav_home'];
    } elseif (!empty($site['layout_nav_sub'])) {
        $nav = (string)$site['layout_nav_sub'];
    }

    return \App\Services\Render\NavigationNormalizer::ensureReviewsLink($nav);
}

function findPageIdForHeroPath(string $path, int $siteId): ?int
{
    try {
        $page = (new PageRepository())->findByUrlPath(trim($path, '/'));
        if (!$page || (int)($page['site_id'] ?? 0) !== $siteId) {
            return null;
        }
        return (int)$page['id'];
    } catch (\Throwable $e) {
        return null;
    }
}

function publicMediaUrl(string $storagePath): string
{
    return \App\Services\Render\MediaUrlResolver::publicMediaUrl($storagePath);
}

function renderPortfolioNav(string $siteName, string $active = 'portfolio'): string
{
    $h = fn($v) => htmlspecialchars($v, ENT_QUOTES);
    $brand = $h($siteName);
    $servicesClass = $active === 'services' ? ' class="nav-active"' : '';
    $portfolioClass = $active === 'portfolio' ? ' class="nav-active"' : '';

    return <<<HTML
<nav class="nav" id="sitenav">
  <div class="container nav-inner">
    <a href="/" class="brand">{$brand}</a>
    <div class="nav-links">
      <a href="/sluzby"{$servicesClass}>Sluzby</a>
      <a href="/#nase-prace"{$portfolioClass}>Naše práce</a>
      <a href="/#reference">Reference</a>
      <a href="/#kontakt" class="nav-cta">Kontakt</a>
    </div>
  </div>
</nav>
HTML;
}

function tryServeServicesPage(string $uri): bool
{
    if ($uri !== '/sluzby') {
        return false;
    }

    $site = (new SiteRepository())->findFirst();
    if (!$site) {
        return false;
    }

    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
    $siteName = (string)($site['name'] ?? 'RB Videos & Photos');
    $page = publicPageForPath('sluzby');
    $pageBlocks = pageBlockMap($page);
    [$heroTitleKey, $heroTitle, $heroTitleType] = firstBlockValue($pageBlocks, ['hero_h1', 'hero_heading'], 'Naše služby');
    [$heroSubKey, $heroSub, $heroSubType] = firstBlockValue(
        $pageBlocks,
        ['hero_sub', 'hero_intro'],
        'Od svateb přes portréty až po firemní vizuál. Každou zakázku stavíme na emocích, detailu a profesionálním výsledku.'
    );
    $editMode = Auth::check() && !isset($_GET['cms_preview']) && $page !== null;

    $sluzbyData = SluzbyPageData::forSite(
        (int)$site['id'],
        (string)($pageBlocks['hero_bg']['content'] ?? '')
    );
    $css = rtrim((string)file_get_contents(ViewFactory::base() . '/app/Views/assets/sluzby.css'), "\n");

    $renderer = new PageRenderer();
    $nav = resolveThemeNav($site, false);
    $previewKeyParam = isset($_GET['theme_preview']) ? trim((string)$_GET['theme_preview']) : null;
    $activeThemeKey = resolveActiveThemeKeyForSite($site, $previewKeyParam);
    $pageContent = ViewFactory::forTheme($activeThemeKey, ['editMode' => $editMode])->render('archetypes/sluzby', [
        'hero' => [
            'title' => $heroTitle,
            'sub' => $heroSub,
            'titleKey' => $heroTitleKey,
            'titleType' => $heroTitleType,
            'subKey' => $heroSubKey,
            'subType' => $heroSubType,
        ],
        'items' => $sluzbyData['items'],
        'heroImage' => $sluzbyData['heroImage'],
    ]);
    [$pageContent, $themeCss, $themeJs, $themeActive] = applyThemeTemplate(
        $renderer,
        $site,
        'sluzby',
        $siteName,
        $pageContent,
        '',
        '',
        '',
        $nav,
        $previewKeyParam
    );

    if ($themeActive) {
        $html = $renderer->injectAssets($pageContent, $css . "\n" . $themeCss, $themeJs);
    } else {
        $fallbackNav = renderPortfolioNav($siteName, 'services');
        $fallbackFooter = buildRichFooterHtml((int)$site['id']);
        $html = <<<HTML
<!DOCTYPE html><html lang="cs"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>{$h('Služby | ' . $siteName)}</title>
</head><body data-cms-page-key="sluzby">{$fallbackNav}<main>{$pageContent}</main><footer>{$fallbackFooter}</footer></body></html>
HTML;
        $html = $renderer->injectAssets($html, $css, '');
    }

    $html = injectFooterSocials($html, (int)$site['id']);
    $html = injectSiteLogo($html, $site);
    $html = injectPageHeroVideo($html, $site, isset($page['id']) ? (int)$page['id'] : findPageIdForHeroPath('sluzby', (int)$site['id']), 'sluzby');
    $html = injectCmsEditorChrome($html, '/sluzby', $heroTitle, isset($page['id']) ? (int)$page['id'] : null, Auth::check() && isset($_GET['cms_preview']));
    $html = injectBreadcrumbTrail($html, $site, breadcrumbItems('Služby', '/sluzby'));
    $html = injectPageHeadMetadata($html, $site, [
        'title' => 'Služby | ' . $siteName,
        'description' => trim((string)$heroSub),
        'path' => '/sluzby',
    ]);
    echo sanitizeFrontendHtml($html, $site);
    return true;
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

function tryRedirectConfiguredPath(string $uri): bool
{
    $site = (new SiteRepository())->findFirst();
    if (!$site) {
        return false;
    }
    $redirect = (new RedirectRepository())->findByPath((int)$site['id'], $uri);
    if (!$redirect) {
        return false;
    }

    $target = trim((string)($redirect['to_path'] ?? ''));
    if ($target === '' || $target === $uri || !str_starts_with($target, '/')) {
        throw new RuntimeException('Neplatné interní přesměrování pro ' . $uri);
    }
    $query = trim((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY));
    if ($query !== '') {
        $target .= (str_contains($target, '?') ? '&' : '?') . $query;
    }
    $status = in_array((int)($redirect['status_code'] ?? 301), [301, 302], true)
        ? (int)$redirect['status_code']
        : 301;
    header('Location: ' . $target, true, $status);
    return true;
}

function tryServePortfolioPage(string $uri, bool $detailOnly = false): bool
{
    $site = (new SiteRepository())->findFirst();
    if (!$site) {
        return false;
    }

    $repo = new PortfolioRepository();
    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
    $siteName = (string)($site['name'] ?? 'RB Videos & Photos');

    if (!$detailOnly && $uri === '/nase-prace') {
        $page = publicPageForPath('nase-prace');
        $pageBlocks = pageBlockMap($page);
        [$heroTitleKey, $heroTitle, $heroTitleType] = firstBlockValue($pageBlocks, ['hero_h1', 'hero_heading'], 'Naše práce');
        [$heroSubKey, $heroSub, $heroSubType] = firstBlockValue($pageBlocks, ['hero_sub', 'hero_intro'], 'Ukázka videí a galerií z naší práce.');
        $editMode = Auth::check() && !isset($_GET['cms_preview']) && $page !== null;
        $siteNameEsc = $h($siteName);

        // CSS specific to portfolio pages - nav/footer/base reset comes from layout_css when layout is active
        $portfolioListingCss = rtrim((string)file_get_contents(ViewFactory::base() . '/app/Views/assets/portfolio-listing.css'), "\n");

        $renderer   = new PageRenderer();
        $nav = resolveThemeNav($site, false);
        $previewKeyParam = isset($_GET['theme_preview']) ? trim((string)$_GET['theme_preview']) : null;
        $activeThemeKey = resolveActiveThemeKeyForSite($site, $previewKeyParam);
        $portfolioData = PortfolioListingData::forSite((int)$site['id']);
        $pageContent = ViewFactory::forTheme($activeThemeKey, ['editMode' => $editMode])->render('archetypes/portfolio-listing', [
            'hero' => [
                'title' => $heroTitle,
                'sub' => $heroSub,
                'titleKey' => $heroTitleKey,
                'titleType' => $heroTitleType,
                'subKey' => $heroSubKey,
                'subType' => $heroSubType,
            ],
            'entries' => $portfolioData['entries'],
            'mixedEntries' => $portfolioData['mixedEntries'],
            'photoEntries' => $portfolioData['photoEntries'],
            'videoEntries' => $portfolioData['videoEntries'],
            // The listing's own copy (kickers, headings, empty state, CTA) is
            // stored per page like any other block, so the view needs them.
            'blocks' => array_values($pageBlocks),
        ]);
        [$pageContent, $themeCss, $themeJs, $themeActive] = applyThemeTemplate(
            $renderer,
            $site,
            'portfolio',
            $siteName,
            $pageContent,
            '',
            '',
            '',
            $nav,
            $previewKeyParam
        );
        $titleTag = '<title>' . $h("Na\u{161}e pr\u{E1}ce | {$siteName}") . '</title>';
        if ($themeActive) {
            $html = $pageContent;
            $html = str_replace('</head>', "\n{$titleTag}\n</head>", $html);
            $html = $renderer->injectAssets($html, $portfolioListingCss . "\n" . $themeCss, $themeJs);
        } elseif (!empty($site['layout_html'])) {
            $html = $renderer->applyLayout($site['layout_html'], $pageContent, $siteName, $nav);
            $html = str_replace('</head>', "\n{$titleTag}\n</head>", $html);
            $html = $renderer->injectAssets($html, ($site['layout_css'] ?? '') . "\n" . $portfolioListingCss . "\n" . $themeCss, ($site['layout_js'] ?? '') . "\n" . $themeJs);
        } else {
            $nav = renderPortfolioNav($siteName, 'portfolio');
            $html = <<<HTML
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Naše práce | {$siteNameEsc}</title>
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
{$pageContent}
<footer>{$siteNameEsc}</footer>
</body>
</html>
HTML;
        }

        header('Content-Type: text/html; charset=UTF-8');
        $html = injectPageHeroVideo($html, $site, findPageIdForHeroPath('nase-prace', (int)$site['id']), 'portfolio');
        $html = injectFooterSocials($html, (int)$site['id']);
        $html = injectSiteLogo($html, $site);
        $html = injectCmsEditorChrome($html, $uri, $heroTitle, isset($page['id']) ? (int)$page['id'] : null, Auth::check() && isset($_GET['cms_preview']));
        $html = injectBreadcrumbTrail($html, $site, breadcrumbItems('Naše práce', '/nase-prace'));
        $html = injectPageHeadMetadata($html, $site, [
            'title' => "Na\u{161}e pr\u{E1}ce | " . $siteName,
            'description' => $heroSub,
            'path' => '/nase-prace',
        ]);
        echo sanitizeFrontendHtml($html, $site);
        return true;
    }

    $slug = trim($uri, '/');
    if ($slug !== '' && $slug !== 'nase-prace') {
        $entry = $repo->findBySlug($slug);
        if (!$entry) {
            return false;
        }

        $detailData = PortfolioDetailData::forEntry($entry);

        $title = $h($entry['title']);
        $siteNameEsc = $h($siteName);
        $metaDescription = $h($detailData['entry']['meta_description']);

        // CSS specific to portfolio detail - nav/footer/base reset comes from layout_css when layout is active
        $portfolioDetailCss = rtrim((string)file_get_contents(ViewFactory::base() . '/app/Views/assets/portfolio-detail.css'), "\n");

        $renderer = new PageRenderer();
        $nav = resolveThemeNav($site, false);
        $previewKeyParam = isset($_GET['theme_preview']) ? trim((string)$_GET['theme_preview']) : null;
        $activeThemeKey = resolveActiveThemeKeyForSite($site, $previewKeyParam);
        $pageContent = ViewFactory::forTheme($activeThemeKey)->render('archetypes/portfolio-detail', [
            'entry' => $detailData['entry'],
            'gallery' => $detailData['gallery'],
        ]);
        [$pageContent, $themeCss, $themeJs, $themeActive] = applyThemeTemplate(
            $renderer,
            $site,
            'portfolio-detail',
            $siteName,
            $pageContent,
            '',
            '',
            '',
            $nav,
            $previewKeyParam
        );
        $metaTitle = (string)$detailData['entry']['meta_title'];
        $titleTag = '<title>' . $h($metaTitle) . '</title>';
        $metaDesc = '<meta name="description" content="' . $metaDescription . '">';
        if ($themeActive) {
            $html = $pageContent;
            $html = str_replace('</head>', "\n{$titleTag}\n{$metaDesc}\n</head>", $html);
            $html = $renderer->injectAssets($html, $portfolioDetailCss . "\n" . $themeCss, $themeJs);
        } elseif (!empty($site['layout_html'])) {
            $html = $renderer->applyLayout($site['layout_html'], $pageContent, $siteName, $nav);
            $html = str_replace('</head>', "\n{$titleTag}\n{$metaDesc}\n</head>", $html);
            $html = $renderer->injectAssets($html, ($site['layout_css'] ?? '') . "\n" . $portfolioDetailCss . "\n" . $themeCss, ($site['layout_js'] ?? '') . "\n" . $themeJs);
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
{$pageContent}
<footer>{$siteNameEsc}</footer>
</body>
</html>
HTML;
        }

        header('Content-Type: text/html; charset=UTF-8');
        $html = injectFooterSocials($html, (int)$site['id']);
        $html = injectSiteLogo($html, $site);
        $html = injectBreadcrumbTrail($html, $site, breadcrumbItems($entry['title'] ?? 'Detail prace', '/' . $slug, [
            ['label' => 'Naše práce', 'url' => '/nase-prace'],
        ]));
        $html = injectPageHeadMetadata($html, $site, [
            'title' => $metaTitle,
            'description' => $detailData['entry']['meta_description'],
            'path' => '/' . $slug,
            'canonical' => '/' . $slug,
            'og_image' => $detailData['entry']['hero_image'],
            'json_ld' => [renderPortfolioVideoJsonLd(
                $site,
                $entry,
                absolutePublicUrl($site, '/' . $slug),
                $detailData['entry']['hero_image']
            )],
        ]);
        echo sanitizeFrontendHtml($html, $site);
        return true;
    }

    return false;
}
function tryServeFaqPage(): bool
{
    $site = (new SiteRepository())->findFirst();
    if (!$site) return false;

    try {
        $faqData = FaqPageData::forSite((int)$site['id']);
    } catch (\Throwable $e) {
        return false;
    }
    $items = $faqData['items'];

    $h        = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
    $siteName = (string)($site['name'] ?? 'RB Videos & Photos');
    $page       = (new PageRepository())->findPublishedByUrlPath('faq');
    $heroTitle  = 'Časté dotazy';
    $heroSub    = 'Odpovědi na nejčastější dotazy.';
    if ($page) {
        $pageBlocks = (new BlockRepository())->allByPage((int)$page['id']);
        foreach ($pageBlocks as $b) {
            if ($b['block_key'] === 'hero_h1' && $b['content']) $heroTitle = (string)$b['content'];
            if ($b['block_key'] === 'hero_sub' && $b['content']) $heroSub  = (string)$b['content'];
        }
    }

    $page = publicPageForPath('faq');
    $pageBlocks = pageBlockMap($page);
    [$heroTitleKey, $heroTitle, $heroTitleType] = firstBlockValue($pageBlocks, ['hero_h1', 'hero_heading'], $heroTitle);
    [$heroSubKey, $heroSub, $heroSubType] = firstBlockValue($pageBlocks, ['hero_sub', 'hero_intro'], $heroSub);
    $editMode = Auth::check() && !isset($_GET['cms_preview']) && $page !== null;

    $faqCss = rtrim((string)file_get_contents(ViewFactory::base() . '/app/Views/assets/faq.css'), "\n");

    $renderer   = new PageRenderer();
    $nav        = resolveThemeNav($site, false);
    $previewKeyParam = isset($_GET['theme_preview']) ? trim((string)$_GET['theme_preview']) : null;
    $activeThemeKey = resolveActiveThemeKeyForSite($site, $previewKeyParam);
    $pageContent = ViewFactory::forTheme($activeThemeKey, ['editMode' => $editMode])->render('archetypes/faq', [
        'hero' => [
            'title' => $heroTitle,
            'sub' => $heroSub,
            'titleKey' => $heroTitleKey,
            'titleType' => $heroTitleType,
            'subKey' => $heroSubKey,
            'subType' => $heroSubType,
        ],
        'items' => $items,
    ]);
    [$pageContent, $themeCss, $themeJs, $themeActive] = applyThemeTemplate(
        $renderer,
        $site,
        'faq',
        $siteName,
        $pageContent,
        '',
        '',
        '',
        $nav,
        $previewKeyParam
    );
    $faqDescription = trim((string)$heroSub) !== '' ? trim((string)$heroSub) : 'Odpovedi na caste dotazy k rezervaci, terminum, foceni a vystupum.';
    if ($themeActive) {
        $html = $pageContent;
        $html = $renderer->injectAssets($html, $faqCss . "\n" . $themeCss, $themeJs);
    } elseif (!empty($site['layout_html'])) {
        $html = $renderer->applyLayout($site['layout_html'], $pageContent, $siteName, $nav);
        $html = $renderer->injectAssets($html, ($site['layout_css'] ?? '') . "\n" . $faqCss . "\n" . $themeCss, ($site['layout_js'] ?? '') . "\n" . $themeJs);
    } else {
        $html = '<!DOCTYPE html><html lang="cs"><head><meta charset="UTF-8"><title>FAQ | ' . $h($siteName) . '</title>'
            . '<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:system-ui,sans-serif;line-height:1.6}.container{max-width:960px;margin:0 auto;padding:0 24px}'
            . $faqCss . '</style></head><body>'
            . $pageContent
            . '</body></html>';
    }

    header('Content-Type: text/html; charset=UTF-8');
    $html = injectPageHeroVideo($html, $site, findPageIdForHeroPath('faq', (int)$site['id']), 'faq');
    $html = injectFooterSocials($html, (int)$site['id']);
    $html = injectSiteLogo($html, $site);
    $html = injectCmsEditorChrome($html, '/faq', $heroTitle, isset($page['id']) ? (int)$page['id'] : null, Auth::check() && isset($_GET['cms_preview']));
    $html = injectBreadcrumbTrail($html, $site, breadcrumbItems('FAQ', '/faq'));
    $html = injectPageHeadMetadata($html, $site, [
        'title' => 'FAQ | ' . $siteName,
        'description' => $faqDescription,
        'path' => '/faq',
        'json_ld' => [renderFaqPageJsonLd($site, $items)],
    ]);
    echo sanitizeFrontendHtml($html, $site);
    return true;
}

function tryServeKontaktPage(): bool
{
    $site = (new SiteRepository())->findFirst();
    if (!$site) return false;

    try {
        $contact = enrichContactWithLegacyBlocks((int)$site['id'], (new ContactRepository())->findBySite((int)$site['id']) ?: []);
    } catch (\Throwable $e) {
        return false;
    }

    $h         = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
    $siteName  = trim((string)($site['name'] ?? ''));
    if ($siteName === '' || preg_match('~erbez~i', $siteName)) {
        $siteName = 'RB Videos and Photos';
    }
    $heroTitle = 'Pojďme si říct o vašem focení nebo videu.';
    $heroSub   = 'Napište nám termín, místo a představu. Ozveme se vám co nejdříve a navrhneme to nejlepší řešení.';

    $page = (new PageRepository())->findPublishedByUrlPath('kontakt');
    if ($page) {
        $pageBlocks = (new BlockRepository())->allByPage((int)$page['id']);
        foreach ($pageBlocks as $b) {
            if ($b['block_key'] === 'hero_h1' && $b['content']) {
                $heroTitle = (string)$b['content'];
            }
            if ($b['block_key'] === 'hero_sub' && $b['content']) {
                $heroSub = (string)$b['content'];
            }
        }
    }
    $page = publicPageForPath('kontakt');
    $pageBlocks = pageBlockMap($page);
    [$heroTitleKey, $heroTitle, $heroTitleType] = firstBlockValue($pageBlocks, ['hero_h1', 'hero_heading'], $heroTitle);
    [$heroSubKey, $heroSub, $heroSubType] = firstBlockValue($pageBlocks, ['hero_sub', 'hero_intro'], $heroSub);
    if (trim((string)$heroTitle) === 'Kontakt') {
        $heroTitle = 'Pojďme si říct o vašem focení nebo videu.';
    }
    $plainHeroSub = trim((string)$heroSub);
    if ($plainHeroSub === 'Ozvěte se nám. Rádi odpovíme na vaše otázky.' || $plainHeroSub === 'Rádi odpovíme na vaše otázky.') {
        $heroSub = 'Napište nám termín, místo a představu. Ozveme se vám co nejdříve a navrhneme to nejlepší řešení.';
    }
    $editMode = Auth::check() && !isset($_GET['cms_preview']) && $page !== null;
    $csrfToken = \App\Core\Auth::csrfToken();
    $sentState = isset($_GET['sent']) ? (string)$_GET['sent'] : null;
    $kontaktCss = rtrim((string)file_get_contents(ViewFactory::base() . '/app/Views/assets/kontakt.css'), "\n");

    $packageId = isset($_GET['balicek']) ? (string) $_GET['balicek'] : null;
    $kontaktData = KontaktPageData::forSite((int)$site['id'], $csrfToken, $sentState, $packageId);

    $renderer   = new PageRenderer();
    $nav        = resolveThemeNav($site, false);
    $previewKeyParam = isset($_GET['theme_preview']) ? trim((string)$_GET['theme_preview']) : null;
    $activeThemeKey = resolveActiveThemeKeyForSite($site, $previewKeyParam);
    $pageContent = ViewFactory::forTheme($activeThemeKey, ['editMode' => $editMode])->render('archetypes/kontakt', [
        'hero' => [
            'title' => $heroTitle,
            'sub' => $heroSub,
            'titleKey' => $heroTitleKey,
            'titleType' => $heroTitleType,
            'subKey' => $heroSubKey,
            'subType' => $heroSubType,
        ],
        'contactCards' => $kontaktData['contactCards'],
        'mapsUrl' => $kontaktData['mapsUrl'],
        'heroActions' => $kontaktData['heroActions'],
        'faqItems' => $kontaktData['faqItems'],
        'form' => $kontaktData['form'],
    ]);
    [$pageContent, $themeCss, $themeJs, $themeActive] = applyThemeTemplate(
        $renderer,
        $site,
        'contact',
        $siteName,
        $pageContent,
        '',
        '',
        '',
        $nav,
        $previewKeyParam
    );
    $contactDescription = trim((string)$heroSub) !== '' ? trim((string)$heroSub) : 'Kontaktní formulář a informace pro poptavku foceni nebo videa.';
    if ($themeActive) {
        $html = $pageContent;
        $html = $renderer->injectAssets($html, $kontaktCss . "\n" . $themeCss, $themeJs);
    } elseif (!empty($site['layout_html'])) {
        $html = $renderer->applyLayout($site['layout_html'], $pageContent, $siteName, $nav);
        $html = $renderer->injectAssets($html, ($site['layout_css'] ?? '') . "\n" . $kontaktCss . "\n" . $themeCss, ($site['layout_js'] ?? '') . "\n" . $themeJs);
    } else {
        $html = '<!DOCTYPE html><html lang="cs"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Kontakt | ' . $h($siteName) . '</title>'
            . '<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:system-ui,sans-serif;line-height:1.6;background:#111;color:#fff}.container{max-width:1100px;margin:0 auto;padding:0 16px}' . $kontaktCss . '</style>'
            . '</head><body>'
            . $pageContent
            . '<footer><div class="container">' . $h($siteName) . '</div></footer></body></html>';
    }

    header('Content-Type: text/html; charset=UTF-8');
    $html = injectFooterSocials($html, (int)$site['id']);
    $html = injectSiteLogo($html, $site);
    $html = injectPageHeroVideo($html, $site, isset($page['id']) ? (int)$page['id'] : findPageIdForHeroPath('kontakt', (int)$site['id']), 'contact');
    $html = injectCmsEditorChrome($html, '/kontakt', $heroTitle, isset($page['id']) ? (int)$page['id'] : null, Auth::check() && isset($_GET['cms_preview']));
    $html = injectBreadcrumbTrail($html, $site, breadcrumbItems('Kontakt', '/kontakt'));
    $html = injectPageHeadMetadata($html, $site, [
        'title' => 'Kontakt | ' . $siteName,
        'description' => $contactDescription,
        'path' => '/kontakt',
        'json_ld' => [renderContactPageJsonLd($site, $contact)],
    ]);
    echo sanitizeFrontendHtml($html, $site);
    return true;
}

function tryServeBlogListingPage(): bool
{
    $site = (new SiteRepository())->findFirst();
    if (!$site) {
        return false;
    }

    $posts     = (new BlogRepository())->allPublished((int)$site['id']);
    $h         = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
    $siteName  = (string)($site['name'] ?? 'RB Videos & Photos');
    $page = publicPageForPath('blog');
    $pageBlocks = pageBlockMap($page);
    [$heroTitleKey, $heroTitle, $heroTitleType] = firstBlockValue($pageBlocks, ['hero_h1', 'hero_heading'], 'Blog & Inspirace');
    [$heroSubKey, $heroSub, $heroSubType] = firstBlockValue($pageBlocks, ['hero_sub', 'hero_intro'], 'Tipy a rady od profesionálního fotografa z Olomouce.');
    $editMode = Auth::check() && !isset($_GET['cms_preview']) && $page !== null;

    $blogCss = rtrim((string)file_get_contents(ViewFactory::base() . '/app/Views/assets/blog-listing.css'), "\n");

    $renderer = new PageRenderer();
    $nav = resolveThemeNav($site, false);
    $previewKeyParam = isset($_GET['theme_preview']) ? trim((string)$_GET['theme_preview']) : null;
    $activeThemeKey = resolveActiveThemeKeyForSite($site, $previewKeyParam);
    $pageContent = ViewFactory::forTheme($activeThemeKey, ['editMode' => $editMode])->render('archetypes/blog-listing', [
        'hero' => [
            'title' => $heroTitle,
            'sub' => $heroSub,
            'titleKey' => $heroTitleKey,
            'titleType' => $heroTitleType,
            'subKey' => $heroSubKey,
            'subType' => $heroSubType,
        ],
        'posts' => BlogListingData::forSite((int)$site['id'])['posts'],
        // The listing's own copy (kickers, empty state, CTA) is stored per page
        // like any other block, so the view needs them.
        'blocks' => array_values($pageBlocks),
    ]);
    [$pageContent, $themeCss, $themeJs, $themeActive] = applyThemeTemplate(
        $renderer,
        $site,
        'blog',
        $siteName,
        $pageContent,
        '',
        '',
        '',
        $nav,
        $previewKeyParam
    );
    $blogDescription = trim((string)$heroSub) !== '' ? trim((string)$heroSub) : 'Tipy, odpovedi a inspirace z oblasti foceni a videa.';
    if ($themeActive) {
        $html = $pageContent;
        $html = $renderer->injectAssets($html, $blogCss . "\n" . $themeCss, $themeJs);
    } elseif (!empty($site['layout_html'])) {
        $html = $renderer->applyLayout($site['layout_html'], $pageContent, $siteName, $nav);
        $html = $renderer->injectAssets($html, ($site['layout_css'] ?? '') . "\n" . $blogCss . "\n" . $themeCss, ($site['layout_js'] ?? '') . "\n" . $themeJs);
    } else {
        $siteNameEsc = $h($siteName);
        $html = <<<HTML
<!DOCTYPE html><html lang="cs"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Blog | {$siteNameEsc}</title>
  <style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:system-ui,-apple-system,sans-serif;line-height:1.6}.container{max-width:1180px;margin:0 auto;padding:0 24px}{$blogCss}footer{padding:28px 24px;border-top:1px solid rgba(255,255,255,.08);text-align:center;color:rgba(255,255,255,.46)}</style>
</head><body>
{$pageContent}
<footer>{$siteNameEsc}</footer></body></html>
HTML;
    }

    header('Content-Type: text/html; charset=UTF-8');
    $html = injectFooterSocials($html, (int)$site['id']);
    $html = injectSiteLogo($html, $site);
    $html = injectPageHeroVideo($html, $site, isset($page['id']) ? (int)$page['id'] : findPageIdForHeroPath('blog', (int)$site['id']), 'blog');
    $html = injectCmsEditorChrome($html, '/blog', $heroTitle, isset($page['id']) ? (int)$page['id'] : null, Auth::check() && isset($_GET['cms_preview']));
    $html = injectBreadcrumbTrail($html, $site, breadcrumbItems('Blog', '/blog'));
    $html = injectPageHeadMetadata($html, $site, [
        'title' => 'Blog | ' . $siteName,
        'description' => $blogDescription,
        'path' => '/blog',
        'json_ld' => [renderBlogListingJsonLd($site, $posts)],
    ]);
    echo sanitizeFrontendHtml($html, $site);
    return true;
}

function tryServeBlogPostPage(string $uri): bool
{
    $slug = trim($uri, '/');
    if ($slug === '') {
        return false;
    }

    $post = (new BlogRepository())->findBySlug($slug);
    if (!$post) {
        return false;
    }

    $site = (new SiteRepository())->findFirst();
    if (!$site) {
        return false;
    }

    $h        = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
    $siteName = (string)($site['name'] ?? 'RB Videos & Photos');

    $blogPostData = BlogPostData::forPost($post);

    $rawMetaTitle = trim((string)($post['meta_title'] ?? ''));
    $fullTitle = $rawMetaTitle !== '' ? $rawMetaTitle : (string)$post['title'];
    $fullTitle = preg_replace('~\s*\|\s*RB\s+(?:Photos\s*&\s*Videos|Videos\s*&\s*Photos)\s*$~iu', '', $fullTitle) ?? $fullTitle;
    if ($siteName !== '' && !preg_match('~\|\s*' . preg_quote($siteName, '~') . '\s*$~u', $fullTitle)) {
        $fullTitle .= ' | ' . $siteName;
    }
    $metaTitle = $h($fullTitle);
    $metaDesc  = $h($post['meta_description'] ?: $post['excerpt'] ?: '');
    $canonical = absolutePublicUrl($site, '/' . $slug);
    $featuredImageUrl = $blogPostData['post']['image_url'] !== ''
        ? absolutePublicUrl($site, $blogPostData['post']['image_url'])
        : null;

    $blogPostCss = rtrim((string)file_get_contents(ViewFactory::base() . '/app/Views/assets/blog-detail.css'), "\n");

    $renderer = new PageRenderer();
    $nav = resolveThemeNav($site, false);
    $previewKeyParam = isset($_GET['theme_preview']) ? trim((string)$_GET['theme_preview']) : null;
    $activeThemeKey = resolveActiveThemeKeyForSite($site, $previewKeyParam);
    $pageContent = ViewFactory::forTheme($activeThemeKey)->render('archetypes/blog-detail', [
        'post' => $blogPostData['post'],
        'gallery' => $blogPostData['gallery'],
    ]);
    [$pageContent, $themeCss, $themeJs, $themeActive] = applyThemeTemplate(
        $renderer,
        $site,
        'blog-post',
        $siteName,
        $pageContent,
        '',
        '',
        '',
        $nav,
        $previewKeyParam
    );
    if ($themeActive) {
        $html = $pageContent;
        $html = $renderer->injectAssets($html, $blogPostCss . "\n" . $themeCss, $themeJs);
    } elseif (!empty($site['layout_html'])) {
        $html = $renderer->applyLayout($site['layout_html'], $pageContent, $siteName, $nav);
        $html = $renderer->injectAssets($html, ($site['layout_css'] ?? '') . "\n" . $blogPostCss . "\n" . $themeCss, ($site['layout_js'] ?? '') . "\n" . $themeJs);
    } else {
        $siteNameEsc = $h($siteName);
        $html = <<<HTML
<!DOCTYPE html><html lang="cs"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>{$metaTitle} | {$siteNameEsc}</title>
  <meta name="description" content="{$metaDesc}">
  <style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:system-ui,-apple-system,sans-serif;line-height:1.6}.container{max-width:1180px;margin:0 auto;padding:0 24px}{$blogPostCss}footer{padding:28px 24px;border-top:1px solid rgba(255,255,255,.08);text-align:center;color:rgba(255,255,255,.46)}</style>
</head><body>
{$pageContent}
<footer>{$siteNameEsc}</footer></body></html>
HTML;
    }

    header('Content-Type: text/html; charset=UTF-8');
    $html = injectFooterSocials($html, (int)$site['id']);
    $html = injectSiteLogo($html, $site);
    $html = injectBreadcrumbTrail($html, $site, breadcrumbItems($post['title'] ?? 'Článek', '/' . $slug, [
        ['label' => 'Blog', 'url' => '/blog'],
    ]));
    $html = injectPageHeadMetadata($html, $site, [
        'title' => $fullTitle,
        'description' => html_entity_decode($metaDesc, ENT_QUOTES, 'UTF-8'),
        'path' => '/' . $slug,
        'canonical' => $canonical,
        'og_type' => 'article',
        'og_image' => $featuredImageUrl ?? '',
        'json_ld' => [renderBlogPostJsonLd($site, $post, $canonical, $featuredImageUrl)],
    ]);
    echo sanitizeFrontendHtml($html, $site);
    return true;
}

// The golden-master test harness (tests/GoldenMasterRunner.php) requires this file
// once to obtain the tryServe*() function definitions and the app bootstrap
// (Env/Config/Runtime/Auth) without triggering the routing dispatch below - it calls
// the tryServe*() functions directly per simulated route instead. This constant is
// never defined outside that harness, so real requests are unaffected.
if (defined('SEO_CMS_GOLDEN_BOOTSTRAP')) {
    return;
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

    if (str_starts_with($uri, '/media/')) {
        $mediaBase = realpath(dirname(__DIR__) . '/storage/media');
        $relative  = ltrim(substr($uri, strlen('/media/')), '/');
        $file      = realpath(dirname(__DIR__) . '/storage/media/' . $relative);
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

    if (tryRedirectConfiguredPath($uri)) {
        exit;
    }

    if ($uri === '/sluzby') {
        if (tryServeServicesPage($uri)) {
            exit;
        }
    }

    if ($uri === '/nase-prace') {
        if (tryServePortfolioPage($uri)) {
            exit;
        }
    }

    if ($uri === '/blog') {
        if (tryServeBlogListingPage()) {
            exit;
        }
    }

    if ($uri === '/faq') {
        if (tryServeFaqPage()) {
            exit;
        }
    }

    if ($uri === '/kontakt') {
        if (tryServeKontaktPage()) {
            exit;
        }
    }

    if ($uri === '/kontakt/odeslat' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        \App\Core\Auth::start();
        if (!\App\Core\Auth::csrfVerify((string)($_POST['_csrf'] ?? ''))) {
            header('Location: /kontakt?sent=csrf');
            exit;
        }
        // Honeypot spam check
        if (!empty($_POST['website'])) {
            header('Location: /kontakt');
            exit;
        }
        $contactThrottleKey = 'contact:' . RequestThrottle::currentIp();
        if (RequestThrottle::isLocked('contact_form', $contactThrottleKey, 10 * 60, 10 * 60)) {
            header('Location: /kontakt?sent=rate-limit');
            exit;
        }
        $site = (new SiteRepository())->findFirst();
        $formEmail = null;
        $formEmailCopy = '';
        $recipientLookupFailed = false;
        if ($site) {
            try {
                $ci = (new ContactRepository())->findBySite((int)$site['id']);
                $formEmail = trim((string)($ci['form_email'] ?? ''));
                $formEmailCopy = trim((string)($ci['form_email_cc'] ?? ''));
                if ($formEmail === '') {
                    $formEmail = trim((string)($ci['email'] ?? ''));
                }
            } catch (\Throwable $e) {
                $recipientLookupFailed = true;
                Logger::error('contact_form', 'Recipient lookup failed', ['type' => get_class($e)]);
            }
        }
        if (!$recipientLookupFailed && ($formEmail === null || $formEmail === '')) {
            $formEmail = defaultContactFormEmail();
        }
        $cleanHeaderValue = static fn (string $value): string => trim(str_ireplace(["\r", "\n", "%0a", "%0d"], '', $value));
        $name    = $cleanHeaderValue((string) ($_POST['name'] ?? ''));
        $contact = trim((string) ($_POST['contact_value'] ?? ''));
        $shootingType = trim($_POST['shooting_type'] ?? '');
        $eventPlace = trim((string) ($_POST['event_place'] ?? ''));
        $selectedPackageId = trim((string) ($_POST['selected_package_id'] ?? ''));
        $selectedPackage = PricingPackageCatalog::resolve($selectedPackageId);
        $invalidPackageSelection = $selectedPackageId !== '' && $selectedPackage === null;
        $message = trim($_POST['message']       ?? '');
        $privacyConsent = !empty($_POST['privacy_consent']);
        $contactMailSent = false;
        $contactSaved = false;
        if (!$invalidPackageSelection && $name && $contact && $shootingType && $message && $privacyConsent) {
            $subject = 'Nová zpráva z webu - ' . $name;
            $body    = "Jméno: {$name}\nKontakt: {$contact}\nTyp focení: {$shootingType}\nMísto akce: {$eventPlace}\n\nZpráva:\n{$message}";
            if ($selectedPackage !== null) {
                $body .= "\n\nVybraný balíček: {$selectedPackage['name']} ({$selectedPackage['price']})";
            }
            RequestThrottle::hit('contact_form', $contactThrottleKey, 3, 10 * 60, 10 * 60);
            try {
                $archive = new \App\Services\MailArchiveService(\App\Core\Database::getInstance());
                $spam = (new \App\Services\ContactSpamFilter())->classify($name, $contact, $message);
                $delivery = $spam['isSpam']
                    ? $archive->archiveSpam((string) $formEmail, mb_substr($subject, 0, 255), $body, $spam['reasons'], $formEmailCopy)
                    : $archive->send('contact', (string) $formEmail, mb_substr($subject, 0, 255), $body, '', $formEmailCopy);
                $contactSaved = true;
                $contactMailSent = $delivery['status'] === 'accepted';
                if ($delivery['status'] === 'spam') {
                    Logger::warning('contact_form', 'Static spam filter suppressed contact email', [
                        'archive_id' => $delivery['id'],
                        'score' => $spam['score'],
                        'reasons' => $spam['reasons'],
                    ]);
                }
            } catch (\Throwable $e) {
                Logger::error('contact_form', 'Contact message could not be archived; mail not sent', ['type' => get_class($e)]);
            }
        } else {
            Logger::warning('contact_form', 'Contact form submission rejected before mail send', [
                'ip' => RequestThrottle::currentIp(),
                'has_name' => $name !== '',
                'has_contact' => $contact !== '',
                'has_shooting_type' => $shootingType !== '',
                'has_message' => $message !== '',
                'has_privacy_consent' => $privacyConsent,
                'has_valid_recipient' => $formEmail !== null && filter_var((string) $formEmail, FILTER_VALIDATE_EMAIL) !== false,
                'has_invalid_package_selection' => $invalidPackageSelection,
            ]);
        }
        $redirectStatus = $invalidPackageSelection
            ? 'invalid-package'
            : ($contactMailSent ? '1' : ($contactSaved ? 'saved' : (($name && $contact && $shootingType && $message && $privacyConsent) ? 'error' : 'invalid')));
        $redirectQuery = ['sent' => $redirectStatus];
        if ($selectedPackage !== null) {
            $redirectQuery['balicek'] = $selectedPackage['id'];
        }
        header('Location: /kontakt?' . http_build_query($redirectQuery));
        exit;
    }

    $previewMode = Auth::check() && isset($_GET['cms_preview']);
    if (tryServeDatabasePage($uri, Auth::check(), $previewMode)) {
        exit;
    }

    if (tryServeBlogPostPage($uri)) {
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
