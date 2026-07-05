<?php

namespace App\Services;

use App\Repositories\BlockRepository;
use App\Repositories\BlogRepository;
use App\Repositories\ContactRepository;
use App\Repositories\FaqRepository;
use App\Repositories\MediaRepository;
use App\Repositories\PageRepository;
use App\Repositories\PortfolioRepository;
use App\Repositories\ServiceRepository;

class HomepageContentService
{
    private const DEFAULT_FORM_EMAIL = ContactRepository::DEFAULT_FORM_EMAIL;
    private const AUTOFILL_BLOCKS = [
        'sluzby_items' => ['label' => 'Služby', 'hint' => 'Automaticky doplnit z dat služby.'],
        'sluzby_grid' => ['label' => 'Služby grid', 'hint' => 'Automaticky doplnit z dat služby.'],
        'service_1' => ['label' => 'Služba 1', 'hint' => 'Automaticky doplnit první službu.'],
        'service_2' => ['label' => 'Služba 2', 'hint' => 'Automaticky doplnit druhou službu.'],
        'service_3' => ['label' => 'Služba 3', 'hint' => 'Automaticky doplnit třetí službu.'],
        'service_4' => ['label' => 'Služba 4', 'hint' => 'Automaticky doplnit čtvrtou službu.'],
        'portfolio-tiles' => ['label' => 'Naše práce', 'hint' => 'Automaticky doplnit z publikovaných prací.'],
        'blog_1' => ['label' => 'Blog 1', 'hint' => 'Automaticky doplnit nejnovější článek.'],
        'blog_2' => ['label' => 'Blog 2', 'hint' => 'Automaticky doplnit druhý nejnovější článek.'],
        'blog_3' => ['label' => 'Blog 3', 'hint' => 'Automaticky doplnit třetí nejnovější článek.'],
        'cenik_body' => ['label' => 'Ceník', 'hint' => 'Automaticky doplnit z aktuálního nastavení ceníku.'],
        'pricing_body' => ['label' => 'Pricing', 'hint' => 'Automaticky doplnit z aktuálního nastavení ceníku.'],
        'faq_1' => ['label' => 'FAQ 1', 'hint' => 'Automaticky doplnit z aktivních FAQ položek.'],
        'faq_2' => ['label' => 'FAQ 2', 'hint' => 'Automaticky doplnit z aktivních FAQ položek.'],
        'faq_3' => ['label' => 'FAQ 3', 'hint' => 'Automaticky doplnit z aktivních FAQ položek.'],
        'faq_4' => ['label' => 'FAQ 4', 'hint' => 'Automaticky doplnit z aktivních FAQ položek.'],
        'faq_5' => ['label' => 'FAQ 5', 'hint' => 'Automaticky doplnit z aktivních FAQ položek.'],
        'kontakt_info' => ['label' => 'Kontakt', 'hint' => 'Automaticky doplnit z kontaktních údajů.'],
        'contact_info' => ['label' => 'Kontakt', 'hint' => 'Automaticky doplnit z kontaktních údajů.'],
        'contact_visual' => ['label' => 'Kontaktni vizual', 'hint' => 'Obrazek pro kontaktni panel na homepage. Doplni demo obsah.'],
        'process_label' => ['label' => 'Proces - stitek', 'hint' => 'Textovy blok z demo obsahu.'],
        'process_title' => ['label' => 'Proces - nadpis', 'hint' => 'Textovy blok z demo obsahu.'],
        'process_intro' => ['label' => 'Proces - uvod', 'hint' => 'Textovy blok z demo obsahu.'],
        'process_body' => ['label' => 'Proces - kroky', 'hint' => 'HTML blok z demo obsahu.'],
        'reviews_demo' => ['label' => 'Recenze na homepage', 'hint' => 'Automaticky doplnit z aktivnich recenzi.'],
        'reviews_body' => ['label' => 'Stranka recenzi', 'hint' => 'Automaticky doplnit z aktivnich recenzi.'],
    ];

    private PageRepository $pages;
    private BlockRepository $blocks;
    private BlogRepository $blog;
    private PortfolioRepository $portfolio;
    private ServiceRepository $services;
    private FaqRepository $faq;
    private ContactRepository $contact;
    private MediaRepository $media;
    private ReviewContentService $reviewContent;

    public function __construct()
    {
        $this->pages = new PageRepository();
        $this->blocks = new BlockRepository();
        $this->blog = new BlogRepository();
        $this->portfolio = new PortfolioRepository();
        $this->services = new ServiceRepository();
        $this->faq = new FaqRepository();
        $this->contact = new ContactRepository();
        $this->media = new MediaRepository();
        $this->reviewContent = new ReviewContentService();
    }

    public function getAutofillDefinitions(): array
    {
        return self::AUTOFILL_BLOCKS;
    }

    public function buildServicesHtml(int $siteId, int $limit = 0): string
    {
        return $this->buildServicesGridHtml($siteId, $limit);
    }

    public function applyFallbacks(array $page, array $blocks): array
    {
        $path = trim((string)($page['url_path'] ?? ''), '/');
        $siteId = (int)($page['site_id'] ?? 0);

        if ($path === '') {
            $this->fillHomepageFallbacks($blocks, $siteId);
        }

        if ($path === '' || $path === 'sluzby') {
            $this->fillServiceFallbacks($blocks, $siteId, $path === '');
        }

        if ($path === '' || $path === 'cenik') {
            $this->fillPricingFallbacks($blocks);
        }

        if ($path === '' || $path === 'faq') {
            $this->fillFaqFallbacks($blocks, $siteId);
        }

        if ($path === '' || $path === 'hodnoceni') {
            $this->fillReviewFallbacks($blocks, $siteId, $path === '');
        }

        if ($path === '' || $path === 'kontakt') {
            $this->fillContactFallbacks($blocks, $siteId);
        }

        if ($path === '') {
            $this->fillBlogFallbacks($blocks, $siteId);
            $this->fillPortfolioFallbacks($blocks, $siteId);
        }

        return $blocks;
    }

    private function fillHomepageFallbacks(array &$blocks, int $siteId): void
    {
        $this->applyBlockFallback($blocks, 'sluzby_items', fn() => $this->buildServicesGridHtml($siteId, 3));
        $this->applyBlockFallback($blocks, 'sluzby_cta', fn() => $this->buildPageCtaHtml($siteId, 'sluzby'));
        $this->applyBlockFallback($blocks, 'portfolio-tiles', fn() => $this->buildPortfolioHtml($siteId));
        $this->applyBlockFallback($blocks, 'portfolio_cta', fn() => $this->buildPageCtaHtml($siteId, 'nase-prace'));
        $this->applyBlockFallback($blocks, 'portfolio_btns', fn() => $this->buildPageCtaHtml($siteId, 'nase-prace'));
        $this->applyBlockFallback($blocks, 'cenik_body', fn() => $this->buildPricingHtml());
        $this->applyBlockFallback($blocks, 'pricing_body', fn() => $this->buildPricingHtml());
        $this->applyBlockFallback($blocks, 'cenik_cta', fn() => $this->buildPageCtaHtml($siteId, 'cenik'));
        $this->applyBlockFallback($blocks, 'pricing_cta', fn() => $this->buildPageCtaHtml($siteId, 'cenik'));
        $this->applyBlockFallback($blocks, 'reviews_demo', fn() => $this->reviewContent->buildHomepageHtml($siteId, 10));
        $this->applyBlockFallback($blocks, 'reviews_cta', fn() => $this->buildPageCtaHtml($siteId, 'hodnoceni'));
        $this->applyBlockFallback($blocks, 'kontakt_info', fn() => $this->buildContactHtml($siteId));
        $this->applyBlockFallback($blocks, 'contact_info', fn() => $this->buildContactHtml($siteId));
        $this->applyBlockFallback($blocks, 'faq_cta', fn() => $this->buildPageCtaHtml($siteId, 'faq'));
        $this->applyBlockFallback($blocks, 'blog_cta', fn() => $this->buildPageCtaHtml($siteId, 'blog'));
        $this->applyBlockFallback($blocks, 'kontakt_cta', fn() => $this->buildPageCtaHtml($siteId, 'kontakt'));
    }

    private function fillPortfolioFallbacks(array &$blocks, int $siteId): void
    {
        $this->applyBlockFallback($blocks, 'portfolio-tiles', fn() => $this->buildPortfolioHtml($siteId));
    }

    private function fillBlogFallbacks(array &$blocks, int $siteId): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->applyBlockFallback($blocks, 'blog_' . $i, fn() => $this->buildBlogCardHtml($siteId, $i - 1));
        }
    }

    private function fillServiceFallbacks(array &$blocks, int $siteId, bool $isHomepage): void
    {
        $this->applyBlockFallback(
            $blocks,
            'sluzby_grid',
            fn() => $this->buildServicesGridHtml($siteId, $isHomepage ? 3 : 0)
        );

        $items = array_slice($this->services->allActive($siteId), 0, 4);
        for ($i = 1; $i <= 4; $i++) {
            $this->applyBlockFallback($blocks, 'service_' . $i, fn() => $this->buildServiceCardHtml($items[$i - 1] ?? null, $i, '', $siteId));
        }
    }

    private function fillPricingFallbacks(array &$blocks): void
    {
        $this->applyBlockFallback($blocks, 'cenik_body', fn() => $this->buildPricingHtml());
        $this->applyBlockFallback($blocks, 'pricing_body', fn() => $this->buildPricingHtml());
    }

    private function fillFaqFallbacks(array &$blocks, int $siteId): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->applyBlockFallback($blocks, 'faq_' . $i, fn() => $this->buildFaqItemHtml($siteId, $i - 1));
        }
    }

    private function fillReviewFallbacks(array &$blocks, int $siteId, bool $isHomepage): void
    {
        if ($isHomepage) {
            $this->applyBlockFallback($blocks, 'reviews_demo', fn() => $this->reviewContent->buildHomepageHtml($siteId, 6));
            return;
        }

        $this->applyBlockFallback($blocks, 'reviews_body', fn() => $this->reviewContent->buildListingHtml($siteId));
    }

    private function fillContactFallbacks(array &$blocks, int $siteId): void
    {
        $this->applyBlockFallback($blocks, 'kontakt_info', fn() => $this->buildContactHtml($siteId));
        $this->applyBlockFallback($blocks, 'contact_info', fn() => $this->buildContactHtml($siteId));
    }

    private function applyBlockFallback(array &$blocks, string $key, callable $builder): void
    {
        foreach ($blocks as &$block) {
            if (($block['block_key'] ?? '') !== $key) {
                continue;
            }

            if (!empty($block['autofill_disabled'])) {
                return;
            }

            $content = (string)($block['content'] ?? '');
            if ($this->isBlankHtml($content) || $this->looksLikePlaceholder($content)) {
                $block['content'] = $builder();
            }
            return;
        }
        unset($block);

        $content = $builder();
        if ($content === '') {
            return;
        }

        $blocks[] = [
            'block_key' => $key,
            'block_type' => 'html',
            'label' => $key,
            'content' => $content,
            'sort_order' => 0,
            'autofill_disabled' => 0,
        ];
    }

    private function isBlankHtml(?string $content): bool
    {
        $decoded = html_entity_decode((string)$content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = str_replace("\xc2\xa0", ' ', $decoded);
        return trim(strip_tags($decoded)) === '';
    }

    private function looksLikePlaceholder(string $content): bool
    {
        $text = mb_strtolower(trim(strip_tags(html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
        if ($text === '') {
            return true;
        }

        return str_contains($text, 'dopln') || str_contains($text, 'placeholder') || str_contains($text, 'vyplnte');
    }

    private function buildServicesGridHtml(int $siteId, int $limit = 0): string
    {
        $items = $this->services->allActive($siteId);
        if ($limit > 0) {
            $items = array_slice($items, 0, $limit);
        }

        if (empty($items)) {
            $legacyPage = $this->pages->findByUrlPath('sluzby');
            if ($legacyPage) {
                $legacyBlocks = $this->blocks->allByPage((int)$legacyPage['id']);
                foreach ($legacyBlocks as $block) {
                    if (($block['block_key'] ?? '') !== 'sluzby_grid') {
                        continue;
                    }
                    $content = (string)($block['content'] ?? '');
                    if ($content !== '' && !$this->isBlankHtml($content) && !$this->looksLikePlaceholder($content)) {
                        return $content;
                    }
                }
            }

            $homePage = $this->pages->findByUrlPath('');
            if ($homePage) {
                $homeBlocks = $this->blocks->allByPage((int)$homePage['id']);
                foreach ($homeBlocks as $block) {
                    if (($block['block_key'] ?? '') !== 'sluzby_items') {
                        continue;
                    }
                    $content = (string)($block['content'] ?? '');
                    if ($content !== '' && !$this->isBlankHtml($content) && !$this->looksLikePlaceholder($content)) {
                        return $content;
                    }
                }
            }

            return '';
        }

        $cards = '';
        foreach ($items as $idx => $item) {
            $cards .= $this->buildServiceCardHtml($item, $idx + 1, '', $siteId);
        }

        return $cards;
    }

    private function buildServiceCardHtml(?array $service, int $index = 0, string $detailId = '', int $siteId = 0): string
    {
        if (!$service) {
            return '';
        }

        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
        $title = (string)($service['title'] ?? '');
        $description = (string)($service['description'] ?? '');
        $imageUrl = $this->resolveServiceImageUrl($siteId, (string)($service['image_url'] ?? ''));
        $iconUrl = $this->publicMediaUrl((string)($service['icon_url'] ?? ''));
        $buttonText = trim((string)($service['button_text'] ?? '')) ?: 'Poptat službu';
        $buttonUrl = trim((string)($service['button_url'] ?? '')) ?: '/kontakt';
        if ($detailId !== '' && $this->isSameServicesUrl($buttonUrl)) {
            $buttonUrl = '#' . $detailId;
        }
        $num = $index > 0 ? str_pad((string)$index, 2, '0', STR_PAD_LEFT) : '';
        $icon = $iconUrl !== ''
            ? '<div class="hp-svc-card__icon"><img src="' . $h($iconUrl) . '" alt="" loading="lazy"></div>'
            : '';

        $media = $imageUrl !== ''
            ? '<div class="hp-svc-card__media"><img src="' . $h($imageUrl) . '" alt="' . $h($title) . '" loading="lazy"></div>'
            : '<div class="hp-svc-card__media"></div>';

        return '<article class="hp-svc-card' . ($icon !== '' ? ' has-icon' : '') . '">'
            . $media
            . '<div class="hp-svc-card__body">'
            . $icon
            . ($num !== '' ? '<div class="hp-svc-card__num">' . $h($num) . '</div>' : '')
            . '<h3 class="hp-svc-card__title">' . $h($title) . '</h3>'
            . '<div class="hp-svc-card__desc">' . nl2br($h($description)) . '</div>'
            . '<a href="' . $h($buttonUrl) . '" class="hp-svc-card__btn">' . $h($buttonText) . '</a>'
            . '</div></article>';
    }

    private function buildServiceDetailHtml(array $service, string $detailId, int $index, int $siteId = 0): string
    {
        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
        $title = trim((string)($service['title'] ?? ''));
        $description = trim((string)($service['description'] ?? ''));
        $imageUrl = $this->resolveServiceImageUrl($siteId, (string)($service['image_url'] ?? ''));
        $contactLabel = $title !== '' ? 'Poptat: ' . $title : 'Nezavazne poptat';
        $eyebrow = $index > 0 ? str_pad((string)$index, 2, '0', STR_PAD_LEFT) . ' / Detail sluzby' : 'Detail sluzby';

        $media = $imageUrl !== ''
            ? '<div class="hp-service-detail__media"><img src="' . $h($imageUrl) . '" alt="' . $h($title) . '" loading="lazy"></div>'
            : '<div class="hp-service-detail__media"></div>';

        return '<article class="hp-service-detail" id="' . $h($detailId) . '">'
            . $media
            . '<div class="hp-service-detail__body">'
            . '<div class="hp-service-detail__eyebrow">' . $h($eyebrow) . '</div>'
            . '<h3 class="hp-service-detail__title">' . $h($title) . '</h3>'
            . '<p class="hp-service-detail__desc">' . nl2br($h($description)) . '</p>'
            . '<a class="hp-service-detail__cta" href="/kontakt">' . $h($contactLabel) . '</a>'
            . '</div></article>';
    }

    private function isSameServicesUrl(string $url): bool
    {
        $normalized = trim($url);
        return $normalized === ''
            || $normalized === '#'
            || $normalized === '/sluzby'
            || $normalized === '/sluzby/';
    }

    private function buildPortfolioHtml(int $siteId): string
    {
        $entries = $this->portfolio->allPublished($siteId);
        if (empty($entries)) {
            return '';
        }

        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
        $tiles = '';

        foreach ($entries as $entry) {
            $photos = $this->portfolio->getMedia((int)$entry['id']);
            $thumb = !empty($entry['video_url']) ? PortfolioRepository::youtubeThumbnail((string)$entry['video_url']) : '';
            $hasVideo = !empty($entry['video_url']);

            if ($thumb === '' && !empty($photos)) {
                $thumbFile = $photos[0]['medium_path'] ?? $photos[0]['thumb_path'] ?? $photos[0]['storage_path'] ?? '';
                $thumb = $this->publicMediaUrl((string)$thumbFile);
            }

            $thumbHtml = $thumb !== ''
                ? '<img src="' . $h($thumb) . '" alt="' . $h($entry['title']) . '" loading="lazy">'
                : '<div class="pt-thumb-placeholder">&#9654;</div>';
            $playIcon = $hasVideo ? '<span class="pt-play">&#9654;</span>' : '';

            $extraHtml = '';
            $stripPhotos = $hasVideo ? array_slice($photos, 0, 3) : array_slice($photos, 1, 3);
            foreach ($stripPhotos as $photo) {
                $pfile = $photo['medium_path'] ?? $photo['thumb_path'] ?? $photo['storage_path'] ?? '';
                $extraHtml .= '<img src="' . $h($this->publicMediaUrl((string)$pfile)) . '" alt="' . $h($photo['alt_text'] ?? '') . '" loading="lazy">';
            }

            $entrySlug = $h($entry['slug'] ?? '');
            $entryHref = $entrySlug !== '' ? '/' . $entrySlug : '/nase-prace';
            $tiles .= <<<HTML
<a class="pt-tile" href="{$entryHref}">
  <div class="pt-caption">{$h($entry['title'])}</div>
  <div class="pt-thumb">{$thumbHtml}{$playIcon}</div>
  <div class="pt-photos">{$extraHtml}</div>
</a>
HTML;
        }

        return '<div class="pt-grid">' . $tiles . '</div>';
    }

    private function buildBlogCardHtml(int $siteId, int $index): string
    {
        $posts = array_slice($this->blog->allPublished($siteId), 0, 3);
        if (!isset($posts[$index])) {
            return '';
        }

        $post = $posts[$index];
        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
        $excerpt = mb_strimwidth(strip_tags((string)($post['excerpt'] ?? '')), 0, 120, '…');
        $imageHtml = '';
        $media = null;
        if (!empty($post['featured_image_id'])) {
            $media = $this->media->findByIdWithThumb((int)$post['featured_image_id']);
        }
        if (!$media) {
            $gallery = $this->blog->getGallery((int)$post['id']);
            if (!empty($gallery)) {
                $media = $gallery[0];
            }
        }
        if ($media) {
            $src = $this->publicMediaUrl((string)($media['thumb_path'] ?? $media['medium_path'] ?? $media['storage_path'] ?? ''));
            if ($src !== '') {
                $imageHtml = '<div class="hp-blog-thumb"><img src="' . $h($src) . '" alt="' . $h($post['title']) . '" loading="lazy"></div>';
            }
        }

        return '<article class="hp-blog-card">'
            . $imageHtml
            . '<div class="hp-blog-body">'
            . '<h3 class="hp-blog-title"><a href="/' . $h($post['slug']) . '" class="blog-title-link">'
            . $h($post['title']) . '</a></h3>'
            . '<p class="hp-blog-excerpt">' . $h($excerpt) . '</p>'
            . '<a class="hp-blog-link" href="/' . $h($post['slug']) . '">&#268;&#237;st d&#225;l &rarr;</a>'
            . '</div></article>';
    }

    private function buildPricingHtml(): string
    {
        $page = $this->pages->findByUrlPath('cenik');
        if (!$page) {
            return '';
        }

        $pageBlocks = $this->blocks->allByPage((int)$page['id']);
        $map = [];
        foreach ($pageBlocks as $block) {
            $map[$block['block_key']] = (string)($block['content'] ?? '');
        }

        $cards = '';
        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
        for ($i = 1; $i <= 3; $i++) {
            $featured = $i === 2 ? ' hp-cenik-card--featured' : '';
            $fullDesc = trim((string)($map['cenik_card_' . $i . '_desc'] ?? ''));
            // Homepage prehled = prvni odstavec (do prazdneho radku). Detailni
            // "Obsahuje" seznam za prazdnym radkem se na homepage nezobrazi —
            // ten patri jen na /cenik. Jednoradkove popisy zustanou beze zmeny.
            $summary = trim(preg_split('/\n\s*\n/', $fullDesc)[0] ?? '');
            $cards .= '<div class="hp-cenik-card' . $featured . '">'
                . '<div class="hp-cenik-card-body">'
                . '<h3 class="hp-cenik-card-title">' . $h($map['cenik_card_' . $i . '_title'] ?? '') . '</h3>'
                . ($summary !== '' ? '<div class="hp-cenik-card-desc">' . $h($summary) . '</div>' : '')
                . '</div>'
                . '</div>';
        }

        return '<div class="hp-cenik-grid">' . $cards . '</div>';
    }

    private function buildPageCtaHtml(int $siteId, string $urlPath): string
    {
        $page = $this->pages->findByUrlPath(trim($urlPath, '/'));
        if (!$page || (int)($page['site_id'] ?? 0) !== $siteId) {
            return '';
        }

        $label = trim((string)($page['title'] ?? ''));
        if ($label === '') {
            return '';
        }

        $url = '/' . trim((string)($page['url_path'] ?? $urlPath), '/');
        if ($url === '/') {
            return '';
        }

        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
        return '<div class="hp-section-cta"><a href="' . $h($url) . '" class="btn-secondary">' . $h($label) . '</a></div>';
    }

    private function buildFaqItemHtml(int $siteId, int $index): string
    {
        $items = array_slice($this->faq->allActive($siteId), 0, 5);
        if (!isset($items[$index])) {
            return '';
        }

        $item = $items[$index];
        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);

        return '<strong class="hp-faq-question">'
            . $h($item['question']) . '</strong>'
            . '<p class="hp-faq-answer">'
            . nl2br($h($item['answer'])) . '</p>';
    }

    public function buildContactHtml(int $siteId): string
    {
        $contact = $this->contact->findBySiteSafe($siteId);
        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
        $lines = [];

        foreach (['phone_1', 'phone_2', 'phone_3'] as $phoneKey) {
            if (!empty($contact[$phoneKey])) {
                $phone = (string)$contact[$phoneKey];
                $tel = preg_replace('/\s+/', '', $phone);
                $lines[] = '<div class="hp-contact-row"><span class="hp-contact-label">Telefon</span><a href="tel:' . $h((string)$tel) . '">' . $h($phone) . '</a></div>';
            }
        }

        if (!empty($contact['email'])) {
            $lines[] = '<div class="hp-contact-row"><span class="hp-contact-label">E-mail</span><a href="mailto:' . $h($contact['email']) . '">' . $h($contact['email']) . '</a></div>';
        }

        foreach (['address_1', 'address_2'] as $addressKey) {
            if (!empty($contact[$addressKey])) {
                $lines[] = '<div class="hp-contact-row"><span class="hp-contact-label">Adresa</span><span>' . nl2br($h($contact[$addressKey])) . '</span></div>';
            }
        }

        for ($i = 1; $i <= 5; $i++) {
            $type = trim((string)($contact['social_' . $i . '_type'] ?? ''));
            $url = trim((string)($contact['social_' . $i . '_url'] ?? ''));
            if ($type !== '' && $url !== '') {
                $lines[] = '<div class="hp-contact-row"><span class="hp-contact-label">Sítě</span><a href="' . $h($url) . '" target="_blank" rel="noopener">' . ucfirst($h($type)) . '</a></div>';
            }
        }

        if ($lines === []) {
            $lines[] = '<p class="hp-contact-empty">Doplňte kontaktní informace v administraci.</p>';
        }

        $map = '';
        if (!empty($contact['maps_url'])) {
            $mapsUrl = trim((string)$contact['maps_url']);
            if (preg_match('~^https?://~i', $mapsUrl)) {
                $map = '<iframe class="hp-contact-map-embed" src="' . $h($mapsUrl) . '" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';
            } else {
                $map = '<a href="' . $h($mapsUrl) . '" target="_blank" rel="noopener" class="hp-contact-map">Otevřít mapu</a>';
            }
        }

        $recipient = trim((string)($contact['form_email'] ?? ''));
        if ($recipient === '') {
            $recipient = trim((string)($contact['email'] ?? ''));
        }
        if ($recipient === '') {
            $recipient = self::DEFAULT_FORM_EMAIL;
        }

        $csrf = htmlspecialchars(\App\Core\Auth::csrfToken(), ENT_QUOTES);
        $form = '<form class="hp-contact-form" method="POST" action="/kontakt/odeslat">'
            . '<input type="hidden" name="_csrf" value="' . $csrf . '">'
            . '<input type="hidden" name="website" value="" autocomplete="off">'
            . '<label>Jméno</label><input type="text" name="name" required placeholder="Vaše jméno">'
            . '<label>E-mail / telefon</label><input type="text" name="contact_value" required placeholder="email nebo telefon">'
            . '<label>Zpráva</label><textarea name="message" required placeholder="Napište nám..."></textarea>'
            . '<button type="submit">Odeslat zprávu</button>'
            . '</form>';
        if ($recipient === '') {
            $form = '<p class="hp-contact-empty">Formulář je připravený, ale chybí cílový e-mail pro odeslání.</p>' . $form;
        }

        $contactCta = $this->buildPageCtaHtml($siteId, 'kontakt');

        return '<div class="hp-contact-grid">'
            . '<div class="hp-contact-box"><div class="hp-contact-lines">' . implode('', $lines) . '</div>' . $map . '<div class="hp-contact-cta">' . $contactCta . '</div></div>'
            . '<div class="hp-contact-box">' . $form . '</div>'
            . '</div>';
    }

    private function publicMediaUrl(string $storagePath): string
    {
        $normalized = str_replace('\\', '/', trim($storagePath));
        if ($normalized === '') {
            return '';
        }

        if (preg_match('~https?://[^/]+(/storage/media/.*)$~i', $normalized, $m)) {
            return '/media/' . ltrim(substr($m[1], strlen('/storage/media/')), '/');
        }

        if (str_starts_with($normalized, '/media/')) {
            return $normalized;
        }

        if (str_starts_with($normalized, '/storage/media/')) {
            return '/media/' . ltrim(substr($normalized, strlen('/storage/media/')), '/');
        }

        if (str_starts_with($normalized, 'storage/media/')) {
            return '/media/' . ltrim(substr($normalized, strlen('storage/media/')), '/');
        }

        return '/' . ltrim($normalized, '/');
    }

    public function resolveOptimizedMediaUrl(int $siteId, string $storagePath, int $maxBytes = 0): string
    {
        $normalized = str_replace('\\', '/', trim($storagePath));
        if ($normalized === '') {
            return '';
        }

        $publicUrl = $this->publicMediaUrl($normalized);
        if (
            !str_starts_with($normalized, '/media/') &&
            !str_starts_with($normalized, '/storage/media/') &&
            !str_starts_with($normalized, 'storage/media/') &&
            !preg_match('~https?://[^/]+/storage/media/~i', $normalized)
        ) {
            return $publicUrl;
        }

        if (preg_match('~https?://[^/]+(/storage/media/.*)$~i', $normalized, $match)) {
            $normalized = ltrim($match[1], '/');
        } elseif (str_starts_with($normalized, '/media/')) {
            $normalized = 'storage/media/' . ltrim(substr($normalized, strlen('/media/')), '/');
        } elseif (str_starts_with($normalized, '/storage/media/')) {
            $normalized = ltrim($normalized, '/');
        }

        if (!str_starts_with($normalized, 'storage/media/')) {
            return $publicUrl;
        }

        $media = $this->media->findByStoragePath($siteId, $normalized);
        if (!$media) {
            return $publicUrl;
        }

        $variants = [];
        foreach ($this->media->variantsByMediaId((int)$media['id']) as $variant) {
            $format = (string)($variant['format'] ?? '');
            $path = (string)($variant['path'] ?? '');
            if ($format !== '' && $path !== '') {
                $variants[$format] = $variant;
            }
        }

        if ($maxBytes > 0) {
            foreach (['medium', 'thumb'] as $format) {
                $filesize = (int)($variants[$format]['filesize'] ?? 0);
                if (!empty($variants[$format]['path']) && $filesize > 0 && $filesize <= $maxBytes) {
                    return $this->publicMediaUrl((string)$variants[$format]['path']);
                }
            }
        }

        foreach (['medium', 'webp', 'thumb'] as $format) {
            if (!empty($variants[$format]['path'])) {
                return $this->publicMediaUrl((string)$variants[$format]['path']);
            }
        }

        return $publicUrl;
    }

    private function resolveServiceImageUrl(int $siteId, string $storagePath): string
    {
        return $this->resolveOptimizedMediaUrl($siteId, $storagePath);
    }
}
