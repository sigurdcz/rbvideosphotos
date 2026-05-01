<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\RedirectRepository;
use App\Repositories\SiteRepository;
use App\Services\PublishService;

class RedirectController extends Controller
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $sites = new SiteRepository();
        $site  = $sites->findFirst();
        $repo  = new RedirectRepository();

        $this->view('redirects.index', [
            'redirects' => $site ? $repo->allBySite($site['id']) : [],
            'site'      => $site,
            'success'   => $this->getFlash('success'),
            'error'     => $this->getFlash('error'),
        ]);
    }

    public function store(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $sites = new SiteRepository();
        $site  = $sites->findFirst();
        if (!$site) {
            $this->flash('error', 'Web nenalezen.');
            $this->redirect('/admin/redirects');
        }

        $from = '/' . trim($_POST['from_path'] ?? '', '/');
        $to   = '/' . trim($_POST['to_path']   ?? '', '/');
        $code = in_array((int)($_POST['status_code'] ?? 301), [301, 302]) ? (int)$_POST['status_code'] : 301;

        (new RedirectRepository())->create([
            'site_id'     => $site['id'],
            'from_path'   => $from,
            'to_path'     => $to,
            'status_code' => $code,
        ]);

        // Regenerate .htaccess
        (new PublishService())->generateHtaccess($site['id']);

        $this->flash('success', 'Redirect přidán.');
        $this->redirect('/admin/redirects');
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $id   = (int)($_POST['id'] ?? 0);
        $repo = new RedirectRepository();
        $r    = $repo->findById($id);
        if ($r) {
            $repo->delete($id);
            $sites = new SiteRepository();
            $site  = $sites->findById($r['site_id']);
            if ($site) (new PublishService())->generateHtaccess($r['site_id']);
        }

        $this->flash('success', 'Redirect smazán.');
        $this->redirect('/admin/redirects');
    }
}
