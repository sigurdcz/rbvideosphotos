<?php
namespace App\Services\Mailbox;

final class ImapException extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, ?\Throwable $previous = null)
    {
        // The message carries only a safe code: server replies can echo credentials or mail content.
        parent::__construct($errorCode, 0, $previous);
    }
}
