<?php

declare(strict_types=1);

namespace App\Services\Render;

use App\Services\ThemeTemplateRegistry;

/**
 * Golden Bash builds part of its homepage hero in theme.js: a second CTA, a benefits
 * strip and the decorative wave SVGs. Because that runs after the document paints, the
 * hero grew by ~160px right after first paint and produced a 0.71 layout shift - the
 * single worst Core Web Vital on the homepage.
 *
 * Rendering the same markup server-side removes the shift entirely. theme.js already
 * guards every insertion with an existence check, so it simply finds the nodes in place
 * and does nothing; no duplication and no behavioural change.
 */
final class HeroEnhancementRenderer
{
    private const WAVE_SVG = '<svg class="gb-hero-wave-defs" width="0" height="0" aria-hidden="true" focusable="false"><defs><clipPath id="gb-hero-wave-clip" clipPathUnits="objectBoundingBox"><path d="M .27 0 H 1 V 1 H .55 Z"/></clipPath></defs></svg>';

    private const WORK_LINK = '<a class="btn-secondary gb-hero-work gb-button" href="/nase-prace">Ukázka práce</a>';

    private const BENEFITS = '<div class="gb-hero-benefits"><span>Diskrétní přístup</span><span>Moderní technika</span><span>Vyprávíme emoce</span><span>Foto i video v jednom týmu</span></div>';

    public static function inject(string $html, ?string $themeKey): string
    {
        if (!ThemeTemplateRegistry::isGoldenBashFamily($themeKey)
            || !str_contains($html, 'class="hp-hero"')) {
            return $html;
        }

        if (!str_contains($html, 'gb-hero-wave-clip')) {
            $html = preg_replace(
                '~(<section\b[^>]*\bclass="hp-hero"[^>]*>)~i',
                '$1' . self::WAVE_SVG,
                $html,
                1
            ) ?? $html;
        }

        if (!str_contains($html, 'gb-hero-work')) {
            $html = preg_replace(
                '~(<div\b[^>]*\bclass="hero-cta-wrap"[^>]*>)(.*?)(</div>)~is',
                '$1$2' . self::WORK_LINK . '$3',
                $html,
                1
            ) ?? $html;
        }

        if (!str_contains($html, 'gb-hero-benefits')) {
            $html = preg_replace(
                '~(<div\b[^>]*\bclass="hero-cta-wrap"[^>]*>.*?</div>)~is',
                '$1' . self::BENEFITS,
                $html,
                1
            ) ?? $html;
        }

        return self::markHeroButtons($html);
    }

    /**
     * theme.js adds the gb-button class to every button once it runs, which changes their
     * padding and therefore the hero height. Applying it server-side for the hero keeps
     * the first paint and the scripted state identical.
     */
    private static function markHeroButtons(string $html): string
    {
        return preg_replace_callback(
            '~(<section[^>]*class="hp-hero"[^>]*>)(.*?)(</section>)~is',
            static function (array $m): string {
                $hero = preg_replace_callback(
                    '~class="([^"]*)"~i',
                    static function (array $c): string {
                        $classes = $c[1];
                        if (!preg_match('~(?:^|\s)(?:btn|btn-primary|btn-secondary|btn-ghost)(?:\s|$)~', $classes)) {
                            return $c[0];
                        }
                        if (preg_match('~(?:^|\s)gb-button(?:\s|$)~', $classes)) {
                            return $c[0];
                        }
                        return 'class="' . $classes . ' gb-button"';
                    },
                    $m[2]
                ) ?? $m[2];

                return $m[1] . $hero . $m[3];
            },
            $html,
            1
        ) ?? $html;
    }
}
