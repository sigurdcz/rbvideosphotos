<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\ContactRepository;
use App\Repositories\SiteRepository;
use App\Services\HomepageContentService;
use App\Services\PublishService;

class ContactController extends Controller
{
    public function edit(array $params): void
    {
        $this->requireAuth();
        $site    = (new SiteRepository())->findFirst();
        $contact = $site ? (new ContactRepository())->findBySiteSafe($site['id']) : [];

        $this->view('contact.edit', [
            'contact' => $contact,
            'site'    => $site,
            'success' => $this->getFlash('success'),
            'error'   => $this->getFlash('error'),
        ]);
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $site = (new SiteRepository())->findFirst();
        if (!$site) {
            $this->flash('error', 'Web nenalezen.');
            $this->redirect('/admin/contact');
        }

        (new ContactRepository())->upsert((int)$site['id'], $_POST, $this->mayChangeRecoveryRecipient((int)$site['id'], $_POST));
        (new HomepageContentService())->ensureHomepageSection((int)$site['id']);
        $user = \App\Core\Auth::user();
        (new PublishService())->publishSite((int)$site['id'], (int)($user['id'] ?? 0));

        $this->flash('success', 'Kontaktní informace uloženy.');
        $this->redirect('/admin/contact');
    }
}
