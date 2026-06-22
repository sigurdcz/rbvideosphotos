<?php

namespace App\Services;

use App\Repositories\GtmConnectionRepository;
use App\Repositories\GtmContainerVersionRepository;
use App\Repositories\GtmEventDefinitionRepository;
use App\Repositories\GtmManagedEntityRepository;
use App\Repositories\GtmSyncRunRepository;
use RuntimeException;

class GtmInventoryService
{
    public function __construct(
        private readonly GtmConnectionService $connections = new GtmConnectionService(),
        private readonly GtmApiClient $client = new GtmApiClient(),
        private readonly GtmEventDefinitionRepository $definitions = new GtmEventDefinitionRepository(),
        private readonly GtmManagedEntityRepository $entities = new GtmManagedEntityRepository(),
        private readonly GtmSyncRunRepository $runs = new GtmSyncRunRepository(),
        private readonly GtmContainerVersionRepository $versions = new GtmContainerVersionRepository(),
    ) {
    }

    public function load(int $siteId): array
    {
        $connection = $this->connections->requireForSite($siteId);
        $accessToken = $this->ensureAccessToken($siteId, $connection);
        $accountId = trim((string)($connection['account_id'] ?? ''));
        $containerId = trim((string)($connection['container_id'] ?? ''));
        $workspaceId = trim((string)($connection['workspace_id'] ?? ''));

        if ($accountId === '') {
            $accounts = $this->client->listAccounts($accessToken);
            return [
                'connection' => $connection,
                'accounts' => $accounts,
                'containers' => [],
                'workspaces' => [],
                'tags' => [],
                'triggers' => [],
                'variables' => [],
                'versions' => [],
                'workspace_status' => [],
            ];
        }

        $accountPath = 'accounts/' . $accountId;
        $containerPath = $containerId !== '' ? $accountPath . '/containers/' . $containerId : '';
        $workspacePath = $connection['workspace_path'] ?: ($containerPath !== '' && $workspaceId !== '' ? $containerPath . '/workspaces/' . $workspaceId : '');

        $accounts = $this->client->listAccounts($accessToken);
        $containers = $this->client->listContainers($accessToken, $accountPath);
        $workspaces = $containerPath !== '' ? $this->client->listWorkspaces($accessToken, $containerPath) : [];
        $tags = $workspacePath !== '' ? $this->client->listTags($accessToken, $workspacePath) : [];
        $triggers = $workspacePath !== '' ? $this->client->listTriggers($accessToken, $workspacePath) : [];
        $variables = $workspacePath !== '' ? $this->client->listVariables($accessToken, $workspacePath) : [];
        $versions = $containerPath !== '' ? $this->client->listVersions($accessToken, $containerPath) : [];
        $workspaceStatus = $workspacePath !== '' ? $this->client->getWorkspaceStatus($accessToken, $workspacePath) : [];

        $this->connections->updateInventoryStamp($siteId);
        $inventory = [
            'connection' => $connection,
            'accounts' => $accounts,
            'containers' => $containers,
            'workspaces' => $workspaces,
            'tags' => $tags,
            'triggers' => $triggers,
            'variables' => $variables,
            'versions' => $versions,
            'workspace_status' => $workspaceStatus,
        ];

        $this->connections->save($siteId, [
            'inventory_snapshot_json' => $this->encodeSnapshot($inventory),
            'inventory_snapshot_at' => date('Y-m-d H:i:s'),
            'status' => 'connected',
        ]);

        return $inventory;
    }

    public function loadCached(int $siteId): array
    {
        $connection = $this->connections->requireForSite($siteId);
        $snapshot = trim((string)($connection['inventory_snapshot_json'] ?? ''));
        if ($snapshot === '') {
            throw new RuntimeException('Cached GTM inventory not found. Click "Nacist inventar" first.');
        }

        $decoded = json_decode($snapshot, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Cached GTM inventory is invalid. Refresh inventory again.');
        }

        $decoded['connection'] = $connection;
        return $decoded;
    }

    public function connectionEntities(int $siteId): array
    {
        return $this->entities->allBySite($siteId);
    }

    public function definitions(int $siteId): array
    {
        return $this->definitions->allBySite($siteId);
    }

    public function runs(int $siteId, int $limit = 10): array
    {
        return $this->runs->allBySite($siteId, $limit);
    }

    public function versions(int $siteId, int $limit = 10): array
    {
        return $this->versions->allBySite($siteId, $limit);
    }

    public function refreshDefinitionsFromCatalog(int $siteId, array $catalog): void
    {
        $this->definitions->ensureCatalog($siteId, $catalog);
    }

    public function latestRun(int $siteId): ?array
    {
        $runs = $this->runs->allBySite($siteId, 1);
        return $runs[0] ?? null;
    }

    private function ensureAccessToken(int $siteId, array $connection): string
    {
        $tokenError = trim((string)($connection['token_error'] ?? ''));
        if ($tokenError !== '') {
            throw new RuntimeException('GTM token decryption failed: ' . $tokenError);
        }

        $token = trim((string)($connection['access_token'] ?? ''));
        $expiresAt = trim((string)($connection['token_expires_at'] ?? ''));
        $refreshToken = trim((string)($connection['refresh_token'] ?? ''));

        if ($token === '') {
            if ($refreshToken === '') {
                throw new RuntimeException('Missing GTM access token and refresh token.');
            }

            $connection = $this->connections->refreshAccessToken($siteId);
            $token = trim((string)($connection['access_token'] ?? ''));
        } elseif ($expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) <= time() + 60) {
            $connection = $this->connections->refreshAccessToken($siteId);
            $token = trim((string)($connection['access_token'] ?? ''));
        }

        if ($token === '') {
            throw new RuntimeException('Missing GTM access token.');
        }

        return $token;
    }

    private function encodeSnapshot(array $inventory): string
    {
        $snapshot = $inventory;
        unset($snapshot['connection']);

        $json = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Unable to encode GTM inventory snapshot.');
        }

        return $json;
    }
}
