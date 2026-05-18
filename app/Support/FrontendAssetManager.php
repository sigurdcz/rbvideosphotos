<?php

namespace App\Support;

final class FrontendAssetManager
{
    private const GENERATED_RELATIVE_DIR = '/assets/generated';
    private const GENERATED_PUBLIC_DIR = __DIR__ . '/../../public/assets/generated';

    public static function injectInlineAssets(string $html, string $css, string $js): string
    {
        $css = trim($css);
        $js = trim($js);

        if ($css !== '') {
            $html = self::injectGeneratedCss($html, $css, 'page-css', 'data-generated-page-css');
        }

        if ($js !== '') {
            $html = self::injectGeneratedJs($html, $js, 'page-js', 'data-generated-page-js');
        }

        return $html;
    }

    public static function injectGeneratedCss(string $html, string $css, string $prefix, string $marker): string
    {
        $css = trim($css);
        if ($css === '' || str_contains($html, $marker)) {
            return $html;
        }

        $href = self::writeGeneratedAsset($prefix, 'css', $css);
        return self::injectStylesheet($html, $href, $marker);
    }

    public static function injectGeneratedJs(string $html, string $js, string $prefix, string $marker): string
    {
        $js = trim($js);
        if ($js === '' || str_contains($html, $marker)) {
            return $html;
        }

        $src = self::writeGeneratedAsset($prefix, 'js', $js);
        return self::injectScript($html, $src, $marker);
    }

    public static function injectStylesheet(string $html, string $href, string $marker): string
    {
        if (str_contains($html, $marker)) {
            return $html;
        }

        $tag = '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES) . '" ' . $marker . '>';
        if (str_contains($html, '</head>')) {
            return str_replace('</head>', "\n" . $tag . "\n</head>", $html);
        }

        return $tag . $html;
    }

    public static function injectScript(string $html, string $src, string $marker): string
    {
        if (str_contains($html, $marker)) {
            return $html;
        }

        $tag = '<script src="' . htmlspecialchars($src, ENT_QUOTES) . '" ' . $marker . '></script>';
        if (str_contains($html, '</body>')) {
            return str_replace('</body>', "\n" . $tag . "\n</body>", $html);
        }

        return $html . $tag;
    }

    public static function writeGeneratedAsset(string $prefix, string $extension, string $content): string
    {
        self::ensureGeneratedDir();

        $content  = AssetMinifier::minify($content, $extension);
        $hash     = substr(sha1($content), 0, 16);
        $filename = $prefix . '-' . $hash . '.' . $extension;
        $path     = rtrim(self::GENERATED_PUBLIC_DIR, '\\/') . DIRECTORY_SEPARATOR . $filename;

        if (!is_file($path)) {
            file_put_contents($path, $content);
        }

        return self::GENERATED_RELATIVE_DIR . '/' . $filename;
    }

    /**
     * Delete all files from public/assets/generated/.
     * Called before a full site publish so stale un-minified assets are purged.
     */
    public static function clearGeneratedAssets(): void
    {
        if (!is_dir(self::GENERATED_PUBLIC_DIR)) {
            return;
        }

        foreach (glob(rtrim(self::GENERATED_PUBLIC_DIR, '\\/') . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private static function ensureGeneratedDir(): void
    {
        if (!is_dir(self::GENERATED_PUBLIC_DIR)) {
            mkdir(self::GENERATED_PUBLIC_DIR, 0755, true);
        }
    }
}
