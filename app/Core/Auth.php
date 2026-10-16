<?php

namespace App\Core;

class Auth
{
    private static bool $passwordChecked = false;
    private const IDLE_TIMEOUT_SECONDS = 2 * 60 * 60;
    private const ABSOLUTE_TIMEOUT_SECONDS = 12 * 60 * 60;
    private const ROTATE_INTERVAL_SECONDS = 30 * 60;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $name = Config::get('app', 'session_name', 'seo_cms_session');
            session_name($name);
            $appUrl = (string) Env::get('APP_URL', '');
            $secure = self::shouldUseSecureCookies($appUrl);
            ini_set('session.use_strict_mode', '1');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }

        self::enforceSessionTimeouts();
        if (!self::$passwordChecked && !empty($_SESSION['user_id'])) {
            self::$passwordChecked = true;
            $stmt = Database::getInstance()->prepare('SELECT password_hash FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $hash = $stmt->fetchColumn();
            if (!$hash || !hash_equals(hash('sha256', $hash), (string) ($_SESSION['password_fingerprint'] ?? ''))) {
                $_SESSION = [];
                session_regenerate_id(true);
            }
        }
    }

    public static function login(array $user): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['password_fingerprint'] = hash('sha256', $user['password_hash']);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['session_started_at'] = time();
        $_SESSION['last_activity_at'] = time();
        $_SESSION['last_regenerated_at'] = time();
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
    }

    public static function check(): bool
    {
        self::start();
        return !empty($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        return [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['user_role'],
        ];
    }

    public static function require(): void
    {
        if (!self::check()) {
            header('Location: /admin/login');
            exit;
        }
    }

    public static function csrfToken(): string
    {
        self::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function csrfVerify(string $token): bool
    {
        self::start();
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    private static function enforceSessionTimeouts(): void
    {
        $now = time();
        $startedAt = (int) ($_SESSION['session_started_at'] ?? 0);
        $lastActivityAt = (int) ($_SESSION['last_activity_at'] ?? 0);
        $lastRegeneratedAt = (int) ($_SESSION['last_regenerated_at'] ?? 0);

        if (!empty($_SESSION['user_id'])) {
            $idleExpired = $lastActivityAt > 0 && ($now - $lastActivityAt) > self::IDLE_TIMEOUT_SECONDS;
            $absoluteExpired = $startedAt > 0 && ($now - $startedAt) > self::ABSOLUTE_TIMEOUT_SECONDS;

            if ($idleExpired || $absoluteExpired) {
                $_SESSION = [];
                session_destroy();
                return;
            }

            if ($lastRegeneratedAt <= 0 || ($now - $lastRegeneratedAt) > self::ROTATE_INTERVAL_SECONDS) {
                session_regenerate_id(true);
                $_SESSION['last_regenerated_at'] = $now;
            }
        }

        if ($startedAt <= 0) {
            $_SESSION['session_started_at'] = $now;
        }
        $_SESSION['last_activity_at'] = $now;
        $_SESSION['last_regenerated_at'] = $lastRegeneratedAt > 0 ? $lastRegeneratedAt : $now;
    }

    private static function shouldUseSecureCookies(string $appUrl): bool
    {
        if (str_starts_with(strtolower($appUrl), 'https://')) {
            return true;
        }

        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}
