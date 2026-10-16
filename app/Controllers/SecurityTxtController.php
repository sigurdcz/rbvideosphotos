<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Logger;
use App\Repositories\SiteRepository;
use App\Services\PublishService;
use App\Services\SecurityTxtService;

class SecurityTxtController extends Controller
{
    public function index(array $params): void
    {
        $this->requireAdmin();
        $site = (new SiteRepository())->findFirst();
        if (!$site) {
            $this->view('security-txt.index', [
                'site' => null,
                'state' => null,
                'formErrors' => [],
                'success' => $this->getFlash('success'),
                'error' => $this->getFlash('error'),
            ]);
            return;
        }

        $state = (new SecurityTxtService())->adminState($site);
        Auth::start();
        $old = $_SESSION['security_txt_old'] ?? null;
        $formErrors = $_SESSION['security_txt_errors'] ?? [];
        unset($_SESSION['security_txt_old'], $_SESSION['security_txt_errors']);
        if (is_array($old)) {
            $state['config'] = array_merge($state['config'], $old);
            $state['config']['expires_at'] = (string) ($old['expires'] ?? $old['expires_at'] ?? '');
        }

        $this->view('security-txt.index', [
            'site' => $site,
            'state' => $state,
            'formErrors' => is_array($formErrors) ? $formErrors : [],
            'success' => $this->getFlash('success'),
            'error' => $this->getFlash('error'),
        ]);
    }

    public function update(array $params): void
    {
        $this->requireAdmin();
        $this->csrfCheck();
        $site = (new SiteRepository())->findFirst();
        if (!$site) {
            $this->flash('error', 'Web nenalezen.');
            $this->redirect('/admin/security-txt');
        }

        $input = [
            'enabled' => isset($_POST['enabled']) ? 1 : 0,
            'contacts' => is_array($_POST['contacts'] ?? null) ? $_POST['contacts'] : [],
            'expires' => trim((string) ($_POST['expires'] ?? '')),
            'canonical_url' => trim((string) ($_POST['canonical_url'] ?? '')),
            'encryption_url' => trim((string) ($_POST['encryption_url'] ?? '')),
            'policy_url' => trim((string) ($_POST['policy_url'] ?? '')),
            'acknowledgments_url' => trim((string) ($_POST['acknowledgments_url'] ?? '')),
            'preferred_languages' => trim((string) ($_POST['preferred_languages'] ?? '')),
            'hiring_url' => trim((string) ($_POST['hiring_url'] ?? '')),
            'comments' => (string) ($_POST['comments'] ?? ''),
        ];

        $service = new SecurityTxtService();
        $result = $service->save($site, $input);
        if (empty($result['success'])) {
            Auth::start();
            $_SESSION['security_txt_old'] = $input;
            $_SESSION['security_txt_errors'] = $result['validation']['errors'] ?? [];
            $this->flash('error', 'Security.txt nebyl ulozen. Oprav validacni chyby.');
            $this->redirect('/admin/security-txt');
        }

        $publishWarning = null;
        try {
            $publish = new PublishService();
            $publish->generateSecurityTxt((int) $site['id']);
            $publish->generateHtaccess((int) $site['id']);
        } catch (\Throwable $e) {
            $publishWarning = 'Nastavení bylo uloženo, ale statický export se nepodařilo obnovit.';
            Logger::warning('security_txt', $publishWarning, [
                'site_id' => (int) $site['id'],
                'error' => $e->getMessage(),
            ]);
        }

        $user = Auth::user() ?? [];
        Logger::info('security_txt', 'security.txt configuration updated', [
            'site_id' => (int) $site['id'],
            'user_id' => (int) ($user['id'] ?? 0),
            'enabled' => !empty($input['enabled']),
            'contact_count' => count(array_filter(array_map('trim', $input['contacts']))),
        ]);

        $warnings = array_filter([$result['cache_warning'] ?? null, $publishWarning]);
        $message = 'Security.txt nastaveni bylo ulozeno.';
        if ($warnings !== []) {
            $message .= ' ' . implode(' ', $warnings);
        }
        $this->flash($warnings === [] ? 'success' : 'error', $message);
        $this->redirect('/admin/security-txt');
    }
}
