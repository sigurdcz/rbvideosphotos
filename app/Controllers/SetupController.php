<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Repositories\PageRepository;
use App\Repositories\SiteRepository;

class SetupController extends Controller
{
    public function index(array $params): void
    {
        $this->requireAuth();

        $pages = (new PageRepository());
        $sites = (new SiteRepository());
        $site  = $sites->findFirst();
        $pageCount = $site ? count($pages->allBySite($site['id'])) : 0;

        $this->view('setup.index', [
            'pageCount' => $pageCount,
            'site'      => $site,
        ]);
    }

    public function run(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $seedFile = dirname(__DIR__, 2) . '/database/seeds/seed_photographer.php';
        if (!file_exists($seedFile)) {
            $this->flash('error', 'Seed soubor nenalezen.');
            $this->redirect('/admin/setup');
        }

        try {
            ob_start();
            // Seed file uses $argv for --fresh flag; web context has none → safe.
            include $seedFile;
            $output = ob_get_clean();

            $this->flash('success', 'Data byla úspěšně načtena. ' . htmlspecialchars(trim($output)));
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->flash('error', 'Chyba při seedování: ' . $e->getMessage());
        }

        $this->redirect('/admin/setup');
    }
}
