<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Repositories\BlogRepository;
use App\Repositories\BlockRepository;
use App\Repositories\ContactRepository;
use App\Repositories\FaqRepository;
use App\Repositories\MediaRepository;
use App\Repositories\PageRepository;
use App\Repositories\PortfolioRepository;
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

        if ($this->updateKnownEntity($page, $key, $content)) {
            $user   = Auth::user();
            $result = (new PublishService())->publishPage($pageId, $user['id']);

            if (!$result['success']) {
                $this->json(['ok' => false, 'error' => $result['message']], 500);
            }

            $this->json(['ok' => true]);
        }

        $blocks = new BlockRepository();
        $blocks->updateContent($pageId, $key, $content);

        $this->maybeSyncFaqToHomepage($page, $key, $content);

        $user   = Auth::user();
        $result = (new PublishService())->publishPage($pageId, $user['id']);

        if (!$result['success']) {
            $this->json(['ok' => false, 'error' => $result['message']], 500);
        }

        $this->json(['ok' => true]);
    }

    private function updateKnownEntity(array $page, string $key, string $content): bool
    {
        if (str_starts_with($key, 'faq:')) {
            return $this->updateFaqInline($page, $key, $content);
        }
        if (str_starts_with($key, 'blog:')) {
            return $this->updateBlogInline($page, $key, $content);
        }
        if (str_starts_with($key, 'portfolio:')) {
            return $this->updatePortfolioInline($page, $key, $content);
        }
        if (str_starts_with($key, 'contact:')) {
            return $this->updateContactInline($page, $key, $content);
        }

        return false;
    }

    private function updateFaqInline(array $page, string $key, string $content): bool
    {
        $parts = explode(':', $key, 3);
        if (count($parts) !== 3 || !in_array($parts[2], ['question', 'answer'], true)) {
            return false;
        }

        $repo = new FaqRepository();
        $item = $repo->findById((int)$parts[1]);
        if (!$item || (int)$item['site_id'] !== (int)$page['site_id']) {
            return false;
        }

        $item[$parts[2]] = $content;
        $repo->update((int)$item['id'], $item);
        return true;
    }

    private function updateBlogInline(array $page, string $key, string $content): bool
    {
        $parts = explode(':', $key, 3);
        if (count($parts) !== 3 || !in_array($parts[2], ['title', 'excerpt', 'content'], true)) {
            return false;
        }

        $repo = new BlogRepository();
        $post = $repo->findById((int)$parts[1]);
        if (!$post || (int)$post['site_id'] !== (int)$page['site_id']) {
            return false;
        }

        $post[$parts[2]] = $content;
        $repo->update((int)$post['id'], $post);
        return true;
    }

    private function updatePortfolioInline(array $page, string $key, string $content): bool
    {
        $parts = explode(':', $key, 3);
        if (count($parts) !== 3 || !in_array($parts[2], ['title', 'description'], true)) {
            return false;
        }

        $repo = new PortfolioRepository();
        $entry = $repo->findById((int)$parts[1]);
        if (!$entry || (int)$entry['site_id'] !== (int)$page['site_id']) {
            return false;
        }

        $entry[$parts[2]] = $content;
        $repo->update((int)$entry['id'], $entry);
        return true;
    }

    private function updateContactInline(array $page, string $key, string $content): bool
    {
        $field = substr($key, strlen('contact:'));
        $allowed = ['phone_1', 'phone_2', 'phone_3', 'email', 'address_1', 'address_2'];
        if (!in_array($field, $allowed, true)) {
            return false;
        }

        $repo = new ContactRepository();
        $current = $repo->findBySite((int)$page['site_id']) ?: [];
        $current[$field] = $content;
        $repo->upsert((int)$page['site_id'], $current);
        return true;
    }

    private function maybeSyncFaqToHomepage(array $page, string $key, string $content): void
    {
        if (!in_array($key, ['faq_1', 'faq_2', 'faq_3', 'faq_4'], true)) return;
        if (ltrim((string)($page['url_path'] ?? ''), '/') !== 'faq') return;

        $homePage = (new PageRepository())->findByUrlPath('');
        if (!$homePage) return;

        (new BlockRepository())->upsert((int)$homePage['id'], $key, [
            'block_type' => 'html',
            'label'      => $key,
            'content'    => $content,
            'sort_order' => 0,
        ]);
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
