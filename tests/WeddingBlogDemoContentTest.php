<?php

declare(strict_types=1);

use App\Services\WeddingBlogDemoContentImporter;

function register_wedding_blog_demo_content_tests(callable $test, callable $assert): void
{
    $test('wedding blog demo manifest maps 12 approved articles to 24 images', function () use ($assert): void {
        $root = dirname(__DIR__) . '/Data pro clanky/RB_Videos_Photos_NASE_PRACE/nase-prace-md';
        $manifest = (new ReflectionClass(WeddingBlogDemoContentImporter::class))->getReflectionConstant('MANIFEST')->getValue();
        $assert(count($manifest) === 12, 'Demo manifest must contain exactly 12 articles.');

        $images = 0;
        $exceptions = 0;
        $surnames = ['Hufovi', 'Kaňkovští', 'Pláškovi', 'Krůpovi', 'Jungligovi', 'Gruntovi', 'Provazovi', 'Krylovi', 'Novákovi', 'Žáčkovi', 'Nevěřilovi'];
        foreach ($manifest as $item) {
            $article = $root . '/' . $item['article'];
            $directory = $root . '/' . $item['directory'];
            $assert(is_file($article), 'Missing source article: ' . $item['article']);
            $assert(is_dir($directory), 'Missing mapped image directory: ' . $item['directory']);
            $source = (string)file_get_contents($article);
            foreach ($surnames as $surname) {
                $assert(!str_contains($source, $surname), 'Current blog article exposes surname ' . $surname . ': ' . $item['article']);
                $assert(!str_contains($item['directory'], $surname), 'Current blog media directory exposes surname ' . $surname . ': ' . $item['directory']);
            }
            $assert(str_contains($source, '# Schválený redakční text'), 'Article lacks approved copy: ' . $item['article']);
            $assert(str_contains($source, 'content_section: blog'), 'Article is not marked as Blog: ' . $item['article']);
            $assert(!preg_match('/\[\d+\]/', $source), 'Article still contains numeric citation markers: ' . $item['article']);
            $count = count(glob($directory . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: []);
            $assert($count === 2, 'Each source article must have exactly two explicitly mapped images: ' . $item['article']);
            $images += $count;
            if (isset($item['date_exception'])) {
                $exceptions++;
                $assert(str_contains($item['date_exception'], '2025-09-02'), 'Date mismatch must remain explicitly documented.');
            }
        }
        $assert($images === 24, 'Expected 24 explicitly mapped source images.');
        $assert($exceptions === 1, 'Expected exactly one documented date mismatch.');
    });

    $test('wedding blog importer requires Media Library thumbnails and blog ownership', function () use ($assert): void {
        $source = (string)file_get_contents(dirname(__DIR__) . '/app/Services/WeddingBlogDemoContentImporter.php');
        $ingestion = (string)file_get_contents(dirname(__DIR__) . '/app/Services/MediaIngestionService.php');
        $assert(str_contains($source, "extension_loaded('gd')"), 'Wedding demo import does not fail before writes when GD is unavailable.');
        $assert(str_contains($source, 'regenerateImageVariants($siteId, $mediaId)'), 'Wedding demo import cannot repair existing media without thumbnail variants.');
        $assert(str_contains($ingestion, 'public function regenerateImageVariants'), 'Media ingestion does not expose explicit derivative repair for existing images.');
        $publishSource = (string)file_get_contents(dirname(__DIR__) . '/app/Services/PublishService.php');
        $assert(str_contains($source, 'ingestUploadedFile('), 'Importer bypasses MediaIngestionService.');
        $assert(str_contains($source, "=== 'thumb'"), 'Importer does not verify thumbnail variants.');
        $assert(str_contains($source, 'throw new RuntimeException'), 'Importer must surface primary-path failures.');
        $assert(str_contains($source, 'blog_posts') && str_contains($source, 'setGallery'), 'Importer does not persist articles through the blog model.');
        $assert(str_contains($source, 'removeIncorrectPortfolioEntries'), 'Importer does not clean the incorrectly classified portfolio records.');
        $assert(str_contains($publishSource, 'blogPostsAsSitemapItems($siteId)'), 'Full publish does not add published blog articles to sitemap.xml.');
        $assert(str_contains($publishSource, 'portfolioEntriesAsSitemapItems($siteId)'), 'Full publish does not add published portfolio articles to sitemap.xml.');
        $assert(str_contains($publishSource, 'uniqueSitemapItems('), 'Sitemap does not deduplicate canonical paths across content types.');
    });

    $test('five overlong wedding URLs have stable 301 redirects', function () use ($assert): void {
        $reflection = new ReflectionClass(WeddingBlogDemoContentImporter::class);
        $redirects = $reflection->getReflectionConstant('LEGACY_SLUG_REDIRECTS')->getValue();
        $sourceRoot = dirname(__DIR__) . '/Data pro clanky/RB_Videos_Photos_NASE_PRACE/nase-prace-md';
        $importerSource = (string)file_get_contents(dirname(__DIR__) . '/app/Services/WeddingBlogDemoContentImporter.php');

        $assert(count($redirects) === 5, 'Exactly five audited wedding URLs must be shortened.');
        foreach ($redirects as $oldSlug => $newSlug) {
            $assert(strlen($newSlug) < strlen($oldSlug), 'Replacement slug is not shorter: ' . $newSlug);
            $matches = glob($sourceRoot . '/*.md') ?: [];
            $found = false;
            foreach ($matches as $path) {
                $article = (string)file_get_contents($path);
                if (str_contains($article, 'slug: ' . $newSlug)) {
                    $found = true;
                    break;
                }
            }
            $assert($found, 'Shortened slug is missing from article sources: ' . $newSlug);
        }
        $assert(str_contains($importerSource, 'public function migrateLegacySlugs'));
        $assert(str_contains($importerSource, 'status_code) VALUES (?, ?, ?, 301)'));
    });
}
