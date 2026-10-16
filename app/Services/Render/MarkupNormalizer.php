<?php

declare(strict_types=1);

namespace App\Services\Render;

use App\Support\AssetMinifier;
use App\Support\FrontendAssetManager;

/**
 * Canonical legacy-markup normalization and inline style/script externalization.
 *
 * Extracted in phase 2 of the templating refactor as the single source of truth
 * shared by the live render path (public/index.php) and the publish path
 * (App\Services\PublishService). Do not fork these implementations.
 */
final class MarkupNormalizer
{
    /** Marks the inlined @font-face block so it is neither hoisted nor injected twice. */
    private const FONT_FACE_MARKER = 'data-cms-fonts-css';

    /**
     * The public site is light-only. "light only" opts out of browser-forced dark themes
     * (Chrome Android Auto Dark Mode for Web Contents, Samsung Internet / Opera force dark
     * where they honour it); supported-color-schemes is the older Safari/Apple Mail name.
     */
    public const LIGHT_ONLY_META = '<meta name="color-scheme" content="light only">'
        . "\n" . '<meta name="supported-color-schemes" content="light">';

    /**
     * Declares the light-only colour scheme in <head>, right after the charset meta so it
     * is known before any stylesheet. Idempotent: a page that already declares a
     * color-scheme meta is left alone, so the tag never appears twice.
     */
    public static function injectLightOnlyColorScheme(string $html): string
    {
        if (preg_match('~<meta\b[^>]*\bname=(["\']?)color-scheme\1[\s>/]~i', $html)) {
            return $html;
        }

        $tags = self::LIGHT_ONLY_META;
        $withCharset = preg_replace('~(<meta\b[^>]*\bcharset=[^>]*>)~i', "$1\n" . $tags, $html, 1, $count);
        if ($withCharset !== null && $count === 1) {
            return $withCharset;
        }
        $withHead = preg_replace('~(<head\b[^>]*>)~i', "$1\n" . $tags, $html, 1, $count);
        if ($withHead !== null && $count === 1) {
            return $withHead;
        }

        return $html;
    }

    public static function normalizeLegacyTemplateSource(string $html): string
{
    return preg_replace(
        '~<header\s+class=(["\'])hero\1>\s*(<div\s+class=(["\'])container\3>\s*<h1\s+data-block=(["\'])hero_heading\4></h1>\s*<p\s+data-block=(["\'])hero_intro\5)(?:\s+class=(["\'])hero-sub\6)?(></p>\s*</div>)\s*</header>~i',
        '<section class="page-hero">$2 class="hero-sub"$7</section>',
        $html
    ) ?? $html;
}

    public static function externalizeBodyCssVariables(string $html): string
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

    return FrontendAssetManager::injectInlineStyle($html, 'body{' . implode(';', $cssParts) . ';}', 'data-cms-body-vars');
}

    public static function extractInlineStyleBlocks(string $html): string
{
    if (!preg_match('~<style\b[^>]*>.*?</style>~is', $html)) {
        return $html;
    }

    $styles = [];
    $html = preg_replace_callback(
        '~<style\b([^>]*)>(.*?)</style>~is',
        static function (array $match) use (&$styles): string {
            $attrs = (string)$match[1];
            $css = trim((string)$match[2]);
            if ($css === '') return '';
            // Any marked block stays inline. The previous explicit list meant the critical
            // CSS survived only because its text happened to mention .theme-hero-video -
            // editing that file could silently have re-deferred it and brought back the
            // layout shift it exists to prevent.
            $keepInline = preg_match('~\bdata-cms-[a-z0-9-]+\b~i', $attrs) === 1 || str_contains($css, '.site-logo-img')
                || str_contains($css, '.site-footer-rich')
                || str_contains($css, '.theme-hero-video')
                || str_contains($css, '.cookie-consent');
            if ($keepInline) return $match[0];
            $styles[] = $css;
            return '';
        },
        $html
    ) ?? $html;
    $styles = array_values(array_filter($styles, static fn(string $css): bool => $css !== ''));
    if ($styles === []) {
        return $html;
    }

    return FrontendAssetManager::injectGeneratedCss($html, implode("\n", $styles), 'inline-style-blocks', 'data-cms-inline-styles');
}

    public static function extractInlineBodyScripts(string $html): string
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

    public static function injectBodyCssVariableToken(string $html, string $name, string $value): string
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

    public static function injectBodyPageKey(string $html, string $pageKey): string
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

    /**
     * The Material Symbols stylesheet used to sit in every layout and blocked the first
     * render on every route (~900 ms on throttled mobile), even though only the about and
     * contact pages actually use the icons. It is now requested per page, and never
     * render-blocking: the print/onload swap lets the page paint first, and the noscript
     * copy keeps the icons for clients without JavaScript.
     */
    private const ICON_FONT_HREF = 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,300,0,0&icon_names=assignment,call,check_circle,description,event,event_available,expand_more,location_on,mail,photo_camera,play_circle,receipt_long,schedule,sell,verified_user,warning&display=block';

    public static function injectIconFontWhenUsed(string $html): string
    {
        if (!str_contains($html, 'material-symbols-outlined')) {
            return $html;
        }

        return FrontendAssetManager::injectDeferredStylesheet($html, self::ICON_FONT_HREF, 'data-cms-icon-font');
    }

    public static function injectSharedFrontendAssets(string $html): string
{
    $html = self::injectLightOnlyColorScheme($html);
    $runtimeCssPath = dirname(__DIR__, 3) . '/public/assets/frontend-runtime.css';
    $runtimeCss = file_get_contents($runtimeCssPath);
    if ($runtimeCss === false) {
        throw new \RuntimeException('Chybi povinny frontend runtime stylesheet: ' . $runtimeCssPath);
    }
    $criticalPath = dirname(__DIR__, 3) . '/public/assets/critical-above-fold.css';
    $criticalCss = is_file($criticalPath) ? (string) file_get_contents($criticalPath) : '';
    if ($criticalCss !== '') {
        $html = FrontendAssetManager::injectInlineStyle($html, $criticalCss, 'data-cms-critical-css');
    }
    $html = FrontendAssetManager::injectInlineStyle($html, $runtimeCss, 'data-cms-frontend-runtime-css');
    $html = self::inlineFontFaces($html);
    $html = self::injectIconFontWhenUsed($html);
    $html = FrontendAssetManager::injectDeferredStylesheet($html, '/assets/cookie-consent.css', 'data-cookie-consent-css');
    // Served under a content-hashed name so they can be cached for a year. Under their
    // plain paths they were revalidated hourly, which for a file that only changes on
    // deploy means every returning visitor re-fetched the whole runtime.
    $html = self::injectRuntimeScript($html, 'frontend-runtime.js', 'frontend-runtime', 'data-cms-frontend-runtime-js');

    return self::injectRuntimeScript($html, 'cookie-consent.js', 'cookie-consent', 'data-cookie-consent-js');
}

    /**
     * Replaces the blocking <link> to fonts.css with the rules themselves.
     *
     * The file is only a few hundred bytes gzipped but costs a whole render-blocking
     * round trip, which on a throttled mobile connection is worth far more than the
     * bytes. It is swapped in place rather than appended, so it keeps its position:
     * after the font preloads and before the page stylesheet.
     */
    private static function inlineFontFaces(string $html): string
{
    if (str_contains($html, self::FONT_FACE_MARKER)) {
        return $html;
    }

    $path = dirname(__DIR__, 3) . '/public/assets/fonts/fonts.css';
    $css = is_file($path) ? (string) file_get_contents($path) : '';
    if ($css === '') {
        // Leave the link in place; a missing file here must not cost the page its fonts.
        return $html;
    }

    $style = '<style ' . self::FONT_FACE_MARKER . '>' . AssetMinifier::minify($css, 'css') . '</style>';

    return preg_replace(
        '~<link\s+rel="stylesheet"\s+href="/assets/fonts/fonts\.css"\s*>~i',
        $style,
        $html,
        1
    ) ?? $html;
}

    /**
     * Adds a runtime script, content-hashed when the source can be read.
     *
     * Deliberately degrades to the plain path instead of throwing. A thrown exception here
     * does not surface: tryServeDatabasePage() catches every Throwable and returns false,
     * so the request falls through to the 404 page. A file missing mid-deploy would take
     * the whole site down rather than cost one script its year of caching.
     */
    private static function injectRuntimeScript(string $html, string $name, string $prefix, string $marker): string
{
    $path = dirname(__DIR__, 3) . '/public/assets/' . $name;
    $contents = is_file($path) ? file_get_contents($path) : false;
    if ($contents === false || $contents === '') {
        return FrontendAssetManager::injectScript($html, '/assets/' . $name, $marker);
    }

    return FrontendAssetManager::injectGeneratedJs($html, $contents, $prefix, $marker);
}

    /**
     * Canonical legacy markup normalization.
     *
     * Phase-2 divergence note: the live path additionally runs
     * normalizeContactFormAccessibility() here; the publish path historically did
     * not. To keep both golden outputs byte-identical the accessibility pass is
     * gated behind $withContactAccessibility (live passes true, publish false).
     * Unifying this behavior is deferred (contact archetype, phase 4).
     */
    public static function normalizeLegacyFrontendMarkup(string $html, bool $withContactAccessibility = false): string
    {
    $html = preg_replace('~<div(?:\s+class="[^"]*")?>\s*<label[^>]*>[^<]*Datum[^<]*</label>\s*<input[^>]*name="event_date"[^>]*>\s*</div>~ui', '', $html) ?? $html;
    $html = preg_replace('~<label[^>]*>[^<]*Datum[^<]*</label>\s*<input[^>]*name="event_date"[^>]*>~ui', '', $html) ?? $html;
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
    if ($withContactAccessibility) {
        $html = \App\Services\Render\MarkupNormalizer::normalizeContactFormAccessibility($html);
    }
    return str_replace(
        '<div style="height:100%;background:#222;display:flex;align-items:center;justify-content:center;font-size:2rem;color:rgba(255,255,255,.3)">&#9654;</div>',
        '<div class="pt-thumb__placeholder">&#9654;</div>',
        $html
    );
}

    public static function normalizeContactFormAccessibility(string $html): string
{
    // Event dates are intentionally outside the public contact contract. This
    // also strips the field from legacy/custom views that have not been updated.
    $html = preg_replace('~<div(?:\s+class="[^"]*")?>\s*<label[^>]*>[^<]*Datum[^<]*</label>\s*<input[^>]*name="event_date"[^>]*>\s*</div>~ui', '', $html) ?? $html;
    $html = preg_replace('~<label[^>]*>[^<]*Datum[^<]*</label>\s*<input[^>]*name="event_date"[^>]*>~ui', '', $html) ?? $html;
    $html = str_replace('<p class="kt-form-intro">', '<p class="kt-form-intro" id="kontakt-form-intro">', $html);
    $html = preg_replace('~<label>([^<]*\*)</label><input type="text" name="name"~u', '<label for="kontakt-name">$1</label><input id="kontakt-name" type="text" name="name" autocomplete="name"', $html) ?? $html;
    $html = preg_replace('~<label>([^<]*\*)</label><input type="text" name="contact_value"~u', '<label for="kontakt-value">$1</label><input id="kontakt-value" type="text" name="contact_value" autocomplete="email" inputmode="email"', $html) ?? $html;
    $html = preg_replace('~<label>([^<]*\*)</label><select name="shooting_type"~u', '<label for="kontakt-shooting-type">$1</label><select id="kontakt-shooting-type" name="shooting_type"', $html) ?? $html;
    $html = preg_replace('~<label>([^<]*)</label><input type="text" name="event_place"~u', '<label for="kontakt-event-place">$1</label><input id="kontakt-event-place" type="text" name="event_place" autocomplete="address-level2"', $html) ?? $html;
    $html = preg_replace('~<label>([^<]*)</label><textarea name="message"~u', '<label for="kontakt-message">$1</label><textarea id="kontakt-message" name="message"', $html) ?? $html;
    $html = str_replace('<label class="kt-consent"><input type="checkbox" required>', '<label class="kt-consent" for="kontakt-consent"><input id="kontakt-consent" type="checkbox" name="privacy_consent" required>', $html);
    $html = str_replace('name="website" class="hp-honeypot" style="display:none" tabindex="-1" autocomplete="off"', 'name="website" class="hp-honeypot" tabindex="-1" autocomplete="off" aria-hidden="true"', $html);
    return str_replace('<div class="kt-form-safe">', '<div class="kt-form-safe" id="kontakt-form-safe">', $html);
}
}
