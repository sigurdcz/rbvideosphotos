<?php

declare(strict_types=1);

namespace App\Services\Render;

use App\Services\PageRenderer;

/**
 * Single orchestration home for the database-page render cascade shared by the
 * live frontend (public/index.php::tryServeDatabasePage) and the publish path
 * (App\Services\PublishService::publishPage).
 *
 * Phase 2 of the templating refactor: this replaces the two hand-inlined,
 * duplicated inject cascades with one class that runs each mode through the
 * shared App\Services\Render\* services. It does NOT yet move rendering to PHTML
 * views (phases 3-5) - it orchestrates the existing HTML generation.
 *
 * The two modes still diverge in ordering and in a handful of leaf injections
 * (site logo, page hero image/video, video overlay, plus the
 * live-only homepage hero / breadcrumb / sanitize / editor chrome). Those
 * divergences are intentional and preserved byte-for-byte here; they are
 * scheduled for unification in phases 5-6. The live leaves are the global
 * functions defined in public/index.php (available in the live request
 * context); the publish leaves come from the injected PublishPageChrome.
 *
 * Phase 4 - cenik: the package-cards + CTA splice (CenikPageRenderer::inject)
 * used to be live-only (the old injectCenikPageLayout() global function in
 * public/index.php, unreachable from PublishService's entry point). It is now
 * a shared App\Services\Render\* + ViewModel/PHTML call, so both composeLive
 * and composePublish run it - /cenik's package cards are no longer
 * live/publish-divergent.
 *
 * Phase 3 - legacy vs. PHTML layout: the choice between the legacy layout.html
 * str_replace path and the new PHTML layout is made UPSTREAM of this class, by
 * App\Services\Render\ThemeLayoutRenderer at the layout step in
 * tryServeDatabasePage()/publishPage(). By the time compose() runs, $ctx['html']
 * already carries the assembled shell from whichever path applied. The post-
 * render inject cascade below is therefore deliberately shared by BOTH layout
 * paths: every step operates on page content or on the finished <head>/<body>
 * markup, independent of how the shell was produced. The chrome fragments it
 * injects (footer, hero, breadcrumb, cookie consent) are now sourced from PHTML
 * partials (single source of truth) but their injection ORDER is preserved so
 * the inline-style externalization contract (extractInlineStyleBlocks) and the
 * live-only special-page routes - which share these same injects until phase 4 -
 * keep producing byte-identical output. Dropping injects for the active theme is
 * therefore intentionally deferred to phase 4, when those routes migrate.
 *
 * Required $ctx keys (both modes):
 *   mode(string live|publish), renderer(PageRenderer), html(string),
 *   css(string), js(string), seoHead(string), bodyScripts(string),
 *   blocks(array), site(array), page(array), isHome(bool), pageKey(string)
 * Live-only:  editMode(bool), previewMode(bool), uri(string)
 * Publish-only: chrome(PublishPageChrome)
 */
final class PageComposer
{
    public const MODE_LIVE = 'live';
    public const MODE_PUBLISH = 'publish';

    public static function compose(array $ctx): string
    {
        $mode = (string)($ctx['mode'] ?? self::MODE_LIVE);

        return $mode === self::MODE_PUBLISH
            ? self::composePublish($ctx)
            : self::composeLive($ctx);
    }

    /**
     * Live frontend cascade - byte-equivalent to the former inline sequence in
     * tryServeDatabasePage(). Live-only / divergent leaves are the global
     * functions from public/index.php (resolved via the global namespace).
     */
    private static function composeLive(array $ctx): string
    {
        /** @var PageRenderer $renderer */
        $renderer = $ctx['renderer'];
        $html     = (string)$ctx['html'];
        $blocks   = (array)$ctx['blocks'];
        $site     = (array)$ctx['site'];
        $page     = (array)$ctx['page'];
        $isHome   = (bool)$ctx['isHome'];
        $pageKey  = (string)$ctx['pageKey'];
        $editMode = (bool)($ctx['editMode'] ?? false);

        $html = HomepageSectionRenderer::injectHomepageMissingSections($html, $blocks, $isHome);
        $html = HomepageSectionRenderer::injectHomepageThemeDataSources($html, $blocks, $isHome);
        $html = HomepageSectionRenderer::stabilizeGoldenBashHero($html, $isHome, self::themeKey($ctx));
        $html = CenikPageRenderer::inject($html, $blocks, $pageKey, self::themeKey($ctx), $editMode);
        $html = HomepageSectionRenderer::injectHomepageAboutVisualSource($html, (int)$page['id'], (int)$page['site_id'], $isHome);
        $html = HomepageSectionRenderer::removeLegacyVideoWeddingTag($html);
        $html = SharedProcessRenderer::inject($html, self::themeKey($ctx));
        $html = HeroEnhancementRenderer::inject($html, self::themeKey($ctx));
        $html = $renderer->injectAssets($html, (string)$ctx['css'], (string)$ctx['js']);
        $html = $renderer->injectSeo($html, (string)$ctx['seoHead'], (string)$ctx['bodyScripts']);
        $html = FooterRenderer::injectFooterSocials($html, (int)$site['id'], self::themeKey($ctx));
        $html = \injectSiteLogo($html, $site);
        $html = \injectPageHeroImage($html, (int)$page['id'], $site);
        $html = \injectHomepageHeroImage($html, (int)$site['id'], $isHome);
        $html = \injectPageHeroVideo($html, $site, (int)$page['id'], $pageKey);
        $html = MarkupNormalizer::injectBodyPageKey($html, $pageKey);
        if (!$isHome) {
            $pagePath = \normalizeBreadcrumbUrl((string)($page['url_path'] ?? ''));
            $html = \injectBreadcrumbTrail($html, $site, \breadcrumbItems((string)($page['title'] ?? 'Stranka'), $pagePath));
        }
        $html = \sanitizeFrontendHtml($html, $site);

        if ($editMode) {
            $html = \injectCmsEditorChrome(
                $html,
                (string)($ctx['uri'] ?? ''),
                (string)($page['title'] ?? ''),
                (int)$page['id'],
                (bool)($ctx['previewMode'] ?? false)
            );
        }

        return $html;
    }

    /**
     * Publish cascade - byte-equivalent to the former inline sequence in
     * publishPage(). Divergent leaves come from the injected PublishPageChrome
     * (implemented by PublishService); the footer is unified to the shared
     * FooterRenderer.
     */
    private static function composePublish(array $ctx): string
    {
        /** @var PageRenderer $renderer */
        $renderer = $ctx['renderer'];
        /** @var PublishPageChrome $chrome */
        $chrome  = $ctx['chrome'];
        $html    = (string)$ctx['html'];
        $blocks  = (array)$ctx['blocks'];
        $site    = (array)$ctx['site'];
        $page    = (array)$ctx['page'];
        $isHome  = (bool)$ctx['isHome'];
        $pageKey = (string)$ctx['pageKey'];

        $html = HomepageSectionRenderer::injectHomepageMissingSections($html, $blocks, $isHome);
        $html = HomepageSectionRenderer::injectHomepageThemeDataSources($html, $blocks, $isHome);
        $html = HomepageSectionRenderer::stabilizeGoldenBashHero($html, $isHome, self::themeKey($ctx));
        $html = CenikPageRenderer::inject($html, $blocks, $pageKey, self::themeKey($ctx));
        $html = HomepageSectionRenderer::injectHomepageAboutVisualSource($html, (int)$page['id'], (int)($page['site_id'] ?? 0), $isHome);
        $html = HomepageSectionRenderer::removeLegacyVideoWeddingTag($html);
        $html = SharedProcessRenderer::inject($html, self::themeKey($ctx));
        $html = HeroEnhancementRenderer::inject($html, self::themeKey($ctx));
        $html = $renderer->injectAssets($html, (string)$ctx['css'], (string)$ctx['js']);
        $html = $renderer->injectSeo($html, (string)$ctx['seoHead'], (string)$ctx['bodyScripts']);
        $html = FooterRenderer::injectFooterSocials($html, (int)($site['id'] ?? 0), self::themeKey($ctx));
        $html = $chrome->injectSiteLogo($html, $site);
        $html = $chrome->injectPageHeroImage($html, (int)$page['id'], $site);
        $html = $chrome->injectPageHeroVideo($html, $site, (int)$page['id']);
        $html = MarkupNormalizer::injectBodyPageKey($html, $pageKey);
        $html = MarkupNormalizer::normalizeLegacyFrontendMarkup($html);
        $html = ResponsiveImageMarkupService::optimize($html, (int)($site['id'] ?? 0));
        $html = MarkupNormalizer::injectSharedFrontendAssets($html);
        $html = MarkupNormalizer::externalizeBodyCssVariables($html);
        $html = $chrome->injectGlobalVideoOverlayStyles($html, $site);
        $html = MarkupNormalizer::extractInlineStyleBlocks($html);
        $html = MarkupNormalizer::extractInlineBodyScripts($html);

        $html = \App\Support\PageCssSubsetter::apply($html);

        return $html;
    }

    private static function themeKey(array $ctx): ?string
    {
        $themeKey = trim((string)($ctx['themeKey'] ?? ''));

        return $themeKey !== '' ? $themeKey : null;
    }
}
