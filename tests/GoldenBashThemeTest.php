<?php

declare(strict_types=1);

use App\Services\ThemeTemplateRegistry;
use App\Services\HomepageContentService;

function register_golden_bash_theme_tests(callable $test, callable $assert): void
{
    $test('legacy homepage video wedding label is removed from rendered markup', function () use ($assert): void {
        $html = '<div class="hp-services-grid"><p class="hp-video-wedding-tag" data-cms-video-wedding-tag>Video svatba</p><article>Service</article></div>';
        $clean = \App\Services\Render\HomepageSectionRenderer::removeLegacyVideoWeddingTag($html);

        $assert(!str_contains($clean, 'Video svatba') && !str_contains($clean, 'hp-video-wedding-tag'), 'Legacy homepage video wedding label must be removed from existing HTML.');
        $assert(str_contains($clean, '<article>Service</article>'), 'Removing the legacy label must preserve the service cards.');
    });

    $root = dirname(__DIR__);
    $themeDir = $root . '/Templates/golden-bash';
    $diagonalThemeDir = $root . '/Templates/golden-bash-diagonal';

    $test('golden bash diagonal is a complete isolated copy with only identity and hero geometry changes', function () use ($assert, $themeDir, $diagonalThemeDir, $root): void {
        $theme = (new ThemeTemplateRegistry($root . '/Templates'))->find('golden-bash-diagonal');
        $assert($theme !== null, 'Golden Bash Diagonal is not discoverable by the theme registry.');
        $assert(($theme['name'] ?? '') === 'Golden Bash Diagonal', 'Golden Bash Diagonal exposes an unexpected display name.');
        $assert(ThemeTemplateRegistry::isGoldenBashFamily('golden-bash'), 'The source theme left its family contract.');
        $assert(ThemeTemplateRegistry::isGoldenBashFamily('golden-bash-diagonal'), 'The diagonal copy is not in the Golden Bash family contract.');
        $assert(!ThemeTemplateRegistry::isGoldenBashFamily('elegant-white'), 'An unrelated theme entered the Golden Bash family contract.');

        $relativeFiles = static function (string $directory): array {
            $files = [];
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
                }
            }
            sort($files);
            return $files;
        };
        $sourceFiles = $relativeFiles($themeDir);
        $diagonalFiles = $relativeFiles($diagonalThemeDir);
        $assert($sourceFiles === $diagonalFiles, 'Golden Bash Diagonal is not a complete file-for-file template copy.');

        $allowedChanges = ['template.json', 'layout.html', 'assets/theme.css', 'assets/theme.js'];
        foreach (array_diff($sourceFiles, $allowedChanges) as $relativePath) {
            $assert(
                hash_file('sha256', $themeDir . '/' . $relativePath) === hash_file('sha256', $diagonalThemeDir . '/' . $relativePath),
                'Unexpected copied-template difference outside identity/hero files: ' . $relativePath
            );
        }

        $sourceLayout = (string)file_get_contents($themeDir . '/layout.html');
        $diagonalLayout = (string)file_get_contents($diagonalThemeDir . '/layout.html');
        $assert(str_replace('<body class="theme-golden-bash">', '<body>', $diagonalLayout) === $sourceLayout, 'Diagonal layout differs beyond the Golden Bash compatibility class.');

        $sourceCss = (string)file_get_contents($themeDir . '/assets/theme.css');
        $diagonalCss = (string)file_get_contents($diagonalThemeDir . '/assets/theme.css');
        $normalizedCss = preg_replace(
            '~\R/\* Golden Bash Diagonal:.*?/\* End Golden Bash Diagonal hero override\. \*/\R~s',
            '',
            $diagonalCss
        );
        $normalizedCss = str_replace('/assets/golden-bash-diagonal/', '/assets/golden-bash/', (string)$normalizedCss);
        $assert($normalizedCss === $sourceCss, 'Diagonal CSS differs beyond its asset identity and bounded hero override.');
        // The diagonal cut was replaced by a left-to-right fade at the client's request,
        // so the hero media is full width and masked rather than clipped.
        $assert(str_contains($diagonalCss, 'width:100%!important;')
            && str_contains($diagonalCss, 'mask-image:linear-gradient(to right,transparent')
            && !str_contains($diagonalCss, 'clip-path:url(#gb-hero-wave-clip)'), 'Diagonal theme must expose full-width hero media faded in from the left, not a clipped diagonal.');

        $sourceJsLines = preg_split('~\R~', (string)file_get_contents($themeDir . '/assets/theme.js')) ?: [];
        $diagonalJsLines = preg_split('~\R~', (string)file_get_contents($diagonalThemeDir . '/assets/theme.js')) ?: [];
        $assert(count($sourceJsLines) === count($diagonalJsLines), 'Diagonal theme JavaScript changed line structure outside the hero geometry replacement.');
        foreach ($sourceJsLines as $index => $sourceLine) {
            $diagonalLine = $diagonalJsLines[$index] ?? '';
            if (str_contains($sourceLine, "hero.insertAdjacentHTML('afterbegin'")) {
                // The gold outline that traced the diagonal is gone with the diagonal
                // itself; the clip path definition is what still separates the themes.
                $assert(str_contains($diagonalLine, 'M .27 0 H 1 V 1 H .55 Z'), 'Diagonal hero must keep its own clip path definition.');
                continue;
            }
            $assert($sourceLine === $diagonalLine, 'Diagonal JavaScript differs outside the hero geometry line ' . ($index + 1) . '.');
        }

        foreach (['welcome-desktop.webp', 'welcome-mobile.webp'] as $asset) {
            $assert(
                hash_file('sha256', $root . '/public/assets/golden-bash/' . $asset) === hash_file('sha256', $root . '/public/assets/golden-bash-diagonal/' . $asset),
                'Diagonal public asset copy differs: ' . $asset
            );
        }
    });

    $test('golden bash homepage hero video stays pinned to the right edge from 701 px up', function () use ($assert, $themeDir, $diagonalThemeDir): void {
        // Vimeo background player letterboxes (contains + centers) the video inside the iframe and
        // the desktop video is 3:2, not 16:9. A 16:9 iframe as tall as the hero therefore left a
        // transparent strip on the right at browser zoom (low viewport), where the poster showed.
        $rule = '@media(min-width:701px){' . "\n"
            . '  body.theme-golden-bash .hp-hero>.theme-hero-video iframe{width:calc(var(--gb-hero-h) * 16 / 9)!important;max-width:none!important;height:calc(var(--gb-hero-h) * 16 / 9)!important;min-height:0!important;aspect-ratio:auto}' . "\n"
            . '  body.theme-golden-bash .hp-hero>.theme-hero-video .theme-hero-video__poster{width:calc(var(--gb-hero-h) * 16 / 9)!important;max-width:none!important}' . "\n"
            . '}';
        foreach ([$themeDir, $diagonalThemeDir] as $dir) {
            $css = str_replace("\r\n", "\n", (string)file_get_contents($dir . '/assets/theme.css'));
            $base = strpos($css, 'body.theme-golden-bash .hp-hero>.theme-hero-video iframe{position:absolute!important;top:50%!important;right:0!important;');
            $override = strpos($css, $rule);
            $mobile = strpos($css, 'body.theme-golden-bash .hp-hero>.theme-hero-video iframe{top:0!important;right:auto!important;');
            $assert($base !== false && $override !== false && $mobile !== false, basename($dir) . ' must keep the right-anchored hero iframe, its 701px+ square sizing override and the mobile full-bleed rule.');
            $assert($base < $override && $override < $mobile, basename($dir) . ' hero iframe override must come after the base rule and before the mobile rule.');
            // min-height must stay explicit: without it frontend-runtime.css falls back to min-height:100vh!important.
            $assert(!str_contains(substr($css, $override, strlen($rule)), 'min(100%'), basename($dir) . ' hero video must not shrink to the hero width; a narrower hero crops the frame on the left.');
        }
    });

    $test('golden bash theme registers a complete isolated design contract', function () use ($assert, $themeDir, $root): void {
        $theme = (new ThemeTemplateRegistry($root . '/Templates'))->find('golden-bash');
        $assert($theme !== null, 'Golden Bash is not discoverable by the theme registry.');
        $assert(($theme['name'] ?? '') === 'Golden Bash', 'Golden Bash exposes an unexpected display name.');
        $assert(($theme['layout_html'] ?? '') === 'layout.html', 'Golden Bash layout contract is missing.');
        $assert(($theme['layout_css'] ?? '') === 'assets/theme.css', 'Golden Bash CSS contract is missing.');
        $assert(($theme['layout_js'] ?? '') === 'assets/theme.js', 'Golden Bash JavaScript contract is missing.');

        foreach (['layout.html', 'assets/theme.css', 'assets/theme.js', 'reference/golden-bash-target-1440.png', 'reference/hero-wave-layer.png'] as $relativePath) {
            $assert(is_file($themeDir . '/' . $relativePath), 'Golden Bash is missing ' . $relativePath);
        }
        foreach (['views/archetypes/blog-listing.phtml', 'views/archetypes/blog-detail.phtml', 'views/archetypes/cenik.phtml', 'views/archetypes/faq.phtml', 'views/archetypes/sluzby.phtml', 'views/archetypes/portfolio-listing.phtml', 'views/archetypes/portfolio-detail.phtml', 'views/pages/o-nas.phtml', 'views/pages/podminky-uziti.phtml', 'views/partials/footer.phtml'] as $relativePath) {
            $assert(is_file($themeDir . '/' . $relativePath), 'Golden Bash is missing themed inner-page view ' . $relativePath);
        }

        $layout = (string) file_get_contents($themeDir . '/layout.html');
        $normalizer = (string) file_get_contents(dirname(__DIR__) . '/app/Services/Render/MarkupNormalizer.php');
        $css = (string) file_get_contents($themeDir . '/assets/theme.css');
        $assert(
            str_contains($css, 'body.theme-golden-bash[data-cms-page-key="hodnoceni"] .reviews-page-card p')
            && str_contains($css, 'color:#5f554b!important')
            && str_contains($css, 'grid-template-columns:minmax(0,1fr)!important'),
            'Golden Bash reviews route must keep mobile cards single-column with readable dark copy.'
        );
        $js = (string) file_get_contents($themeDir . '/assets/theme.js');
        $contactCss = (string) file_get_contents($root . '/app/Views/assets/kontakt.css');
        $contactPage = (string) file_get_contents($root . '/app/Views/archetypes/kontakt.phtml');
        $portfolioDetailCss = (string) file_get_contents($root . '/app/Views/assets/portfolio-detail.css');
        $portfolioPublisher = (string) file_get_contents($root . '/app/Services/PortfolioPublishService.php');
        $homepagePortfolio = (string) file_get_contents($root . '/app/Views/partials/home-portfolio.phtml');
        $homepageContentService = (string) file_get_contents($root . '/app/Services/HomepageContentService.php');
        $homepageCta = (string) file_get_contents($root . '/app/Views/partials/home-section-cta.phtml');
        $portfolioListing = (string) file_get_contents($root . '/app/Views/archetypes/portfolio-listing.phtml');
        $portfolioListingData = (string) file_get_contents($root . '/app/ViewModels/PortfolioListingData.php');
        $blogListing = (string) file_get_contents($themeDir . '/views/archetypes/blog-listing.phtml');
        $blogDetail = (string) file_get_contents($themeDir . '/views/archetypes/blog-detail.phtml');
        $pricingPage = (string) file_get_contents($themeDir . '/views/archetypes/cenik.phtml');
        $faqPage = (string) file_get_contents($themeDir . '/views/archetypes/faq.phtml');
        $servicesPage = (string) file_get_contents($themeDir . '/views/archetypes/sluzby.phtml');
        $themedPortfolioListing = (string) file_get_contents($themeDir . '/views/archetypes/portfolio-listing.phtml');
        $themedPortfolioDetail = (string) file_get_contents($themeDir . '/views/archetypes/portfolio-detail.phtml');
        $footer = (string) file_get_contents($themeDir . '/views/partials/footer.phtml');
        $aboutPage = (string) file_get_contents($themeDir . '/views/pages/o-nas.phtml');
        $aboutStoriesData = (string) file_get_contents($root . '/app/ViewModels/AboutStoriesData.php');
        $termsPage = (string) file_get_contents($themeDir . '/views/pages/podminky-uziti.phtml');
        $frontController = (string) file_get_contents($root . '/public/index.php');
        $publishService = (string) file_get_contents($root . '/app/Services/PublishService.php');
        $footerRenderer = (string) file_get_contents($root . '/app/Services/Render/FooterRenderer.php');
        $pageComposer = (string) file_get_contents($root . '/app/Services/Render/PageComposer.php');
        $cenikData = (string) file_get_contents($root . '/app/ViewModels/CenikPageData.php');
        $demoSeeder = (string) file_get_contents($root . '/app/Services/DemoContentSeeder.php');
        $setupController = (string) file_get_contents($root . '/app/Controllers/SetupController.php');
        $assert(str_contains($layout, '{site_name}') && str_contains($layout, '{nav}') && str_contains($layout, '{page_content}'), 'Golden Bash layout bypasses CMS placeholders.');
        $assert(str_contains($termsPage, '$fragment') && str_contains($termsPage, 'gb-legal-toc') && str_contains($termsPage, 'data-legal-number'), 'Golden Bash terms page must preserve DB content and add an accessible anchored overview.');
        $assert(str_contains($css, '.gb-legal-page .legal-card h3') && str_contains($css, 'color:var(--gb-ink)!important') && str_contains($css, '.gb-legal-toc'), 'Golden Bash terms content must remain readable on the light theme.');
        $assert(str_contains($css, '.gb-legal-toc{position:absolute;z-index:2;top:680px') && !str_contains($css, '.gb-legal-toc{position:absolute;z-index:2;top:560px'), 'Golden Bash legal navigation must sit beside the legal cards without overlapping the introduction or adding a flow row.');
        // The wave clip is gone with the diagonal; what the hero must still have is the
        // fade that replaced it, and the five-service strip is unrelated to either.
        $assert(str_contains($css, 'grid-template-columns:repeat(5,1fr)')
            && str_contains($css, 'mask-image:linear-gradient(to right,transparent'), 'Golden Bash misses the five-service strip or the hero fade.');
        $assert(str_contains($css, '@media(max-width:700px)') && str_contains($css, 'gb-nav-open'), 'Golden Bash responsive contract is incomplete.');
        $assert(str_contains($css, '.nav-toggle-input') && str_contains($css, '.nav-toggle-label'), 'Golden Bash leaks the CMS checkbox navigation controls.');
        $assert(str_contains($css, '.gb-service-icon svg') && str_contains($css, 'background-clip:text'), 'Golden Bash misses service icons or gradient hero typography.');
        $assert(str_contains($css, 'a.gb-button') && str_contains($css, 'outline:1px solid #b77a20'), 'Golden Bash CTA styling is not protected from shared runtime CSS.');
        $assert(str_contains($css, '.gb-header-cta:hover') && str_contains($css, '.gb-contact button[type="submit"]:hover'), 'Golden Bash does not provide the shared gradient hover for all CTA surfaces.');
        $assert(str_contains($css, '.gb-pricing>.container') && str_contains($css, '.gb-price-button') && str_contains($css, '.hp-cenik-card-badge'), 'Golden Bash pricing composition is incomplete.');
        $assert(str_contains($css, '.gb-pricing .hp-cenik-card-features') && str_contains($css, 'content:"✓"'), 'Golden Bash homepage pricing must present package descriptions as readable feature bullets.');
        $assert(str_contains($js, "packageQueryKey = 'balicek'") && str_contains($js, 'card.dataset.packageId') && str_contains($js, 'params.set(packageQueryKey, selectedPackage.id)'), 'Golden Bash package selection must transport only the stable package identifier.');
        $assert(str_contains($js, "card.classList.toggle('is-selected', selected)") && str_contains($js, 'homepageContact.scrollIntoView') && str_contains($css, '.hp-cenik-card.is-selected'), 'Homepage package selection must visibly mark one card and move the user to contact.');
        $assert(str_contains($js, "packageInput.name = 'selected_package_id'") && !str_contains($js, 'selected_package_price') && str_contains($css, '.gb-selected-package__value'), 'Selected packages must appear in a styled contact summary while submitting only their identifier.');
        $assert(str_contains($frontController, "\$_POST['selected_package_id']") && str_contains($frontController, 'PricingPackageCatalog::resolve') && !str_contains($frontController, "\$_POST['selected_package']"), 'Contact delivery must resolve the submitted package identifier through the server allowlist.');
        $assert(str_contains($js, "document.querySelector('#nase-prace')") && str_contains($js, "document.querySelector('#cenik')"), 'Golden Bash does not compose DB-owned homepage sections.');
        $assert(str_contains($normalizer, 'Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,300,0,0')
            && str_contains($normalizer, 'icon_names=assignment,call,check_circle,description,event,event_available,expand_more,location_on,mail,photo_camera,play_circle,receipt_long,schedule,sell,verified_user,warning')
            && str_contains($normalizer, "str_contains(\$html, 'material-symbols-outlined')")
            && !str_contains($layout, 'Material+Symbols'), 'The bounded Material Symbols subset must be requested per page and only where the icons are used, never as a render-blocking link in every layout.');
        $assert(str_contains($js, "icon.className = 'gb-service-icon'"), 'Golden Bash must preserve the stable service icon hook.');
        foreach (['Fotoaparát a videokamera', 'Filmový klip', 'Zásnubní prsten', 'Oslava', 'Firemní natáčení', 'Dron'] as $serviceIcon) {
            $assert(str_contains($js, 'aria-label="' . $serviceIcon . '"'), 'Golden Bash is missing semantic SVG icon ' . $serviceIcon . '.');
        }
        $assert(str_contains($js, 'clipPathUnits="objectBoundingBox"') && !str_contains($css, 'clip-path:polygon'), 'Golden Bash hero must use one scalable smooth curve instead of polygon teeth.');
        // The gold outline traced the diagonal edge. With the edge replaced by a fade it
        // had nothing left to trace, so it is no longer injected at all.
        $assert(!str_contains($js, 'gb-hero-wave-outline') && !str_contains($js, 'id="gb-hero-wave-gold"'), 'The gold hero outline must not be injected once the diagonal edge is gone.');
        $assert(str_contains($css, '.gb-hero-wave-glow') && str_contains($css, 'stroke:url(#gb-hero-wave-gold)'), 'Golden Bash hero wave border does not expose the glow and gradient stroke.');
        $assert(str_contains($css, '.gb-portfolio .pt-grid') && str_contains($css, '.gb-reviews .hp-reviews-grid'), 'Golden Bash does not support the canonical generic portfolio and review markup.');
        $assert(str_contains($css, '.gb-contact .gb-contact-grid') && str_contains($css, '.gb-footer .site-footer-rich'), 'Golden Bash does not support the canonical generic contact and footer markup.');
        $assert(str_contains($js, "button.classList.add('gb-button')"), 'Golden Bash does not mark CMS actions for theme-safe CTA styling.');
        $assert(str_contains($js, "pricingTitle.innerHTML = 'Vyberte si balíček") && str_contains($js, "action.textContent = 'Vybrat balíček'"), 'Golden Bash does not compose the requested pricing presentation.');
        $assert(str_contains($js, 'faqContainer.firstChild') && str_contains($js, 'contactContainer.appendChild(panel)'), 'Golden Bash does not integrate FAQ into the contact band.');
        $assert(str_contains($js, "question.setAttribute('aria-expanded'") && str_contains($js, "answer.style.height = answer.scrollHeight + 'px'") && str_contains($css, 'height .38s cubic-bezier(.22,.61,.36,1)'), 'Golden Bash homepage FAQ must expose the smooth accessible accordion contract used by the contact route.');
        $assert(str_contains($js, "reviewsGrid.parentElement.classList.add('gb-review-list')") && str_contains($js, "contactGrid.classList.add('gb-contact-grid')"), 'Golden Bash does not adapt active DB-first homepage wrappers.');
        $assert(str_contains($js, 'gb-mobile-contact') && str_contains($css, '.gb-nav-open .gb-menu span:first-child'), 'Golden Bash mobile navigation is missing its contact action or open-state animation.');
        $assert(str_contains($css, '.gb-blog .hp-blog-title a{color:var(--gb-ink)!important}'), 'Golden Bash blog title links must stay dark on light cards.');
        $assert(str_contains($css, '.gb-blog{height:450px') && str_contains($css, '.gb-blog .hp-blog-card{height:360px') && str_contains($css, '.gb-contact>.container{height:620px'), 'Golden Bash desktop sections must leave enough vertical room for complete blog copy and the fixed-height contact composition.');
        $assert(str_contains($css, '.gb-reviews{height:640px') && str_contains($css, '.gb-reviews .hp-review-card:nth-child(n+7){display:none!important}') && str_contains($css, '.gb-reviews .hp-review-card:nth-child(n+4){border-top:1px solid'), 'Golden Bash homepage reviews must show exactly two rows of three DB reviews.');
        $assert(str_contains($homepageContentService, "['reviews_url']") && str_contains($homepageContentService, "'Recenze na Google'"), 'Homepage reviews CTA must use the canonical contact-info Google reviews URL.');
        $assert(str_contains($homepageCta, 'hp-section-cta__google') && str_contains($homepageCta, 'noopener noreferrer external'), 'Homepage Google reviews CTA must open safely as an accessible external link.');
        $assert(str_contains($css, '.gb-reviews .hp-section-cta--multiple') && str_contains($css, 'grid-template-columns:repeat(2,minmax(0,1fr))'), 'Golden Bash must present both review CTAs on desktop and mobile.');
        $assert(str_contains($js, "benefits.className = 'gb-contact-benefits'") && str_contains($js, '<svg viewBox="0 0 24 24">') && str_contains($js, "'Osobn\u00ed p\u0159\u00edstup'") && str_contains($css, '.gb-contact .gb-contact-benefits{display:grid;grid-column:2;grid-template-columns:repeat(3'), 'Golden Bash contact form must expose the three-column reassurance strip with Google Material SVG icons.');
        $assert(str_contains($css, '.gb-contact:has(.hp-faq-item.is-open)>.container{height:620px;min-height:620px;overflow:hidden}') && str_contains($css, '.gb-contact-faq .hp-faq{flex:1;min-height:0;align-content:start;grid-auto-rows:max-content;gap:10px;overflow-x:hidden;overflow-y:auto'), 'Opening a Golden Bash FAQ answer must not increase the desktop contact section height or stretch closed rows.');
        $assert(str_contains($css, '.gb-contact-faq .hp-faq-item{padding:0!important;border:1px solid #e7dccd;border-radius:5px;background:#fff;overflow:hidden}') && str_contains($css, '.gb-contact-faq .hp-faq-question{display:flex;min-height:52px;'), 'Golden Bash homepage FAQ must use the same compact bordered-card rhythm as the contact page FAQ.');
        $assert(str_contains($css, '.gb-contact-faq .hp-faq::-webkit-scrollbar{display:none') && str_contains($css, '.gb-contact-faq>.btn-secondary,.gb-contact-faq>.gb-button{flex:0 0 auto;align-self:flex-start;margin-top:18px!important}'), 'Golden Bash FAQ must hide its internal scrollbar and keep the all-questions CTA anchored below the flexible question list.');
        $assert(str_contains($contactCss, '.kt-form-block .kt-consent{display:flex!important;align-items:center!important') && str_contains($contactCss, '.kt-form-block .kt-consent:focus-within') && str_contains($contactCss, 'flex:0 0 22px!important') && str_contains($contactCss, '.kt-form-block .kt-consent input:checked::after'), 'The contact route consent must provide a large clickable panel with visible focus and checked states.');
        $assert(str_contains($normalizer, 'family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,300,0,0')
            && str_contains($js, "document.querySelector('.gb-main>.kt-hero, .gb-main>.gb-about-hero') !== null")
            && str_contains($js, 'if (!preserveGoogleMaterialSymbols) renderSystemSymbol(node);')
            && str_contains($css, 'body.theme-golden-bash:has(.gb-main>.kt-hero) .material-symbols-outlined,')
            && str_contains($css, 'body.theme-golden-bash:has(.gb-main>.gb-about-hero) .material-symbols-outlined{')
            && str_contains($css, 'font-family:"Material Symbols Outlined"!important'), 'The Contact and About routes must preserve and render official Google Material Symbols instead of Unicode fallbacks.');
        $contactStatus = (string) file_get_contents($root . '/app/Views/partials/contact-status.phtml');
        foreach (['call', 'mail', 'location_on', 'receipt_long', 'schedule', 'expand_more'] as $contactSymbol) {
            $assert(str_contains($contactPage, '>' . $contactSymbol . '</span>'), 'Contact page is missing Google Material Symbol: ' . $contactSymbol);
        }
        $assert(str_contains($contactPage, "partials/contact-status") && str_contains($contactStatus, 'check_circle</span>') && str_contains($contactStatus, 'warning</span>'), 'Shared status partial must keep official status icons.');
        $assert(!str_contains($contactPage, '&#9888;') && !str_contains($contactPage, '&#10003;') && !str_contains($contactPage, 'class="kt-faq-plus">+</span>')
            && str_contains($contactCss, '.kt-faq-item.is-open .kt-faq-plus{transform:rotate(180deg)}'), 'Contact page must not retain emoji status glyphs or text plus icons.');
        $assert(str_contains($css, '.gb-contact .gb-contact-intro>.section-label,.gb-contact .gb-contact-form::before,.gb-contact-faq>.section-label{margin-top:0!important;color:var(--gb-gold)!important'), 'Golden Bash contact column labels must share one gold top-left visual contract.');
        $assert(str_contains($css, 'body.theme-golden-bash:has(.gb-main>.kt-hero) .kt-steps{display:grid!important;grid-template-columns:repeat(4') && str_contains($css, 'html body.theme-golden-bash:has(.gb-main>.kt-hero) .kt-form-block .kt-form-btn') && str_contains($css, '.kt-faq>.container::before{content:"Časté dotazy"'), 'Golden Bash contact route must expose the four-card process, homepage-style submit button, and light editorial FAQ composition.');
        $assert(!str_contains($css, 'body.theme-golden-bash[data-cms-page-key="kontakt"]'), 'Golden Bash contact route must not depend on a body page-key attribute that the runtime does not emit.');
        $assert(str_contains($css, '.gb-blog .hp-blog-body>.hp-blog-link{align-self:flex-start;min-width:0!important;min-height:0!important;margin-top:auto!important') && str_contains($css, 'background:none!important'), 'Golden Bash blog actions must be bottom-anchored text links rather than buttons.');
        $assert(!str_contains($js, ".btn-ghost, .hp-blog-link"), 'Golden Bash must not apply the shared button marker to blog text links.');
        $assert(str_contains($js, 'accentTitleTail(reviewsTitle, 2)') && str_contains($js, "accentTitleTail(blog.querySelector('.section-title'), 1)"), 'Golden Bash reviews and blog headings must share the accented title treatment.');
        $assert(str_contains($css, '.gb-reviews>.container>.section-intro,.gb-blog>.container>.section-intro{display:block') && str_contains($css, '.gb-reviews>.container>div:last-child,.gb-blog>.container>div:last-child{position:absolute;bottom:0;left:0'), 'Golden Bash reviews and blog must expose matching descriptions and bottom-aligned section CTAs.');
        $assert(str_contains($js, 'gb-work-lightbox') && str_contains($js, "event.key === 'ArrowLeft'") && str_contains($js, "event.key === 'ArrowRight'"), 'Golden Bash work cards must expose a keyboard-controlled modal gallery.');
        $assert(str_contains($js, 'initPortfolioTabs') && str_contains($js, 'history.replaceState') && str_contains($js, "event.key === 'Home'") && str_contains($js, "event.key === 'End'"), 'Golden Bash portfolio tabs must support animated switching, URL state, and keyboard navigation.');
        $assert(str_contains($themedPortfolioListing, 'data-portfolio-filter="all"') && str_contains($themedPortfolioListing, 'data-portfolio-filter="photo"') && str_contains($themedPortfolioListing, 'data-portfolio-filter="video"') && str_contains($themedPortfolioListing, 'data-portfolio-grid'), 'Golden Bash portfolio listing must render the single merged grid with its three filter buttons.');
        $assert(str_contains($js, '(nextIndex + galleryItems.length) % galleryItems.length') && str_contains($js, 'event.target === lightbox'), 'Golden Bash work gallery must loop infinitely and close from its backdrop.');
        $assert(str_contains($js, 'if (!home) {') && str_contains($js, "innerHero.classList.add('gb-unified-hero')") && str_contains($js, "    initWorkGallery();\n    initPortfolioTabs();\n    initCenikAutoScroll();\n    return;"), 'Golden Bash must initialize the work gallery, portfolio filter, and cenik auto-scroll after adapting non-homepage hero markup - these live on non-homepage routes (Nase prace, Cenik) that return before the homepage-only tail of the script.');
        $assert(str_contains($js, "services.classList.add('gb-services', 'gb-services-profile')") && str_contains($js, 'servicesContainer.replaceChildren(servicesVisual, servicesCopy)'), 'Golden Bash homepage services must use the portrait-led two-column composition.');
        $assert(str_contains($js, "document.querySelector('.jb-about-visual__source[data-src]')") && str_contains($js, "services.dataset.compositionError = 'missing-required-db-source'"), 'Golden Bash services must require the explicit DB team photograph and visibly flag a missing contract.');
        $assert(str_contains($js, "aboutVisualSource.tagName === 'IMG'") && str_contains($js, "servicesImage.removeAttribute('hidden')"), 'Golden Bash must move the server-rendered team image instead of creating a late duplicate request.');
        $assert(!str_contains($js, "document.querySelectorAll('.gb-main img[loading=\"lazy\"]')"), 'Golden Bash must not force below-fold lazy images to eager loading.');
        $assert(str_contains($js, "link.setAttribute('href', '/#kontakt')") && !str_contains($js, 'aboutVisualSource.dataset.srcSecondary'), 'Every homepage About link must target the contact anchor and the visual must use the approved single team photograph.');
        $assert(str_contains($css, '.gb-services-visual{position:relative;align-self:start;width:100%;height:auto;min-height:0;aspect-ratio:10/11') && str_contains($css, '.gb-services-visual>img{position:absolute;right:0;bottom:0;left:0;width:100%;height:auto;min-height:100%') && str_contains($css, 'object-fit:cover;object-position:55% bottom'), 'The approved homepage team photograph must fit the frame width, preserve its bottom edge, and crop from the top.');
        $assert(str_contains($css, '.gb-services-visual::before{content:none}') && !str_contains($css, 'box-shadow:inset 0 0 48px 22px') && str_contains($css, 'border-radius:18px;background:#fffdf9;box-shadow:none') && str_contains($css, 'aspect-ratio:10/11!important;border-radius:18px!important'), 'The homepage team photograph must render with an 18px radius and without a white vignette or outer glow.');
        $assert(str_contains($js, "servicesLink.textContent = 'Více info o nás  →'") && str_contains($js, "servicesLink.setAttribute('href', '/o-nas')") && !str_contains($js, 'Poznat všechny služby'), 'The homepage About section CTA to /o-nas must read "Více info o nás".');
        $assert(str_contains($css, '.gb-services-visual::after{content:none}') && !str_contains($css, '.gb-services-visual figcaption') && !str_contains($js, "createElement('figcaption')") && !str_contains($js, 'Dva pohledy. Jeden příběh.'), 'The homepage team photograph must render clean: no caption text and no dark gradient overlay over the photo.');
        $assert(str_contains($css, '.gb-services-profile>.container{display:grid!important;grid-template-columns:minmax(330px,430px)') && str_contains($css, 'grid-template-columns:repeat(3,minmax(0,1fr))'), 'Golden Bash services must expose the reference desktop portrait and 3x2 service grid.');
        $assert(str_contains($css, '.gb-services::before{content:none!important;display:none!important}'), 'Golden Bash services must not cast the generic homepage section fade over the bottom of the hero.');
        $assert(str_contains($js, "portfolio.classList.add('gb-portfolio', 'gb-portfolio-mosaic')") && !str_contains($js, 'mosaicTiles') && !str_contains($js, 'gb-mosaic-derived'), 'Golden Bash homepage portfolio must keep exactly one card per server-rendered database gallery.');
        $assert(str_contains($js, "var clickedImage = card.querySelector('.pt-thumb img, .bl-thumb img')") && str_contains($js, 'showGalleryItem(initialGalleryIndex)'), 'Golden Bash work gallery must open on the photograph visible in the clicked card.');
        $assert(str_contains($layout, 'class="gb-welcome-layer"') && str_contains($layout, 'class="gb-welcome-brand__mark">RB</strong>') && str_contains($layout, 'data-welcome-progress'), 'Golden Bash layout must render the branded progress screen in the initial HTML response.');
        $assert(str_contains($css, 'body.theme-golden-bash:not([data-cms-page-key="homepage"]) .gb-welcome-layer{display:none!important}') && str_contains($js, "document.body.dataset.cmsPageKey === 'homepage'") && str_contains($js, 'if (welcomeLayer && (!isHomepage || skipWelcome))') && str_contains($js, 'welcomeLayer.remove()'), 'Golden Bash must expose and animate its welcome screen only on desktop homepage.');
        $assert(str_contains($css, '.gb-welcome-layer{--gb-load-progress:0;position:fixed;z-index:20000;inset:0') && str_contains($css, 'url("/assets/golden-bash/welcome-desktop.webp")') && str_contains($css, '.gb-welcome-layer.is-leaving{animation:none!important;opacity:0!important;filter:none!important}'), 'Golden Bash desktop welcome layer must cover the viewport and leave without a flash or blur — the entrance keyframe animation must be cancelled so the exit opacity transition can actually play instead of snapping instantly.');
        $assert(!str_contains($css, 'filter:blur(5px)') && !str_contains($css, 'filter .68s ease'), 'Golden Bash welcome transition must avoid non-composited animated filters.');
        $assert(str_contains($js, 'var welcomeDuration = reduceWelcomeMotion ? 100 : 375') && str_contains($js, 'var welcomeMaximumWait = reduceWelcomeMotion ? 100 : 450') && str_contains($js, "welcomeLayer.dataset.loadTimeout = 'true'") && str_contains($js, 'window.load exceeded 450 ms') && str_contains($js, "welcomeProgress.textContent = value + '%'") && str_contains($js, "welcomeLayer.classList.add('is-leaving')") && str_contains($js, 'welcomeLayer.remove()') && str_contains($js, 'reduceWelcomeMotion ? 20 : 130') && str_contains($js, 'reduceWelcomeMotion ? 1 : 90'), 'Golden Bash welcome layer must use the halved desktop timing, visibly report a bounded load timeout, finish at 100 percent, remove itself from DOM, and respect reduced motion.');
        $assert(str_contains($css, 'opacity:0;visibility:hidden;pointer-events:none;transform:translateY(-12px)') && str_contains($css, '.gb-nav-open .gb-nav{opacity:1;visibility:visible;pointer-events:auto;transform:none') && str_contains($js, "event.key === 'Escape'"), 'Golden Bash mobile navigation must use an animatable fade/slide state and remain keyboard dismissible.');
        $assert(str_contains($css, 'Final mobile navigation/hero polish') && str_contains($css, 'translate(-50%,-50%) rotate(45deg)!important') && str_contains($css, 'translate(-50%,-50%) rotate(-45deg)!important') && str_contains($css, '.hp-hero .gb-hero-benefits{border:0!important}'), 'Golden Bash mobile menu must use a centered symmetric close icon and the homepage benefits panel must not render an outer gold border.');
        $assert(str_contains($css, 'body.theme-golden-bash .gb-shell{')
            && str_contains($css, 'padding-top:72px!important')
            && str_contains($css, 'body.theme-golden-bash .gb-header{')
            && str_contains($css, 'position:fixed!important')
            && str_contains($css, 'top:0!important')
            && str_contains($css, 'z-index:120!important')
            && str_contains($css, 'body.theme-golden-bash .gb-nav{')
            && str_contains($css, 'top:100%!important'), 'Golden Bash must keep the complete mobile header fixed on every route, reserve its height in the shell, and anchor the open menu below it.');
        $assert(str_contains($js, "document.body.classList.toggle('gb-mobile-header-compact', mobileHeaderQuery.matches && window.scrollY > 8)")
            && str_contains($css, 'body.theme-golden-bash.gb-mobile-header-compact .gb-shell{padding-top:42px!important}')
            && str_contains($css, 'body.theme-golden-bash.gb-mobile-header-compact .gb-header{min-height:42px!important}')
            && str_contains($css, 'transition:min-height .28s ease'), 'Golden Bash mobile header must smoothly shrink from 72px to 42px after leaving the top of the page.');
        $assert(str_contains($js, "workAction.className = 'btn-secondary gb-hero-work'")
            && str_contains($css, '.gb-hero-work{display:none!important}'), 'Golden Bash homepage must keep the work CTA available in markup but visually hide it.');
        $assert(str_contains($css, 'body.theme-golden-bash .gb-header>.gb-brand .gb-brand__mark{font-size:31px}') && !str_contains($css, ':has(.hp-hero) .gb-brand__mark{font-size:31px}'), 'Golden Bash must keep the compact mobile header logo identical on homepage and inner routes.');
        $assert(str_contains($js, "scrollTopButton.className = 'gb-scroll-top'") && str_contains($js, "window.scrollY > 320") && str_contains($js, "window.scrollTo({ top: 0, behavior: reduceScrollMotion ? 'auto' : 'smooth' })") && str_contains($css, '@media(min-width:701px){.gb-scroll-top{display:none!important}}') && str_contains($css, '.gb-scroll-top.is-visible{opacity:1;visibility:visible;pointer-events:auto;transform:none'), 'Golden Bash must expose an accessible mobile-only scroll-to-top control with reduced-motion support.');
        $assert(!str_contains($layout, 'rel="preload" as="image" href="/assets/golden-bash/welcome-'), 'Decorative welcome backgrounds must not be preloaded ahead of real page media or social metadata.');
        $assert(is_file(dirname(__DIR__) . '/public/assets/golden-bash/welcome-desktop.webp') && is_file(dirname(__DIR__) . '/public/assets/golden-bash/welcome-mobile.webp'), 'Golden Bash welcome screen background assets must be publicly deployable.');
        $assert(str_contains($css, '.gb-footer-rich .site-footer-social:is(:hover,:focus-visible)') && str_contains($css, 'background:#f6ead5!important;color:#a66b12!important'), 'Golden Bash footer social icons must retain a contrast-safe gold hover and keyboard-focus state.');
        $assert(str_contains($css, 'Final mobile footer contract') && str_contains($css, '.site-footer-contact{') && str_contains($css, 'display:contents!important') && str_contains($css, 'grid-template-columns:repeat(2,minmax(0,1fr))!important'), 'Golden Bash mobile footer must stack readable sections while retaining a two-column quick-link grid.');
        $assert(str_contains($css, '.gb-portfolio-mosaic{height:auto!important;min-height:0!important') && str_contains($css, 'grid-template-rows:repeat(2,201px)') && str_contains($css, 'grid-auto-rows:201px!important') && !str_contains($css, '414px'), 'Golden Bash homepage portfolio must lay out the first five galleries in two desktop rows and keep growing in 201px rows for every batch the load-more control reveals afterwards, not clip them behind a fixed-height box.');
        $assert(str_contains($css, '.gb-work-lightbox__thumbs') && str_contains($css, '.gb-work-lightbox__caption') && str_contains($css, '.gb-work-lightbox__arrow--prev'), 'Golden Bash work gallery is missing its thumbnail rail, image gradient caption, or directional controls.');
        $assert(str_contains($css, '.gb-portfolio-tabs') && str_contains($css, '.gb-portfolio-tabs__divider') && str_contains($css, '.gb-work-listing .pt-card.is-filtering-out') && str_contains($css, '.gb-work-listing .pt-card.is-filtered-out'), 'Golden Bash portfolio tabs need a divider, active presentation, and fast filter animation states.');
        // The client asked for the gallery detail to carry only title, date and the
        // "more info" link, so the description and author are no longer rendered. The data
        // stays on the tile (data-gallery-description / -author) for whenever it returns.
        $assert(!str_contains($js, 'galleryAuthor') && !str_contains($js, 'galleryDescription')
            && !str_contains($js, 'gb-work-lightbox__copy'), 'The gallery detail must not render a description or an author.');
        $assert(str_contains($css, '.gb-work-lightbox__copy') && str_contains($css, '.gb-work-lightbox__caption small'), 'Golden Bash work gallery must give description and author distinct typography.');
        $assert(str_contains($css, 'width:min(1800px,calc(100vw - 48px))') && str_contains($css, 'height:calc(100vh - 32px)') && str_contains($css, '.gb-work-lightbox__stage{position:relative;flex:1 1 auto;min-height:0;overflow:hidden') && str_contains($css, '.gb-work-lightbox__image-button{display:block;width:100%;height:100%;min-height:0;overflow:hidden') && str_contains($css, '.gb-work-lightbox__thumbs{display:flex;flex:0 0 auto'), 'Golden Bash work gallery must clip its desktop image to the available stage and reserve a separate non-shrinking thumbnail row.');
        $assert(str_contains($css, '.gb-work-lightbox{align-items:center;padding:16px') && str_contains($css, 'max-height:calc(100vh - 32px)') && str_contains($css, 'border-radius:18px}') && !str_contains($css, '.gb-work-lightbox__caption{display:none}') && str_contains($css, '.gb-work-lightbox__caption{position:static'), 'Golden Bash mobile work gallery must remain centered with visible radius on all four dialog corners and keep the caption in flow below the picture - it carries the only link to the work detail on phones.');
        $assert(str_contains($js, 'gb-work-lightbox__frame') && str_contains($css, '.gb-work-lightbox__frame{position:relative'), 'Golden Bash work gallery must wrap picture, video and arrows in a frame so the arrows stay centred on the media once the caption takes up flow space.');
        $assert(str_contains($css, '.gb-services-profile .hp-section-cta a:focus-visible') && str_contains($css, 'min-width:230px!important') && str_contains($css, 'linear-gradient(108deg,#8b5b18'), 'Golden Bash services CTA must use the shared bordered gradient button treatment with an accessible focus state.');
        $assert(str_contains($homepagePortfolio, 'data-gallery-date') && str_contains($homepagePortfolio, 'data-gallery-description'), 'Homepage portfolio cards must expose the gallery metadata.');
        $assert(HomepageContentService::HOMEPAGE_PORTFOLIO_BATCH_SIZE === 5 && str_contains($homepageContentService, 'ThemeTemplateRegistry::isGoldenBashFamily($this->currentThemeKey)') && str_contains($homepageContentService, 'self::HOMEPAGE_PORTFOLIO_BATCH_SIZE'), 'Golden Bash family themes must receive every published homepage gallery while other themes retain the named five-gallery limit.');
        $assert(str_contains($js, 'initHomepagePortfolioLoadMore') && str_contains($js, "hiddenCards.slice(0, batchSize)") && str_contains($js, "controls.hidden = true") && str_contains($css, '.gb-portfolio-more__button'), 'Golden Bash homepage portfolio must progressively reveal repeated five-gallery batches and remove the control after the final batch.');
        $assert(str_contains($portfolioListingData, 'foreach ($repo->allPublished($siteId) as $entry)') && !str_contains($portfolioListingData, 'array_slice('), 'The Nase prace listing must keep every published gallery without the homepage limit.');
        $assert(str_contains($portfolioListing, 'data-gallery-date') && str_contains($portfolioListing, 'class="pt-photos"'), 'Portfolio listing cards must expose dates and the full hidden gallery media set.');
        $assert(str_contains($themedPortfolioListing, 'class="pt-card gb-work-tile"'), 'Golden Bash portfolio listing must expose one full-link tile per database entry.');
        $assert(str_contains($css, '.gb-work-listing .pt-grid{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;grid-template-rows:repeat(4,230px)')
            && str_contains($css, '.gb-work-listing .pt-card:nth-child(1){grid-column:1;grid-row:1 / span 2}')
            && str_contains($css, '.gb-work-listing .pt-card:nth-child(9){grid-column:1 / span 2;grid-row:4}'), 'Golden Bash portfolio listing must expose all nine entries in the asymmetric desktop mosaic.');
        $assert(str_contains($css, '.gb-work-listing .pt-card:focus-visible{outline:3px solid #b77a20') && str_contains($css, '.gb-work-listing .pt-card__media::after{content:""'), 'Golden Bash work tiles must expose a keyboard focus ring and a contrast gradient over media.');
        $assert(str_contains($css, '.gb-work-listing .pt-grid{grid-auto-rows:220px!important;gap:9px!important}') && str_contains($css, '.gb-work-listing .pt-card:nth-child(1){grid-row:auto!important}'), 'Golden Bash mobile work tiles must stay compact without the desktop row span.');
        $assert(!str_contains($css, '-webkit-line-clamp'), 'Golden Bash must not hide blog headings or excerpts with line clamping.');
        $assert(str_contains($blogListing, 'gb-blog-listing') && str_contains($blogListing, '$posts as $post'), 'Golden Bash blog listing must keep DB posts in the light editorial grid.');
        $assert(str_contains($blogListing, 'gb-route-cta') && str_contains($faqPage, 'gb-route-cta'), 'Golden Bash blog and FAQ pages must end with the shared homepage-style CTA.');
        $assert(str_contains($servicesPage, 'gb-services-hero') && str_contains($servicesPage, '$servicesHeroImage') && str_contains($servicesPage, "preg_split('/(?<=[.!?])") && str_contains($servicesPage, 'gb-services-process') && str_contains($servicesPage, 'gb-services-benefits') && str_contains($servicesPage, 'gb-route-cta--services'), 'Golden Bash services must reuse DB media, derive card details only from stored descriptions, and expose the reference process, benefits, and CTA composition.');
        $assert(str_contains($servicesPage, '$servicesHeroImage = $heroImage;') && !str_contains($servicesPage, '$items[0][\'image_url\']'), 'Golden Bash services hero must use the page hero media, not the first service-card image.');
        $assert(str_contains($css, '.gb-services-hero::after') && str_contains($css, 'linear-gradient(90deg,#fffdf9') && str_contains($css, '.gb-route-cta'), 'Golden Bash services hero and route CTAs must use the light editorial gradient contract.');
        $assert(str_contains($js, "serviceDetailHero.classList.add('gb-service-detail-hero')") && str_contains($js, 'duplicateServiceImage.remove()') && str_contains($css, '.gb-service-detail-cta'), 'Golden Bash service details must convert both brown panels to the light contract and remove the repeated lead image.');
        $assert(str_contains($pricingPage, '$packages as $package') && str_contains($pricingPage, '$faqItems as $item') && str_contains($pricingPage, '$transportText') && !str_contains($pricingPage, '17 000'), 'Golden Bash pricing page must derive packages, transport and FAQ from DB content.');
        $assert(str_contains($pricingPage, "\$package['category']") && str_contains($pricingPage, "\$package['ctaLabel']") && str_contains($pricingPage, "\$package['badge']"), 'Golden Bash pricing cards must render their full DB-owned category, CTA and badge contract.');
        $assert(str_contains($cenikData, '$i <= 8') && str_contains($cenikData, "'cenik_faq_'") && str_contains($cenikData, "'pricing_transport_text'"), 'Pricing ViewModel must expose eight packages, transport copy and five DB FAQ slots.');
        foreach (['Dva kameramani', '25 000 Kč', '7 000 Kč', '10 000 Kč', '15 000 Kč', '32 000 Kč', '2 000 Kč', '3 000 Kč', 'Nejžádanější', '7 Kč / km', 'do 80 km zdarma'] as $requiredPricingCopy) {
            $assert(str_contains($demoSeeder, $requiredPricingCopy), 'Demo pricing data is missing exact client copy: ' . $requiredPricingCopy);
        }
        $assert(str_contains($frontController, "\$_POST['selected_package_id']") && str_contains($frontController, "\$redirectQuery['balicek']") && str_contains($frontController, "'invalid-package'"), 'Pricing selection must preserve only a validated identifier and visibly reject unknown values.');
        $assert(str_contains($pricingPage, 'gb-cenik-cta-actions') && str_contains($css, '.gb-cenik-cta-actions a'), 'Golden Bash pricing CTA must normalize DB-owned links to the shared visual button.');
        $assert(str_contains($blogDetail, '$this->raw($post[\'content\'])') && !str_contains($blogDetail, 'premium-contact-cta'), 'Golden Bash article detail must preserve stored article/CTA HTML instead of replacing it.');
        $assert(str_contains($footer, '$footer[\'phones\']') && str_contains($footer, '$footer[\'email\']') && str_contains($footer, "partial('partials/footer-socials'"), 'Golden Bash compact footer must use canonical contact and social data.');
        $assert(str_contains($footer, '$phones as $phone') && str_contains($footer, '/podminky-uziti'), 'Golden Bash footer must render every canonical phone and the general terms link.');
        $assert(str_contains($footer, '$footer[\'addressLines\']') && str_contains($footer, '$footer[\'navLinks\']') && str_contains($footer, 'gb-footer-legal'), 'Golden Bash footer must expose canonical address, quick links, and legal navigation.');
        $assert(str_contains($footerRenderer, 'buildRichFooterHtml(int $siteId, ?string $themeKey = null)') && str_contains($pageComposer, 'self::themeKey($ctx)'), 'Theme previews must pass their resolved theme key into the canonical footer renderer.');
        $assert(str_contains($aboutPage, 'gb-about-team-grid') && str_contains($aboutPage, 'gb-about-values-grid') && str_contains($aboutPage, 'gb-about-process') && str_contains($aboutPage, 'gb-about-trust-grid') && str_contains($aboutPage, 'gb-about-story-grid'), 'Golden Bash About page must provide the complete editorial composition.');
        $assert(str_contains($aboutPage, 'Zdeněk Černohlávek') && str_contains($aboutPage, 'Tomáš Šincl'), 'Golden Bash About page must identify the real photographer and cameraman.');
        $aboutActions = '';
        if (preg_match('~<div class="gb-about-actions">(?<actions>.*?)</div>~s', $aboutPage, $aboutActionMatch) === 1) {
            $aboutActions = (string) ($aboutActionMatch['actions'] ?? '');
        }
        $assert(substr_count($aboutActions, 'class="gb-about-action-icon"') === 2
            && str_contains($aboutActions, 'href="/kontakt"><svg class="gb-about-action-icon"')
            && str_contains($aboutActions, 'href="/nase-prace"><svg class="gb-about-action-icon"')
            && !str_contains($aboutActions, 'material-symbols-outlined'), 'Golden Bash About hero actions must use two purpose-built SVG icons instead of font fallbacks.');
        $aboutValues = '';
        if (preg_match('~<div class="gb-about-values-grid">(?<values>.*?)</div>~s', $aboutPage, $aboutValuesMatch) === 1) {
            $aboutValues = (string) ($aboutValuesMatch['values'] ?? '');
        }
        $assert(substr_count($aboutValues, 'class="gb-about-value-icon"') === 4
            && !str_contains($aboutValues, 'material-symbols-outlined')
            && str_contains($css, '.gb-about-values-grid svg.gb-about-value-icon')
            && !str_contains($css, '.gb-about-values-grid :is(i,.gb-about-value-icon)'), 'Golden Bash About values must use four publish-safe purpose-built SVG icons instead of font fallbacks.');
        $assert(str_contains($aboutPage, '$reviews') && str_contains($aboutPage, 'gb-about-proof__quotes'), 'Golden Bash About proof must render multiple DB-owned reviews instead of a placeholder quote.');
        $assert(str_contains($aboutPage, '$stories') && str_contains($aboutPage, "(string)\$story['slug']") && !str_contains($aboutPage, 'Romantická svatba na zámku'), 'Golden Bash About stories must render real passed blog posts without fake placeholder copy.');
        $assert(str_contains($aboutPage, "alt=\"<?= \$this->e((string)\$story['title']) ?>\""), 'About story images must expose their real article title to assistive technology.');
        $assert(str_contains($aboutStoriesData, "BlogListingData::forSite(\$siteId)") && str_contains($aboutStoriesData, "hash('sha256'") && str_contains($frontController, 'AboutStoriesData::forSite') && str_contains($publishService, 'AboutStoriesData::forSite'), 'About stories must use one deterministic pseudo-random published-post source in live and publish rendering.');
        $assert(str_contains($aboutPage, 'about_photographer_image') && str_contains($aboutPage, 'about_cameraman_image'), 'Golden Bash About page must use its two explicit CMS media blocks.');
        $assert(str_contains($frontController, "'blocks' => \$blocks") && str_contains($publishService, "'blocks' => \$blocks"), 'Live and publish renderers must pass identical content blocks to per-page theme views.');
        $assert(str_contains($css, 'body.theme-golden-bash[data-cms-page-key="o-nas"] .gb-about-hero') && str_contains($css, '.gb-about-team-grid') && str_contains($css, '.gb-about-values-grid'), 'Golden Bash About page must own its route-scoped desktop and mobile presentation.');
        $assert(str_contains($css, 'linear-gradient(#fffdf9,#fffdf9) padding-box') && str_contains($css, 'color:#b87312'), 'Shared process number controls must use white fill and a gold reflective border.');
        $assert(str_contains($js, 'a[href="#faq"]') && str_contains($js, "setAttribute('href', '/faq')"), 'Golden Bash must repair stale homepage FAQ hash navigation.');
        $assert(str_contains($css, '.site-footer-brand__copy small{margin-top:5px;color:#685747'), 'Golden Bash footer tagline must meet contrast on the cream footer.');
        $assert(str_contains($css, '.site-footer-socials__list{display:flex!important;flex:0 0 auto!important;flex-wrap:nowrap!important'), 'Golden Bash footer social icons must remain in one horizontal row.');
        $assert(!str_contains($js, '<span>Osobní přístup</span>')
            && substr_count($js, "benefits.innerHTML = '<span>") === 1
            && str_contains($js, '<span>Vyprávíme emoce</span><span>Foto i video v jednom týmu</span>')
            && str_contains($css, 'grid-template-columns:repeat(2,minmax(0,1fr))!important;')
            && str_contains($css, 'background:rgba(15,12,10,.1)!important;')
            && !str_contains($css, 'background:rgba(15,12,10,.72)!important;')
            && str_contains($js, 'gb-service-card-link'), 'Golden Bash homepage must keep four hero benefits, expose a near-transparent mobile grid (text-shadow carries contrast, not an opaque box), and retain real links over each About/service block.');
        $assert(str_contains($js, "mobileVideoPlay.className = 'gb-mobile-video-play'") && str_contains($js, "mobileHeroVideo.classList.add('is-playing')") && str_contains($css, '.theme-hero-video:not(.is-playing) iframe{display:none!important}'), 'Golden Bash mobile hero must show its poster and activate the real video only after the accessible play control is used.');
        $assert(str_contains($layout, 'RB Videos &amp; Photos') && !str_contains($layout, 'RB Photos &amp; Videos'), 'Golden Bash welcome screen must use the canonical brand order.');
        $assert(str_contains($css, '@media(max-width:700px){.gb-welcome-layer{display:none!important}') && str_contains($js, "window.matchMedia('(max-width: 700px)').matches"), 'Golden Bash mobile rendering must not delay LCP behind the decorative welcome layer.');
        $assert(str_contains($layout, '<link rel="stylesheet" href="/assets/fonts/fonts.css">')
            && str_contains($layout, 'rel="preload" as="font" type="font/woff2" crossorigin')
            && !str_contains($layout, 'fonts.googleapis.com')
            && !str_contains($layout, 'fonts.gstatic.com')
            && is_file(dirname(__DIR__) . '/public/assets/fonts/fonts.css'), 'Golden Bash text fonts must be self-hosted same-origin so they need neither a third-party connection nor JavaScript, with the hero face preloaded.');
        $fontCss = (string) file_get_contents(dirname(__DIR__) . '/public/assets/fonts/fonts.css');
        $assert(str_contains($fontCss, 'font-display: swap') && !str_contains($fontCss, 'font-display: optional;'), 'Brand fonts must repair late loads rather than permanently retain a system face or mixed Czech glyphs.');

        // The upright faces are variable files, so one file serves every weight. Naming a
        // file after a weight is what led to six byte-identical copies being downloaded.
        // Only a weight token is forbidden: these files are served immutable for a year, so
        // replacing a face means giving it a new name, and a version marker needs digits.
        $fontPaths = glob(dirname(__DIR__) . '/public/assets/fonts/*.woff2') ?: [];
        $weighted = array_values(array_filter(
            array_map('basename', $fontPaths),
            static fn (string $name): bool => preg_match('~-[1-9]00(?=[-.])~', $name) === 1
        ));
        $assert($fontPaths !== [] && $weighted === [], 'A weight in a font filename means the variable file is being duplicated per weight again: ' . implode(', ', $weighted));

        $fontHashes = array_map('md5_file', $fontPaths);
        $assert(count($fontHashes) === count(array_unique($fontHashes)), 'Two font files hold identical bytes, so one of them is downloaded for nothing.');

        // Every url the stylesheet and the layout name must exist, or the page silently
        // falls back to system fonts while every check above still passes.
        $fontCssDir = dirname(__DIR__) . '/public';
        preg_match_all('~url\((/assets/fonts/[^)]+\.woff2)\)~', $fontCss, $srcMatches);
        $declared = array_unique($srcMatches[1]);
        $assert($declared !== [], 'The font stylesheet declares no woff2 source at all.');
        foreach ($declared as $src) {
            $assert(is_file($fontCssDir . $src), 'fonts.css names a file that is not on disk: ' . $src);
        }
        preg_match_all('~rel="preload"[^>]*href="(/assets/fonts/[^"]+\.woff2)"~', $layout, $preloadMatches);
        $assert($preloadMatches[1] !== [], 'The layout preloads no font at all.');
        foreach (array_unique($preloadMatches[1]) as $href) {
            $assert(in_array($href, $declared, true), 'The layout preloads a font the stylesheet never uses: ' . $href);
        }

        // Czech splits across both files of a family - latin carries the ASCII, latin-ext
        // the carons - so preloading one without the other lets the other miss the
        // font-display:optional window, and optional never repairs it: the page would then
        // render half a word in the brand face and half in the system one.
        foreach (['montserrat', 'cormorantgaramond', 'cormorantgaramond-italic'] as $face) {
            $assert(
                str_contains($layout, '/assets/fonts/' . $face . '-latin.woff2')
                === str_contains($layout, '/assets/fonts/' . $face . '-latin-ext.woff2'),
                'Preload both halves of ' . $face . ' or neither, or Czech text renders in two families at once.'
            );
        }
        $assert(!str_contains($js, "heroTitle.insertAdjacentHTML('afterend'") && str_contains($js, 'window.requestAnimationFrame(updateScrollTopButton)'), 'Golden Bash must render the hero heading server-side and defer the initial scroll geometry read.');
        $stableHero = \App\Services\Render\HomepageSectionRenderer::stabilizeGoldenBashHero(
            '<main><h1>Jiný nadpis</h1><section class="hp-hero"><div><h1 data-block="hero_h1">SEO nadpis</h1></div></section></main>',
            true,
            'golden-bash'
        );
        $assert(str_contains($stableHero, '<h1 data-block="hero_h1" class="gb-hero-seo-title">SEO nadpis</h1><h2 class="gb-hero-story-title">') && str_starts_with($stableHero, '<main><h1>Jiný nadpis</h1>'), 'Golden Bash hero must reserve its final heading layout in server HTML without touching unrelated headings.');
        $diagonalStableHero = \App\Services\Render\HomepageSectionRenderer::stabilizeGoldenBashHero(
            '<section class="hp-hero"><h1>SEO nadpis</h1></section>',
            true,
            'golden-bash-diagonal'
        );
        $assert(str_contains($diagonalStableHero, 'gb-hero-story-title'), 'Golden Bash Diagonal must retain the source theme server-side hero stabilization.');
        $assert(str_contains($normalizer, 'icon_names=assignment,call,check_circle,description,event,event_available,expand_more,location_on,mail,photo_camera,play_circle,receipt_long,schedule,sell,verified_user,warning')
            && str_contains($js, 'systemSymbols') && str_contains($js, 'gb-symbol-fallback'), 'Golden Bash must keep local fallbacks outside Contact and About while loading only a bounded Google symbol subset for those two routes.');
        $assert(str_contains($js, "pricingDetailAction.textContent = 'Zobrazit podrobný ceník'"), 'Golden Bash homepage pricing must expose the detailed-pricing CTA.');
        $assert(str_contains($css, 'body.theme-golden-bash .pf-hero::after,') && str_contains($css, 'background:linear-gradient(180deg,rgba(255,253,249,1) 0%') && str_contains($css, '.gb-services-hero>.container{padding:30px 22px 180px!important'), 'Golden Bash mobile route heroes must share the full-height vertical white fade with top-left content.');
        $assert(str_contains($css, 'body.theme-golden-bash:has(.gb-services-list) .gb-services-list .services-grid{') && str_contains($css, 'grid-template-columns:repeat(2,minmax(0,1fr))!important') && str_contains($css, '.gb-services-process__steps{') && str_contains($css, '.gb-services-benefits{'), 'Golden Bash services redesign must expose the two-column desktop cards, process timeline, and benefit strip.');
        // Captions were taken off the gallery previews at the client's request. The element
        // has to stay in the DOM: theme.js reads the gallery title out of it and screen
        // readers use it to name the tile link, so display:none would take both away.
        $assert(str_contains($css, 'body.theme-golden-bash .pt-tile>.pt-caption{position:absolute!important')
            && str_contains($css, 'clip-path:inset(50%)!important'), 'Gallery preview captions must be hidden visually, not removed from the document.');

        // Author rules beat the browser's own [hidden]{display:none}, so without this
        // guard an element carrying the hidden attribute still renders. That is exactly
        // what put the about-section photograph full size at the top of the homepage.
        $assert(str_contains($css, 'body.theme-golden-bash [hidden]{display:none!important}'), 'The theme must honour the hidden attribute; img{display:block} silently overrides it.');

        // A <video> with no source still claims a 300x150 replaced-element box. The theme
        // takes it out of flow, and the hook for that is a class the server puts on it -
        // a CSS :empty test would stop matching on a single stray newline inside the tag.
        $pageRenderer = (string) file_get_contents($root . '/app/Services/PageRenderer.php');
        $assert(str_contains($pageRenderer, 'hero-video--sourceless')
            && str_contains($css, 'hero-video--sourceless{position:absolute!important')
            && !str_contains($css, 'hero-video:not([src]):empty'), 'A sourceless hero video must be taken out of flow through a server-set class, not a :empty selector.');

        // Autoplay is a setting, not a hard-coded attribute: the hero partial and the
        // about page must both emit data-hero-video-autoplay only when it is switched on,
        // or the client cannot turn off a behaviour that triples the page weight.
        $heroPartial = (string) file_get_contents($root . '/app/Views/partials/hero-video.phtml');
        $aboutPage = (string) file_get_contents($themeDir . '/views/pages/o-nas.phtml');
        $assert(str_contains($heroPartial, chr(36) . 'autoplay')
            && !str_contains($heroPartial, 'data-hero-video-facade data-hero-video-autoplay'), 'The hero must take its autoplay from the setting, not hard-code it.');
        $assert(str_contains($aboutPage, chr(36) . 'videoAutoplay')
            && str_contains($aboutPage, 'data-hero-video-autoplay'), 'The about page video must take its autoplay from the setting.');
        $assert(str_contains($css, 'Final mobile homepage portfolio contract')
            && str_contains($css, 'grid-template-columns:1fr!important;')
            && str_contains($css, 'grid-auto-rows:250px!important;')
            && str_contains($css, 'gap:16px!important;')
            && str_contains($css, '.gb-portfolio-mosaic>.container>div.hp-section-cta,')
            && str_contains($css, 'position:static!important;'), 'Golden Bash mobile homepage galleries must use one spaced card per row and keep the all-work CTA in normal flow below the grid.');
        $assert(str_contains($css, '/* Constrained desktop/tablet guard: keep the art direction without compressed or clipped grids. */')
            && str_contains($css, '.gb-portfolio-mosaic .pt-tile:nth-child(5){')
            && str_contains($css, 'height:260px!important;')
            && str_contains($css, '.gb-reviews>.container>div:last-child{')
            && str_contains($css, '.gb-shared-process__step{')
            && str_contains($css, 'grid-template-rows:72px auto!important;')
            && str_contains($css, '.gb-footer-rich>.gb-footer-brand,')
            && str_contains($css, '.gb-footer-rich>.gb-footer-legal{'), 'Golden Bash tablet guard must keep portfolio cards in normal flow, review actions side by side, process copy inside its cards, and footer groups in full-width rows.');
        $assert(str_contains($css, '.hp-hero{height:380px!important;min-height:380px!important;padding:18px 0 64px!important}')
            && str_contains($css, '.hp-hero>.container{padding-top:38px!important;padding-bottom:52px!important}')
            && str_contains($css, '.hero-cta-wrap{margin-top:10px!important}'), 'Golden Bash mobile hero must reserve enough space above the benefit strip for its visible CTA.');
        $assert(str_contains($css, 'font:500 clamp(60px,5.4vw,82px)/1.23 "Cormorant Garamond",serif;')
            && str_contains($css, 'line-height:1.23!important;'), 'Golden Bash hero story title must keep non-overlapping desktop and mobile line spacing.');
        $assert(str_contains($css, '.gb-shell{padding-top:0!important}')
            && str_contains($css, 'height:600px!important;')
            && str_contains($css, 'background:linear-gradient(180deg,rgba(18,14,11,.02)')
            && str_contains($css, '.hp-hero .section-label{display:none!important}'), 'Golden Bash mobile homepage hero must remove blank bands and keep its text readable over one full-bleed image.');
        $assert(str_contains($css, '.gb-services-profile{padding:18px 0 2px!important}')
            && str_contains($css, '.gb-services-profile .hp-services-grid{margin-top:4px!important}')
            && str_contains($css, '.gb-portfolio-mosaic{padding:10px 0 24px!important}'), 'Golden Bash mobile About-to-portfolio transition must stay compact without excess white space.');
        $assert(str_contains($css, '.gb-services-profile .hp-svc-card:last-child,')
            && str_contains($css, '.gb-services-profile .premium-service-card:last-child{border-right:1px solid #dfd4c7!important}'), 'Golden Bash services grid must retain its right outer border on the final card.');
        $assert(str_contains($css, 'margin:0 auto!important;align-self:flex-start!important'), 'Golden Bash mobile route hero copy must stay at the upper-left instead of vertical centering.');
        $assert(str_contains($css, 'Shared Golden Bash inner-page hero') && !str_contains($css, 'body.theme-golden-bash :is(.pf-hero'), 'Golden Bash inner heroes must share a minifier-safe visual contract.');
        $assert(str_contains($css, '.pf-hero>.hero-bg') && str_contains($css, 'mask-image:linear-gradient'), 'Golden Bash inner heroes must retain their full-size media with the editorial fade.');
        $assert(str_contains($themedPortfolioListing, 'gb-work-listing') && str_contains($themedPortfolioListing, 'pt-card__media'), 'Golden Bash portfolio listing must use its light editorial card composition.');
        $assert(str_contains($themedPortfolioDetail, '$entry[\'hero_image\']') && str_contains($themedPortfolioDetail, 'data-responsive-profile="portfolio-hero"') && str_contains($themedPortfolioDetail, 'data-lcp-candidate') && str_contains($themedPortfolioDetail, 'gb-work-detail'), 'Golden Bash portfolio detail must expose a responsive medium lead image as the priority hero and keep the light body.');
        $assert(str_contains($portfolioDetailCss, 'width:50px!important;height:50px!important;min-width:50px!important;min-height:50px!important;aspect-ratio:1')
            && str_contains($portfolioDetailCss, 'width:52px!important;height:52px!important;min-width:52px!important;min-height:52px!important;aspect-ratio:1')
            && str_contains($portfolioDetailCss, 'background:transparent!important;box-shadow:none!important')
            && str_contains($portfolioPublisher, 'aspect-ratio:1;padding:0!important'), 'Portfolio lightbox controls must remain exact transparent circles despite inherited global button sizing.');
        $assert(str_contains($frontController, "\$mediaItems[0]['storage_path'] ?? \$mediaItems[0]['medium_path'] ?? \$mediaItems[0]['thumb_path']")
            && str_contains($frontController, "\$media['storage_path'] ?? \$media['medium_path'] ?? \$media['thumb_path']"), 'Large homepage hero fallbacks must prefer the original media over thumbnail derivatives.');
        $assert(str_contains($setupController, 'DemoContentBatchService')
            && str_contains($setupController, 'demoContentStart')
            && str_contains($setupController, 'demoContentStep')
            && !str_contains($setupController, 'set_time_limit(0)'), 'Public Demo seeding must use persistent short batches instead of lifting the HTTP execution limit.');
        foreach (['golden-bash', 'golden-bash-diagonal'] as $cenikIntroTheme) {
            $cenikIntroCss = (string) file_get_contents(dirname(__DIR__) . '/Templates/' . $cenikIntroTheme . '/assets/theme.css');
            $assert(str_contains($cenikIntroCss, 'body.theme-golden-bash[data-cms-page-key="cenik"] .gb-cenik-packages .cenik-intro{margin:14px auto 42px!important;text-align:center}'), $cenikIntroTheme . ' /cenik package intro must be centred and beat the margin:0!important of frontend-runtime.css.');
        }
    });
}
