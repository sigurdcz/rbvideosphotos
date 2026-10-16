<?php

declare(strict_types=1);

namespace App\Services\Render;

final class PortfolioCaptionFormatter
{
    /** @return array{description:string,author:string} */
    public static function fromHtml(string $html, int $descriptionLimit): array
    {
        $author = '';
        if (preg_match('~<strong\b[^>]*>\s*Autor:\s*(.*?)</strong>~isu', $html, $match) === 1) {
            $author = self::plainText((string)$match[1]);
            $html = (string)preg_replace(
                '~<p\b[^>]*>\s*<strong\b[^>]*>\s*Autor:\s*.*?</strong>\s*</p>~isu',
                '',
                $html,
                1
            );
        }

        $withBreaks = (string)preg_replace('~<br\s*/?>|</(?:p|div|li)>~iu', "\n", $html);
        $description = self::plainText($withBreaks, true);
        if ($descriptionLimit > 0) {
            $description = mb_strimwidth($description, 0, $descriptionLimit, '…');
        }

        return ['description' => $description, 'author' => $author];
    }

    private static function plainText(string $value, bool $preserveLines = false): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace("\u{00A0}", ' ', $value);
        if (!$preserveLines) {
            return trim((string)preg_replace('/\s+/u', ' ', $value));
        }

        $lines = preg_split('/\R+/u', $value) ?: [];
        $lines = array_values(array_filter(array_map(
            static fn(string $line): string => trim((string)preg_replace('/[\t ]+/u', ' ', $line)),
            $lines
        ), static fn(string $line): bool => $line !== ''));

        return implode("\n", $lines);
    }
}
