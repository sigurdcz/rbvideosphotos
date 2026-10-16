<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\MediaRepository;
use App\Repositories\PageRepository;
use App\Repositories\SeoRepository;
use App\Repositories\SiteRepository;
use App\Services\SeoAuditService;

class SeoController extends Controller
{
    public function edit(array $params): void
    {
        $this->requireAuth();
        $pageId  = (int)($_GET['page_id'] ?? 0);
        $pages   = new PageRepository();
        $page    = $pages->findById($pageId);
        if (!$page) {
            $this->flash('error', 'Stránka nenalezena.');
            $this->redirect('/admin/pages');
        }

        $seoRepo = new SeoRepository();
        $seo     = $seoRepo->findByPage($pageId) ?? [];

        $sites   = new SiteRepository();
        $media   = new MediaRepository();
        $site    = $sites->findById($page['site_id']);
        $mediaList = [];
        foreach (['og_image_id', 'twitter_image_id', 'schema_image_id'] as $field) {
            $id = (int)($seo[$field] ?? 0);
            if ($id && ($item = $media->findByIdWithThumb($id))) $mediaList[] = $item;
        }

        // Read published HTML for audit
        $publishedPath = dirname(__DIR__, 2) . '/storage/published';
        $path = $page['url_path'];
        $filePath = ($path === '' || $path === 'index')
            ? $publishedPath . '/index.html'
            : $publishedPath . '/' . trim($path, '/') . '/index.html';

        $publishedHtml = file_exists($filePath) ? file_get_contents($filePath) : '';

        $audit = (new SeoAuditService())->audit($page, $seo, $publishedHtml);

        $this->view('seo.edit', [
            'page'      => $page,
            'seo'       => $seo,
            'mediaList' => $mediaList,
            'audit'     => $audit,
            'success'   => $this->getFlash('success'),
            'error'     => $this->getFlash('error'),
        ]);
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $pageId = (int)($_POST['page_id'] ?? 0);
        $pages = new PageRepository();
        $page = $pages->findById($pageId);
        if (!$page) {
            $this->flash('error', 'Stranka nenalezena.');
            $this->redirect('/admin/pages');
        }

        $ogImageId = $this->validatedImageMediaId((int)($_POST['og_image_id'] ?? 0), (int)$page['site_id'], $pageId);
        $twitterImageId = $this->validatedImageMediaId((int)($_POST['twitter_image_id'] ?? 0), (int)$page['site_id'], $pageId);
        $schemaImageId = $this->validatedImageMediaId((int)($_POST['schema_image_id'] ?? 0), (int)$page['site_id'], $pageId);

        $repo   = new SeoRepository();
        $repo->upsert($pageId, [
            'meta_title'          => $_POST['meta_title']          ?? '',
            'meta_description'    => $_POST['meta_description']    ?? '',
            'meta_keywords'       => $_POST['meta_keywords']       ?? '',
            'canonical_url'       => $_POST['canonical_url']       ?? '',
            'robots_index'        => (int)!empty($_POST['robots_index']),
            'robots_follow'       => (int)!empty($_POST['robots_follow']),
            'og_title'            => $_POST['og_title']            ?? '',
            'og_description'      => $_POST['og_description']      ?? '',
            'og_image_id'         => $ogImageId,
            'twitter_title'       => $_POST['twitter_title']       ?? '',
            'twitter_description' => $_POST['twitter_description'] ?? '',
            'twitter_image_id'    => $twitterImageId,
            'schema_type'             => $_POST['schema_type'] ?? '',
            'schema_name'             => $_POST['schema_name'] ?? '',
            'schema_description'      => $_POST['schema_description'] ?? '',
            'schema_image_id'         => $schemaImageId,
            'schema_contact_auto'     => (int)!empty($_POST['schema_contact_auto']),
            'schema_telephone'        => $_POST['schema_telephone'] ?? '',
            'schema_email'            => $_POST['schema_email'] ?? '',
            'schema_address_street'   => $_POST['schema_address_street'] ?? '',
            'schema_address_locality' => $_POST['schema_address_locality'] ?? '',
        ]);

        $this->flash('success', 'SEO nastavení uloženo.');
        $this->redirect('/admin/seo/edit?page_id=' . $pageId);
    }

    private function validatedImageMediaId(int $mediaId, int $siteId, int $pageId): ?int
    {
        if ($mediaId <= 0) {
            return null;
        }

        $media = (new MediaRepository())->findById($mediaId);
        if (
            !$media
            || (int)($media['site_id'] ?? 0) !== $siteId
            || !str_starts_with((string)($media['mime_type'] ?? ''), 'image/')
        ) {
            $this->flash('error', 'Vybrany socialni obrazek neni platny obrazek tohoto webu.');
            $this->redirect('/admin/seo/edit?page_id=' . $pageId);
        }

        return $mediaId;
    }
}
