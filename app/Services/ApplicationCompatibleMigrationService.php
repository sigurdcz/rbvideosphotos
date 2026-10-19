<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SiteRepository;

final class ApplicationCompatibleMigrationService
{
    private const SUPPORTED_MIGRATIONS = [
        '048_restore_complete_pricing_content.sql',
        '050_restore_missing_production_seo_pages.sql',
        '051_restore_homepage_about_visual.sql',
        '052_restore_sixth_homepage_service.sql',
        '054_optimize_homepage_lcp_media.sql',
        '055_apply_content_seo_remediation.sql',
        '067_fix_mix_manifest_model_count_copy.sql',
        '085_replace_homepage_about_visual_with_weare4.sql',
        '086_replace_homepage_about_visual_with_weare5.sql',
        '087_apply_approved_site_defaults_and_about_profile.sql',
        '095_set_first_portfolio_cover_weare5.sql',
        '097_replace_first_service_visual_with_weare5.sql',
        '098_revert_first_portfolio_cover_weare5.sql',
        '101_replace_about_cameraman_video_fallback.sql',
        '102_replace_work_listing_hero.sql',
        '106_replace_approved_video_portfolio.sql',
    ];

    private SiteRepository $sites;
    private DemoContentSeeder $seeder;

    public function __construct(?SiteRepository $sites = null, ?DemoContentSeeder $seeder = null)
    {
        $this->sites = $sites ?: new SiteRepository();
        $this->seeder = $seeder ?: new DemoContentSeeder();
    }

    public function supports(string $migrationName): bool
    {
        return in_array($migrationName, self::SUPPORTED_MIGRATIONS, true);
    }

    /**
     * Content-only upgrades need the complete demo dataset. During a clean
     * Armagedon reset the current DemoContentSeeder runs immediately after the
     * base seed, so the SQL marker is applied normally instead of dispatching
     * an upgrade against data that does not exist yet.
     */
    public function requiresExistingDemoContent(string $migrationName): bool
    {
        return in_array($migrationName, [
            '055_apply_content_seo_remediation.sql',
            '067_fix_mix_manifest_model_count_copy.sql',
            '095_set_first_portfolio_cover_weare5.sql',
            '097_replace_first_service_visual_with_weare5.sql',
            '098_revert_first_portfolio_cover_weare5.sql',
            '101_replace_about_cameraman_video_fallback.sql',
            '102_replace_work_listing_hero.sql',
        ], true);
    }

    public function apply(string $migrationName): string
    {
        if (!$this->supports($migrationName)) {
            throw new \InvalidArgumentException('Migrace nema application-compatible handler: ' . $migrationName);
        }

        $site = $this->sites->findFirst();
        if (!$site) {
            throw new \RuntimeException('Application-compatible migrace vyzaduje existujici web: ' . $migrationName);
        }

        $siteId = (int)$site['id'];
        $seeder = $this->seeder;

        return match ($migrationName) {
            '048_restore_complete_pricing_content.sql' => $this->applyWithoutResult(
                static fn() => $seeder->restoreRealPricingContent($siteId)
            ),
            '050_restore_missing_production_seo_pages.sql' => $this->applyWithoutResult(
                static fn() => $seeder->restoreProductionSeoContent($siteId)
            ),
            '051_restore_homepage_about_visual.sql' => $this->applyWithoutResult(
                static fn() => $seeder->restoreHomepageAboutVisual($siteId)
            ),
            '052_restore_sixth_homepage_service.sql' => $this->applyWithoutResult(
                static fn() => $seeder->restoreSixthService($siteId)
            ),
            '054_optimize_homepage_lcp_media.sql' => 'application-compatible:'
                . $seeder->optimizeHomepageLcpMedia($siteId)
                . '-variants',
            '055_apply_content_seo_remediation.sql' => $this->applyWithoutResult(
                static fn() => $seeder->restoreContentSeoRemediation($siteId)
            ),
            '067_fix_mix_manifest_model_count_copy.sql' => $this->applyWithoutResult(
                static fn() => (new PortfolioDemoContentImporter())->refreshMixManifestModelCopy($siteId)
            ),
            '085_replace_homepage_about_visual_with_weare4.sql' => $this->applyWithoutResult(
                static fn() => $seeder->restoreHomepageAboutVisual($siteId)
            ),
            '086_replace_homepage_about_visual_with_weare5.sql' => $this->applyWithoutResult(
                static fn() => $seeder->replaceHomepageAboutVisualWithWeare5($siteId)
            ),
            '087_apply_approved_site_defaults_and_about_profile.sql' => $this->applyWithoutResult(
                static fn() => $seeder->applyApprovedSiteDefaultsAndAboutProfile($siteId)
            ),
            // 095 dala weare5.jpg omylem do galerie prace kamila-a-matej a je uz nasazena
            // (append-only). Handler zustava jako zamerny no-op, aby 095 na ciste DB i pri
            // replayi porad prosla a SQL marker zustal netknuty; omyl na DB, kde 095 bezela,
            // vraci migrace 098.
            '095_set_first_portfolio_cover_weare5.sql' => $this->applyWithoutResult(
                static fn() => null
            ),
            '097_replace_first_service_visual_with_weare5.sql' => $this->applyWithoutResult(
                static fn() => $seeder->replaceFirstServiceVisualWithWeare5($siteId)
            ),
            '098_revert_first_portfolio_cover_weare5.sql' => $this->applyWithoutResult(
                static fn() => (new PortfolioDemoContentImporter())->revertMistakenWeare5Cover($siteId)
            ),
            '101_replace_about_cameraman_video_fallback.sql' => $this->applyWithoutResult(
                static fn() => $seeder->replaceAboutCameramanFallback($siteId)
            ),
            '102_replace_work_listing_hero.sql' => $this->applyWithoutResult(
                static fn() => $seeder->replaceWorkListingHero($siteId)
            ),
            '106_replace_approved_video_portfolio.sql' => $this->applyWithoutResult(
                static fn() => (new ApprovedVideoPortfolioService())->replace($siteId)
            ),
        };
    }

    private function applyWithoutResult(callable $handler): string
    {
        $handler();
        return 'application-compatible';
    }
}
