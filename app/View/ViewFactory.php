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

    /**
     * Ordered view roots for a theme: theme override root first, core root last.
     *
     * @return string[]
     */
    public static function roots(?string $themeKey): array
    {
        $roots = [];
        $themeKey = trim((string) $themeKey);
        if ($themeKey !== '' && preg_match(self::THEME_KEY_PATTERN, $themeKey) === 1) {
            $roots[] = self::base() . '/Templates/' . $themeKey . '/views';
        }
        $roots[] = self::base() . '/app/Views';

        return $roots;
    }

    /**
     * Build a ViewEngine for a theme.
     *
     * @param array $options shared render options (editMode, contentBlocks, assetBaseUrl)
     */
    public static function forTheme(?string $themeKey, array $options = []): ViewEngine
    {
        return new ViewEngine(new ViewResolver(self::roots($themeKey)), $options);
    }

    /**
     * Absolute path to a theme's PHTML layout, or null when the theme ships no
     * views/layout.phtml (→ legacy layout.html path is used instead).
     */
    public static function themeLayoutPath(?string $themeKey): ?string
    {
        $themeKey = trim((string) $themeKey);
        if ($themeKey === '' || preg_match(self::THEME_KEY_PATTERN, $themeKey) !== 1) {
            return null;
        }
        $path = self::base() . '/Templates/' . $themeKey . '/views/layout.phtml';

        return is_file($path) ? $path : null;
    }
}
