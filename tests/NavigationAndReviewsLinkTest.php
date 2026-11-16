<?php

declare(strict_types=1);

use App\Services\Render\NavigationNormalizer;

function register_navigation_and_reviews_link_tests(callable $test, callable $assert): void
{
    $test('homepage work navigation targets its section while subpages keep the listing route', function () use ($assert): void {
        $seed = (string)file_get_contents(dirname(__DIR__) . '/database/seeds/seed.php');
        preg_match('~\$layoutNavHome = <<<\'NAV\'\R(.*?)\RNAV;~s', $seed, $matches);
        $source = $matches[1] ?? '';
        $assert($source !== '', 'The real seeded homepage menu must be available.');
        $home = NavigationNormalizer::forPage($source, true);
        $sub = NavigationNormalizer::forPage($source, false);

        $assert(str_contains($home, 'href="#nase-prace"'), 'Homepage must link to its own portfolio section.');
        $assert($home === str_replace('href="/nase-prace"', 'href="#nase-prace"', $source), 'Other menu links and markup must be preserved.');
        $assert($sub === $source, 'Subpage navigation must retain its route URLs.');
        $assert(NavigationNormalizer::forPage($home, true) === $home, 'Repeated rendering must be idempotent.');

        $layout = (string)file_get_contents(dirname(__DIR__) . '/Templates/golden-bash-diagonal/layout.html');
        $rendered = (new \App\Services\PageRenderer())->applyLayout($layout, '<section id="nase-prace">Portfolio</section>', 'Test site', $home);
        $assert(str_contains($rendered, 'href="#nase-prace"') && str_contains($rendered, 'id="nase-prace"'), 'The actual theme layout must retain the section link and target.');
        $assert(str_contains($rendered, 'href="/kontakt"'), 'The header contact action must not change.');
    });

    $test('homepage work navigation preserves explicit destinations and supports stored quote variants', function () use ($assert): void {
        $source = <<<'HTML'
<nav><a href='/nase-prace/'>Work</a><a class="work" href = "/nase-prace">Work</a><a href="#nase-prace">Section</a><a href="/#nase-prace">Home section</a><a href="/nase-prace#videogalerie">Videos</a><a href="/nase-prace?filter=video">Filtered</a><a href="https://example.org/nase-prace">External</a><a data-href="/nase-prace" href="/blog">Blog</a><a href="/o-nas">About</a><a href="/hodnoceni">Reviews</a></nav>
HTML;
        $expected = str_replace(
            ["href='/nase-prace/'", 'href = "/nase-prace"'],
            ["href='#nase-prace'", 'href="#nase-prace"'],
            $source
        );
        $assert(NavigationNormalizer::forPage($source, true) === $expected);
        $assert(NavigationNormalizer::forPage($source, false) === $source);
        $assert(NavigationNormalizer::forPage('', true) === '');
    });

    $test('navigation normalizer adds Reference before the contact CTA once', function () use ($assert): void {
        $source = '<nav><div class="nav-links"><a href="/sluzby">Služby</a><a href="/kontakt" class="nav-cta">Kontakt</a></div></nav>';
        $normalized = NavigationNormalizer::ensureReviewsLink($source);

        $assert(substr_count($normalized, 'href="/hodnoceni"') === 1);
        $assert(strpos($normalized, 'href="/hodnoceni"') < strpos($normalized, 'href="/kontakt"'));
        $assert(NavigationNormalizer::ensureReviewsLink($normalized) === $normalized);
    });

    $test('reviews page uses a database URL and safe external link contract', function () use ($assert): void {
        $root = dirname(__DIR__);
        $view = (string)file_get_contents($root . '/app/Views/archetypes/reviews.phtml');
        $repository = (string)file_get_contents($root . '/app/Repositories/ContactRepository.php');
        $seeder = (string)file_get_contents($root . '/app/Services/DemoContentSeeder.php');
        $migration = (string)file_get_contents($root . '/database/migrations/045_update_google_reviews_url.sql');
        $repairMigration = (string)file_get_contents($root . '/database/migrations/056_restore_google_reviews_url.sql');

        $assert(str_contains($view, '$reviewsUrl'));
        $assert(str_contains($view, 'class="reviews-page-list"'));
        $assert(!str_contains($view, '<div class="reviews-page-grid">'), 'The archetype must not duplicate the CMS grid wrapper.');
        $assert(str_contains($view, 'rel="noopener noreferrer external"'));
        $assert(substr_count($view, 'href="<?= $this->e($reviewsUrl) ?>"') === 2, 'Both Google review actions must use the same database URL.');
        $assert(str_contains($view, 'reviews-google-panel--add') && str_contains($view, 'Přidat recenzi'), 'Reviews page is missing the add-review CTA section.');
        $assert(!str_contains($view, 'mi3f1qMJENJY2bVN7'), 'The public URL must not be hardcoded in the template.');
        $assert(str_contains($repository, "'reviews_url'"));
        $assert(str_contains($repository, "DEFAULT_REVIEWS_URL = 'https://maps.app.goo.gl/DN9Y1ohrSgAePg3n9'"));
        $assert(str_contains($seeder, "'reviews_url' => ContactRepository::DEFAULT_REVIEWS_URL"), 'Demo seed must not clear the canonical Google reviews URL.');
        $assert(str_contains($migration, 'https://maps.app.goo.gl/DN9Y1ohrSgAePg3n9'));
        $assert(str_contains($repairMigration, 'https://maps.app.goo.gl/DN9Y1ohrSgAePg3n9') && str_contains($repairMigration, 'TRIM(`reviews_url`) = \'\''), 'Append-only migration must restore only missing Google review URLs.');
    });

    $test('homepage hero label is removed from current seeds and existing installations', function () use ($assert): void {
        $root = dirname(__DIR__);
        $seed = (string)file_get_contents($root . '/database/seeds/seed.php');
        $demoSeeder = (string)file_get_contents($root . '/app/Services/DemoContentSeeder.php');
        $migration = (string)file_get_contents($root . '/database/migrations/061_remove_homepage_hero_label.sql');

        $assert(!str_contains($seed, '<p data-block="hero_label" class="section-label">'), 'Base seed still renders the redundant homepage hero label.');
        $assert(str_contains($demoSeeder, 'removeHomepageHeroLabel($homepage)'));
        $assert(str_contains($demoSeeder, "id=([\"\\'])uvod"), 'Demo reseed does not target the homepage hero explicitly.');
        $assert(str_contains($migration, '061_remove_homepage_hero_label.sql') === false, 'Migration must contain SQL, not its own filename.');
        $assert(str_contains($migration, 'hero_label') && str_contains($migration, "p.url_path IN ('', '/')"));
    });
}
