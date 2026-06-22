<?php

namespace App\Services;

use App\Repositories\GtmConnectionRepository;
use App\Repositories\SiteRepository;
use RuntimeException;

class GtmConnectionService
{
    public function __construct(
        private readonly GtmConnectionRepository $connections = new GtmConnectionRepository(),
        private readonly GtmTokenCipher $cipher = new GtmTokenCipher(),
        private readonly GtmApiClient $client = new GtmApiClient(),
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function apiClient(): GtmApiClient
    {
        return $this->client;
    }

    public function authorizationUrl(string $state): string
    {
        return $this->client->authorizationUrl($state, $this->scopes());
    }

    public function storeTokenResponse(int $siteId, array $tokenResponse): array
    {
        $existing = $this->connections->findBySite($siteId) ?? [];
        $refreshToken = trim((string)($tokenResponse['refresh_token'] ?? ''));
        if ($refreshToken === '' && !empty($existing['refresh_token_encrypted'])) {
            $refreshToken = $this->cipher->decrypt((string) $existing['refresh_token_encrypted']);
        }
        $accessToken = trim((string)($tokenResponse['access_token'] ?? ''));
        if ($accessToken === '') {
            throw new RuntimeException('OAuth token response did not contain an access token.');
        }

        $connectionId = $this->connections->save($siteId, [
            'status' => 'connected',
            'access_token_encrypted' => $this->cipher->encrypt($accessToken),
            'refresh_token_encrypted' => $refreshToken !== '' ? $this->cipher->encrypt($refreshToken) : null,
            'token_scopes' => (string)($tokenResponse['scope'] ?? implode(' ', $this->scopes())),
            'token_expires_at' => $this->tokenExpiresAt($tokenResponse['expires_in'] ?? null),
            'last_verified_at' => date('Y-m-d H:i:s'),
            'last_error' => null,
        ]);

        return $this->loadById($connectionId);
    }

    public function disconnect(int $siteId): void
    {
        $this->connections->setDisconnected($siteId);
    }

    public function loadForSite(int $siteId): ?array
    {
        $connection = $this->connections->findBySite($siteId);
        if (!$connection) {
            return null;
        }

        return $this->hydrate($connection);
    }

    public function requireForSite(int $siteId): array
    {
        $connection = $this->loadForSite($siteId);
        if (!$connection) {
            throw new RuntimeException('GTM connection not found.');
        }

        return $connection;
    }

    public function refreshAccessToken(int $siteId): array
    {
        $connection = $this->requireForSite($siteId);
        $refreshToken = trim((string)($connection['refresh_token'] ?? ''));
        if ($refreshToken === '') {
            throw new RuntimeException('Missing GTM refresh token.');
        }

        $tokenResponse = $this->client->refreshAccessToken($refreshToken);
        return $this->storeTokenResponse($siteId, $tokenResponse);
    }

    public function updateSelection(int $siteId, array $selection): array
    {
        $existing = $this->connections->findBySite($siteId);
        if (!$existing) {
            throw new RuntimeException('GTM connection not found.');
        }

        $this->connections->update((int) $existing['id'], [
            'account_id' => $selection['account_id'] ?? null,
            'account_name' => $selection['account_name'] ?? null,
            'container_id' => $selection['container_id'] ?? null,
            'container_name' => $selection['container_name'] ?? null,
            'workspace_id' => $selection['workspace_id'] ?? null,
            'workspace_name' => $selection['workspace_name'] ?? null,
            'workspace_path' => $selection['workspace_path'] ?? null,
            'workspace_fingerprint' => $selection['workspace_fingerprint'] ?? null,
            'last_verified_at' => date('Y-m-d H:i:s'),
            'status' => 'connected',
            'last_error' => null,
        ]);

        return $this->requireForSite($siteId);
    }

    public function updateInventoryStamp(int $siteId): void
    {
        $connection = $this->connections->findBySite($siteId);
        if (!$connection) {
            return;
        }

        $this->connections->update((int) $connection['id'], [
            'last_inventory_at' => date('Y-m-d H:i:s'),
            'status' => 'connected',
        ]);
    }

    public function updateSyncStamp(int $siteId): void
    {
        $connection = $this->connections->findBySite($siteId);
        if (!$connection) {
            return;
        }

        $this->connections->update((int) $connection['id'], [
            'last_synced_at' => date('Y-m-d H:i:s'),
            'status' => 'connected',
        ]);
    }

    public function updateFailure(int $siteId, string $message): void
    {
        $connection = $this->connections->findBySite($siteId);
        if (!$connection) {
            return;
        }

        $this->connections->update((int) $connection['id'], [
            'status' => 'error',
            'last_error' => $message,
        ]);
    }

    public function site(): ?array
    {
        return (new SiteRepository())->findFirst();
    }

    private function loadById(int $connectionId): array
    {
        $connection = null;
        foreach ($this->connections->all() as $row) {
            if ((int) $row['id'] === $connectionId) {
                $connection = $row;
                break;
            }
        }

        if (!$connection) {
            throw new RuntimeException('Unable to reload GTM connection.');
        }

        return $this->hydrate($connection);
    }

    private function hydrate(array $connection): array
    {
        try {
            $connection['access_token'] = $this->cipher->decrypt($connection['access_token_encrypted'] ?? '');
        } catch (\Throwable $e) {
            $connection['access_token'] = '';
            $connection['token_error'] = $e->getMessage();
        }

        try {
            $connection['refresh_token'] = $this->cipher->decrypt($connection['refresh_token_encrypted'] ?? '');
        } catch (\Throwable $e) {
            $connection['refresh_token'] = '';
            $connection['token_error'] = $connection['token_error'] ?? $e->getMessage();
        }
        $connection['token_scopes_list'] = array_values(array_filter(preg_split('/\s+/', trim((string)($connection['token_scopes'] ?? ''))) ?: []));
        return $connection;
    }

    private function tokenExpiresAt(mixed $expiresIn): ?string
    {
        $seconds = (int) ($expiresIn ?? 0);
        if ($seconds <= 0) {
            return null;
        }

        return date('Y-m-d H:i:s', time() + $seconds);
    }

    private function scopes(): array
    {
        return (array) \App\Core\Config::get('gtm', 'scopes', []);
    }
}
