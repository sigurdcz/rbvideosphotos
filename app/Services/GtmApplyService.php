<?php

namespace App\Services;

use App\Repositories\GtmContainerVersionRepository;
use App\Repositories\GtmManagedEntityRepository;
use App\Repositories\GtmSyncOperationRepository;
use App\Repositories\GtmSyncRunRepository;

class GtmApplyService
{
    public function __construct(
        private readonly GtmConnectionService $connections = new GtmConnectionService(),
        private readonly GtmApiClient $client = new GtmApiClient(),
        private readonly GtmManagedEntityRepository $entities = new GtmManagedEntityRepository(),
        private readonly GtmSyncRunRepository $runs = new GtmSyncRunRepository(),
        private readonly GtmSyncOperationRepository $operations = new GtmSyncOperationRepository(),
        private readonly GtmContainerVersionRepository $versions = new GtmContainerVersionRepository(),
        private readonly GtmManifestCompiler $compiler = new GtmManifestCompiler(),
        private readonly GtmAuditService $audit = new GtmAuditService(),
    ) {
    }

    public function apply(int $siteId, array $site, array $connection, array $plan, int $userId): array
    {
        if (empty($connection['workspace_path'])) {
            throw new \RuntimeException('Vyber workspace pred aplikaci plánu.');
        }

        $accessToken = $this->connections->validAccessToken($siteId, $connection);
        $workspacePath = (string) $connection['workspace_path'];
        $this->audit->info('gtm.apply.start', 'Applying GTM plan', [
            'site_id' => $siteId,
            'workspace_path' => $workspacePath,
            'operations' => count($plan['operations'] ?? []),
        ]);

        $runId = $this->runs->create([
            'site_id' => $siteId,
            'connection_id' => $connection['id'] ?? null,
            'user_id' => $userId,
            'run_type' => 'apply',
            'status' => 'running',
            'summary' => 'Aplikace GTM plánu',
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        $results = [];
        $order = 0;
        $triggerIds = [];

        foreach ($plan['operations'] as $operation) {
            $order++;
            $entityType = (string)($operation['entity_type'] ?? '');
            $action = (string)($operation['action'] ?? 'noop');
            $managementKey = (string)($operation['management_key'] ?? '');
            $definition = $this->compiler->compile($site, [$this->definitionForOperation($operation)])[0] ?? null;

            if ($definition === null) {
                $results[] = ['action' => 'conflict', 'management_key' => $managementKey, 'message' => 'Chybí definice eventu.'];
                continue;
            }

            try {
                if ($entityType === 'trigger') {
                    $result = $this->applyTrigger($accessToken, $workspacePath, $operation, $definition['trigger']);
                    $triggerIds[$definition['event_key']] = $result['triggerId'] ?? null;
                } elseif ($entityType === 'tag') {
                    $tagPayload = $definition['tag'];
                    if (!empty($triggerIds[$definition['event_key']])) {
                        $tagPayload['firingTriggerId'] = [(string) $triggerIds[$definition['event_key']]];
                    }
                    $result = $this->applyTag($accessToken, $workspacePath, $operation, $tagPayload);
                } else {
                    $result = ['status' => 'skipped', 'message' => 'Nepodporovaný typ entity'];
                }

                $this->persistEntityResult($siteId, $operation, $result ?? [], $entityType);
                $results[] = $result + ['operation_order' => $order, 'management_key' => $managementKey];
                $this->operations->createMany($runId, [[
                    'operation_order' => $order,
                    'entity_type' => $entityType,
                    'action' => $action,
                    'management_key' => $managementKey,
                    'remote_path' => $result['path'] ?? ($operation['remote_path'] ?? null),
                    'status' => 'succeeded',
                    'summary' => $operation['summary'] ?? null,
                    'request_json' => $operation['request_json'] ?? null,
                    'response_json' => $result,
                ]]);
            } catch (\Throwable $e) {
                $this->operations->createMany($runId, [[
                    'operation_order' => $order,
                    'entity_type' => $entityType,
                    'action' => $action,
                    'management_key' => $managementKey,
                    'remote_path' => $operation['remote_path'] ?? null,
                    'status' => 'failed',
                    'summary' => $operation['summary'] ?? null,
                    'request_json' => $operation['request_json'] ?? null,
                    'error_message' => $e->getMessage(),
                ]]);
                $this->runs->update($runId, [
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'finished_at' => date('Y-m-d H:i:s'),
                ]);
                $this->connections->updateFailure($siteId, $e->getMessage());
                throw $e;
            }
        }

        $this->connections->updateSyncStamp($siteId);
        $this->runs->update($runId, [
            'status' => 'succeeded',
            'summary' => 'GTM plan applied',
            'finished_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'run_id' => $runId,
            'results' => $results,
        ];
    }

    private function applyTrigger(string $accessToken, string $workspacePath, array $operation, array $triggerPayload): array
    {
        $existingPath = $operation['remote_path'] ?? null;
        if ($existingPath) {
            return $this->client->updateTrigger($accessToken, $existingPath, $triggerPayload, $operation['fingerprint'] ?? null);
        }

        return $this->client->createTrigger($accessToken, $workspacePath, $triggerPayload);
    }

    private function applyTag(string $accessToken, string $workspacePath, array $operation, array $tagPayload): array
    {
        $existingPath = $operation['remote_path'] ?? null;
        if ($existingPath) {
            return $this->client->updateTag($accessToken, $existingPath, $tagPayload, $operation['fingerprint'] ?? null);
        }

        return $this->client->createTag($accessToken, $workspacePath, $tagPayload);
    }

    private function definitionForOperation(array $operation): array
    {
        return [
            'event_key' => $operation['event_key'] ?? '',
            'label' => $operation['summary'] ?? '',
            'description' => '',
            'category' => '',
            'enabled' => 1,
            'trigger_type' => 'custom_event',
            'tag_type' => 'gaawe',
            'tag_name' => $operation['request_json']['name'] ?? '',
            'trigger_name' => $operation['request_json']['name'] ?? '',
            'tag_parameter_json' => $operation['request_json'] ?? [],
            'trigger_filter_json' => $operation['request_json'] ?? [],
            'notes_json' => [],
        ];
    }

    private function persistEntityResult(int $siteId, array $operation, array $result, string $entityType): void
    {
        if (empty($operation['management_key'])) {
            return;
        }

        $remotePath = (string)($result['path'] ?? $operation['remote_path'] ?? '');
        $remoteId = (string)($result['tagId'] ?? ($result['triggerId'] ?? ($result['variableId'] ?? '')));
        $fingerprint = (string)($result['fingerprint'] ?? '');
        $existing = $this->entities->findBySiteAndKey($siteId, (string) $operation['management_key']);
        $entityId = $existing['id'] ?? null;
        if (!$entityId) {
            $entityId = $this->entities->upsert($siteId, [
                'event_definition_id' => $operation['definition_id'] ?? null,
                'entity_type' => $entityType,
                'management_key' => $operation['management_key'],
                'display_name' => $operation['request_json']['name'] ?? $operation['summary'] ?? $operation['management_key'],
                'ownership' => $operation['ownership'] ?? 'managed',
                'remote_path' => $remotePath !== '' ? $remotePath : null,
                'remote_id' => $remoteId !== '' ? $remoteId : null,
                'fingerprint' => $fingerprint !== '' ? $fingerprint : null,
                'config_hash' => $operation['desired_hash'] ?? hash('sha256', json_encode($operation['request_json'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'),
                'payload_json' => [
                    'request' => $operation['request_json'] ?? [],
                    'response' => $result,
                ],
                'last_status' => 'succeeded',
                'last_seen_at' => date('Y-m-d H:i:s'),
            ]);
            return;
        }

        $this->entities->update((int) $entityId, [
            'event_definition_id' => $operation['definition_id'] ?? null,
            'entity_type' => $entityType,
            'management_key' => $operation['management_key'],
            'display_name' => $operation['request_json']['name'] ?? $operation['summary'] ?? $operation['management_key'],
            'ownership' => $operation['ownership'] ?? 'managed',
            'remote_path' => $remotePath !== '' ? $remotePath : null,
            'remote_id' => $remoteId !== '' ? $remoteId : null,
            'fingerprint' => $fingerprint !== '' ? $fingerprint : null,
            'config_hash' => $operation['desired_hash'] ?? hash('sha256', json_encode($operation['request_json'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'),
            'payload_json' => [
                'request' => $operation['request_json'] ?? [],
                'response' => $result,
            ],
            'last_status' => 'succeeded',
            'last_seen_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
