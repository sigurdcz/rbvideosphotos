<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Services\Render\PortfolioCaptionFormatter;

use App\Domain\PricingPackageCatalog;

/**
 * Pure data provider ("ViewModel") for the homepage section fragments - phase 5
 * of the templating refactor.
 *
 * Background: unlike the phase-4 special pages (faq/sluzby/kontakt/... - full
 * heredoc routes replaced by a single archetype), the homepage is a *generic
 * database page*: it goes through public/index.php::tryServeDatabasePage() /
 * PublishService::publishPage(), its section shells (hero, section labels,
 * ordering, section--alt/--dark classes, empty section-title placeholders) come
 * from the theme-specific templates.html_source and stay admin-editable, and
 * App\Services\HomepageContentService::applyFallbacks() only autofills the INNER
 * HTML of each empty data-block. That per-block inner HTML used to be built by
 * string concatenation inside HomepageContentService (and ReviewContentService
 * for the reviews teaser). This class replaces that concatenation with plain
 * view DATA, which the matching app/Views/partials/home-*.phtml partials render.
 *
 * Contract: every method returns scalars/arrays only - no HTML, no escaping (the
 * partials call ViewContext::e()). Media URLs are already root-relative
 * (/media/...) because the DB-heavy optimized-variant resolution stays in
 * HomepageContentService (it owns the MediaRepository); this class receives the
 * resolved URLs and only shapes presentation-derived fields (padded numbers,
 * has-icon flags, truncated excerpts, first-paragraph summaries, star strings).
 * It performs NO database access of its own.
 */
final class HomepageData
{
    /**
     * Packages shown in the homepage pricing teaser, in display order. The
     * highlighted card is whichever of them carries a badge on /cenik
     * (`cenik_card_<slot>_badge`), i.e. the middle "Kompletni balicek".
     */
    private const HOMEPAGE_PRICING_PACKAGES = [
        PricingPackageCatalog::WEDDING_PHOTO_FULL_DAY,
        PricingPackageCatalog::PHOTO_VIDEO_COMPLETE,
        PricingPackageCatalog::WEDDING_VIDEO_ONLY_FULL_DAY,
    ];

    /**
     * One homepage service card (`.hp-svc-card`). Mirrors the field derivation of
     * the former HomepageContentService::buildServiceCardHtml() so the rendered
     * fragment is byte-identical.
     *
     * @param array  $service    raw service_items row
     * @param int    $index      1-based position (0 = no number badge)
     * @param string $imageUrl   resolved root-relative image URL ('' = empty media box)
     * @param string $iconUrl    resolved root-relative icon URL ('' = no icon)
     * @param string $buttonText already-defaulted CTA label
     * @param string $buttonUrl  already-resolved CTA href
     * @return array{title:string,description:string,imageUrl:string,iconUrl:string,hasIcon:bool,num:string,buttonText:string,buttonUrl:string}
     */
    public static function serviceCard(array $service, int $index, string $imageUrl, string $iconUrl, string $buttonText, string $buttonUrl): array
    {
        return [
            'title' => (string)($service['title'] ?? ''),
            'description' => (string)($service['description'] ?? ''),
            'imageUrl' => $imageUrl,
            'iconUrl' => $iconUrl,
            'hasIcon' => $iconUrl !== '',
            'num' => $index > 0 ? str_pad((string)$index, 2, '0', STR_PAD_LEFT) : '',
            'buttonText' => $buttonText,
            'buttonUrl' => $buttonUrl,
        ];
    }

    /**
     * The three homepage pricing cards (`.hp-cenik-grid`). $map is the cenik
     * page's block_key => content map. Homepage intentionally omits prices and
     * uses at most four concise feature bullets from the stored package detail.
     * Legacy descriptions without a detail list fall back to sentence bullets.
     * The cards are bound to packages, not to /cenik slots, so reordering the
     * pricing page (migration 094) does not change the homepage selection.
     *
     * @param array<string,string> $map
     * @return list<array{id:string,title:string,category:string,badge:string,features:list<string>,featured:bool}>
     */
    public static function pricingCards(array $map): array
    {
        $cards = [];
        foreach (self::HOMEPAGE_PRICING_PACKAGES as $packageId) {
            $i = PricingPackageCatalog::slotForId($packageId);
            $fullDesc = trim((string)($map['cenik_card_' . $i . '_desc'] ?? ''));
            $parts = preg_split('/\n\s*\n/', $fullDesc) ?: [];
            $summary = trim((string)($parts[0] ?? ''));
            $details = trim(implode("\n\n", array_slice($parts, 1)));
            $features = [];

            foreach ((preg_split('/\R+/', $details) ?: []) as $feature) {
                $feature = trim((string)preg_replace('/^\s*[-*•]\s*/u', '', (string)$feature));
                if ($feature === '' || preg_match('/^obsahuje:?$/iu', $feature)) {
                    continue;
                }
                if (preg_match('/\b\d[\d\s.,]*(?:Kč|Kc|CZK)\b/iu', $feature)) {
                    continue;
                }
                $features[] = $feature;
            }
            if ($features === [] && $summary !== '') {
                $features = array_values(array_filter(array_map(
                    'trim',
                    preg_split('/(?<=[.!?])\s+/u', $summary) ?: []
                ), static fn (string $sentence): bool => !preg_match('/\b\d[\d\s.,]*(?:Kč|Kc|CZK)\b/iu', $sentence)));
            }

            $badge = trim((string)($map['cenik_card_' . $i . '_badge'] ?? ''));
            $cards[] = [
                'id' => $packageId,
                'title' => (string)($map['cenik_card_' . $i . '_title'] ?? ''),
                'category' => trim((string)($map['cenik_card_' . $i . '_category'] ?? '')),
                'badge' => $badge,
                'features' => array_slice($features, 0, 4),
                'featured' => $badge !== '',
            ];
        }

        return $cards;
    }

    /**
     * One homepage portfolio tile (`.pt-tile`). Media (thumbnail, strip photos)
     * is already resolved to root-relative URLs by the caller; this method only
     * assembles the href/title/flags, matching the former
     * HomepageContentService::buildPortfolioHtml() heredoc.
     *
     * @param string $thumbUrl '' → the tile renders the ▶ placeholder instead of an <img>
     * @param list<array{url:string,fullUrl:string,alt:string}> $photos preview and original gallery URLs
     * @param string $videoEmbedUrl '' for photo entries; the playable embed URL for video entries
     * @return array{href:string,title:string,date:string,description:string,thumbUrl:string,hasVideo:bool,photos:list<array{url:string,fullUrl:string,alt:string}>,videoEmbedUrl:string}
     */
    public static function portfolioTile(array $entry, string $thumbUrl, bool $hasVideo, array $photos, string $videoEmbedUrl = ''): array
    {
        $slug = (string)($entry['slug'] ?? '');
        $caption = PortfolioCaptionFormatter::fromHtml((string)($entry['description'] ?? ''), 220);

        return [
            'href' => $slug !== '' ? '/' . $slug : '/nase-prace',
            'title' => (string)($entry['title'] ?? ''),
            'date' => !empty($entry['published_at'])
                ? date('j. n. Y', (int)strtotime((string)$entry['published_at']))
                : '',
            'description' => $caption['description'],
            'author' => $caption['author'],
            'thumbUrl' => $thumbUrl,
            'hasVideo' => $hasVideo,
            'photos' => $photos,
            'videoEmbedUrl' => $videoEmbedUrl,
        ];
    }

    /**
     * One homepage blog card (`.hp-blog-card`). The excerpt is stripped of tags
     * and truncated to 120 display columns with an ellipsis, matching the former
     * HomepageContentService::buildBlogCardHtml(). $imageUrl is '' when the post
     * has no resolvable thumbnail (the card then renders without a thumb).
     *
     * @return array{slug:string,title:string,excerpt:string,imageUrl:string}
     */
    public static function blogCard(array $post, string $imageUrl): array
    {
        return [
            'slug' => (string)($post['slug'] ?? ''),
            'title' => (string)($post['title'] ?? ''),
            'excerpt' => mb_strimwidth(strip_tags((string)($post['excerpt'] ?? '')), 0, 120, '…'),
            'imageUrl' => $imageUrl,
        ];
    }

    /**
     * One homepage FAQ item (`.hp-faq-question` + `.hp-faq-answer`). Passthrough
     * of the raw question/answer (the partial escapes + nl2br()s the answer),
     * matching the former HomepageContentService::buildFaqItemHtml().
     *
     * @return array{question:string,answer:string}
     */
    public static function faqItem(array $row): array
    {
        return [
            'question' => (string)($row['question'] ?? ''),
            'answer' => (string)($row['answer'] ?? ''),
        ];
    }

    /**
     * The homepage reviews teaser cards (`.hp-reviews-grid` / `.hp-review-card`),
     * rendered by app/Views/partials/home-reviews.phtml: the star string is built
     * from the clamped 1-5 rating. The teaser's own inline <style> stays in
     * ReviewContentService::homepageCss() (byte-identical to before; its
     * relocation to a shared asset is a possible future cleanup).
     *
     * @return list<array{stars:string,review_text:string,author_name:string,context_label:string}>
     */
    public static function reviewCards(array $items): array
    {
        $cards = [];
        foreach ($items as $item) {
            $rating = max(1, min(5, (int)($item['rating'] ?? 5)));
            $cards[] = [
                'stars' => str_repeat('★', $rating) . str_repeat('☆', 5 - $rating),
                'review_text' => (string)($item['review_text'] ?? ''),
                'author_name' => (string)($item['author_name'] ?? ''),
                'context_label' => trim((string)($item['context_label'] ?? '')),
            ];
        }

        return $cards;
    }

    /**
     * A section CTA button (`.hp-section-cta`). Both inputs are already validated
     * / resolved by the caller (an invalid CTA returns '' before reaching here).
     *
     * @return array{url:string,label:string,secondaryUrl:string,secondaryLabel:string}
     */
    public static function sectionCta(
        string $url,
        string $label,
        string $secondaryUrl = '',
        string $secondaryLabel = ''
    ): array
    {
        return [
            'url' => $url,
            'label' => $label,
            'secondaryUrl' => $secondaryUrl,
            'secondaryLabel' => $secondaryLabel,
        ];
    }

    /**
     * Homepage contact panel (`.hp-contact-grid`): contact rows, optional map,
     * pre-rendered CTA, and the lead form. Mirrors the field derivation of the
     * former HomepageContentService::buildContactHtml() so the fragment is
     * byte-identical (the downstream normalizeContactFormAccessibility() pass
     * then upgrades the event-date field the same way as before).
     *
     * SECURITY: the CSRF token is a caller-supplied parameter - it is NEVER
     * generated inside this ViewModel or the view. The view only echoes it
     * (escaped). $cta is already-rendered, trusted HTML (from the section-cta
     * partial) and is emitted raw.
     *
     * @param array  $contact   contact_info row (may be empty)
     * @param string $csrfToken raw token generated by the caller
     * @param string $cta       already-rendered CTA HTML ('' = none)
     * @return array{lines:list<array<string,string>>,map:array{kind:string,url:string},cta:string,form:array{csrfToken:string,showMissingEmailNote:bool}}
     */
    public static function contact(array $contact, string $csrfToken, string $cta, string $imageUrl = ''): array
    {
        $lines = [];
        foreach (['phone_1', 'phone_2', 'phone_3'] as $phoneKey) {
            if (!empty($contact[$phoneKey])) {
                $phone = (string)$contact[$phoneKey];
                $lines[] = [
                    'kind' => 'phone',
                    'tel' => (string)preg_replace('/\s+/', '', $phone),
                    'phone' => $phone,
                ];
            }
        }
        if (!empty($contact['email'])) {
            $lines[] = ['kind' => 'email', 'email' => (string)$contact['email']];
        }
        foreach (['address_1', 'address_2'] as $addressKey) {
            if (!empty($contact[$addressKey])) {
                $lines[] = ['kind' => 'address', 'address' => (string)$contact[$addressKey]];
            }
        }
        for ($i = 1; $i <= 5; $i++) {
            $type = trim((string)($contact['social_' . $i . '_type'] ?? ''));
            $url = trim((string)($contact['social_' . $i . '_url'] ?? ''));
            if ($type !== '' && $url !== '') {
                $lines[] = ['kind' => 'social', 'url' => $url, 'type' => $type];
            }
        }

        $map = ['kind' => 'none', 'url' => ''];
        if (!empty($contact['maps_url'])) {
            $mapsUrl = trim((string)$contact['maps_url']);
            $map = [
                'kind' => preg_match('~^https?://~i', $mapsUrl) ? 'iframe' : 'link',
                'url' => $mapsUrl,
            ];
        }

        $recipient = trim((string)($contact['form_email'] ?? ''));
        if ($recipient === '') {
            $recipient = trim((string)($contact['email'] ?? ''));
        }
        if ($recipient === '') {
            $recipient = \App\Repositories\ContactRepository::DEFAULT_FORM_EMAIL;
        }

        return [
            'imageUrl' => $imageUrl,
            'lines' => $lines,
            'map' => $map,
            'cta' => $cta,
            'form' => [
                'csrfToken' => $csrfToken,
                'showMissingEmailNote' => $recipient === '',
            ],
        ];
    }
}
