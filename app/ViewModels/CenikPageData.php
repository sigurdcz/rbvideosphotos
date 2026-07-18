<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Repositories\BlockRepository;
use App\Repositories\PageRepository;
use App\Services\Render\HomepageSectionRenderer;

/**
 * Pure data provider for the `cenik` archetype (app/Views/archetypes/cenik.phtml).
 *
 * Phase 4 of the templating refactor: replaces buildCenikPackageCardsHtml() /
 * injectCenikPageLayout() (global functions in public/index.php that built the
 * package-card HTML directly). Unlike faq/sluzby, the /cenik page keeps going
 * through the generic content_blocks/html_source pipeline for its hero and
 * page shell — only the package-cards + CTA section (previously a live-only
 * post-render regex splice) is replaced by this ViewModel + the archetype
 * view. See App\Services\Render\CenikPageRenderer for the splice itself,
 * which both PageComposer::composeLive and composePublish now call.
 */
final class CenikPageData
{
    /**
     * @return array{
     *   packages: list<array{title:string,price:string,summary:string,features:list<string>,featured:bool}>,
     *   headingPackages: string, introPackages: string,
     *   otherHeading: string, otherText: string,
     *   ctaHeading: string, ctaText: string, ctaWrap: string
     * }
     */
    public static function forSite(int $siteId): array
    {
        $page = (new PageRepository())->findByUrlPath('cenik');
        $blocks = $page ? (new BlockRepository())->allByPage((int)$page['id']) : [];

        return self::fromBlocks($blocks);
    }

    /**
     * Same shape as forSite(), but built from an already-loaded blocks list.
     * PageComposer already has the current page's blocks in scope when it
     * renders /cenik, so it uses this to avoid a redundant DB round-trip.
     *
     * @param array $blocks list of block rows for the cenik page
     */
    public static function fromBlocks(array $blocks): array
    {
        $packages = [];
        for ($i = 1; $i <= 3; $i++) {
            $title = HomepageSectionRenderer::blockContent($blocks, 'cenik_card_' . $i . '_title');
            $price = HomepageSectionRenderer::blockContent($blocks, 'cenik_card_' . $i . '_price');
            $desc = HomepageSectionRenderer::blockContent($blocks, 'cenik_card_' . $i . '_desc');

            if ($title === '' && $price === '' && $desc === '') {
                continue;
            }

            $parts = preg_split('/\n\s*\n/', trim($desc)) ?: [];
            $summary = trim((string)($parts[0] ?? ''));
            $details = trim(implode("\n\n", array_slice($parts, 1)));
            $features = [];

            if ($details !== '') {
                foreach ((preg_split('/\R+/', $details) ?: []) as $feature) {
                    $feature = trim((string)preg_replace('/^\s*[-*•]\s*/u', '', (string)$feature));
                    if ($feature === '' || preg_match('/^obsahuje:?$/iu', $feature)) {
                        continue;
                    }
                    $features[] = $feature;
                }
            }

            $packages[] = [
                'title' => $title,
                'price' => $price,
                'summary' => $summary,
                'features' => $features,
                'featured' => $i === 2,
            ];
        }

        return [
            'packages' => $packages,
            'headingPackages' => HomepageSectionRenderer::blockContent($blocks, 'pricing_weddings_heading') ?: 'Svatebni balicky',
            'introPackages' => HomepageSectionRenderer::blockContent($blocks, 'pricing_weddings_sub'),
            'otherHeading' => HomepageSectionRenderer::blockContent($blocks, 'pricing_other_heading'),
            'otherText' => HomepageSectionRenderer::blockContent($blocks, 'pricing_other_sub'),
            'ctaHeading' => HomepageSectionRenderer::blockContent($blocks, 'cta_heading') ?: 'Domluvme termin',
            'ctaText' => HomepageSectionRenderer::blockContent($blocks, 'cta_text'),
            'ctaWrap' => HomepageSectionRenderer::blockContent($blocks, 'cta_wrap'),
        ];
    }
}
