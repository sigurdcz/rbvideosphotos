<?php

namespace App\Repositories;

class GtmEventDefinitionRepository extends GtmRepository
{
    public function allBySite(int $siteId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM gtm_event_definitions WHERE site_id = ? ORDER BY category ASC, event_key ASC');
        $stmt->execute([$siteId]);
        return $this->normalizeRows($stmt->fetchAll());
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM gtm_event_definitions WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $this->normalizeRow($row) : null;
    }

    public function findBySiteAndKey(int $siteId, string $eventKey): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM gtm_event_definitions WHERE site_id = ? AND event_key = ? LIMIT 1');
        $stmt->execute([$siteId, $eventKey]);
        $row = $stmt->fetch();
        return $row ? $this->normalizeRow($row) : null;
    }

    public function ensureCatalog(int $siteId, array $catalog): void
    {
        foreach ($catalog as $event) {
            $existing = $this->findBySiteAndKey($siteId, (string) $event['event_key']);
            if ($existing) {
                continue;
            }
            $this->create($siteId, $event);
        }
    }

    public function create(int $siteId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO gtm_event_definitions (site_id, event_key, label, description, category, enabled, trigger_type, tag_type, tag_name, trigger_name, tag_parameter_json, trigger_filter_json, notes_json, local_version)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $siteId,
            $data['event_key'],
            $data['label'],
            $data['description'] ?? null,
            $data['category'],
            isset($data['enabled']) ? (int) $data['enabled'] : 1,
            $data['trigger_type'] ?? 'custom_event',
            $data['tag_type'] ?? 'gaawe',
            $data['tag_name'] ?? $data['label'],
            $data['trigger_name'] ?? $data['label'],
            $this->encodeJson($data['tag_parameter_json'] ?? null),
            $this->encodeJson($data['trigger_filter_json'] ?? null),
            $this->encodeJson($data['notes_json'] ?? null),
            (int)($data['local_version'] ?? 1),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE gtm_event_definitions
             SET label = ?, description = ?, category = ?, enabled = ?, trigger_type = ?, tag_type = ?, tag_name = ?, trigger_name = ?, tag_parameter_json = ?, trigger_filter_json = ?, notes_json = ?, local_version = local_version + 1
             WHERE id = ?'
        );
        $stmt->execute([
            $data['label'],
            $data['description'] ?? null,
            $data['category'],
            isset($data['enabled']) ? (int) $data['enabled'] : 0,
            $data['trigger_type'] ?? 'custom_event',
            $data['tag_type'] ?? 'gaawe',
            $data['tag_name'] ?? $data['label'],
            $data['trigger_name'] ?? $data['label'],
            $this->encodeJson($data['tag_parameter_json'] ?? null),
            $this->encodeJson($data['trigger_filter_json'] ?? null),
            $this->encodeJson($data['notes_json'] ?? null),
            $id,
        ]);
    }

    public function upsertByEventKey(int $siteId, array $data): int
    {
        $existing = $this->findBySiteAndKey($siteId, (string) $data['event_key']);
        if ($existing) {
            $this->update((int) $existing['id'], $data);
            return (int) $existing['id'];
        }

        return $this->create($siteId, $data);
    }

    private function normalizeRows(array $rows): array
    {
        return array_map([$this, 'normalizeRow'], $rows);
    }

    private function normalizeRow(array $row): array
    {
        $row['enabled'] = (int)($row['enabled'] ?? 0);
        $row['tag_parameter_json'] = $this->decodeJson($row['tag_parameter_json'] ?? null);
        $row['trigger_filter_json'] = $this->decodeJson($row['trigger_filter_json'] ?? null);
        $row['notes_json'] = $this->decodeJson($row['notes_json'] ?? null);
        return $row;
    }
}
