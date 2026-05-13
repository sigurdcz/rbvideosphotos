<?php

namespace App\Services;

class ThemeTemplateRegistry
{
    private string $rootPath;
    private string $stateFile;

    public function __construct(?string $rootPath = null)
    {
        $this->rootPath = $rootPath ?: dirname(__DIR__, 2) . '/Templates';
        $this->stateFile = dirname(__DIR__, 2) . '/storage/active-theme-key.txt';
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
            return $this->find($previewKey);
        }

        $activeKey = trim((string)($site['active_theme_key'] ?? ''));
        if ($activeKey === '') {
            $activeKey = $this->getPersistedActiveThemeKey();
        }
        if ($activeKey === '') {
            return null;
        }

        return $this->find($activeKey);
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
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if ($key === '') {
            if (is_file($this->stateFile)) {
                @unlink($this->stateFile);
            }
            return;
        }

        file_put_contents($this->stateFile, $key, LOCK_EX);
    }

    public function getCssForPage(array $theme, string $pageKey): string
    {
        return $this->collectAssets($theme, $pageKey, 'css');
    }

    public function getJsForPage(array $theme, string $pageKey): string
    {
        return $this->collectAssets($theme, $pageKey, 'js');
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
