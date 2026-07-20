<?php

declare(strict_types=1);

use App\Services\ThemeTemplateRegistry;
use App\View\ViewFactory;

function register_premium_photo_data_contract_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);
    $themeDir = $root . '/Templates/premium-photo';

    $test('premium photo uses one dynamic layout without static route replacements', function () use ($assert, $themeDir): void {
        $manifest = json_decode((string)file_get_contents($themeDir . '/template.json'), true, 512, JSON_THROW_ON_ERROR);
        $assert(($manifest['layout_html'] ?? '') === 'layout.html', 'Premium Photo must use the shared layout.');
        $assert(($manifest['overrides'] ?? null) === [], 'Static per-route layouts must stay disabled.');
        $assert(!is_dir($themeDir . '/overrides') || glob($themeDir . '/overrides/*') === [], 'Dead static route copies remain in the theme.');

        foreach (['blog', 'faq', 'kontakt', 'nase-prace'] as $page) {
            $assert(!is_file($themeDir . '/views/pages/' . $page . '.phtml'), 'Static page view remains: ' . $page);
        }

        $reviewsPage = (string)file_get_contents($themeDir . '/views/pages/hodnoceni.phtml');
        $assert(str_contains($reviewsPage, '$fragment'), 'Reviews wrapper must preserve the database fragment.');
        $assert(str_contains($reviewsPage, 'premium-reviews-route'), 'Reviews wrapper must expose the theme route hook.');
        $assert(!str_contains($reviewsPage, 'Jan Jungling'), 'Reviews wrapper contains static review content.');
    });

    $test('premium photo homepage renders the supplied database fragment unchanged', function () use ($assert): void {
        $registry = new ThemeTemplateRegistry();
        $theme = $registry->find('premium-photo');
        $assert($theme !== null, 'Premium Photo theme is missing.');
        $contentView = $registry->pageContentView($theme, 'homepage');
        $assert($contentView !== null, 'Premium Photo homepage view is missing.');

        $sentinel = '<section data-db-sentinel="portfolio-42">DB ONLY</section>';
        $output = ViewFactory::forTheme('premium-photo', [], 'views')
            ->render('pages/homepage', ['fragment' => $sentinel, 'pageKey' => 'homepage']);
        $assert(str_contains($output, $sentinel), 'Database fragment was not preserved.');
        $assert(!str_contains($output, 'youtube.com/embed'), 'Homepage view embeds a static hero video.');
    });

    $test('premium photo chrome exposes dynamic navigation and footer injection targets', function () use ($assert, $themeDir): void {
        $layout = (string)file_get_contents($themeDir . '/layout.html');
        $assert(str_contains($layout, '{site_name}'), 'Site name placeholder is missing.');
        $assert(str_contains($layout, '{nav}'), 'Dynamic navigation placeholder is missing.');
        $assert(str_contains($layout, '<footer class="site-footer"'), 'Footer injection target is missing.');
        $assert(!str_contains($layout, 'info@rbvideos.cz'), 'Static contact details remain in the layout.');
        $assert(!str_contains($layout, 'href="#" aria-label="Instagram"'), 'Static social links remain in the layout.');
    });

    $test('premium photo homepage suppresses legacy fades and preserves the scroll timeline axis', function () use ($assert, $themeDir): void {
        $css = (string)file_get_contents($themeDir . '/assets/cms.css');
        $js = (string)file_get_contents($themeDir . '/assets/theme.js');

        $assert(str_contains($css, '.premium-photo-home .section::before'), 'Homepage fade reset selector is missing.');
        $assert(str_contains($css, 'content: none !important'), 'Legacy homepage fade is not disabled.');
        $assert(str_contains($css, '.premium-photo-home #nase-prace'), 'Portfolio dark-background override is missing.');
        $assert(str_contains($css, '#proces.timeline .tl-rail'), 'Timeline rail styling is missing.');
        $assert(str_contains($css, '#proces.timeline .tl-playhead'), 'Timeline playhead styling is missing.');
        $assert(preg_match('/#proces\.timeline \.tl-rail\s*\{[^}]*position:\s*absolute\s*!important;[^}]*bottom:\s*0\s*!important;[^}]*height:\s*auto\s*!important;/s', $css) === 1, 'Timeline rail must be absolutely positioned across the section height.');
        $assert(preg_match('/#proces\.timeline \.tl-playhead\s*\{[^}]*position:\s*absolute\s*!important;/s', $css) === 1, 'Timeline playhead must be absolutely positioned against the timeline.');
        $assert(str_contains($css, 'top: var(--timeline-track-start, 0px)'), 'Timeline progress fill must begin at the dynamic list boundary below the heading.');
        $assert(str_contains($css, '.tl-playhead.is-visible'), 'Timeline playhead needs an explicit visible state.');
        $assert(preg_match('/#proces\.timeline \.tl-playhead\s*\{[^}]*opacity:\s*0\s*!important;[^}]*visibility:\s*hidden\s*!important;/s', $css) === 1, 'Timeline playhead must be hidden in its zero-progress default state.');
        $assert(str_contains($js, 'tl.style.setProperty("--timeline-track-start", trackStart.toFixed(2) + "px")'), 'Timeline rail fill start is not connected to the list geometry.');
        $assert(str_contains($js, 'tl.style.setProperty("--timeline-progress-height", progressHeight.toFixed(2) + "px")'), 'Timeline rail fill is not connected to scroll progress.');
        $assert(str_contains($js, 'playhead.classList.toggle("is-visible", progress > 0)'), 'Timeline playhead does not hide at zero progress.');
    });

    $test('premium photo owns database snippet markup through theme partials and archetypes', function () use ($assert, $themeDir): void {
        $requiredViews = [
            'partials/home-service-card.phtml' => 'premium-service-card',
            'partials/home-portfolio.phtml' => 'premium-portfolio-grid',
            'partials/home-blog-card.phtml' => 'premium-blog-card',
            'partials/home-reviews.phtml' => 'premium-review-grid',
            'partials/home-faq-item.phtml' => 'faq-item',
            'partials/home-contact.phtml' => 'premium-contact-grid',
            'archetypes/portfolio-listing.phtml' => 'premium-list-grid',
            'archetypes/blog-listing.phtml' => 'premium-blog-list',
            'archetypes/blog-detail.phtml' => 'premium-article',
            'archetypes/faq.phtml' => 'premium-faq-list',
            'archetypes/kontakt.phtml' => 'premium-contact-page',
            'archetypes/reviews.phtml' => 'premium-review-page',
        ];

        foreach ($requiredViews as $relativePath => $hook) {
            $path = $themeDir . '/views/' . $relativePath;
            $assert(is_file($path), 'Missing Premium Photo DB renderer: ' . $relativePath);
            $assert(str_contains((string)file_get_contents($path), $hook), 'Missing designer hook ' . $hook . ' in ' . $relativePath);
        }

        $reviewsView = (string)file_get_contents($themeDir . '/views/archetypes/reviews.phtml');
        $assert(str_contains($reviewsView, 'array_slice($items, 0, 6)'), 'Standalone reviews must keep the designer six-card composition.');

        $service = ViewFactory::forTheme('premium-photo', [], 'views')->render('partials/home-service-card', [
            'card' => [
                'num' => '01',
                'title' => 'DB SERVICE SENTINEL',
                'description' => 'DB DESCRIPTION SENTINEL',
                'imageUrl' => '/media/db-sentinel.webp',
                'buttonText' => 'DB CTA',
                'buttonUrl' => '/db-service',
            ],
        ]);
        $assert(str_contains($service, 'premium-service-card'), 'Theme service DOM was not rendered.');
        $assert(str_contains($service, 'DB SERVICE SENTINEL'), 'Theme service renderer lost database data.');
        $assert(str_contains($service, '/media/db-sentinel.webp'), 'Theme service renderer lost database media.');
    });
}
