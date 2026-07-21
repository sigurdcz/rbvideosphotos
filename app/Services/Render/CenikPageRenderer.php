<?php

declare(strict_types=1);

namespace App\Services\Render;

use App\View\ViewFactory;
use App\ViewModels\CenikPageData;

/**
 * Cenik "packages + CTA" section splice — phase 4 of the templating refactor.
 *
 * Replaces the pair of live-only global functions in public/index.php
 * (injectCenikPageLayout() / buildCenikPackageCardsHtml()). Those functions
 * were only reachable from the live request (public/index.php's global
 * scope), so App\Services\PublishService — which boots from a different
 * entry point — could never call them; publish instead fell back to whatever
 * static HTML happened to be baked into the page's content_blocks (a stale,
 * non-accented snapshot, see UKOL.md phase 4 notes). Moving the logic to
 * CenikPageData + archetypes/cenik.phtml, both autoloadable from anywhere,
 * lets PageComposer::composePublish call the exact same splice as
 * composeLive, so live and publish share one source of truth for /cenik.
 *
 * The splice mechanism itself (regex-replacing the page-hero…footer span) is
 * unchanged: /cenik is a generic content_blocks/html_source page, not a
 * dedicated tryServe*Page() route like faq/sluzby, so its hero/breadcrumb/SEO
 * still come from the shared page pipeline — only the package cards + CTA
 * (previously assembled by string concatenation) are now ViewModel + PHTML.
 */
final class CenikPageRenderer
{
    public static function inject(string $html, array $blocks, string $pageKey, ?string $themeKey = null): string
    {
        if ($pageKey !== 'cenik') {
            return $html;
        }

        $data = CenikPageData::fromBlocks($blocks);
        if ($data['packages'] === []) {
            return $html;
        }

        $content = ViewFactory::forTheme($themeKey)->render('archetypes/cenik', $data);

        $updated = preg_replace(
            '~(<section\b[^>]*\bclass=(["\'])[^"\']*\bpage-hero\b[^"\']*\2[^>]*>.*?</section>)\s*.*?(<footer\b)~is',
            '$1' . $content . '$3',
            $html,
            1,
            $count
        );

        if ($count > 0 && is_string($updated)) {
            return $updated;
        }

        return preg_replace('~(<footer\b)~i', $content . '$1', $html, 1) ?? $html;
    }
}
