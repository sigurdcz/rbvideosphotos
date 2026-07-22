<?php

declare(strict_types=1);

use App\Services\NasePraceDemoContentImporter;

function register_nase_prace_demo_content_tests(callable $test, callable $assert): void
{
    $test('Nase prace demo manifest maps 12 approved articles to 24 images', function () use ($assert): void {
        $root = dirname(__DIR__) . '/Data pro clanky/RB_Videos_Photos_NASE_PRACE/nase-prace-md';
        $manifest = (new ReflectionClass(NasePraceDemoContentImporter::class))->getReflectionConstant('MANIFEST')->getValue();
        $assert(count($manifest) === 12, 'Demo manifest must contain exactly 12 articles.');

        $images = 0;
        $exceptions = 0;
        foreach ($manifest as $item) {
            $article = $root . '/' . $item['article'];
            $directory = $root . '/' . $item['directory'];
            $assert(is_file($article), 'Missing source article: ' . $item['article']);
            $assert(is_dir($directory), 'Missing mapped image directory: ' . $item['directory']);
            $source = (string)file_get_contents($article);
            $assert(str_contains($source, '# Schválený redakční text'), 'Article lacks approved copy: ' . $item['article']);
            $assert(str_contains($source, 'content_section: nase-prace'), 'Article is not marked as Nase prace: ' . $item['article']);
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

    $test('Nase prace importer requires Media Library thumbnails', function () use ($assert): void {
        $source = (string)file_get_contents(dirname(__DIR__) . '/app/Services/NasePraceDemoContentImporter.php');
        $assert(str_contains($source, 'ingestUploadedFile('), 'Importer bypasses MediaIngestionService.');
        $assert(str_contains($source, "=== 'thumb'"), 'Importer does not verify thumbnail variants.');
        $assert(str_contains($source, 'throw new RuntimeException'), 'Importer must surface primary-path failures.');
    });
}
