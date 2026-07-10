<?php

namespace App\Services;

class SecurityTxtHttpResponder
{
    public function build(string $uri, string $method, array $payload, array $requestHeaders = []): array
    {
        $method = strtoupper($method);
        if (empty($payload['enabled']) || empty($payload['valid'])) {
            return $this->response(404, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ], $method === 'HEAD' ? '' : "Not Found\n");
        }

        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return $this->response(405, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Allow' => 'GET, HEAD',
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ], "Method Not Allowed\n");
        }

        if ($uri === '/security.txt') {
            return $this->response(308, [
                'Location' => '/.well-known/security.txt',
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ], '');
        }

        $headers = [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=300, must-revalidate',
            'ETag' => (string) ($payload['etag'] ?? ''),
            'Last-Modified' => (string) ($payload['last_modified'] ?? ''),
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($this->notModified($payload, $requestHeaders)) {
            return $this->response(304, $headers, '');
        }

        return $this->response(200, $headers, $method === 'HEAD' ? '' : (string) ($payload['content'] ?? ''));
    }

    private function notModified(array $payload, array $requestHeaders): bool
    {
        $headers = [];
        foreach ($requestHeaders as $name => $value) {
            $headers[strtolower((string) $name)] = trim((string) $value);
        }

        $etag = (string) ($payload['etag'] ?? '');
        if ($etag !== '' && isset($headers['if-none-match'])) {
            foreach (explode(',', $headers['if-none-match']) as $candidate) {
                $candidate = trim($candidate);
                if ($candidate === '*' || $candidate === $etag || preg_replace('/^W\//', '', $candidate) === $etag) {
                    return true;
                }
            }
        }

        $lastModified = strtotime((string) ($payload['last_modified'] ?? ''));
        $ifModifiedSince = isset($headers['if-modified-since']) ? strtotime($headers['if-modified-since']) : false;
        return $lastModified !== false && $ifModifiedSince !== false && $ifModifiedSince >= $lastModified;
    }

    private function response(int $status, array $headers, string $body): array
    {
        return ['status' => $status, 'headers' => $headers, 'body' => $body];
    }
}
