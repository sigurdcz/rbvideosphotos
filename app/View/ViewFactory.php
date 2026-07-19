<?php

declare(strict_types=1);

namespace App\View;

/**
 * Builds ViewEngine instances wired to the correct resolution cascade for a
 * given theme: theme views first (Templates/<key>/views), core views last
 * (app/Views). This is the single production entry point that turns the phase-1
 * view engine into a live renderer.
 *
 * The theme key is validated against a strict pattern before it is ever used to
 * assemble a search root; an invalid key simply drops the theme root (core-only
 * resolution) rather than assembling an attacker-influenced path. The resolver
 * itself performs realpath containment on top of this.
 */
final class ViewFactory
{
    private const THEME_KEY_PATTERN = '~^[a-z0-9][a-z0-9_-]*$~D';

    /** Project root (…/seo-editor). app/View/ViewFactory.php → app → root. */
    private static ?string $base = null;

    public static function base(): string
    {
        if (self::$base === null) {
            self::$base = dirname(__DIR__, 2);
        }

        return self::$base;
    }

    /** Default theme views subdirectory when the manifest declares no views_dir. */
    private const DEFAULT_VIEWS_DIR = 'views';

    /**
     * Ordered view roots for a theme: theme override root first, core root last.
     *
     * $viewsDir lets manifest v2 (template.json "views_dir") point the theme's
     * PHTML cascade at a directory other than the default "views"; an invalid
     * value falls back to the default. When null the default "views" is used, so
     * existing callers keep their exact behaviour.
     *
     * @return string[]
     */
    public static function roots(?string $themeKey, ?string $viewsDir = null): array
    {
        $roots = [];
        $themeKey = trim((string) $themeKey);
        if ($themeKey !== '' && preg_match(self::THEME_KEY_PATTERN, $themeKey) === 1) {
            $roots[] = self::base() . '/Templates/' . $themeKey . '/' . self::normalizeViewsDir($viewsDir);
        }
        $roots[] = self::base() . '/app/Views';

        return $roots;
    }

    /**
     * Build a ViewEngine for a theme.
     *
     * @param array $options shared render options (editMode, contentBlocks, assetBaseUrl)
     * @param ?string $viewsDir manifest v2 views_dir (default "views" when null)
     */
    public static function forTheme(?string $themeKey, array $options = [], ?string $viewsDir = null): ViewEngine
    {
        return new ViewEngine(new ViewResolver(self::roots($themeKey, $viewsDir)), $options);
    }

    /**
     * Absolute path to a theme's PHTML layout, or null when the theme ships no
     * <views_dir>/layout.phtml (→ legacy layout.html path is used instead).
     */
    public static function themeLayoutPath(?string $themeKey, ?string $viewsDir = null): ?string
    {
        return self::themeViewPath($themeKey, 'layout', $viewsDir);
    }

    /**
     * Absolute path to an arbitrary theme PHTML view (logical name, no extension,
     * e.g. "layout" or "pages/cenik"), or null when the theme key is invalid or
     * the file does not exist. Generalises themeLayoutPath() for manifest v2
     * per-page content overrides (overrides.<pageKey>.view). realpath containment
     * to the theme views root guards against traversal in the logical name.
     */
    public static function themeViewPath(?string $themeKey, string $view, ?string $viewsDir = null): ?string
    {
        $themeKey = trim((string) $themeKey);
        if ($themeKey === '' || preg_match(self::THEME_KEY_PATTERN, $themeKey) !== 1) {
            return null;
        }

        $view = trim($view);
        if ($view === '' || preg_match('~^[a-z0-9][a-z0-9/_-]*$~D', $view) !== 1) {
            return null;
        }

        $root = self::base() . '/Templates/' . $themeKey . '/' . self::normalizeViewsDir($viewsDir);
        $path = $root . '/' . $view . '.phtml';

        $realRoot = realpath($root);
        $realPath = realpath($path);
        if ($realRoot === false || $realPath === false) {
            return null;
        }
        if (strncmp($realPath, $realRoot, strlen($realRoot)) !== 0 || !is_file($realPath)) {
            return null;
        }

        return $realPath;
    }

    private static function normalizeViewsDir(?string $viewsDir): string
    {
        $viewsDir = trim(str_replace(['\\', "\0"], ['/', ''], (string) $viewsDir), '/');
        if ($viewsDir === '' || preg_match('~^[a-z0-9][a-z0-9/_-]*$~D', $viewsDir) !== 1) {
            return self::DEFAULT_VIEWS_DIR;
        }

        return $viewsDir;
    }
}
