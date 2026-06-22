<?php

namespace App\Services;

use App\Core\Config;
use RuntimeException;

class GtmApiClient
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $authUri;
    private string $tokenUri;
    private string $apiBaseUri;
    private int $connectTimeout;
    private int $requestTimeout;

    public function __construct()
    {
        $this->clientId = trim((string) Config::get('gtm', 'client_id', ''));
        $this->clientSecret = trim((string) Config::get('gtm', 'client_secret', ''));
        $this->redirectUri = trim((string) Config::get('gtm', 'redirect_uri', ''));
        $this->authUri = trim((string) Config::get('gtm', 'auth_uri', 'https://accounts.google.com/o/oauth2/v2/auth'));
        $this->tokenUri = trim((string) Config::get('gtm', 'token_uri', 'https://oauth2.googleapis.com/token'));
        $this->apiBaseUri = rtrim((string) Config::get('gtm', 'api_base_uri', 'https://tagmanager.googleapis.com/tagmanager/v2'), '/');
        $this->connectTimeout = max(1, (int) Config::get('gtm', 'connect_timeout', 10));
        $this->requestTimeout = max(1, (int) Config::get('gtm', 'request_timeout', 20));
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '' && $this->redirectUri !== '';
    }

    public function authorizationUrl(string $state, array $scopes): string
    {
        $query = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
        ];

        return $this->authUri . '?' . http_build_query($query);
    }

    public function exchangeCodeForToken(string $code): array
    {
        return $this->postForm($this->tokenUri, [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
        ], false);
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        return $this->postForm($this->tokenUri, [
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
        ], false);
    }

    public function listAccounts(string $accessToken): array
    {
        return $this->request('GET', 'accounts', accessToken: $accessToken)['account'] ?? [];
    }

    public function listContainers(string $accessToken, string $accountPath): array
    {
        return $this->request('GET', $accountPath . '/containers', accessToken: $accessToken)['container'] ?? [];
    }

    public function listWorkspaces(string $accessToken, string $containerPath): array
    {
        return $this->request('GET', $containerPath . '/workspaces', accessToken: $accessToken)['workspace'] ?? [];
    }

    public function listTags(string $accessToken, string $workspacePath): array
    {
        return $this->request('GET', $workspacePath . '/tags', accessToken: $accessToken)['tag'] ?? [];
    }

    public function listTriggers(string $accessToken, string $workspacePath): array
    {
        return $this->request('GET', $workspacePath . '/triggers', accessToken: $accessToken)['trigger'] ?? [];
    }

    public function listVariables(string $accessToken, string $workspacePath): array
    {
        return $this->request('GET', $workspacePath . '/variables', accessToken: $accessToken)['variable'] ?? [];
    }

    public function listVersions(string $accessToken, string $containerPath): array
    {
        return $this->request('GET', $containerPath . '/versions', accessToken: $accessToken)['containerVersion'] ?? [];
    }

    public function getWorkspace(string $accessToken, string $workspacePath): array
    {
        return $this->request('GET', $workspacePath, accessToken: $accessToken);
    }

    public function getWorkspaceStatus(string $accessToken, string $workspacePath): array
    {
        return $this->request('POST', $workspacePath . ':sync', accessToken: $accessToken, body: [], retryable: true);
    }

    public function quickPreview(string $accessToken, string $workspacePath): array
    {
        return $this->request('POST', $workspacePath . ':quick_preview', accessToken: $accessToken, body: [], retryable: true);
    }

    public function syncWorkspace(string $accessToken, string $workspacePath): array
    {
        return $this->request('POST', $workspacePath . ':sync', accessToken: $accessToken, body: [], retryable: true);
    }

    public function createVersion(string $accessToken, string $workspacePath, array $options): array
    {
        return $this->request('POST', $workspacePath . ':create_version', accessToken: $accessToken, body: $options, retryable: false);
    }

    public function publishVersion(string $accessToken, string $versionPath): array
    {
        return $this->request('POST', $versionPath . ':publish', accessToken: $accessToken, body: [], retryable: false);
    }

    public function createTag(string $accessToken, string $workspacePath, array $tag): array
    {
        return $this->request('POST', $workspacePath . '/tags', accessToken: $accessToken, body: $tag, retryable: false);
    }

    public function updateTag(string $accessToken, string $tagPath, array $tag, ?string $fingerprint): array
    {
        return $this->request('PUT', $tagPath, accessToken: $accessToken, body: $tag, query: $fingerprint ? ['fingerprint' => $fingerprint] : [], retryable: false);
    }

    public function deleteTag(string $accessToken, string $tagPath, ?string $fingerprint = null): array
    {
        return $this->request('DELETE', $tagPath, accessToken: $accessToken, query: $fingerprint ? ['fingerprint' => $fingerprint] : [], retryable: false);
    }

    public function createTrigger(string $accessToken, string $workspacePath, array $trigger): array
    {
        return $this->request('POST', $workspacePath . '/triggers', accessToken: $accessToken, body: $trigger, retryable: false);
    }

    public function updateTrigger(string $accessToken, string $triggerPath, array $trigger, ?string $fingerprint): array
    {
        return $this->request('PUT', $triggerPath, accessToken: $accessToken, body: $trigger, query: $fingerprint ? ['fingerprint' => $fingerprint] : [], retryable: false);
    }

    public function deleteTrigger(string $accessToken, string $triggerPath, ?string $fingerprint = null): array
    {
        return $this->request('DELETE', $triggerPath, accessToken: $accessToken, query: $fingerprint ? ['fingerprint' => $fingerprint] : [], retryable: false);
    }

    public function createVariable(string $accessToken, string $workspacePath, array $variable): array
    {
        return $this->request('POST', $workspacePath . '/variables', accessToken: $accessToken, body: $variable, retryable: false);
    }

    public function updateVariable(string $accessToken, string $variablePath, array $variable, ?string $fingerprint): array
    {
        return $this->request('PUT', $variablePath, accessToken: $accessToken, body: $variable, query: $fingerprint ? ['fingerprint' => $fingerprint] : [], retryable: false);
    }

    public function deleteVariable(string $accessToken, string $variablePath, ?string $fingerprint = null): array
    {
        return $this->request('DELETE', $variablePath, accessToken: $accessToken, query: $fingerprint ? ['fingerprint' => $fingerprint] : [], retryable: false);
    }

    private function postForm(string $url, array $fields, bool $retryable): array
    {
        $body = http_build_query($fields);
        return $this->rawRequest('POST', $url, ['Content-Type: application/x-www-form-urlencoded'], $body, $retryable);
    }

    private function request(string $method, string $path, array $query = [], array $body = [], ?string $accessToken = null, bool $retryable = false): array
    {
        $url = $this->buildUrl($path, $query);
        $headers = ['Accept: application/json'];
        if ($accessToken !== null && $accessToken !== '') {
            $headers[] = 'Authorization: Bearer ' . $accessToken;
        }

        $payload = $body !== [] ? json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        if ($payload !== null && $payload === false) {
            throw new RuntimeException('Unable to encode GTM request body.');
        }
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        return $this->rawRequest($method, $url, $headers, $payload, $retryable);
    }

    private function rawRequest(string $method, string $url, array $headers, ?string $body, bool $retryable): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL extension is required for GTM API requests.');
        }

        $attempt = 0;
        $maxAttempts = $retryable ? 3 : 1;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            $attempt++;
            $ch = curl_init($url);
            if ($ch === false) {
                throw new RuntimeException('Unable to initialize cURL for GTM API.');
            }

            $responseHeaders = [];
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
                CURLOPT_TIMEOUT => $this->requestTimeout,
                CURLOPT_HEADER => true,
                CURLOPT_FAILONERROR => false,
            ]);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($curl, string $header) use (&$responseHeaders): int {
                $len = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return $len;
            });

            $raw = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            if ($errno !== 0) {
                $lastException = new RuntimeException('GTM API network error: ' . $error, $errno);
                if ($attempt < $maxAttempts) {
                    usleep((int) ((int) Config::get('gtm', 'retry_delay_ms', 500) * 1000));
                    continue;
                }
                throw $lastException;
            }

            $headerSize = 0;
            $bodyText = '';
            if (is_string($raw)) {
                $headerSize = 0;
                $headerEnd = strpos($raw, "\r\n\r\n");
                if ($headerEnd !== false) {
                    $headerSize = $headerEnd + 4;
                }
                $bodyText = substr($raw, $headerSize) ?: '';
            }

            $decoded = $bodyText !== '' ? json_decode($bodyText, true) : [];
            if ($bodyText !== '' && $decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                $decoded = ['raw' => $bodyText];
            }

            if ($status >= 200 && $status < 300) {
                return is_array($decoded) ? $decoded : [];
            }

            $message = $this->extractErrorMessage($decoded, $bodyText, $status);
            $details = [
                'url' => $this->redactUrl($url),
                'method' => $method,
                'status' => $status,
                'response' => $this->redactPayload(is_array($decoded) ? $decoded : ['raw' => $bodyText]),
            ];

            if ($retryable && ($status === 429 || $status >= 500) && $attempt < $maxAttempts) {
                usleep((int) ((int) Config::get('gtm', 'retry_delay_ms', 500) * 1000) * $attempt);
                continue;
            }

            throw new GtmApiException($message, $status, $details);
        }

        throw $lastException ?? new RuntimeException('GTM API request failed.');
    }

    private function buildUrl(string $path, array $query = []): string
    {
        $normalized = ltrim($path, '/');
        $url = $this->apiBaseUri . '/' . $normalized;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }
        return $url;
    }

    private function extractErrorMessage(array $decoded, string $bodyText, int $status): string
    {
        if (isset($decoded['error']['message'])) {
            return (string) $decoded['error']['message'];
        }
        if (isset($decoded['error']['errors'][0]['message'])) {
            return (string) $decoded['error']['errors'][0]['message'];
        }
        return 'GTM API request failed with HTTP ' . $status . ($bodyText !== '' ? ': ' . $bodyText : '.');
    }

    private function redactPayload(array $payload): array
    {
        $redactKeys = ['access_token', 'refresh_token', 'client_secret', 'id_token'];
        foreach ($payload as $key => $value) {
            if (in_array($key, $redactKeys, true)) {
                $payload[$key] = '[redacted]';
                continue;
            }
            if (is_array($value)) {
                $payload[$key] = $this->redactPayload($value);
            }
        }
        return $payload;
    }

    private function redactUrl(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return $url;
        }
        unset($parts['query']);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $path = $parts['path'] ?? '';
        return $scheme . '://' . $host . $path;
    }
}
