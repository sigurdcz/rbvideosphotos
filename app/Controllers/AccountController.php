<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Security\RequestThrottle;
use App\Services\AccountPasswordService;
use App\Services\AccountUsernameService;
use InvalidArgumentException;

final class AccountController extends Controller
{
    public function usernameForm(array $params): void
    {
        $this->requireAuth();
        header('Cache-Control: no-store');
        $this->view('account.username');
    }

    public function changeUsername(array $params): void
    {
        $this->requireAuth();
        header('Cache-Control: no-store');
        $this->csrfCheck();
        $userId = (int) Auth::user()['id'];
        $key = 'user:' . $userId;
        if (RequestThrottle::isLocked('username-change', $key, 900, 900)) {
            $this->flash('error', 'Příliš mnoho pokusů. Zkuste to za 15 minut.');
            $this->redirect('/admin/account/username');
        }
        RequestThrottle::hit('username-change', $key, 5, 900, 900);
        $username = $_POST['username'] ?? '';
        $currentPassword = $_POST['current_password'] ?? '';
        if (!is_string($username) || !is_string($currentPassword)) {
            $this->flash('error', 'Vyplňte přihlašovací jméno a současné heslo.');
            $this->redirect('/admin/account/username');
        }
        try {
            (new AccountUsernameService(Database::getInstance()))->change($userId, $currentPassword, $username);
        } catch (InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('/admin/account/username');
        }
        Logger::info('auth', 'Account username changed', ['user_id' => $userId]);
        RequestThrottle::clear('username-change', $key);
        $this->flash('success', 'Přihlašovací jméno bylo uloženo.');
        $this->redirect('/admin/account/username');
    }

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
        Logger::info('auth', 'Account password changed', ['user_id' => (int) $user['id']]);
        RequestThrottle::clear('password-change', $key);
        Auth::logout();
        $this->flash('success', 'Heslo bylo změněno. Přihlaste se novým heslem.');
        $this->redirect('/admin/login');
    }
}
