<?php
namespace App\Services;

final class SmtpTransportException extends \RuntimeException
{
    public function __construct(public string $errorCode)
    {
        // Safe for logs: never retain raw SMTP errors, passwords or message bodies.
        parent::__construct($errorCode);
    }
}
