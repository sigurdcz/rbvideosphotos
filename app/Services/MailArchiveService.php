<?php
namespace App\Services;

use App\Core\Env;
use App\Core\Logger;
use PDO;
use RuntimeException;

final class MailArchiveService
{
    public function __construct(private PDO $db, private ?\Closure $sender = null) {}

    /** Contact bodies are durable; password reset secrets must never be archived. */
    public function send(string $kind, string $recipient, string $subject, string $body, string $headers = '', string $cc = ''): array
    {
        if (!in_array($kind, ['contact', 'password_reset'], true)) { throw new RuntimeException('Unknown mail kind'); }
        $cc = trim($cc);
        if ($kind !== 'contact' && $cc !== '') { throw new RuntimeException('Password reset copies are not allowed'); }
        $ccError = false;
        if ($cc !== '') {
            try {
                $cc = EmailRecipientList::normalize($cc, $recipient);
            } catch (\InvalidArgumentException $e) {
                $ccError = true;
            }
        }
        // If storage fails, no mail is sent and no success is reported.
        $stmt = $this->db->prepare('INSERT INTO mail_archive (kind, recipient, cc_recipient, subject, body, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$kind, $recipient, $cc !== '' ? $cc : null, $subject, $kind === 'contact' ? $body : null, gmdate('Y-m-d H:i:s')]);
        $id = (int) $this->db->lastInsertId();
        $status = 'failed';
        $error = null;
        try {
            if ($headers === '') { $headers = self::headers((string) Env::get('APP_URL', '')); }
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $recipient)) {
                $error = 'invalid_recipient';
            } elseif (preg_match('/[\r\n]/', $subject)) {
                $error = 'invalid_subject';
            } elseif ($ccError) {
                $error = 'invalid_cc_recipient';
            } else {
                // SMTP acceptance cannot prove mailbox delivery.
                $sent = $this->sender ? ($this->sender)($recipient, $subject, $body, $headers, $cc)
                    : (new SmtpMailer())->send($recipient, $subject, $body, $cc);
                $status = $sent ? 'accepted' : 'failed';
                $error = $sent ? null : 'transport_rejected';
            }
        } catch (\Throwable $e) {
            // Exceptions may contain credentials or message bodies; persist only a safe code.
            $error = $e instanceof SmtpTransportException ? $e->errorCode : 'transport_exception';
        }
        try {
            $this->db->prepare('UPDATE mail_archive SET status = ?, error_code = ?, attempted_at = ? WHERE id = ?')
                ->execute([$status, $error, gmdate('Y-m-d H:i:s'), $id]);
        } catch (\Throwable $e) {
            // Content is already durable; a crash/update failure leaves stored = unconfirmed.
            Logger::error('mail_archive', 'Mail attempt status could not be recorded', ['id' => $id]);
            return ['id' => $id, 'status' => 'unconfirmed'];
        }
        if ($status === 'failed') {
            Logger::error('mail_archive', 'Mail transport failed; archive retained', ['id' => $id, 'code' => $error]);
        }
        return ['id' => $id, 'status' => $status];
    }

    public static function headers(string $appUrl): string
    {
        $host = parse_url($appUrl, PHP_URL_HOST);
        $from = (string) Env::get('MAIL_FROM', (string) Env::get('SMTP_USERNAME', ''));
        if (!is_string($host) || !filter_var($from, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $from)) {
            throw new SmtpTransportException('smtp_configuration');
        }
        return 'From: ' . $from . "\r\nContent-Type: text/plain; charset=UTF-8";
    }
}
