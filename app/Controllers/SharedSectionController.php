<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\SharedSectionRepository;
use App\Repositories\SiteRepository;

final class SharedSectionController extends Controller
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $site = (new SiteRepository())->findFirst();
        $sections = $site ? (new SharedSectionRepository())->allBySite((int)$site['id']) : [];
        $this->view('shared-sections.index', ['site' => $site, 'sections' => $sections]);
    }

    public function edit(array $params): void
    {
        $this->requireAuth();
        $site = (new SiteRepository())->findFirst();
        $key = (string)($_GET['key'] ?? '');
        $row = $site ? (new SharedSectionRepository())->findByKey((int)$site['id'], $key) : null;
        if (!$site || !$row || $key !== 'process_cooperation') {
            $this->flash('error', 'Sdilena sekce nebyla nalezena.');
            $this->redirect('/admin/shared-sections');
        }
        $content = json_decode((string)$row['content_json'], true);
        if (!is_array($content)) {
            $this->flash('error', 'Sdilena sekce obsahuje neplatna data.');
            $this->redirect('/admin/shared-sections');
        }
        $this->view('shared-sections.form', ['section' => $row, 'content' => $content]);
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();
        $site = (new SiteRepository())->findFirst();
        if (!$site) {
            $this->flash('error', 'Web nenalezen.');
            $this->redirect('/admin/shared-sections');
        }

        $steps = $this->rows('step', 4);
        $benefits = $this->rows('benefit', 4);
        (new SharedSectionRepository())->updateContent(
            (int)$site['id'],
            'process_cooperation',
            trim((string)($_POST['admin_title'] ?? 'Jak to probiha')),
            [
                'kicker' => trim((string)($_POST['kicker'] ?? '')),
                'title' => trim((string)($_POST['title'] ?? '')),
                'intro' => trim((string)($_POST['intro'] ?? '')),
                'steps' => $steps,
                'benefits' => $benefits,
            ],
            isset($_POST['is_active'])
        );
        $this->flash('success', 'Sdilena sekce byla ulozena.');
        $this->redirect('/admin/shared-sections/edit?key=process_cooperation');
    }

    private function rows(string $prefix, int $count): array
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $title = trim((string)($_POST[$prefix . '_title_' . $i] ?? ''));
            $text = trim((string)($_POST[$prefix . '_text_' . $i] ?? ''));
            if ($title === '' && $text === '') {
                continue;
            }
            $rows[] = [
                'title' => $title,
                'text' => $text,
                'icon' => trim((string)($_POST[$prefix . '_icon_' . $i] ?? '')),
            ];
        }
        return $rows;
    }
}
