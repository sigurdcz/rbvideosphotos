<?php

declare(strict_types=1);

namespace App\Support\Css;

/**
 * Character-level stylesheet parser.
 *
 * Written because Chrome's rule coverage reports a rule nested in @media(...) as the bare
 * inner rule: slicing those offsets silently drops every wrapper. Parsing into a tree and
 * re-serializing keeps each rule inside the at-rule it was found in, so the context cannot
 * be lost - a rule is never separated from its parent node in the first place.
 *
 * It tracks strings, escapes, comments and url() tokens, because theme CSS contains things
 * like content:"}" and url("data:...)...") that defeat any line- or brace-counting approach.
 *
 * Anything it cannot account for - an unterminated string or comment, a stray or missing
 * brace, trailing garbage - raises CssParseException instead of being guessed at. A parser
 * that quietly truncates a stylesheet produces a smaller file, which looks exactly like a
 * successful subset, so silence here would be indistinguishable from success.
 */
final class CssParser
{
    /** At-rules whose body is a list of nested rules rather than declarations. */
    private const NESTING_AT_RULES = ['media', 'supports', 'container', 'layer', 'document'];

    private const BACKSLASH = "\\";

    /**
     * @return list<CssNode>
     * @throws CssParseException when the input cannot be parsed unambiguously
     */
    public static function parse(string $css): array
    {
        $pos = 0;
        $nodes = self::parseNodes($css, $pos, strlen($css), false);
        if ($pos < strlen($css)) {
            throw new CssParseException('Unconsumed input at offset ' . $pos . '.');
        }

        return $nodes;
    }

    /** True when the stylesheet survives a parse and re-serialize without changing a byte. */
    public static function isLossless(string $css): bool
    {
        try {
            return self::serialize(self::parse($css)) === $css;
        } catch (CssParseException) {
            return false;
        }
    }

    /** @param list<CssNode> $nodes */
    public static function serialize(array $nodes): string
    {
        $out = '';
        foreach ($nodes as $node) {
            if ($node->type === CssNode::TYPE_VERBATIM_AT) {
                $out .= $node->prelude;
                continue;
            }
            if ($node->type === CssNode::TYPE_BLOCK_AT) {
                $out .= $node->prelude . '{' . self::serialize($node->children) . '}';
                continue;
            }
            $out .= $node->prelude . '{' . $node->body . '}';
        }

        return $out;
    }

    /**
     * @param int  $pos    cursor, advanced past the consumed text
     * @param int  $end    offset at which this nesting level ends
     * @param bool $nested true inside an at-rule body, where a closing brace is required
     * @return list<CssNode>
     */
    private static function parseNodes(string $css, int &$pos, int $end, bool $nested): array
    {
        $nodes = [];
        $prelude = '';
        $closed = false;

        while ($pos < $end) {
            $char = $css[$pos];

            if ($char === '}') {
                if (!$nested) {
                    throw new CssParseException('Stray closing brace at offset ' . $pos . '.');
                }
                $pos++;
                $closed = true;
                break;
            }

            if ($char === '/' && ($css[$pos + 1] ?? '') === '*') {
                $close = strpos($css, '*/', $pos + 2);
                if ($close === false) {
                    throw new CssParseException('Unterminated comment at offset ' . $pos . '.');
                }
                // Preserved comments carry provenance and must survive; the rest is noise.
                if (($css[$pos + 2] ?? '') === '!') {
                    $nodes[] = CssNode::verbatimAt('comment', substr($css, $pos, $close + 2 - $pos));
                }
                $pos = $close + 2;
                continue;
            }

            if ($char === '{') {
                $pos++;
                $name = self::atRuleName($prelude);
                if ($name !== '' && in_array($name, self::NESTING_AT_RULES, true)) {
                    $children = self::parseNodes($css, $pos, $end, true);
                    $nodes[] = CssNode::blockAt($name, rtrim($prelude), $children);
                } elseif ($name !== '') {
                    $body = self::readBalancedBody($css, $pos, $end);
                    $nodes[] = CssNode::verbatimAt($name, rtrim($prelude) . '{' . $body . '}');
                } else {
                    $body = self::readBalancedBody($css, $pos, $end);
                    $nodes[] = CssNode::rule(trim($prelude), $body);
                }
                $prelude = '';
                continue;
            }

            if ($char === ';' && str_starts_with(ltrim($prelude), '@')) {
                // Statement at-rule such as @import or @charset.
                $nodes[] = CssNode::verbatimAt(self::atRuleName($prelude), rtrim($prelude) . ';');
                $pos++;
                $prelude = '';
                continue;
            }

            $prelude .= self::consumeToken($css, $pos, $end);
        }

        if ($nested && !$closed) {
            throw new CssParseException('Unclosed block at offset ' . $end . '.');
        }
        if (trim($prelude) !== '') {
            throw new CssParseException('Trailing text outside any rule.');
        }

        return $nodes;
    }

    /** Reads declarations (or nested blocks) up to the matching closing brace. */
    private static function readBalancedBody(string $css, int &$pos, int $end): string
    {
        $body = '';
        $depth = 0;

        while ($pos < $end) {
            $char = $css[$pos];
            if ($char === '}' && $depth === 0) {
                $pos++;

                return $body;
            }
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
            }
            $body .= self::consumeToken($css, $pos, $end);
        }

        throw new CssParseException('Unclosed declaration block at offset ' . $end . '.');
    }

    /**
     * Copies one unit of text, keeping strings, escapes, comments and url() intact so that
     * a brace or semicolon inside them is never mistaken for structure.
     */
    private static function consumeToken(string $css, int &$pos, int $end): string
    {
        $char = $css[$pos];

        if ($char === self::BACKSLASH) {
            if ($pos + 1 >= $end) {
                throw new CssParseException('Trailing escape at offset ' . $pos . '.');
            }
            $chunk = substr($css, $pos, 2);
            $pos += 2;

            return $chunk;
        }

        if ($char === '"' || $char === "'") {
            return self::consumeString($css, $pos, $end);
        }

        // url() may hold unquoted content, so a brace inside it would otherwise read as
        // structure; a quoted argument may equally hold the closing paren itself.
        if (($char === 'u' || $char === 'U') && strcasecmp(substr($css, $pos, 4), 'url(') === 0) {
            $start = $pos;
            $pos += 4;
            while ($pos < $end && strpos(" \t\r\n", $css[$pos]) !== false) {
                $pos++;
            }
            if ($pos < $end && ($css[$pos] === '"' || $css[$pos] === "'")) {
                self::consumeString($css, $pos, $end);
            }
            while ($pos < $end && $css[$pos] !== ')') {
                if ($css[$pos] === self::BACKSLASH) {
                    $pos += 2;
                    continue;
                }
                $pos++;
            }
            if ($pos >= $end) {
                throw new CssParseException('Unterminated url() at offset ' . $start . '.');
            }
            $pos++;

            return substr($css, $start, $pos - $start);
        }

        if ($char === '/' && ($css[$pos + 1] ?? '') === '*') {
            $close = strpos($css, '*/', $pos + 2);
            if ($close === false) {
                throw new CssParseException('Unterminated comment at offset ' . $pos . '.');
            }
            $chunk = substr($css, $pos, $close + 2 - $pos);
            $pos = $close + 2;

            return $chunk;
        }

        $pos++;

        return $char;
    }

    /** Consumes a quoted string including both delimiters. */
    private static function consumeString(string $css, int &$pos, int $end): string
    {
        $quote = $css[$pos];
        $start = $pos;
        $pos++;
        while ($pos < $end) {
            if ($css[$pos] === self::BACKSLASH) {
                $pos += 2;
                continue;
            }
            if ($css[$pos] === $quote) {
                $pos++;

                return substr($css, $start, $pos - $start);
            }
            $pos++;
        }

        throw new CssParseException('Unterminated string at offset ' . $start . '.');
    }

    /** Lower-cased at-rule name of a prelude, or '' when the prelude is a selector. */
    private static function atRuleName(string $prelude): string
    {
        $trimmed = ltrim($prelude);
        if (($trimmed[0] ?? '') !== '@') {
            return '';
        }

        return strtolower((string) preg_replace('~^@([a-z-]+).*$~is', '$1', $trimmed));
    }
}
