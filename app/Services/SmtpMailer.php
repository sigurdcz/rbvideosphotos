<?php
namespace App\Services;

use App\Core\Env;
use PHPMailer\PHPMailer\PHPMailer;

final class SmtpMailer
{
    public function __construct(private ?PHPMailer $mailer = null) {}

    public function send(string $recipient, string $subject, string $body, string $cc = ''): bool
    {
        try {
            $ccRecipients = EmailRecipientList::parse($cc);
        } catch (\InvalidArgumentException $e) {
            throw new SmtpTransportException('smtp_invalid_message');
        }
        $host = trim((string) Env::get('SMTP_HOST', ''));
        $username = trim((string) Env::get('SMTP_USERNAME', ''));
        $password = (string) Env::get('SMTP_PASSWORD', '');
        $encryption = strtolower(trim((string) Env::get('SMTP_ENCRYPTION', 'ssl')));
        $port = filter_var(Env::get('SMTP_PORT', $encryption === 'tls' ? '587' : '465'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        $from = trim((string) Env::get('MAIL_FROM', $username));
        if (!preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/iD', $host)
            || !filter_var($username, FILTER_VALIDATE_EMAIL) || $password === '' || str_contains($password, "\0")
            || !in_array($encryption, ['ssl', 'tls'], true) || $port === false
            || !filter_var($from, FILTER_VALIDATE_EMAIL) || strcasecmp($from, $username) !== 0) {
            throw new SmtpTransportException('smtp_configuration');
        }
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $recipient)
            || preg_match('/[\r\n]/', $subject)) {
            throw new SmtpTransportException('smtp_invalid_message');
        }
        $mail = $this->mailer ?? new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = (int) $port;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->SMTPSecure = $encryption === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPAutoTLS = false; // Encryption is mandatory and explicitly selected above.
            $mail->SMTPDebug = 0;
            $mail->Timeout = 20;
            $mail->getSMTPInstance()->Timelimit = 30;
            $mail->SMTPKeepAlive = false;
            $mail->SMTPOptions = ['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]];
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
            $mail->clearAllRecipients();
            $mail->clearReplyTos();
            $mail->setFrom($from, 'RB Videos Photos');
            $mail->addAddress($recipient); // Recipient is supplied by CMS, never replaced with the sender.
            foreach ($ccRecipients as $ccRecipient) {
                if (strcasecmp($ccRecipient, $recipient) !== 0) { $mail->addCC($ccRecipient); }
            }
            $mail->Subject = $subject;
            $mail->isHTML(false);
            $mail->Body = $body;
            if (!$mail->send()) { throw new SmtpTransportException('smtp_transport'); }
            return true;
        } catch (\Throwable $e) {
            // Discard PHPMailer error text: it may include recipient or message details.
            throw new SmtpTransportException('smtp_transport');
        } finally {
            $mail->smtpClose();
        }
    }
}
