<?php

namespace App\Services;

final class SetupFilesystemResetService
{
    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = rtrim($basePath ?: dirname(__DIR__, 2), '/\\');
    }

    /**
     * Remove every filesystem artifact that can affect a freshly seeded site.
     * Log files are intentionally retained because they do not influence render state.
     */
    public function reset(): array
    {
        $cleared = [];
        foreach ([
            'storage/media',
            'storage/published',
            'storage/cache',
            'public/assets/generated',
        ] as $relativeDirectory) {
            $this->purgeDirectoryContents($this->path($relativeDirectory));
            $cleared[] = $relativeDirectory;
        }

        foreach (['storage/active-theme-key.txt'] as $relativeFile) {
            $file = $this->path($relativeFile);
            if (is_file($file) && !unlink($file)) {
                throw new \RuntimeException('Nepodarilo se smazat stavovy soubor: ' . $relativeFile);
            }
            $cleared[] = $relativeFile;
        }

        return $cleared;
    }

    private function path(string $relativePath): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    private function purgeDirectoryContents(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            throw new \RuntimeException('Nepodarilo se nacist obsah adresare: ' . $directory);
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->purgeDirectoryContents($path);
                if (!rmdir($path)) {
                    throw new \RuntimeException('Nepodarilo se smazat adresar: ' . $path);
                }
                continue;
            }

            if (!unlink($path)) {
                throw new \RuntimeException('Nepodarilo se smazat soubor: ' . $path);
            }
        }
    }
}
