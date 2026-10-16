<?php

declare(strict_types=1);

function register_czech_copy_quality_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);

    $test('current visible-copy sources use plain hyphens and valid UTF-8', function () use ($assert, $root): void {
        $roots = ['app', 'admin', 'public', 'Templates', 'database/seeds', 'config'];
        $extensions = ['php', 'phtml', 'html', 'js', 'json'];
        foreach ($roots as $relativeRoot) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
                $root . '/' . $relativeRoot,
                FilesystemIterator::SKIP_DOTS
            ));
            foreach ($iterator as $file) {
                if (!$file->isFile() || !in_array(strtolower($file->getExtension()), $extensions, true)) {
                    continue;
                }
                $content = (string)file_get_contents($file->getPathname());
                $assert(preg_match('//u', $content) === 1, 'Invalid UTF-8: ' . $file->getPathname());
                $assert(!str_contains($content, '—') && !str_contains($content, '–'), 'Long dash: ' . $file->getPathname());
            }
        }
    });

    $test('photographer seed contains no known mojibake markers', function () use ($assert, $root): void {
        $seed = (string)file_get_contents($root . '/database/seeds/seed_photographer.php');
        foreach (['Ã', 'Ä', 'Ă', 'Ë', 'â', 'Â', 'Cenè'] as $marker) {
            $assert(!str_contains($seed, $marker), 'Mojibake marker remains: ' . $marker);
        }
    });
}
