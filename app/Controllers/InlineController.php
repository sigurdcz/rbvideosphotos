<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Repositories\BlockRepository;
use App\Repositories\MediaRepository;
use App\Repositories\PageRepository;
use App\Repositories\SiteRepository;
use App\Services\ImageVariantService;
use App\Services\PublishService;

class InlineController extends Controller
{
    public function update(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $pageId  = (int)($_POST['page_id']   ?? 0);
        $key     = trim($_POST['block_key']  ?? '');
        $content = $_POST['content']         ?? '';

        if (!$pageId || $key === '') {
            $this->json(['ok' => false, 'error' => 'Chybí parametry.'], 400);
        }

        $pages = new PageRepository();
        $page  = $pages->findById($pageId);
        if (!$page) {
            $this->json(['ok' => false, 'error' => 'Stránka nenalezena.'], 404);
        }

        $blocks = new BlockRepository();
        $blocks->updateContent($pageId, $key, $content);

        $user   = Auth::user();
        $result = (new PublishService())->publishPage($pageId, $user['id']);

        if (!$result['success']) {
            $this->json(['ok' => false, 'error' => $result['message']], 500);
        }

        $this->json(['ok' => true]);
    }

    public function upload(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $pageId = (int)($_POST['page_id']  ?? 0);
        $key    = trim($_POST['block_key'] ?? '');

        if (!$pageId || $key === '') {
            $this->json(['ok' => false, 'error' => 'Chybí parametry.'], 400);
        }

        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->json(['ok' => false, 'error' => 'Upload selhal.'], 400);
        }

        $appCfg      = Config::get('app');
        $allowedExt  = $appCfg['allowed_image_extensions'];
        $allowedMime = $appCfg['allowed_image_types'];

        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mime = mime_content_type($file['tmp_name']);

        if (!in_array($ext, $allowedExt, true) || !in_array($mime, $allowedMime, true)) {
            $this->json(['ok' => false, 'error' => 'Nepodporovaný typ souboru.'], 400);
        }

        if ($file['size'] > $appCfg['upload_max_size']) {
            $this->json(['ok' => false, 'error' => 'Soubor je příliš velký.'], 400);
        }

        $sites   = new SiteRepository();
        $pages   = new PageRepository();
        $page    = $pages->findById($pageId);
        if (!$page) {
            $this->json(['ok' => false, 'error' => 'Stránka nenalezena.'], 404);
        }
        $site = $sites->findById($page['site_id']);

        $storagePath = 'storage/media/' . date('Y/m');
        $fullDir     = dirname(__DIR__, 2) . '/' . $storagePath;
        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }

        $newName = uniqid('img_', true) . '.' . $ext;
        $dest    = $fullDir . '/' . $newName;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $this->json(['ok' => false, 'error' => 'Nepodařilo se uložit soubor.'], 500);
        }

        $variant = new ImageVariantService();
        [$w, $h] = $variant->getDimensions($dest);

        $mediaRepo = new MediaRepository();
        $mediaId   = $mediaRepo->create([
            'site_id'           => $site['id'],
            'original_filename' => basename($file['name']),
            'storage_path'      => $storagePath . '/' . $newName,
            'alt_text'          => '',
            'title'             => '',
            'width'             => $w,
            'height'            => $h,
            'mime_type'         => $mime,
        ]);

        // Update block content with the public-facing path
        $publicPath = '/' . $storagePath . '/' . $newName;
        (new BlockRepository())->updateContent($pageId, $key, $publicPath);

        $user   = Auth::user();
        $result = (new PublishService())->publishPage($pageId, $user['id']);

        if (!$result['success']) {
            $this->json(['ok' => false, 'error' => $result['message']], 500);
        }

        $this->json(['ok' => true, 'url' => $publicPath, 'media_id' => $mediaId]);
    }
}
