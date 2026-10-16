<?php

declare(strict_types=1);

namespace App\Support\Css;

/**
 * One node of a parsed stylesheet.
 *
 * Declarations are never parsed - a rule keeps its body as the original string. Only the
 * structure that matters for subsetting (which rule sits inside which at-rule) is modelled.
 */
final class CssNode
{
    public const TYPE_RULE = 'rule';
    public const TYPE_BLOCK_AT = 'block-at';
    public const TYPE_VERBATIM_AT = 'verbatim-at';

    /** @param list<CssNode> $children */
    public function __construct(
        public readonly string $type,
        public readonly string $prelude,
        public readonly string $body = '',
        public array $children = [],
        public readonly string $name = '',
    ) {
    }

    public static function rule(string $selector, string $body): self
    {
        return new self(self::TYPE_RULE, $selector, $body);
    }

    /** @param list<CssNode> $children */
    public static function blockAt(string $name, string $prelude, array $children): self
    {
        return new self(self::TYPE_BLOCK_AT, $prelude, '', $children, $name);
    }

    public static function verbatimAt(string $name, string $raw): self
    {
        return new self(self::TYPE_VERBATIM_AT, $raw, '', [], $name);
    }
}
