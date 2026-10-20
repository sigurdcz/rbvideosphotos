<?php
namespace App\Services\Mailbox;

/** Line/byte oriented connection; swapped for a scripted fake in tests. */
interface MailTransport
{
    /** Next line without the trailing CRLF, or null on EOF/timeout. */
    public function readLine(): ?string;

    public function readBytes(int $length): ?string;

    public function write(string $data): void;

    public function close(): void;
}
