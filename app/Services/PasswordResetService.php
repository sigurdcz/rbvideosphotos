<?php
namespace App\Services;

use App\Core\Env;
use PDO;
use RuntimeException;

final class PasswordResetService
{
    public function __construct(private PDO $db, private ?\Closure $sender = null) {}

    public function request(string $email): void
    {
        // Never derive recovery links from the untrusted Host header.
        $base = rtrim((string) Env::get('APP_URL', ''), '/');
        $host = parse_url($base, PHP_URL_HOST);
        if (!filter_var($base, FILTER_VALIDATE_URL) || parse_url($base, PHP_URL_SCHEME) !== 'https'
            || !is_string($host) || parse_url($base, PHP_URL_USER) !== null || parse_url($base, PHP_URL_QUERY) !== null
            || parse_url($base, PHP_URL_FRAGMENT) !== null) {
            throw new RuntimeException('Obnova hesla vyžaduje správné HTTPS APP_URL v .env.');
        }
        $from = (string) Env::get('MAIL_FROM', (string) Env::get('SMTP_USERNAME', ''));
        if (!filter_var($from, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $from)) {
            throw new RuntimeException('Není správně nastaven odesílatel MAIL_FROM.');
        }
        $stmt = $this->db->prepare('SELECT id, email FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) { return; } // Same confirmation for unknown logins.
        $recipientQuery = $this->db->query('SELECT c.form_email FROM sites s LEFT JOIN contact_info c ON c.site_id = s.id ORDER BY s.id ASC LIMIT 1');
        $recipient = trim((string) $recipientQuery->fetchColumn());
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $recipient)) {
            throw new RuntimeException('Obnova není dostupná: správce musí v Nastavení uložit platný e-mail pro příjem formulářů a obnovu přístupu.');
        }
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $stmt = $this->db->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at)');
        $stmt->execute([$user['id'], $hash, gmdate('Y-m-d H:i:s', time() + 1800)]);
        $url = $base . '/admin/reset-password?token=' . $token;
        $body = "Žádost pro účet: " . $user['email'] . "\n\nPro nastavení nového hesla otevřete tento odkaz (platnost 30 minut):\n\n" . $url
            . "\n\nPokud jste o změnu nežádali, tento e-mail ignorujte.\n";
        $headers = 'From: ' . $from . "\r\nContent-Type: text/plain; charset=UTF-8";
        try {
            $result = (new MailArchiveService($this->db, $this->sender))->send('password_reset', $recipient, 'Obnova hesla - CMS', $body, $headers);
            if ($result['status'] !== 'accepted') { throw new RuntimeException('Mail transport acceptance not confirmed'); }
        } catch (\Throwable $e) {
            $this->db->prepare('DELETE FROM password_resets WHERE user_id = ? AND token_hash = ?')->execute([$user['id'], $hash]);
            throw new RuntimeException('E-mail se nepodařilo předat k odeslání. Kontaktujte správce hostingu.', 0, $e);
        }
    }

    public function reset(string $token, string $password): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new RuntimeException('Odkaz je neplatný nebo již vypršel.');
        }
        if (strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new RuntimeException('Heslo musí mít 12 až 72 bajtů (u diakritiky může být znaků méně).');
        }
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $this->db->beginTransaction();
        try {
            // The lock makes simultaneous submissions single use as well.
            $stmt = $this->db->prepare('SELECT user_id FROM password_resets WHERE token_hash = ? AND expires_at > ? FOR UPDATE');
            $stmt->execute([hash('sha256', $token), gmdate('Y-m-d H:i:s')]);
            $id = $stmt->fetchColumn();
            if ($id === false) { throw new RuntimeException('Odkaz je neplatný nebo již vypršel.'); }
            $this->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$passwordHash, $id]);
            $this->db->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$id]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $e;
        }
    }
}
