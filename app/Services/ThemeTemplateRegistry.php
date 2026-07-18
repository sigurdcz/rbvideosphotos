<?php

namespace App\Services;

use App\Core\Logger;

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
        $css = $this->collectAssets($theme, $pageKey, 'css');

        if (($theme['key'] ?? '') === 'jemna-bezova' && $pageKey === 'homepage') {
            $css = trim($css . "\n\n" . $this->getJemnaBezovaHomepageCssOverrides());
        }

        return $css;
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

    private function getJemnaBezovaHomepageCssOverrides(): string
    {
        return <<<'CSS'
/* Runtime-safe homepage hero overrides. This is appended after saved layout CSS. */
.theme-jemna-bezova .hp-hero {
  text-align: center !important;
  align-items: center !important;
  justify-content: center !important;
}

.theme-jemna-bezova .hp-hero > .container {
  position: static !important;
  display: flex !important;
  width: min(calc(100% - 48px), 760px) !important;
  max-width: 760px !important;
  min-height: auto !important;
  margin-left: auto !important;
  margin-right: auto !important;
  padding-left: 0 !important;
  padding-right: 0 !important;
  flex-direction: column !important;
  align-items: center !important;
  justify-content: center !important;
  text-align: center !important;
}

.theme-jemna-bezova .hp-hero .section-label,
.theme-jemna-bezova .hp-hero h1,
.theme-jemna-bezova .hp-hero .hero-sub,
.theme-jemna-bezova .hp-hero .hero-cta-wrap {
  width: 100% !important;
  margin-left: auto !important;
  margin-right: auto !important;
  text-align: center !important;
  justify-content: center !important;
}

.theme-jemna-bezova .hp-hero .hero-cta-wrap {
  align-items: center !important;
}

@media (max-width: 760px) {
  .theme-jemna-bezova .hp-hero > .container {
    width: min(calc(100% - 40px), 352px) !important;
    max-width: 352px !important;
  }

  .theme-jemna-bezova .hp-hero .section-label {
    display: inline-flex !important;
    width: auto !important;
    max-width: 100% !important;
    min-height: 36px !important;
    padding: 6px 14px !important;
    margin: 0 auto 18px !important;
    align-items: center !important;
    justify-content: center !important;
    flex-wrap: wrap !important;
    white-space: normal !important;
    overflow-wrap: anywhere !important;
    word-break: break-word !important;
    line-height: 1.45 !important;
    text-align: center !important;
    letter-spacing: .22em !important;
    box-sizing: border-box !important;
  }

  .theme-jemna-bezova .hp-hero h1,
  .theme-jemna-bezova .hp-hero .hero-sub {
    max-width: 100% !important;
  }
}
CSS;
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
