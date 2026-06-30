<?php

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use App\Repositories\MediaRepository;

class MediaIngestionService
{
    private MediaRepository $media;
    private ImageVariantService $variants;

    public function __construct()
    {
        $this->media = new MediaRepository();
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
            $variantCount = $this->generateVariants($mediaId, $absolutePath, dirname($relativePath), dirname($absolutePath), $w, $h, $ext);
        }

        return [
            'media_id' => $mediaId,
            'storage_path' => $relativePath,
            'variant_count' => $variantCount,
            'reused' => false,
        ];
    }

    private function generateVariants(
        int $mediaId,
        string $dest,
        string $storagePath,
        string $fullDir,
        int $w,
        int $h,
        string $ext
    ): int {
        if (!extension_loaded('gd')) {
            Logger::warning('media', 'GD extension not loaded - skipping variant generation', ['media_id' => $mediaId]);
            return 0;
        }

        $isGif = $ext === 'gif';
        $created = 0;

        if (!$isGif && $ext !== 'webp' && $this->variants->supportsWebP()) {
            $webpName = uniqid('img_', true) . '.webp';
            $webpDest = $fullDir . '/' . $webpName;
            if ($this->variants->createWebP($dest, $webpDest)) {
                $this->media->addVariant($mediaId, [
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
            $thumbName = uniqid('img_', true) . '_th.webp';
            $thumbDest = $fullDir . '/' . $thumbName;
            if ($this->variants->createResized($dest, $thumbDest, 300)) {
                [$tw, $th] = $this->variants->getDimensions($thumbDest);
                $this->media->addVariant($mediaId, [
                    'format' => 'thumb',
                    'width' => $tw,
                    'height' => $th,
                    'path' => $storagePath . '/' . $thumbName,
                    'filesize' => @filesize($thumbDest) ?: null,
                ]);
                $created++;
            }
        }

        if (!$isGif && $w > 900) {
            $medName = uniqid('img_', true) . '_md.webp';
            $medDest = $fullDir . '/' . $medName;
            if ($this->variants->createResized($dest, $medDest, 900)) {
                [$mw, $mh] = $this->variants->getDimensions($medDest);
                $this->media->addVariant($mediaId, [
                    'format' => 'medium',
                    'width' => $mw,
                    'height' => $mh,
                    'path' => $storagePath . '/' . $medName,
                    'filesize' => @filesize($medDest) ?: null,
                ]);
                $created++;
            }
        }

        // OG/Twitter nahled — pevny pomer 1200x630 (cover crop).
        if (!$isGif) {
            $ogName = uniqid('img_', true) . '_og.webp';
            $ogDest = $fullDir . '/' . $ogName;
            if ($this->variants->createCropped($dest, $ogDest, 1200, 630)) {
                $this->media->addVariant($mediaId, [
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
}
