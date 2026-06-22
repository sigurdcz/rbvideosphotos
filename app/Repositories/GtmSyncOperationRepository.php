<?php

namespace App\Repositories;

class GtmSyncOperationRepository extends GtmRepository
{
    public function createMany(int $syncRunId, array $operations): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO gtm_sync_operations (sync_run_id, operation_order, entity_type, action, management_key, remote_path, status, summary, request_json, response_json, error_message)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($operations as $operation) {
            $stmt->execute([
                $syncRunId,
                (int) ($operation['operation_order'] ?? 0),
                $operation['entity_type'],
                $operation['action'],
                $operation['management_key'],
                $operation['remote_path'] ?? null,
                $operation['status'] ?? 'pending',
                $operation['summary'] ?? null,
                $this->encodeJson($operation['request_json'] ?? null),
                $this->encodeJson($operation['response_json'] ?? null),
                $operation['error_message'] ?? null,
            ]);
        }
    }

    public function allByRun(int $syncRunId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM gtm_sync_operations WHERE sync_run_id = ? ORDER BY operation_order ASC, id ASC');
        $stmt->execute([$syncRunId]);
        $rows = $stmt->fetchAll();

        return array_map(static function (array $row): array {
            $row['request_json'] = json_decode((string)($row['request_json'] ?? ''), true) ?: [];
            $row['response_json'] = json_decode((string)($row['response_json'] ?? ''), true) ?: [];
            return $row;
        }, $rows);
    }
}
