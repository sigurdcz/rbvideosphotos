<?php

namespace App\Repositories;

class GtmManagedEntityRepository extends GtmRepository
{
    public function allBySite(int $siteId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM gtm_managed_entities WHERE site_id = ? ORDER BY entity_type ASC, display_name ASC');
        $stmt->execute([$siteId]);
        return $this->normalizeRows($stmt->fetchAll());
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM gtm_managed_entities WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $this->normalizeRow($row) : null;
    }

    public function findBySiteAndKey(int $siteId, string $managementKey): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM gtm_managed_entities WHERE site_id = ? AND management_key = ? LIMIT 1');
        $stmt->execute([$siteId, $managementKey]);
        $row = $stmt->fetch();
        return $row ? $this->normalizeRow($row) : null;
    }

    public function findByRemotePath(int $siteId, string $remotePath): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM gtm_managed_entities WHERE site_id = ? AND remote_path = ? LIMIT 1');
        $stmt->execute([$siteId, $remotePath]);
        $row = $stmt->fetch();
        return $row ? $this->normalizeRow($row) : null;
    }

    public function upsert(int $siteId, array $data): int
    {
        $existing = $this->findBySiteAndKey($siteId, (string) $data['management_key']);
        if ($existing) {
            $this->update((int) $existing['id'], $data);
            return (int) $existing['id'];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO gtm_managed_entities (site_id, event_definition_id, entity_type, management_key, display_name, ownership, remote_path, remote_id, fingerprint, config_hash, payload_json, last_status, last_error, last_seen_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $siteId,
            $data['event_definition_id'] ?? null,
            $data['entity_type'],
            $data['management_key'],
            $data['display_name'],
            $data['ownership'] ?? 'managed',
            $data['remote_path'] ?? null,
            $data['remote_id'] ?? null,
            $data['fingerprint'] ?? null,
            $data['config_hash'],
            $this->encodeJson($data['payload_json'] ?? null),
            $data['last_status'] ?? 'new',
            $data['last_error'] ?? null,
            $data['last_seen_at'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [
            'event_definition_id',
            'entity_type',
            'management_key',
            'display_name',
            'ownership',
            'remote_path',
            'remote_id',
            'fingerprint',
            'config_hash',
            'payload_json',
            'last_status',
            'last_error',
            'last_seen_at',
        ];

        $sets = [];
        $values = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $sets[] = "`{$field}` = ?";
            $values[] = $field === 'payload_json' ? $this->encodeJson($data[$field]) : $data[$field];
        }

        if ($sets === []) {
            return;
        }

        $values[] = $id;
        $this->db->prepare('UPDATE gtm_managed_entities SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
    }

    public function deleteById(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM gtm_managed_entities WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function normalizeRows(array $rows): array
    {
        return array_map([$this, 'normalizeRow'], $rows);
    }

    private function normalizeRow(array $row): array
    {
        $row['payload_json'] = $this->decodeJson($row['payload_json'] ?? null);
        return $row;
    }
}
