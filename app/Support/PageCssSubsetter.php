<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Env;
use App\Support\Css\CssParser;
use App\Support\Css\PageCssPruner;
use App\Support\Css\TokenUniverse;

/**
 * Serves each page only the part of the theme stylesheet it can actually use.
 *
 * The shared bundle is ~40 KiB gzipped on every route while a single route needs a third
 * of it, and it blocks first paint for over a second on throttled mobile. The subset takes
 * the place of the bundle in the same blocking <link>, so nothing is deferred and the
 * layout cannot shift - the page still paints fully styled on the first try.
 *
 * Safety comes from three directions: the parser keeps at-rule context, the pruner only
 * drops a rule when the page provably lacks its class/id, and any failure at all returns
 * the original HTML with the full bundle still in place. PAGE_CSS_SUBSET=0 turns it off.
 */
final class PageCssSubsetter
{
    private const MARKER = 'data-generated-page-css';

    public static function isEnabled(): bool
    {
        // Env, not getenv: .env values are loaded into $_ENV/$_SERVER and never exported
        // to the process environment, so getenv() would ignore the switch entirely.
        return (string) (Env::get('PAGE_CSS_SUBSET', '1') ?? '1') !== '0';
    }

    public static function apply(string $html): string
    {
        if (!self::isEnabled()) {
            return $html;
        }

        try {
            return self::subset($html);
        } catch (\Throwable $e) {
            // A miss here must never cost the visitor a stylesheet.
            return $html;
        }
    }

    public static function cacheDir(): string
    {
        return dirname(__DIR__, 2) . '/storage/cache/page-css';
    }

    public static function clearCache(): void
    {
        $dir = self::cacheDir();
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $entry) {
            if (is_file($entry)) {
                @unlink($entry);
            }
        }
    }

    private static function subset(string $html): string
    {
        if (!preg_match('~<link rel="stylesheet" href="(/assets/generated/page-css-([a-f0-9]+)\.css)"\s+' . self::MARKER . '>~', $html, $m)) {
            return $html;
        }

        $root = dirname(__DIR__, 2);
        $bundlePath = $root . '/public' . $m[1];
        if (!is_file($bundlePath)) {
            return $html;
        }

        $css = (string) file_get_contents($bundlePath);

        // Unless the bundle survives a parse and re-serialize byte for byte, nothing about
        // a pruned version of it can be trusted. This is the guard the size check below
        // cannot provide: a mangled stylesheet is *smaller*, so it looks like a success.
        if (!self::isLossless($bundlePath, $css)) {
            return $html;
        }

        $universe = TokenUniverse::fromHtml($html, $root . '/public');

        $dictionary = self::dictionary($bundlePath, $css);
        $present = [];
        foreach ($dictionary as $token) {
            if (isset($universe[$token]) || TokenUniverse::isSafelisted($token)) {
                $present[] = $token;
            }
        }

        $key = sha1($m[2] . "\n" . implode(' ', $present));
        $pointer = self::cacheDir() . '/' . $key . '.href';

        if (is_file($pointer)) {
            $href = trim((string) file_get_contents($pointer));
            if ($href !== '' && is_file($root . '/public' . $href)) {
                return self::replaceHref($html, $m[0], $href);
            }
        }

        $keep = [];
        foreach ($present as $token) {
            $keep[$token] = true;
        }
        // Safelisted names are kept even when the page never mentions them.
        foreach ($dictionary as $token) {
            if (TokenUniverse::isSafelisted($token)) {
                $keep[$token] = true;
            }
        }

        $pruned = PageCssPruner::prune($css, $keep);
        if ($pruned === '' || strlen($pruned) >= strlen($css)) {
            return $html;
        }

        $href = FrontendAssetManager::writeGeneratedAsset(
            'page-css',
            'css',
            '/*! subset of page-css-' . $m[2] . '.css */' . $pruned
        );

        self::ensureCacheDir();
        self::writeAtomic($pointer, $href);

        return self::replaceHref($html, $m[0], $href);
    }

    /**
     * Class/id tokens the stylesheet cares about. Cached per bundle hash so the 260 KiB
     * parse happens once per theme build rather than once per request.
     *
     * @return list<string>
     */
    private static function dictionary(string $bundlePath, string $css): array
    {
        $file = self::cacheDir() . '/dict-' . self::buildHash($bundlePath) . '.json';

        if (is_file($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $tokens = PageCssPruner::selectorTokens($css);
        self::ensureCacheDir();
        self::writeAtomic($file, (string) json_encode($tokens));

        return $tokens;
    }

    /**
     * Whether the bundle round-trips through the parser unchanged, cached per build so the
     * 260 KiB parse is not repeated on every request.
     */
    private static function isLossless(string $bundlePath, string $css): bool
    {
        $file = self::cacheDir() . '/lossless-' . self::buildHash($bundlePath) . '.flag';
        if (is_file($file)) {
            return trim((string) file_get_contents($file)) === '1';
        }

        $verdict = CssParser::isLossless($css);
        self::ensureCacheDir();
        self::writeAtomic($file, $verdict ? '1' : '0');

        return $verdict;
    }

    private static function buildHash(string $bundlePath): string
    {
        return substr(sha1($bundlePath . '|' . (string) filemtime($bundlePath)), 0, 16);
    }

    /**
     * Writes through a temporary file so a concurrent reader never sees a partial one.
     * A failure is logged rather than swallowed - silently losing the cache turns every
     * request into a full parse of the bundle, with nothing to show why.
     */
    private static function writeAtomic(string $path, string $contents): void
    {
        $temp = $path . '.' . getmypid() . '.tmp';
        if (file_put_contents($temp, $contents) === false || !@rename($temp, $path)) {
            @unlink($temp);
            error_log('PageCssSubsetter: cannot write cache file ' . $path);
        }
    }

    private static function ensureCacheDir(): void
    {
        $dir = self::cacheDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    private static function replaceHref(string $html, string $linkTag, string $href): string
    {
        $replacement = '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES) . '" ' . self::MARKER . '>';

        return str_replace($linkTag, $replacement, $html);
    }
}
