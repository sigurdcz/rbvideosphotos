<?php

namespace App\Support;

final class AssetMinifier
{
    public static function minify(string $content, string $type): string
    {
        return match ($type) {
            'css'   => self::minifyCss($content),
            'js'    => self::minifyJs($content),
            default => $content,
        };
    }

    public static function minifyCss(string $css): string
    {
        // Remove block comments (keep /*! preserved comments)
        $css = preg_replace('~/\*(?!!)[^*]*\*+(?:[^/*][^*]*\*+)*/~', '', $css) ?? $css;
        // Collapse all whitespace to single space
        $css = preg_replace('~\s+~', ' ', $css) ?? $css;
        // Remove spaces around braces and semicolons
        $css = preg_replace('~ *\{ *~', '{', $css) ?? $css;
        $css = preg_replace('~ *\} *~', '}', $css) ?? $css;
        $css = preg_replace('~ *; *~', ';', $css) ?? $css;
        $css = preg_replace('~ *: *~', ':', $css) ?? $css;
        $css = preg_replace('~ *, *~', ',', $css) ?? $css;
        // Remove last semicolons before closing brace
        $css = str_replace(';}', '}', $css);
        return trim($css);
    }

    public static function minifyJs(string $js): string
    {
        // Remove block comments (keep /*! preserved comments)
        $js = preg_replace('~/\*(?!!)[^*]*\*+(?:[^/*][^*]*\*+)*/~s', '', $js) ?? $js;
        // Remove single-line comments — skip URLs (preceded by : or /) and shebang
        $js = preg_replace('~(?<![:/=\'"])//[^\n]*~m', '', $js) ?? $js;
        // Collapse whitespace
        $js = preg_replace('~[ \t]+~', ' ', $js) ?? $js;
        $js = preg_replace('~(\r?\n){2,}~', "\n", $js) ?? $js;
        // Remove spaces around common operators/punctuation (safe subset)
        $js = preg_replace('~ *([{};,]) *~', '$1', $js) ?? $js;
        // Remove leading/trailing space on each line
        $js = preg_replace('~^ +| +$~m', '', $js) ?? $js;
        // Remove blank lines
        $js = preg_replace('~\n+~', "\n", $js) ?? $js;
        return trim($js);
    }
}
