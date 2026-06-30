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
use App\Services\SeoRenderer;
use App\Services\ThemeTemplateRegistry;
use App\Support\FrontendAssetManager;

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
        $html = injectPageHeroVideo($html, $site, (int)$page['id'], $themeKey);
        $html = sanitizeFrontendHtml($html, $site);

        if ($authenticated) {
            $html = injectCmsEditorChrome(
                $html,
                $uri,
                (string)($page['title'] ?? ''),
                (int)$page['id'],
                $previewMode
            );
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

function injectCookieConsent(string $html): string
{
    if (str_contains($html, 'data-cookie-consent-modal')) {
        return $html;
    }

    $runtime = '';
    if (!str_contains($html, 'cookie-consent-runtime.js')) {
        try {
            $site = (new SiteRepository())->findFirst();
            if ($site) {
                $runtime = (new SeoRenderer())->renderConsentRuntime($site) . "\n";
            }
        } catch (\Throwable $e) {
            Logger::error('cookie-consent', $e->getMessage());
        }
    }

    $consent = <<<'HTML'
<style data-cookie-consent-style>
.cookie-consent-backdrop[hidden],.cookie-consent[hidden],.cookie-consent-host[hidden]{display:none!important}
.cookie-consent-host{
  position:fixed;
  inset:0;
  z-index:99998;
  pointer-events:none;
}
.cookie-consent-host:not([hidden]){display:block!important}
.cookie-consent-host > .cookie-consent-backdrop,
.cookie-consent-host > .cookie-consent{
  pointer-events:auto;
}
.cookie-consent-host > .cookie-consent-backdrop{position:absolute;inset:0;z-index:0;background:rgba(0,0,0,.56);backdrop-filter:blur(8px)}
.cookie-consent,.cookie-consent *{box-sizing:border-box}
.cookie-consent-host > .cookie-consent,
body > .cookie-consent{
  position:fixed;
  left:50%;
  top:50%;
  z-index:99999;
  width:min(760px,calc(100vw - 32px));
  max-width:min(760px,calc(100vw - 32px));
  max-height:calc(100vh - 32px);
  overflow:auto;
  margin:0;
  padding:16px;
  border:1px solid rgba(198,148,61,.45);
  background:rgba(5,5,5,.98);
  color:#f7f1e8;
  box-shadow:0 24px 80px rgba(0,0,0,.38);
  backdrop-filter:blur(12px);
  transform:translate(-50%,-50%);
  font-family:Arial,Helvetica,sans-serif;
  line-height:1.45;
  isolation:isolate;
}
.cookie-consent__grid{display:grid;gap:14px}
.cookie-consent__label{color:#e0b866;font-size:11px;font-weight:900;letter-spacing:.16em;text-transform:uppercase}
.cookie-consent__hero{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
.cookie-consent__title{margin:0;color:#fff;font-family:Georgia,"Times New Roman",serif;font-size:22px;line-height:1.1}
.cookie-consent__smile{flex:0 0 auto;color:#e0b866;font-size:48px;line-height:1}
.cookie-consent__text{margin:8px 0 0;color:rgba(255,255,255,.72);font-size:14px;line-height:1.55}
.cookie-consent__legal{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:12px;color:rgba(255,255,255,.68);font-size:12px;line-height:1.5}
.cookie-consent__legal a{color:#e0b866;text-decoration:underline}
.cookie-consent__actions{display:flex;flex-wrap:wrap;gap:8px}
.cookie-consent__btn{min-height:40px;padding:10px 16px;border:1px solid rgba(224,184,102,.62);background:transparent;color:#f7f1e8;font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;cursor:pointer}
.cookie-consent__btn--accept{background:#c6943d;border-color:#c6943d;color:#050505}
.cookie-consent__btn:hover{transform:translateY(-1px)}
.cookie-consent__settings{display:none;margin-top:14px;padding-top:14px;border-top:1px solid rgba(255,255,255,.12)}
.cookie-consent[data-mode="settings"] .cookie-consent__settings{display:grid;gap:10px}
.cookie-consent__option{display:grid;gap:5px;padding:10px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.03)}
.cookie-consent__option label{display:flex;align-items:center;justify-content:space-between;gap:12px;color:#fff;font-size:13px;font-weight:800}
.cookie-consent__option p{margin:0;color:rgba(255,255,255,.62);font-size:12px;line-height:1.45}
.cookie-consent__option input{width:auto}
.cookie-consent-link{display:inline-flex;margin-top:12px;border:0;background:transparent;color:inherit;text-decoration:underline;cursor:pointer;font:inherit}
.site-footer-bottom .cookie-consent-link{margin-top:0;vertical-align:baseline;opacity:inherit}
@media(min-width:720px){.cookie-consent-host > .cookie-consent,body > .cookie-consent{width:min(620px,calc(100vw - 48px));max-width:min(620px,calc(100vw - 48px))}.cookie-consent__grid{grid-template-columns:1fr auto;align-items:end}.cookie-consent__settings{grid-column:1/-1}.cookie-consent__actions{justify-content:flex-end}}
</style>
<div class="cookie-consent-host" data-cookie-consent-host hidden>
  <div class="cookie-consent-backdrop" data-cookie-consent-backdrop hidden></div>
  <div class="cookie-consent" data-cookie-consent-modal role="dialog" aria-live="polite" aria-label="Nastaveni cookies" data-mode="summary" hidden>
  <div class="cookie-consent__grid">
    <div>
      <div class="cookie-consent__label">Soukromi</div>
      <div class="cookie-consent__hero">
        <h2 class="cookie-consent__title">Pouzivame cookies</h2>
        <div class="cookie-consent__smile" aria-hidden="true">&#9786;</div>
      </div>
      <p class="cookie-consent__text">Pouzivame cookies k zajisteni spravneho fungovani webu a se souhlasem take k mereni navstevnosti pomocí Google Analytics. Diky tomu vime, co navstevniky zajima, a muzeme web zlepsovat.</p>
      <div class="cookie-consent__legal">
        <a href="/podminky-uziti">Podmínky užití</a>
      </div>
    </div>
    <div class="cookie-consent__actions">
      <button class="cookie-consent__btn cookie-consent__btn--accept" type="button" data-cookie-accept-all>Prijmout vse</button>
      <button class="cookie-consent__btn" type="button" data-cookie-reject-all>Odmitnout vse</button>
      <button class="cookie-consent__btn" type="button" data-cookie-settings aria-expanded="false">Nastaveni</button>
    </div>
    <div class="cookie-consent__settings" data-cookie-settings-panel>
      <div class="cookie-consent__option">
        <label>Nezbytne cookies <input type="checkbox" checked disabled></label>
        <p>Vzdy aktivni. Jsou potreba pro zakladni fungovani webu.</p>
      </div>
      <div class="cookie-consent__option" data-cookie-option="analytics">
        <label>Analyticke cookies <input type="checkbox" data-cookie-category="analytics"></label>
        <p>Google Analytics a merici nastroje, pouze se souhlasem.</p>
      </div>
      <div class="cookie-consent__option" data-cookie-option="marketing">
        <label>Marketingove cookies <input type="checkbox" data-cookie-category="marketing"></label>
        <p>Reklamni a remarketingove nastroje, pouze pokud je pouzivame a povolite je.</p>
      </div>
      <div class="cookie-consent__actions">
        <button class="cookie-consent__btn cookie-consent__btn--accept" type="button" data-cookie-save>Ulozit nastaveni</button>
      </div>
    </div>
  </div>
  </div>
</div>

HTML;

    if (str_contains($html, '</body>')) {
        return str_replace('</body>', $runtime . $consent . "\n</body>", $html);
    }

    return $html . $runtime . $consent;
}

function sanitizeFrontendHtml(string $html, ?array $site = null): string
{
    $html = preg_replace('~https?://localhost(?::\d+)?/storage/media/~i', '/storage/media/', $html) ?? $html;
    $html = normalizeLegacyFrontendMarkup($html);
    $html = externalizeBodyCssVariables($html);
    $html = injectGlobalVideoOverlayStyles($html, $site);
    $html = injectEditorialGoldOverrides($html);
    $html = injectCookieConsent($html);
    $html = injectSharedFrontendAssets($html);
    $html = injectFaviconLinks($html, $site);
    $html = extractInlineStyleBlocks($html);
    $html = extractInlineBodyScripts($html);
    return $html;
}

/**
 * Centralni doplneni favicon odkazu do <head> pro vsechny stranky.
 * Specialni handlery (FAQ, Kontakt, portfolio, hodnoceni, blog) vkladaji do
 * <head> jen <title>, takze jim favicon chybel — tady ho doplnime jednotne.
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
    $html = FrontendAssetManager::injectStylesheet($html, '/assets/frontend-runtime.css', 'data-cms-frontend-runtime-css');
    $html = FrontendAssetManager::injectStylesheet($html, '/assets/cookie-consent.css', 'data-cookie-consent-css');
    $html = FrontendAssetManager::injectScript($html, '/assets/frontend-runtime.js', 'data-cms-frontend-runtime-js');
    return FrontendAssetManager::injectScript($html, '/assets/cookie-consent.js', 'data-cookie-consent-js');
}

function normalizeLegacyFrontendMarkup(string $html): string
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
    $html = str_replace('<p style="color:rgba(255,255,255,.4)">', '<p class="frontend-note">', $html);
    $html = str_replace(' style="display:none" tabindex="-1" autocomplete="off"', ' class="hp-honeypot" tabindex="-1" autocomplete="off"', $html);
    return str_replace(
        '<div style="height:100%;background:#222;display:flex;align-items:center;justify-content:center;font-size:2rem;color:rgba(255,255,255,.3)">&#9654;</div>',
        '<div class="pt-thumb__placeholder">&#9654;</div>',
        $html
    );
}

function externalizeBodyCssVariables(string $html): string
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

function injectEditorialGoldOverrides(string $html): string
{
    return $html;
}

function extractInlineStyleBlocks(string $html): string
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

function extractInlineBodyScripts(string $html): string
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
      <button type="submit" class="cms-ab-btn cms-ab-logout">Odhlasit</button>
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
    foreach ($keys as $key) {
        if (isset($blocks[$key]) && trim((string)($blocks[$key]['content'] ?? '')) !== '') {
            return [$key, (string)$blocks[$key]['content'], (string)($blocks[$key]['block_type'] ?? 'text')];
        }
    }

    return [$keys[0] ?? '', $fallback, 'text'];
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

function injectPageHeroImage(string $html, int $pageId): string
{
    $heroImage = resolvePageHeroImageUrl($pageId);
    if ($heroImage === '') {
        return $html;
    }

    return injectBodyCssVariableToken($html, 'theme-page-hero-image', "url('" . str_replace("'", '%27', $heroImage) . "')");
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

function injectPageHeroVideo(string $html, array $site, ?int $pageId, string $pageKey): string
{
    $siteId = (int)($site['id'] ?? 0);
    $pageKey = getThemeTemplateRegistry()->normalizePageKey($pageKey);
    if (hasHeroVideoElement($html)) {
        return ensureHeroVideoStyle($html);
    }

    $videoUrl = resolvePageHeroVideoUrl($siteId, $pageId, $site);
    if ($videoUrl === '') {
        return $html;
    }

    $posterUrl = normalizePublicAssetUrl((string)($site['default_hero_poster_url'] ?? ''));
    $videoHtml = buildHeroVideoHtml($videoUrl, $posterUrl);
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

function resolvePageHeroVideoUrl(int $siteId, ?int $pageId, array $site): string
{
    try {
        if ($pageId !== null && $pageId > 0) {
            foreach ((new BlockRepository())->allByPage($pageId) as $block) {
                if (($block['block_key'] ?? '') !== 'hero_video') {
                    continue;
                }

                $url = normalizePublicAssetUrl((string)($block['content'] ?? ''));
                if ($url !== '') {
                    Logger::infoTo('theme-debug', 'theme.hero.video', 'Page hero video resolved', [
                        'site_id' => $siteId,
                        'page_id' => $pageId,
                        'source' => 'page_block',
                        'video_url' => $url,
                    ]);
                    return $url;
                }
            }
        }

        $defaultUrl = normalizePublicAssetUrl((string)($site['default_hero_video_url'] ?? ''));
        if ($defaultUrl !== '') {
            Logger::infoTo('theme-debug', 'theme.hero.video', 'Default hero video resolved', [
                    'site_id' => $siteId,
                    'page_id' => $pageId,
                    'source' => 'site_default',
                    'video_url' => $defaultUrl,
            ]);
            return $defaultUrl;
        }
    } catch (\Throwable $e) {
        Logger::infoTo('theme-debug', 'theme.hero.video.error', 'Failed to resolve homepage hero video', [
            'site_id' => $siteId,
            'error' => $e->getMessage(),
        ]);
    }

    return '';
}

function buildHeroVideoHtml(string $videoUrl, string $posterUrl = ''): string
{
    $embedUrl = buildVideoEmbedUrl($videoUrl);
    $posterStyle = $posterUrl !== '' ? ' style="background-image:url(\'' . htmlspecialchars($posterUrl, ENT_QUOTES) . '\')"' : '';
    if ($embedUrl !== '') {
        $safeEmbed = htmlspecialchars($embedUrl, ENT_QUOTES);
        return '<div class="theme-hero-video theme-hero-video--embed" aria-hidden="true"' . $posterStyle . '><iframe src="' . $safeEmbed . '" title="Hero video" allow="autoplay; fullscreen; picture-in-picture" loading="eager"></iframe></div>';
    }

    return '';
}

function hasHeroVideoElement(string $html): bool
{
    return (bool) preg_match('/<[^>]+\bclass=(["\'])[^"\']*\btheme-hero-video\b[^"\']*\1/i', $html);
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

function normalizeSocialType(string $type, string $url = ''): string
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

function socialLabel(string $type): string
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

function socialIconSvg(string $type): string
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

function enrichContactWithLegacyBlocks(int $siteId, array $contact): array
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

    $pages = new PageRepository();
    $blocks = new BlockRepository();
    $htmlChunks = [];

    foreach (['kontakt', ''] as $path) {
        $page = $pages->findByUrlPath($path);
        if (!$page || (int)($page['site_id'] ?? 0) !== $siteId) {
            continue;
        }
        foreach (['kontakt_info', 'contact_info', 'contact_card'] as $blockKey) {
            $block = $blocks->findByKey((int)$page['id'], $blockKey);
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
            $type = normalizeSocialType('', $href);
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

    if (trim((string)($contact['form_email'] ?? '')) === '' && trim((string)($contact['email'] ?? '')) !== '') {
        $contact['form_email'] = $contact['email'];
    }

    return $contact;
}

function buildFooterSocialsHtml(int $siteId): string
{
    $contact = enrichContactWithLegacyBlocks($siteId, (new ContactRepository())->findBySiteSafe($siteId));

    $items = [];
    for ($i = 1; $i <= 5; $i++) {
        $type = trim((string)($contact['social_' . $i . '_type'] ?? ''));
        $url = trim((string)($contact['social_' . $i . '_url'] ?? ''));
        if ($type === '' || $url === '') {
            continue;
        }

        $canonicalType = normalizeSocialType($type, $url);
        $icon = socialIconSvg($canonicalType);
        $label = htmlspecialchars(socialLabel($canonicalType), ENT_QUOTES);
        $items[] = '<a class="site-footer-social site-footer-social--' . htmlspecialchars($canonicalType, ENT_QUOTES) . '" href="' . htmlspecialchars($url, ENT_QUOTES) . '" target="_blank" rel="noopener" aria-label="' . $label . '" title="' . $label . '">'
            . '<span class="site-footer-social__icon">' . $icon . '</span>'
            . '</a>';
    }

    if ($items === []) {
        return '';
    }

    return '<style>'
        . '.site-footer-socials{margin-top:24px;padding-top:24px;border-top:1px solid rgba(255,255,255,.08);display:flex;flex-direction:column;gap:14px;align-items:center}'
        . '.site-footer-socials__label{font-size:.76rem;letter-spacing:.16em;text-transform:uppercase;color:rgba(255,255,255,.45)}'
        . '.site-footer-socials__list{display:flex;flex-wrap:wrap;justify-content:center;gap:10px}'
        . '.site-footer-social{--social:#c6943d;display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;padding:0;border:1px solid rgba(255,255,255,.14);border-radius:999px;background:#1c1c22;color:#f3efe7;text-decoration:none;transition:transform .2s,border-color .2s,color .2s}'
        . '.site-footer-social:hover{transform:translateY(-1px);border-color:rgba(255,255,255,.3);color:var(--social)}'
        . '.site-footer-social__icon{display:grid;place-items:center;line-height:1;transition:color .2s,transform .2s}.site-footer-social:hover .site-footer-social__icon{transform:scale(1.06)}.site-footer-social__icon svg{width:18px;height:18px;fill:currentColor;stroke:currentColor;stroke-width:1.6}'
        . '.site-footer-social--instagram{--social:#e4405f}.site-footer-social--facebook{--social:#1877f2}.site-footer-social--youtube{--social:#ff0033}.site-footer-social--tiktok{--social:#00f2ea}.site-footer-social--linkedin{--social:#0a66c2}.site-footer-social--twitter{--social:#fff}.site-footer-social--pinterest{--social:#bd081c}.site-footer-social--whatsapp{--social:#25d366}.site-footer-social--telegram{--social:#229ed9}'
        . '</style>'
        . '<div class="site-footer-socials">'
        . '<div class="site-footer-socials__label">Sociální sítě</div>'
        . '<div class="site-footer-socials__list">' . implode('', $items) . '</div>'
        . '</div>';
}

function buildRichFooterHtml(int $siteId): string
{
    $contact = enrichContactWithLegacyBlocks($siteId, (new ContactRepository())->findBySiteSafe($siteId));
    $site = (new SiteRepository())->findById($siteId) ?? [];
    $siteName = (string)($site['name'] ?? 'RB Videos & Photos');
    $logoUrl = normalizePublicAssetUrl((string)($site['logo_url'] ?? ''));
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
        $canonicalType = normalizeSocialType($type, $url);
        $label = $h(socialLabel($canonicalType));
        $socialItems[] = '<a class="site-footer-social site-footer-social--' . $h($canonicalType) . '" href="' . $h($url) . '" target="_blank" rel="noopener" aria-label="' . $label . '" title="' . $label . '"><span class="site-footer-social__icon">' . socialIconSvg($canonicalType) . '</span></a>';
    }

    $brand = $logoUrl !== ''
        ? '<span class="site-footer-brand__logoBox"><img class="site-footer-brand__logo" src="' . $h($logoUrl) . '" alt="' . $h($siteName) . '"></span>'
        : '<span class="site-footer-brand__mark">' . $h(mb_substr($siteName, 0, 2)) . '</span>';

    return '<style data-cms-rich-footer>'
        . 'html,body{min-height:100%}body{min-height:100vh;display:flex;flex-direction:column}.theme-shell{width:100%;flex:1 0 auto;display:flex;flex-direction:column}.theme-shell,.theme-shell__frame{min-height:100vh}.theme-shell__frame{display:flex;flex-direction:column;flex:1 0 auto}.theme-main,main,#page-content{flex:1 0 auto}footer,.theme-footer,.site-footer{margin-top:auto}'
        . 'footer .site-footer-rich,.theme-footer .site-footer-rich,.site-footer .site-footer-rich{width:100%;max-width:1180px;margin:0 auto;padding:0;display:grid;grid-template-columns:minmax(220px,.85fr) minmax(300px,1fr) minmax(220px,.75fr);gap:34px;align-items:start;text-align:left}'
        . '.site-footer-brand{display:flex;flex-direction:column;gap:12px;align-items:flex-start}.site-footer-brand__logoBox{display:inline-flex;align-items:center;justify-content:center;min-width:74px;min-height:58px;padding:9px 12px;border:1px solid rgba(198,148,61,.7);background:rgba(0,0,0,.45);box-shadow:0 14px 32px rgba(198,148,61,.12)}.site-footer-brand__logo{max-height:42px;max-width:220px;width:auto;height:auto;object-fit:contain}.site-footer-brand__mark{display:inline-grid;place-items:center;width:54px;height:54px;border:1px solid rgba(198,148,61,.55);color:#c6943d;font-family:Georgia,serif;font-weight:800;font-size:20px;letter-spacing:.08em;text-transform:uppercase}.site-footer-brand__name{color:#f3efe7;font-family:Georgia,serif;font-size:22px;line-height:1.1}.site-footer-brand__note{color:rgba(255,255,255,.52);font-size:13px;line-height:1.65}'
        . '.site-footer-contact{display:grid;gap:9px}.site-footer-title{margin:0 0 12px;color:#c6943d;font-size:11px;font-weight:900;letter-spacing:.18em;text-transform:uppercase}.site-footer-contact__item{display:grid;grid-template-columns:86px minmax(0,1fr);gap:14px;align-items:baseline;color:#f3efe7;text-decoration:none}.site-footer-contact__item span{color:rgba(255,255,255,.44);font-size:11px;text-transform:uppercase;letter-spacing:.12em;white-space:nowrap}.site-footer-contact__item strong,.site-footer-contact__item a{min-width:0;color:#f3efe7;font-size:14px;line-height:1.45;font-weight:800;word-break:break-word}'
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
    $siteName = (string)($site['name'] ?? 'RB Videos & Photos');

    if (!$detailOnly && $uri === '/nase-prace') {
        $page = publicPageForPath('nase-prace');
        $pageBlocks = pageBlockMap($page);
        [$heroTitleKey, $heroTitle, $heroTitleType] = firstBlockValue($pageBlocks, ['hero_h1', 'hero_heading'], 'Naše práce');
        [$heroSubKey, $heroSub, $heroSubType] = firstBlockValue($pageBlocks, ['hero_sub', 'hero_intro'], 'Ukázka videí a galerií z naší práce.');
        $heroTitleAttrs = inlineAttrs($page, $heroTitleKey, $heroTitleType);
        $heroSubAttrs = inlineAttrs($page, $heroSubKey, $heroSubType);
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
            $titleAttrs = inlineAttrs($page, 'portfolio:' . (int)$entry['id'] . ':title', 'text');

            $tiles .= <<<HTML
<a class="pt-tile" href="/{$h($entry['slug'])}">
  <div class="pt-caption"{$titleAttrs}>{$h($entry['title'])}</div>
  <div class="pt-thumb">{$thumbHtml}{$playIcon}</div>
  <div class="pt-photos">{$extraHtml}</div>
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
.pt-tile{display:flex;flex-direction:column;text-decoration:none;color:inherit;background:#1b1b1f;border-radius:14px;overflow:hidden;box-shadow:0 14px 30px rgba(0,0,0,.25);transition:transform .2s,box-shadow .2s}
.pt-tile:hover{transform:translateY(-3px);box-shadow:0 20px 40px rgba(0,0,0,.4)}
.pt-thumb{position:relative;height:220px;background:#0e0e11;overflow:hidden}
.pt-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.pt-thumb-empty{height:100%;display:flex;align-items:center;justify-content:center;font-size:3rem;color:rgba(255,255,255,.25)}
.pt-play{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:68px;height:68px;border-radius:999px;background:rgba(196,150,60,.92);display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#fff;box-shadow:0 12px 28px rgba(0,0,0,.32)}
.pt-photos{display:flex;gap:4px;height:84px;padding:4px;background:#17171a}
.pt-photos img{flex:1;min-width:0;height:100%;object-fit:cover}
.pt-caption{display:flex;align-items:center;min-height:68px;padding:16px 18px;font-weight:700;font-size:1rem;line-height:1.35;color:#fff;background:#17171a;border-bottom:1px solid rgba(255,255,255,.08)}
.pt-empty{color:rgba(255,255,255,.72);padding:32px 0;text-align:center}
@media(max-width:720px){.pt-grid{grid-template-columns:1fr}}
CSS;

        $renderer   = new PageRenderer();
        $nav = resolveThemeNav($site, false);
$pageContent = <<<HTML
<section class="pf-hero">
  <div class="container">
    <h1{$heroTitleAttrs}>{$h($heroTitle)}</h1>
    <p{$heroSubAttrs}>{$h($heroSub)}</p>
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
        $html = injectPageHeroVideo($html, $site, findPageIdForHeroPath('nase-prace', (int)$site['id']), 'portfolio');
        $html = injectFooterSocials($html, (int)$site['id']);
        $html = injectCmsEditorChrome($html, $uri, $heroTitle, isset($page['id']) ? (int)$page['id'] : null, Auth::check() && isset($_GET['cms_preview']));
        echo sanitizeFrontendHtml($html, $site);
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
        foreach ($mediaItems as $idx => $media) {
            $thumbSrc = publicMediaUrl((string)($media['thumb_path'] ?? $media['medium_path'] ?? $media['storage_path'] ?? ''));
            $fullSrc = publicMediaUrl((string)($media['storage_path'] ?? ''));
            $alt = $h($media['alt_text'] ?? $entry['title']);
            $galleryHtml .= '<button type="button" class="pf-photo" data-pf-full="' . $h($fullSrc) . '" data-pf-idx="' . $idx . '" aria-label="Otevřít fotografii">'
                . '<img src="' . $h($thumbSrc) . '" alt="' . $alt . '" loading="lazy"></button>';
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
.pf-photo{padding:0;border:0;background:transparent;cursor:pointer}
.pf-photo img{width:100%;height:240px;object-fit:cover;border-radius:10px;display:block}
.pf-lightbox{display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.92);align-items:center;justify-content:center}
.pf-lightbox.active{display:flex}
.pf-lb-img{max-width:92vw;max-height:88vh;object-fit:contain;border-radius:8px}
.pf-lb-close{position:absolute;top:20px;right:24px;font-size:2rem;color:#fff;cursor:pointer;line-height:1;opacity:.7}
.pf-lb-close:hover{opacity:1}
.pf-lb-prev,.pf-lb-next{position:absolute;top:50%;transform:translateY(-50%);font-size:2.5rem;color:#fff;cursor:pointer;padding:12px;opacity:.6;user-select:none}
.pf-lb-prev:hover,.pf-lb-next:hover{opacity:1}
.pf-lb-prev{left:16px}
.pf-lb-next{right:16px}
.pf-back{margin-top:28px}
.pf-back a{display:inline-flex;background:#c4963c;color:#fff;text-decoration:none;padding:12px 18px;border-radius:6px;font-weight:700}
CSS;

        $lightboxHtml = $galleryHtml !== '' ? <<<'HTML'
<div class="pf-lightbox" id="pf-lightbox">
  <span class="pf-lb-close" id="pf-lb-close">&times;</span>
  <span class="pf-lb-prev" id="pf-lb-prev">&#8249;</span>
  <img class="pf-lb-img" id="pf-lb-img" src="" alt="">
  <span class="pf-lb-next" id="pf-lb-next">&#8250;</span>
</div>
<script>
(function(){
  var lb=document.getElementById('pf-lightbox'),img=document.getElementById('pf-lb-img'),items=Array.from(document.querySelectorAll('.pf-photo')),idx=0;
  if(!lb||!items.length)return;
  function open(i){idx=i;img.src=items[i].dataset.pfFull||'';lb.classList.add('active');}
  function close(){lb.classList.remove('active');img.src='';}
  function prev(){open((idx-1+items.length)%items.length);}
  function next(){open((idx+1)%items.length);}
  items.forEach(function(el,i){el.addEventListener('click',function(){open(i);});});
  document.getElementById('pf-lb-close').addEventListener('click',close);
  document.getElementById('pf-lb-prev').addEventListener('click',prev);
  document.getElementById('pf-lb-next').addEventListener('click',next);
  lb.addEventListener('click',function(e){if(e.target===lb)close();});
  document.addEventListener('keydown',function(e){if(!lb.classList.contains('active'))return;if(e.key==='Escape')close();if(e.key==='ArrowLeft')prev();if(e.key==='ArrowRight')next();});
})();
</script>
HTML : '';

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
{$lightboxHtml}
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
{$lightboxHtml}
<footer>{$siteNameEsc}</footer>
</body>
</html>
HTML;
        }

        header('Content-Type: text/html; charset=UTF-8');
        echo sanitizeFrontendHtml(injectFooterSocials($html, (int)$site['id']), $site);
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
    $heroTitleAttrs = inlineAttrs($page, $heroTitleKey, $heroTitleType);
    $heroSubAttrs = inlineAttrs($page, $heroSubKey, $heroSubType);

    $faqHtml = '';
    foreach ($items as $item) {
        $questionAttrs = inlineAttrs($page, 'faq:' . (int)$item['id'] . ':question', 'text');
        $answerAttrs = inlineAttrs($page, 'faq:' . (int)$item['id'] . ':answer', 'text');
        $faqHtml .= '<div class="faq-item">'
            . '<h3 class="faq-q"' . $questionAttrs . '>' . $h($item['question']) . '</h3>'
            . '<div class="faq-a"' . $answerAttrs . '>' . nl2br($h($item['answer'])) . '</div>'
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
    <h1{$heroTitleAttrs}>{$h($heroTitle)}</h1>
    <p{$heroSubAttrs}>{$h($heroSub)}</p>
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
    $html = injectPageHeroVideo($html, $site, findPageIdForHeroPath('faq', (int)$site['id']), 'faq');
    $html = injectFooterSocials($html, (int)$site['id']);
    $html = injectCmsEditorChrome($html, '/faq', $heroTitle, isset($page['id']) ? (int)$page['id'] : null, Auth::check() && isset($_GET['cms_preview']));
    echo sanitizeFrontendHtml($html, $site);
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
    $siteName  = trim((string)($site['name'] ?? ''));
    if ($siteName === '' || preg_match('~erbez~i', $siteName)) {
        $siteName = 'RB Videos and Photos';
    }
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
    $page = publicPageForPath('kontakt');
    $pageBlocks = pageBlockMap($page);
    [$heroTitleKey, $heroTitle, $heroTitleType] = firstBlockValue($pageBlocks, ['hero_h1', 'hero_heading'], $heroTitle);
    [$heroSubKey, $heroSub, $heroSubType] = firstBlockValue($pageBlocks, ['hero_sub', 'hero_intro'], $heroSub);
    $heroTitleAttrs = inlineAttrs($page, $heroTitleKey, $heroTitleType);
    $heroSubAttrs = inlineAttrs($page, $heroSubKey, $heroSubType);

    // Contact details block
    $detailsHtml = '<div class="ci-brand">'
        . '<div class="ci-brand__label">Ozvěte se</div>'
        . '<div class="ci-brand__name">' . $h($siteName) . '</div>'
        . '<div class="ci-brand__sub">Svatební fotografie a video • Olomouc • celá ČR</div>'
        . '</div>';
    if (!empty($contact['phone_1'])) {
        $tel = preg_replace('/\s+/', '', $contact['phone_1']);
        $detailsHtml .= '<div class="ci-row"><span class="ci-label">Telefon</span>'
            . '<a href="tel:' . $h($tel) . '"' . inlineAttrs($page, 'contact:phone_1', 'text') . '>' . $h($contact['phone_1']) . '</a></div>';
    }
    if (!empty($contact['phone_2'])) {
        $tel = preg_replace('/\s+/', '', $contact['phone_2']);
        $detailsHtml .= '<div class="ci-row"><span class="ci-label">&nbsp;</span>'
            . '<a href="tel:' . $h($tel) . '"' . inlineAttrs($page, 'contact:phone_2', 'text') . '>' . $h($contact['phone_2']) . '</a></div>';
    }
    if (!empty($contact['phone_3'])) {
        $tel = preg_replace('/\s+/', '', $contact['phone_3']);
        $detailsHtml .= '<div class="ci-row"><span class="ci-label">&nbsp;</span>'
            . '<a href="tel:' . $h($tel) . '"' . inlineAttrs($page, 'contact:phone_3', 'text') . '>' . $h($contact['phone_3']) . '</a></div>';
    }
    if (!empty($contact['email'])) {
        $detailsHtml .= '<div class="ci-row"><span class="ci-label">E-mail</span>'
            . '<a href="mailto:' . $h($contact['email']) . '"' . inlineAttrs($page, 'contact:email', 'text') . '>' . $h($contact['email']) . '</a></div>';
    }
    if (!empty($contact['address_1'])) {
        $detailsHtml .= '<div class="ci-row"><span class="ci-label">Adresa</span>'
            . '<span' . inlineAttrs($page, 'contact:address_1', 'text') . '>' . nl2br($h($contact['address_1'])) . '</span></div>';
    }
    if (!empty($contact['address_2'])) {
        $detailsHtml .= '<div class="ci-row"><span class="ci-label">&nbsp;</span>'
            . '<span' . inlineAttrs($page, 'contact:address_2', 'text') . '>' . nl2br($h($contact['address_2'])) . '</span></div>';
    }

    // Social links
    $socialsHtml = '';
    for ($i = 1; $i <= 5; $i++) {
        $type = $contact['social_' . $i . '_type'] ?? '';
        $url  = $contact['social_' . $i . '_url']  ?? '';
        if ($type && $url) {
            $canonicalType = normalizeSocialType((string)$type, (string)$url);
            $icon = socialIconSvg($canonicalType);
            $socialsHtml .= '<a href="' . $h($url) . '" target="_blank" rel="noopener" class="ci-social">'
                . '<span class="ci-social__icon">' . $icon . '</span><span>' . $h(socialLabel($canonicalType)) . '</span></a>';
        }
    }
    if ($socialsHtml) {
        $detailsHtml .= '<div class="ci-socials">' . $socialsHtml . '</div>';
    }

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
.ci-social{display:inline-flex;align-items:center;gap:10px;padding:8px 14px;border:1px solid rgba(212,176,111,.24);background:transparent;color:#f3efe7;text-decoration:none;font-size:.9rem;transition:border-color .2s,color .2s,background-color .2s,transform .2s}
.ci-social:hover{transform:translateY(-1px);border-color:rgba(212,176,111,.62);background:rgba(212,176,111,.06);color:#fff6ea}
.ci-social__icon{display:grid;place-items:center;width:32px;height:32px;border:1px solid rgba(212,176,111,.24)}
.ci-social__icon svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.75;stroke-linecap:round;stroke-linejoin:round}
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
    <h1{$heroTitleAttrs}>{$h($heroTitle)}</h1>
    <p{$heroSubAttrs}>{$h($heroSub)}</p>
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
    $html = injectFooterSocials($html, (int)$site['id']);
    $html = injectPageHeroVideo($html, $site, isset($page['id']) ? (int)$page['id'] : findPageIdForHeroPath('kontakt', (int)$site['id']), 'contact');
    $html = injectCmsEditorChrome($html, '/kontakt', $heroTitle, isset($page['id']) ? (int)$page['id'] : null, Auth::check() && isset($_GET['cms_preview']));
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
    $mediaRepo = new MediaRepository();
    $h         = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
    $siteName  = (string)($site['name'] ?? 'RB Videos & Photos');
    $page = publicPageForPath('blog');
    $pageBlocks = pageBlockMap($page);
    [$heroTitleKey, $heroTitle, $heroTitleType] = firstBlockValue($pageBlocks, ['hero_h1', 'hero_heading'], 'Blog & Inspirace');
    [$heroSubKey, $heroSub, $heroSubType] = firstBlockValue($pageBlocks, ['hero_sub', 'hero_intro'], 'Tipy a rady od profesionálního fotografa z Olomouce.');
    $heroTitleAttrs = inlineAttrs($page, $heroTitleKey, $heroTitleType);
    $heroSubAttrs = inlineAttrs($page, $heroSubKey, $heroSubType);

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
        $titleAttrs = inlineAttrs($page, 'blog:' . (int)$post['id'] . ':title', 'text');
        $excerptAttrs = inlineAttrs($page, 'blog:' . (int)$post['id'] . ':excerpt', 'text');

        $cardsHtml .= <<<HTML
<a class="bl-card" href="/{$slug}">
  {$imgHtml}
  <div class="bl-body">
    <div class="bl-date">{$date}</div>
    <h2 class="bl-title"{$titleAttrs}>{$title}</h2>
    <p class="bl-excerpt"{$excerptAttrs}>{$excerpt}</p>
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
    <h1{$heroTitleAttrs}>{$h($heroTitle)}</h1>
    <p{$heroSubAttrs}>{$h($heroSub)}</p>
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
    $html = injectFooterSocials($html, (int)$site['id']);
    $html = injectPageHeroVideo($html, $site, isset($page['id']) ? (int)$page['id'] : findPageIdForHeroPath('blog', (int)$site['id']), 'blog');
    $html = injectCmsEditorChrome($html, '/blog', $heroTitle, isset($page['id']) ? (int)$page['id'] : null, Auth::check() && isset($_GET['cms_preview']));
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
    echo sanitizeFrontendHtml(injectFooterSocials($html, (int)$site['id']), $site);
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

