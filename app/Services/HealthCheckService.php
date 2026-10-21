<?php

namespace App\Services;

final class HealthCheckService
{
    public const PATH = '/api/health';
    public const TIMEOUT_SECONDS = 15;
    public const STALE_AFTER_MINUTES = 20;
    public const RETENTION_MONTHS = 14;

    /** @return array{status:int|null,body:string,duration_ms:int,error:?string} */
    public static function request(string $url): array
    {
        $started = hrtime(true);
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::TIMEOUT_SECONDS,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\nUser-Agent: SEO-CMS-Health-Cron/1.0\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $duration = (int) round((hrtime(true) - $started) / 1_000_000);
        $headers = $http_response_header ?? [];
        $status = null;
        foreach ($headers as $header) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~i', (string) $header, $match) === 1) {
                $status = (int) $match[1];
            }
        }

        return [
            'status' => $status,
            'body' => is_string($body) ? $body : '',
            'duration_ms' => max(0, $duration),
            'error' => $body === false ? 'network_error' : null,
        ];
    }

    /**
     * @param array{status:int|null,body:string,duration_ms:int,error:?string} $response
     * @return array<string,mixed>
     */
    public static function recordFromResponse(array $response, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $decoded = json_decode((string) $response['body'], true);
        $validJson = is_array($decoded) && isset($decoded['status']) && isset($decoded['checks']['database']);
        $httpStatus = is_int($response['status']) ? $response['status'] : null;
        $databaseStatus = $validJson && in_array($decoded['checks']['database'], ['ok', 'error'], true)
            ? (string) $decoded['checks']['database']
            : 'unknown';
        $healthy = $response['error'] === null
            && $httpStatus === 200
            && $validJson
            && $decoded['status'] === 'ok'
            && $databaseStatus === 'ok';

        $errorCode = null;
        if (!$healthy) {
            if ($response['error'] !== null) {
                $errorCode = 'network_error';
            } elseif ($httpStatus !== 200) {
                $errorCode = 'http_' . ($httpStatus ?? 'missing');
            } elseif (!$validJson) {
                $errorCode = 'invalid_json';
            } else {
                $errorCode = 'health_unhealthy';
            }
        }

        return [
            'check_uuid' => self::uuid(),
            'checked_at' => $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'http_status' => $httpStatus,
            'response_time_ms' => max(0, (int) $response['duration_ms']),
            'database_status' => $databaseStatus,
            'error_code' => $errorCode,
            'source' => 'cron',
        ];
    }

    public static function targetUrl(?string $configured, ?string $appUrl): string
    {
        $base = trim((string) ($configured ?: $appUrl));
        if ($base === '' || filter_var($base, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('Set HEALTH_CHECK_URL or a valid APP_URL in .env.');
        }
        $parts = parse_url($base);
        if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Health check URL must use HTTP or HTTPS.');
        }

        if ($configured) {
            return $base;
        }
        return rtrim($base, '/') . self::PATH;
    }

    public static function retentionCutoff(?\DateTimeImmutable $now = null): string
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return $now->modify('first day of this month')->modify('-' . self::RETENTION_MONTHS . ' months')->format('Y-m-d H:i:s');
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
