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

    $test('premium photo homepage pricing teaser hides prices and owns its CTA', function () use ($assert, $themeDir): void {
        $pricing = (string)file_get_contents($themeDir . '/views/partials/home-pricing.phtml');
        $cta = (string)file_get_contents($themeDir . '/views/partials/home-section-cta.phtml');
        $css = (string)file_get_contents($themeDir . '/assets/cms.css');

        $assert(!str_contains($pricing, "card['price']") && !str_contains($pricing, 'hp-cenik-card-price'), 'Homepage pricing teaser still renders package prices.');
        $assert(str_contains($pricing, 'hp-cenik-card-badge') && str_contains($pricing, 'Nejoblíbenější'), 'Featured homepage pricing badge is missing.');
        $assert(str_contains($cta, "\$url === '/cenik'") && str_contains($cta, 'Více o cenách'), 'Homepage pricing CTA does not use the requested label.');
        $assert(str_contains($css, 'background:#3a352f') && str_contains($css, 'background:#d7bc8b'), 'Featured homepage pricing colors are not using the muted palette.');
    });

    $test('premium photo chrome exposes dynamic navigation and footer injection targets', function () use ($assert, $themeDir): void {
        $layout = (string)file_get_contents($themeDir . '/layout.html');
        $assert(str_contains($layout, '{site_name}'), 'Site name placeholder is missing.');
        $assert(str_contains($layout, '{nav}'), 'Dynamic navigation placeholder is missing.');
        $assert(str_contains($layout, '<footer class="site-footer"'), 'Footer injection target is missing.');
        $footer = (string)file_get_contents($themeDir . '/views/partials/footer.phtml');
        $css = (string)file_get_contents($themeDir . '/assets/theme.css');
        $assert(str_contains($footer, 'foot-mobile-contact') && str_contains($footer, 'foot-mobile-nav'), 'Reference mobile footer structure is missing.');
        $assert(str_contains($footer, 'foot-contact-row') && substr_count($footer, 'foot-contact-icon') >= 3, 'Desktop footer contact icon rows are missing.');
        $assert(str_contains($footer, 'href="tel:') && str_contains($footer, 'href="mailto:'), 'Desktop footer contact rows lost their actionable DB links.');
        $assert(str_contains($footer, '$__phones[0]') && str_contains($footer, '$__email') && str_contains($footer, '$__addr'), 'Mobile footer contact rows are not sourced from footer view data.');
        $assert(str_contains($footer, "['url' => '/cenik', 'label' => 'Ceník']"), 'Mobile footer pricing navigation entry is missing.');
        $assert(str_contains($css, '@media (max-width:640px)') && str_contains($css, '.foot-desktop{display:none !important}'), 'Mobile footer is not isolated from the desktop footer.');
        $assert(!str_contains($layout, 'info@rbvideos.cz'), 'Static contact details remain in the layout.');
        $assert(!str_contains($layout, 'href="#" aria-label="Instagram"'), 'Static social links remain in the layout.');
    });

    $test('premium photo uses contrast-safe demo logo assets', function () use ($assert, $themeDir, $root): void {
        $layout = (string)file_get_contents($themeDir . '/layout.html');
        $footer = (string)file_get_contents($themeDir . '/views/partials/footer.phtml');
        $css = (string)file_get_contents($themeDir . '/assets/theme.css');
        $assetDir = $root . '/public/assets/demo/logos';

        foreach (['rb-logo-black.png', 'rb-logo-beige.png', 'rb-logo-orange.png'] as $asset) {
            $assert(is_file($assetDir . '/' . $asset), 'Missing organized demo logo asset: ' . $asset);
        }
        $assert(str_contains($layout, '/assets/demo/logos/rb-logo-black.png'), 'Light header does not use the black logo variant.');
        $assert(str_contains($footer, '/assets/demo/logos/rb-logo-beige.png'), 'Dark footer does not use the beige logo variant.');
        $assert(str_contains($layout, 'aria-label="{site_name}"') && str_contains($footer, "aria-label=\"<?= \$this->e(\$__b['name']) ?>\""), 'Logo links must preserve an accessible brand name.');
        $assert(str_contains($layout, '<span class="premium-brand-logo__text">{site_name}.</span>') && str_contains($footer, 'premium-brand-logo__text'), 'Header or footer is missing the visible brand text next to its logo.');
        $assert(substr_count($layout, 'alt=""') >= 1 && substr_count($footer, 'alt=""') >= 1, 'Decorative logo marks must not duplicate the accessible brand name.');
        $assert(str_contains($css, '.premium-brand-logo--header img') && str_contains($css, '.premium-brand-logo--footer img'), 'Logo dimensions are not owned by Premium Photo CSS.');
    });

    $test('premium photo internal routes share one shortened hero contract', function () use ($assert, $themeDir): void {
        $css = (string)file_get_contents($themeDir . '/assets/cms.css');
        $js = (string)file_get_contents($themeDir . '/assets/theme.js');

        $assert(str_contains($css, '--premium-inner-hero-height: clamp(320px, 39vh, 440px)'), 'Desktop internal hero is not two thirds of the Services reference height.');
        $assert(str_contains($css, 'not([data-cms-page-key="homepage"]) .premium-inner-hero'), 'Internal hero contract is not explicitly isolated from homepage.');
        $assert(str_contains($css, 'width:min(calc(100% - 48px),1120px)') && str_contains($css, 'justify-items:start'), 'Generic and service-detail heroes do not share the Services content axis.');
        $assert(str_contains($css, '.premium-inner-hero .page-head--hero') && str_contains($css, '.premium-inner-hero h1'), 'Listing/detail hero typography is not owned by the shared contract.');
        $assert(str_contains($css, '--premium-inner-hero-height:360px'), 'Mobile internal hero height contract is missing.');
        $assert(str_contains($js, 'innerPageKey !== "homepage"') && str_contains($js, 'classList.add("premium-inner-hero")'), 'Runtime does not classify non-homepage hero variants consistently.');
        $assert(str_contains($js, 'eyebrow.textContent = innerHeroTitle.textContent.trim()'), 'Generic Ceník and legal heroes do not derive their missing section label from the real H1.');
        $assert(str_contains($js, 'innerPageKey === "cenik"') && str_contains($js, 'ceník · balíčky na míru'), 'Cenik hero does not receive the standard production meta row.');
        $assert(str_contains($css, '.page-hero.premium-inner-hero > .inner-hero-band-cap') && str_contains($css, 'width:min(calc(100% - 48px),1120px)'), 'Cenik meta row does not share the standard internal hero axis.');
    });

    $test('public contact contract excludes event dates on frontend and backend', function () use ($assert, $themeDir): void {
        $homeContact = (string)file_get_contents($themeDir . '/views/partials/home-contact.phtml');
        $contactPage = (string)file_get_contents($themeDir . '/views/archetypes/kontakt.phtml');
        $contactForm = (string)file_get_contents($themeDir . '/views/partials/contact-form.phtml');
        $publicIndex = (string)file_get_contents(dirname($themeDir, 2) . '/public/index.php');
        $normalizer = (string)file_get_contents(dirname($themeDir, 2) . '/app/Services/Render/MarkupNormalizer.php');

        $assert(str_contains($homeContact, "partials/contact-form") && str_contains($contactPage, "partials/contact-form"), 'Homepage and contact route do not share the same form partial.');
        $assert(str_contains($homeContact, 'premium-contact-form-wrap') && str_contains($contactPage, 'premium-contact-form-wrap'), 'Homepage and contact route do not share the same form wrapper hierarchy.');
        $assert(!str_contains($homeContact, '<form class="premium-contact-form"') && !str_contains($contactPage, '<form class="premium-contact-form"'), 'A route still owns a divergent inline contact form.');
        $assert(str_contains($contactForm, '<form class="premium-contact-form"'), 'Shared Premium Photo contact form is missing.');
        $assert(str_contains($contactPage, 'class="btn-secondary">Všechny otázky</a>'), 'Contact route is missing the shared all-FAQ CTA.');
        $contactCss = (string)file_get_contents($themeDir . '/assets/cms.css');
        $assert(str_contains($contactCss, '#kontakt > .container > .section-title { display: none; }'), 'Homepage contact title is still visible.');
        $assert(str_contains($contactCss, '.hp-faq-item { padding:0;') && str_contains($contactCss, 'border:0 !important; background:transparent !important;'), 'Moved homepage FAQ cards still expose a duplicate outer border.');
        $assert(str_contains($contactCss, '.premium-contact-faq > .hp-section-cta .btn-secondary'), 'Shared all-FAQ CTA has no Premium Photo button styling.');
        $namePosition = strpos($contactForm, 'name="name"');
        $contactPosition = strpos($contactForm, 'name="contact_value"');
        $placePosition = strpos($contactForm, 'name="event_place"');
        $messagePosition = strpos($contactForm, 'name="message"');
        $assert($namePosition !== false && $contactPosition !== false && $placePosition !== false && $messagePosition !== false
            && $namePosition < $contactPosition && $contactPosition < $placePosition && $placePosition < $messagePosition,
            'Shared contact fields are not ordered like the contact-page reference.');
        $assert(!str_contains($contactForm, 'placeholder='), 'Homepage-only placeholders leaked into the shared contact design.');
        $assert(!str_contains($homeContact, 'event_date') && !str_contains($contactPage, 'event_date') && !str_contains($contactForm, 'event_date'), 'Premium Photo still renders an event date field.');
        $assert(!str_contains($publicIndex, "\$_POST['event_date']") && !str_contains($publicIndex, 'Datum akce:'), 'Contact POST handler still consumes or mails an event date.');
        $assert(str_contains($normalizer, 'name="event_date"'), 'Legacy frontend date-field guard is missing.');
    });

    $test('premium photo pricing route uses themed DB archetype and reference composition', function () use ($assert, $themeDir): void {
        $view = (string)file_get_contents($themeDir . '/views/archetypes/cenik.phtml');
        $css = (string)file_get_contents($themeDir . '/assets/cms.css');
        $themeCss = (string)file_get_contents($themeDir . '/assets/theme.css');
        $renderer = (string)file_get_contents(dirname($themeDir, 2) . '/app/Services/Render/CenikPageRenderer.php');
        $runtimeCss = (string)file_get_contents(dirname($themeDir, 2) . '/public/assets/frontend-runtime.css');
        $seeder = (string)file_get_contents(dirname($themeDir, 2) . '/app/Services/DemoContentSeeder.php');
        $migration = (string)file_get_contents(dirname($themeDir, 2) . '/database/migrations/023_balance_cenik_premium_features.sql');

        $assert(str_contains($renderer, 'ViewFactory::forTheme($themeKey)'), 'Pricing renderer bypasses the active theme view cascade.');
        $assert(str_contains($view, 'premium-cenik-head') && str_contains($view, '$packages as $package'), 'Pricing theme archetype does not preserve DB-driven package rendering.');
        $assert(!str_contains($view, '14 900') && !str_contains($view, '24 900') && !str_contains($view, '34 900'), 'Static package prices leaked into the theme archetype.');
        $assert(str_contains($css, 'justify-items:start') && str_contains($css, '.ckp-card--featured { color:var(--ink)'), 'Reference hero alignment or light featured card styling is missing.');
        $assert(str_contains($css, 'grid-template-columns:1fr'), 'Pricing cards do not collapse to one column on mobile.');
        $assert(str_contains($css, 'right:0 !important; left:0 !important; width:max-content'), 'Featured pricing badge is not centered independently of legacy offsets.');
        $assert(str_contains($css, '.cenik-other h2') && str_contains($css, 'text-align:center !important'), 'Supplementary pricing copy has no explicit centering contract.');
        $assert(str_contains($css, 'grid-column:1 / -1') && str_contains($css, 'justify-self:stretch'), 'Supplementary pricing copy does not span the full pricing grid.');
        $assert(str_contains($css, 'max-width:none !important; margin:64px auto 0 !important'), 'Supplementary pricing copy cannot override the global 860px runtime constraint.');
        $assert(str_contains($runtimeCss, 'body:not(.theme-premium-photo)[data-cms-page-key="cenik"] .cenik-other'), 'Global runtime pricing width constraint still targets Premium Photo.');
        $assert(str_contains($themeCss, '.site-header{') && str_contains($themeCss, 'backdrop-filter:blur(12px);') && str_contains($themeCss, 'border:0;'), 'Premium Photo header still exposes a separator border.');
        $assert(str_contains($themeCss, 'transition:transform .65s,opacity .65s'), 'Burger-to-close transition does not use the required 0.65 second duration.');
        $assert(str_contains($themeCss, '.nav-toggle{display:grid;place-items:center;width:44px;height:44px;padding:0;border:0'), 'Navigation toggle base state is not explicitly borderless.');
        $assert(str_contains($themeCss, 'border:0;border-radius:0;'), 'Open navigation toggle is not explicitly borderless.');

        $portfolioListing = file_get_contents($themeDir . '/views/archetypes/portfolio-listing.phtml');
        $faqView = file_get_contents($themeDir . '/views/archetypes/faq.phtml');
        $assert(is_string($portfolioListing) && str_contains($portfolioListing, 'portfolio · Naše práce'), 'Portfolio hero meta label is incorrect.');
        $assert(is_string($portfolioListing) && !str_contains($portfolioListing, 'databáze Naše práce'), 'Legacy portfolio database label is still present.');
        $assert(is_string($faqView) && str_contains($faqView, 'FAQ · časté dotazy'), 'FAQ hero meta label is incorrect.');
        $assert(is_string($faqView) && !str_contains($faqView, 'odpovědi ze systému'), 'Legacy FAQ system-answer label is still present.');
        $assert(str_contains($seeder, 'vše ze Standardního balíčku') && str_contains($migration, 'vše ze Standardního balíčku'), 'Balanced Premium package copy is missing from seed or migration paths.');
        $assert(!str_contains($migration, '• online galerie') && !str_contains($migration, '• konzultace před svatbou'), 'Premium migration still duplicates Standard package features.');
    });

    $test('premium photo homepage suppresses legacy fades and preserves the scroll timeline axis', function () use ($assert, $themeDir): void {
        $css = (string)file_get_contents($themeDir . '/assets/cms.css');
        $js = (string)file_get_contents($themeDir . '/assets/theme.js');

        $assert(str_contains($css, '.premium-photo-home .section::before'), 'Homepage fade reset selector is missing.');
        $assert(str_contains($css, 'content: none !important'), 'Legacy homepage fade is not disabled.');
        $assert(str_contains($css, '.premium-photo-home #nase-prace'), 'Portfolio dark-background override is missing.');
        $assert(str_contains($css, '#proces.timeline .tl-rail'), 'Timeline rail styling is missing.');
        $assert(str_contains($css, '#proces.timeline .tl-playhead'), 'Timeline playhead styling is missing.');
        $assert(preg_match('/#proces\.timeline \.tl-rail\s*\{[^}]*position:\s*absolute\s*!important;[^}]*top:\s*var\(--timeline-track-start,\s*0px\)\s*!important;[^}]*height:\s*var\(--timeline-track-height,\s*0px\)\s*!important;/s', $css) === 1, 'Timeline rail must begin at the playhead track start and match the measured list height.');
        $assert(preg_match('/#proces\.timeline \.tl-playhead\s*\{[^}]*position:\s*absolute\s*!important;/s', $css) === 1, 'Timeline playhead must be absolutely positioned against the timeline.');
        $assert(str_contains($css, 'top: var(--timeline-track-start, 0px)'), 'Timeline progress fill must begin at the dynamic list boundary below the heading.');
        $assert(str_contains($css, '.tl-playhead.is-visible'), 'Timeline playhead needs an explicit visible state.');
        $assert(preg_match('/#proces\.timeline \.tl-playhead\s*\{[^}]*opacity:\s*0\s*!important;[^}]*visibility:\s*hidden\s*!important;/s', $css) === 1, 'Timeline playhead must be hidden in its zero-progress default state.');
        $assert(str_contains($js, 'tl.style.setProperty("--timeline-track-start", trackStart.toFixed(2) + "px")'), 'Timeline rail fill start is not connected to the list geometry.');
        $assert(str_contains($js, 'tl.style.setProperty("--timeline-track-height", trackHeight.toFixed(2) + "px")'), 'Timeline grey rail height is not connected to the list geometry.');
        $assert(str_contains($js, 'tl.style.setProperty("--timeline-progress-height", progressHeight.toFixed(2) + "px")'), 'Timeline rail fill is not connected to scroll progress.');
        $assert(str_contains($js, 'playhead.classList.toggle("is-visible", progress > 0)'), 'Timeline playhead does not hide at zero progress.');
    });

    $test('premium photo keeps route context and hero copy inside themed bands', function () use ($assert, $themeDir): void {
        $js = (string)file_get_contents($themeDir . '/assets/theme.js');
        $css = (string)file_get_contents($themeDir . '/assets/theme.css');
        $cmsCss = (string)file_get_contents($themeDir . '/assets/cms.css');
        $assert(str_contains($js, 'link.setAttribute("aria-current", "page")'), 'Active route is not reflected in the primary navigation.');
        $assert(str_contains($js, 'item.link.setAttribute("aria-current", "location")'), 'Homepage anchor navigation has no viewport-driven active state.');
        $assert(str_contains($js, 'window.addEventListener("scroll", requestHomepageNavigationUpdate'), 'Homepage active navigation is not connected to scrolling.');
        $assert(str_contains($css, '.site-nav a[aria-current]:not(.nav-cta)::before'), 'Active navigation state has no visible marker or fails to exclude Contact.');
        $assert(str_contains($css, '.site-nav .nav-cta::before{content:none !important;display:none !important}'), 'Contact navigation CTA can still receive an underline.');
        $assert(str_contains($cmsCss, '.nav-links a.nav-cta { padding:9px 30px; }'), 'Desktop Contact navigation CTA can lose its readable horizontal padding.');
        $assert(str_contains($css, 'grid-template-columns:1fr'), 'Mobile navigation is not a vertical single-column list.');
        $assert(!str_contains($js, 'pricing.hidden = true'), 'Homepage pricing is still hidden by the theme.');
        $assert(!str_contains($js, 'faq.hidden = true'), 'Homepage FAQ is still hidden by the theme.');
        $assert(str_contains($js, 'contactGrid.appendChild(faqContent)'), 'Homepage FAQ preview is not composed next to the contact form.');
        $assert(str_contains($cmsCss, 'grid-template-columns: minmax(0, 1fr) minmax(0, 1fr)'), 'Homepage FAQ and contact form do not use a desktop two-column grid.');
        $assert(str_contains($js, 'premium-review-controls'), 'Desktop review carousel controls are missing.');
        $assert(str_contains($js, 'aria-label", "Předchozí recenze'), 'Review carousel previous control is not accessible.');
        $assert(str_contains($js, 'aria-label", "Další recenze'), 'Review carousel next control is not accessible.');
        $assert(str_contains($cmsCss, '@media (max-width: 959px)') && str_contains($cmsCss, '.premium-review-controls { display: none; }'), 'Review controls are not hidden on mobile.');

        $reviewPartial = (string)file_get_contents($themeDir . '/views/partials/home-reviews.phtml');
        $assert(!str_contains($reviewPartial, 'array_slice($items, 0, 3)'), 'Premium Photo still discards reviews required by the desktop carousel.');

        foreach (['kontakt.phtml', 'blog-listing.phtml', 'portfolio-listing.phtml', 'blog-detail.phtml', 'portfolio-detail.phtml', 'sluzby.phtml', 'faq.phtml'] as $view) {
            $markup = (string)file_get_contents($themeDir . '/views/archetypes/' . $view);
            $assert(str_contains($markup, 'page-head--hero'), 'Hero copy is outside the image band in ' . $view);
        }

        $blogDetail = (string)file_get_contents($themeDir . '/views/archetypes/blog-detail.phtml');
        $assert(str_contains($blogDetail, '$heroImage') && str_contains($blogDetail, 'class="hero-bg"'), 'Blog detail does not render its primary media in the hero.');
        $assert(str_contains($blogDetail, '$articleGallery') && !str_contains($blogDetail, 'premium-article-cover'), 'Blog primary media can still be repeated below the hero.');

        $portfolioDetail = (string)file_get_contents($themeDir . '/views/archetypes/portfolio-detail.phtml');
        $assert(str_contains($portfolioDetail, '$gallery[0][\'full\']') && str_contains($portfolioDetail, '$articleGallery = array_slice($gallery, 1)'), 'Portfolio detail does not consume the first gallery image as its non-repeated hero.');
        $assert(str_contains($css, 'font-weight:300'), 'Premium Photo navigation does not neutralize route-specific bold font rules.');

        foreach (['faq.phtml', 'blog-listing.phtml', 'blog-detail.phtml', 'portfolio-detail.phtml', 'sluzby.phtml'] as $view) {
            $markup = (string)file_get_contents($themeDir . '/views/archetypes/' . $view);
            $assert(str_contains($markup, 'premium-contact-cta'), 'Contact CTA is missing in ' . $view);
            $assert(!str_contains($markup, 'Vše si vysvětlíme a domluvíme se.'), 'Redundant CTA heading remains in ' . $view);
        }
        $assert(!str_contains($js, 'Vše si vysvětlíme a domluvíme se.'), 'Redundant CTA heading remains in the generated service-detail CTA.');
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

        foreach ([
            'partials/home-portfolio.phtml',
            'partials/home-blog-card.phtml',
            'archetypes/portfolio-listing.phtml',
            'archetypes/blog-listing.phtml',
        ] as $mediaView) {
            $markup = (string)file_get_contents($themeDir . '/views/' . $mediaView);
            $assert(str_contains($markup, 'premium-media--film'), 'Film media contract is missing in ' . $mediaView);
            $assert(!str_contains($markup, 'background-image:url'), 'Article thumbnail is duplicated as CSS background in ' . $mediaView);
        }
        $cmsCss = (string)file_get_contents($themeDir . '/assets/cms.css');
        $assert(str_contains($cmsCss, '.premium-media--film > img'), 'Film thumbnail image rule is missing.');
        $assert(str_contains($cmsCss, 'height: calc(100% - 40px)'), 'Film thumbnail does not reserve equal top and bottom rails.');
        $assert(str_contains($cmsCss, 'background: #07080a'), 'Film thumbnail rails do not use the dark film canvas.');
        $assert(str_contains($cmsCss, '.bp-hero + .premium-article') && str_contains($cmsCss, '.pf-detail-hero + .premium-article'), 'Blog and portfolio detail media do not keep spacing below the hero.');

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
