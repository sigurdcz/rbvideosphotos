<?php

namespace App\Services;

use App\Repositories\BlockRepository;
use App\Repositories\BlogRepository;
use App\Repositories\ContactRepository;
use App\Repositories\FaqRepository;
use App\Repositories\PageRepository;
use App\Repositories\PortfolioRepository;

class HomepageContentService
{
    private PageRepository $pages;
    private BlockRepository $blocks;
    private BlogRepository $blog;
    private PortfolioRepository $portfolio;
    private FaqRepository $faq;
    private ContactRepository $contact;

    public function __construct()
    {
        $this->pages     = new PageRepository();
        $this->blocks    = new BlockRepository();
        $this->blog      = new BlogRepository();
        $this->portfolio = new PortfolioRepository();
        $this->faq       = new FaqRepository();
        $this->contact   = new ContactRepository();
    }

    public function applyFallbacks(array $page, array $blocks): array
    {
        if (trim((string)($page['url_path'] ?? ''), '/') !== '') {
            return $blocks;
        }

        $this->applyBlockFallback($blocks, 'sluzby_items', function () {
            return $this->buildServicesHtml();
        });
        $this->applyBlockFallback($blocks, 'portfolio-tiles', function () use ($page) {
            return $this->buildPortfolioHtml((int)$page['site_id']);
        });
        $this->applyBlockFallback($blocks, 'blog_1', function () use ($page) {
            return $this->buildBlogCardHtml((int)$page['site_id'], 0);
        });
        $this->applyBlockFallback($blocks, 'blog_2', function () use ($page) {
            return $this->buildBlogCardHtml((int)$page['site_id'], 1);
        });
        $this->applyBlockFallback($blocks, 'blog_3', function () use ($page) {
            return $this->buildBlogCardHtml((int)$page['site_id'], 2);
        });
        $this->applyBlockFallback($blocks, 'cenik_body', function () {
            return $this->buildPricingHtml();
        });
        $this->applyBlockFallback($blocks, 'faq_1', function () use ($page) {
            return $this->buildFaqItemHtml((int)$page['site_id'], 0);
        });
        $this->applyBlockFallback($blocks, 'faq_2', function () use ($page) {
            return $this->buildFaqItemHtml((int)$page['site_id'], 1);
        });
        $this->applyBlockFallback($blocks, 'faq_3', function () use ($page) {
            return $this->buildFaqItemHtml((int)$page['site_id'], 2);
        });
        $this->applyBlockFallback($blocks, 'kontakt_info', function () use ($page) {
            return $this->buildContactHtml((int)$page['site_id']);
        });

        return $blocks;
    }

    private function applyBlockFallback(array &$blocks, string $key, callable $builder): void
    {
        foreach ($blocks as &$block) {
            if (($block['block_key'] ?? '') !== $key) {
                continue;
            }

            if ($this->isBlankHtml($block['content'] ?? '')) {
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
            'block_key'  => $key,
            'block_type' => 'html',
            'label'      => $key,
            'content'    => $content,
            'sort_order' => 0,
        ];
    }

    private function isBlankHtml(?string $content): bool
    {
        $decoded = html_entity_decode((string)$content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = str_replace("\xc2\xa0", ' ', $decoded);
        return trim(strip_tags($decoded)) === '';
    }

    private function buildServicesHtml(): string
    {
        $page = $this->pages->findByUrlPath('sluzby');
        if (!$page) {
            return '';
        }

        $block = $this->blocks->findByKey((int)$page['id'], 'sluzby_grid');
        return trim((string)($block['content'] ?? ''));
    }

    private function buildPortfolioHtml(int $siteId): string
    {
        $entries = $this->portfolio->allPublished($siteId);
        if (empty($entries)) {
            return '<p style="color:rgba(255,255,255,.45);text-align:center;padding:40px 0">Zatím žádné publikované práce.</p>';
        }

        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
        $tiles = '';

        foreach ($entries as $entry) {
            $photos   = $this->portfolio->getMedia((int)$entry['id']);
            $thumb    = !empty($entry['video_url']) ? PortfolioRepository::youtubeThumbnail((string)$entry['video_url']) : '';
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

        return '<h3 style="margin:0 0 10px;font-size:1rem;color:#fff;line-height:1.35">'
            . '<a href="/' . $h($post['slug']) . '" style="color:inherit;text-decoration:none">'
            . $h($post['title']) . '</a></h3>'
            . '<p style="color:rgba(255,255,255,.65);font-size:.875rem;line-height:1.55;margin:0 0 16px">'
            . $h($excerpt) . '</p>'
            . '<a href="/' . $h($post['slug']) . '" style="color:#c4963c;font-weight:700;font-size:.875rem">&#268;&#237;st d&#225;l &rarr;</a>';
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
            $cards .= '<div class="hp-cenik-card' . $featured . '">'
                . '<div class="hp-cenik-card-body">'
                . '<h3 class="hp-cenik-card-title">' . $h($map['cenik_card_' . $i . '_title'] ?? '') . '</h3>'
                . '<div class="hp-cenik-card-desc">' . nl2br($h($map['cenik_card_' . $i . '_desc'] ?? '')) . '</div>'
                . '</div>'
                . '<div class="hp-cenik-card-price">' . $h($map['cenik_card_' . $i . '_price'] ?? '') . '</div>'
                . '</div>';
        }

        $css = '<style>'
            . '.hp-cenik-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:24px}'
            . '.hp-cenik-card{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:32px;display:flex;flex-direction:column;gap:24px}'
            . '.hp-cenik-card--featured{border-color:rgba(255,255,255,.3);background:rgba(255,255,255,.09)}'
            . '.hp-cenik-card-body{flex:1}'
            . '.hp-cenik-card-title{font-size:1.25rem;font-weight:700;color:#fff;margin-bottom:12px}'
            . '.hp-cenik-card-desc{color:rgba(255,255,255,.65);line-height:1.7;font-size:.95rem;white-space:pre-line}'
            . '.hp-cenik-card-price{font-size:1.3rem;font-weight:800;color:#fff;padding-top:20px;border-top:1px solid rgba(255,255,255,.1)}'
            . '</style>';

        return $css . '<div class="hp-cenik-grid">' . $cards . '</div>';
    }

    private function buildFaqItemHtml(int $siteId, int $index): string
    {
        $items = array_slice($this->faq->allActive($siteId), 0, 3);
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

    private function buildContactHtml(int $siteId): string
    {
        $contact = $this->contact->findBySiteSafe($siteId);
        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
        $lines = [];

        foreach (['phone_1', 'phone_2', 'phone_3'] as $phoneKey) {
            if (!empty($contact[$phoneKey])) {
                $phone = (string)$contact[$phoneKey];
                $tel = preg_replace('/\s+/', '', $phone);
                $lines[] = '<p><a href="tel:' . $h((string)$tel) . '">' . $h($phone) . '</a></p>';
            }
        }

        if (!empty($contact['email'])) {
            $lines[] = '<p><a href="mailto:' . $h($contact['email']) . '">' . $h($contact['email']) . '</a></p>';
        }

        foreach (['address_1', 'address_2'] as $addressKey) {
            if (!empty($contact[$addressKey])) {
                $lines[] = '<p>' . nl2br($h($contact[$addressKey])) . '</p>';
            }
        }

        for ($i = 1; $i <= 5; $i++) {
            $type = trim((string)($contact['social_' . $i . '_type'] ?? ''));
            $url  = trim((string)($contact['social_' . $i . '_url'] ?? ''));
            if ($type !== '' && $url !== '') {
                $lines[] = '<p><a href="' . $h($url) . '" target="_blank" rel="noopener">'
                    . ucfirst($h($type)) . '</a></p>';
            }
        }

        if ($lines === []) {
            return '<p style="color:rgba(255,255,255,.45)">Doplňte kontaktní informace v administraci.</p>';
        }

        return '<div class="cta-info">' . implode('', $lines) . '</div>';
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
