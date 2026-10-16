<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/vendor/autoload.php';
use App\Core\Env;
use App\Services\PasswordResetService;
Env::load(dirname(__DIR__) . '/.env');
$db = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', Env::get('DB_HOST'), Env::get('DB_PORT', '3306')), Env::get('DB_USER'), Env::get('DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$name = 'rbvp_recovery_test_' . bin2hex(random_bytes(6));
$db->exec('CREATE DATABASE `' . $name . '`');
$checks = 0;
function check(bool $ok, string $name): void { global $checks; if (!$ok) { throw new RuntimeException($name); } $checks++; }
function rejected(Closure $fn): void { try { $fn(); } catch (RuntimeException $e) { return; } throw new Exception('Expected rejection'); }
try {
    $db->exec('USE `' . $name . '`');
    $db->exec("CREATE TABLE users (id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT, email VARCHAR(255) UNIQUE, password_hash VARCHAR(255)) ENGINE=InnoDB");
    $db->exec(file_get_contents(dirname(__DIR__) . '/database/migrations/103_add_password_resets.sql'));
    $oldHash = password_hash('old-password-123', PASSWORD_BCRYPT);
    $db->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)')->execute(['admin@example.test', $oldHash]);
    $_ENV['APP_URL'] = 'https://example.test';
    $_ENV['MAIL_FROM'] = 'noreply@example.test';
    $messages = [];
    $service = new PasswordResetService($db, function (...$args) use (&$messages): bool { $messages[] = $args; return true; });
    $service->request('unknown@example.test');
    check(count($messages) === 0, 'Unknown account sends no mail');
    $service->request('admin@example.test');
    check($messages[0][0] === 'admin@example.test', 'Mail goes to account');
    preg_match('/token=([a-f0-9]{64})/', $messages[0][2], $m); $token = $m[1];
    check($db->query('SELECT token_hash FROM password_resets')->fetchColumn() === hash('sha256', $token), 'Only hash stored');
    check(!str_contains($messages[0][2], $oldHash), 'No password hash mailed');
    $service->request('admin@example.test');
    rejected(fn() => $service->reset($token, 'new-password-123'));
    preg_match('/token=([a-f0-9]{64})/', $messages[1][2], $m); $token = $m[1];
    rejected(fn() => $service->reset($token, 'short'));
    rejected(fn() => $service->reset($token, str_repeat('x', 73)));
    rejected(fn() => $service->reset($token, "long-password\0bad"));
    check((int) $db->query('SELECT COUNT(*) FROM password_resets')->fetchColumn() === 1, 'Bad password does not consume token');
    $service->reset($token, 'new-password-123');
    check(password_verify('new-password-123', $db->query('SELECT password_hash FROM users')->fetchColumn()), 'New hash verifies');
    check(!password_verify('old-password-123', $db->query('SELECT password_hash FROM users')->fetchColumn()), 'Old password rejected');
    rejected(fn() => $service->reset($token, 'another-password-123'));
    $service->request('admin@example.test');
    preg_match('/token=([a-f0-9]{64})/', $messages[2][2], $m);
    $db->exec("UPDATE password_resets SET expires_at = '2000-01-01 00:00:00'");
    rejected(fn() => $service->reset($m[1], 'another-password-123'));
    rejected(fn() => $service->reset('invalid', 'another-password-123'));
    $broken = new PasswordResetService($db, fn() => false);
    rejected(fn() => $broken->request('admin@example.test'));
    check((int) $db->query('SELECT COUNT(*) FROM password_resets')->fetchColumn() === 0, 'Mail failure removes unusable token');
    $_ENV['APP_URL'] = 'http://untrusted.test';
    rejected(fn() => $service->request('admin@example.test'));
    $_ENV['APP_URL'] = 'https://example.test';
    // Verify existing authenticated sessions become invalid after a password change.
    $property = new ReflectionProperty(App\Core\Database::class, 'instance'); $property->setValue(null, $db);
    App\Core\Auth::login(['id' => 1, 'email' => 'admin@example.test', 'role' => 'admin', 'password_hash' => $oldHash]);
    check(!App\Core\Auth::check(), 'Session with old hash revoked');
    App\Core\Auth::login(['id' => 1, 'email' => 'admin@example.test', 'role' => 'admin', 'password_hash' => $db->query('SELECT password_hash FROM users')->fetchColumn()]);
    (new ReflectionProperty(App\Core\Auth::class, 'passwordChecked'))->setValue(null, false);
    check(App\Core\Auth::check(), 'Current password session stays valid');
    echo "PASS: {$checks} assertions plus expired, reused, superseded, malformed, invalid-password and transport rejection cases.\n";
} finally {
    $db->exec('DROP DATABASE `' . $name . '`');
}
