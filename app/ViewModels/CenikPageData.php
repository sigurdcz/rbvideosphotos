<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Domain\PricingPackageCatalog;
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
 * page shell - only the package-cards + CTA section (previously a live-only
 * post-render regex splice) is replaced by this ViewModel + the archetype
 * view. See App\Services\Render\CenikPageRenderer for the splice itself,
 * which both PageComposer::composeLive and composePublish now call.
 */
final class CenikPageData
{
    /**
     * @return array{
     *   packages: list<array{id:string,slot:int,title:string,price:string,unit:string,category:string,summary:string,features:list<string>,featured:bool,badge:string,ctaLabel:string,ctaUrl:string}>,
     *   headingPackages: string, introPackages: string,
     *   otherHeading: string, otherText: string,
     *   transportHeading: string, transportText: string,
     *   faqItems: list<array{slot:int,question:string,answer:string}>,
     *   ctaHeading: string, ctaText: string, ctaTextKey: string, ctaWrap: string,
     *   kickerPackages: string, kickerTransport: string, kickerFaq: string,
     *   headingFaq: string, kickerCta: string, ctaFallbackLabel: string
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
        for ($i = 1; $i <= 8; $i++) {
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
                'id' => PricingPackageCatalog::idForSlot($i),
                // The card's block-key slot (cenik_card_<slot>_*). The view needs
                // it to build the inline-editor key for each individual field.
                'slot' => $i,
                'title' => $title,
                'price' => $price,
                'unit' => HomepageSectionRenderer::blockContent($blocks, 'cenik_card_' . $i . '_unit'),
                'category' => HomepageSectionRenderer::blockContent($blocks, 'cenik_card_' . $i . '_category'),
                'summary' => $summary,
                'features' => $features,
                'featured' => HomepageSectionRenderer::blockContent($blocks, 'cenik_card_' . $i . '_badge') !== '',
                'badge' => HomepageSectionRenderer::blockContent($blocks, 'cenik_card_' . $i . '_badge'),
                'ctaLabel' => HomepageSectionRenderer::blockContent($blocks, 'cenik_card_' . $i . '_cta_label') ?: 'Mám zájem',
                'ctaUrl' => HomepageSectionRenderer::blockContent($blocks, 'cenik_card_' . $i . '_cta_url') ?: '/kontakt',
            ];
        }

        $faqItems = [];
        for ($i = 1; $i <= 5; $i++) {
            $question = HomepageSectionRenderer::blockContent($blocks, 'cenik_faq_' . $i . '_question');
            $answer = HomepageSectionRenderer::blockContent($blocks, 'cenik_faq_' . $i . '_answer');
            if ($question !== '' && $answer !== '') {
                // Same reason as the package slot: cenik_faq_<slot>_question/_answer.
                $faqItems[] = ['slot' => $i, 'question' => $question, 'answer' => $answer];
            }
        }

        $ctaSub = HomepageSectionRenderer::blockContent($blocks, 'cta_sub');

        return [
            'packages' => $packages,
            'headingPackages' => HomepageSectionRenderer::blockContent($blocks, 'pricing_weddings_heading') ?: 'Svatebni balicky',
            'introPackages' => HomepageSectionRenderer::blockContent($blocks, 'pricing_weddings_sub'),
            'otherHeading' => HomepageSectionRenderer::blockContent($blocks, 'pricing_other_heading'),
            'otherText' => HomepageSectionRenderer::blockContent($blocks, 'pricing_other_sub'),
            'transportHeading' => HomepageSectionRenderer::blockContent($blocks, 'pricing_transport_heading'),
            'transportText' => HomepageSectionRenderer::blockContent($blocks, 'pricing_transport_text'),
            'faqItems' => $faqItems,
            'ctaHeading' => HomepageSectionRenderer::blockContent($blocks, 'cta_heading') ?: 'Domluvme termin',
            'ctaText' => $ctaSub ?: HomepageSectionRenderer::blockContent($blocks, 'cta_text'),
            // Which of the two blocks the paragraph above actually came from, so
            // an inline edit writes back into the block that is being displayed.
            'ctaTextKey' => $ctaSub !== '' ? 'cta_sub' : 'cta_text',
            'ctaWrap' => HomepageSectionRenderer::blockContent($blocks, 'cta_wrap'),
            // Section labels that used to be hardcoded in the view.
            'kickerPackages' => HomepageSectionRenderer::blockContent($blocks, 'cenik_packages_kicker') ?: 'Ceník služeb',
            'kickerTransport' => HomepageSectionRenderer::blockContent($blocks, 'cenik_transport_kicker') ?: 'Doprava',
            'kickerFaq' => HomepageSectionRenderer::blockContent($blocks, 'cenik_faq_kicker') ?: 'Časté otázky',
            'headingFaq' => HomepageSectionRenderer::blockContent($blocks, 'cenik_faq_heading') ?: 'Nejčastější dotazy k cenám',
            'kickerCta' => HomepageSectionRenderer::blockContent($blocks, 'cenik_cta_kicker') ?: 'Rezervace termínu',
            'ctaFallbackLabel' => HomepageSectionRenderer::blockContent($blocks, 'cenik_cta_button') ?: 'Nezávazná poptávka',
        ];
    }
}
