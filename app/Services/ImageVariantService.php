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
        $src = $this->toTrueColor($src);
        $result = imagewebp($src, $destPath, $quality);
        unset($src);
        return $result;
    }

    /**
     * Create a resized variant. The destination extension determines the real
     * encoder; a .webp path is never populated with disguised JPEG data.
     * If source is smaller than maxW, it is converted without upscaling.
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

        unset($src);
        unset($dst);
        return (bool)$result;
    }

    /**
     * Encode a WebP derivative under an explicit byte budget. Quality is
     * reduced in deterministic steps; failure remains visible to the caller.
     *
     * @return array{width:int,height:int,filesize:int,quality:int}
     */
    public function createResizedWithinBudget(
        string $sourcePath,
        string $destPath,
        int $maxWidth,
        int $maxBytes,
        int $startQuality = 82,
        int $minQuality = 52,
        int $qualityStep = 5
    ): array {
        if (strtolower(pathinfo($destPath, PATHINFO_EXTENSION)) !== 'webp') {
            throw new \InvalidArgumentException('Varianta s datovym limitem musi mit priponu .webp.');
        }
        if (!$this->supportsWebP()) {
            throw new \RuntimeException('WebP encoder neni dostupny; variantu nelze vytvorit.');
        }
        if ($maxWidth <= 0 || $maxBytes <= 0 || $qualityStep <= 0) {
            throw new \InvalidArgumentException('Rozmery, datovy limit a krok kvality musi byt kladne.');
        }

        $startQuality = max(1, min(100, $startQuality));
        $minQuality = max(1, min($startQuality, $minQuality));
        $lastSize = 0;

        for ($quality = $startQuality; $quality >= $minQuality; $quality -= $qualityStep) {
            if (!$this->createResized($sourcePath, $destPath, $maxWidth, $quality)) {
                @unlink($destPath);
                throw new \RuntimeException('WebP variantu se nepodarilo zakodovat.');
            }

            clearstatcache(true, $destPath);
            $lastSize = (int)(filesize($destPath) ?: 0);
            if ($lastSize > 0 && $lastSize <= $maxBytes) {
                [$width, $height] = $this->getDimensions($destPath);
                return [
                    'width' => $width,
                    'height' => $height,
                    'filesize' => $lastSize,
                    'quality' => $quality,
                ];
            }
        }

        @unlink($destPath);
        throw new \RuntimeException(sprintf(
            'WebP variantu nelze dostat pod limit %d B (posledni velikost %d B).',
            $maxBytes,
            $lastSize
        ));
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

        unset($src);
        unset($dst);
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

    /**
     * GD cannot encode palette-backed PNG/GIF resources directly as WebP.
     * Copying onto an alpha-enabled true-color canvas keeps transparent pixels
     * and avoids leaking a PHP warning from imagewebp().
     */
    private function toTrueColor(\GdImage $source): \GdImage
    {
        if (imageistruecolor($source)) {
            return $source;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $converted = imagecreatetruecolor($width, $height);
        imagealphablending($converted, false);
        imagesavealpha($converted, true);
        $transparent = imagecolorallocatealpha($converted, 0, 0, 0, 127);
        imagefill($converted, 0, 0, $transparent);
        imagecopy($converted, $source, 0, 0, 0, 0, $width, $height);
        unset($source);

        return $converted;
    }
}
