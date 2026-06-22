<?php

namespace App\Repositories;

class GtmContainerVersionRepository extends GtmRepository
{
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO gtm_container_versions (site_id, connection_id, sync_run_id, container_version_id, version_name, version_path, fingerprint, published, compiler_error, new_workspace_path, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['site_id'],
            $data['connection_id'] ?? null,
            $data['sync_run_id'] ?? null,
            $data['container_version_id'],
            $data['version_name'],
            $data['version_path'] ?? null,
            $data['fingerprint'] ?? null,
            !empty($data['published']) ? 1 : 0,
            isset($data['compiler_error']) ? (int) $data['compiler_error'] : null,
            $data['new_workspace_path'] ?? null,
            $data['note'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function allBySite(int $siteId, int $limit = 20): array
    {
        $stmt = $this->db->prepare('SELECT * FROM gtm_container_versions WHERE site_id = ? ORDER BY created_at DESC LIMIT ' . (int) $limit);
        $stmt->execute([$siteId]);
        return $stmt->fetchAll();
    }

    public function latestBySite(int $siteId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM gtm_container_versions WHERE site_id = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([$siteId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
