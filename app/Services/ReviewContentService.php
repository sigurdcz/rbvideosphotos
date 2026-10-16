<?php

namespace App\Services;

use App\Repositories\PageRepository;
use App\Repositories\ReviewRepository;
use App\Repositories\TemplateRepository;

class ReviewContentService
{
    private ReviewRepository $reviews;
    private PageRepository $pages;
    private TemplateRepository $templates;

    public function __construct()
    {
        $this->reviews = new ReviewRepository();
        $this->pages = new PageRepository();
        $this->templates = new TemplateRepository();
    }

    public function buildHomepageHtml(int $siteId, int $limit = 6, ?string $themeKey = null): string
    {
        $items = $this->reviews->allActive($siteId);
        if ($limit > 0) {
            $items = array_slice($items, 0, $limit);
        }

        // Phase 5: the teaser grid markup now comes from a PHTML partial fed by
        // HomepageData::reviewCards() (data, not concatenated HTML). The inline
        // <style> stays here byte-identically - moving it to a shared asset is a
        // phase-6 cleanup. $themeKey (post-refactor) lets a theme override
        // partials/home-reviews via the same ViewResolver cascade every other
        // archetype/partial now uses.
        return $this->homepageCss()
            . \App\View\ViewFactory::forTheme($themeKey)->render(
                'partials/home-reviews',
                ['items' => \App\ViewModels\HomepageData::reviewCards($items)]
            );
    }

    public function countActive(int $siteId): int
    {
        return count($this->reviews->allActive($siteId));
    }

    public function buildListingHtml(int $siteId, ?string $themeKey = null): string
    {
        // Phase 4 of the templating refactor: the /hodnoceni listing grid now
        // renders through the reviews archetype (App\ViewModels\ReviewsPageData
        // + app/Views/archetypes/reviews.phtml) instead of string concatenation
        // here. The homepage teaser (buildHomepageHtml() below) is unchanged -
        // it belongs to the homepage archetype, phase 5.
        $data = \App\ViewModels\ReviewsPageData::forSite($siteId);

        return \App\View\ViewFactory::forTheme($themeKey)->render('archetypes/reviews', $data);
    }

    public function ensureHomepageSection(int $siteId): void
    {
        $homepage = $this->pages->findByUrlPath('');
        if (!$homepage || empty($homepage['template_id'])) {
            return;
        }

        $template = $this->templates->findById((int)$homepage['template_id']);
        if (!$template) {
            return;
        }

        $html = (string)($template['html_source'] ?? '');
        if (str_contains($html, 'reviews_demo')) {
            return;
        }

        $section = <<<'HTML'

<section class="section section--dark hp-reviews-demo" id="hodnoceni">
  <div class="container">
    <p class="section-label">Co o nas rikaji</p>
    <h2 data-block="reviews_title" class="section-title">Hodnocení klientů</h2>
    <p data-block="reviews_intro" class="section-intro">Vybrane reference klientu, kterym jsme dodali foto nebo video.</p>
    <div data-block="reviews_demo"><!-- Recenze --></div>
    <div data-block="reviews_cta" class="hp-section-cta"><a href="/hodnoceni" class="btn-secondary">Všechny recenze</a></div>
  </div>
</section>
HTML;

        $updated = preg_replace('~(<section\b[^>]*id=(["\'])kontakt\2[^>]*>)~i', $section . "\n$1", $html, 1, $count);
        if (!$updated || $count === 0) {
            $updated = $html . $section;
        }

        $this->templates->update((int)$template['id'], [
            'name' => $template['name'],
            'html_source' => $updated,
            'css_source' => $template['css_source'] ?? '',
            'js_source' => $template['js_source'] ?? '',
        ]);
    }

    private function homepageCss(): string
    {
        return '<style>'
            . '.hp-reviews-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px;margin-top:22px}'
            . '.hp-review-card{background:rgba(255,255,255,.04);border:1px solid rgba(196,150,60,.32);padding:22px;min-height:190px}'
            . '.hp-review-stars{color:#d9a441;letter-spacing:.16em;margin-bottom:12px;font-size:.95rem}'
            . '.hp-review-card p{color:rgba(255,255,255,.72);line-height:1.7;margin:0 0 18px}'
            . '.hp-review-card strong{display:block;color:#fff;font-size:.95rem;margin-bottom:2px}'
            . '.hp-review-card span{color:rgba(255,255,255,.48);font-size:.82rem}'
            . '</style>';
    }
}
