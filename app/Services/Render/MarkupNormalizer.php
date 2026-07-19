<?php

declare(strict_types=1);

namespace App\Services\Render;

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

    return FrontendAssetManager::injectGeneratedCss($html, 'body{' . implode(';', $cssParts) . ';}', 'body-vars', 'data-cms-body-vars');
}

    public static function extractInlineStyleBlocks(string $html): string
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

    public static function injectSharedFrontendAssets(string $html): string
{
    $html = FrontendAssetManager::injectStylesheet($html, '/assets/frontend-runtime.css', 'data-cms-frontend-runtime-css');
    $html = FrontendAssetManager::injectStylesheet($html, '/assets/cookie-consent.css', 'data-cookie-consent-css');
    $html = FrontendAssetManager::injectScript($html, '/assets/frontend-runtime.js', 'data-cms-frontend-runtime-js');
    return FrontendAssetManager::injectScript($html, '/assets/cookie-consent.js', 'data-cookie-consent-js');
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
    $html = str_replace('<p class="kt-form-intro">', '<p class="kt-form-intro" id="kontakt-form-intro">', $html);
    $html = preg_replace('~<label>([^<]*\*)</label><input type="text" name="name"~u', '<label for="kontakt-name">$1</label><input id="kontakt-name" type="text" name="name" autocomplete="name"', $html) ?? $html;
    $html = preg_replace('~<label>([^<]*\*)</label><input type="text" name="contact_value"~u', '<label for="kontakt-value">$1</label><input id="kontakt-value" type="text" name="contact_value" autocomplete="email" inputmode="email"', $html) ?? $html;
    $html = preg_replace('~<label>([^<]*\*)</label><select name="shooting_type"~u', '<label for="kontakt-shooting-type">$1</label><select id="kontakt-shooting-type" name="shooting_type"', $html) ?? $html;
    $html = preg_replace('~<label>([^<]*)</label><input type="text" name="event_date"~u', '<label for="kontakt-event-date">$1</label><input id="kontakt-event-date" type="date" name="event_date" autocomplete="off"', $html) ?? $html;
    $html = preg_replace('~<label>([^<]*)</label><input type="text" name="event_place"~u', '<label for="kontakt-event-place">$1</label><input id="kontakt-event-place" type="text" name="event_place" autocomplete="address-level2"', $html) ?? $html;
    $html = preg_replace('~<label>([^<]*)</label><textarea name="message"~u', '<label for="kontakt-message">$1</label><textarea id="kontakt-message" name="message"', $html) ?? $html;
    $html = str_replace('<label class="kt-consent"><input type="checkbox" required>', '<label class="kt-consent" for="kontakt-consent"><input id="kontakt-consent" type="checkbox" name="privacy_consent" required>', $html);
    $html = str_replace('name="website" class="hp-honeypot" style="display:none" tabindex="-1" autocomplete="off"', 'name="website" class="hp-honeypot" tabindex="-1" autocomplete="off" aria-hidden="true"', $html);
    return str_replace('<div class="kt-form-safe">', '<div class="kt-form-safe" id="kontakt-form-safe">', $html);
}
}
