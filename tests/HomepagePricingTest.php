<?php

declare(strict_types=1);

use App\View\ViewFactory;
use App\ViewModels\HomepageData;

function register_homepage_pricing_tests(callable $test, callable $assert): void
{
    $test('homepage pricing renders feature bullets without prices', function () use ($assert): void {
        $cards = HomepageData::pricingCards([
            'cenik_card_1_title' => 'Testovací balíček',
            'cenik_card_1_price' => '99 999 Kč',
            'cenik_card_1_desc' => "Souhrn, který na homepage nechceme.\n\nObsahuje:\n• první služba\n• doprava za 7 Kč / km\n• druhá služba\n• třetí služba\n• čtvrtá služba\n• pátá služba",
        ]);

        $assert(!array_key_exists('price', $cards[0]), 'Homepage pricing data must not expose prices.');
        $assert($cards[0]['features'] === ['první služba', 'druhá služba', 'třetí služba', 'čtvrtá služba']);

        $html = ViewFactory::forTheme(null)->render('partials/home-pricing', ['cards' => $cards]);
        $assert(str_contains($html, 'class="hp-cenik-card-features"'));
        $assert(substr_count($html, '<li>') === 4);
        $assert(!str_contains($html, '99 999') && !str_contains($html, 'hp-cenik-card-price'));
        $assert(!str_contains($html, '7 Kč') && !str_contains($html, 'Kč'));
        $assert(!str_contains($html, 'Souhrn, který na homepage nechceme.'));
    });

    $test('homepage pricing turns a legacy summary into sentence bullets', function () use ($assert): void {
        $cards = HomepageData::pricingCards([
            'cenik_card_1_title' => 'Legacy',
            'cenik_card_1_desc' => 'První stručný bod. Druhý stručný bod. Třetí stručný bod.',
        ]);

        $assert($cards[0]['features'] === [
            'První stručný bod.',
            'Druhý stručný bod.',
            'Třetí stručný bod.',
        ]);
    });

    $test('existing databases restore seven real pricing packages while homepage stays price free', function () use ($assert): void {
        $root = dirname(__DIR__);
        $migration = (string)file_get_contents($root . '/database/migrations/048_restore_complete_pricing_content.sql');
        $homepageView = (string)file_get_contents($root . '/app/Views/partials/home-pricing.phtml');
        $themeScript = (string)file_get_contents($root . '/Templates/golden-bash/assets/theme.js');
        $migrationUtility = (string)file_get_contents($root . '/migrate-util.php');
        $applicationMigrations = (string)file_get_contents($root . '/app/Services/ApplicationCompatibleMigrationService.php');
        $compatibilityMigration = (string)file_get_contents($root . '/database/migrations/049_apply_pricing_without_temporary_tables.sql');
        $priceUpdateMigration = (string)file_get_contents($root . '/database/migrations/091_update_wedding_video_package_prices.sql');
        $featuredUpdateMigration = (string)file_get_contents($root . '/database/migrations/092_update_portrait_studio_prices_and_featured_package.sql');
        $demoSeeder = (string)file_get_contents($root . '/app/Services/DemoContentSeeder.php');

        foreach ([
            'Celodenní svatební video' => '17 000 Kč',
            'Focení obřadu a portrétů' => '7 000 Kč',
            'Svatební focení do 8 hodin' => '10 000 Kč',
            'Celodenní svatební focení' => '15 000 Kč',
            'Kompletní balíček foto + video' => '31 000 Kč',
            'Portrétní a rodinné focení' => '1 800 Kč',
            'Fotografování v ateliéru' => '2 600 Kč',
        ] as $title => $price) {
            $assert(str_contains($migration, $title), 'Pricing migration is missing package: ' . $title);
            $assert(str_contains($migration, $price), 'Pricing migration is missing price: ' . $price);
        }

        $assert(!str_contains($migration, "'od 5 000 Kč'") && !str_contains($migration, "'od 20 000 Kč'"));
        $assert(!str_contains($homepageView, "card['price']") && !str_contains($homepageView, 'hp-cenik-card-price'));
        $assert(!str_contains($homepageView, 'data-package-price'));
        $assert(!str_contains($themeScript, 'selected_package_price'));
        $assert(str_contains($migrationUtility, 'ApplicationCompatibleMigrationService'));
        $assert(str_contains($applicationMigrations, "048_restore_complete_pricing_content.sql"));
        $assert(str_contains($applicationMigrations, 'restoreRealPricingContent'));
        $assert(str_contains($applicationMigrations, "return 'application-compatible'"));
        $assert(str_contains($demoSeeder, 'public function restoreRealPricingContent'));
        $assert(str_contains($compatibilityMigration, 'WHERE 1 = 0'));
        $assert(!str_contains($compatibilityMigration, 'CREATE TEMPORARY TABLE'));
        $assert(str_contains($priceUpdateMigration, "WHEN 'cenik_card_1_title' THEN 'Dva kameramani'"));
        $assert(str_contains($priceUpdateMigration, "WHEN 'cenik_card_1_price' THEN '25 000 Kč'"));
        $assert(str_contains($priceUpdateMigration, "WHEN 'cenik_card_5_price' THEN '32 000 Kč'"));
        $assert(str_contains($demoSeeder, "'Dva kameramani', '25 000 Kč'"));
        $assert(str_contains($demoSeeder, "'Kompletní balíček foto + video', '32 000 Kč'"));
        $assert(str_contains($featuredUpdateMigration, "WHEN 'cenik_card_4_badge' THEN 'Nejžádanější'"));
        $assert(str_contains($featuredUpdateMigration, "WHEN 'cenik_card_5_badge' THEN ''"));
        $assert(str_contains($featuredUpdateMigration, "WHEN 'cenik_card_6_price' THEN '2 000 Kč'"));
        $assert(str_contains($featuredUpdateMigration, "WHEN 'cenik_card_7_price' THEN '3 000 Kč'"));
        $assert(str_contains($featuredUpdateMigration, '<article class="ckp-card ckp-card--featured"><span class="ckp-badge">Nejžádanější</span><span class="ckp-category">Svatební focení</span>'));
        $assert(str_contains($demoSeeder, "'Celodenní svatební focení', '15 000 Kč'") && str_contains($demoSeeder, "zdarma'], 'Nejžádanější'"));
        $assert(str_contains($demoSeeder, "'Portrétní a rodinné focení', '2 000 Kč'"));
        $assert(str_contains($demoSeeder, "'Fotografování v ateliéru', '3 000 Kč'"));
    });

    $test('pricing cards use the 094 order, every card keeps its package id and only Kompletni carries the badge', function () use ($assert): void {
        $root = dirname(__DIR__);
        $demoSeeder = (string)file_get_contents($root . '/app/Services/DemoContentSeeder.php');
        $migration = (string)file_get_contents($root . '/database/migrations/093_add_wedding_video_only_package_card.sql');

        // Canonical seeder rows, in slot order: [category, title, price, unit, summary, [features], badge, ctaLabel, ctaUrl].
        $start = strpos($demoSeeder, 'private function seedRealPricingContent');
        $assert($start !== false, 'seedRealPricingContent() not found.');
        $cardsStart = strpos($demoSeeder, '$cards = [', (int)$start);
        $cardsEnd = strpos($demoSeeder, "\n        ];", (int)$cardsStart);
        $cardsSource = substr($demoSeeder, (int)$cardsStart, (int)$cardsEnd - (int)$cardsStart);
        preg_match_all("/^\\s*\\['([^']*)', '([^']*)', '([^']*)', '([^']*)', '([^']*)', \\[[^\\]]*\\], '([^']*)', '([^']*)', '([^']*)'\\],\$/mu", $cardsSource, $rows, PREG_SET_ORDER);

        $expected = [
            1 => ['Celodenní svatební focení', '15 000 Kč', ''],
            2 => ['Kompletní balíček foto + video', '32 000 Kč', 'Nejžádanější'],
            3 => ['Celodenní svatební video', '17 000 Kč', ''],
            4 => ['Dva kameramani', '25 000 Kč', ''],
            5 => ['Focení obřadu a portrétů', '7 000 Kč', ''],
            6 => ['Svatební focení do 8 hodin', '10 000 Kč', ''],
            7 => ['Portrétní a rodinné focení', '2 000 Kč', ''],
            8 => ['Fotografování v ateliéru', '3 000 Kč', ''],
        ];
        // Each card stays bound to its own package id (data-package-id / ?balicek=).
        $expectedIds = [
            1 => \App\Domain\PricingPackageCatalog::WEDDING_PHOTO_FULL_DAY,
            2 => \App\Domain\PricingPackageCatalog::PHOTO_VIDEO_COMPLETE,
            3 => \App\Domain\PricingPackageCatalog::WEDDING_VIDEO_ONLY_FULL_DAY,
            4 => \App\Domain\PricingPackageCatalog::WEDDING_VIDEO_FULL_DAY,
            5 => \App\Domain\PricingPackageCatalog::CEREMONY_AND_PORTRAITS,
            6 => \App\Domain\PricingPackageCatalog::WEDDING_PHOTO_8_HOURS,
            7 => \App\Domain\PricingPackageCatalog::PORTRAIT_AND_FAMILY,
            8 => \App\Domain\PricingPackageCatalog::STUDIO_PHOTOGRAPHY,
        ];
        $assert(count($rows) === 8, 'Demo seeder must define exactly eight pricing cards, found ' . count($rows) . '.');

        $blocks = [];
        foreach ($rows as $index => $row) {
            $slot = $index + 1;
            [$title, $price, $badge] = $expected[$slot];
            $assert($row[2] === $title && $row[3] === $price && $row[6] === $badge, 'Seeder card ' . $slot . ' changed: ' . $row[2] . ' / ' . $row[3] . ' / badge "' . $row[6] . '".');
            foreach (['category' => $row[1], 'title' => $row[2], 'price' => $row[3], 'unit' => $row[4], 'desc' => $row[5], 'badge' => $row[6], 'cta_label' => $row[7], 'cta_url' => $row[8]] as $suffix => $content) {
                $blocks[] = ['block_key' => 'cenik_card_' . $slot . '_' . $suffix, 'content' => $content];
            }
        }
        $assert($rows[2][1] === 'Svatební video' && $rows[2][4] === 'celý den', 'Card 3 must be the full-day video-only wedding package.');

        $packages = \App\ViewModels\CenikPageData::fromBlocks($blocks)['packages'];
        $assert(count($packages) === 8, 'Cenik ViewModel must render all eight cards.');
        foreach ($packages as $index => $package) {
            $assert($package['slot'] === $index + 1 && $package['id'] === $expectedIds[$index + 1], 'Card ' . ($index + 1) . ' lost its package id: ' . $package['id']);
        }
        $assert($packages[2]['price'] === '17 000 Kč' && $packages[2]['featured'] === false);
        $assert(array_keys(array_filter(array_column($packages, 'featured'))) === [1], 'Only card 2 (Kompletní balíček) may carry the featured badge.');
        $catalogEntry = \App\Domain\PricingPackageCatalog::resolve($packages[2]['id']);
        $assert($catalogEntry !== null && $catalogEntry['name'] === 'Celodenní svatební video' && $catalogEntry['price'] === '17 000 Kč');

        // Append-only migration: adds only card 8 and never overwrites admin-edited content.
        $assert(str_contains($migration, "'cenik_card_8_price', 'text', 'Karta 8 - price', '17 000 Kč'"));
        $assert(str_contains($migration, "'cenik_card_8_title' AS block_key") && str_contains($migration, "'Jen natáčení celodenní svatby'"));
        $assert(preg_match('/cenik_card_[1-7]_|cenik_faq_/', $migration) === 0, 'Migration 093 must not touch cards 1-7 or the FAQ.');
        $onDuplicate = substr($migration, (int)strpos($migration, 'ON DUPLICATE KEY UPDATE'));
        $assert(!str_contains(substr($onDuplicate, 0, (int)strpos($onDuplicate, ';')), '`content`'), 'Migration 093 must not overwrite content on conflict.');
        $assert(str_contains($migration, "NOT LIKE '%Jen natáčení celodenní svatby%'"), 'Legacy HTML append must be idempotent.');
    });
    $test('migration 094 reorders cards idempotently and the homepage keeps its packages', function () use ($assert): void {
        $root = dirname(__DIR__);
        $migration = (string)file_get_contents($root . '/database/migrations/094_reorder_pricing_cards.sql');

        $assert(str_contains($migration, 'WHERE FIND_IN_SET(page_id, @m094_old_order_pages) > 0'), 'Card move must be guarded per cenik page by the old order.');
        $assert(str_contains($migration, 'HAVING COUNT(DISTINCT SUBSTRING(cb.block_key, 12, 1)) = 4'), 'Old-order fingerprint must need cards 1, 4, 5 and 8 on the same page.');
        foreach (['/kontakt?sluzba=svatebni-video', '/kontakt?sluzba=foto-cely-den', '/kontakt?sluzba=foto-video-komplet', '/kontakt?sluzba=jen-natoceni-svatby'] as $ctaUrl) {
            $assert(str_contains($migration, "_cta_url' AND cb.content = '" . $ctaUrl . "'"), 'Old-order fingerprint must also accept the CTA link ' . $ctaUrl . '.');
        }
        $phase2 = substr($migration, (int)strpos($migration, '-- Phase 2:'), (int)strpos($migration, '-- Phase 3 guard:') - (int)strpos($migration, '-- Phase 2:'));
        $assert(str_contains($phase2, "REGEXP '^m094_cenik_card_[1-8]_'") && !str_contains($phase2, '@m094'), 'Prefix strip must stay unguarded so an interrupted run can finish.');
        $assert(str_contains($migration, 'WHERE FIND_IN_SET(page_id, @m094_badge_pages) > 0'), 'Badge move must have its own guard.');
        $assert(preg_match('/UPDATE\s+`?content_blocks`?\s+\w+\s+JOIN/i', $migration) === 0, 'Migration 094 must use single-table UPDATEs.');
        $assert(str_contains($migration, "ELT(CAST(SUBSTRING(block_key, 12, 1) AS UNSIGNED), '4', '5', '6', '1', '2', '7', '8', '3')"), 'Old slot -> new slot map changed.');
        $assert(str_contains($migration, "WHEN 'cenik_card_2_badge' THEN 'Nejžádanější'") && str_contains($migration, "WHEN 'cenik_card_1_badge' THEN ''"));
        $assert(!str_contains($migration, 'CREATE TEMPORARY TABLE'), 'Production Webzdarma has no TEMPORARY TABLE privilege.');
        $assert(str_contains($migration, "LIKE '%>Dva kameramani</h3>%>Focení obřadu a portrétů</h3>%"), 'Legacy HTML reorder must only match the old order.');
        $assert(!str_contains($migration, "_price'") && !str_contains($migration, "_desc'") && !str_contains($migration, 'cenik_faq_'), 'Migration 094 must not rewrite prices, texts or FAQ.');

        // Homepage keeps its own three packages regardless of the /cenik order (see next test).
        $assert(count(HomepageData::pricingCards([])) === 3);
    });

    $test('migration 099 renames the video-only card title idempotently without touching other texts', function () use ($assert): void {
        $root = dirname(__DIR__);
        $migration = (string)file_get_contents($root . '/database/migrations/099_rename_wedding_video_only_pricing_card.sql');
        $old = 'Jen natáčení celodenní svatby';
        $assert(str_contains($migration, "cb.block_key LIKE 'cenik\_card\_%\_title'") && str_contains($migration, "AND cb.content = '" . $old . "'"), 'Title rename must be guarded by the original value, independent of slot.');
        $assert(str_contains($migration, "SET cb.content = 'Celodenní svatební video'"));
        $assert(str_contains($migration, "cb.block_key IN ('pricing_packages', 'pricing_body')") && str_contains($migration, '<h3 class="ckp-title">' . $old . '</h3>'), 'Legacy HTML replace is limited to the card heading.');
        $assert(!str_contains($migration, 'cenik_faq') && !str_contains($migration, "cta_label") && !str_contains($migration, "cta_url") && !str_contains($migration, 'ALTER'));
        // Historical migrations stay untouched: 093/094 still know the old name (fresh DB replay must keep working).
        $assert(str_contains((string)file_get_contents($root . '/database/migrations/093_add_wedding_video_only_package_card.sql'), $old));
        $assert(str_contains((string)file_get_contents($root . '/database/migrations/094_reorder_pricing_cards.sql'), "cb.block_key = 'cenik_card_8_cta_url' AND cb.content = '/kontakt?sluzba=jen-natoceni-svatby'"), 'Migration 094 cta_url fingerprint fallback must stay.');
        $assert(!str_contains((string)file_get_contents($root . '/app/Services/DemoContentSeeder.php'), $old), 'Seeder must use the new card title.');
        $assert(\App\Domain\PricingPackageCatalog::resolve('svatebni-video-jen-natoceni')['name'] === 'Celodenní svatební video');
        $assert(\App\Domain\PricingPackageCatalog::resolve('svatebni-video-cely-den')['name'] !== 'Celodenní svatební video', 'Catalog names must stay unique.');
    });
    $test('homepage pricing shows full-day photo, complete photo+video (badge, featured) and video-only in this order', function () use ($assert): void {
        $map = [];
        foreach ([
            1 => ['Celodenní svatební focení', 'Svatební focení', ''],
            2 => ['Kompletní balíček foto + video', 'Kompletní balíček', 'Nejžádanější'],
            3 => ['Celodenní svatební video', 'Svatební video', ''],
            4 => ['Dva kameramani', 'Svatební video', ''],
            5 => ['Focení obřadu a portrétů', 'Svatební focení', ''],
        ] as $slot => [$title, $category, $badge]) {
            $map['cenik_card_' . $slot . '_title'] = $title;
            $map['cenik_card_' . $slot . '_category'] = $category;
            $map['cenik_card_' . $slot . '_badge'] = $badge;
            $map['cenik_card_' . $slot . '_desc'] = "Souhrn.

Obsahuje:
• bod " . $slot;
        }
        $cards = HomepageData::pricingCards($map);
        $assert(array_column($cards, 'id') === [
            \App\Domain\PricingPackageCatalog::WEDDING_PHOTO_FULL_DAY,
            \App\Domain\PricingPackageCatalog::PHOTO_VIDEO_COMPLETE,
            \App\Domain\PricingPackageCatalog::WEDDING_VIDEO_ONLY_FULL_DAY,
        ]);
        $assert(array_column($cards, 'title') === ['Celodenní svatební focení', 'Kompletní balíček foto + video', 'Celodenní svatební video']);
        $assert(array_column($cards, 'category') === ['Svatební focení', 'Kompletní balíček', 'Svatební video']);
        $assert(array_column($cards, 'featured') === [false, true, false], 'Only the middle card may be featured.');
        $assert(array_column($cards, 'badge') === ['', 'Nejžádanější', '']);
        $assert($cards[1]['features'] === ['bod 2']);

        $html = ViewFactory::forTheme(null)->render('partials/home-pricing', ['cards' => $cards]);
        $assert(substr_count($html, 'hp-cenik-card-badge') === 1 && str_contains($html, 'Nejžádanější'), 'Badge must be rendered exactly once.');
        $assert(substr_count($html, 'hp-cenik-card--featured') === 1);
        $assert(preg_match('/hp-cenik-card--featured" data-package-id="foto-video-komplet"><span class="hp-cenik-card-badge">Nejžádanější/', $html) === 1, 'Badge and featured class belong to the Kompletní card.');
        $assert(preg_match_all('/data-package-id="([^"]+)"/', $html, $m) && $m[1] === ['svatebni-foto-cely-den', 'foto-video-komplet', 'svatebni-video-jen-natoceni']);
        $assert(!str_contains($html, 'Kč'), 'Homepage teaser must stay price free.');
        // The contact form / e-mail resolves name and price from the catalog, so it must match /cenik.
        foreach ([['svatebni-foto-cely-den', 'Celodenní svatební focení', '15 000 Kč'], ['foto-video-komplet', 'Kompletní balíček foto + video', '32 000 Kč'], ['svatebni-video-jen-natoceni', 'Celodenní svatební video', '17 000 Kč']] as [$id, $name, $price]) {
            $entry = \App\Domain\PricingPackageCatalog::resolve($id);
            $assert($entry !== null && $entry['name'] === $name && $entry['price'] === $price, 'Catalog entry out of sync with /cenik: ' . $id);
        }
    });
}
