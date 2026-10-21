<?php

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use App\Repositories\MediaRepository;
use App\Repositories\SiteRepository;

class MediaIngestionService
{
    private const RESPONSIVE_VARIANTS = [
        'thumb' => ['width' => 240, 'bytes' => 50 * 1024, 'suffix' => '_th'],
        'small' => ['width' => 480, 'bytes' => 90 * 1024, 'suffix' => '_sm'],
        'medium' => ['width' => 720, 'bytes' => 72 * 1024, 'suffix' => '_md'],
        'large' => ['width' => 1280, 'bytes' => 240 * 1024, 'suffix' => '_lg'],
    ];

    private MediaRepository $media;
    private SiteRepository $sites;
    private ImageVariantService $variants;

    public function __construct()
    {
        $this->media = new MediaRepository();
        $this->sites = new SiteRepository();
        $this->variants = new ImageVariantService();
    }

    public function ingestUploadedFile(
        int $siteId,
        string $sourcePath,
        string $originalFilename,
        string $altText,
        string $title,
        bool $requireUploadedFile = true
    ): array {
        $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $mime = $this->detectMimeType($sourcePath, $ext);
        $isVideo = $this->isVideo($mime, $ext);

        $this->assertAllowed($mime, $ext, $isVideo);

        $storagePath = 'storage/media/' . date('Y/m');
        $fullDir = dirname(__DIR__, 2) . '/' . $storagePath;
        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }

        $prefix = $isVideo ? 'vid_' : 'img_';
        $newName = uniqid($prefix, true) . '.' . $ext;
        $dest = $fullDir . '/' . $newName;

        $stored = $requireUploadedFile
            ? move_uploaded_file($sourcePath, $dest)
            : copy($sourcePath, $dest);

        if (!$stored) {
            throw new \RuntimeException('Nepodařilo se uložit médium: ' . $originalFilename);
        }

        return $this->createMediaRecord($siteId, $originalFilename, $storagePath . '/' . $newName, $dest, $altText, $title, $mime, $ext, $isVideo);
    }

    /**
     * Completes a freshly stored video with what the browser found out about it: its
     * dimensions and one captured frame as the thumbnail. The browser values are untrusted
     * (see VideoMediaInfo), and the frame is decoded and re-encoded as WebP, so whatever the
     * client sent never reaches disk as-is. A failing thumbnail never fails the upload.
     *
     * @return array{width:int,height:int,thumb:bool,thumb_error:?string}
     */
    public function attachVideoDetails(int $mediaId, string $storagePath, array $meta, ?string $thumbTmpPath): array
    {
        $info = VideoMediaInfo::sanitize($meta);
        $this->media->updateDimensions($mediaId, $info['width'], $info['height']);

        $result = ['width' => $info['width'], 'height' => $info['height'], 'thumb' => false, 'thumb_error' => null];
        if ($thumbTmpPath === null || $thumbTmpPath === '') {
            return $result;
        }

        $dest = '';
        try {
            if (!extension_loaded('gd')) {
                throw new \RuntimeException('PHP rozsireni GD neni nactene.');
            }
            $size = (int)@filesize($thumbTmpPath);
            if ($size <= 0 || $size > 3 * 1024 * 1024) {
                throw new \RuntimeException('Miniatura ma nepovolenou velikost.');
            }
            $image = @getimagesize($thumbTmpPath);
            $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
            if (!$image || !in_array($image[2], $allowed, true) || $image[0] < 1 || $image[1] < 1
                || $image[0] * $image[1] > 16_000_000) {
                throw new \RuntimeException('Miniatura neni platny obrazek.');
            }

            $fullDir = dirname(__DIR__, 2) . '/' . dirname($storagePath);
            $name = pathinfo($storagePath, PATHINFO_FILENAME) . '_th.webp';
            $dest = $fullDir . '/' . $name;
            $encoded = $this->variants->createResizedWithinBudget($thumbTmpPath, $dest, 480, 70 * 1024, 82, 47, 5);
            $this->replaceVariantFile($mediaId, [
                'format' => 'thumb',
                'width' => $encoded['width'],
                'height' => $encoded['height'],
                'path' => dirname($storagePath) . '/' . $name,
                'filesize' => $encoded['filesize'],
            ]);
            $result['thumb'] = true;
        } catch (\Throwable $e) {
            if ($dest !== '' && is_file($dest)) {
                @unlink($dest);
            }
            $result['thumb_error'] = $e->getMessage();
            Logger::warning('media', 'Video thumbnail was not stored', ['media_id' => $mediaId, 'error' => $e->getMessage()]);
        }

        return $result;
    }

    public function ingestRemoteImage(
        int $siteId,
        string $url,
        string $storageKey,
        string $altText,
        string $title
    ): array {
        $storageKey = $this->safeStorageKey($storageKey);
        $storagePath = 'storage/media/' . date('Y/m');
        $fullDir = dirname(__DIR__, 2) . '/' . $storagePath;
        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }

        $filename = 'seed-' . $storageKey . '.png';
        $relativePath = $storagePath . '/' . $filename;
        $dest = $fullDir . '/' . $filename;

        $existing = $this->media->findByStoragePath($siteId, $relativePath);
        if ($existing) {
            return [
                'media_id' => (int)$existing['id'],
                'storage_path' => $relativePath,
                'variant_count' => count($this->media->variantsByMediaId((int)$existing['id'])),
                'reused' => true,
            ];
        }

        if (!is_file($dest)) {
            $this->download($url, $dest);
        }

        $mime = $this->detectMimeType($dest, 'png');
        $this->assertAllowed($mime, 'png', false);

        return $this->createMediaRecord($siteId, $filename, $relativePath, $dest, $altText, $title, $mime, 'png', false);
    }

    public function detectMimeType(string $path, string $ext): string
    {
        if (class_exists('\finfo')) {
            $fi = new \finfo(\FILEINFO_MIME_TYPE);
            $result = $fi->file($path);
            if ($result && $result !== false) {
                return $result;
            }
        }
        if (function_exists('mime_content_type')) {
            $result = \mime_content_type($path);
            if ($result && $result !== false) {
                return $result;
            }
        }

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            'avi' => 'video/x-msvideo',
            'mkv' => 'video/x-matroska',
            default => 'application/octet-stream',
        };
    }

    public function isVideo(string $mime, string $ext): bool
    {
        $appCfg = Config::get('app');
        return in_array($mime, $appCfg['allowed_video_types'], true)
            || in_array($ext, $appCfg['allowed_video_extensions'], true);
    }

    /**
     * Rebuild derivatives for an already registered image whose original file
     * exists. Demo imports use this after an earlier run was interrupted while
     * GD was unavailable; the missing capability remains a visible error.
     */
    public function regenerateImageVariants(int $siteId, int $mediaId): int
    {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('PHP rozšíření GD není načtené; nelze vytvořit thumbnail média #' . $mediaId);
        }

        $media = $this->media->findById($mediaId);
        if (!$media || (int)($media['site_id'] ?? 0) !== $siteId) {
            throw new \RuntimeException('Médium #' . $mediaId . ' nepatří do aktuálního webu.');
        }

        $relativePath = (string)($media['storage_path'] ?? '');
        $absolutePath = dirname(__DIR__, 2) . '/' . ltrim($relativePath, '/');
        if ($relativePath === '' || !is_file($absolutePath)) {
            throw new \RuntimeException('Chybí originální soubor média #' . $mediaId);
        }

        $mime = (string)($media['mime_type'] ?? '');
        $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        if ($this->isVideo($mime, $ext)) {
            throw new \RuntimeException('Varianty obrázku nelze vytvořit pro video médium #' . $mediaId);
        }

        [$width, $height] = $this->variants->getDimensions($absolutePath);
        if ($width <= 0 || $height <= 0) {
            throw new \RuntimeException('Nelze načíst rozměry média #' . $mediaId);
        }

        return $this->generateVariants(
            $siteId,
            $mediaId,
            $absolutePath,
            dirname($relativePath),
            dirname($absolutePath),
            $width,
            $height,
            $ext
        );
    }

    public function regenerateImageVariantsByStoragePath(int $siteId, string $storagePath): int
    {
        $path = parse_url(trim($storagePath), PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            throw new \RuntimeException('Cesta média pro regeneraci je prázdná nebo neplatná.');
        }
        $normalized = str_replace('\\', '/', ltrim($path, '/'));
        if (str_starts_with($normalized, 'media/')) {
            $normalized = 'storage/media/' . substr($normalized, strlen('media/'));
        }
        if (!str_starts_with($normalized, 'storage/media/')) {
            throw new \RuntimeException('Regenerovat lze pouze médium z Media Library: ' . $storagePath);
        }
        $media = $this->media->findByStoragePath($siteId, $normalized);
        if (!$media) {
            throw new \RuntimeException('Médium pro regeneraci nebylo nalezeno: ' . $normalized);
        }
        return $this->regenerateImageVariants($siteId, (int)$media['id']);
    }

    private function createMediaRecord(
        int $siteId,
        string $originalFilename,
        string $relativePath,
        string $absolutePath,
        string $altText,
        string $title,
        string $mime,
        string $ext,
        bool $isVideo
    ): array {
        $w = $h = 0;
        if (!$isVideo) {
            [$w, $h] = $this->variants->getDimensions($absolutePath);
        }

        $mediaId = $this->media->create([
            'site_id' => $siteId,
            'original_filename' => basename($originalFilename),
            'storage_path' => $relativePath,
            'alt_text' => $altText,
            'title' => $title,
            'width' => $w,
            'height' => $h,
            'mime_type' => $mime,
        ]);

        $variantCount = 0;
        if (!$isVideo) {
            try {
                $variantCount = $this->generateVariants($siteId, $mediaId, $absolutePath, dirname($relativePath), dirname($absolutePath), $w, $h, $ext);
            } catch (\Throwable $e) {
                foreach ($this->media->variantsByMediaId($mediaId) as $variant) {
                    $variantPath = dirname(__DIR__, 2) . '/' . ltrim((string)($variant['path'] ?? ''), '/');
                    if (is_file($variantPath)) @unlink($variantPath);
                }
                $this->media->delete($mediaId);
                if (is_file($absolutePath)) @unlink($absolutePath);
                throw $e;
            }
        }

        return [
            'media_id' => $mediaId,
            'storage_path' => $relativePath,
            'variant_count' => $variantCount,
            'reused' => false,
        ];
    }

    private function generateVariants(
        int $siteId,
        int $mediaId,
        string $dest,
        string $storagePath,
        string $fullDir,
        int $w,
        int $h,
        string $ext
    ): int {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('PHP rozsireni GD neni nactene; povinne obrazove varianty nelze vytvorit.');
        }

        $isGif = $ext === 'gif';
        $created = 0;
        $quality = $this->mediaQuality($siteId);

        if (!$isGif && $ext !== 'webp' && $this->variants->supportsWebP()) {
            $webpName = uniqid('img_', true) . '.webp';
            $webpDest = $fullDir . '/' . $webpName;
            if ($this->variants->createWebP($dest, $webpDest, $quality)) {
                $this->replaceVariantFile($mediaId, [
                    'format' => 'webp',
                    'width' => $w,
                    'height' => $h,
                    'path' => $storagePath . '/' . $webpName,
                    'filesize' => @filesize($webpDest) ?: null,
                ]);
                $created++;
            }
        }

        if (!$isGif) {
            foreach (self::RESPONSIVE_VARIANTS as $format => $spec) {
                $name = uniqid('img_', true) . $spec['suffix'] . '.webp';
                $absolutePath = $fullDir . '/' . $name;
                $meta = $this->variants->createResizedWithinBudget(
                    $dest,
                    $absolutePath,
                    $spec['width'],
                    $spec['bytes'],
                    min(82, $quality),
                    47,
                    5
                );
                $this->replaceVariantFile($mediaId, [
                    'format' => $format,
                    'width' => $meta['width'],
                    'height' => $meta['height'],
                    'path' => $storagePath . '/' . $name,
                    'filesize' => $meta['filesize'],
                ]);
                $created++;
            }
        }

        // OG/Twitter nahled - pevny pomer 1200x630 (cover crop).
        if (!$isGif) {
            $ogName = uniqid('img_', true) . '_og.webp';
            $ogDest = $fullDir . '/' . $ogName;
            if ($this->variants->createCropped($dest, $ogDest, 1200, 630, $quality)) {
                $this->replaceVariantFile($mediaId, [
                    'format' => 'og',
                    'width' => 1200,
                    'height' => 630,
                    'path' => $storagePath . '/' . $ogName,
                    'filesize' => @filesize($ogDest) ?: null,
                ]);
                $created++;
            }
        }

        return $created;
    }

    private function replaceVariantFile(int $mediaId, array $data): void
    {
        $oldPath = $this->media->replaceVariant($mediaId, $data);
        if ($oldPath === null || $oldPath === $data['path']) {
            return;
        }

        $absoluteOldPath = dirname(__DIR__, 2) . '/' . ltrim($oldPath, '/');
        if (is_file($absoluteOldPath) && !unlink($absoluteOldPath)) {
            Logger::warning('media', 'Obsolete media variant could not be removed', [
                'media_id' => $mediaId,
                'path' => $oldPath,
            ]);
        }
    }

    private function assertAllowed(string $mime, string $ext, bool $isVideo): void
    {
        $appCfg = Config::get('app');
        $allowedExt = $isVideo ? $appCfg['allowed_video_extensions'] : $appCfg['allowed_image_extensions'];
        $allowedMime = $isVideo ? $appCfg['allowed_video_types'] : $appCfg['allowed_image_types'];

        if (!in_array($ext, $allowedExt, true) || !in_array($mime, $allowedMime, true)) {
            throw new \RuntimeException('Nepodporovaný typ média: ' . $mime . ' (' . $ext . ')');
        }
    }

    private function download(string $url, string $dest): void
    {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 25,
                'user_agent' => 'SEO-CMS-DemoSeeder/1.0',
            ],
        ]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false || $data === '') {
            throw new \RuntimeException('Nepodařilo se stáhnout obrázek: ' . $url);
        }
        if (file_put_contents($dest, $data) === false) {
            throw new \RuntimeException('Nepodařilo se uložit stažený obrázek: ' . $dest);
        }
    }

    private function safeStorageKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('~[^a-z0-9-]+~', '-', $key) ?? 'demo';
        $key = trim($key, '-');
        return $key !== '' ? $key : 'demo';
    }

    private function mediaQuality(int $siteId): int
    {
        $site = $this->sites->findById($siteId);
        $quality = (int)($site['media_image_quality'] ?? 90);
        if ($quality <= 0) {
            $quality = 90;
        }
        return max(1, min(100, $quality));
    }
}
