<?php
namespace App\Services\Mailbox;

/**
 * Attachment files live outside the web root (storage/mailbox) under random keys; the original file
 * name is only metadata in the database and never part of a path.
 */
final class AttachmentStore
{
    /** Extensions that must never be uploaded from the CMS (executable / script / server-side). */
    private const BLOCKED_EXTENSIONS = [
        'exe', 'bat', 'cmd', 'com', 'scr', 'pif', 'msi', 'msp', 'dll', 'js', 'jse', 'vbs', 'vbe', 'wsf', 'wsh', 'ps1', 'psm1',
        'jar', 'lnk', 'hta', 'reg', 'cpl', 'php', 'phtml', 'phar', 'htaccess', 'sh',
    ];
    /** Types a browser may render inline; everything else is always served as a download. */
    private const INLINE_SAFE_MIME = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf', 'text/plain'];

    public function __construct(private ?string $root = null)
    {
        $this->root = $root ?? MailboxConfig::storagePath();
    }

    public function save(string $content): string
    {
        $key = bin2hex(random_bytes(20));
        $path = $this->path($key);
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('mailbox_storage');
        }
        $this->protectRoot();
        if (file_put_contents($path, $content, LOCK_EX) === false) {
            throw new \RuntimeException('mailbox_storage');
        }
        return $key;
    }

    public function path(string $key): string
    {
        if (!preg_match('/^[a-f0-9]{40}$/', $key)) {
            throw new \InvalidArgumentException('mailbox_storage_key');
        }
        return $this->root . '/' . substr($key, 0, 2) . '/' . $key;
    }

    public function delete(string $key): void
    {
        $path = $this->path($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function isBlockedFilename(string $filename): bool
    {
        $name = strtolower(trim($filename));
        foreach (explode('.', $name) as $i => $part) {
            // "invoice.pdf.exe" and "x.php.jpg" are both dangerous: check every extension segment.
            if ($i > 0 && in_array($part, self::BLOCKED_EXTENSIONS, true)) {
                return true;
            }
        }
        return in_array(ltrim($name, '.'), self::BLOCKED_EXTENSIONS, true);
    }

    public static function safeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[\x00-\x1F\x7F"<>|:*?\/\\\\]+/u', '_', $filename) ?? '';
        $filename = trim($filename, " .\t");
        return mb_substr($filename !== '' ? $filename : 'priloha', 0, 200, 'UTF-8');
    }

    public static function isInlineSafe(string $mime): bool
    {
        return in_array(strtolower($mime), self::INLINE_SAFE_MIME, true);
    }

    private function protectRoot(): void
    {
        $guard = $this->root . '/.htaccess';
        if (!is_file($guard)) {
            @file_put_contents($guard, "Require all denied\nDeny from all\n");
        }
    }
}
