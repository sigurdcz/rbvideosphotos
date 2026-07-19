<?php

namespace App\Services;

use App\Core\Logger;
use App\View\ViewFactory;

class ThemeTemplateRegistry
{
    public const DEFAULT_THEME_KEY = 'elegant-white';
    public const DEFAULT_THEME_VERSION = '1.0.0';

    private string $rootPath;
    private string $stateFile;

    public function __construct(?string $rootPath = null, ?string $stateFile = null)
    {
        $this->rootPath = $rootPath ?: dirname(__DIR__, 2) . '/Templates';
        $this->stateFile = $stateFile ?: dirname(__DIR__, 2) . '/storage/active-theme-key.txt';
    }

    public function all(): array
    {
        if (!is_dir($this->rootPath)) {
            return [];
        }

        $items = [];
        foreach (glob($this->rootPath . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $theme = $this->loadTheme($dir);
            if ($theme !== null) {
                $items[] = $theme;
            }
        }

        usort($items, static function (array $a, array $b): int {
            $orderA = (int)($a['sort_order'] ?? 0);
            $orderB = (int)($b['sort_order'] ?? 0);
            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }

            return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });

        return $items;
    }

    public function find(string $key): ?array
    {
        $key = trim($key);
        if ($key === '') {
            return null;
        }

        foreach ($this->all() as $theme) {
            if (($theme['key'] ?? '') === $key) {
                return $theme;
            }
        }

        return null;
    }

    public function resolveForSite(?array $site, ?string $previewKey = null): ?array
    {
        $previewKey = trim((string)$previewKey);
        if ($previewKey !== '') {
            $theme = $this->find($previewKey);
            Logger::infoTo('theme-debug', 'theme.preview.resolve', 'Preview theme resolution requested', [
                'preview_key' => $previewKey,
                'resolved' => $theme['key'] ?? null,
                'site_id' => $site['id'] ?? null,
                'site_name' => $site['name'] ?? null,
            ]);
            return $theme;
        }

        $activeKey = trim((string)($site['active_theme_key'] ?? ''));
        $activeKeySource = 'db';
        if ($activeKey === '') {
            $activeKey = $this->getPersistedActiveThemeKey();
            $activeKeySource = 'state_file';
        }
        if ($activeKey === '') {
            $activeKey = self::DEFAULT_THEME_KEY;
            $activeKeySource = 'default';
        }

        Logger::infoTo('theme-debug', 'theme.resolve.request', 'Theme resolution requested', [
            'site_id' => $site['id'] ?? null,
            'site_name' => $site['name'] ?? null,
            'db_active_theme_key' => trim((string)($site['active_theme_key'] ?? '')) ?: null,
            'state_active_theme_key' => $this->getPersistedActiveThemeKey() ?: null,
            'resolved_active_theme_key' => $activeKey !== '' ? $activeKey : null,
            'active_key_source' => $activeKeySource,
            'preview_key' => null,
        ]);

        $theme = $this->find($activeKey);
        if ($activeKey === self::DEFAULT_THEME_KEY) {
            $resolvedVersion = trim((string)($theme['version'] ?? ''));
            if ($theme === null || $resolvedVersion !== self::DEFAULT_THEME_VERSION) {
                Logger::errorTo('theme-debug', 'theme.resolve.default_invalid', 'Default theme is missing or has an unexpected version', [
                    'expected_key' => self::DEFAULT_THEME_KEY,
                    'expected_version' => self::DEFAULT_THEME_VERSION,
                    'resolved_version' => $resolvedVersion !== '' ? $resolvedVersion : null,
                ]);
                throw new \RuntimeException(sprintf(
                    'Default theme %s version %s was not found.',
                    self::DEFAULT_THEME_KEY,
                    self::DEFAULT_THEME_VERSION
                ));
            }
        }

        Logger::infoTo('theme-debug', 'theme.resolve.result', 'Theme resolution completed', [
            'site_id' => $site['id'] ?? null,
            'site_name' => $site['name'] ?? null,
            'resolved_active_theme_key' => $activeKey,
            'theme_found' => $theme !== null,
            'theme_key' => $theme['key'] ?? null,
            'theme_name' => $theme['name'] ?? null,
        ]);
        return $theme;
    }

    public function normalizePageKey(string $urlPath): string
    {
        $path = trim($urlPath, '/');
        return $path === '' ? 'homepage' : $path;
    }

    public function getPersistedActiveThemeKey(): string
    {
        if (!is_file($this->stateFile)) {
            return '';
        }

        $value = trim((string)file_get_contents($this->stateFile));
        return $value !== '' ? $value : '';
    }

    public function persistActiveThemeKey(?string $key): void
    {
        $key = trim((string)$key);
        $dir = dirname($this->stateFile);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Unable to create active theme state directory: ' . $dir);
        }

        if ($key === '') {
            if (is_file($this->stateFile) && !unlink($this->stateFile)) {
                throw new \RuntimeException('Unable to remove active theme state file: ' . $this->stateFile);
            }
            return;
        }

        if (file_put_contents($this->stateFile, $key, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to persist active theme state: ' . $this->stateFile);
        }
    }

    public function getCssForPage(array $theme, string $pageKey): string
    {
        $pageKey = $this->normalizePageKey($pageKey);

        // Phase 6: the former jemna-bezova homepage hero override heredoc moved
        // out of core into Templates/jemna-bezova/assets/homepage-overrides.css,
        // wired via the theme's overrides.homepage.assets.css — so it is now just
        // another homepage asset collected below (no theme-specific branch here).
        return $this->collectAssets($theme, $pageKey, 'css');
    }

    public function getJsForPage(array $theme, string $pageKey): string
    {
        return $this->collectAssets($theme, $pageKey, 'js');
    }

    public function getSharedComponentCss(): string
    {
        // Shared component CSS lives as a static asset (app/Views/assets/shared-components.css)
        // rather than an inline heredoc; see TEMPLATE_REFACTOR_PLAN.md phase 3.
        $path = dirname(__DIR__) . "/Views/assets/shared-components.css";
        $css = is_file($path) ? (string) file_get_contents($path) : "";

        return $css;
    }

    public function getLayoutHtmlForPage(array $theme, string $pageKey): string
    {
        $pageKey = $this->normalizePageKey($pageKey);
        $candidates = [];

        if (!empty($theme['layout_html'])) {
            $candidates[] = (string)$theme['layout_html'];
        }

        if (!empty($theme['overrides'][$pageKey]['layout_html'])) {
            $candidates[] = (string)$theme['overrides'][$pageKey]['layout_html'];
        }

        foreach ($candidates as $relativePath) {
            $content = $this->readThemeFile($theme, $relativePath);
            if ($content !== '') {
                return $content;
            }
        }

        return '';
    }

    /**
     * Manifest v2 views_dir (opt-in PHTML views cascade directory). Empty string
     * when the theme has not adopted manifest v2.
     */
    public function getViewsDir(array $theme): string
    {
        return trim((string)($theme['views_dir'] ?? ''));
    }

    /**
     * Whether a theme has opted into the manifest v2 PHTML views cascade. Mirrors
     * the phase-3 ThemeLayoutRenderer::hasPhtmlLayout() opt-in precedent, but is
     * driven by an explicit manifest key rather than only file existence, so a
     * theme's per-page content overrides never activate by accident.
     */
    public function hasViewsCascade(array $theme): bool
    {
        return $this->getViewsDir($theme) !== '';
    }

    /**
     * Manifest v2 per-page content view override (overrides.<pageKey>.view), or
     * '' when none. The value may be a logical view name ("pages/cenik") or the
     * manifest-style path form from the plan ("views/pages/cenik.phtml").
     */
    public function getPageViewOverride(array $theme, string $pageKey): string
    {
        $pageKey = $this->normalizePageKey($pageKey);
        return trim((string)($theme['overrides'][$pageKey]['view'] ?? ''));
    }

    /**
     * Resolve the theme content view a generic DB page should render through,
     * honoring manifest v2. Returns ['themeKey','view','viewsDir'] only when the
     * theme has opted into the views cascade (declares views_dir) AND ships a
     * content view for $pageKey — either an explicit overrides.<pageKey>.view or a
     * <views_dir>/pages/<pageKey>.phtml file. Returns null otherwise, so callers
     * fall back to the core archetypes/page wrapper. Every theme that has not
     * adopted manifest v2 (including the active theme) resolves to null → no
     * behaviour change.
     *
     * @return array{themeKey: string, view: string, viewsDir: string}|null
     */
    public function pageContentView(?array $theme, string $pageKey): ?array
    {
        if ($theme === null) {
            return null;
        }

        $viewsDir = $this->getViewsDir($theme);
        if ($viewsDir === '') {
            return null;
        }

        $themeKey = (string)($theme['key'] ?? '');
        $pageKey = $this->normalizePageKey($pageKey);

        $candidates = [];
        $override = $this->getPageViewOverride($theme, $pageKey);
        if ($override !== '') {
            $candidates[] = $this->normalizeViewLogicalName($override, $viewsDir);
        }
        $candidates[] = 'pages/' . $pageKey;

        foreach ($candidates as $view) {
            if ($view !== '' && ViewFactory::themeViewPath($themeKey, $view, $viewsDir) !== null) {
                return ['themeKey' => $themeKey, 'view' => $view, 'viewsDir' => $viewsDir];
            }
        }

        return null;
    }

    /**
     * Resolve the content view for a page directly from a site row (resolves the
     * active theme first). Convenience for the live render seam, which does not
     * carry a pre-resolved theme array.
     *
     * @return array{themeKey: string, view: string, viewsDir: string}|null
     */
    public function pageContentViewForSite(?array $site, string $pageKey, ?string $previewKey = null): ?array
    {
        return $this->pageContentView($this->resolveForSite($site, $previewKey), $pageKey);
    }

    private function normalizeViewLogicalName(string $value, string $viewsDir): string
    {
        $value = trim(str_replace('\\', '/', $value));
        if (str_ends_with($value, '.phtml')) {
            $value = substr($value, 0, -6);
        }
        $prefix = trim($viewsDir, '/') . '/';
        if (str_starts_with($value, $prefix)) {
            $value = substr($value, strlen($prefix));
        }
        return trim($value, '/');
    }

    public function getThemeSummary(array $theme): array
    {
        return [
            'key' => (string)($theme['key'] ?? ''),
            'name' => (string)($theme['name'] ?? ''),
            'description' => (string)($theme['description'] ?? ''),
            'version' => (string)($theme['version'] ?? ''),
            'author' => (string)($theme['author'] ?? ''),
            'sort_order' => (int)($theme['sort_order'] ?? 0),
            'pages' => array_values(array_filter((array)($theme['pages'] ?? []), static fn($v) => trim((string)$v) !== '')),
            'asset_css' => count($this->assetList($theme, 'css')),
            'asset_js' => count($this->assetList($theme, 'js')),
            'preview_image' => (string)($theme['preview_image'] ?? ''),
            'active' => !empty($theme['active']),
        ];
    }

    public function injectBodyClass(string $html, string $themeKey): string
    {
        $themeClass = 'theme-' . preg_replace('~[^a-z0-9_-]+~i', '-', trim($themeKey));
        if ($themeClass === 'theme-') {
            return $html;
        }

        return preg_replace_callback('~<body\b([^>]*)>~i', static function (array $matches) use ($themeClass): string {
            $attrs = $matches[1] ?? '';
            if (preg_match('~\bclass=(["\'])(.*?)\1~i', $attrs, $classMatch)) {
                $existing = trim($classMatch[2]);
                $classes = preg_split('~\s+~', $existing, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                if (!in_array($themeClass, $classes, true)) {
                    $classes[] = $themeClass;
                }
                $replacement = 'class="' . htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8') . '"';
                $attrs = preg_replace('~\bclass=(["\'])(.*?)\1~i', $replacement, $attrs, 1);
            } else {
                $attrs .= ' class="' . htmlspecialchars($themeClass, ENT_QUOTES, 'UTF-8') . '"';
            }

            return '<body' . $attrs . '>';
        }, $html, 1) ?? $html;
    }

    private function loadTheme(string $dir): ?array
    {
        $manifestPath = rtrim($dir, '/\\') . '/template.json';
        if (!is_file($manifestPath)) {
            return null;
        }

        $json = json_decode((string)file_get_contents($manifestPath), true);
        if (!is_array($json)) {
            return null;
        }

        $key = trim((string)($json['key'] ?? basename($dir)));
        $name = trim((string)($json['name'] ?? ''));
        if ($key === '' || $name === '') {
            return null;
        }

        $theme = [
            'key' => $key,
            'name' => $name,
            'description' => trim((string)($json['description'] ?? '')),
            'version' => trim((string)($json['version'] ?? '')),
            'author' => trim((string)($json['author'] ?? '')),
            'sort_order' => (int)($json['sort_order'] ?? 0),
            'preview_image' => trim((string)($json['preview_image'] ?? '')),
            'pages' => array_values(array_map('strval', (array)($json['pages'] ?? []))),
            'assets' => [
                'css' => $this->normalizeAssetList($json['assets']['css'] ?? []),
                'js' => $this->normalizeAssetList($json['assets']['js'] ?? []),
            ],
            'layout_html' => trim((string)($json['layout_html'] ?? '')),
            'layout_css' => trim((string)($json['layout_css'] ?? '')),
            'layout_js' => trim((string)($json['layout_js'] ?? '')),
            // Manifest v2 (TEMPLATE_REFACTOR_PLAN.md 2.6): opt-in PHTML views
            // cascade directory. Empty string = theme has not adopted v2, legacy
            // behaviour unchanged.
            'views_dir' => trim((string)($json['views_dir'] ?? '')),
            'overrides' => [],
            'path' => $dir,
        ];

        foreach ((array)($json['overrides'] ?? []) as $pageKey => $override) {
            $pageKey = $this->normalizePageKey((string)$pageKey);
            if ($pageKey === '') {
                continue;
            }

            $theme['overrides'][$pageKey] = [
                'layout_html' => trim((string)($override['layout_html'] ?? '')),
                // Manifest v2: per-page CONTENT view override (not just layout).
                'view' => trim((string)($override['view'] ?? '')),
                'css' => $this->normalizeAssetList($override['assets']['css'] ?? $override['css'] ?? []),
                'js' => $this->normalizeAssetList($override['assets']['js'] ?? $override['js'] ?? []),
            ];
        }

        return $theme;
    }

    private function normalizeAssetList(mixed $assets): array
    {
        if (is_string($assets)) {
            $assets = [$assets];
        }

        $items = [];
        foreach ((array)$assets as $asset) {
            $asset = trim((string)$asset);
            if ($asset !== '') {
                $items[] = $asset;
            }
        }

        return array_values(array_unique($items));
    }

    private function assetList(array $theme, string $type): array
    {
        $items = [];

        foreach ($this->normalizeAssetList($theme['assets'][$type] ?? []) as $relativePath) {
            $items[] = $relativePath;
        }

        $pageKey = 'homepage';
        foreach ($this->normalizeAssetList($theme['overrides'][$pageKey]['assets'][$type] ?? $theme['overrides'][$pageKey][$type] ?? []) as $relativePath) {
            $items[] = $relativePath;
        }

        return array_values(array_unique($items));
    }

    private function collectAssets(array $theme, string $pageKey, string $type): string
    {
        $pageKey = $this->normalizePageKey($pageKey);
        $paths = [];

        if (!empty($theme['layout_' . $type])) {
            $paths[] = (string)$theme['layout_' . $type];
        }

        foreach ($this->normalizeAssetList($theme['assets'][$type] ?? []) as $relativePath) {
            $paths[] = $relativePath;
        }

        foreach ($this->normalizeAssetList($theme['overrides'][$pageKey]['assets'][$type] ?? $theme['overrides'][$pageKey][$type] ?? []) as $relativePath) {
            $paths[] = $relativePath;
        }

        if ($pageKey !== 'homepage' && !empty($theme['overrides'][$pageKey]['layout_' . $type])) {
            $paths[] = (string)$theme['overrides'][$pageKey]['layout_' . $type];
        }

        $contents = [];
        foreach (array_values(array_unique($paths)) as $relativePath) {
            $content = $this->readThemeFile($theme, $relativePath);
            if ($content !== '') {
                $contents[] = $content;
            }
        }

        return trim(implode("\n\n", $contents));
    }

    private function readThemeFile(array $theme, string $relativePath): string
    {
        $relativePath = str_replace(['\\', "\0"], ['/', ''], trim($relativePath));
        if ($relativePath === '') {
            return '';
        }

        $root = rtrim((string)($theme['path'] ?? ''), '/\\');
        if ($root === '') {
            return '';
        }

        $fullPath = $root . '/' . ltrim($relativePath, '/');
        $realRoot = realpath($root);
        $realPath = realpath($fullPath);
        if ($realRoot === false || $realPath === false) {
            return is_file($fullPath) ? (string)file_get_contents($fullPath) : '';
        }

        if (strpos($realPath, $realRoot) !== 0 || !is_file($realPath)) {
            return '';
        }

        return (string)file_get_contents($realPath);
    }
}
