<?php

declare(strict_types=1);

use App\Services\ImageVariantService;

function register_image_variant_service_tests(callable $test, callable $assert): void
{
    $test('media ingestion keeps the mobile medium variant under 72 KiB', function () use ($assert): void {
        $source = (string)file_get_contents(__DIR__ . '/../app/Services/MediaIngestionService.php');
        $assert(str_contains($source, "'medium' => ['width' => 720, 'bytes' => 72 * 1024"));
        $assert(str_contains($source, 'regenerateImageVariantsByStoragePath'));
    });

    $test('palette PNG converts to readable true-color WebP without warnings', function () use ($assert): void {
        if (!extension_loaded('gd') || empty(gd_info()['WebP Support'])) {
            throw new RuntimeException('GD with WebP support is required for this regression test.');
        }

        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'seo-editor-palette-' . bin2hex(random_bytes(6));
        if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create test directory.');
        }
        $source = $dir . DIRECTORY_SEPARATOR . 'palette.png';
        $dest = $dir . DIRECTORY_SEPARATOR . 'variant.webp';

        try {
            $palette = imagecreate(32, 18);
            $transparent = imagecolorallocate($palette, 0, 0, 0);
            imagecolortransparent($palette, $transparent);
            $gold = imagecolorallocate($palette, 201, 145, 48);
            imagefilledrectangle($palette, 4, 3, 27, 14, $gold);
            imagepng($palette, $source);
            imagedestroy($palette);

            set_error_handler(static function (int $severity, string $message): never {
                throw new ErrorException($message, 0, $severity);
            });
            try {
                $created = (new ImageVariantService())->createWebP($source, $dest, 90);
            } finally {
                restore_error_handler();
            }

            $assert($created === true, 'Palette source did not produce a WebP variant.');
            $assert(is_file($dest), 'WebP variant file is missing.');
            $info = getimagesize($dest);
            $assert(is_array($info) && $info[0] === 32 && $info[1] === 18 && $info[2] === IMAGETYPE_WEBP);
            $decoded = imagecreatefromwebp($dest);
            $assert($decoded instanceof GdImage && imageistruecolor($decoded));
            if ($decoded instanceof GdImage) {
                imagedestroy($decoded);
            }
        } finally {
            foreach ([$source, $dest] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    });

    $test('responsive WebP obeys byte budget and impossible budget fails visibly', function () use ($assert): void {
        if (!extension_loaded('gd') || empty(gd_info()['WebP Support'])) {
            throw new RuntimeException('GD with WebP support is required for this regression test.');
        }

        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'seo-editor-budget-' . bin2hex(random_bytes(6));
        if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create test directory.');
        }
        $source = $dir . DIRECTORY_SEPARATOR . 'source.png';
        $dest = $dir . DIRECTORY_SEPARATOR . 'medium.webp';
        $impossible = $dir . DIRECTORY_SEPARATOR . 'impossible.webp';

        try {
            $image = imagecreatetruecolor(1440, 900);
            for ($y = 0; $y < 900; $y += 10) {
                $color = imagecolorallocate($image, 45 + ($y % 170), 70 + ($y % 120), 95 + ($y % 100));
                imagefilledrectangle($image, 0, $y, 1439, min(899, $y + 9), $color);
            }
            imagepng($image, $source);
            imagedestroy($image);

            $service = new ImageVariantService();
            $meta = $service->createResizedWithinBudget($source, $dest, 720, 80 * 1024, 82, 47, 5);
            $assert($meta['width'] === 720, 'Responsive variant has an unexpected width.');
            $assert($meta['filesize'] > 0 && $meta['filesize'] <= 80 * 1024, 'Responsive variant exceeded its byte budget.');
            $assert((getimagesize($dest)[2] ?? 0) === IMAGETYPE_WEBP, 'Budgeted .webp is not real WebP data.');

            $thrown = false;
            try {
                $service->createResizedWithinBudget($source, $impossible, 720, 1, 82, 72, 5);
            } catch (RuntimeException) {
                $thrown = true;
            }
            $assert($thrown, 'Impossible byte budget did not raise a visible failure.');
            $assert(!is_file($impossible), 'Failed budget left a misleading derivative on disk.');
        } finally {
            foreach ([$source, $dest, $impossible] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    });
}
