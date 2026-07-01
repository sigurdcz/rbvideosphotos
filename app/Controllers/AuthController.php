<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Logger;
use App\Repositories\UserRepository;
use App\Security\RequestThrottle;

class AuthController extends Controller
{
    public function loginForm(array $params): void
    {
        if (Auth::check()) {
            $this->redirect('/admin');
        }
        $this->view('auth.login', [
            'error' => $this->getFlash('error'),
        ]);
    }

    public function login(array $params): void
    {
        $this->csrfCheck();

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $emailKey = strtolower($email);
        $ip = RequestThrottle::currentIp();
        $ipKey = 'ip:' . $ip;
        $identityKey = 'ip-email:' . $ip . ':' . $emailKey;
        $windowSeconds = 15 * 60;
        $lockSeconds = 15 * 60;
        $maxAttempts = 5;

        if (
            RequestThrottle::isLocked('login', $ipKey, $windowSeconds, $lockSeconds)
            || ($emailKey !== '' && RequestThrottle::isLocked('login', $identityKey, $windowSeconds, $lockSeconds))
        ) {
            $retryAfter = max(
                RequestThrottle::retryAfter('login', $ipKey, $windowSeconds, $lockSeconds),
                $emailKey !== '' ? RequestThrottle::retryAfter('login', $identityKey, $windowSeconds, $lockSeconds) : 0
            );
            Logger::warning('auth', 'Blocked login attempt due to throttle', [
                'email' => $emailKey,
                'ip' => $ip,
                'retry_after' => $retryAfter,
            ]);
            $this->flash('error', 'Prilis mnoho pokusu. Zkuste to znovu pozdeji.');
            $this->redirect('/admin/login');
        }

        $repo = new UserRepository();
        $user = $repo->findByEmail($email);

        if ($user && password_verify($password, $user['password_hash'])) {
            RequestThrottle::clear('login', $ipKey);
            if ($emailKey !== '') {
                RequestThrottle::clear('login', $identityKey);
            }
            Auth::login($user);
            $this->redirect('/admin');
        }

        RequestThrottle::hit('login', $ipKey, $maxAttempts, $windowSeconds, $lockSeconds);
        if ($emailKey !== '') {
            RequestThrottle::hit('login', $identityKey, $maxAttempts, $windowSeconds, $lockSeconds);
        }
        Logger::warning('auth', 'Failed login attempt', [
            'email' => $emailKey,
            'ip' => $ip,
        ]);
        $this->flash('error', 'Nespravny e-mail nebo heslo.');
        $this->redirect('/admin/login');
    }

    public function logout(array $params): void
    {
        Auth::logout();
        $this->redirect('/admin/login');
    }
}
