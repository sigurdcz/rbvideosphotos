<?php

declare(strict_types=1);

namespace App\Support\Css;

/**
 * Drops rules whose class/id requirements the page cannot satisfy.
 *
 * Pure: no IO, no state. At-rules that carry no selectors of their own (@keyframes,
 * @font-face, @import, @property, ...) are always kept, because deciding whether an
 * animation or a face is reachable is not something a token check can do safely.
 */
final class PageCssPruner
{
    /** @param array<string,true> $universe */
    public static function prune(string $css, array $universe): string
    {
        $nodes = CssParser::parse($css);

        return CssParser::serialize(self::filter($nodes, $universe));
    }

    /**
     * @param list<CssNode>        $nodes
     * @param array<string,true>   $universe
     * @return list<CssNode>
     */
    private static function filter(array $nodes, array $universe): array
    {
        $kept = [];

        foreach ($nodes as $node) {
            if ($node->type === CssNode::TYPE_VERBATIM_AT) {
                $kept[] = $node;
                continue;
            }

            if ($node->type === CssNode::TYPE_BLOCK_AT) {
                $children = self::filter($node->children, $universe);
                // An empty @media block is dead weight, but one with survivors has to keep
                // its wrapper - that wrapper is exactly what the offset-slicing approach lost.
                if ($children !== []) {
                    $kept[] = CssNode::blockAt($node->name, $node->prelude, $children);
                }
                continue;
            }

            if (SelectorTokenExtractor::matches($node->prelude, $universe)) {
                $kept[] = $node;
            }
        }

        return $kept;
    }

    /**
     * Every class/id token that appears anywhere in the stylesheet's selectors.
     *
     * Used as a dictionary: intersecting it with a page's tokens gives a compact cache key
     * that changes only when something the stylesheet actually cares about changes.
     *
     * @return list<string>
     */
    public static function selectorTokens(string $css): array
    {
        $tokens = [];
        self::collectTokens(CssParser::parse($css), $tokens);
        ksort($tokens);

        return array_keys($tokens);
    }

    /**
     * @param list<CssNode>      $nodes
     * @param array<string,true> $tokens
     */
    private static function collectTokens(array $nodes, array &$tokens): void
    {
        foreach ($nodes as $node) {
            if ($node->type === CssNode::TYPE_BLOCK_AT) {
                self::collectTokens($node->children, $tokens);
                continue;
            }
            if ($node->type !== CssNode::TYPE_RULE) {
                continue;
            }
            foreach (SelectorTokenExtractor::requiredTokens($node->prelude) as $required) {
                foreach ($required as $token) {
                    $tokens[$token] = true;
                }
            }
        }
    }
}
