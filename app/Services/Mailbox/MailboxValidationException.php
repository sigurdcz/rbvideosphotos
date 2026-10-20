<?php
namespace App\Services\Mailbox;

/** User-correctable input problem; the message is safe to show in the UI. */
final class MailboxValidationException extends \RuntimeException {}
