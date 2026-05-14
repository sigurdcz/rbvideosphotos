<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\ReviewRepository;
use App\Repositories\SiteRepository;
use App\Services\ReviewContentService;

class ReviewController extends Controller
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $site = (new SiteRepository())->findFirst();
        $items = $site ? $this->repo()->allBySite((int)$site['id']) : [];

        $this->view('reviews.index', [
            'items' => $items,
            'site' => $site,
            'success' => $this->getFlash('success'),
            'error' => $this->getFlash('error'),
        ]);
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->view('reviews.form', ['item' => null]);
    }

    public function store(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $site = (new SiteRepository())->findFirst();
        if (!$site) {
            $this->flash('error', 'Web nenalezen.');
            $this->redirect('/admin/reviews');
        }

        $this->repo()->create([
            'site_id' => (int)$site['id'],
            'author_name' => trim((string)($_POST['author_name'] ?? '')),
            'context_label' => trim((string)($_POST['context_label'] ?? '')),
            'rating' => (int)($_POST['rating'] ?? 5),
            'review_text' => trim((string)($_POST['review_text'] ?? '')),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ]);

        (new ReviewContentService())->ensureHomepageSection((int)$site['id']);
        $this->flash('success', 'Recenze pridana.');
        $this->redirect('/admin/reviews');
    }

    public function edit(array $params): void
    {
        $this->requireAuth();
        $id = (int)($_GET['id'] ?? 0);
        $item = $this->repo()->findById($id);
        if (!$item) {
            $this->flash('error', 'Recenze nenalezena.');
            $this->redirect('/admin/reviews');
        }

        $this->view('reviews.form', [
            'item' => $item,
            'success' => $this->getFlash('success'),
            'error' => $this->getFlash('error'),
        ]);
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $id = (int)($_POST['id'] ?? 0);
        $item = $this->repo()->findById($id);
        if (!$item) {
            $this->flash('error', 'Recenze nenalezena.');
            $this->redirect('/admin/reviews');
        }

        $this->repo()->update($id, [
            'author_name' => trim((string)($_POST['author_name'] ?? '')),
            'context_label' => trim((string)($_POST['context_label'] ?? '')),
            'rating' => (int)($_POST['rating'] ?? 5),
            'review_text' => trim((string)($_POST['review_text'] ?? '')),
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ]);

        (new ReviewContentService())->ensureHomepageSection((int)$item['site_id']);
        $this->flash('success', 'Recenze ulozena.');
        $this->redirect('/admin/reviews/edit?id=' . $id);
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $id = (int)($_POST['id'] ?? 0);
        $item = $this->repo()->findById($id);
        $this->repo()->delete($id);

        if ($item) {
            (new ReviewContentService())->ensureHomepageSection((int)$item['site_id']);
        }

        $this->flash('success', 'Recenze smazana.');
        $this->redirect('/admin/reviews');
    }

    private function repo(): ReviewRepository
    {
        return new ReviewRepository();
    }
}
