<?php

namespace App\Services;

use App\Repositories\GtmEventDefinitionRepository;
use App\Repositories\GtmManagedEntityRepository;

class GtmPlanService
{
    public function __construct(
        private readonly GtmEventDefinitionRepository $definitions = new GtmEventDefinitionRepository(),
        private readonly GtmManagedEntityRepository $entities = new GtmManagedEntityRepository(),
        private readonly GtmManifestCompiler $compiler = new GtmManifestCompiler(),
    ) {
    }

    public function build(array $site, array $connection, array $inventory, array $definitions): array
    {
        $manifest = $this->compiler->compile($site, $definitions);
        $operations = [];
        $conflicts = [];
        $order = 0;

        foreach ($manifest as $item) {
            $eventKey = (string) $item['event_key'];
            $triggerPlan = $this->planEntity($site['id'], $item['management_keys']['trigger'], 'trigger', $item['trigger'], $inventory['triggers'] ?? [], $definitions, $eventKey);
            $tagPlan = $this->planEntity($site['id'], $item['management_keys']['tag'], 'tag', $item['tag'], $inventory['tags'] ?? [], $definitions, $eventKey);

            foreach ([$triggerPlan, $tagPlan] as $plan) {
                $order++;
                $plan['operation_order'] = $order;
                $operations[] = $plan;
                if (($plan['action'] ?? '') === 'conflict') {
                    $conflicts[] = $plan;
                }
            }
        }

        return [
            'manifest' => $manifest,
            'operations' => $operations,
            'conflicts' => $conflicts,
            'summary' => [
                'total' => count($operations),
                'create' => count(array_filter($operations, static fn(array $op): bool => ($op['action'] ?? '') === 'create')),
                'update' => count(array_filter($operations, static fn(array $op): bool => ($op['action'] ?? '') === 'update')),
                'noop' => count(array_filter($operations, static fn(array $op): bool => ($op['action'] ?? '') === 'noop')),
                'conflict' => count($conflicts),
            ],
        ];
    }

    private function planEntity(int $siteId, string $managementKey, string $entityType, array $desired, array $remoteEntities, array $definitions, string $eventKey): array
    {
        $existing = $this->entities->findBySiteAndKey($siteId, $managementKey);
        $remote = $this->findRemote($remoteEntities, $managementKey, (string) ($desired['name'] ?? ''));
        $desiredHash = $this->compiler->hashDefinition($desired + ['entity_type' => $entityType]);

        if (isset($remote['__conflict'])) {
            return [
                'entity_type' => $entityType,
                'action' => 'conflict',
                'management_key' => $managementKey,
                'remote_path' => null,
                'status' => 'conflict',
                'summary' => 'Nalezena nejednoznacna duplicita pro ' . $eventKey,
                'request_json' => $desired,
                'response_json' => $remote['matches'] ?? [],
                'desired_hash' => $desiredHash,
                'event_key' => $eventKey,
                'definition_id' => $this->findDefinitionId($definitions, $eventKey),
                'ownership' => $existing['ownership'] ?? 'managed',
            ];
        }

        if ($remote === null) {
            return [
                'entity_type' => $entityType,
                'action' => 'create',
                'management_key' => $managementKey,
                'remote_path' => null,
                'status' => 'pending',
                'summary' => 'Vytvorit ' . $entityType . ' pro ' . $eventKey,
                'request_json' => $desired,
                'desired_hash' => $desiredHash,
                'event_key' => $eventKey,
                'definition_id' => $this->findDefinitionId($definitions, $eventKey),
            ];
        }

        $remoteHash = $this->compiler->hashDefinition($this->normalizeRemote($remote));
        $action = $remoteHash === $desiredHash ? 'noop' : 'update';

        if ($existing && (($existing['ownership'] ?? 'managed') === 'external')) {
            $action = 'conflict';
        }

        return [
            'entity_type' => $entityType,
            'action' => $action,
            'management_key' => $managementKey,
            'remote_path' => $remote['path'] ?? null,
            'remote_id' => $remote['tagId'] ?? ($remote['triggerId'] ?? ($remote['variableId'] ?? null)),
            'fingerprint' => $remote['fingerprint'] ?? null,
            'status' => $action === 'noop' ? 'succeeded' : 'pending',
            'summary' => ucfirst($action) . ' ' . $entityType . ' for ' . $eventKey,
            'request_json' => $desired,
            'response_json' => $remote,
            'desired_hash' => $desiredHash,
            'event_key' => $eventKey,
            'definition_id' => $this->findDefinitionId($definitions, $eventKey),
            'ownership' => $existing['ownership'] ?? 'managed',
        ];
    }

    private function findRemote(array $remoteEntities, string $managementKey, string $name): ?array
    {
        $matches = [];
        foreach ($remoteEntities as $entity) {
            $notes = (string)($entity['notes'] ?? '');
            if ($notes !== '' && str_contains($notes, 'management_key=' . $managementKey)) {
                $matches[] = $entity;
                continue;
            }
            if ($name !== '' && isset($entity['name']) && trim((string)$entity['name']) === $name) {
                $matches[] = $entity;
            }
        }

        if (count($matches) > 1) {
            return ['__conflict' => true, 'matches' => $matches];
        }

        return $matches[0] ?? null;
    }

    private function normalizeRemote(array $remote): array
    {
        unset($remote['path'], $remote['accountId'], $remote['containerId'], $remote['workspaceId'], $remote['tagId'], $remote['triggerId'], $remote['variableId'], $remote['fingerprint'], $remote['tagManagerUrl']);
        return $remote;
    }

    private function findDefinitionId(array $definitions, string $eventKey): ?int
    {
        foreach ($definitions as $definition) {
            if (($definition['event_key'] ?? '') === $eventKey) {
                return (int) $definition['id'];
            }
        }

        return null;
    }
}
