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

        foreach (['blog', 'faq', 'hodnoceni', 'kontakt', 'nase-prace'] as $page) {
            $assert(!is_file($themeDir . '/views/pages/' . $page . '.phtml'), 'Static page view remains: ' . $page);
        }
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
}
