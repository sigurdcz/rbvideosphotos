<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Public result states accepted from the contact-form redirect query string.
 *
 * Any other URL value is deliberately discarded before it reaches a view.
 */
enum ContactSubmissionStatus: string
{
    case Sent = '1';
    case Saved = 'saved';
    case Error = 'error';
    case Invalid = 'invalid';
    case InvalidPackage = 'invalid-package';
    case Csrf = 'csrf';
    case RateLimit = 'rate-limit';

    public static function normalize(?string $value): ?string
    {
        return self::tryFrom((string) $value)?->value;
    }
}
