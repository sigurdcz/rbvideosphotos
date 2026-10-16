<?php

declare(strict_types=1);

function register_demo_content_batch_contract_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);

    $test('demo content admin uses persistent browser-driven batches', function () use ($assert, $root): void {
        $service = (string)file_get_contents($root . '/app/Services/DemoContentBatchService.php');
        $controller = (string)file_get_contents($root . '/app/Controllers/SetupController.php');
        $routes = (string)file_get_contents($root . '/admin/index.php');
        $view = (string)file_get_contents($root . '/admin/views/setup/index.php');
        $migration = (string)file_get_contents($root . '/database/migrations/063_create_demo_seed_jobs.sql');

        foreach (['validate', 'base_media', 'wedding_items', 'portfolio_items', 'publish', 'fingerprint'] as $phase) {
            $assert(str_contains($service, "'{$phase}'"), 'Batch service is missing phase ' . $phase . '.');
        }
        $assert(str_contains($service, 'lock_expires_at') && str_contains($service, 'completed_units')
            && str_contains($service, 'markFailed'), 'Batch service must persist progress, lock concurrent requests and expose failures.');
        $assert(str_contains($routes, '/admin/setup/demo-content/start')
            && str_contains($routes, '/admin/setup/demo-content/step')
            && str_contains($routes, '/admin/setup/demo-content/status')
            && str_contains($routes, '/admin/setup/demo-content/retry'), 'Admin routes do not expose the full batch lifecycle.');
        $assert(str_contains($controller, '$this->csrfCheck()')
            && str_contains($controller, '$this->requireAdmin()'), 'Batch mutations must retain admin and CSRF protection.');
        $assert(str_contains($view, 'role="progressbar"')
            && str_contains($view, 'Dávka ${value.completed_units} z ${value.total_units}')
            && str_contains($view, 'Opakovat poslední dávku')
            && str_contains($view, 'loadStatus(true)'), 'Setup UI must show progress, retry failures and resume after reload.');
        $assert(str_contains($migration, 'UNIQUE KEY uq_demo_seed_jobs_site')
            && str_contains($migration, 'last_error TEXT')
            && str_contains($migration, 'lock_expires_at DATETIME'), 'Append-only job schema is missing concurrency or failure state.');
    });

    $test('demo importers expose retry-safe bounded items', function () use ($assert, $root): void {
        $wedding = (string)file_get_contents($root . '/app/Services/WeddingBlogDemoContentImporter.php');
        $portfolio = (string)file_get_contents($root . '/app/Services/PortfolioDemoContentImporter.php');
        $seeder = (string)file_get_contents($root . '/app/Services/DemoContentSeeder.php');
        $assert(str_contains($wedding, 'importBatchItem') && str_contains($wedding, 'preflight'), 'Wedding import is still monolithic.');
        $assert(str_contains($portfolio, 'importBatchItem') && str_contains($portfolio, 'preflight')
            && str_contains($portfolio, "=== \$record['slug']"), 'Portfolio batches must replace their own slug safely on retry.');
        $assert(str_contains($seeder, 'seedDemoAssetBatchItem') && str_contains($seeder, 'runBatchContentPhase'), 'Base media and content phases are not batch-addressable.');
    });
}
