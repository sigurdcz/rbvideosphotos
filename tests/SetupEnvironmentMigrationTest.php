<?php

declare(strict_types=1);

use App\Services\ApplicationCompatibleMigrationService;
use App\Repositories\SiteRepository;
use App\Services\DemoContentSeeder;

function register_setup_environment_migration_tests(callable $test, callable $assert): void
{
    $test('Armagedon defers restricted migrations until after the base seed', function () use ($assert): void {
        $root = dirname(__DIR__);
        $serviceSource = (string)file_get_contents($root . '/app/Services/SetupEnvironmentService.php');
        $migrationServiceSource = (string)file_get_contents($root . '/app/Services/ApplicationCompatibleMigrationService.php');
        $maintenanceSource = (string)file_get_contents($root . '/migrate-util.php');
        $restrictedSql = (string)file_get_contents($root . '/database/migrations/048_restore_complete_pricing_content.sql');

        $policy = new ApplicationCompatibleMigrationService();
        foreach ([
            '048_restore_complete_pricing_content.sql',
            '050_restore_missing_production_seo_pages.sql',
            '051_restore_homepage_about_visual.sql',
            '052_restore_sixth_homepage_service.sql',
            '054_optimize_homepage_lcp_media.sql',
            '055_apply_content_seo_remediation.sql',
            '085_replace_homepage_about_visual_with_weare4.sql',
            '086_replace_homepage_about_visual_with_weare5.sql',
            '087_apply_approved_site_defaults_and_about_profile.sql',
            '095_set_first_portfolio_cover_weare5.sql',
            '097_replace_first_service_visual_with_weare5.sql',
            '098_revert_first_portfolio_cover_weare5.sql',
            '101_replace_about_cameraman_video_fallback.sql',
            '102_replace_work_listing_hero.sql',
        ] as $migrationName) {
            $assert($policy->supports($migrationName), 'Missing application-compatible migration: ' . $migrationName);
        }
        $assert(!$policy->supports('049_apply_pricing_without_temporary_tables.sql'));

        $planAt = strpos($serviceSource, '$migrationPlan = $this->runMigrations()');
        $seedAt = strpos($serviceSource, '$seedOutput = $this->runBaseSeed()');
        $deferredAt = strpos($serviceSource, '$migrations = $this->applyDeferredMigrations(');
        $assert($planAt !== false && $seedAt !== false && $deferredAt !== false);
        $assert($planAt < $seedAt && $seedAt < $deferredAt, 'Restricted migrations must run only after the base seed creates the site.');
        $assert(str_contains($serviceSource, '$applicationMigrations->supports($migrationName)'));
        $assert(str_contains($serviceSource, '!$applicationMigrations->requiresExistingDemoContent($migrationName)'));
        $assert($policy->requiresExistingDemoContent('055_apply_content_seo_remediation.sql'));
        $assert($policy->requiresExistingDemoContent('095_set_first_portfolio_cover_weare5.sql'), 'Clean reset: Demo seed owns the portfolio cover, 095 only records its marker.');
        $assert($policy->requiresExistingDemoContent('097_replace_first_service_visual_with_weare5.sql'), 'Clean reset: Demo seed owns the first service image, 097 only records its marker.');
        $assert($policy->requiresExistingDemoContent('098_revert_first_portfolio_cover_weare5.sql'), 'Clean reset: Demo seed owns the portfolio gallery, 098 only records its marker.');
        $assert($policy->requiresExistingDemoContent('101_replace_about_cameraman_video_fallback.sql'), 'Clean reset: current seeds own Tomas video fallback, 101 only records its marker.');
        $assert($policy->requiresExistingDemoContent('102_replace_work_listing_hero.sql'), 'Clean reset: current seeds own the work listing hero, 102 only records its marker.');
        $assert(!$policy->requiresExistingDemoContent('054_optimize_homepage_lcp_media.sql'));
        $assert(str_contains($serviceSource, '$result = $applicationMigrations->apply((string)$migrationName)'));
        $assert(strpos($serviceSource, '$this->markMigrationApplied((string)$migrationName)') > strpos($serviceSource, '$result = $applicationMigrations->apply((string)$migrationName)'));

        $assert(str_contains($restrictedSql, 'CREATE TEMPORARY TABLE tmp_pricing_blocks'));
        $assert(str_contains($migrationServiceSource, 'restoreRealPricingContent'));
        $assert(str_contains($maintenanceSource, 'new ApplicationCompatibleMigrationService()'));
        $assert(!str_contains($maintenanceSource, "if (\$name === '048_restore_complete_pricing_content.sql')"));
    });

    $test('unsupported application migration fails explicitly', function () use ($assert): void {
        $thrown = false;
        try {
            (new ApplicationCompatibleMigrationService())->apply('999_unknown.sql');
        } catch (InvalidArgumentException $e) {
            $thrown = str_contains($e->getMessage(), '999_unknown.sql');
        }
        $assert($thrown, 'Unknown application migration must fail before any database access.');
    });

    $test('application migration dispatcher invokes every restricted-host handler', function () use ($assert): void {
        $sites = new class extends SiteRepository {
            public function __construct() {}
            public function findFirst(): ?array { return ['id' => 42]; }
        };
        $seeder = new class extends DemoContentSeeder {
            public array $calls = [];
            public function __construct() {}
            public function restoreRealPricingContent(int $siteId): void { $this->calls[] = ['pricing', $siteId]; }
            public function restoreProductionSeoContent(int $siteId): void { $this->calls[] = ['seo', $siteId]; }
            public function restoreHomepageAboutVisual(int $siteId): void { $this->calls[] = ['about', $siteId]; }
            public function replaceHomepageAboutVisualWithWeare5(int $siteId): void { $this->calls[] = ['about-weare5', $siteId]; }
            public function applyApprovedSiteDefaultsAndAboutProfile(int $siteId): void { $this->calls[] = ['approved-defaults', $siteId]; }
            public function replaceFirstServiceVisualWithWeare5(int $siteId): void { $this->calls[] = ['service-weare5', $siteId]; }
            public function replaceAboutCameramanFallback(int $siteId): void { $this->calls[] = ['tomas-fallback', $siteId]; }
            public function replaceWorkListingHero(int $siteId): void { $this->calls[] = ['work-hero', $siteId]; }
            public function restoreSixthService(int $siteId): void { $this->calls[] = ['service', $siteId]; }
            public function optimizeHomepageLcpMedia(int $siteId): int { $this->calls[] = ['lcp', $siteId]; return 6; }
            public function restoreContentSeoRemediation(int $siteId): void { $this->calls[] = ['content-seo', $siteId]; }
        };
        $dispatcher = new ApplicationCompatibleMigrationService($sites, $seeder);

        $assert($dispatcher->apply('048_restore_complete_pricing_content.sql') === 'application-compatible');
        $assert($dispatcher->apply('050_restore_missing_production_seo_pages.sql') === 'application-compatible');
        $assert($dispatcher->apply('051_restore_homepage_about_visual.sql') === 'application-compatible');
        $assert($dispatcher->apply('052_restore_sixth_homepage_service.sql') === 'application-compatible');
        $assert($dispatcher->apply('054_optimize_homepage_lcp_media.sql') === 'application-compatible:6-variants');
        $assert($dispatcher->apply('055_apply_content_seo_remediation.sql') === 'application-compatible');
        $assert($dispatcher->apply('085_replace_homepage_about_visual_with_weare4.sql') === 'application-compatible');
        $assert($dispatcher->apply('086_replace_homepage_about_visual_with_weare5.sql') === 'application-compatible');
        $assert($dispatcher->apply('087_apply_approved_site_defaults_and_about_profile.sql') === 'application-compatible');
        $assert($dispatcher->apply('097_replace_first_service_visual_with_weare5.sql') === 'application-compatible');
        $assert($dispatcher->apply('101_replace_about_cameraman_video_fallback.sql') === 'application-compatible');
        $assert($dispatcher->apply('102_replace_work_listing_hero.sql') === 'application-compatible');
        // 095 was reverted by 098; its handler stays a deliberate no-op (no seeder call, no DB access).
        $assert($dispatcher->apply('095_set_first_portfolio_cover_weare5.sql') === 'application-compatible');
        $assert(in_array(['about-weare5', 42], $seeder->calls, true));
        $assert($seeder->calls === [
            ['pricing', 42],
            ['seo', 42],
            ['about', 42],
            ['service', 42],
            ['lcp', 42],
            ['content-seo', 42],
            ['about', 42],
            ['about-weare5', 42],
            ['approved-defaults', 42],
            ['service-weare5', 42],
            ['tomas-fallback', 42],
            ['work-hero', 42],
        ]);
    });

    $test('first service card uses weare5 in Demo seed and migration 097', function () use ($assert): void {
        $root = dirname(__DIR__);
        $seeder = (string)file_get_contents($root . '/app/Services/DemoContentSeeder.php');
        $migration = (string)file_get_contents($root . '/database/migrations/097_replace_first_service_visual_with_weare5.sql');

        $assert(DemoContentSeeder::FIRST_SERVICE_VISUAL === 'weare5.jpg');
        $assert(DemoContentSeeder::FIRST_SERVICE_TITLE === 'Svatební den - foto + video');
        $assert(is_file($root . '/database/seeds/demo_assets/' . DemoContentSeeder::FIRST_SERVICE_VISUAL), 'Service image source weare5.jpg is missing.');
        $assert(str_contains($seeder, "'title' => self::FIRST_SERVICE_TITLE"), 'Seeded service and migration must share the title constant.');
        // Seed and migration share one writer that finds the service by title/detail URL, not by id or position only.
        $assert(substr_count($seeder, '$this->assignFirstServiceVisual($siteId, $imageUrl)') === 2, 'Seed and migration must share assignFirstServiceVisual().');
        $assert(str_contains($seeder, 'WHERE site_id = ? AND (title = ? OR button_url = ?)'));
        $assert(!str_contains($seeder, "\$this->demoMedia['weare2.png']"), 'Seed must not put weare2.png back on the first service.');
        // Media is resolved by original_filename / ingested through the upload pipeline, never by a hardcoded id.
        $assert(str_contains($seeder, '$this->ingestDemoAsset($siteId, self::FIRST_SERVICE_VISUAL, self::FIRST_SERVICE_TITLE)'));
        $assert(!preg_match('/media\s+WHERE\s+id\s*=\s*179/i', $seeder . $migration));
        // Stored service HTML is reset only when filled and autofill stays enabled.
        $assert(str_contains($seeder, "AND autofill_disabled = 0 AND content IS NOT NULL AND content <> ''"));
        $assert(str_contains($migration, 'WHERE 1 = 0'), 'Migration 097 is an application-compatible marker.');
    });

    $test('Tomas About video fallback uses a real demo image through application migration 101', function () use ($assert): void {
        $root = dirname(__DIR__);
        $seeder = (string)file_get_contents($root . '/app/Services/DemoContentSeeder.php');
        $migration = (string)file_get_contents($root . '/database/migrations/101_replace_about_cameraman_video_fallback.sql');

        $assert(DemoContentSeeder::ABOUT_CAMERAMAN_FALLBACK_ASSET === 'fallback tomáš.png');
        $assert(is_file($root . '/database/seeds/demo_assets/' . DemoContentSeeder::ABOUT_CAMERAMAN_FALLBACK_ASSET));
        $assert(str_contains($seeder, '$this->ingestDemoAsset('));
        $assert(str_contains($seeder, "'about_cameraman_image'"));
        $assert(str_contains($migration, 'WHERE 1 = 0'), 'Migration 101 must remain an application-compatible marker.');
    });

    $test('Nase prace hero uses the supplied Media Library image through application migration 102', function () use ($assert): void {
        $root = dirname(__DIR__);
        $seeder = (string)file_get_contents($root . '/app/Services/DemoContentSeeder.php');
        $migration = (string)file_get_contents($root . '/database/migrations/102_replace_work_listing_hero.sql');

        $assert(DemoContentSeeder::WORK_LISTING_HERO_ASSET === 'Snímek obrazovky 2026-10-06 132148.png');
        $assert(is_file($root . '/database/seeds/demo_assets/' . DemoContentSeeder::WORK_LISTING_HERO_ASSET));
        $assert(str_contains($seeder, 'replaceWorkListingHero'));
        $assert(str_contains($seeder, "'hero_bg'"));
        $assert(str_contains($migration, 'WHERE 1 = 0'), 'Migration 102 must remain an application-compatible marker.');
    });

}
