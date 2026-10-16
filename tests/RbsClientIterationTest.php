<?php

declare(strict_types=1);

function register_rbs_client_iteration_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);

    $test('RBS client iteration uses shared editable sources and deterministic portfolio groups', function () use ($assert, $root): void {
        $migration = (string)file_get_contents($root . '/database/migrations/065_add_portfolio_type_and_shared_sections.sql');
        $repo = (string)file_get_contents($root . '/app/Repositories/PortfolioRepository.php');
        $listing = (string)file_get_contents($root . '/app/ViewModels/PortfolioListingData.php');
        $admin = (string)file_get_contents($root . '/admin/views/portfolio/form.php');
        $routes = (string)file_get_contents($root . '/admin/index.php');
        $layout = (string)file_get_contents($root . '/Templates/golden-bash/layout.html');
        $footer = (string)file_get_contents($root . '/Templates/golden-bash/views/partials/footer.phtml');
        $contact = (string)file_get_contents($root . '/app/Views/partials/home-contact.phtml');
        $public = (string)file_get_contents($root . '/public/index.php');

        $assert(str_contains($migration, 'ADD COLUMN `content_type`') && str_contains($migration, 'CREATE TABLE IF NOT EXISTS `shared_sections`'));
        // The hand-typed number decides the listing order now; grouping by content
        // type survives only as the tiebreak inside one number (interleaveByContentType).
        $assert(str_contains($repo, 'ORDER BY (sort_order = 0) ASC, sort_order ASC, id ASC') && !str_contains($repo, 'content_type ASC'));
        $assert(str_contains($listing, "'photoEntries'") && str_contains($listing, "'videoEntries'"));
        $assert(str_contains($admin, 'name="content_type"') && str_contains($admin, 'name="sort_order"'));
        $assert(str_contains($routes, "'/admin/shared-sections'"));
        $assert(!str_contains(strtolower($layout . $footer), 'wedding stories'));
        $assert(str_contains($contact, 'hp-contact-visual'));
        $assert(substr_count($public, '$html = injectSiteLogo($html, $site);') === 7);
        $assert(str_contains($public, 'function tryServeServicesPage(string $uri): bool'));
        $assert(str_contains($public, 'function tryServePortfolioPage(string $uri, bool $detailOnly = false): bool'));
        $assert(str_contains($public, 'function tryServeKontaktPage(): bool'));
        $assert(str_contains($public, 'function tryServeBlogPostPage(string $uri): bool'));
    });
}
