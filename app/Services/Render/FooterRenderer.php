<?php

declare(strict_types=1);

namespace App\Services\Render;

/**
 * Canonical rich footer builder and footer injection (unified live/publish).
 *
 * Extracted in phase 2 of the templating refactor as the single source of truth
 * shared by the live render path (public/index.php) and the publish path
 * (App\Services\PublishService). Do not fork these implementations.
 */
final class FooterRenderer
{
    public static function buildRichFooterHtml(int $siteId, ?string $themeKey = null): string
    {
        // Rendered by the PHTML partial (app/Views/partials/footer.phtml); the data
        // shape is produced by FooterViewData so live and publish share one source.
        // Resolves the site's active theme so a theme can override the footer
        // markup (Templates/<key>/views/partials/footer.phtml) the same way
        // every other partial/archetype does; falls back to the core partial
        // when no theme is active or it doesn't provide its own override.
        $themeKey = trim((string)$themeKey) ?: null;
        if ($themeKey === null) {
            $site = (new \App\Repositories\SiteRepository())->findById($siteId);
            $theme = $site ? (new \App\Services\ThemeTemplateRegistry())->resolveForSite($site) : null;
            $themeKey = trim((string)($theme['key'] ?? '')) ?: null;
        }

        return \App\View\ViewFactory::forTheme($themeKey)->render('partials/footer', [
            'footer' => FooterViewData::forSite($siteId),
        ]);
    }

    public static function injectFooterSocials(string $html, int $siteId, ?string $themeKey = null): string
{
    $socials = \App\Services\Render\FooterRenderer::buildRichFooterHtml($siteId, $themeKey);
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
}
