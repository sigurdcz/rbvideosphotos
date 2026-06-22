<?php

namespace App\Services;

use App\Core\Logger;

class GtmAuditService
{
    public function info(string $event, string $message, array $context = []): void
    {
        Logger::infoTo('gtm-audit', $event, $message, $this->redact($context));
    }

    public function warning(string $event, string $message, array $context = []): void
    {
        Logger::warningTo('gtm-audit', $event, $message, $this->redact($context));
    }

    public function error(string $event, string $message, array $context = []): void
    {
        Logger::errorTo('gtm-audit', $event, $message, $this->redact($context));
    }

    private function redact(array $context): array
    {
        $sensitiveKeys = [
            'access_token',
            'refresh_token',
            'client_secret',
            'client_id',
            'authorization',
            'id_token',
            'token',
        ];

        foreach ($context as $key => $value) {
            if (in_array($key, $sensitiveKeys, true)) {
                $context[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $context[$key] = $this->redact($value);
            }
        }

        return $context;
    }
}
