<?php

namespace App\Repositories;

class GtmSyncRunRepository extends GtmRepository
{
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO gtm_sync_runs (site_id, connection_id, user_id, run_type, status, summary, request_id, sync_status_json, merge_conflicts_json, error_code, error_message, started_at, finished_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['site_id'],
            $data['connection_id'] ?? null,
            $data['user_id'] ?? null,
            $data['run_type'],
            $data['status'] ?? 'pending',
            $data['summary'] ?? null,
            $data['request_id'] ?? null,
            $this->encodeJson($data['sync_status_json'] ?? null),
            $this->encodeJson($data['merge_conflicts_json'] ?? null),
            $data['error_code'] ?? null,
            $data['error_message'] ?? null,
            $data['started_at'] ?? null,
            $data['finished_at'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [
            'status',
            'summary',
            'request_id',
            'sync_status_json',
            'merge_conflicts_json',
            'error_code',
            'error_message',
            'started_at',
            'finished_at',
        ];

        $sets = [];
        $values = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $sets[] = "`{$field}` = ?";
            $values[] = in_array($field, ['sync_status_json', 'merge_conflicts_json'], true)
                ? $this->encodeJson($data[$field])
                : $data[$field];
        }

        if ($sets === []) {
            return;
        }

        $values[] = $id;
        $this->db->prepare('UPDATE gtm_sync_runs SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM gtm_sync_runs WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $this->normalizeRow($row) : null;
    }

    public function allBySite(int $siteId, int $limit = 20): array
    {
        $stmt = $this->db->prepare('SELECT * FROM gtm_sync_runs WHERE site_id = ? ORDER BY created_at DESC LIMIT ' . (int) $limit);
        $stmt->execute([$siteId]);
        return array_map([$this, 'normalizeRow'], $stmt->fetchAll());
    }

    private function normalizeRow(array $row): array
    {
        $row['sync_status_json'] = $this->decodeJson($row['sync_status_json'] ?? null);
        $row['merge_conflicts_json'] = $this->decodeJson($row['merge_conflicts_json'] ?? null);
        return $row;
    }
}
