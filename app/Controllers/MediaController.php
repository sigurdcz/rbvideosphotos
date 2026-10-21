<?php

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Repositories\MediaRepository;
use App\Repositories\SiteRepository;
use App\Services\MediaIngestionService;

class MediaController extends Controller
{
    public function picker(array $params): void
    {
        $this->requireAuth();
        $site = (new SiteRepository())->findFirst();
        if (!$site) $this->json(['error' => 'Web nenalezen'], 404);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $kind = (string)($_GET['kind'] ?? 'all');
        if (!in_array($kind, ['all', 'image', 'video'], true)) $kind = 'all';
        $result = (new MediaRepository())->pickerPage(
            (int)$site['id'], trim((string)($_GET['q'] ?? '')), $page, 24,
            ($_GET['images'] ?? '') === '1', $kind
        );
        $publicUrl = static function(string $path): string {
            $path = str_replace('\\', '/', trim($path));
            if (str_starts_with($path, '/media/')) return $path;
            if (str_starts_with($path, '/storage/media/')) return '/media/' . ltrim(substr($path, 15), '/');
            if (str_starts_with($path, 'storage/media/')) return '/media/' . ltrim(substr($path, 14), '/');
            return '/' . ltrim($path, '/');
        };
        $base = dirname(__DIR__, 2) . '/';
        $items = array_map(static function (array $m) use ($publicUrl, $base): array {
            $isVideo = str_starts_with((string)$m['mime_type'], 'video/');
            $item = [
                'id' => (int)$m['id'], 'name' => (string)$m['original_filename'],
                'url' => $publicUrl((string)$m['storage_path']),
                // A video without a captured thumbnail gets no image at all; the picker draws a placeholder.
                'thumb' => $isVideo && !$m['thumb_path'] ? '' : $publicUrl((string)($m['thumb_path'] ?: $m['storage_path'])),
                'mime' => (string)$m['mime_type'], 'alt' => (string)($m['alt_text'] ?? ''),
            ];
            if ($isVideo) {
                $bytes = (int)@filesize($base . ltrim((string)$m['storage_path'], '/'));
                $item['video'] = \App\Services\VideoMediaInfo::describe((int)$m['width'], (int)$m['height'], $bytes);
            }
            return $item;
        }, $result['items']);
        $this->json(['items' => $items, 'total' => $result['total'], 'page' => $page,
            'pages' => max(1, (int)ceil($result['total'] / $result['per_page']))]);
    }

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
        $imagesOnly  = ($_POST['images_only'] ?? '') === '1';

        $altText = trim($_POST['alt_text'] ?? '');
        if ($altText === '') {
            $this->json(['error' => 'ALT text je povinný.'], 400);
        }
        $title = trim($_POST['title'] ?? '') ?: $altText;

        $files = $this->normalizeFiles($_FILES['files'] ?? $_FILES['file'] ?? null);
        if (empty($files)) {
            // $_FILES is empty - most likely post_max_size exceeded on the server
            $phpLimit = ini_get('post_max_size');
            $this->json(['error' => 'Žádné soubory nebyly přijaty serverem. Pravděpodobně překročen limit velikosti POST dat (' . $phpLimit . '). Zkuste menší soubor.'], 400);
        }

        $results = [];
        $ingest  = new MediaIngestionService();
        $repo    = new MediaRepository();

        foreach ($files as $index => $file) {
            if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
                $results[] = ['error' => 'Soubor je příliš velký (PHP limit): ' . $file['name']];
                continue;
            }
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $results[] = ['error' => 'Upload selhal (kód ' . $file['error'] . '): ' . $file['name']];
                continue;
            }

            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $mime     = $ingest->detectMimeType($file['tmp_name'], $ext);
            $isVideo  = in_array($mime, $appCfg['allowed_video_types']) || in_array($ext, $appCfg['allowed_video_extensions']);
            $maxSize  = $isVideo ? $appCfg['upload_video_max_size'] : $appCfg['upload_max_size'];

            if ($imagesOnly && (!in_array($ext, $appCfg['allowed_image_extensions'], true) || !in_array($mime, $appCfg['allowed_image_types'], true))) {
                $results[] = ['error' => 'Pro toto pole lze nahrat pouze obrazek: ' . $file['name']];
                continue;
            }

            if (!in_array($ext, $allowedExt) || !in_array($mime, $allowedMime)) {
                $results[] = ['error' => 'Nepodporovaný typ (' . $mime . '): ' . $file['name']];
                continue;
            }

            if ($file['size'] > $maxSize) {
                $mb = round($maxSize / 1_048_576);
                $results[] = ['error' => 'Příliš velký soubor (max ' . $mb . ' MB): ' . $file['name']];
                continue;
            }

            try {
                $stored = $ingest->ingestUploadedFile(
                    (int)$site['id'],
                    (string)$file['tmp_name'],
                    (string)$file['name'],
                    $altText,
                    $title
                );
            } catch (\Throwable $e) {
                $results[] = ['error' => 'Zpracování selhalo: ' . $file['name'] . ' - ' . $e->getMessage()];
                continue;
            }

            $mediaId = (int)$stored['media_id'];
            $videoInfo = null;
            if ($isVideo) {
                // The browser measured the video and captured a frame (no ffmpeg on the server);
                // the values are untrusted and sanitised in the service.
                $meta = json_decode((string)($_POST['video_meta_' . $index] ?? ''), true);
                $thumbFile = $_FILES['video_thumb_' . $index] ?? null;
                $thumbTmp = is_array($thumbFile) && ($thumbFile['error'] ?? 1) === UPLOAD_ERR_OK
                    && is_uploaded_file((string)$thumbFile['tmp_name'])
                    ? (string)$thumbFile['tmp_name']
                    : null;
                $details = $ingest->attachVideoDetails($mediaId, (string)$stored['storage_path'], is_array($meta) ? $meta : [], $thumbTmp);
                $videoInfo = \App\Services\VideoMediaInfo::describe(
                    $details['width'],
                    $details['height'],
                    (int)@filesize(dirname(__DIR__, 2) . '/' . ltrim((string)$stored['storage_path'], '/'))
                );
                $videoInfo['thumb'] = $details['thumb'];
            }
            $storedMedia = $repo->findByIdWithThumb($mediaId);
            $r = [
                'success' => true,
                'media_id' => $mediaId,
                'name' => basename($file['name']),
                'mime_type' => $mime,
                'storage_path' => $storedMedia['storage_path'] ?? $stored['storage_path'],
                'thumb_path' => $storedMedia['thumb_path'] ?? null,
                'url' => $this->publicMediaUrl((string)($storedMedia['storage_path'] ?? $stored['storage_path'])),
                'thumb_url' => $this->publicMediaUrl((string)($storedMedia['thumb_path'] ?? $storedMedia['storage_path'] ?? $stored['storage_path'])),
                'alt_text' => $altText,
                'title' => $title,
            ];
            if ($videoInfo !== null) {
                $r['video'] = $videoInfo;
            }
            $results[] = $r;
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

    private function publicMediaUrl(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        if (str_starts_with($path, '/media/')) return $path;
        if (str_starts_with($path, '/storage/media/')) return '/media/' . ltrim(substr($path, 15), '/');
        if (str_starts_with($path, 'storage/media/')) return '/media/' . ltrim(substr($path, 14), '/');
        return '/' . ltrim($path, '/');
    }

}
