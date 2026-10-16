<?php

declare(strict_types=1);

function register_company_registration_id_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);

    $test('company registration ID is append-only, validated and visible on contact', function () use ($assert, $root): void {
        $migration = (string) file_get_contents($root . '/database/migrations/039_add_company_registration_id.sql');
        $schema = (string) file_get_contents($root . '/database/schema.sql');
        $settings = (string) file_get_contents($root . '/admin/views/settings/index.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/SettingsController.php');
        $viewModel = (string) file_get_contents($root . '/app/ViewModels/KontaktPageData.php');
        $contactView = (string) file_get_contents($root . '/app/Views/archetypes/kontakt.phtml');

        $assert(str_contains($migration, 'ADD COLUMN `company_registration_id`'));
        $assert(str_contains($schema, '`company_registration_id` VARCHAR(20)'));
        $assert(str_contains($settings, 'name="company_registration_id"') && str_contains($settings, 'pattern="[0-9]{8}"'));
        $assert(str_contains($controller, "preg_match('/^\\d{8}$/', \$companyRegistrationId)"));
        $assert(str_contains($viewModel, "'type' => 'company'"));
        $assert(str_contains($contactView, 'ci-card--company') && str_contains($contactView, 'Identifikační číslo firmy'));
    });

    $test('homepage FAQ navigation is repaired by a new migration', function () use ($assert, $root): void {
        $migration = (string) file_get_contents($root . '/database/migrations/040_fix_homepage_faq_navigation.sql');
        $assert(str_contains($migration, "REPLACE(`layout_nav_home`, 'href=\"#faq\"', 'href=\"/faq\"')"));
    });
}
