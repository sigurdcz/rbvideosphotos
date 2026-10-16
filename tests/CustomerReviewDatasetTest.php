<?php

declare(strict_types=1);

use App\Content\CustomerReviewDataset;

function register_customer_review_dataset_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);

    $test('customer review dataset contains only the 39 approved reviews', function () use ($assert): void {
        $reviews = CustomerReviewDataset::all();
        $assert(count($reviews) === 39);
        $assert($reviews[0]['author_name'] === 'Václav Konopka');
        $assert($reviews[26]['author_name'] === 'Radka Polýnková');
        $assert($reviews[27]['author_name'] === 'Sigurd Gnezdilov');
        $assert($reviews[38]['author_name'] === 'Viera Sedlackova');
        $assert(array_column($reviews, 'sort_order') === range(1, 39));
        $assert(count(array_filter($reviews, static fn(array $row): bool => $row['rating'] === 5 && $row['is_active'] === 1)) === 39);

        $authors = array_column($reviews, 'author_name');
        foreach (['Markéta a Tomáš', 'Lucie Nováková', 'Petr Malý', 'Anna a Michal', 'Kateřina D.', 'Dominik S.', 'Veronika a Pavel'] as $placeholder) {
            $assert(!in_array($placeholder, $authors, true));
        }
    });

    $test('review source labels and supplied truncation remain explicit', function () use ($assert): void {
        $reviews = CustomerReviewDataset::all();
        $assert(count(array_filter($reviews, static fn(array $row): bool => $row['context_label'] === 'RB reference')) === 27);
        $assert(count(array_filter($reviews, static fn(array $row): bool => $row['context_label'] === 'Google recenze · 5/5')) === 12);
        $assert(str_ends_with($reviews[30]['review_text'], '…'));
        $assert($reviews[38]['review_text'] === 'Nám dala pět hvězdiček na google recenzích. Moc děkujeme!');
    });

    $test('all seed paths and append-only migration use the canonical review dataset', function () use ($assert, $root): void {
        foreach ([
            '/app/Services/DemoContentSeeder.php',
            '/database/seeds/seed.php',
            '/database/seeds/seed_reviews_page.php',
            '/database/seeds/seed_photographer.php',
        ] as $path) {
            $assert(str_contains((string)file_get_contents($root . $path), 'CustomerReviewDataset'));
        }

        $historicalMigration = (string)file_get_contents($root . '/database/migrations/025_replace_placeholder_reviews.sql');
        $assert(str_contains($historicalMigration, 'DELETE FROM reviews;'));
        $assert(str_contains($historicalMigration, 'JSON_TABLE'));

        $copyMigration = (string)file_get_contents($root . '/database/migrations/046_normalize_czech_visible_copy.sql');
        $assert(str_contains($copyMigration, 'UPDATE reviews'));
        $assert(str_contains($copyMigration, "REPLACE(REPLACE(review_text, '—', '-'), '–', '-')"));
    });
}
