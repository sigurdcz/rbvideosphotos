<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\PageRepository;
use App\Repositories\PublishHistoryRepository;
use App\Repositories\SiteRepository;
use App\Repositories\TemplateRepository;

class DashboardController extends Controller
{
    public function index(array $params): void
    {
        $this->requireAuth();

        $sites   = new SiteRepository();
        $pages   = new PageRepository();
        $tmpl    = new TemplateRepository();
        $history = new PublishHistoryRepository();

        $site = $sites->findFirst();
        $allPages = $pages->allForAdmin();
        $allTemplates = $tmpl->allForAdmin();

        $this->view('dashboard.index', [
            'site'         => $site,
            'pagesCount'   => count($allPages),
            'templatesCount' => count($allTemplates),
            'history'      => $site ? $history->allBySite($site['id'], 5) : [],
            'success'      => $this->getFlash('success'),
        ]);
    }
}
