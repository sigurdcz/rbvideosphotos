<?php

namespace App\Services;

class ImageVariantService
{
    public function createWebP(string $sourcePath, string $destPath, int $quality = 85): bool
    {
        if (!$this->supportsWebP()) {
            return false;
        }

        $info = @getimagesize($sourcePath);
        if (!$info) return false;

        $src = match ($info[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG  => imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => imagecreatefromwebp($sourcePath),
            default        => null,
        };

        if (!$src) return false;

        $result = imagewebp($src, $destPath, $quality);
        imagedestroy($src);
        return $result;
    }

    public function supportsWebP(): bool
    {
        if (extension_loaded('imagick')) {
            return in_array('WEBP', \Imagick::queryFormats());
        }
        if (extension_loaded('gd')) {
            $info = gd_info();
            return !empty($info['WebP Support']);
        }
        return false;
    }

    public function getDimensions(string $path): array
    {
        $size = @getimagesize($path);
        if (!$size) return [0, 0];
        return [$size[0], $size[1]];
    }
}
