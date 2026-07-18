<?php

declare(strict_types=1);

namespace App\Services\Render;

use App\View\ViewFactory;

/**
 * Chooses the layout ("chrome") rendering path for a theme, phase 3 of the
 * templating refactor.
 *
 * Priority (TEMPLATE_REFACTOR_PLAN.md 2.6): a theme that ships
 * Templates/<key>/views/layout.phtml renders its shell through the ViewEngine
 * (PHTML partials for nav / logo / footer placeholder); every other theme keeps
 * the legacy PageRenderer::applyLayout() str_replace over layout.html. This is a
 * per-theme opt-in, not a big-bang switch — the two paths are byte-equivalent so
 * golden output is unchanged.
 *
 * Only the layout SHELL moves here. The post-render inject cascade
 * (footer socials, hero media, breadcrumb, cookie consent, SEO/assets) still
 * runs afterwards for both paths; those partials share a single source of truth
 * but their injection order is preserved so nothing regresses.
 */
final class ThemeLayoutRenderer
{
    /** The logical view name a theme ships to opt into PHTML layout rendering. */
    public const LAYOUT_VIEW = 'layout';

    public static function hasPhtmlLayout(?string $themeKey): bool
    {
        return ViewFactory::themeLayoutPath($themeKey) !== null;
    }

    /**
     * Render a theme's layout shell around the page content via the ViewEngine.
     * Byte-equivalent to PageRenderer::applyLayout(layout.html, …) for the active
     * theme; callers use it only when hasPhtmlLayout() is true.
     */
    public static function render(?string $themeKey, string $content, string $siteName, string $nav): string
    {
        return ViewFactory::forTheme($themeKey)->render(self::LAYOUT_VIEW, [
            'content' => $content,
            'siteName' => $siteName,
            'nav' => $nav,
        ]);
    }
}
