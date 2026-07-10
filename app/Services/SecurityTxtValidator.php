<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

class SecurityTxtValidator
{
    public const MAX_URI_LENGTH = 2048;
    public const MAX_COMMENTS_LENGTH = 4000;

    public function validate(array $input, bool $requireComplete = true, ?DateTimeImmutable $now = null): array
    {
        $now = ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
        $errors = [];
        $warnings = [];
        $normalized = [
            'enabled' => !empty($input['enabled']) ? 1 : 0,
            'contacts' => [],
            'expires_at' => null,
            'expires_db' => null,
            'canonical_url' => '',
            'encryption_url' => '',
            'policy_url' => '',
            'acknowledgments_url' => '',
            'preferred_languages' => '',
            'hiring_url' => '',
            'comments' => '',
        ];

        $contacts = $input['contacts'] ?? [];
        if (!is_array($contacts)) {
            $contacts = [$contacts];
        }
        foreach ($contacts as $index => $contact) {
            $contact = trim((string) $contact);
            if ($contact === '') {
                continue;
            }
            $error = $this->validateContact($contact);
            if ($error !== null) {
                $errors['contacts.' . $index] = $error;
                continue;
            }
            $normalized['contacts'][] = $contact;
        }
        if ($requireComplete && $normalized['contacts'] === []) {
            $errors['contacts'] = 'Alespon jeden platny Contact je povinny.';
        }

        $expiresValues = $input['expires'] ?? $input['expires_at'] ?? '';
        if (!is_array($expiresValues)) {
            $expiresValues = [$expiresValues];
        }
        $expiresValues = array_values(array_filter(array_map(static fn($value): string => trim((string) $value), $expiresValues), static fn(string $value): bool => $value !== ''));
        if (count($expiresValues) > 1) {
            $errors['expires'] = 'Expires smi byt uvedeno prave jednou.';
        } elseif ($expiresValues === []) {
            if ($requireComplete) {
                $errors['expires'] = 'Expires je povinne.';
            }
        } else {
            $expires = $this->parseRfc3339($expiresValues[0]);
            if ($expires === null) {
                $errors['expires'] = 'Expires musi byt platne RFC 3339 datum, napriklad 2027-01-10T23:59:59Z.';
            } elseif ($expires <= $now) {
                $errors['expires'] = 'Expires musi byt v budoucnosti.';
            } elseif ($expires >= $now->modify('+1 year')) {
                $errors['expires'] = 'Expires musi byt mene nez jeden rok v budoucnosti.';
            } else {
                $normalized['expires_at'] = $expires->format('Y-m-d\TH:i:s\Z');
                $normalized['expires_db'] = $expires->format('Y-m-d H:i:s');
                $days = (int) floor(($expires->getTimestamp() - $now->getTimestamp()) / 86400);
                if ($days <= 7) {
                    $warnings['expires'] = 'Security.txt expiruje do 7 dnu.';
                } elseif ($days <= 30) {
                    $warnings['expires'] = 'Security.txt expiruje do 30 dnu.';
                }
            }
        }

        foreach ([
            'canonical_url' => 'Canonical',
            'encryption_url' => 'Encryption',
            'policy_url' => 'Policy',
            'acknowledgments_url' => 'Acknowledgments',
            'hiring_url' => 'Hiring',
        ] as $key => $label) {
            $value = trim((string) ($input[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $error = $this->validateHttpsUrl($value, $label);
            if ($error !== null) {
                $errors[$key] = $error;
                continue;
            }
            $normalized[$key] = $value;
        }

        $languages = $input['preferred_languages'] ?? '';
        if (is_array($languages)) {
            $languages = implode(',', $languages);
        }
        $languages = trim((string) $languages);
        if ($languages !== '') {
            if ($this->containsLineBreak($languages) || strlen($languages) > 255) {
                $errors['preferred_languages'] = 'Preferred-Languages obsahuje nepovolenou delku nebo novy radek.';
            } else {
                $tags = array_values(array_filter(array_map('trim', explode(',', $languages)), static fn(string $tag): bool => $tag !== ''));
                foreach ($tags as $tag) {
                    if (!$this->isLanguageTag($tag)) {
                        $errors['preferred_languages'] = 'Neplatny jazykovy tag: ' . $tag;
                        break;
                    }
                }
                if (!isset($errors['preferred_languages'])) {
                    $normalized['preferred_languages'] = implode(', ', array_values(array_unique(array_map('strtolower', $tags))));
                }
            }
        }

        $comments = (string) ($input['comments'] ?? '');
        if (str_contains($comments, "\0") || strlen($comments) > self::MAX_COMMENTS_LENGTH) {
            $errors['comments'] = 'Komentare jsou prilis dlouhe nebo obsahuji nepovoleny znak.';
        } else {
            $normalized['comments'] = trim(str_replace(["\r\n", "\r"], "\n", $comments));
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'config' => $normalized,
        ];
    }

    public function expirationStatus(?string $expiresAt, ?DateTimeImmutable $now = null): array
    {
        $now = ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
        $expires = $expiresAt !== null ? $this->parseRfc3339($expiresAt) : null;
        if ($expires === null) {
            return ['code' => 'missing', 'label' => 'Expirace chybi', 'days' => null];
        }

        $seconds = $expires->getTimestamp() - $now->getTimestamp();
        $days = (int) floor($seconds / 86400);
        if ($seconds <= 0) {
            return ['code' => 'expired', 'label' => 'Expirovano', 'days' => $days];
        }
        if ($days <= 7) {
            return ['code' => 'critical', 'label' => 'Expiruje do 7 dnu', 'days' => $days];
        }
        if ($days <= 30) {
            return ['code' => 'warning', 'label' => 'Expiruje do 30 dnu', 'days' => $days];
        }

        return ['code' => 'ok', 'label' => 'Expirace je v poradku', 'days' => $days];
    }

    private function validateContact(string $value): ?string
    {
        if ($this->containsLineBreak($value) || strlen($value) > self::MAX_URI_LENGTH) {
            return 'Contact obsahuje nepovolenou delku nebo novy radek.';
        }
        if (str_starts_with(strtolower($value), 'https://')) {
            return filter_var($value, FILTER_VALIDATE_URL) !== false ? null : 'Contact obsahuje neplatnou HTTPS URL.';
        }
        if (str_starts_with(strtolower($value), 'mailto:')) {
            $email = rawurldecode(explode('?', substr($value, 7), 2)[0]);
            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? null : 'Contact mailto obsahuje neplatny e-mail.';
        }
        if (str_starts_with(strtolower($value), 'tel:')) {
            $phone = substr($value, 4);
            return preg_match('/^\+?[0-9().;=\-]{3,64}$/', $phone) === 1 ? null : 'Contact tel obsahuje neplatne telefonni URI.';
        }

        return 'Contact musi zacinat mailto:, tel: nebo https://.';
    }

    private function validateHttpsUrl(string $value, string $label): ?string
    {
        if ($this->containsLineBreak($value) || strlen($value) > self::MAX_URI_LENGTH) {
            return $label . ' obsahuje nepovolenou delku nebo novy radek.';
        }
        if (!str_starts_with(strtolower($value), 'https://') || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return $label . ' musi byt platna HTTPS URL.';
        }
        return null;
    }

    private function parseRfc3339(string $value): ?DateTimeImmutable
    {
        if ($this->containsLineBreak($value) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+\-]\d{2}:\d{2})$/', $value) !== 1) {
            return null;
        }

        $parseValue = str_ends_with($value, 'Z') ? substr($value, 0, -1) . '+00:00' : $value;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $parseValue);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date->setTimezone(new DateTimeZone('UTC'));
    }

    private function isLanguageTag(string $tag): bool
    {
        return preg_match('/^(?:[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*|x(?:-[A-Za-z0-9]{1,8})+)$/', $tag) === 1;
    }

    private function containsLineBreak(string $value): bool
    {
        return str_contains($value, "\r") || str_contains($value, "\n") || str_contains(strtolower($value), '%0d') || str_contains(strtolower($value), '%0a');
    }
}
