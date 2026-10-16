<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Security\RequestThrottle;
use App\Services\AccountPasswordService;
use InvalidArgumentException;

final class AccountController extends Controller
{
    public function passwordForm(array $params): void
    {
        $this->requireAuth();
        header('Cache-Control: no-store');
        $this->view('account.password');
    }

    public function changePassword(array $params): void
    {
        $this->requireAuth();
        header('Cache-Control: no-store');
        $this->csrfCheck();
        $user = Auth::user();
        $key = 'user:' . (int) $user['id'];
        if (RequestThrottle::isLocked('password-change', $key, 900, 900)) {
            $this->flash('error', 'Příliš mnoho pokusů. Zkuste to za 15 minut.');
            $this->redirect('/admin/account/password');
        }
        RequestThrottle::hit('password-change', $key, 5, 900, 900);
        $password = (string) ($_POST['password'] ?? '');
        if ($password !== (string) ($_POST['password_confirmation'] ?? '')) {
            $this->flash('error', 'Nová hesla se neshodují.');
            $this->redirect('/admin/account/password');
        }
        try {
            // Account identity always comes from the authenticated session, never POST.
            (new AccountPasswordService(Database::getInstance()))->change((int) $user['id'], (string) ($_POST['current_password'] ?? ''), $password);
        } catch (InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('/admin/account/password');
        }
        \App\Core\Logger::info('auth', 'Account password changed', ['user_id' => (int) $user['id']]);
        RequestThrottle::clear('password-change', $key);
        Auth::logout();
        $this->flash('success', 'Heslo bylo změněno. Přihlaste se novým heslem.');
        $this->redirect('/admin/login');
    }
}
