<?php

declare(strict_types=1);

use App\Services\SeoRenderer;

function register_seo_audit_regression_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);

    $test('published sitemap is limited to indexable CMS pages', function () use ($assert, $root): void {
        $source = (string)file_get_contents($root . '/app/Services/PublishService.php');
        $assert(str_contains($source, "\$indexablePages = array_values(array_filter("));
        $assert(str_contains($source, "(int)(\$seo['robots_index'] ?? 0) === 1"));
        $assert(str_contains($source, '$this->builtInPublicRouteSitemapItems($indexablePages)'));
    });

    $test('live SEO head upsert removes duplicate tags instead of replacing only the first', function () use ($assert, $root): void {
        $source = (string)file_get_contents($root . '/public/index.php');
        $start = strpos($source, 'function upsertHeadTag');
        $end = $start === false ? false : strpos($source, 'function injectPageHeadMetadata', $start);
        $upsert = $start !== false && $end !== false ? substr($source, $start, $end - $start) : '';
        $assert($upsert !== '', 'Head upsert implementation is missing.');
        $assert(str_contains($upsert, 'preg_replace_callback'), 'Head upsert does not inspect every duplicate tag.');
        $assert(str_contains($upsert, '$replacementCount++ === 0 ? $tag :'), 'Head upsert does not retain exactly one canonical tag.');
    });

    $test('priority service content meets depth and internal-link contract', function () use ($assert, $root): void {
        $migrations = implode("\n", array_map(
            static fn(string $path): string => (string)file_get_contents($path),
            glob($root . '/database/migrations/03{1,2,3}_*.sql', GLOB_BRACE) ?: []
        ));

        foreach (['/svatebni-foto-video', '/rodinne-a-portretni-foceni', '/reklamni-foto-video'] as $path) {
            $assert(str_contains($migrations, $path), 'Missing expanded content for ' . $path);
        }
        foreach (['href="/kontakt"', 'href="/cenik"', 'href="/nase-prace"', 'href="/hodnoceni"'] as $link) {
            $assert(str_contains($migrations, $link), 'Missing internal-link target ' . $link);
        }
    });

    $test('LocalBusiness schema enrichment uses contact repository NAP and social profiles', function () use ($assert, $root): void {
        $source = (string)file_get_contents($root . '/app/Services/SeoRenderer.php');
        $assert(str_contains($source, '(new ContactRepository())->findBySiteSafe'));
        $assert(str_contains($source, "\$node['telephone']"));
        $assert(str_contains($source, "\$node['email']"));
        $assert(str_contains($source, "'@type' => 'PostalAddress'"));
        $assert(str_contains($source, "\$node['sameAs']"));
        $assert(str_contains($source, "\$node['@id'] = \$organizationId"));
        $assert(str_contains($source, "\$address['addressCountry'] = 'CZ'"));
    });

    $test('homepage keeps one semantic H1 and a separate visual story heading', function () use ($assert, $root): void {
        $script = (string)file_get_contents($root . '/Templates/golden-bash/assets/theme.js');
        $style = (string)file_get_contents($root . '/Templates/golden-bash/assets/theme.css');
        $migration = (string)file_get_contents($root . '/database/migrations/030_set_homepage_semantic_h1.sql');
        $homepageRenderer = (string)file_get_contents($root . '/app/Services/Render/HomepageSectionRenderer.php');

        $assert(str_contains($migration, 'Svatební fotograf a kameraman v Olomouci'));
        $assert(str_contains($homepageRenderer, 'gb-hero-seo-title'));
        $assert(str_contains($homepageRenderer, '<h2 class="gb-hero-story-title">'));
        $assert(!str_contains($script, 'heroTitle.insertAdjacentHTML'));
        $assert(str_contains($style, '.gb-hero-story-title'));
    });

    $test('demo SEO content keeps one brand, non-duplicated intros and verified about claims', function () use ($assert, $root): void {
        $demo = (string)file_get_contents($root . '/app/Services/DemoContentSeeder.php');
        $seed = (string)file_get_contents($root . '/database/seeds/seed.php');
        $about = (string)file_get_contents($root . '/Templates/golden-bash/views/pages/o-nas.phtml');

        $assert(str_contains($demo, "'Párové a zásnubní focení Olomouc | RB Videos & Photos'"));
        $assert(!str_contains($demo, '| RB Photos & Videos'));
        $assert(!str_contains($demo, '| RB Videos Photos'));
        $assert(str_contains($demo, '$intro !== $excerpt'));
        $assert(str_contains($demo, "'related_links'"));
        foreach (['/svatebni-foto-video', '/cenik', '/kontakt'] as $internalTarget) {
            $assert(str_contains($demo, "['{$internalTarget}'"), 'Missing explicit related content target ' . $internalTarget);
        }
        // NAP guard (was: buildSchemaJson() pulling findBySiteSafe()). Since migration 079 the
        // seeder no longer assembles JSON-LD at all; LocalBusiness contact data must come from
        // ContactRepository at render time (SeoRenderer::contactProfile, schema_contact_auto=1
        // column default). So the seeder must never write schema_json / manual contact columns
        // or flip schema_contact_auto, and Kontakt itself must still be seeded via ContactRepository.
        $assert(!str_contains($demo, 'schema_json'), 'Demo seeder must not write deprecated schema_json.');
        foreach (['schema_contact_auto', 'schema_telephone', 'schema_email', 'schema_address_street', 'schema_address_locality'] as $manualColumn) {
            $assert(!str_contains($demo, $manualColumn), 'Demo seeder must leave ' . $manualColumn . ' at its default so NAP comes from Kontakt at render time.');
        }
        $assert(str_contains($demo, '(new ContactRepository())->upsert($siteId'), 'Demo Kontakt NAP must be seeded through ContactRepository.');
        $assert(!str_contains($demo, 'priceRange'));
        $randeStart = strpos($demo, "'slug' => 'rande-a-zasnuby'");
        $randeEnd = $randeStart === false ? false : strpos($demo, "\n            ],", $randeStart);
        $assert($randeStart !== false && $randeEnd !== false, 'Rande service definition is missing.');
        $rande = substr($demo, (int)$randeStart, (int)$randeEnd - (int)$randeStart);
        foreach (['konference', 'koncert', 'ples', 'firemní večírek', 'firemní akce'] as $forbiddenTopic) {
            $assert(!str_contains(mb_strtolower($rande), mb_strtolower($forbiddenTopic)), 'Rande content contains forbidden topic: ' . $forbiddenTopic);
        }
        foreach (['Olomouci', 'oblečení', 'přibližně hodinu', 'online galerii'] as $requiredFact) {
            $assert(str_contains($rande, $requiredFact), 'Rande content lacks required detail: ' . $requiredFact);
        }
        // Homepage hero_h1 was re-keyworded by migration 081 to cover both the
        // fotograf and kameraman phrases; the seed must match it.
        $assert(str_contains($seed, "'Svatební fotograf Olomouc a svatební kameraman Olomouc'"), 'Seed homepage H1 must match migration 081 keyword H1.');
        foreach (['href="/sluzby"', 'href="/nase-prace"', 'href="/blog"', 'href="/cenik"', 'href="/o-nas"', 'href="/hodnoceni"', 'href="/kontakt"'] as $href) {
            $assert(str_contains($seed, $href), 'Missing canonical navigation target ' . $href);
        }
        $assert(str_contains($about, 'Dva lidé, jeden společný příběh'));
        foreach (['250+', '10+', '4,9/5', '40+', '4–24 h'] as $unsupportedClaim) {
            $assert(!str_contains($about, $unsupportedClaim), 'Unsupported About claim remains: ' . $unsupportedClaim);
        }
    });

    $test('priority audit has a durable about page and clear portfolio intent', function () use ($assert, $root): void {
        $migration = (string)file_get_contents($root . '/database/migrations/035_close_priority_seo_audit.sql');
        $assert(str_contains($migration, "'o-nas', 'o-nas'"));
        $assert(str_contains($migration, 'Banny — fotografie'));
        $assert(str_contains($migration, 'Tomáš — video'));
        $assert(str_contains($migration, 'Ukázka svatebního foto a videa v Olomouci'));
        $assert(str_contains($migration, "schema_type`,`schema_json"));
    });

    $test('measurement plan uses the requested GA4 event vocabulary', function () use ($assert, $root): void {
        $tracking = (string)file_get_contents($root . '/public/assets/tracking.js');
        $catalog = (string)file_get_contents($root . '/app/Services/GtmEventCatalog.php');
        foreach ([
            'generate_lead',
            'click_phone',
            'click_email',
            'select_package',
            'video_start',
            'video_complete',
            'view_google_reviews',
        ] as $eventName) {
            $assert(str_contains($tracking . $catalog, "'{$eventName}'"), 'Missing event ' . $eventName);
        }
    });

    $test('portfolio videos emit VideoObject structured data', function () use ($assert, $root): void {
        $front = (string)file_get_contents($root . '/public/index.php');
        $assert(str_contains($front, 'function renderPortfolioVideoJsonLd'));
        $assert(str_contains($front, "'@type' => 'VideoObject'"));
        $assert(str_contains($front, "'embedUrl' => PortfolioRepository::embedUrl"));
        $assert(str_contains($front, 'renderPortfolioVideoJsonLd('));
    });

    $test('special routes receive organization schema and tracking runtime', function () use ($assert, $root): void {
        $front = (string)file_get_contents($root . '/public/index.php');
        $renderer = (string)file_get_contents($root . '/app/Services/SeoRenderer.php');
        $assert(str_contains($front, "if (!str_contains(\$html, 'cms-tracking-config'))"));
        $assert(str_contains($front, 'renderTrackingRuntime($site)'));
        $assert(str_contains($front, "renderOrganizationSchema(\$site, \$ogImage !== '' ? \$ogImage : null)"));
        $assert(str_contains($renderer, 'data-cms-organization-schema'));
        $assert(str_contains($renderer, "'@type' => ['Organization', 'LocalBusiness']"));
    });

    $test('frontend image pipeline reserves layout space and prioritizes hero media', function () use ($assert, $root): void {
        $front = (string)file_get_contents($root . '/public/index.php');
        $responsive = (string)file_get_contents($root . '/app/Services/Render/ResponsiveImageMarkupService.php');
        $composer = (string)file_get_contents($root . '/app/Services/Render/PageComposer.php');
        $assert(str_contains($front, 'function injectImagePerformanceAttributes'));
        $assert(str_contains($front, 'ResponsiveImageMarkupService::optimize'));
        $assert(str_contains($responsive, "loading=\"' . (\$isPriority || \$isLogo ? 'eager' : 'lazy')"), 'Only the priority image and the above-the-fold logo may load eagerly.');
        $assert(str_contains($responsive, "fetchpriority=\"high\""));
        $assert(str_contains($responsive, 'data-lcp-candidate'));
        $assert(str_contains($responsive, "['small', 'medium', 'large']"));
        $assert(str_contains($responsive, "['homepage-card', 'homepage-hero']") && str_contains($responsive, "['small', 'medium']"), 'Homepage image profiles must cap candidates at the bounded medium derivative.');
        $assert(str_contains($responsive, 'srcset=') && str_contains($responsive, 'sizes='));
        $assert(str_contains($responsive, 'getimagesize($local)'));
        $assert(str_contains($composer, 'ResponsiveImageMarkupService::optimize'));

        $optimized = \App\Services\Render\ResponsiveImageMarkupService::optimize(
            '<main><img class="team" data-lcp-candidate src="/assets/team.png"><img class="hero-bg" src="/assets/hero.png"></main>',
            0
        );
        $assert((bool)preg_match('~<img[^>]*data-lcp-candidate[^>]*loading="eager"[^>]*fetchpriority="high"~', $optimized));
        $assert((bool)preg_match('~<img[^>]*class="hero-bg"[^>]*loading="lazy"~', $optimized));
        $assert(substr_count($optimized, 'fetchpriority="high"') === 1);
    });

    $test('mobile presentation uses variants without downgrading gallery originals', function () use ($assert, $root): void {
        $homepage = (string)file_get_contents($root . '/app/Services/HomepageContentService.php');
        $portfolioPartial = (string)file_get_contents($root . '/app/Views/partials/home-portfolio.phtml');
        $goldenJs = (string)file_get_contents($root . '/Templates/golden-bash/assets/theme.js');
        $services = (string)file_get_contents($root . '/app/ViewModels/SluzbyPageData.php');
        $portfolioDetail = (string)file_get_contents($root . '/app/ViewModels/PortfolioDetailData.php');
        $responsive = (string)file_get_contents($root . '/app/Services/Render/ResponsiveImageMarkupService.php');

        $assert(str_contains($homepage, "['medium_path'] ?? \$photos[0]['thumb_path'] ?? \$photos[0]['storage_path']"));
        $assert(str_contains($homepage, "'fullUrl' => \$this->publicMediaUrl"));
        $assert(str_contains($portfolioPartial, 'data-full="<?= $this->e($photo[\'fullUrl\']) ?>"'));
        $assert(str_contains($goldenJs, "image.getAttribute('data-full')") && str_contains($goldenJs, 'thumbImage.src = item.thumb || item.src'));
        $assert(str_contains($services, "['format'] ?? '') === 'medium'"));
        $assert(str_contains($portfolioDetail, "'hero_image' => \$gallery[0]['medium'] ?? ''"));
        $assert(str_contains($responsive, "\$responsiveProfile === 'portfolio-hero'") && str_contains($responsive, "['small', 'medium', 'large']"));
    });

    $test('public media cache bypasses sessions and accessibility audit selectors are repaired', function () use ($assert, $root): void {
        $front = (string)file_get_contents($root . '/public/index.php');
        $contact = (string)file_get_contents($root . '/app/Views/partials/home-contact.phtml');
        $hero = (string)file_get_contents($root . '/app/Views/partials/hero-video.phtml');
        $css = (string)file_get_contents($root . '/Templates/golden-bash/assets/theme.css');

        $assetCall = strpos($front, 'tryServePublicAssetWithoutSession($earlyUri)');
        $sessionCall = strpos($front, 'Auth::start();');
        $assert($assetCall !== false && $sessionCall !== false && $assetCall < $sessionCall, 'Public files still start a PHP session.');
        $assert(str_contains($front, '31536000, immutable'));
        $assert(str_contains($front, "header_remove('Set-Cookie')"));
        $assert(str_contains($contact, 'for="hp-contact-shooting-type"'));
        $assert(str_contains($contact, 'id="hp-contact-shooting-type"'));
        $assert(str_contains($contact, 'aria-label="Typ služby"'));
        $assert(str_contains($hero, 'data-hero-video-facade') && !str_contains($hero, 'data-hero-video-facade" aria-hidden="true"'));
        $assert(str_contains($css, 'Accessibility floor: readable footer copy and 44px touch targets.'));
        $assert(!str_contains($css, '.gb-contact .hp-contact-form label{display:none}'));

        $assets = (string)file_get_contents($root . '/app/Support/FrontendAssetManager.php');
        $normalizer = (string)file_get_contents($root . '/app/Services/Render/MarkupNormalizer.php');
        $publish = (string)file_get_contents($root . '/app/Services/PublishService.php');
        $assert(str_contains($assets, 'function injectInlineStyle'));
        $assert(str_contains($normalizer, "injectInlineStyle(\$html, \$runtimeCss, 'data-cms-frontend-runtime-css')"));
        $assert(str_contains($normalizer, "injectInlineStyle(\$html, 'body{'"));
        $assert(str_contains($front, "injectInlineStyle(\$html, \$css, 'data-cms-video-overlay')"));
        $assert(str_contains($publish, "injectInlineStyle(\$html, \$css, 'data-cms-video-overlay')"));

        $normalizedStyles = \App\Services\Render\MarkupNormalizer::extractInlineStyleBlocks(
            '<html><head><style data-cms-video-overlay>.theme-hero-video{display:block}</style><style>.below-fold{color:red}</style></head><body></body></html>'
        );
        $assert(str_contains($normalizedStyles, '<style data-cms-video-overlay>'));
        $assert(str_contains($normalizedStyles, 'data-cms-inline-styles'));
        $assert(!str_contains($normalizedStyles, '<style>.below-fold'));
    });

    $test('favicon assets and homepage pricing heading survive seed and existing databases', function () use ($assert, $root): void {
        $seed = (string)file_get_contents($root . '/database/seeds/seed.php');
        $migration = (string)file_get_contents($root . '/database/migrations/042_fix_favicons_and_home_pricing_heading.sql');
        $restoreMigration = (string)file_get_contents($root . '/database/migrations/043_restore_home_pricing_copy.sql');
        $demo = (string)file_get_contents($root . '/app/Services/DemoContentSeeder.php');
        foreach (['/assets/favicons/favicon.ico', '/assets/favicons/favicon-32x32.png', '/assets/favicons/apple-touch-icon.png'] as $asset) {
            $assert(is_file($root . '/public' . $asset), 'Missing favicon asset ' . $asset);
            $assert(str_contains($seed, $asset) && str_contains($migration, $asset), 'Seed and corrective migration must agree on ' . $asset);
        }
        $assert(str_contains($migration, "b.block_key = 'cenik_title'") && str_contains($migration, 'SET b.content ='));
        $assert(str_contains($restoreMigration, "WHEN 'cenik_title' THEN") && str_contains($restoreMigration, "WHEN 'cenik_intro' THEN"));
        $resetStart = strpos($demo, 'private function resetHomepageAutofillBlocks');
        $resetEnd = $resetStart === false ? false : strpos($demo, 'private function seedDefaultHeroPoster', $resetStart);
        $resetSource = $resetStart !== false && $resetEnd !== false ? substr($demo, $resetStart, $resetEnd - $resetStart) : '';
        $assert($resetSource !== '' && !str_contains($resetSource, "'cenik_title'") && !str_contains($resetSource, "'cenik_intro'"));
    });

    $test('current SEO metadata survives demo reseeding and existing database upgrades', function () use ($assert, $root): void {
        $demo = (string)file_get_contents($root . '/app/Services/DemoContentSeeder.php');
        $seed = (string)file_get_contents($root . '/database/seeds/seed.php');
        $migration = (string)file_get_contents($root . '/database/migrations/047_complete_current_seo_metadata.sql');
        $privacyMigration = (string)file_get_contents($root . '/database/migrations/059_remove_article_surnames.sql');
        $portfolioImporter = (string)file_get_contents($root . '/app/Services/PortfolioDemoContentImporter.php');
        $blogImporter = (string)file_get_contents($root . '/app/Services/WeddingBlogDemoContentImporter.php');

        foreach ([$demo, $seed, $migration] as $source) {
            $assert(str_contains($source, 'Podmínky užití a ochrana obsahu'));
            $assert(str_contains($source, '/podminky-uziti'));
            $assert(str_contains($source, 'ochraně fotografií'));
        }
        foreach ([
            'kamila-a-matej',
            'aneta-a-martin',
            'lenka-a-lukas',
            'marie-a-miroslav',
            'martina-a-jaroslav',
            'simona-a-michal',
            'lubos-a-iva',
            'lucie-a-jarek',
            'lea-foceni-pro-ritualni-saty',
        ] as $slug) {
            $assert(str_contains($portfolioImporter, "'slug' => '" . $slug . "'"), 'Current portfolio importer is missing privacy-safe slug ' . $slug);
        }
        foreach (['duriskovi', 'hrazdilovi', 'tuikovi', 'cervenkovi', 'zackovi', 'novakovi', 'neverilovi', 'stipcakovi'] as $legacySlugPart) {
            $assert(str_contains($privacyMigration, $legacySlugPart), 'Privacy migration is missing legacy redirect support for ' . $legacySlugPart);
        }
        foreach (['Hufovi', 'Kaňkovští', 'Pláškovi', 'Krůpovi', 'Jungligovi', 'Gruntovi', 'Provazovi', 'Krylovi', 'Novákovi', 'Žáčkovi', 'Nevěřilovi'] as $surname) {
            $assert(!str_contains($blogImporter, $surname), 'Current blog importer exposes surname ' . $surname);
        }
    });

    $test('portfolio live and static renderers use curated meta titles', function () use ($assert, $root): void {
        $front = (string)file_get_contents($root . '/public/index.php');
        $viewModel = (string)file_get_contents($root . '/app/ViewModels/PortfolioDetailData.php');
        $publisher = (string)file_get_contents($root . '/app/Services/PortfolioPublishService.php');

        $assert(str_contains($viewModel, "'meta_title' => trim((string)(\$entry['meta_title'] ?? ''))"));
        $assert(str_contains($front, "'title' => \$metaTitle"));
        $assert(str_contains($publisher, "\$metaTitle = trim((string)(\$entry['meta_title'] ?? ''))"));
        $assert(!str_contains($publisher, "'meta_title' => (string)\$entry['title'] . ' - '"));
    });

    $test('Active24 deployment preserves environment credentials and explicit token maintenance', function () use ($assert, $root): void {
        $workflow = (string)file_get_contents($root . '/.github/workflows/deploy.yml');
        $utility = (string)file_get_contents($root . '/migrate-util.php');
        $applicationMigrations = (string)file_get_contents($root . '/app/Services/ApplicationCompatibleMigrationService.php');
        $placeholder = (string)file_get_contents($root . '/deploy-maintenance.php');
        $demoSeeder = (string)file_get_contents($root . '/app/Services/DemoContentSeeder.php');
        $seoCompatibility = (string)file_get_contents($root . '/database/migrations/050_restore_missing_production_seo_pages.sql');
        $aboutVisualCompatibility = (string)file_get_contents($root . '/database/migrations/051_restore_homepage_about_visual.sql');
        $sixthServiceCompatibility = (string)file_get_contents($root . '/database/migrations/052_restore_sixth_homepage_service.sql');
        $lcpMediaCompatibility = (string)file_get_contents($root . '/database/migrations/054_optimize_homepage_lcp_media.sql');

        $assert(str_contains($workflow, 'Deploy to Active24 (production)'));
        $assert(str_contains($workflow, 'environment: production'));
        $assert(str_contains($workflow, 'server-dir: /'));
        foreach (['FTP_SERVER', 'FTP_USERNAME', 'FTP_PASSWORD'] as $secretName) {
            $assert(str_contains($workflow, 'secrets.' . $secretName));
        }
        $assert(str_contains($workflow, 'protocol: ftps'));
        $assert(str_contains($workflow, "--exclude='.env'"));
        $assert(str_contains($workflow, "--exclude='storage/media/'"));
        $assert(str_contains($workflow, "--exclude='storage/published/'"));
        $assert(!str_contains($workflow, 'resetBaseState') && !str_contains($workflow, 'resetToDemo'));
        $assert(str_contains($placeholder, 'http_response_code(404)'));
        $assert(str_contains($utility, "(new PublishService())->publishSite"));
        $assert(str_contains($utility, "(string)(\$_GET['publish'] ?? '') === '1'"));
        $assert(str_contains($utility, 'Úplný publish selhal'));
        $assert(str_contains($utility, 'ApplicationCompatibleMigrationService'));
        $assert(str_contains($applicationMigrations, '050_restore_missing_production_seo_pages.sql'));
        $assert(str_contains($applicationMigrations, 'restoreProductionSeoContent'));
        $assert(str_contains($demoSeeder, 'public function restoreProductionSeoContent'));
        $assert(str_contains($demoSeeder, '$this->seedServicePages($siteId, $site)'));
        $assert(str_contains($demoSeeder, '$this->seedPageSeo($siteId, $site)'));
        $assert(str_contains($seoCompatibility, 'WHERE 1 = 0'));
        $assert(str_contains($applicationMigrations, '051_restore_homepage_about_visual.sql'));
        $assert(str_contains($applicationMigrations, 'restoreHomepageAboutVisual'));
        $assert(str_contains($demoSeeder, "replaceHomepageAboutVisualWithWeare5") && str_contains($demoSeeder, "'weare5.jpg'"));
        $assert(str_contains($applicationMigrations, '086_replace_homepage_about_visual_with_weare5.sql'));
        $assert(str_contains($applicationMigrations, '087_apply_approved_site_defaults_and_about_profile.sql'));
        $assert(str_contains($demoSeeder, "'company_registration_id' => '29719194'")
            && str_contains($demoSeeder, "'default_hero_video_url' => 'https://vimeo.com/1229273025'")
            && str_contains($demoSeeder, "'default_hero_video_url_mobile' => 'https://vimeo.com/1228952734'")
            && str_contains($demoSeeder, "'aboutuszdenek.jpg'")
            && str_contains($demoSeeder, "'https://vimeo.com/1228181473'"));
        $assert(str_contains($demoSeeder, "'jb-about-visual__media'"));
        $assert(str_contains($aboutVisualCompatibility, 'WHERE 1 = 0'));
        $assert(str_contains($applicationMigrations, '052_restore_sixth_homepage_service.sql'));
        $assert(str_contains($applicationMigrations, 'restoreSixthService'));
        $assert(str_contains($demoSeeder, 'public function restoreSixthService'));
        $assert(str_contains($demoSeeder, "'Letecké záběry dronem'"));
        $assert(str_contains($sixthServiceCompatibility, 'WHERE 1 = 0'));
        $assert(str_contains($applicationMigrations, '054_optimize_homepage_lcp_media.sql'));
        $assert(str_contains($applicationMigrations, 'optimizeHomepageLcpMedia'));
        $assert(str_contains($demoSeeder, 'public function optimizeHomepageLcpMedia'));
        $assert(str_contains($demoSeeder, "findByKey((int)\$homepage['id'], 'jb-about-visual__media')"));
        $assert(str_contains($lcpMediaCompatibility, 'SELECT 1'));
        $assert(!preg_match('/\b(?:ALTER|CREATE|DROP|TRUNCATE|UPDATE|DELETE|INSERT)\b/i', preg_replace('/--.*$/m', '', $lcpMediaCompatibility) ?? ''));
    });

    $test('blog articles expose visible team authorship and matching structured data', function () use ($assert, $root): void {
        $front = (string)file_get_contents($root . '/public/index.php');
        $data = (string)file_get_contents($root . '/app/ViewModels/BlogPostData.php');
        $coreView = (string)file_get_contents($root . '/app/Views/archetypes/blog-detail.phtml');
        $themeView = (string)file_get_contents($root . '/Templates/golden-bash/views/archetypes/blog-detail.phtml');

        $assert(str_contains($data, "'author' => 'RB Videos & Photos - Zdeněk a Tomáš'"));
        $assert(str_contains($coreView, 'Autor:') && str_contains($themeView, 'Autor:'));
        $assert(substr_count($front, "'author' => [") >= 2);
        $assert(str_contains($front, "'@id' => absolutePublicUrl(\$site, '/#organization')"));
    });

    $test('mobile homepage disables compressed artboard and enforces readable controls', function () use ($assert, $root): void {
        $css = (string)file_get_contents($root . '/Templates/golden-bash/assets/theme.css');

        $assert(str_contains($css, 'Mobile accessibility guard'));
        $assert(str_contains($css, 'min-height:44px'));
        $assert(str_contains($css, 'font-size:16px'));
        $assert(str_contains($css, '@media(max-width:700px) and (min-width:701px)'));
    });
}
