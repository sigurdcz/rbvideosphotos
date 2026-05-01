<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Repositories\UserRepository;

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

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $repo = new UserRepository();
        $user = $repo->findByEmail($email);

        if ($user && password_verify($password, $user['password_hash'])) {
            Auth::login($user);
            $this->redirect('/admin');
        }

        $this->flash('error', 'Nesprávný e-mail nebo heslo.');
        $this->redirect('/admin/login');
    }

    public function logout(array $params): void
    {
        Auth::logout();
        $this->redirect('/admin/login');
    }
}
