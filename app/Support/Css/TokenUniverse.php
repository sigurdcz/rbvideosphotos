<?php

declare(strict_types=1);

namespace App\Support\Css;

use RuntimeException;

/**
 * Every identifier a page could plausibly carry as a class or id.
 *
 * The HTML alone is not enough: theme.js and the runtime scripts add classes such as
 * is-playing, gb-nav-open or gb-button after load. Those names are string literals in the
 * scripts, so tokenizing the page's own JavaScript captures them without having to run it.
 *
 * The cost of the two kinds of mistake is wildly asymmetric. A name collected that nothing
 * uses wastes a few bytes; a name missed drops the rule that styles it, and if that rule
 * carried a dimension the page shifts under the visitor after load. So every ambiguity here
 * resolves towards collecting more, and anything that cannot be read at all - a referenced
 * script that is missing from disk - aborts subsetting rather than guessing.
 */
final class TokenUniverse
{
    /**
     * Patterns kept no matter what, for names that may be assembled at runtime.
     * Deliberately broad - an unnecessary rule costs bytes, a missing one costs a bug.
     */
    private const SAFELIST = [
        '~^is-~',
        '~^has-~',
        '~^nav-active$~',
        '~^theme-~',
        // Ids the FAQ builds per item: 'gb-home-faq-answer-' + random.
        '~^gb-home-faq-answer-~',
    ];

    /**
     * Class/id attributes, in every quoting style that appears in served markup - including
     * the backslash-escaped form that turns up inside JavaScript string literals.
     */
    private const ATTRIBUTE_PATTERN =
        '~\b(?:class|id|for|data-target)\s*=\s*(?:\\\\"([^"]*)\\\\"|"([^"]*)"|\x27([^\x27]*)\x27|([^\s>"\x27]+))~i';

    /**
     * @return array<string,true>
     * @throws RuntimeException when a script the page loads cannot be read
     */
    public static function fromHtml(string $html, string $publicDir): array
    {
        // The document is scanned with the script patterns as well, so inline <script>
        // bodies and event-handler attributes are covered by the same rules as .js files.
        $universe = self::tokensOfScript($html);

        foreach (self::pageScopeTokens($html) as $token) {
            $universe[$token] = true;
        }

        foreach (self::localScriptPaths($html) as $relative) {
            $file = $publicDir . $relative;
            if (!is_file($file)) {
                throw new RuntimeException('Cannot read script ' . $relative . ' - token universe would be incomplete.');
            }
            foreach (self::tokensOfFile($file) as $token => $_) {
                $universe[$token] = true;
            }
        }

        return $universe;
    }

    /**
     * Class and id names carried by the markup.
     *
     * Only attribute values count. Tokenizing the whole document would pull in every Czech
     * word of body copy and almost nothing could ever be dropped - the first version of
     * this did exactly that and saved 18% where 50% was available.
     *
     * @return array<string,true>
     */
    public static function tokensOf(string $text): array
    {
        $tokens = [];

        if (!preg_match_all(self::ATTRIBUTE_PATTERN, $text, $matches, PREG_SET_ORDER)) {
            return $tokens;
        }

        foreach ($matches as $match) {
            // Exactly one of the four quoting alternatives participates in each match.
            $value = '';
            for ($group = 1; $group <= 4; $group++) {
                if (($match[$group] ?? '') !== '') {
                    $value = $match[$group];
                    break;
                }
            }
            foreach (preg_split('~\s+~', $value) ?: [] as $token) {
                if ($token !== '') {
                    $tokens[$token] = true;
                }
            }
        }

        return $tokens;
    }

    /**
     * Class names a script can add at runtime.
     *
     * theme.js never builds a class name by concatenation, so every name it can apply is a
     * string literal in one of these call shapes or in an innerHTML fragment.
     *
     * @return array<string,true>
     */
    public static function tokensOfScript(string $js): array
    {
        $tokens = self::tokensOf($js);

        $patterns = [
            '~classList\.(?:add|remove|toggle|contains|replace)\(([^)]*)\)~i',
            // classList['add']('x') - the bracket form reaches the same API.
            '~classList\s*\[\s*[\x27"](?:add|remove|toggle|contains|replace)[\x27"]\s*\]\s*\(([^)]*)\)~i',
            '~className\s*\+?=\s*([^;]+);~i',
            '~(?:querySelector|querySelectorAll|closest|matches)\(([^)]*)\)~i',
            '~setAttribute\(\s*[\x27"](?:class|id)[\x27"]\s*,([^)]*)\)~i',
            '~\.id\s*\+?=\s*([^;]+);~i',
        ];

        foreach ($patterns as $pattern) {
            if (!preg_match_all($pattern, $js, $matches)) {
                continue;
            }
            foreach ($matches[1] as $argument) {
                if (!preg_match_all('~[\x27"`]([^\x27"`]*)[\x27"`]~', $argument, $literals)) {
                    continue;
                }
                foreach ($literals[1] as $literal) {
                    foreach (preg_split('#[\s,.>+~\[\]:()\#]+#', $literal) ?: [] as $token) {
                        if ($token !== '' && preg_match('~^[A-Za-z_][A-Za-z0-9_-]*$~', $token) === 1) {
                            $tokens[$token] = true;
                        }
                    }
                }
            }
        }

        return $tokens;
    }

    /** True when a stylesheet token must survive regardless of the page. */
    public static function isSafelisted(string $token): bool
    {
        foreach (self::SAFELIST as $pattern) {
            if (preg_match($pattern, $token) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Values the document carries for the attributes rules may scope themselves by.
     *
     * @return list<string>
     */
    private static function pageScopeTokens(string $html): array
    {
        $tokens = [];
        foreach (SelectorTokenExtractor::PAGE_SCOPE_ATTRIBUTES as $attribute) {
            $pattern = '~\b' . preg_quote($attribute, '~')
                . '\s*=\s*(?:"([^"]*)"|\x27([^\x27]*)\x27|([^\s>"\x27]+))~i';
            if (!preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($matches as $match) {
                for ($group = 1; $group <= 3; $group++) {
                    if (($match[$group] ?? '') !== '') {
                        $tokens[] = SelectorTokenExtractor::attributeToken($attribute, $match[$group]);
                        break;
                    }
                }
            }
        }

        return $tokens;
    }

    /** @return list<string> same-origin script paths referenced by the document */
    private static function localScriptPaths(string $html): array
    {
        if (!preg_match_all('~<script[^>]+src\s*=\s*[\x27"](/[^\x27"]+)[\x27"]~i', $html, $matches)) {
            return [];
        }

        $paths = [];
        foreach ($matches[1] as $src) {
            // Cache-busting query strings are not part of the path on disk.
            $paths[strtok($src, '?')] = true;
        }

        return array_keys($paths);
    }

    /** @return array<string,true> */
    private static function tokensOfFile(string $file): array
    {
        static $cache = [];
        $key = $file . '|' . (string) filemtime($file) . '|' . (string) filesize($file);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $contents = (string) file_get_contents($file);

        return $cache[$key] = self::tokensOfScript($contents);
    }
}
