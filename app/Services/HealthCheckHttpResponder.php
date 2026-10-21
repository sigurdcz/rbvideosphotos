<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

final class HealthCheckHttpResponder
{
    public function __construct(private ?\Closure $databaseProbe = null) {}

    public function serve(string $uri): bool
    {
        if ($uri !== HealthCheckService::PATH) {
            return false;
        }

        header_remove('Set-Cookie');
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
        header('X-Robots-Tag: noindex, nofollow, noarchive');

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            http_response_code(405);
            header('Allow: GET, HEAD');
            return true;
        }

        $database = 'error';
        try {
            if ($this->databaseProbe !== null) {
                $database = ($this->databaseProbe)() === true ? 'ok' : 'error';
            } else {
                $connection = Database::createConnection();
                $database = (int) $connection->query('SELECT 1')->fetchColumn() === 1 ? 'ok' : 'error';
            }
        } catch (\Throwable $e) {
            Logger::error('health-check', 'Database health probe failed', ['type' => $e::class]);
        }

        $healthy = $database === 'ok';
        $payload = [
            'status' => $healthy ? 'ok' : 'error',
            'checked_at' => gmdate('c'),
            'checks' => ['application' => 'ok', 'database' => $database],
        ];
        $json = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        http_response_code($healthy ? 200 : 503);
        header('Content-Length: ' . strlen($json));
        if ($method !== 'HEAD') {
            echo $json;
        }
        return true;
    }
}
