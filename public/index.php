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
use App\Repositories\BlogRepository;
use App\Repositories\ContactRepository;
use App\Repositories\FaqRepository;
use App\Repositories\MediaRepository;
use App\Repositories\PageRepository;
use App\Repositories\PortfolioRepository;
use App\Repositories\SeoRepository;
use App\Repositories\SiteRepository;
use App\Repositories\TemplateRepository;
use App\Services\HomepageContentService;
use App\Services\PageRenderer;
use App\Services\ThemeTemplateRegistry;
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
        $themeKey = getThemeTemplateRegistry()->normalizePageKey((string)($page['url_path'] ?? ''));
        $isHome = ltrim((string)($page['url_path'] ?? ''), '/') === '';
        $themeNav = resolveThemeNav($site, $isHome);
        $themePreviewKey = isset($_GET['theme_preview']) ? trim((string)$_GET['theme_preview']) : null;
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
            $js = ($template['js_source'] ?? '') . "\n" . $themeJs;
        } elseif (!empty($site['layout_html'])) {
            $html = $renderer->applyLayout($site['layout_html'], $html, $site['name'] ?? '', $themeNav);
            $css  = ($site['layout_css'] ?? '') . "\n" . ($template['css_source'] ?? '') . "\n" . $themeCss;
            $js   = ($site['layout_js']  ?? '') . "\n" . ($template['js_source']  ?? '') . "\n" . $themeJs;
        } else {
            $css = ($template['css_source'] ?? '') . "\n" . $themeCss;
            $js  = ($template['js_source']  ?? '') . "\n" . $themeJs;
        }
        $html = $renderer->injectAssets($html, $css, $js);
        $html = $renderer->injectSeo(
            $html,
            $seoRenderer->renderHead($seo, $site, $pageUrl, $ogImageUrl, $twImageUrl),
            $seoRenderer->renderBodyScripts($site)
        );
        $html = injectFooterSocials($html, (int)$site['id']);
        $html = injectSiteLogo($html, $site);
        $html = injectPageHeroImage($html, (int)$page['id']);
        $html = injectHomepageHeroImage($html, (int)$site['id'], $isHome);
        $html = injectHomepageHeroVideo($html, (int)$site['id'], $isHome);
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

function injectHomepageHeroImage(string $html, int $siteId, bool $isHome): string
{
    if (!$isHome) {
        return $html;
    }

    $heroImage = resolveHomepageHeroImageUrl($siteId);
    if ($heroImage === '') {
        return $html;
    }

    $styleValue = '--theme-hero-image:url(\'' . str_replace("'", '%27', $heroImage) . '\')';

    if (preg_match('/<body([^>]*)>/i', $html, $m)) {
        $attrs = $m[1];
        $styleAttr = ' style="' . htmlspecialchars($styleValue . ';', ENT_QUOTES) . '"';
        if (preg_match('/\sstyle=(["\'])(.*?)\1/i', $attrs, $styleMatch)) {
            $existing = html_entity_decode($styleMatch[2], ENT_QUOTES, 'UTF-8');
            if (str_contains($existing, '--theme-hero-image')) {
                $existing = preg_replace('/--theme-hero-image\s*:\s*[^;]+;?/i', $styleValue . ';', $existing) ?? $existing;
            } else {
                $existing = rtrim($existing, '; ') . ';' . $styleValue . ';';
            }
            $attrs = preg_replace('/\sstyle=(["\'])(.*?)\1/i', ' style="' . htmlspecialchars($existing, ENT_QUOTES) . '"', $attrs, 1) ?? $attrs;
        } else {
            $attrs .= $styleAttr;
        }

        return preg_replace('/<body([^>]*)>/i', '<body' . $attrs . '>', $html, 1) ?? $html;
    }

    return $html;
}

function injectPageHeroImage(string $html, int $pageId): string
{
    $heroImage = resolvePageHeroImageUrl($pageId);
    if ($heroImage === '') {
        return $html;
    }

    return injectBodyCssVariable($html, '--theme-page-hero-image', "url('" . str_replace("'", '%27', $heroImage) . "')");
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

function injectBodyCssVariable(string $html, string $name, string $value): string
{
    $styleValue = $name . ':' . $value;

    if (preg_match('/<body([^>]*)>/i', $html, $m)) {
        $attrs = $m[1];
        $styleAttr = ' style="' . htmlspecialchars($styleValue . ';', ENT_QUOTES) . '"';
        $pattern = '/' . preg_quote($name, '/') . '\s*:\s*[^;]+;?/i';
        if (preg_match('/\sstyle=(["\'])(.*?)\1/i', $attrs, $styleMatch)) {
            $existing = html_entity_decode($styleMatch[2], ENT_QUOTES, 'UTF-8');
            if (preg_match($pattern, $existing)) {
                $existing = preg_replace($pattern, $styleValue . ';', $existing) ?? $existing;
            } else {
                $existing = rtrim($existing, '; ') . ';' . $styleValue . ';';
            }
            $attrs = preg_replace('/\sstyle=(["\'])(.*?)\1/i', ' style="' . htmlspecialchars($existing, ENT_QUOTES) . '"', $attrs, 1) ?? $attrs;
        } else {
            $attrs .= $styleAttr;
        }

        return preg_replace('/<body([^>]*)>/i', '<body' . $attrs . '>', $html, 1) ?? $html;
    }

    return $html;
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
                $candidate = (string)($mediaItems[0]['medium_path'] ?? $mediaItems[0]['thumb_path'] ?? $mediaItems[0]['storage_path'] ?? '');
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

            $candidate = (string)($media['medium_path'] ?? $media['thumb_path'] ?? $media['storage_path'] ?? '');
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

function injectHomepageHeroVideo(string $html, int $siteId, bool $isHome): string
{
    if (!$isHome || str_contains($html, 'theme-hero-video')) {
        return $html;
    }

    $videoUrl = resolveHomepageHeroVideoUrl($siteId);
    if ($videoUrl === '') {
        return $html;
    }

    $videoHtml = buildHomepageHeroVideoHtml($videoUrl);
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

    return preg_replace_callback(
        '/(<main\b[^>]*>)/i',
        static fn(array $m): string => $m[1] . $videoHtml,
        $html,
        1
    ) ?? $html;
}

function resolveHomepageHeroVideoUrl(int $siteId): string
{
    try {
        $homePage = (new PageRepository())->findByUrlPath('');
        if (!$homePage || (int)($homePage['site_id'] ?? 0) !== $siteId) {
            return '';
        }

        foreach ((new BlockRepository())->allByPage((int)$homePage['id']) as $block) {
            if (($block['block_key'] ?? '') !== 'hero_video') {
                continue;
            }

            $url = normalizePublicAssetUrl((string)($block['content'] ?? ''));
            if ($url !== '') {
                Logger::infoTo('theme-debug', 'theme.hero.video', 'Homepage hero video resolved', [
                    'site_id' => $siteId,
                    'page_id' => $homePage['id'] ?? null,
                    'video_url' => $url,
                ]);
                return $url;
            }
        }
    } catch (\Throwable $e) {
        Logger::infoTo('theme-debug', 'theme.hero.video.error', 'Failed to resolve homepage hero video', [
            'site_id' => $siteId,
            'error' => $e->getMessage(),
        ]);
    }

    return '';
}

function buildHomepageHeroVideoHtml(string $videoUrl): string
{
    $embedUrl = buildVideoEmbedUrl($videoUrl);
    if ($embedUrl !== '') {
        $safeEmbed = htmlspecialchars($embedUrl, ENT_QUOTES);
        return '<div class="theme-hero-video theme-hero-video--embed" aria-hidden="true"><iframe src="' . $safeEmbed . '" title="Homepage hero video" allow="autoplay; fullscreen; picture-in-picture" loading="eager"></iframe></div>';
    }

    $safeUrl = htmlspecialchars($videoUrl, ENT_QUOTES);
    return '<video class="theme-hero-video" src="' . $safeUrl . '" autoplay muted loop playsinline preload="auto" aria-hidden="true"></video>';
}

function buildVideoEmbedUrl(string $videoUrl): string
{
    $url = trim($videoUrl);
    if ($url === '') {
        return '';
    }

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

    if ($youtubeId !== '') {
        return 'https://www.youtube.com/embed/' . $youtubeId . '?autoplay=1&mute=1&controls=0&loop=1&playlist=' . $youtubeId . '&playsinline=1&modestbranding=1&rel=0';
    }

    return '';
}

function injectSiteLogo(string $html, array $site): string
{
    $logoUrl = normalizePublicAssetUrl((string)($site['logo_url'] ?? ''));
    if ($logoUrl === '') {
        return $html;
    }

    $siteName = (string)($site['name'] ?? 'Web');
    $safeUrl = htmlspecialchars($logoUrl, ENT_QUOTES);
    $safeName = htmlspecialchars($siteName, ENT_QUOTES);
    $brandHtml = '<img class="site-logo-img" src="' . $safeUrl . '" alt="' . $safeName . '"><span class="site-logo-text">' . $safeName . '</span>';

    $html = preg_replace_callback(
        '~(<a\b(?=[^>]*\bclass=(["\'])(?=[^"\']*(?:theme-brand|brand|pf-nav-logo|pl-nav-logo))[^"\']*\2)[^>]*>).*?(</a>)~is',
        static fn(array $m): string => $m[1] . $brandHtml . $m[3],
        $html
    ) ?? $html;

    $style = <<<'HTML'
<style data-cms-site-logo>
.site-logo-img{display:block;max-height:44px;max-width:240px;width:auto;height:auto;object-fit:contain}
.site-logo-text{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
</style>
HTML;

    if (!str_contains($html, 'data-cms-site-logo')) {
        $html = str_replace('</head>', "\n" . $style . "\n</head>", $html);
    }

    return $html;
}

function normalizePublicAssetUrl(string $value): string
{
    $normalized = str_replace('\\', '/', trim($value));
    if ($normalized === '') {
        return '';
    }

    if (preg_match('~^https?://~i', $normalized)) {
        return $normalized;
    }

    if (str_starts_with($normalized, '/')) {
        return $normalized;
    }

    return '/' . ltrim($normalized, '/');
}

function buildFooterSocialsHtml(int $siteId): string
{
    $contact = (new ContactRepository())->findBySiteSafe($siteId);
    $socialIcons = [
        'instagram' => '&#x1F4F7;',
        'facebook' => '&#x1F426;',
        'youtube' => '&#x25B6;',
        'tiktok' => '&#x1F3B5;',
        'linkedin' => '&#x1F4BC;',
        'twitter' => '&#x1D54F;',
        'pinterest' => '&#x1F4CC;',
        'whatsapp' => '&#x1F4AC;',
        'telegram' => '&#x2708;',
    ];

    $items = [];
    for ($i = 1; $i <= 5; $i++) {
        $type = trim((string)($contact['social_' . $i . '_type'] ?? ''));
        $url = trim((string)($contact['social_' . $i . '_url'] ?? ''));
        if ($type === '' || $url === '') {
            continue;
        }

        $icon = $socialIcons[$type] ?? '&#128279;';
        $items[] = '<a class="site-footer-social" href="' . htmlspecialchars($url, ENT_QUOTES) . '" target="_blank" rel="noopener">'
            . '<span class="site-footer-social__icon">' . $icon . '</span>'
            . '<span class="site-footer-social__text">' . htmlspecialchars(ucfirst($type), ENT_QUOTES) . '</span>'
            . '</a>';
    }

    if ($items === []) {
        return '';
    }

    return '<style>'
        . '.site-footer-socials{margin-top:24px;padding-top:24px;border-top:1px solid rgba(255,255,255,.08);display:flex;flex-direction:column;gap:14px;align-items:center}'
        . '.site-footer-socials__label{font-size:.76rem;letter-spacing:.16em;text-transform:uppercase;color:rgba(255,255,255,.45)}'
        . '.site-footer-socials__list{display:flex;flex-wrap:wrap;justify-content:center;gap:10px}'
        . '.site-footer-social{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;background:#1c1c22;color:#fff;text-decoration:none;font-size:.9rem;font-weight:700;transition:transform .2s,background .2s}'
        . '.site-footer-social:hover{transform:translateY(-1px);background:#c4963c;color:#111}'
        . '.site-footer-social__icon{font-size:1rem;line-height:1}'
        . '</style>'
        . '<div class="site-footer-socials">'
        . '<div class="site-footer-socials__label">Sociální sítě</div>'
        . '<div class="site-footer-socials__list">' . implode('', $items) . '</div>'
        . '</div>';
}

function buildRichFooterHtml(int $siteId): string
{
    $contact = (new ContactRepository())->findBySiteSafe($siteId);
    $site = (new SiteRepository())->findById($siteId) ?? [];
    $siteName = (string)($site['name'] ?? 'Muj web');
    $logoUrl = normalizePublicAssetUrl((string)($site['logo_url'] ?? ''));
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
        $socialItems[] = '<a class="site-footer-social" href="' . $h($url) . '" target="_blank" rel="noopener"><span class="site-footer-social__icon">' . $icon . '</span><span class="site-footer-social__text">' . $h(ucfirst($type)) . '</span></a>';
    }

    $brand = $logoUrl !== ''
        ? '<img class="site-footer-brand__logo" src="' . $h($logoUrl) . '" alt="' . $h($siteName) . '">'
        : '<span class="site-footer-brand__mark">' . $h(mb_substr($siteName, 0, 2)) . '</span>';

    return '<style data-cms-rich-footer>'
        . 'html,body{min-height:100%}body{min-height:100vh;display:flex;flex-direction:column}.theme-shell{width:100%;flex:1 0 auto;display:flex;flex-direction:column}.theme-shell,.theme-shell__frame{min-height:100vh}.theme-shell__frame{display:flex;flex-direction:column;flex:1 0 auto}.theme-main,main,#page-content{flex:1 0 auto}footer,.theme-footer,.site-footer{margin-top:auto}'
        . 'footer .site-footer-rich,.theme-footer .site-footer-rich,.site-footer .site-footer-rich{width:100%;max-width:1180px;margin:0 auto;padding:0;display:grid;grid-template-columns:minmax(220px,.85fr) minmax(260px,1fr) minmax(220px,.75fr);gap:34px;align-items:start;text-align:left}'
        . '.site-footer-brand{display:flex;flex-direction:column;gap:12px;align-items:flex-start}.site-footer-brand__logo{max-height:52px;max-width:260px;width:auto;height:auto;object-fit:contain}.site-footer-brand__mark{display:inline-grid;place-items:center;width:54px;height:54px;border:1px solid rgba(198,148,61,.45);color:#c6943d;font-family:Georgia,serif;font-weight:800;font-size:20px;letter-spacing:.08em;text-transform:uppercase}.site-footer-brand__name{color:#f3efe7;font-family:Georgia,serif;font-size:22px;line-height:1.1}.site-footer-brand__note{color:rgba(255,255,255,.52);font-size:13px;line-height:1.65}'
        . '.site-footer-contact{display:grid;gap:12px}.site-footer-title{margin:0 0 12px;color:#c6943d;font-size:11px;font-weight:900;letter-spacing:.18em;text-transform:uppercase}.site-footer-contact__item{display:grid;gap:4px;color:#f3efe7;text-decoration:none}.site-footer-contact__item span{color:rgba(255,255,255,.44);font-size:11px;text-transform:uppercase;letter-spacing:.12em}.site-footer-contact__item strong{font-size:14px;line-height:1.5;font-weight:700}'
        . '.site-footer-socials{display:flex;flex-direction:column;gap:14px;align-items:flex-start}.site-footer-socials__list{display:flex;flex-wrap:wrap;gap:10px}.site-footer-social{display:inline-flex;align-items:center;gap:9px;padding:10px 13px;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.03);color:#fff;text-decoration:none;font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;transition:transform .2s,border-color .2s,background .2s,color .2s}.site-footer-social:hover{transform:translateY(-1px);border-color:#c6943d;background:#c6943d;color:#080808}.site-footer-social__icon{display:grid;place-items:center;width:26px;height:26px;border:1px solid currentColor;border-radius:50%;font-size:10px;line-height:1}.site-footer-bottom{grid-column:1/-1;margin-top:4px;padding-top:20px;border-top:1px solid rgba(255,255,255,.09);color:rgba(255,255,255,.36);font-size:12px;text-align:left}'
        . '@media(max-width:820px){footer .site-footer-rich,.theme-footer .site-footer-rich,.site-footer .site-footer-rich{grid-template-columns:1fr;gap:24px}.site-footer-bottom{text-align:left}}'
        . '</style>'
        . '<div class="site-footer-rich">'
        . '<div class="site-footer-brand">' . $brand . '<div class="site-footer-brand__name">' . $h($siteName) . '</div><div class="site-footer-brand__note">Svatebni fotografie a video. Olomouc, Morava a cela CR.</div></div>'
        . '<div class="site-footer-contact"><div class="site-footer-title">Kontakt</div>' . ($contactItems !== [] ? implode('', $contactItems) : '<div class="site-footer-contact__item"><span>Kontakt</span><strong>Doplnte kontaktni udaje v administraci.</strong></div>') . '</div>'
        . '<div class="site-footer-socials"><div class="site-footer-title">Socialni site</div><div class="site-footer-socials__list">' . ($socialItems !== [] ? implode('', $socialItems) : '<span class="site-footer-brand__note">Doplnte socialni site v kontaktu.</span>') . '</div></div>'
        . '<div class="site-footer-bottom">&copy; ' . date('Y') . ' ' . $h($siteName) . '</div>'
        . '</div>';
}

function injectFooterSocials(string $html, int $siteId): string
{
    $socials = buildRichFooterHtml($siteId);
    if ($socials === '') {
        return $html;
    }

    if (preg_match('~<footer\b([^>]*)>.*?</footer>~is', $html)) {
        return preg_replace('~<footer\b([^>]*)>.*?</footer>~is', '<footer$1>' . $socials . '</footer>', $html, 1) ?? $html;
    }

    if (str_contains($html, '</body>')) {
        return str_replace('</body>', $socials . '</body>', $html);
    }

    return $html . $socials;
}

function getThemeTemplateRegistry(): ThemeTemplateRegistry
{
    static $registry = null;
    if ($registry === null) {
        $registry = new ThemeTemplateRegistry();
    }

    return $registry;
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

    if ($themeLayout !== '') {
        $html = $renderer->applyLayout($themeLayout, $html, $siteName, $nav);
    }
    $html = $renderer->stripInlineStyles($html);
    $html = $registry->injectBodyClass($html, (string)($theme['key'] ?? ''));

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
    if ($isHome && !empty($site['layout_nav_home'])) {
        return (string)$site['layout_nav_home'];
    }

    if (!empty($site['layout_nav_sub'])) {
        return (string)$site['layout_nav_sub'];
    }

    return '';
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
            $photos   = $repo->getMedia((int)$entry['id']);
            $hasVideo = !empty($entry['video_url']);
            $thumb    = $hasVideo ? PortfolioRepository::youtubeThumbnail((string)$entry['video_url']) : '';
            if ($thumb === '' && !empty($photos)) {
                $thumbFile = $photos[0]['thumb_path'] ?? $photos[0]['medium_path'] ?? $photos[0]['storage_path'] ?? '';
                $thumb = publicMediaUrl((string)$thumbFile);
            }

            $extraHtml   = '';
            $stripPhotos = $hasVideo ? array_slice($photos, 0, 3) : array_slice($photos, 1, 3);
            foreach ($stripPhotos as $photo) {
                $pfile = $photo['thumb_path'] ?? $photo['storage_path'] ?? '';
                $extraHtml .= '<img src="' . $h(publicMediaUrl((string)$pfile)) . '" alt="' . $h($photo['alt_text'] ?? $entry['title']) . '" loading="lazy">';
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

        $renderer   = new PageRenderer();
        $nav = resolveThemeNav($site, false);
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
            isset($_GET['theme_preview']) ? trim((string)$_GET['theme_preview']) : null
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
        echo sanitizeFrontendHtml(injectFooterSocials($html, (int)$site['id']));
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

        $renderer = new PageRenderer();
        $nav = resolveThemeNav($site, false);
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
            isset($_GET['theme_preview']) ? trim((string)$_GET['theme_preview']) : null
        );
        $titleTag = '<title>' . $h("{$entry['title']} | {$siteName}") . '</title>';
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
        echo sanitizeFrontendHtml(injectFooterSocials($html, (int)$site['id']));
        return true;
    }

    return false;
}
function tryServeFaqPage(): bool
{
    $site = (new SiteRepository())->findFirst();
    if (!$site) return false;

    try {
        $items = (new FaqRepository())->allActive((int)$site['id']);
    } catch (\Throwable $e) {
        return false;
    }

    $h        = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
    $siteName = (string)($site['name'] ?? 'Muj web');
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

    $faqHtml = '';
    foreach ($items as $item) {
        $faqHtml .= '<div class="faq-item">'
            . '<h3 class="faq-q">' . $h($item['question']) . '</h3>'
            . '<div class="faq-a">' . nl2br($h($item['answer'])) . '</div>'
            . '</div>';
    }
    if ($faqHtml === '') {
        $faqHtml = '<p style="color:rgba(255,255,255,.45)">Zatím žádné otázky.</p>';
    }

    $faqCss = <<<'CSS'
body{background:#111;color:#fff}
.fq-hero{background:#1a1a1a;padding:64px 0 40px;text-align:center}
.fq-hero h1{font-size:clamp(2rem,4vw,3.2rem);color:#fff;margin-bottom:12px}
.fq-hero p{color:rgba(255,255,255,.7);font-size:1.05rem;max-width:680px;margin:0 auto}
.fq-wrap{padding:56px 0 80px}
.faq-list{display:flex;flex-direction:column;gap:16px;max-width:820px;margin:0 auto}
.faq-item{padding:24px 28px;background:#18181c;border-radius:12px;border-left:3px solid #c4963c;cursor:pointer}
.faq-q{font-size:1.05rem;color:#fff;margin:0 0 0;font-weight:700;display:flex;justify-content:space-between;align-items:center;gap:16px;line-height:1.4}
.faq-q::after{content:"＋";flex-shrink:0;font-size:1.2rem;color:#c4963c;transition:transform .2s}
.faq-item.open .faq-q::after{transform:rotate(45deg)}
.faq-a{color:rgba(255,255,255,.75);line-height:1.65;margin-top:14px;display:none}
.faq-item.open .faq-a{display:block}
CSS;

    $renderer   = new PageRenderer();
    $nav        = resolveThemeNav($site, false);
$pageContent = <<<HTML
<section class="fq-hero">
  <div class="container">
    <h1>{$h($heroTitle)}</h1>
    <p>{$h($heroSub)}</p>
  </div>
</section>
<section class="fq-wrap">
  <div class="container">
    <div class="faq-list">{$faqHtml}</div>
  </div>
</section>
<script>
document.querySelectorAll('.faq-item').forEach(function(el){
  el.addEventListener('click',function(){el.classList.toggle('open');});
});
</script>
HTML;
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
        isset($_GET['theme_preview']) ? trim((string)$_GET['theme_preview']) : null
    );
    if ($themeActive) {
        $html = $pageContent;
        $html = str_replace('</head>', "\n<title>" . $h('FAQ | ' . $siteName) . "</title>\n</head>", $html);
        $html = $renderer->injectAssets($html, $faqCss . "\n" . $themeCss, $themeJs);
    } elseif (!empty($site['layout_html'])) {
        $html = $renderer->applyLayout($site['layout_html'], $pageContent, $siteName, $nav);
        $html = str_replace('</head>', "\n<title>" . $h('FAQ | ' . $siteName) . "</title>\n</head>", $html);
        $html = $renderer->injectAssets($html, ($site['layout_css'] ?? '') . "\n" . $faqCss . "\n" . $themeCss, ($site['layout_js'] ?? '') . "\n" . $themeJs);
    } else {
        $html = '<!DOCTYPE html><html lang="cs"><head><meta charset="UTF-8"><title>FAQ | ' . $h($siteName) . '</title>'
            . '<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:system-ui,sans-serif;line-height:1.6}.container{max-width:960px;margin:0 auto;padding:0 24px}'
            . $faqCss . '</style></head><body>'
            . '<section class="fq-hero"><div class="container"><h1>' . $h($heroTitle) . '</h1><p>' . $h($heroSub) . '</p></div></section>'
            . '<section class="fq-wrap"><div class="container"><div class="faq-list">' . $faqHtml . '</div></div></section>'
            . '<script>document.querySelectorAll(".faq-item").forEach(function(el){el.addEventListener("click",function(){el.classList.toggle("open");});});</script>'
            . '</body></html>';
    }

    header('Content-Type: text/html; charset=UTF-8');
    echo sanitizeFrontendHtml(injectFooterSocials($html, (int)$site['id']));
    return true;
}

function tryServeKontaktPage(): bool
{
    $site = (new SiteRepository())->findFirst();
    if (!$site) return false;

    try {
        $contact = (new ContactRepository())->findBySite((int)$site['id']) ?: [];
    } catch (\Throwable $e) {
        return false;
    }

    $h         = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
    $siteName  = (string)($site['name'] ?? 'Erbez Photos Videos');
    $heroTitle = 'Kontakt';
    $heroSub   = 'Ozvěte se nám. Rádi odpovíme na vaše otázky.';

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

    $socialIcons = [
        'instagram' => '&#x1F4F7;', 'facebook' => '&#x1F426;', 'youtube'   => '&#x25B6;',
        'tiktok'    => '&#x1F3B5;', 'linkedin'  => '&#x1F4BC;', 'twitter'   => '&#x1D54F;',
        'pinterest' => '&#x1F4CC;', 'whatsapp'  => '&#x1F4AC;', 'telegram'  => '&#x2708;',
    ];

    // Contact details block
    $detailsHtml = '<div class="ci-brand">'
        . '<div class="ci-brand__label">Ozvěte se</div>'
        . '<div class="ci-brand__name">Erbez Photos Videos</div>'
        . '<div class="ci-brand__sub">Svatební fotografie a video • Olomouc • celá ČR</div>'
        . '</div>';
    if (!empty($contact['phone_1'])) {
        $tel = preg_replace('/\s+/', '', $contact['phone_1']);
        $detailsHtml .= '<div class="ci-row"><span class="ci-label">Telefon</span>'
            . '<a href="tel:' . $h($tel) . '">' . $h($contact['phone_1']) . '</a></div>';
    }
    if (!empty($contact['phone_2'])) {
        $tel = preg_replace('/\s+/', '', $contact['phone_2']);
        $detailsHtml .= '<div class="ci-row"><span class="ci-label">&nbsp;</span>'
            . '<a href="tel:' . $h($tel) . '">' . $h($contact['phone_2']) . '</a></div>';
    }
    if (!empty($contact['phone_3'])) {
        $tel = preg_replace('/\s+/', '', $contact['phone_3']);
        $detailsHtml .= '<div class="ci-row"><span class="ci-label">&nbsp;</span>'
            . '<a href="tel:' . $h($tel) . '">' . $h($contact['phone_3']) . '</a></div>';
    }
    if (!empty($contact['email'])) {
        $detailsHtml .= '<div class="ci-row"><span class="ci-label">E-mail</span>'
            . '<a href="mailto:' . $h($contact['email']) . '">' . $h($contact['email']) . '</a></div>';
    }
    if (!empty($contact['address_1'])) {
        $detailsHtml .= '<div class="ci-row"><span class="ci-label">Adresa</span>'
            . '<span>' . nl2br($h($contact['address_1'])) . '</span></div>';
    }
    if (!empty($contact['address_2'])) {
        $detailsHtml .= '<div class="ci-row"><span class="ci-label">&nbsp;</span>'
            . '<span>' . nl2br($h($contact['address_2'])) . '</span></div>';
    }

    // Social links
    $socialsHtml = '';
    for ($i = 1; $i <= 5; $i++) {
        $type = $contact['social_' . $i . '_type'] ?? '';
        $url  = $contact['social_' . $i . '_url']  ?? '';
        if ($type && $url) {
            $icon = $socialIcons[$type] ?? '&#128279;';
            $socialsHtml .= '<a href="' . $h($url) . '" target="_blank" rel="noopener" class="ci-social">'
                . $icon . ' ' . ucfirst($h($type)) . '</a>';
        }
    }
    if ($socialsHtml) {
        $detailsHtml .= '<div class="ci-socials">' . $socialsHtml . '</div>';
    }

    $detailsHtml .= '<div class="ci-links">'
        . '<a href="/kontakt" class="ci-link-btn">Kontakt</a>'
        . '</div>';

    if (!$detailsHtml) {
        $detailsHtml = '<p style="color:rgba(255,255,255,.4)">Kontaktní informace zatím nejsou vyplněny.</p>';
    }

    // Maps link
    $mapsHtml = '';
    if (!empty($contact['maps_url'])) {
        $mapsHtml = '<a href="' . $h($contact['maps_url']) . '" target="_blank" rel="noopener" class="btn-maps">&#128506; Otevřít v Google Maps</a>';
    }

    $kontaktCss = <<<'CSS'
body{background:#111;color:#fff}
.kt-hero{background:#1a1a1a;padding:56px 0 32px;text-align:center}
.kt-hero h1{font-size:clamp(2rem,4vw,3.2rem);color:#fff;margin-bottom:10px}
.kt-hero p{color:rgba(255,255,255,.7);max-width:640px;margin:0 auto}
.kt-wrap{padding:32px 0 72px}
.kt-grid{display:grid;grid-template-columns:1fr;gap:20px;align-items:start;max-width:1000px;margin:0 auto}
.ci-block,.kt-form-block{background:#18181c;border-radius:14px;padding:20px;min-width:0}
.ci-brand{display:flex;flex-direction:column;gap:8px;margin-bottom:24px}
.ci-brand__label{font-size:.76rem;letter-spacing:.18em;text-transform:uppercase;color:#c4963c;font-weight:800}
.ci-brand__name{font-size:clamp(1.7rem,4vw,2.6rem);line-height:1.05;font-weight:800;color:#fff;font-family:Georgia,serif}
.ci-brand__sub{font-size:1.05rem;color:rgba(255,255,255,.82);line-height:1.5}
.ci-title{font-size:1.15rem;font-weight:700;color:#fff;margin-bottom:20px}
.ci-row{display:flex;flex-direction:column;gap:4px;margin-bottom:14px;line-height:1.55;color:rgba(255,255,255,.82)}
.ci-row a{color:#c4963c;text-decoration:none}.ci-row a:hover{text-decoration:underline}
.ci-label{font-size:.82rem;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:.04em}
.ci-socials{display:flex;flex-wrap:wrap;gap:10px;margin-top:20px}
.ci-social{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#1f1f26;border-radius:8px;color:#fff;text-decoration:none;font-size:.9rem}
.ci-social:hover{background:#c4963c}
.ci-links{margin-top:18px;display:flex;flex-wrap:wrap;gap:10px}
.ci-link-btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 16px;border:1px solid rgba(196,150,60,.5);border-radius:8px;color:#fff;text-decoration:none;font-weight:700}
.ci-link-btn:hover{background:#c4963c;color:#111}
.btn-maps{display:inline-flex;align-items:center;gap:8px;margin-top:20px;padding:12px 22px;background:#c4963c;color:#fff;border-radius:8px;text-decoration:none;font-weight:700}
.btn-maps:hover{opacity:.9}
.kt-form-block input,.kt-form-block textarea,.kt-form-block select{width:100%;background:#111;border:1px solid rgba(255,255,255,.12);color:#fff;padding:12px 14px;border-radius:8px;font-size:.95rem;font-family:inherit;margin-bottom:14px}
.kt-form-block textarea{min-height:120px;resize:vertical}
.kt-form-block input::placeholder,.kt-form-block textarea::placeholder{color:rgba(255,255,255,.35)}
.kt-form-block label{display:block;font-size:.82rem;color:rgba(255,255,255,.55);text-transform:uppercase;letter-spacing:.04em;margin-bottom:5px}
.kt-form-btn{width:100%;padding:14px;background:#c4963c;color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer}
.kt-form-btn:hover{opacity:.9}
.kt-success{background:#18181c;border-radius:10px;padding:20px;text-align:center;color:#4ade80;margin-bottom:16px}
@media(min-width:900px){.kt-hero{padding:64px 0 40px}.kt-wrap{padding:56px 0 80px}.kt-grid{grid-template-columns:1fr 1fr;gap:40px}.ci-block,.kt-form-block{padding:32px}.ci-row{flex-direction:row;gap:12px}.ci-label{flex:0 0 80px;padding-top:2px}}
CSS;

    // Contact form HTML
    $formSent    = isset($_GET['sent']) && $_GET['sent'] === '1';
    $formHtml    = '';
    if ($formSent) {
        $formHtml = '<div class="kt-success">&#10003; Zpráva odeslána. Ozveme se co nejdříve.</div>';
    }
    $formHtml .= '<form method="POST" action="/kontakt/odeslat">'
        . '<input type="hidden" name="_csrf" value="' . $h(\App\Core\Auth::csrfToken()) . '">'
        . '<div><label>Jméno *</label><input type="text" name="name" required placeholder="Vaše jméno"></div>'
        . '<div><label>E-mail nebo telefon *</label><input type="text" name="contact_value" required placeholder="email@domena.cz nebo +420..."></div>'
        . '<div><label>Typ focení *</label><select name="shooting_type" required><option value="">Vyberte typ focení</option><option value="svatebni">Svatební foto a video</option><option value="portret">Portrét</option><option value="rodinne">Rodinné focení</option><option value="firemni">Firemní focení</option><option value="jine">Jiné</option></select></div>'
        . '<div><label>Zpráva *</label><textarea name="message" required placeholder="Napište nám…"></textarea></div>'
        . '<input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off">'
        . '<button type="submit" class="kt-form-btn">Odeslat zprávu</button>'
        . '</form>';

    $renderer   = new PageRenderer();
    $nav        = resolveThemeNav($site, false);
    $pageContent = <<<HTML
<section class="kt-hero">
  <div class="container">
    <h1>{$h($heroTitle)}</h1>
    <p>{$h($heroSub)}</p>
  </div>
</section>
<section class="kt-wrap">
  <div class="container">
    <div class="kt-grid">
      <div class="ci-block">
        <div class="ci-title">Kontaktní informace</div>
        {$detailsHtml}
        {$mapsHtml}
      </div>
      <div class="kt-form-block">
        <div class="ci-title">Napište nám</div>
        {$formHtml}
      </div>
    </div>
  </div>
</section>
HTML;
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
        isset($_GET['theme_preview']) ? trim((string)$_GET['theme_preview']) : null
    );
    if ($themeActive) {
        $html = $pageContent;
        $html = str_replace('</head>', "\n<title>" . $h('Kontakt | ' . $siteName) . "</title>\n</head>", $html);
        $html = $renderer->injectAssets($html, $kontaktCss . "\n" . $themeCss, $themeJs);
    } elseif (!empty($site['layout_html'])) {
        $html = $renderer->applyLayout($site['layout_html'], $pageContent, $siteName, $nav);
        $html = str_replace('</head>', "\n<title>" . $h('Kontakt | ' . $siteName) . "</title>\n</head>", $html);
        $html = $renderer->injectAssets($html, ($site['layout_css'] ?? '') . "\n" . $kontaktCss . "\n" . $themeCss, ($site['layout_js'] ?? '') . "\n" . $themeJs);
    } else {
        $html = '<!DOCTYPE html><html lang="cs"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Kontakt | ' . $h($siteName) . '</title>'
            . '<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:system-ui,sans-serif;line-height:1.6;background:#111;color:#fff}.container{max-width:1100px;margin:0 auto;padding:0 16px}' . $kontaktCss . '</style>'
            . '</head><body>'
            . '<section class="kt-hero"><div class="container"><h1>' . $h($heroTitle) . '</h1><p>' . $h($heroSub) . '</p></div></section>'
            . '<section class="kt-wrap"><div class="container"><div class="kt-grid">'
            . '<div class="ci-block"><div class="ci-title">Kontaktní informace</div>' . $detailsHtml . $mapsHtml . '</div>'
            . '<div class="kt-form-block"><div class="ci-title">Napište nám</div>' . $formHtml . '</div>'
            . '</div></div></section><footer><div class="container">' . $h($siteName) . '</div></footer></body></html>';
    }

    header('Content-Type: text/html; charset=UTF-8');
    echo sanitizeFrontendHtml(injectFooterSocials($html, (int)$site['id']));
    return true;
}

function tryServeBlogListingPage(): bool
{
    $site = (new SiteRepository())->findFirst();
    if (!$site) {
        return false;
    }

    $posts     = (new BlogRepository())->allPublished((int)$site['id']);
    $mediaRepo = new MediaRepository();
    $h         = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
    $siteName  = (string)($site['name'] ?? 'Muj web');

    $cardsHtml = '';
    foreach ($posts as $post) {
        $imgHtml = '';
        if (!empty($post['featured_image_id'])) {
            $media = $mediaRepo->findByIdWithThumb((int)$post['featured_image_id']);
            if ($media) {
                $src = publicMediaUrl((string)($media['thumb_path'] ?? $media['medium_path'] ?? $media['storage_path']));
                $imgHtml = '<div class="bl-thumb"><img src="' . $h($src) . '" alt="' . $h($post['title']) . '" loading="lazy"></div>';
            }
        }
        if ($imgHtml === '') {
            $imgHtml = '<div class="bl-thumb bl-thumb--empty"></div>';
        }

        $date = !empty($post['published_at']) ? date('j. n. Y', strtotime($post['published_at'])) : '';
        $slug    = $h($post['slug']);
        $title   = $h($post['title']);
        $excerpt = $h($post['excerpt'] ?? '');

        $cardsHtml .= <<<HTML
<a class="bl-card" href="/{$slug}">
  {$imgHtml}
  <div class="bl-body">
    <div class="bl-date">{$date}</div>
    <h2 class="bl-title">{$title}</h2>
    <p class="bl-excerpt">{$excerpt}</p>
    <span class="bl-more">&#268;&#237;st d&#225;l &rarr;</span>
  </div>
</a>
HTML;
    }

    if ($cardsHtml === '') {
        $cardsHtml = '<p class="bl-empty">Zat&#237;m &#382;&#225;dn&#233; &#269;l&#225;nky.</p>';
    }

    $blogCss = <<<'CSS'
body{background:#111;color:#fff}
.bl-hero{background:#1a1a1a;padding:72px 0 46px;text-align:center}
.bl-hero h1{font-size:clamp(2.4rem,5vw,4rem);color:#fff;margin-bottom:10px}
.bl-hero p{color:rgba(255,255,255,.7);font-size:1.1rem;max-width:720px;margin:0 auto}
.bl-grid-wrap{padding:48px 0 80px}
.bl-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:28px}
.bl-card{display:flex;flex-direction:column;background:#1b1b1f;border-radius:14px;overflow:hidden;text-decoration:none;color:inherit;box-shadow:0 14px 30px rgba(0,0,0,.25);transition:transform .2s,box-shadow .2s}
.bl-card:hover{transform:translateY(-3px);box-shadow:0 20px 40px rgba(0,0,0,.4)}
.bl-thumb{height:210px;overflow:hidden;background:#0e0e11}
.bl-thumb img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .3s}
.bl-card:hover .bl-thumb img{transform:scale(1.04)}
.bl-thumb--empty{height:210px;background:#16161a}
.bl-body{padding:22px 22px 26px;display:flex;flex-direction:column;flex:1}
.bl-date{font-size:.8rem;color:rgba(255,255,255,.4);margin-bottom:8px}
.bl-title{font-size:1.15rem;font-weight:800;color:#fff;margin:0 0 10px;line-height:1.35}
.bl-excerpt{font-size:.9rem;color:rgba(255,255,255,.6);line-height:1.55;flex:1;margin-bottom:16px}
.bl-more{display:inline-block;color:#c4963c;font-weight:700;font-size:.9rem;margin-top:auto}
.bl-empty{color:rgba(255,255,255,.5);padding:40px 0;text-align:center}
@media(max-width:720px){.bl-grid{grid-template-columns:1fr}}
CSS;

    $renderer = new PageRenderer();
    $nav = resolveThemeNav($site, false);
    $pageContent = <<<HTML
<section class="bl-hero">
  <div class="container">
    <h1>Blog &amp; Inspirace</h1>
    <p>Tipy a rady od profesion&#225;ln&#237;ho fotografa z Olomouce.</p>
  </div>
</section>
<section class="bl-grid-wrap">
  <div class="container">
    <div class="bl-grid">{$cardsHtml}</div>
  </div>
</section>
HTML;
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
        isset($_GET['theme_preview']) ? trim((string)$_GET['theme_preview']) : null
    );
    $titleTag = '<title>' . $h("Blog | {$siteName}") . '</title>';
    $metaTag  = '<meta name="description" content="' . $h('Tipy a inspirace od fotografa z Olomouce.') . '">';
    if ($themeActive) {
        $html = $pageContent;
        $html = str_replace('</head>', "\n{$titleTag}\n{$metaTag}\n</head>", $html);
        $html = $renderer->injectAssets($html, $blogCss . "\n" . $themeCss, $themeJs);
    } elseif (!empty($site['layout_html'])) {
        $html = $renderer->applyLayout($site['layout_html'], $pageContent, $siteName, $nav);
        $html = str_replace('</head>', "\n{$titleTag}\n{$metaTag}\n</head>", $html);
        $html = $renderer->injectAssets($html, ($site['layout_css'] ?? '') . "\n" . $blogCss . "\n" . $themeCss, ($site['layout_js'] ?? '') . "\n" . $themeJs);
    } else {
        $siteNameEsc = $h($siteName);
        $html = <<<HTML
<!DOCTYPE html><html lang="cs"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Blog | {$siteNameEsc}</title>
  <style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:system-ui,-apple-system,sans-serif;line-height:1.6}.container{max-width:1180px;margin:0 auto;padding:0 24px}{$blogCss}footer{padding:28px 24px;border-top:1px solid rgba(255,255,255,.08);text-align:center;color:rgba(255,255,255,.46)}</style>
</head><body>
<section class="bl-hero"><div class="container"><h1>Blog &amp; Inspirace</h1><p>Tipy a rady od profesion&#225;ln&#237;ho fotografa z Olomouce.</p></div></section>
<section class="bl-grid-wrap"><div class="container"><div class="bl-grid">{$cardsHtml}</div></div></section>
<footer>{$siteNameEsc}</footer></body></html>
HTML;
    }

    header('Content-Type: text/html; charset=UTF-8');
    echo sanitizeFrontendHtml(injectFooterSocials($html, (int)$site['id']));
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
    $siteName = (string)($site['name'] ?? 'Muj web');

    $imgHtml = '';
    if (!empty($post['featured_image_id'])) {
        $media = (new MediaRepository())->findByIdWithThumb((int)$post['featured_image_id']);
        if ($media) {
            $src     = publicMediaUrl((string)($media['medium_path'] ?? $media['storage_path']));
            $imgHtml = '<div class="bp-featured"><img src="' . $h($src) . '" alt="' . $h($post['title']) . '"></div>';
        }
    }

    try {
        $galleryItems = (new BlogRepository())->getGallery((int)$post['id']);
    } catch (\Throwable $e) {
        $galleryItems = [];
    }
    $galleryHtml  = '';
    foreach ($galleryItems as $idx => $gItem) {
        $tSrc = publicMediaUrl((string)($gItem['thumb_path'] ?? $gItem['medium_path'] ?? $gItem['storage_path'] ?? ''));
        $fSrc = publicMediaUrl((string)($gItem['storage_path'] ?? ''));
        $alt  = $h($gItem['alt_text'] ?? '');
        $galleryHtml .= '<div class="bg-item" data-full="' . $h($fSrc) . '" data-idx="' . $idx . '">'
            . '<img src="' . $h($tSrc) . '" alt="' . $alt . '" loading="lazy">'
            . '</div>';
    }

    $date      = !empty($post['published_at']) ? date('j. n. Y', strtotime($post['published_at'])) : '';
    $title     = $h($post['title']);
    $excerpt   = $h($post['excerpt'] ?? '');
    $content   = (string)($post['content'] ?? '');
    $metaTitle = $h($post['meta_title'] ?: $post['title']);
    $metaDesc  = $h($post['meta_description'] ?: $post['excerpt'] ?: '');

    $blogPostCss = <<<'CSS'
body{background:#111;color:#fff}
.bp-hero{background:#1a1a1a;padding:64px 0 40px}
.bp-hero .bp-date{color:rgba(255,255,255,.45);font-size:.9rem;margin-bottom:14px}
.bp-hero h1{font-size:clamp(2rem,4vw,3.2rem);color:#fff;margin-bottom:14px;line-height:1.2}
.bp-hero .bp-excerpt{color:rgba(255,255,255,.72);font-size:1.1rem;max-width:760px;line-height:1.6}
.bp-featured{width:100%;max-height:480px;overflow:hidden;background:#0e0e11}
.bp-featured img{width:100%;max-height:480px;object-fit:cover;display:block}
.bp-content-wrap{padding:48px 0 80px}
.bp-content{max-width:760px}
.bp-content h2{font-size:1.5rem;color:#fff;margin:32px 0 12px}
.bp-content h3{font-size:1.2rem;color:#e0e0e0;margin:24px 0 10px}
.bp-content p{color:rgba(255,255,255,.78);line-height:1.7;margin-bottom:16px}
.bp-content ul,.bp-content ol{color:rgba(255,255,255,.78);padding-left:1.5em;margin-bottom:16px;line-height:1.7}
.bp-content img{max-width:100%;border-radius:10px;margin:16px 0}
.bp-back{margin-top:36px}
.bp-back a{color:#c4963c;text-decoration:none;font-weight:700}
.bp-back a:hover{text-decoration:underline}
.bg-gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;margin:40px 0}
.bg-item{cursor:pointer;border-radius:10px;overflow:hidden;background:#1b1b1f}
.bg-item img{width:100%;height:160px;object-fit:cover;display:block;transition:transform .2s}
.bg-item:hover img{transform:scale(1.04)}
.bp-lightbox{display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.92);align-items:center;justify-content:center}
.bp-lightbox.active{display:flex}
.bp-lb-img{max-width:92vw;max-height:88vh;object-fit:contain;border-radius:8px}
.bp-lb-close{position:absolute;top:20px;right:24px;font-size:2rem;color:#fff;cursor:pointer;line-height:1;opacity:.7}
.bp-lb-close:hover{opacity:1}
.bp-lb-prev,.bp-lb-next{position:absolute;top:50%;transform:translateY(-50%);font-size:2.5rem;color:#fff;cursor:pointer;padding:12px;opacity:.6;user-select:none}
.bp-lb-prev:hover,.bp-lb-next:hover{opacity:1}
.bp-lb-prev{left:16px}
.bp-lb-next{right:16px}
CSS;

    $gallerySection = '';
    if ($galleryHtml !== '') {
        $gallerySection = '<div class="bg-gallery">' . $galleryHtml . '</div>';
    }

    $lightboxHtml = $galleryHtml !== '' ? <<<'HTML'
<div class="bp-lightbox" id="bp-lightbox">
  <span class="bp-lb-close" id="bp-lb-close">&times;</span>
  <span class="bp-lb-prev" id="bp-lb-prev">&#8249;</span>
  <img class="bp-lb-img" id="bp-lb-img" src="" alt="">
  <span class="bp-lb-next" id="bp-lb-next">&#8250;</span>
</div>
<script>
(function(){
  var lb=document.getElementById('bp-lightbox'),img=document.getElementById('bp-lb-img'),items=Array.from(document.querySelectorAll('.bg-item')),idx=0;
  if(!lb||!items.length)return;
  function open(i){idx=i;img.src=items[i].dataset.full||'';lb.classList.add('active');}
  function close(){lb.classList.remove('active');img.src='';}
  function prev(){open((idx-1+items.length)%items.length);}
  function next(){open((idx+1)%items.length);}
  items.forEach(function(el,i){el.addEventListener('click',function(){open(i);});});
  document.getElementById('bp-lb-close').addEventListener('click',close);
  document.getElementById('bp-lb-prev').addEventListener('click',prev);
  document.getElementById('bp-lb-next').addEventListener('click',next);
  lb.addEventListener('click',function(e){if(e.target===lb)close();});
  document.addEventListener('keydown',function(e){if(!lb.classList.contains('active'))return;if(e.key==='Escape')close();if(e.key==='ArrowLeft')prev();if(e.key==='ArrowRight')next();});
})();
</script>
HTML : '';

    $renderer = new PageRenderer();
    $nav = resolveThemeNav($site, false);
    $pageContent = <<<HTML
<section class="bp-hero">
  <div class="container">
    <div class="bp-date">{$date}</div>
    <h1>{$title}</h1>
    <p class="bp-excerpt">{$excerpt}</p>
  </div>
</section>{$imgHtml}
<section class="bp-content-wrap">
  <div class="container">
    <div class="bp-content">{$content}</div>
    {$gallerySection}
    <div class="bp-back"><a href="/blog">&larr; Zp&#283;t na v&#353;echny &#269;l&#225;nky</a></div>
  </div>
</section>
{$lightboxHtml}
HTML;
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
        isset($_GET['theme_preview']) ? trim((string)$_GET['theme_preview']) : null
    );
    $titleTag    = '<title>' . $metaTitle . ' | ' . $h($siteName) . '</title>';
    $metaDescTag = '<meta name="description" content="' . $metaDesc . '">';
    if ($themeActive) {
        $html = $pageContent;
        $html = str_replace('</head>', "\n{$titleTag}\n{$metaDescTag}\n</head>", $html);
        $html = $renderer->injectAssets($html, $blogPostCss . "\n" . $themeCss, $themeJs);
    } elseif (!empty($site['layout_html'])) {
        $html = $renderer->applyLayout($site['layout_html'], $pageContent, $siteName, $nav);
        $html = str_replace('</head>', "\n{$titleTag}\n{$metaDescTag}\n</head>", $html);
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
<section class="bp-hero"><div class="container"><div class="bp-date">{$date}</div><h1>{$title}</h1><p class="bp-excerpt">{$excerpt}</p></div></section>
{$imgHtml}
<section class="bp-content-wrap"><div class="container"><div class="bp-content">{$content}</div>{$gallerySection}<div class="bp-back"><a href="/blog">&larr; Zp&#283;t na v&#353;echny &#269;l&#225;nky</a></div></div></section>
{$lightboxHtml}
<footer>{$siteNameEsc}</footer></body></html>
HTML;
    }

    header('Content-Type: text/html; charset=UTF-8');
    echo sanitizeFrontendHtml(injectFooterSocials($html, (int)$site['id']));
    return true;
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
        // Honeypot spam check
        if (!empty($_POST['website'])) {
            header('Location: /kontakt');
            exit;
        }
        $site = (new SiteRepository())->findFirst();
        $formEmail = null;
        if ($site) {
            try {
                $ci = (new ContactRepository())->findBySite((int)$site['id']);
                $formEmail = trim((string)($ci['form_email'] ?? ''));
                if ($formEmail === '') {
                    $formEmail = trim((string)($ci['email'] ?? ''));
                }
            } catch (\Throwable $e) {}
        }
        $name    = trim($_POST['name']          ?? '');
        $contact = trim($_POST['contact_value'] ?? '');
        $shootingType = trim($_POST['shooting_type'] ?? '');
        $message = trim($_POST['message']       ?? '');
        if ($name && $contact && $message && $formEmail) {
            $subject = 'Nová zpráva z webu — ' . $name;
            $body    = "Jméno: {$name}\nKontakt: {$contact}\nTyp focení: {$shootingType}\n\nZpráva:\n{$message}";
            @mail($formEmail, $subject, $body, "From: noreply@{$_SERVER['HTTP_HOST']}\r\nContent-Type: text/plain; charset=UTF-8");
        }
        header('Location: /kontakt?sent=1');
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

