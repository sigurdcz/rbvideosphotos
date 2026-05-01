<?php

namespace App\Core;

abstract class Controller
{
    protected function view(string $template, array $data = []): void
    {
        $data['_user']      = Auth::user();
        $data['_csrfToken'] = Auth::csrfToken();
        View::render($template, $data);
    }

    protected function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    protected function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function requireAuth(): void
    {
        Auth::require();
    }

    protected function csrfCheck(): void
    {
        $token = $_POST['_csrf'] ?? '';
        if (!Auth::csrfVerify($token)) {
            http_response_code(403);
            echo 'CSRF token mismatch.';
            exit;
        }
    }

    protected function input(string $key, mixed $default = ''): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    protected function flash(string $key, string $message): void
    {
        Auth::start();
        $_SESSION['flash'][$key] = $message;
    }

    protected function getFlash(string $key): ?string
    {
        Auth::start();
        $msg = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
}
