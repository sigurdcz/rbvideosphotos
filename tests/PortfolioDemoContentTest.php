<?php

use App\Services\PortfolioDemoContentImporter;

$test('new portfolio manifest maps nine complete galleries and 33 source photos', function () use ($assert): void {
    $reflection = new ReflectionClass(PortfolioDemoContentImporter::class);
    $manifest = $reflection->getReflectionConstant('MANIFEST')->getValue();
    $sourceRoot = dirname(__DIR__) . '/public/assets/demo/dalsifotkydoportfolia';

    $assert(count($manifest) === 9, 'Portfolio manifest must contain exactly nine galleries.');
    $slugs = [];
    $metaTitles = [];
    $photos = [];
    foreach ($manifest as $record) {
        $assert(!isset($slugs[$record['slug']]), 'Portfolio manifest contains duplicate slug ' . $record['slug']);
        $slugs[$record['slug']] = true;
        $assert(trim((string)$record['title']) !== '', 'Portfolio gallery title is empty.');
        $metaTitle = trim((string)($record['meta_title'] ?? ''));
        $assert($metaTitle !== '', 'Portfolio gallery meta title is empty.');
        $assert(mb_strlen($metaTitle) <= 65, 'Portfolio gallery meta title is longer than 65 characters: ' . $record['slug']);
        $assert(!isset($metaTitles[$metaTitle]), 'Portfolio gallery meta title is duplicated: ' . $metaTitle);
        $assert(!str_contains($metaTitle, '–') && !str_contains($metaTitle, '—'), 'Portfolio meta title contains a long dash: ' . $record['slug']);
        $metaTitles[$metaTitle] = true;
        $assert(trim((string)$record['excerpt']) !== '', 'Portfolio gallery excerpt is empty.');
        $assert(trim((string)($record['alt_prefix'] ?? '')) !== '', 'Portfolio gallery alt prefix is empty: ' . $record['slug']);
        $assert(count($record['paragraphs']) >= 7, 'Portfolio gallery text is incomplete: ' . $record['slug']);
        foreach ($record['photos'] as $photo) {
            $assert(!isset($photos[$photo]), 'Portfolio photo is mapped more than once: ' . $photo);
            $photos[$photo] = true;
            $assert(is_file($sourceRoot . '/' . $photo), 'Mapped portfolio photo is missing: ' . $photo);
        }
    }

    $actual = glob($sourceRoot . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
    $assert(count($photos) === 33, 'Portfolio manifest must map exactly 33 photos.');
    $assert(count($actual) === 33, 'Portfolio source directory must contain exactly 33 photos.');
    $assert(isset($slugs['kamila-a-matej'], $slugs['lea-foceni-pro-ritualni-saty']), 'Expected first and final portfolio gallery slugs are missing.');
    $privacySource = json_encode($manifest, JSON_UNESCAPED_UNICODE) . "\n" . implode("\n", array_map('basename', $actual));
    foreach (['Ďuriškovi', 'Hrazdilovi', 'Ťuikovi', 'Červenkovi', 'Žáčkovi', 'Novákovi', 'Nevěřilovi', 'Štipčákovi', 'Černohlávek'] as $surname) {
        $assert(!str_contains($privacySource, $surname), 'Current portfolio content exposes surname ' . $surname);
    }
});

$test('mixed-batch manifest maps fifteen galleries and 76 source photos without duplicating slugs', function () use ($assert): void {
    $reflection = new ReflectionClass(PortfolioDemoContentImporter::class);
    $manifest = $reflection->getReflectionConstant('MANIFEST')->getValue();
    $mixManifest = $reflection->getReflectionConstant('MIX_MANIFEST')->getValue();
    $videoManifest = $reflection->getReflectionConstant('VIDEO_MANIFEST')->getValue();
    $sourceRoot = dirname(__DIR__) . '/public/assets/demo/mix-vseho-portfolio';

    $assert(count($mixManifest) === 15, 'Mixed-batch manifest must contain exactly fifteen galleries.');

    $otherSlugs = array_merge(array_column($manifest, 'slug'), array_column($videoManifest, 'slug'));
    $slugs = [];
    $metaTitles = [];
    $photos = [];
    foreach ($mixManifest as $record) {
        $assert(!isset($slugs[$record['slug']]), 'Mixed-batch manifest contains duplicate slug ' . $record['slug']);
        $assert(!in_array($record['slug'], $otherSlugs, true), 'Mixed-batch manifest slug collides with an existing gallery: ' . $record['slug']);
        $slugs[$record['slug']] = true;
        $assert(trim((string)$record['title']) !== '', 'Mixed-batch gallery title is empty: ' . $record['slug']);
        $metaTitle = trim((string)($record['meta_title'] ?? ''));
        $assert($metaTitle !== '', 'Mixed-batch gallery meta title is empty: ' . $record['slug']);
        $assert(mb_strlen($metaTitle) <= 65, 'Mixed-batch gallery meta title is longer than 65 characters: ' . $record['slug']);
        $assert(!isset($metaTitles[$metaTitle]), 'Mixed-batch gallery meta title is duplicated: ' . $metaTitle);
        $assert(!str_contains($metaTitle, '–') && !str_contains($metaTitle, '—'), 'Mixed-batch meta title contains a long dash: ' . $record['slug']);
        $metaTitles[$metaTitle] = true;
        $assert(trim((string)$record['excerpt']) !== '', 'Mixed-batch gallery excerpt is empty: ' . $record['slug']);
        $assert(trim((string)($record['alt_prefix'] ?? '')) !== '', 'Mixed-batch gallery alt prefix is empty: ' . $record['slug']);
        $assert(count($record['paragraphs']) >= 7, 'Mixed-batch gallery text is incomplete: ' . $record['slug']);
        $assert($record['date'] === null, 'Mixed-batch gallery invents a capture date it does not have: ' . $record['slug']);
        foreach ($record['photos'] as $photo) {
            $assert(!isset($photos[$photo]), 'Mixed-batch photo is mapped more than once: ' . $photo);
            $photos[$photo] = true;
            $assert(is_file($sourceRoot . '/' . $photo), 'Mapped mixed-batch photo is missing: ' . $photo);
        }
    }

    $actual = glob($sourceRoot . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
    $assert(count($photos) === 76, 'Mixed-batch manifest must map exactly 76 photos.');
    $assert(count($actual) === 76, 'Mixed-batch source directory must contain exactly 76 photos.');
});

$test('video gallery manifest maps exactly 12 distinguishable YouTube items', function () use ($assert): void {
    $reflection = new ReflectionClass(PortfolioDemoContentImporter::class);
    $photoManifest = $reflection->getReflectionConstant('MANIFEST')->getValue();
    $videoManifest = $reflection->getReflectionConstant('VIDEO_MANIFEST')->getValue();

    $assert(count($videoManifest) === 12, 'Video gallery manifest must contain exactly twelve items.');
    $approvedIds = ['L-zaa5hj-aU','cXU7Ty-kjyA','hYj2w3P5rK8','5ZlVVcIu10U','O5i9TzCms5c','b9WhNN8VYhc','C1wL3QFZhn0','DBWKJ46eM4c','su0hKnBnCKw','1z-qckQn5UU','o-1xGllIShY','GBKJ_VB_Nmw'];
    $assert(array_column($videoManifest, 'video_url') === array_map(static fn(string $id): string => 'https://www.youtube.com/watch?v=' . $id, $approvedIds), 'Demo replay must preserve the exact user-approved video order, not the old chronological gallery.');

    $photoSlugs = array_column($photoManifest, 'slug');
    $slugs = [];
    $titles = [];
    foreach ($videoManifest as $record) {
        $assert(!isset($slugs[$record['slug']]), 'Video manifest contains duplicate slug ' . $record['slug']);
        $assert(!in_array($record['slug'], $photoSlugs, true), 'Video manifest slug collides with a photo gallery slug: ' . $record['slug']);
        $slugs[$record['slug']] = true;

        $title = trim((string)$record['title']);
        $assert($title !== '', 'Video gallery title is empty: ' . $record['slug']);
        $assert(!isset($titles[$title]), 'Video gallery titles must be distinguishable, duplicate: ' . $title);
        $titles[$title] = true;

        $assert(trim((string)($record['meta_title'] ?? '')) !== '', 'Video gallery meta title is empty: ' . $record['slug']);
        $assert(trim((string)($record['excerpt'] ?? '')) !== '', 'Video gallery excerpt is empty: ' . $record['slug']);
        $assert(
            preg_match('~^https://www\.youtube\.com/(watch\?v=|embed/)[A-Za-z0-9_-]{11}$~', (string)$record['video_url']) === 1,
            'Video gallery entry does not use a canonical YouTube watch/embed URL: ' . $record['slug']
        );
        $assert($record['date'] === null || preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)$record['date']) === 1, 'Video gallery date is not ISO or null: ' . $record['slug']);
    }
});

$test('new portfolio importer uses Media Library and deletes old entries explicitly', function () use ($assert): void {
    $source = (string)file_get_contents(dirname(__DIR__) . '/app/Services/PortfolioDemoContentImporter.php');
    $demo = (string)file_get_contents(dirname(__DIR__) . '/app/Services/DemoContentSeeder.php');

    $assert(str_contains($source, 'ingestUploadedFile('), 'Portfolio importer bypasses MediaIngestionService.');
    $assert(str_contains($source, 'removeAllPortfolioEntries('), 'Portfolio importer does not explicitly remove the old portfolio.');
    $assert(str_contains($source, 'Staré portfolio se nepodařilo kompletně odstranit.'), 'Portfolio deletion has no visible failure guard.');
    $assert(str_contains($source, 'count($expected) !== 33'), 'Portfolio importer does not reject an incomplete photo manifest.');
    $assert(str_contains($source, "'meta_title' => \$record['meta_title']"), 'Portfolio importer does not use the curated short meta title.');
    $assert(str_contains($source, 'public function refreshSeoMetadata'), 'Portfolio importer cannot refresh existing SEO metadata.');
    $assert(str_contains($source, 'private function imageAlt'), 'Portfolio importer does not create contextual image alt text.');
    $assert(str_contains($demo, '(new PortfolioDemoContentImporter())->import($siteId)'), 'DemoContentSeeder does not use the new portfolio importer.');
});

$test('weare5 is not in the kamila-a-matej gallery and migration 098 reverts 095', function () use ($assert): void {
    $root = dirname(__DIR__);
    $reflection = new ReflectionClass(PortfolioDemoContentImporter::class);
    $manifest = $reflection->getReflectionConstant('MANIFEST')->getValue();
    $mixManifest = $reflection->getReflectionConstant('MIX_MANIFEST')->getValue();
    $source = (string)file_get_contents($root . '/app/Services/PortfolioDemoContentImporter.php');
    $migration = (string)file_get_contents($root . '/database/migrations/098_revert_first_portfolio_cover_weare5.sql');
    $dispatcher = (string)file_get_contents($root . '/app/Services/ApplicationCompatibleMigrationService.php');

    // Seed: no record carries a cover and the kamila-a-matej gallery is only its own two photos.
    foreach ([...$manifest, ...$mixManifest] as $record) {
        $assert(!array_key_exists('cover', $record), 'Portfolio manifest must not carry a cover: ' . $record['slug']);
        $assert(!in_array('weare5.jpg', $record['photos'], true), 'weare5.jpg must not be in a portfolio gallery: ' . $record['slug']);
    }
    $assert($manifest[0]['slug'] === 'kamila-a-matej');
    $assert($manifest[0]['photos'] === ['18.7.2026 Kamila a Matěj.jpg', '18.7.2026 Kamila a Matěj (2).jpg']);
    $assert(!str_contains($source, 'applyManifestCovers') && !str_contains($source, 'resolveCoverMedia'), 'Code added by 095 must be removed.');

    // Migration 098: registered, marker-only on clean reset, removes weare5 by filename and slug.
    $assert(str_contains($migration, 'WHERE 1 = 0'), 'Migration 098 is an application-compatible marker.');
    $assert(str_contains($dispatcher, "'098_revert_first_portfolio_cover_weare5.sql' => \$this->applyWithoutResult("));
    $assert(str_contains($dispatcher, '->revertMistakenWeare5Cover($siteId)'));
    $assert(str_contains($source, "private const MISTAKEN_COVER_SLUG = 'kamila-a-matej';"));
    $assert(str_contains($source, "private const MISTAKEN_COVER_FILENAME = 'weare5.jpg';"));
    $assert(str_contains($source, 'AND alt_text = ? AND title = ?'), 'Alt/title revert must not overwrite an admin edit.');
    $assert(!preg_match('/\b179\b/', $source . $migration), 'Revert must not hardcode a media id.');
});
