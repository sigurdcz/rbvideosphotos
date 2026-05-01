<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\SiteRepository;
use App\Repositories\TemplateRepository;
use App\Services\TemplateParser;

class TemplateController extends Controller
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $sites = new SiteRepository();
        $repo  = new TemplateRepository();
        $site  = $sites->findFirst();

        $this->view('templates.index', [
            'templates' => $site ? $repo->allBySite($site['id']) : [],
            'site'      => $site,
            'success'   => $this->getFlash('success'),
            'error'     => $this->getFlash('error'),
        ]);
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $sites = new SiteRepository();
        $this->view('templates.form', [
            'template' => null,
            'site'     => $sites->findFirst(),
        ]);
    }

    public function store(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $sites = new SiteRepository();
        $site  = $sites->findFirst();
        if (!$site) {
            $this->flash('error', 'Nejprve nastavte web.');
            $this->redirect('/admin/templates');
        }

        $repo = new TemplateRepository();
        $id   = $repo->create([
            'site_id'     => $site['id'],
            'name'        => trim($_POST['name'] ?? ''),
            'html_source' => $_POST['html_source'] ?? '',
            'css_source'  => $_POST['css_source']  ?? '',
            'js_source'   => $_POST['js_source']   ?? '',
        ]);

        $this->flash('success', 'Šablona uložena.');
        $this->redirect('/admin/templates/edit?id=' . $id);
    }

    public function edit(array $params): void
    {
        $this->requireAuth();
        $id   = (int)($_GET['id'] ?? 0);
        $repo = new TemplateRepository();
        $tmpl = $repo->findById($id);
        if (!$tmpl) {
            $this->flash('error', 'Šablona nenalezena.');
            $this->redirect('/admin/templates');
        }
        $sites = new SiteRepository();
        $this->view('templates.form', [
            'template' => $tmpl,
            'site'     => $sites->findFirst(),
            'success'  => $this->getFlash('success'),
        ]);
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $id   = (int)($_POST['id'] ?? 0);
        $repo = new TemplateRepository();
        $repo->update($id, [
            'name'        => trim($_POST['name'] ?? ''),
            'html_source' => $_POST['html_source'] ?? '',
            'css_source'  => $_POST['css_source']  ?? '',
            'js_source'   => $_POST['js_source']   ?? '',
        ]);

        $this->flash('success', 'Šablona aktualizována.');
        $this->redirect('/admin/templates/edit?id=' . $id);
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();
        $id = (int)($_POST['id'] ?? 0);
        (new TemplateRepository())->delete($id);
        $this->flash('success', 'Šablona smazána.');
        $this->redirect('/admin/templates');
    }

    /** Parse template and return discovered blocks as JSON */
    public function parse(array $params): void
    {
        $this->requireAuth();
        $id     = (int)($_GET['id'] ?? 0);
        $repo   = new TemplateRepository();
        $tmpl   = $repo->findById($id);
        if (!$tmpl) {
            $this->json(['error' => 'Not found'], 404);
        }
        $parser = new TemplateParser();
        $blocks = $parser->parseBlocks($tmpl['html_source']);
        $this->json(['blocks' => $blocks]);
    }
}
