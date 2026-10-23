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

    /**
     * Persist a contact submission rejected by the static spam filter without
     * invoking SMTP for the untrusted message. An optional fixed notification
     * contains only the archive ID and bounded reason codes.
     */
    public function archiveSpam(
        string $recipient,
        string $subject,
        string $body,
        array $reasons,
        string $cc = '',
        bool $notifyRecipients = false
    ): array
    {
        $reasonCodes = array_values(array_filter(array_map(
            static fn($reason): string => preg_replace('/[^a-z0-9-]/', '', strtolower((string) $reason)) ?? '',
            $reasons
        )));
        $errorCode = mb_substr('static_spam:' . implode(',', $reasonCodes), 0, 64);
        $cc = trim($cc);
        if ($cc !== '') {
            try {
                $cc = EmailRecipientList::normalize($cc, $recipient);
            } catch (\InvalidArgumentException $e) {
                // Preserve the configured value for diagnosis. No recipient is
                // contacted because this path never invokes SMTP.
            }
        }
        $stmt = $this->db->prepare(
            'INSERT INTO mail_archive (kind, recipient, cc_recipient, subject, body, status, error_code, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            'contact', $recipient, $cc !== '' ? $cc : null, $subject, $body,
            'spam', $errorCode, gmdate('Y-m-d H:i:s'),
        ]);
        $id = (int) $this->db->lastInsertId();
        $notificationStatus = $notifyRecipients
            ? $this->notifySpamRecipients($id, $recipient, $cc, $reasonCodes)
            : 'disabled';
        return ['id' => $id, 'status' => 'spam', 'notification_status' => $notificationStatus];
    }

    /** @param list<string> $reasonCodes */
    private function notifySpamRecipients(int $archiveId, string $recipient, string $cc, array $reasonCodes): string
    {
        try {
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $recipient)) {
                throw new SmtpTransportException('smtp_invalid_message');
            }
            $normalizedCc = $cc === '' ? '' : EmailRecipientList::normalize($cc, $recipient);
            $headers = self::headers((string) Env::get('APP_URL', ''));
            $subject = 'Antispam zachytil zprávu z webu';
            $body = "Statický antispam zachytil a bezpečně uložil zprávu.\n\n"
                . 'ID záznamu: ' . $archiveId . "\n"
                . 'Důvody: ' . ($reasonCodes === [] ? 'neuvedeny' : implode(', ', $reasonCodes)) . "\n\n"
                . "Původní obsah najdete po přihlášení v administraci v sekci E-maily a zprávy.";
            $sent = $this->sender
                ? ($this->sender)($recipient, $subject, $body, $headers, $normalizedCc)
                : (new SmtpMailer())->send($recipient, $subject, $body, $normalizedCc);
            if (!$sent) {
                Logger::error('contact_spam', 'Spam notification transport rejected', ['archive_id' => $archiveId]);
                return 'failed';
            }
            Logger::info('contact_spam', 'Spam notification accepted by mail transport', ['archive_id' => $archiveId]);
            return 'accepted';
        } catch (\Throwable $e) {
            $code = $e instanceof SmtpTransportException ? $e->errorCode : 'transport_exception';
            Logger::error('contact_spam', 'Spam notification failed', ['archive_id' => $archiveId, 'code' => $code]);
            return 'failed';
        }
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
