<?php

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Repositories\MediaRepository;
use App\Repositories\SiteRepository;
use App\Services\ImageVariantService;

class MediaController extends Controller
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $sites = new SiteRepository();
        $site  = $sites->findFirst();
        $media = $site ? (new MediaRepository())->allBySiteWithVariants($site['id']) : [];

        $this->view('media.index', [
            'media'   => $media,
            'site'    => $site,
            'success' => $this->getFlash('success'),
            'error'   => $this->getFlash('error'),
        ]);
    }

    public function upload(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $sites = new SiteRepository();
        $site  = $sites->findFirst();
        if (!$site) {
            $this->json(['error' => 'Web nenalezen'], 400);
        }

        $appCfg      = Config::get('app');
        $allowedExt  = array_merge($appCfg['allowed_image_extensions'], $appCfg['allowed_video_extensions']);
        $allowedMime = array_merge($appCfg['allowed_image_types'],      $appCfg['allowed_video_types']);

        $files = $this->normalizeFiles($_FILES['files'] ?? $_FILES['file'] ?? null);
        if (empty($files)) {
            // $_FILES is empty — most likely post_max_size exceeded on the server
            $phpLimit = ini_get('post_max_size');
            $this->json(['error' => 'Žádné soubory nebyly přijaty serverem. Pravděpodobně překročen limit velikosti POST dat (' . $phpLimit . '). Zkuste menší soubor.'], 400);
        }

        $results = [];
        $repo    = new MediaRepository();
        $svc     = new ImageVariantService();

        foreach ($files as $file) {
            if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
                $results[] = ['error' => 'Soubor je příliš velký (PHP limit): ' . $file['name']];
                continue;
            }
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $results[] = ['error' => 'Upload selhal (kód ' . $file['error'] . '): ' . $file['name']];
                continue;
            }

            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $mime     = mime_content_type($file['tmp_name']);
            $isVideo  = in_array($mime, $appCfg['allowed_video_types']) || in_array($ext, $appCfg['allowed_video_extensions']);
            $maxSize  = $isVideo ? $appCfg['upload_video_max_size'] : $appCfg['upload_max_size'];

            if (!in_array($ext, $allowedExt) || !in_array($mime, $allowedMime)) {
                $results[] = ['error' => 'Nepodporovaný typ (' . $mime . '): ' . $file['name']];
                continue;
            }

            if ($file['size'] > $maxSize) {
                $mb = round($maxSize / 1_048_576);
                $results[] = ['error' => 'Příliš velký soubor (max ' . $mb . ' MB): ' . $file['name']];
                continue;
            }

            $storagePath = 'storage/media/' . date('Y/m');
            $fullDir     = dirname(__DIR__, 2) . '/' . $storagePath;
            if (!is_dir($fullDir)) mkdir($fullDir, 0755, true);

            $prefix  = $isVideo ? 'vid_' : 'img_';
            $newName = uniqid($prefix, true) . '.' . $ext;
            $dest    = $fullDir . '/' . $newName;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                $results[] = ['error' => 'Uložení selhalo: ' . $file['name']];
                continue;
            }

            $w = $h = 0;
            if (!$isVideo) {
                [$w, $h] = $svc->getDimensions($dest);
            }

            $mediaId = $repo->create([
                'site_id'           => $site['id'],
                'original_filename' => basename($file['name']),
                'storage_path'      => $storagePath . '/' . $newName,
                'alt_text'          => $_POST['alt_text'] ?? '',
                'title'             => $_POST['title']    ?? '',
                'width'             => $w,
                'height'            => $h,
                'mime_type'         => $mime,
            ]);

            if (!$isVideo) {
                $this->generateVariants($repo, $svc, $mediaId, $dest, $storagePath, $fullDir, $w, $h, $ext);
            }

            $results[] = ['success' => true, 'media_id' => $mediaId, 'name' => basename($file['name'])];
        }

        $this->json(['results' => $results]);
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $id   = (int)($_POST['id'] ?? 0);
        $repo = new MediaRepository();
        $repo->update($id, [
            'alt_text' => $_POST['alt_text'] ?? '',
            'title'    => $_POST['title']    ?? '',
        ]);

        $this->flash('success', 'Médium aktualizováno.');
        $this->redirect('/admin/media');
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $id   = (int)($_POST['id'] ?? 0);
        $repo = new MediaRepository();
        $m    = $repo->findById($id);
        if ($m) {
            $base = dirname(__DIR__, 2) . '/';
            @unlink($base . $m['storage_path']);
            foreach ($repo->variantsByMediaId($id) as $v) {
                @unlink($base . $v['path']);
            }
            $repo->delete($id);
        }

        $this->flash('success', 'Médium smazáno.');
        $this->redirect('/admin/media');
    }

    private function normalizeFiles(?array $raw): array
    {
        if (!$raw) return [];
        // Single file: name is a plain string
        if (is_string($raw['name'])) {
            return [$raw];
        }
        // Multiple files: name is an array
        $out = [];
        foreach ($raw['name'] as $i => $_) {
            $out[] = [
                'name'     => $raw['name'][$i],
                'tmp_name' => $raw['tmp_name'][$i],
                'error'    => $raw['error'][$i],
                'size'     => $raw['size'][$i],
            ];
        }
        return $out;
    }

    private function generateVariants(
        MediaRepository   $repo,
        ImageVariantService $svc,
        int    $mediaId,
        string $dest,
        string $storagePath,
        string $fullDir,
        int    $w,
        int    $h,
        string $ext
    ): void {
        $isGif = ($ext === 'gif');

        // WebP conversion of original (skip GIF — preserve animation)
        if (!$isGif && $ext !== 'webp' && $svc->supportsWebP()) {
            $webpName = uniqid('img_', true) . '.webp';
            $webpDest = $fullDir . '/' . $webpName;
            if ($svc->createWebP($dest, $webpDest)) {
                $repo->addVariant($mediaId, [
                    'format'   => 'webp',
                    'width'    => $w,
                    'height'   => $h,
                    'path'     => $storagePath . '/' . $webpName,
                    'filesize' => @filesize($webpDest) ?: null,
                ]);
            }
        }

        // Thumbnail 300px (skip GIF — preserve animation)
        if (!$isGif) {
            $thumbName = uniqid('img_', true) . '_th.webp';
            $thumbDest = $fullDir . '/' . $thumbName;
            if ($svc->createResized($dest, $thumbDest, 300)) {
                [$tw, $th] = $svc->getDimensions($thumbDest);
                $repo->addVariant($mediaId, [
                    'format'   => 'thumb',
                    'width'    => $tw,
                    'height'   => $th,
                    'path'     => $storagePath . '/' . $thumbName,
                    'filesize' => @filesize($thumbDest) ?: null,
                ]);
            }
        }

        // Medium 900px (only if original is wider; skip GIF)
        if (!$isGif && $w > 900) {
            $medName = uniqid('img_', true) . '_md.webp';
            $medDest = $fullDir . '/' . $medName;
            if ($svc->createResized($dest, $medDest, 900)) {
                [$mw, $mh] = $svc->getDimensions($medDest);
                $repo->addVariant($mediaId, [
                    'format'   => 'medium',
                    'width'    => $mw,
                    'height'   => $mh,
                    'path'     => $storagePath . '/' . $medName,
                    'filesize' => @filesize($medDest) ?: null,
                ]);
            }
        }
    }
}
