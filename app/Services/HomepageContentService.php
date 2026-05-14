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
        $this->fillHomepageTextDefaults($blocks);
        $this->applyBlockFallback($blocks, 'sluzby_items', fn() => $this->buildServicesGridHtml($siteId, 3));
        $this->applyBlockFallback($blocks, 'sluzby_cta', fn() => $this->buildSectionCtaHtml('/sluzby', 'Všechny služby'));
        $this->applyBlockFallback($blocks, 'portfolio-tiles', fn() => $this->buildPortfolioHtml($siteId));
        $this->applyBlockFallback($blocks, 'portfolio_cta', fn() => $this->buildSectionCtaHtml('/nase-prace', 'Všechny práce'));
        $this->applyBlockFallback($blocks, 'cenik_body', fn() => $this->buildPricingHtml());
        $this->applyBlockFallback($blocks, 'cenik_cta', fn() => $this->buildSectionCtaHtml('/cenik', 'Celý ceník'));
        $this->applyBlockFallback($blocks, 'reviews_demo', fn() => $this->reviewContent->buildHomepageHtml($siteId, 6));
        $this->applyBlockFallback($blocks, 'reviews_cta', fn() => $this->buildSectionCtaHtml('/hodnoceni', 'Vsechny recenze'));
        $this->applyBlockFallback($blocks, 'kontakt_info', fn() => $this->buildContactHtml($siteId));
        $this->applyBlockFallback($blocks, 'contact_info', fn() => $this->buildContactHtml($siteId));
        $this->applyBlockFallback($blocks, 'faq_cta', fn() => $this->buildSectionCtaHtml('/faq', 'Všechny otázky'));
        $this->applyBlockFallback($blocks, 'blog_cta', fn() => $this->buildSectionCtaHtml('/blog', 'Všechny články'));
        $this->applyBlockFallback($blocks, 'kontakt_cta', fn() => $this->buildSectionCtaHtml('/kontakt', 'Kontakt'));
    }

    private function fillHomepageTextDefaults(array &$blocks): void
    {
        $defaults = [
            'hero_label' => 'Fotograf a kameraman - Olomouc, Praha',
            'hero_h1' => 'Zachytíme váš příběh <span>navždy</span>.',
            'hero_sub' => 'Profesionální svatební fotografie a videografie v Olomouci a po celé České republice. Každý okamžik si zaslouží být nezapomenutelný.',
            'sluzby_title' => 'Naše služby',
            'sluzby_intro' => 'Každý příběh je jedinečný. Naše služby přizpůsobíme přesně vám.',
            'portfolio_title' => 'Naše práce',
            'portfolio_intro' => 'Ukázka projektů a realizací.',
            'blog_title' => 'Nejnovější články',
            'blog_intro' => 'Rady a novinky z naší praxe.',
            'cenik_title' => 'Ceník',
            'cenik_intro' => 'Orientační přehled cen. Každá zakázka je individuální.',
            'faq_title' => 'Časté dotazy',
            'reviews_title' => 'Co o nas rikaji klienti',
            'reviews_intro' => 'Vybrane reference klientu, kterym jsme dodali fotografie nebo video.',
            'kontakt_title' => 'Kontakt',
            'kontakt_sub' => 'Ozvěte se nám. Rádi odpovíme na vaše otázky.',
        ];

        foreach ($blocks as &$block) {
            $key = (string)($block['block_key'] ?? '');
            if ($key === '' || !array_key_exists($key, $defaults)) {
                continue;
            }

            if ($this->isBlankHtml($block['content'] ?? '')) {
                $block['content'] = $defaults[$key];
            }
        }
        unset($block);

        foreach ($defaults as $key => $content) {
            $found = false;
            foreach ($blocks as $block) {
                if (($block['block_key'] ?? '') === $key) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $blocks[] = [
                    'block_key' => $key,
                    'block_type' => 'text',
                    'label' => $key,
                    'content' => $content,
                    'sort_order' => 0,
                    'autofill_disabled' => 0,
                ];
            }
        }
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
            $this->applyBlockFallback($blocks, 'service_' . $i, fn() => $this->buildServiceCardHtml($items[$i - 1] ?? null, $i));
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
            $cards .= $this->buildServiceCardHtml($item, $idx + 1);
        }

        $css = '<style>'
            . '.hp-svc-card,.svc-card{background:#1b1b1f;border-radius:14px;overflow:hidden;box-shadow:0 14px 30px rgba(0,0,0,.22);border:1px solid rgba(255,255,255,.06);display:flex;flex-direction:column;width:100%;min-width:0}'
            . '.hp-svc-card__media{height:190px;background:#0e0e11;overflow:hidden}'
            . '.hp-svc-card__media img{width:100%;height:100%;object-fit:cover;display:block}'
            . '.hp-svc-card__body{padding:20px;display:flex;flex-direction:column;gap:12px;flex:1}'
            . '.hp-svc-card__icon{width:38px;height:38px;border-radius:50%;display:grid;place-items:center;overflow:hidden;background:rgba(196,150,60,.14);border:1px solid rgba(196,150,60,.45)}'
            . '.hp-svc-card__icon img{width:100%;height:100%;object-fit:contain;padding:7px}'
            . '.hp-svc-card__num{font-size:.72rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:#c4963c}'
            . '.hp-svc-card__title{font-size:1.15rem;font-weight:800;color:#fff;line-height:1.25}'
            . '.hp-svc-card__desc{color:rgba(255,255,255,.68);line-height:1.65;font-size:.95rem;white-space:pre-line}'
            . '.hp-svc-card__btn{display:inline-flex;align-self:flex-start;margin-top:auto;padding:11px 16px;border-radius:6px;background:#c4963c;color:#fff;text-decoration:none;font-weight:700;font-size:.86rem}'
            . '.hp-svc-card__btn:hover{background:#d4a64c}'
            . '</style>';

        return $css . $cards;
    }

    private function buildServiceCardHtml(?array $service, int $index = 0): string
    {
        if (!$service) {
            return '';
        }

        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
        $title = (string)($service['title'] ?? '');
        $description = (string)($service['description'] ?? '');
        $imageUrl = $this->publicMediaUrl((string)($service['image_url'] ?? ''));
        $iconUrl = $this->publicMediaUrl((string)($service['icon_url'] ?? ''));
        $buttonText = trim((string)($service['button_text'] ?? '')) ?: 'Poptat službu';
        $buttonUrl = trim((string)($service['button_url'] ?? '')) ?: '/kontakt';
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
                $thumbFile = $photos[0]['thumb_path'] ?? $photos[0]['medium_path'] ?? $photos[0]['storage_path'] ?? '';
                $thumb = $this->publicMediaUrl((string)$thumbFile);
            }

            $thumbHtml = $thumb !== ''
                ? '<img src="' . $h($thumb) . '" alt="' . $h($entry['title']) . '" loading="lazy">'
                : '<div style="height:100%;background:#222;display:flex;align-items:center;justify-content:center;font-size:2rem;color:rgba(255,255,255,.3)">&#9654;</div>';
            $playIcon = $hasVideo ? '<span class="pt-play">&#9654;</span>' : '';

            $extraHtml = '';
            $stripPhotos = $hasVideo ? array_slice($photos, 0, 3) : array_slice($photos, 1, 3);
            foreach ($stripPhotos as $photo) {
                $pfile = $photo['thumb_path'] ?? $photo['storage_path'] ?? '';
                $extraHtml .= '<img src="' . $h($this->publicMediaUrl((string)$pfile)) . '" alt="' . $h($photo['alt_text'] ?? '') . '" loading="lazy">';
            }

            $tiles .= <<<HTML
<a class="pt-tile" href="/nase-prace">
  <div class="pt-thumb">{$thumbHtml}{$playIcon}</div>
  <div class="pt-photos">{$extraHtml}</div>
  <div class="pt-caption">{$h($entry['title'])}</div>
</a>
HTML;
        }

        $css = '<style>'
            . '.pt-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px}'
            . '.pt-tile{display:block;text-decoration:none;color:inherit;background:#1b1b1f;border-radius:14px;overflow:hidden;box-shadow:0 14px 30px rgba(0,0,0,.25);transition:transform .2s,box-shadow .2s}'
            . '.pt-tile:hover{transform:translateY(-3px);box-shadow:0 20px 40px rgba(0,0,0,.4)}'
            . '.pt-thumb{position:relative;height:200px;background:#0e0e11;overflow:hidden}'
            . '.pt-thumb img{width:100%;height:100%;object-fit:cover;display:block}'
            . '.pt-play{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:56px;height:56px;border-radius:50%;background:rgba(196,150,60,.92);display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#fff}'
            . '.pt-photos{display:flex;gap:3px;height:72px;background:#17171a;padding:3px}'
            . '.pt-photos img{flex:1;min-width:0;height:100%;object-fit:cover;display:block}'
            . '.pt-caption{padding:14px 16px 16px;font-weight:700;font-size:.95rem;color:#fff;background:#17171a}'
            . '@media(max-width:600px){.pt-grid{grid-template-columns:1fr}}'
            . '</style>';

        return $css . '<div class="pt-grid">' . $tiles . '</div>';
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

        $css = '<style>'
            . '.hp-blog-card{background:#1b1b1f;border-radius:14px;overflow:hidden;box-shadow:0 14px 30px rgba(0,0,0,.22);border:1px solid rgba(255,255,255,.06)}'
            . '.hp-blog-thumb{height:200px;background:#0e0e11;overflow:hidden}'
            . '.hp-blog-thumb img{width:100%;height:100%;object-fit:cover;display:block}'
            . '.hp-blog-body{padding:20px}'
            . '.hp-blog-title{margin:0 0 10px;font-size:1rem;color:#fff;line-height:1.35}'
            . '.hp-blog-excerpt{color:rgba(255,255,255,.65);font-size:.875rem;line-height:1.55;margin:0 0 16px}'
            . '.hp-blog-link{color:#c4963c;font-weight:700;font-size:.875rem;text-decoration:none}'
            . '</style>';

        return $css . '<article class="hp-blog-card">'
            . $imageHtml
            . '<div class="hp-blog-body">'
            . '<h3 class="hp-blog-title"><a href="/' . $h($post['slug']) . '" style="color:inherit;text-decoration:none">'
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
            $plan = trim((string)($map['cenik_card_' . $i . '_title'] ?? '')) ?: ('Balíček ' . $i);
            $cards .= '<button type="button" class="hp-cenik-card' . $featured . '" data-cenik-open data-plan="' . $h($plan) . '">'
                . '<div class="hp-cenik-card-body">'
                . '<h3 class="hp-cenik-card-title">' . $h($map['cenik_card_' . $i . '_title'] ?? '') . '</h3>'
                . '<div class="hp-cenik-card-desc">' . nl2br($h($map['cenik_card_' . $i . '_desc'] ?? '')) . '</div>'
                . '</div>'
                . '<div class="hp-cenik-card-price">' . $h($map['cenik_card_' . $i . '_price'] ?? '') . '</div>'
                . '</button>';
        }

        $css = '<style>'
            . '.hp-cenik-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:24px}'
            . '.hp-cenik-card{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:32px;display:flex;flex-direction:column;gap:24px;text-align:left;width:100%;cursor:pointer;color:inherit;transition:transform .2s,border-color .2s}'
            . '.hp-cenik-card:hover{transform:translateY(-2px);border-color:rgba(255,255,255,.24)}'
            . '.hp-cenik-card--featured{border-color:rgba(255,255,255,.3);background:rgba(255,255,255,.09)}'
            . '.hp-cenik-card-body{flex:1}'
            . '.hp-cenik-card-title{font-size:1.25rem;font-weight:700;color:#fff;margin-bottom:12px}'
            . '.hp-cenik-card-desc{color:rgba(255,255,255,.65);line-height:1.7;font-size:.95rem;white-space:pre-line}'
            . '.hp-cenik-card-price{font-size:1.3rem;font-weight:800;color:#fff;padding-top:20px;border-top:1px solid rgba(255,255,255,.1)}'
            . '.hp-cenik-modal{position:fixed;inset:0;z-index:9999;display:none;align-items:center;justify-content:center;padding:24px;background:rgba(0,0,0,.72)}'
            . '.hp-cenik-modal.is-open{display:flex}'
            . '.hp-cenik-modal__dialog{position:relative;max-width:560px;width:100%;background:#151518;border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:28px;box-shadow:0 30px 80px rgba(0,0,0,.5)}'
            . '.hp-cenik-modal__title{font-size:1.5rem;font-weight:900;color:#fff;margin-bottom:8px}'
            . '.hp-cenik-modal__plan{color:#c4963c;font-weight:800;margin-bottom:20px}'
            . '.hp-cenik-modal__close{position:absolute;top:12px;right:12px;background:transparent;border:none;color:#fff;font-size:2rem;line-height:1;cursor:pointer}'
            . '.hp-cenik-modal form{display:grid;gap:12px}'
            . '.hp-cenik-modal label{font-size:.82rem;color:rgba(255,255,255,.55);text-transform:uppercase;letter-spacing:.04em}'
            . '.hp-cenik-modal input,.hp-cenik-modal textarea{width:100%;background:#0f0f12;border:1px solid rgba(255,255,255,.12);color:#fff;padding:12px 14px;border-radius:8px;font-size:.95rem;font-family:inherit}'
            . '.hp-cenik-modal textarea{min-height:120px;resize:vertical}'
            . '.hp-cenik-modal input::placeholder,.hp-cenik-modal textarea::placeholder{color:rgba(255,255,255,.35)}'
            . '.hp-cenik-modal__submit{padding:14px 18px;background:#c4963c;color:#fff;border:none;border-radius:8px;font-weight:800;cursor:pointer}'
            . '.hp-cenik-modal__submit:hover{background:#d4a64c}'
            . '</style>';

        $csrf = htmlspecialchars(\App\Core\Auth::csrfToken(), ENT_QUOTES);
        $modal = <<<HTML
<div class="hp-cenik-modal" data-cenik-modal aria-hidden="true">
  <div class="hp-cenik-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="cenik-modal-title">
    <button type="button" class="hp-cenik-modal__close" data-cenik-close aria-label="Zavřít">&times;</button>
    <h3 id="cenik-modal-title" class="hp-cenik-modal__title">Nezávazně poptat</h3>
    <div class="hp-cenik-modal__plan" data-cenik-plan></div>
    <form method="POST" action="/kontakt/odeslat">
      <input type="hidden" name="_csrf" value="{$csrf}">
      <input type="hidden" name="website" value="" autocomplete="off">
      <label>Jméno *</label>
      <input type="text" name="name" required placeholder="Vaše jméno">
      <label>E-mail nebo telefon *</label>
      <input type="text" name="contact_value" required placeholder="email nebo telefon">
      <label>Zpráva *</label>
      <textarea name="message" required placeholder="Napište nám co nejvíce detailů"></textarea>
      <button type="submit" class="hp-cenik-modal__submit">Odeslat poptávku</button>
    </form>
  </div>
</div>
<script>
(function(){
  var modal = document.querySelector('[data-cenik-modal]');
  if(!modal) return;
  var planEl = modal.querySelector('[data-cenik-plan]');
  var message = modal.querySelector('textarea[name="message"]');
  var buttons = document.querySelectorAll('[data-cenik-open]');
  function openModal(plan){
    if(planEl) planEl.textContent = plan ? plan : 'Nezávazně poptat';
    if(message) message.value = plan ? ('Mám zájem o službu: ' + plan + '\\n\\nProsím o nezávaznou nabídku.') : '';
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
  }
  function closeModal(){
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
  }
  buttons.forEach(function(btn){ btn.addEventListener('click', function(){ openModal(btn.dataset.plan || ''); }); });
  modal.querySelectorAll('[data-cenik-close]').forEach(function(el){ el.addEventListener('click', closeModal); });
  modal.addEventListener('click', function(e){ if(e.target === modal) closeModal(); });
  document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closeModal(); });
})();
</script>
HTML;

        return $css . '<div class="hp-cenik-grid">' . $cards . '</div>' . $modal;
    }

    private function buildSectionCtaHtml(string $url, string $label): string
    {
        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
        return '<style>'
            . '.hp-section-cta{margin-top:22px;display:flex;justify-content:flex-start}'
            . '.hp-section-cta .btn-secondary{display:inline-flex;align-items:center;justify-content:center;padding:12px 18px;border:1px solid rgba(255,255,255,.18);border-radius:8px;color:#fff;text-decoration:none;font-weight:700}'
            . '.hp-section-cta .btn-secondary:hover{background:#c4963c;border-color:#c4963c;color:#111}'
            . '</style>'
            . '<div class="hp-section-cta"><a href="' . $h($url) . '" class="btn-secondary">' . $h($label) . '</a></div>';
    }

    private function buildFaqItemHtml(int $siteId, int $index): string
    {
        $items = array_slice($this->faq->allActive($siteId), 0, 5);
        if (!isset($items[$index])) {
            return '';
        }

        $item = $items[$index];
        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);

        return '<strong style="display:block;color:#fff;margin-bottom:8px">'
            . $h($item['question']) . '</strong>'
            . '<p style="color:rgba(255,255,255,.72);line-height:1.6;margin:0">'
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
            $lines[] = '<p style="color:rgba(255,255,255,.45)">Doplňte kontaktní informace v administraci.</p>';
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
            $form = '<p style="color:rgba(255,255,255,.45);margin-bottom:12px">Formulář je připravený, ale chybí cílový e-mail pro odeslání.</p>' . $form;
        }

        $css = '<style>'
            . '.hp-contact-grid{display:grid;grid-template-columns:1fr;gap:20px}'
            . '.hp-contact-grid > :first-child{order:2}'
            . '.hp-contact-grid > :last-child{order:1}'
            . '.hp-contact-box{background:#18181c;border-radius:14px;padding:20px;min-width:0}'
            . '.hp-contact-lines{display:flex;flex-direction:column;gap:12px}'
            . '.hp-contact-row{display:flex;flex-direction:column;gap:4px;line-height:1.55;color:rgba(255,255,255,.86)}'
            . '.hp-contact-label{font-size:.78rem;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:.04em}'
            . '.hp-contact-row a{color:#c4963c;text-decoration:none}'
            . '.hp-contact-socials{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}'
            . '.hp-contact-social{display:inline-flex;align-items:center;padding:8px 14px;border-radius:8px;background:#202028;color:#fff;text-decoration:none;font-size:.9rem}'
            . '.hp-contact-map{display:inline-flex;margin-top:16px;padding:12px 18px;background:#c4963c;color:#fff;text-decoration:none;border-radius:8px;font-weight:800}'
            . '.hp-contact-map-embed{display:block;width:100%;aspect-ratio:4/3;min-height:260px;border:0;border-radius:12px;margin-top:16px;background:#0f0f12}'
            . '.hp-contact-cta{margin-top:20px}'
            . '.hp-contact-cta .btn-secondary{display:inline-flex;align-items:center;justify-content:center;padding:12px 18px;border:1px solid rgba(255,255,255,.18);border-radius:8px;color:#fff;text-decoration:none;font-weight:700}'
            . '.hp-contact-cta .btn-secondary:hover{background:#c4963c;border-color:#c4963c;color:#111}'
            . '.hp-contact-form{display:grid;gap:10px}'
            . '.hp-contact-form label{font-size:.82rem;color:rgba(255,255,255,.55);text-transform:uppercase;letter-spacing:.04em}'
            . '.hp-contact-form input,.hp-contact-form textarea{width:100%;background:#111;border:1px solid rgba(255,255,255,.12);color:#fff;padding:12px 14px;border-radius:8px;font-size:.95rem;font-family:inherit}'
            . '.hp-contact-form textarea{min-height:120px;resize:vertical}'
            . '.hp-contact-form button{padding:14px 18px;background:#c4963c;color:#fff;border:none;border-radius:8px;font-weight:800;cursor:pointer}'
            . '.hp-contact-form button:hover{background:#d4a64c}'
            . '@media(min-width:760px){.hp-contact-grid{grid-template-columns:1.2fr .8fr;gap:24px}.hp-contact-grid > :first-child{order:1}.hp-contact-grid > :last-child{order:2}.hp-contact-box{padding:24px}.hp-contact-row{flex-direction:row;gap:12px}.hp-contact-label{flex:0 0 90px;padding-top:2px}}'
            . '</style>';

        return $css
            . '<div class="hp-contact-grid">'
            . '<div class="hp-contact-box"><div class="hp-contact-lines">' . implode('', $lines) . '</div>' . $map . '<div class="hp-contact-cta"><a href="/kontakt" class="btn-secondary">Kontakt</a></div></div>'
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
            return $m[1];
        }

        if (str_starts_with($normalized, '/storage/media/')) {
            return $normalized;
        }

        if (str_starts_with($normalized, 'storage/media/')) {
            return '/' . $normalized;
        }

        return '/' . ltrim($normalized, '/');
    }
}
