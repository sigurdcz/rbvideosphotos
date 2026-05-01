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
        $media = $site ? (new MediaRepository())->allBySite($site['id']) : [];

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

        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->json(['error' => 'Upload selhal.'], 400);
        }

        $appCfg    = Config::get('app');
        $allowedExt  = $appCfg['allowed_image_extensions'];
        $allowedMime = $appCfg['allowed_image_types'];

        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mime = mime_content_type($file['tmp_name']);

        if (!in_array($ext, $allowedExt) || !in_array($mime, $allowedMime)) {
            $this->json(['error' => 'Nepodporovaný typ souboru.'], 400);
        }

        if ($file['size'] > $appCfg['upload_max_size']) {
            $this->json(['error' => 'Soubor je příliš velký.'], 400);
        }

        $storagePath = 'storage/media/' . date('Y/m');
        $fullDir     = dirname(__DIR__, 2) . '/' . $storagePath;
        if (!is_dir($fullDir)) mkdir($fullDir, 0755, true);

        $newName = uniqid('img_', true) . '.' . $ext;
        $dest    = $fullDir . '/' . $newName;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $this->json(['error' => 'Nepodařilo se uložit soubor.'], 500);
        }

        $variant = new ImageVariantService();
        [$w, $h] = $variant->getDimensions($dest);

        $repo    = new MediaRepository();
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

        // Try WebP variant
        if ($ext !== 'webp') {
            $webpName = uniqid('img_', true) . '.webp';
            $webpDest = $fullDir . '/' . $webpName;
            if ($variant->createWebP($dest, $webpDest)) {
                $repo->addVariant($mediaId, [
                    'format'   => 'webp',
                    'width'    => $w,
                    'height'   => $h,
                    'path'     => $storagePath . '/' . $webpName,
                    'filesize' => filesize($webpDest),
                ]);
            }
        }

        $this->json(['success' => true, 'media_id' => $mediaId, 'path' => $storagePath . '/' . $newName]);
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
            $repo->delete($id);
        }

        $this->flash('success', 'Médium smazáno.');
        $this->redirect('/admin/media');
    }
}
