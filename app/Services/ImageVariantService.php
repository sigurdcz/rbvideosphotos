<?php

namespace App\Services;

class ImageVariantService
{
    public function createWebP(string $sourcePath, string $destPath, int $quality = 90): bool
    {
        if (!$this->supportsWebP()) return false;
        $info = @getimagesize($sourcePath);
        if (!$info) return false;
        $src = $this->loadImage($sourcePath, $info[2]);
        if (!$src) return false;
        $result = imagewebp($src, $destPath, $quality);
        imagedestroy($src);
        return $result;
    }

    /**
     * Create a resized variant, saved as WebP (falls back to JPEG).
     * If source is smaller than maxW, the original is just converted/copied.
     */
    public function createResized(string $sourcePath, string $destPath, int $maxW, int $quality = 90): bool
    {
        if (!extension_loaded('gd')) return false;
        $info = @getimagesize($sourcePath);
        if (!$info) return false;

        [$origW, $origH] = [$info[0], $info[1]];
        if ($origW <= 0 || $origH <= 0) return false;

        $scale = $origW > $maxW ? $maxW / $origW : 1.0;
        $newW  = (int)round($origW * $scale);
        $newH  = (int)round($origH * $scale);

        $src = $this->loadImage($sourcePath, $info[2]);
        if (!$src) return false;

        $dst = imagecreatetruecolor($newW, $newH);
        // Preserve alpha for PNG
        if ($info[2] === IMAGETYPE_PNG) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        $ext    = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));
        $result = match ($ext) {
            'webp'        => $this->supportsWebP() ? imagewebp($dst, $destPath, $quality) : false,
            'jpg', 'jpeg' => imagejpeg($dst, $destPath, $quality),
            'png'         => imagepng($dst, $destPath, 7),
            default       => false,
        };

        // If WebP not supported, fall back to JPEG
        if (!$result && $ext === 'webp') {
            $jpegPath = preg_replace('/\.webp$/i', '.jpg', $destPath);
            $result   = imagejpeg($dst, $jpegPath, $quality);
            if ($result) {
                // Caller checks actual path via return value — signal with rename
                @rename($jpegPath, $destPath); // best-effort; caller should use getDimensions to verify
            }
        }

        imagedestroy($src);
        imagedestroy($dst);
        return (bool)$result;
    }

    /**
     * Vytvori variantu s presnymi rozmery (cover crop, vystred). Vhodne pro
     * OG/Twitter nahledy (napr. 1200x630), kde je potreba pevny pomer stran.
     */
    public function createCropped(string $sourcePath, string $destPath, int $targetW, int $targetH, int $quality = 90): bool
    {
        if (!extension_loaded('gd')) return false;
        $info = @getimagesize($sourcePath);
        if (!$info) return false;

        [$origW, $origH] = [$info[0], $info[1]];
        if ($origW <= 0 || $origH <= 0 || $targetW <= 0 || $targetH <= 0) return false;

        $src = $this->loadImage($sourcePath, $info[2]);
        if (!$src) return false;

        // Cover: vyber ze zdroje co nejvetsi vyrez s pomerem cile, pak resample.
        $targetRatio = $targetW / $targetH;
        $origRatio = $origW / $origH;
        if ($origRatio > $targetRatio) {
            $cropH = $origH;
            $cropW = (int) round($origH * $targetRatio);
        } else {
            $cropW = $origW;
            $cropH = (int) round($origW / $targetRatio);
        }
        $srcX = (int) floor(($origW - $cropW) / 2);
        $srcY = (int) floor(($origH - $cropH) / 2);

        $dst = imagecreatetruecolor($targetW, $targetH);
        if ($info[2] === IMAGETYPE_PNG) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $targetW, $targetH, $cropW, $cropH);

        $ext = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));
        $result = match ($ext) {
            'webp'        => $this->supportsWebP() ? imagewebp($dst, $destPath, $quality) : false,
            'jpg', 'jpeg' => imagejpeg($dst, $destPath, $quality),
            'png'         => imagepng($dst, $destPath, 7),
            default       => false,
        };

        if (!$result && $ext === 'webp') {
            $jpegPath = preg_replace('/\.webp$/i', '.jpg', $destPath);
            if ($jpegPath !== null && imagejpeg($dst, $jpegPath, $quality)) {
                @rename($jpegPath, $destPath);
                $result = true;
            }
        }

        imagedestroy($src);
        imagedestroy($dst);
        return (bool) $result;
    }

    public function supportsWebP(): bool
    {
        if (extension_loaded('imagick')) {
            return in_array('WEBP', \Imagick::queryFormats());
        }
        if (extension_loaded('gd')) {
            return !empty(gd_info()['WebP Support']);
        }
        return false;
    }

    public function getDimensions(string $path): array
    {
        $size = @getimagesize($path);
        return $size ? [$size[0], $size[1]] : [0, 0];
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1_048_576) return round($bytes / 1_048_576, 1) . ' MB';
        if ($bytes >= 1_024)    return round($bytes / 1_024, 0) . ' kB';
        return $bytes . ' B';
    }

    private function loadImage(string $path, int $type): ?\GdImage
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            IMAGETYPE_GIF  => @imagecreatefromgif($path),
            default        => null,
        };
    }
}
