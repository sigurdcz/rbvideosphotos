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
use App\Repositories\SiteRepository;
use App\View\ViewFactory;
use App\ViewModels\HomepageData;

class HomepageContentService
{
    private const AUTOFILL_BLOCKS = [
        'sluzby_items' => ['label' => 'Služby', 'hint' => 'Automaticky doplnit z dat služby.'],
        'sluzby_grid' => ['label' => 'Služby grid', 'hint' => 'Automaticky doplnit z dat služby.'],
        'service_1' => ['label' => 'Služba 1', 'hint' => 'Automaticky doplnit první službu.'],
        'service_2' => ['label' => 'Služba 2', 'hint' => 'Automaticky doplnit druhou službu.'],
        'service_3' => ['label' => 'Služba 3', 'hint' => 'Automaticky doplnit třetí službu.'],
        'service_4' => ['label' => 'Služba 4', 'hint' => 'Automaticky doplnit čtvrtou službu.'],
        'service_5' => ['label' => 'Služba 5', 'hint' => 'Automaticky doplnit pátou službu.'],
        'service_6' => ['label' => 'Služba 6', 'hint' => 'Automaticky doplnit šestou službu.'],
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

    /**
     * Theme key for the site currently being rendered, resolved once per
     * applyFallbacks() call and reused by renderHomePartial() below so every
     * homepage section partial can be overridden per-theme via
     * Templates/<key>/views/partials/home-*.phtml (same ViewResolver cascade
     * the dedicated archetypes use). Null when no theme resolves — the cascade
     * then falls back to the core partial, unchanged from before.
     */
    private ?string $currentThemeKey = null;

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

    private function resolveThemeKeyForSite(int $siteId): ?string
    {
        $site = (new SiteRepository())->findById($siteId);
        if (!$site) {
            return null;
        }

        $theme = (new ThemeTemplateRegistry())->resolveForSite($site);
        $key = trim((string)($theme['key'] ?? ''));

        return $key !== '' ? $key : null;
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
        $this->currentThemeKey = $this->resolveThemeKeyForSite($siteId);

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
        $this->applyBlockFallback($blocks, 'sluzby_items', fn() => $this->buildServicesGridHtml($siteId, 6));
        $this->applyBlockFallback($blocks, 'sluzby_cta', fn() => $this->buildPageCtaHtml($siteId, 'sluzby'));
        $this->applyBlockFallback($blocks, 'portfolio-tiles', fn() => $this->buildPortfolioHtml($siteId));
        $this->applyBlockFallback($blocks, 'portfolio_cta', fn() => $this->buildPageCtaHtml($siteId, 'nase-prace'));
        $this->applyBlockFallback($blocks, 'portfolio_btns', fn() => $this->buildPageCtaHtml($siteId, 'nase-prace'));
        $this->applyBlockFallback($blocks, 'cenik_body', fn() => $this->buildPricingHtml());
        $this->applyBlockFallback($blocks, 'pricing_body', fn() => $this->buildPricingHtml());
        $this->applyBlockFallback($blocks, 'cenik_cta', fn() => $this->buildPageCtaHtml($siteId, 'cenik'));
        $this->applyBlockFallback($blocks, 'pricing_cta', fn() => $this->buildPageCtaHtml($siteId, 'cenik'));
        $this->applyBlockFallback($blocks, 'reviews_demo', fn() => $this->reviewContent->buildHomepageHtml($siteId, 10, $this->currentThemeKey));
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
            fn() => $this->buildServicesGridHtml($siteId, $isHomepage ? 6 : 0)
        );

        $items = array_slice($this->services->allActive($siteId), 0, 6);
        for ($i = 1; $i <= 6; $i++) {
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
            $this->applyBlockFallback($blocks, 'reviews_demo', fn() => $this->reviewContent->buildHomepageHtml($siteId, 6, $this->currentThemeKey));
            return;
        }

        $this->applyBlockFallback($blocks, 'reviews_body', fn() => $this->reviewContent->buildListingHtml($siteId, $this->currentThemeKey));
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

        $imageUrl = $this->resolveServiceImageUrl($siteId, (string)($service['image_url'] ?? ''));
        $iconUrl = $this->publicMediaUrl((string)($service['icon_url'] ?? ''));
        $buttonText = trim((string)($service['button_text'] ?? '')) ?: 'Poptat službu';
        $buttonUrl = trim((string)($service['button_url'] ?? '')) ?: '/kontakt';
        if ($detailId !== '' && $this->isSameServicesUrl($buttonUrl)) {
            $buttonUrl = '#' . $detailId;
        }

        return $this->renderHomePartial('home-service-card', [
            'card' => HomepageData::serviceCard($service, $index, $imageUrl, $iconUrl, $buttonText, $buttonUrl),
        ]);
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

        $tiles = [];
        foreach ($entries as $entry) {
            $photos = $this->portfolio->getMedia((int)$entry['id']);
            $hasVideo = !empty($entry['video_url']);
            $thumb = $hasVideo ? PortfolioRepository::youtubeThumbnail((string)$entry['video_url']) : '';

            if ($thumb === '' && !empty($photos)) {
                $thumbFile = $photos[0]['medium_path'] ?? $photos[0]['thumb_path'] ?? $photos[0]['storage_path'] ?? '';
                $thumb = $this->publicMediaUrl((string)$thumbFile);
            }

            $photoData = [];
            $stripPhotos = $hasVideo ? array_slice($photos, 0, 3) : array_slice($photos, 1, 3);
            foreach ($stripPhotos as $photo) {
                $pfile = $photo['medium_path'] ?? $photo['thumb_path'] ?? $photo['storage_path'] ?? '';
                $photoData[] = [
                    'url' => $this->publicMediaUrl((string)$pfile),
                    'alt' => (string)($photo['alt_text'] ?? ''),
                ];
            }

            $tiles[] = HomepageData::portfolioTile($entry, $thumb, $hasVideo, $photoData);
        }

        return $this->renderHomePartial('home-portfolio', ['tiles' => $tiles]);
    }

    private function buildBlogCardHtml(int $siteId, int $index): string
    {
        $posts = array_slice($this->blog->allPublished($siteId), 0, 3);
        if (!isset($posts[$index])) {
            return '';
        }

        $post = $posts[$index];
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
        $imageUrl = $media
            ? $this->publicMediaUrl((string)($media['thumb_path'] ?? $media['medium_path'] ?? $media['storage_path'] ?? ''))
            : '';

        return $this->renderHomePartial('home-blog-card', ['card' => HomepageData::blogCard($post, $imageUrl)]);
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

        return $this->renderHomePartial('home-pricing', ['cards' => HomepageData::pricingCards($map)]);
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

        return $this->renderHomePartial('home-section-cta', HomepageData::sectionCta($url, $label));
    }

    private function buildFaqItemHtml(int $siteId, int $index): string
    {
        $items = array_slice($this->faq->allActive($siteId), 0, 5);
        if (!isset($items[$index])) {
            return '';
        }

        return $this->renderHomePartial('home-faq-item', ['item' => HomepageData::faqItem($items[$index])]);
    }

    /**
     * Render a homepage section fragment through the core PHTML partials
     * (app/Views/partials/home-*.phtml). Phase 5 of the templating refactor:
     * the autofill builders below prepare view DATA (via App\ViewModels\HomepageData)
     * and delegate markup to these partials instead of concatenating HTML here.
     * Core-only resolution (no theme override) matches the previous behaviour —
     * the fragment used to be produced by this service, not by a theme.
     */
    private function renderHomePartial(string $name, array $data): string
    {
        return ViewFactory::forTheme($this->currentThemeKey)->render('partials/' . $name, $data);
    }

    public function buildContactHtml(int $siteId): string
    {
        // CSRF token is generated exactly once here (never inside the ViewModel or
        // the view) and handed to the partial as data — the /kontakt/odeslat POST
        // handler still validates it via Auth::csrfVerify() unchanged.
        $contact = $this->contact->findBySiteSafe($siteId) ?: [];
        $csrfToken = \App\Core\Auth::csrfToken();
        $contactCta = $this->buildPageCtaHtml($siteId, 'kontakt');

        return $this->renderHomePartial(
            'home-contact',
            HomepageData::contact($contact, $csrfToken, $contactCta)
        );
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
