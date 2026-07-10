<?php

namespace App\Services;

use InvalidArgumentException;

class SecurityTxtGenerator
{
    public function generate(array $config): string
    {
        $lines = ['# Security contact information'];

        $comments = trim((string) ($config['comments'] ?? ''));
        if ($comments !== '') {
            foreach (preg_split('/\r\n|\r|\n/', $comments) ?: [] as $comment) {
                $comment = trim((string) $comment);
                if ($comment === '') {
                    continue;
                }
                $lines[] = '# ' . ltrim($comment, "# \t");
            }
        }

        $lines[] = '';
        foreach (($config['contacts'] ?? []) as $contact) {
            $lines[] = 'Contact: ' . $this->singleLine((string) $contact);
        }

        $expires = trim((string) ($config['expires_at'] ?? ''));
        if ($expires !== '') {
            $lines[] = 'Expires: ' . $this->singleLine($expires);
        }

        $languages = trim((string) ($config['preferred_languages'] ?? ''));
        if ($languages !== '') {
            $lines[] = 'Preferred-Languages: ' . $this->singleLine($languages);
        }

        foreach ([
            'canonical_url' => 'Canonical',
            'policy_url' => 'Policy',
            'encryption_url' => 'Encryption',
            'acknowledgments_url' => 'Acknowledgments',
            'hiring_url' => 'Hiring',
        ] as $key => $field) {
            $value = trim((string) ($config[$key] ?? ''));
            if ($value !== '') {
                $lines[] = $field . ': ' . $this->singleLine($value);
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function singleLine(string $value): string
    {
        if (str_contains($value, "\r") || str_contains($value, "\n") || str_contains($value, "\0")) {
            throw new InvalidArgumentException('security.txt values must stay on one line.');
        }
        return $value;
    }
}
