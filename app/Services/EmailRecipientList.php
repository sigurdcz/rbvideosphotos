<?php

namespace App\Services;

use InvalidArgumentException;

final class EmailRecipientList
{
    public const MAX_STORAGE_LENGTH = 60000;

    /** @return list<string> */
    public static function parse(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }
        if (strlen($value) > self::MAX_STORAGE_LENGTH) {
            throw new InvalidArgumentException('Seznam e-mailů pro kopii je příliš dlouhý.');
        }

        $parts = preg_split('/[\s,]+/u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            throw new InvalidArgumentException('Seznam e-mailů pro kopii nelze zpracovat.');
        }

        $result = [];
        $seen = [];
        foreach ($parts as $part) {
            $email = trim($part);
            if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email)) {
                throw new InvalidArgumentException('Seznam obsahuje neplatnou e-mailovou adresu.');
            }
            $key = strtolower($email);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $result[] = $email;
            }
        }

        return $result;
    }

    public static function normalize(string $value, string $primaryRecipient = ''): string
    {
        $primaryKey = strtolower(trim($primaryRecipient));
        $recipients = array_values(array_filter(
            self::parse($value),
            static fn(string $email): bool => $primaryKey === '' || strtolower($email) !== $primaryKey
        ));

        return implode(', ', $recipients);
    }
}
