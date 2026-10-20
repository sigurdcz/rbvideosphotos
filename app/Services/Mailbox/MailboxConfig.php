<?php
namespace App\Services\Mailbox;

use App\Core\Env;

/** Environment-driven settings of the shared mailbox. Credentials fall back to the SMTP ones (same Active24 mailbox). */
final class MailboxConfig
{
    public static function address(): string
    {
        return strtolower(trim((string) Env::get('MAIL_FROM', (string) Env::get('SMTP_USERNAME', ''))));
    }

    public static function fromName(): string
    {
        return trim((string) Env::get('MAILBOX_FROM_NAME', 'RB Videos Photos')) ?: 'RB Videos Photos';
    }

    public static function imapHost(): string
    {
        $host = trim((string) Env::get('IMAP_HOST', ''));
        if ($host === '') {
            $smtp = trim((string) Env::get('SMTP_HOST', ''));
            $host = str_starts_with($smtp, 'smtp.') ? 'imap.' . substr($smtp, 5) : $smtp;
        }
        return $host;
    }

    public static function imapPort(): int
    {
        $port = filter_var(Env::get('IMAP_PORT', '993'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        return $port === false ? 993 : (int) $port;
    }

    public static function imapUsername(): string
    {
        return trim((string) Env::get('IMAP_USERNAME', (string) Env::get('SMTP_USERNAME', '')));
    }

    private static function imapPassword(): string
    {
        return (string) Env::get('IMAP_PASSWORD', (string) Env::get('SMTP_PASSWORD', ''));
    }

    public static function isConfigured(): bool
    {
        return self::imapHost() !== '' && self::imapUsername() !== '' && self::imapPassword() !== '' && self::address() !== '';
    }

    public static function inboxFolder(): string
    {
        return trim((string) Env::get('MAILBOX_INBOX_FOLDER', 'INBOX')) ?: 'INBOX';
    }

    /** Empty = auto-detect through the \Sent special-use flag. */
    public static function sentFolder(): string
    {
        return trim((string) Env::get('MAILBOX_SENT_FOLDER', ''));
    }

    public static function initialImportDays(): int
    {
        return self::intSetting('MAILBOX_INITIAL_DAYS', 90, 1, 3650);
    }

    public static function syncBatch(): int
    {
        return self::intSetting('MAILBOX_SYNC_BATCH', 150, 1, 2000);
    }

    /** 0 disables the on-demand refresh (cron only). */
    public static function lazySyncSeconds(): int
    {
        return self::intSetting('MAILBOX_LAZY_SYNC_SECONDS', 120, 0, 86400);
    }

    /**
     * Largest message the sync downloads. Parsing needs roughly 8x the raw size in memory, so the cap follows
     * memory_limit; bigger messages become a placeholder that points to the webmail.
     */
    public static function maxFetchBytes(): int
    {
        $override = filter_var(Env::get('MAILBOX_MAX_MESSAGE_MB', ''), FILTER_VALIDATE_INT);
        if ($override !== false && $override > 0) {
            return min($override, 200) * 1048576;
        }
        $raw = trim((string) ini_get('memory_limit'));
        if ($raw === '' || $raw === '-1') {
            return 20 * 1048576;
        }
        $bytes = (int) $raw;
        $unit = strtolower(substr($raw, -1));
        $bytes *= $unit === 'g' ? 1073741824 : ($unit === 'm' ? 1048576 : ($unit === 'k' ? 1024 : 1));
        return (int) max(2 * 1048576, min(20 * 1048576, intdiv($bytes, 8)));
    }

    public static function maxAttachmentBytes(): int
    {
        return self::intSetting('MAILBOX_MAX_ATTACHMENT_MB', 10, 1, 100) * 1048576;
    }

    public static function maxTotalAttachmentBytes(): int
    {
        return self::intSetting('MAILBOX_MAX_TOTAL_MB', 20, 1, 200) * 1048576;
    }

    public static function storagePath(): string
    {
        return dirname(__DIR__, 3) . '/storage/mailbox';
    }

    public static function connectImap(): ImapClient
    {
        if (!self::isConfigured()) {
            throw new ImapException('imap_configuration');
        }
        $client = new ImapClient(new SocketMailTransport(self::imapHost(), self::imapPort(), true));
        try {
            $client->connect(self::imapUsername(), self::imapPassword());
        } catch (\Throwable $e) {
            $client->close();
            throw $e;
        }
        return $client;
    }

    private static function intSetting(string $name, int $default, int $min, int $max): int
    {
        $value = filter_var(Env::get($name, (string) $default), FILTER_VALIDATE_INT);
        return $value === false ? $default : max($min, min($max, (int) $value));
    }
}
