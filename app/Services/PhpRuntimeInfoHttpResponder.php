<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;

final class PhpRuntimeInfoHttpResponder
{
    public const PATH = '/api/runtime-info';

    private const SECURITY_MINIMUMS = [
        '8.2' => '8.2.35',
        '8.3' => '8.3.36',
        '8.4' => '8.4.27',
        '8.5' => '8.5.12',
    ];

    /** @param null|callable(string):bool $extensionLoaded */
    public function __construct(
        private ?string $expectedToken = null,
        private ?string $runtimeVersion = null,
        private ?string $runtimeSapi = null,
        private $extensionLoaded = null,
    ) {}

    public function serve(string $uri): bool
    {
        if ($uri !== self::PATH) {
            return false;
        }

        header_remove('Set-Cookie');
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
        header('Pragma: no-cache');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Referrer-Policy: no-referrer');

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return $this->respond(405, ['status' => 'error', 'error' => 'method_not_allowed'], $method, ['Allow' => 'GET, HEAD']);
        }

        $expected = $this->expectedToken ?? (string) Env::get('MIGRATE_TOKEN', '');
        if ($expected === '') {
            return $this->respond(503, ['status' => 'error', 'error' => 'runtime_info_unavailable'], $method);
        }

        $provided = trim((string) ($_SERVER['HTTP_X_MIGRATE_TOKEN'] ?? ''));
        if ($provided === '') {
            $authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
            if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $match) === 1) {
                $provided = trim($match[1]);
            }
        }
        if ($provided === '' || !hash_equals($expected, $provided)) {
            return $this->respond(404, ['status' => 'error', 'error' => 'not_found'], $method);
        }

        $version = $this->runtimeVersion ?? PHP_VERSION;
        $sapi = $this->runtimeSapi ?? PHP_SAPI;
        $branch = implode('.', array_slice(explode('.', $version), 0, 2));
        $minimum = self::SECURITY_MINIMUMS[$branch] ?? null;
        $meetsMinimum = $minimum !== null ? version_compare($version, $minimum, '>=') : null;
        $hasExtension = $this->extensionLoaded ?? static fn(string $name): bool => extension_loaded($name);

        return $this->respond(200, [
            'status' => 'ok',
            'checked_at' => gmdate('c'),
            'php' => [
                'version' => $version,
                'version_id' => $this->versionId($version),
                'sapi' => $sapi,
                'security_advisory' => 'CVE-2026-109618,CVE-2026-109619',
                'security_minimum' => $minimum,
                'meets_security_minimum' => $meetsMinimum,
                'security_status' => $minimum === null ? 'unknown_branch' : ($meetsMinimum ? 'patched' : 'outdated'),
            ],
            'required_extensions' => [
                'pdo_mysql' => (bool) $hasExtension('pdo_mysql'),
                'gd' => (bool) $hasExtension('gd'),
                'mbstring' => (bool) $hasExtension('mbstring'),
                'openssl' => (bool) $hasExtension('openssl'),
                'json' => (bool) $hasExtension('json'),
            ],
        ], $method);
    }

    /** @param array<string,mixed> $payload @param array<string,string> $extraHeaders */
    private function respond(int $status, array $payload, string $method, array $extraHeaders = []): bool
    {
        $json = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        http_response_code($status);
        foreach ($extraHeaders as $name => $value) {
            header($name . ': ' . $value);
        }
        header('Content-Length: ' . strlen($json));
        if ($method !== 'HEAD') {
            echo $json;
        }
        return true;
    }

    private function versionId(string $version): int
    {
        $parts = array_map('intval', array_pad(explode('.', $version), 3, '0'));
        return ($parts[0] * 10000) + ($parts[1] * 100) + $parts[2];
    }
}
