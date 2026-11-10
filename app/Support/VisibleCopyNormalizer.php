<?php
declare(strict_types=1);

namespace App\Support;

final class VisibleCopyNormalizer
{
    /** Normalize plain user-facing copy, including leaked Markdown emphasis markers. */
    public static function plain(string $value): string
    {
        return str_replace(
            ['—', '–', '**', '__', '~~', '```'],
            ['-', '-', '', '', '', ''],
            $value
        );
    }

    /** Keep valid Markdown syntax for the parser, but remove AI-style long dashes. */
    public static function markdown(string $value): string
    {
        return str_replace(['—', '–'], '-', $value);
    }
}
