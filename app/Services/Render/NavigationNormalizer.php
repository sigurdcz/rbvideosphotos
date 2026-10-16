<?php

declare(strict_types=1);

namespace App\Services\Render;

final class NavigationNormalizer
{
    private const REVIEWS_LINK = '<a href="/hodnoceni">Reference</a>';
    private const ABOUT_LINK = '<a href="/o-nas">O nás</a>';

    public static function ensureReviewsLink(string $nav): string
    {
        if (trim($nav) === '') {
            return $nav;
        }

        foreach ([
            ['/o-nas', self::ABOUT_LINK],
            ['/hodnoceni', self::REVIEWS_LINK],
        ] as [$path, $link]) {
            if (preg_match('~href=(["\'])' . preg_quote($path, '~') . '(?:[#?][^"\']*)?\1~i', $nav)) {
                continue;
            }
            $withLink = preg_replace(
                '~(<a\b[^>]*class=(["\'])[^"\']*\bnav-cta\b[^"\']*\2)~i',
                $link . "\n      $1",
                $nav,
                1,
                $count
            );
            if (!is_string($withLink) || $count !== 1) {
                $withLink = preg_replace('~</nav>~i', $link . "\n</nav>", $nav, 1, $count);
            }
            if (is_string($withLink) && $count === 1) {
                $nav = $withLink;
            }
        }

        return $nav;
    }
}
