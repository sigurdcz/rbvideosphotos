<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Repositories\PublishHistoryRepository;
use App\Repositories\SiteRepository;
use App\Services\PublishService;

class PublishController extends Controller
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $sites   = new SiteRepository();
        $site    = $sites->findFirst();
        $history = $site ? (new PublishHistoryRepository())->allBySite($site['id']) : [];

        $this->view('publish.index', [
            'site'    => $site,
            'history' => $history,
            'success' => $this->getFlash('success'),
            'error'   => $this->getFlash('error'),
        ]);
    }

    public function publishPage(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $pageId  = (int)($_POST['page_id'] ?? 0);
        $user    = Auth::user();
        $service = new PublishService();
        $result  = $service->publishPage($pageId, $user['id']);

        if ($result['success']) {
            $this->flash('success', $result['message']);
        } else {
            $this->flash('error', $result['message']);
        }

        $refererPath = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH) ?? '';
        $this->redirect(str_starts_with($refererPath, '/admin/') ? $refererPath : '/admin/pages');
    }

    public function publishSite(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $siteId  = (int)($_POST['site_id'] ?? 0);
        $user    = Auth::user();
        $service = new PublishService();
        $result  = $service->publishSite($siteId, $user['id']);

        if ($result['success']) {
            $this->flash('success', 'Všechny stránky byly publikovány.');
        } else {
            $this->flash('error', 'Chyby při publikování: ' . implode('; ', $result['errors']));
        }

        $this->redirect('/admin/publish');
    }
}
