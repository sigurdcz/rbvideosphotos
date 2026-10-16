<?php

declare(strict_types=1);

namespace App\Support\Css;

/**
 * Works out which class and id tokens a selector genuinely depends on.
 *
 * The rule of this whole subsystem: keeping CSS that turns out to be unnecessary is fine,
 * dropping CSS that was needed is a bug. Everything ambiguous therefore resolves to "no
 * requirement", which keeps the rule.
 */
final class SelectorTokenExtractor
{
    /** Attributes the server stamps on the page and no script ever rewrites. */
    public const PAGE_SCOPE_ATTRIBUTES = ['data-cms-page-key'];


    /**
     * Required tokens per comma-separated alternative.
     *
     * An empty inner list means "this alternative has no class/id requirement at all", so
     * the rule always survives - element, attribute and universal selectors never make a
     * rule droppable.
     *
     * @return list<list<string>>
     */
    public static function requiredTokens(string $selectorList): array
    {
        $out = [];
        foreach (self::splitAlternatives($selectorList) as $alternative) {
            $out[] = self::tokensOfAlternative($alternative);
        }

        return $out === [] ? [[]] : $out;
    }

    /** @param array<string,true> $universe */
    public static function matches(string $selectorList, array $universe): bool
    {
        foreach (self::requiredTokens($selectorList) as $required) {
            $ok = true;
            foreach ($required as $token) {
                if (!isset($universe[$token])) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return true;
            }
        }

        return false;
    }

    /** Splits on commas that sit outside parentheses, strings and escapes. */
    private static function splitAlternatives(string $selectorList): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $len = strlen($selectorList);

        for ($i = 0; $i < $len; $i++) {
            $char = $selectorList[$i];
            if ($char === chr(92)) {
                $current .= substr($selectorList, $i, 2);
                $i++;
                continue;
            }
            if ($char === '"' || $char === "'") {
                $end = self::skipString($selectorList, $i, $len);
                $current .= substr($selectorList, $i, $end - $i);
                $i = $end - 1;
                continue;
            }
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth = max(0, $depth - 1);
            } elseif ($char === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';
                continue;
            }
            $current .= $char;
        }

        if (trim($current) !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    private static function skipString(string $text, int $start, int $len): int
    {
        $quote = $text[$start];
        $i = $start + 1;
        while ($i < $len) {
            if ($text[$i] === chr(92)) {
                $i += 2;
                continue;
            }
            if ($text[$i] === $quote) {
                return $i + 1;
            }
            $i++;
        }

        return $len;
    }

    /** @return list<string> */
    private static function tokensOfAlternative(string $alternative): array
    {
        // An escaped identifier (.\31 0, .md\:flex) cannot be decoded reliably here, and a
        // wrong decode means a wrong drop, so such a selector states no requirement at all.
        if (str_contains($alternative, chr(92))) {
            return [];
        }

        // Attribute selectors carry arbitrary strings - [href$=".pdf"], [data-v="#top"] -
        // whose dots and hashes are not class or id references. Removing them first stops
        // "pdf" and "top" from being demanded of the page. The one exception is the page
        // scope attribute, which is a genuine requirement; see attributeRequirements().
        $required = self::attributeRequirements($alternative);
        $cleaned = self::stripAttributeSelectors($alternative);

        // No functional pseudo-class makes a token mandatory. :not()/:is()/:where() match
        // without their argument; :has(a, b) and :nth-child(n of S) take a selector *list*,
        // where any one alternative suffices - so requiring all of them would drop rules
        // that do apply. Dropping the whole argument keeps the rule instead.
        $cleaned = self::stripFunctional($cleaned);

        $tokens = [];
        foreach ($required as $requirement) {
            $tokens[$requirement] = true;
        }
        if (preg_match_all(self::tokenPattern(), $cleaned, $matches)) {
            foreach ($matches[1] as $token) {
                $token = self::unescape($token);
                if ($token !== '') {
                    $tokens[$token] = true;
                }
            }
        }

        return array_keys($tokens);
    }

    /** Removes every :name(...) functional pseudo-class along with its argument. */
    private static function stripFunctional(string $selector): string
    {
        while (preg_match('~:[A-Za-z-]+\(~', $selector, $m, PREG_OFFSET_CAPTURE) === 1) {
            $pos = (int) $m[0][1];
            $i = $pos + strlen($m[0][0]) - 1;
            $len = strlen($selector);
            $depth = 0;
            for (; $i < $len; $i++) {
                if ($selector[$i] === '"' || $selector[$i] === "'") {
                    $i = self::skipString($selector, $i, $len) - 1;
                    continue;
                }
                if ($selector[$i] === '(') {
                    $depth++;
                } elseif ($selector[$i] === ')') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                }
            }
            $selector = substr($selector, 0, $pos) . substr($selector, min($i + 1, $len));
        }

        return $selector;
    }

    /**
     * Requirements stated by attribute selectors that can be trusted as page facts.
     *
     * Only PAGE_SCOPE_ATTRIBUTES with the exact-match operator qualify. data-cms-page-key
     * is written once on <body> by the server and never touched at runtime - it is how 341
     * of the theme's rules scope themselves to one page - so a page whose key differs
     * provably cannot match them. Every other attribute is left as no requirement: a value
     * that some script might set later must not be allowed to drop a rule.
     *
     * @return list<string>
     */
    private static function attributeRequirements(string $selector): array
    {
        $out = [];
        foreach (self::PAGE_SCOPE_ATTRIBUTES as $attribute) {
            $pattern = '~\[' . preg_quote($attribute, '~')
                . '\s*=\s*(?:"([^"]*)"|\x27([^\x27]*)\x27|([^\]\s]+))\]~i';
            if (!preg_match_all($pattern, $selector, $matches, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($matches as $match) {
                for ($group = 1; $group <= 3; $group++) {
                    if (($match[$group] ?? '') !== '') {
                        $out[] = self::attributeToken($attribute, $match[$group]);
                        break;
                    }
                }
            }
        }

        return $out;
    }

    /** The universe key under which a page records one of its scope attributes. */
    public static function attributeToken(string $attribute, string $value): string
    {
        return '@' . strtolower($attribute) . '=' . $value;
    }

    /** Removes [...] attribute selectors, whose quoted values are not class or id names. */
    private static function stripAttributeSelectors(string $selector): string
    {
        $out = '';
        $len = strlen($selector);

        for ($i = 0; $i < $len; $i++) {
            $char = $selector[$i];
            if ($char !== '[') {
                $out .= $char;
                continue;
            }
            $i++;
            while ($i < $len && $selector[$i] !== ']') {
                if ($selector[$i] === '"' || $selector[$i] === "'") {
                    $i = self::skipString($selector, $i, $len);
                    continue;
                }
                $i++;
            }
        }

        return $out;
    }

    private static function tokenPattern(): string
    {
        // [.#] followed by an identifier, where an escaped character counts as part of it.
        $bs = chr(92);
        return '~[.#]((?:[A-Za-z0-9_-]|[^\x00-\x7F]|' . $bs . $bs . '.)+)~u';
    }

    private static function unescape(string $token): string
    {
        return (string) preg_replace('~' . chr(92) . chr(92) . '(.)~u', '$1', $token);
    }
}
