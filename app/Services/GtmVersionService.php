<?php

namespace App\Services;

use App\Repositories\GtmContainerVersionRepository;
use App\Repositories\GtmSyncRunRepository;

class GtmVersionService
{
    public function __construct(
        private readonly GtmConnectionService $connections = new GtmConnectionService(),
        private readonly GtmApiClient $client = new GtmApiClient(),
        private readonly GtmContainerVersionRepository $versions = new GtmContainerVersionRepository(),
        private readonly GtmSyncRunRepository $runs = new GtmSyncRunRepository(),
        private readonly GtmAuditService $audit = new GtmAuditService(),
    ) {
    }

    public function createVersion(int $siteId, array $connection, int $userId, string $name, string $notes): array
    {
        if (empty($connection['workspace_path'])) {
            throw new \RuntimeException('Vyber workspace pred vytvorenim verze.');
        }

        $workspacePath = (string) $connection['workspace_path'];
        $accessToken = $this->connections->validAccessToken($siteId, $connection);
        $runId = $this->runs->create([
            'site_id' => $siteId,
            'connection_id' => $connection['id'] ?? null,
            'user_id' => $userId,
            'run_type' => 'version',
            'status' => 'running',
            'summary' => 'Vytvoreni GTM verze',
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        $response = $this->client->createVersion($accessToken, $workspacePath, [
            'name' => $name,
            'notes' => $notes,
        ]);

        $version = $response['containerVersion'] ?? [];
        $this->versions->create([
            'site_id' => $siteId,
            'connection_id' => $connection['id'] ?? null,
            'sync_run_id' => $runId,
            'container_version_id' => (string)($version['containerVersionId'] ?? $version['versionId'] ?? $name),
            'version_name' => $name,
            'version_path' => $version['path'] ?? null,
            'fingerprint' => $version['fingerprint'] ?? null,
            'published' => 0,
            'compiler_error' => $response['compilerError'] ?? null,
            'new_workspace_path' => $response['newWorkspacePath'] ?? null,
            'note' => $notes,
        ]);

        $this->runs->update($runId, [
            'status' => 'succeeded',
            'summary' => 'GTM verze vytvorena',
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
        $this->connections->updateSyncStamp($siteId);
        $this->audit->info('gtm.version.create', 'GTM version created', [
            'site_id' => $siteId,
            'version_name' => $name,
            'version_path' => $version['path'] ?? null,
        ]);

        return ['run_id' => $runId, 'response' => $response];
    }

    public function publishVersion(int $siteId, array $connection, int $userId, string $versionPath): array
    {
        $accessToken = $this->connections->validAccessToken($siteId, $connection);
        $runId = $this->runs->create([
            'site_id' => $siteId,
            'connection_id' => $connection['id'] ?? null,
            'user_id' => $userId,
            'run_type' => 'publish',
            'status' => 'running',
            'summary' => 'Publikace GTM verze',
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        $response = $this->client->publishVersion($accessToken, $versionPath);
        $version = $response['containerVersion'] ?? [];

        $this->versions->create([
            'site_id' => $siteId,
            'connection_id' => $connection['id'] ?? null,
            'sync_run_id' => $runId,
            'container_version_id' => (string)($version['containerVersionId'] ?? $version['versionId'] ?? $versionPath),
            'version_name' => (string)($version['name'] ?? $versionPath),
            'version_path' => $version['path'] ?? $versionPath,
            'fingerprint' => $version['fingerprint'] ?? null,
            'published' => 1,
            'compiler_error' => $response['compilerError'] ?? null,
            'note' => 'publish',
        ]);

        $this->runs->update($runId, [
            'status' => 'succeeded',
            'summary' => 'GTM verze publikovana',
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
        $this->connections->updateSyncStamp($siteId);
        $this->audit->info('gtm.version.publish', 'GTM version published', [
            'site_id' => $siteId,
            'version_path' => $versionPath,
        ]);

        return ['run_id' => $runId, 'response' => $response];
    }

}
