<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Security\RequestThrottle;
use App\Services\PasswordResetService;

final class PasswordResetController extends Controller
{
    private function headers(): void
    {
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow');
    }
    public function requestForm(array $params): void
    {
        $this->headers();
        $this->view('auth.recovery', ['reset' => false]);
    }
    public function resetForm(array $params): void
    {
        $this->headers();
        $token = (string) ($_GET['token'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            $this->flash('error', 'Odkaz je neplatný nebo již vypršel.');
            $this->redirect('/admin/forgot-password');
        }
        // Remove the secret from the browser URL and subsequent POST/access logs.
        $_SESSION['password_reset_token'] = $token;
        $this->redirect('/admin/new-password');
    }
    public function newPasswordForm(array $params): void
    {
        $this->headers();
        if (empty($_SESSION['password_reset_token'])) { $this->redirect('/admin/forgot-password'); }
        $this->view('auth.recovery', ['reset' => true]);
    }
    public function requestLink(array $params): void
    {
        $this->headers();
        $this->csrfCheck();
        $email = trim((string) ($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'Zadejte platný e-mail účtu.');
            $this->redirect('/admin/forgot-password');
        }
        $keys = ['ip:' . RequestThrottle::currentIp(), 'email:' . strtolower($email)];
        foreach ($keys as $key) {
            if (RequestThrottle::isLocked('password-reset', $key, 900, 900)) {
                $this->flash('error', 'Příliš mnoho žádostí. Zkuste to za 15 minut.');
                $this->redirect('/admin/forgot-password');
            }
        }
        foreach ($keys as $key) { RequestThrottle::hit('password-reset', $key, 3, 900, 900); }
        try {
            (new PasswordResetService(Database::getInstance()))->request($email);
            $this->flash('success', 'Pokud účet existuje, byl e-mail s odkazem předán k odeslání. Zkontrolujte i spam.');
        } catch (\Throwable $e) {
            // Do not log tokens, mail body, passwords or request payloads.
            Logger::error('password_reset', 'Password recovery request failed', ['type' => get_class($e)]);
            $this->flash('error', $e instanceof \RuntimeException && !$e instanceof \PDOException
                ? $e->getMessage() : 'Obnova není dostupná. Správce musí ověřit migrace a nastavení pošty.');
        }
        $this->redirect('/admin/forgot-password');
    }
    public function resetPassword(array $params): void
    {
        $this->headers();
        $this->csrfCheck();
        $password = (string) ($_POST['password'] ?? '');
        if ($password !== (string) ($_POST['password_confirmation'] ?? '')) {
            $this->flash('error', 'Zadaná hesla se neshodují.');
            $this->redirect('/admin/new-password');
        }
        try {
            (new PasswordResetService(Database::getInstance()))->reset((string) ($_SESSION['password_reset_token'] ?? ''), $password);
        } catch (\RuntimeException $e) {
            $this->flash('error', $e instanceof \PDOException ? 'Obnova selhala. Správce musí ověřit databázi.' : $e->getMessage());
            $this->redirect('/admin/new-password');
        }
        unset($_SESSION['password_reset_token']);
        Auth::logout();
        $this->flash('success', 'Heslo bylo změněno. Přihlaste se novým heslem.');
        $this->redirect('/admin/login');
    }
}
