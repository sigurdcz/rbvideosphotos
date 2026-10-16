<?php

declare(strict_types=1);

use App\Services\SeoRenderer;
use App\Services\Render\MediaUrlResolver;

function register_og_image_settings_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);

    $test('SEO renderer emits explicit absolute social image URLs', function () use ($assert): void {
        $html = (new SeoRenderer())->renderHead(
            [
                'meta_title' => 'Test page',
                'robots_index' => 1,
                'robots_follow' => 1,
            ],
            ['name' => 'Test site', 'domain' => 'https://example.test'],
            'https://example.test/test',
            'https://example.test/media/og/test-og.webp',
            'https://example.test/media/og/test-twitter.webp'
        );

        $assert(str_contains($html, '<meta property="og:image" content="https://example.test/media/og/test-og.webp">'));
        $assert(str_contains($html, '<meta name="twitter:image" content="https://example.test/media/og/test-twitter.webp">'));
    });

    $test('social media resolver publishes storage images through the public media endpoint', function () use ($assert): void {
        $url = MediaUrlResolver::absolutePublicMediaUrl(
            'storage/media/2026/08/example_og.webp',
            'https://example.test/'
        );

        $assert($url === 'https://example.test/media/2026/08/example_og.webp');
        $assert(!str_contains($url, '/storage/media/'));
    });

    $test('LocalBusiness JSON-LD image follows the resolved Facebook Open Graph image', function () use ($assert): void {
        $html = (new SeoRenderer())->renderHead(
            [
                'meta_title' => 'Test page',
                'robots_index' => 1,
                'robots_follow' => 1,
                'schema_type' => 'LocalBusiness',
                'schema_name' => 'Test business',
            ],
            ['name' => 'Test site', 'domain' => 'https://example.test'],
            'https://example.test/test',
            '/media/og/facebook.webp',
            'https://example.test/media/og/twitter.webp'
        );

        $assert(str_contains($html, '"image": "https://example.test/media/og/facebook.webp"'));
        $assert(!str_contains($html, 'https://example.test/media/old.webp'));
        $assert(!str_contains($html, '"image": "https://example.test/media/og/twitter.webp"'));
        $assert(str_contains($html, 'data-cms-organization-schema'));
        $ogUsageCount = substr_count($html, 'https://example.test/media/og/facebook.webp');
        $assert($ogUsageCount === 3, 'OG meta and both LocalBusiness schemas must use the same selected OG image; count=' . $ogUsageCount . '.');
    });

    $test('explicit schema image URL wins over the OG image in the JSON-LD payload', function () use ($assert): void {
        $html = (new SeoRenderer())->renderHead(
            [
                'meta_title' => 'Test page',
                'robots_index' => 1,
                'robots_follow' => 1,
                'schema_type' => 'WebPage',
                'schema_name' => 'Test page schema',
            ],
            ['name' => 'Test site', 'domain' => 'https://example.test'],
            'https://example.test/test',
            '/media/og/facebook.webp',
            'https://example.test/media/og/twitter.webp',
            '/media/og/schema-specific.webp'
        );

        $assert(str_contains($html, '"image": "https://example.test/media/og/schema-specific.webp"'));
        $assert(!str_contains($html, '"image": "https://example.test/media/og/facebook.webp"'));
    });

    $test('global OG image setting is media-ID based and validated', function () use ($assert, $root): void {
        $migration = (string)file_get_contents($root . '/database/migrations/026_add_platform_social_images.sql');
        $settings = (string)file_get_contents($root . '/admin/views/settings/index.php');
        $controller = (string)file_get_contents($root . '/app/Controllers/SettingsController.php');
        $siteRepository = (string)file_get_contents($root . '/app/Repositories/SiteRepository.php');

        $assert(str_contains($migration, 'default_instagram_image_id') && str_contains($migration, 'default_twitter_image_id'));
        $assert(substr_count($migration, 'ON DELETE SET NULL') === 2);
        $assert(str_contains($settings, 'name="default_og_image_id"'));
        $assert(str_contains($settings, 'name="default_instagram_image_id"'));
        $assert(str_contains($settings, 'name="default_twitter_image_id"'));
        $assert(str_contains($settings, '1200 &times; 630'));
        $assert(str_contains($settings, '1080 &times; 1080'));
        $assert(str_contains($settings, '1200 &times; 600'));
        $assert(str_contains($settings, 'webove OG tagy jej automaticky nepublikuji'));
        $assert(str_contains($settings, 'data-brand-value-mode="id"'));
        $assert(str_contains($controller, 'validatedImageMediaId'));
        $assert(str_contains($controller, "str_starts_with((string)(\$media['mime_type'] ?? ''), 'image/')"));
        $assert(str_contains($controller, "(int)(\$media['site_id'] ?? 0) !== \$siteId"));
        $assert(str_contains($siteRepository, "'default_og_image_id'"));
        $assert(str_contains($siteRepository, "'default_instagram_image_id'"));
        $assert(str_contains($siteRepository, "'default_twitter_image_id'"));
    });

    $test('live and publish rendering honor page override before global OG fallback', function () use ($assert, $root): void {
        $public = (string)file_get_contents($root . '/public/index.php');
        $publish = (string)file_get_contents($root . '/app/Services/PublishService.php');
        $portfolioPublish = (string)file_get_contents($root . '/app/Services/PortfolioPublishService.php');
        $seoController = (string)file_get_contents($root . '/app/Controllers/SeoController.php');
        $legacyTwitterRelease = (string)file_get_contents($root . '/database/migrations/027_release_legacy_twitter_image_duplicates.sql');

        $assert(str_contains($public, "\$seo['og_image_id'] ?? \$site['default_og_image_id']"));
        $assert(str_contains($public, "\$seo['twitter_image_id']\n            ?? \$site['default_twitter_image_id']\n            ?? \$seo['og_image_id']"));
        $assert(str_contains($public, "\$pageSeo['og_image_id']"));
        $assert(str_contains($public, "resolveSocialImageUrl(\$mediaRepo, \$site['default_og_image_id'], \$site)"));
        $assert(substr_count($public, "\$html = injectPageHeadMetadata(\$html, \$site, [") >= 7);
        $assert(str_contains($public, "'path' => '/nase-prace'"));
        $assert(str_contains($public, "'og_image' => \$detailData['entry']['hero_image']"));
        $assert(substr_count($public, 'normalizeCanonicalUrl($ogImage, $site, $path)') === 1);
        $assert(substr_count($public, 'normalizeCanonicalUrl($twitterImage, $site, $path)') === 1);
        $assert(str_contains($public, 'cms-social-image-'));
        $assert(str_contains($public, 'cms-structured-data-'));
        $assert(str_contains($public, 'renderOrganizationSchema($site, $ogImage !== \'\' ? $ogImage : null)'));
        $assert(str_contains($public, 'application/ld\\+json'));
        $assert(str_contains($public, 'strtr($html, $socialImageTags)'));
        $assert(str_contains($publish, "\$seo['og_image_id'] ?? \$site['default_og_image_id']"));
        $assert(str_contains($publish, "\$seo['twitter_image_id']\n            ?? \$site['default_twitter_image_id']\n            ?? \$seo['og_image_id']"));
        $assert(str_contains($portfolioPublish, '(new SeoRenderer())->renderHead'));
        $assert(str_contains($portfolioPublish, "\$site['default_twitter_image_id'] ?? \$site['default_og_image_id']"));
        $assert(str_contains($portfolioPublish, '<!--cms-portfolio-social-'));
        $assert(str_contains($legacyTwitterRelease, '`seo`.`twitter_image_id` = `seo`.`og_image_id`'));
        $assert(str_contains($legacyTwitterRelease, '`site`.`default_twitter_image_id` IS NOT NULL'));
        $assert(str_contains($seoController, 'validatedImageMediaId'));
    });
}
