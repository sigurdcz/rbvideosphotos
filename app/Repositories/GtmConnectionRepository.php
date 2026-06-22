<?php

namespace App\Repositories;

class GtmConnectionRepository extends GtmRepository
{
    public function findBySite(int $siteId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM gtm_connections WHERE site_id = ? LIMIT 1');
        $stmt->execute([$siteId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function all(): array
    {
        return $this->db->query('SELECT * FROM gtm_connections ORDER BY updated_at DESC')->fetchAll();
    }

    public function save(int $siteId, array $data): int
    {
        $existing = $this->findBySite($siteId);
        if ($existing) {
            $this->update((int) $existing['id'], $data);
            return (int) $existing['id'];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO gtm_connections
             (site_id, status, google_account_email, google_account_name, access_token_encrypted, refresh_token_encrypted, token_scopes, token_expires_at, account_id, account_name, container_id, container_name, workspace_id, workspace_name, workspace_path, workspace_fingerprint, last_inventory_at, last_synced_at, last_verified_at, last_error, inventory_snapshot_json, inventory_snapshot_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $siteId,
            $data['status'] ?? 'disconnected',
            $data['google_account_email'] ?? null,
            $data['google_account_name'] ?? null,
            $data['access_token_encrypted'] ?? null,
            $data['refresh_token_encrypted'] ?? null,
            $data['token_scopes'] ?? null,
            $data['token_expires_at'] ?? null,
            $data['account_id'] ?? null,
            $data['account_name'] ?? null,
            $data['container_id'] ?? null,
            $data['container_name'] ?? null,
            $data['workspace_id'] ?? null,
            $data['workspace_name'] ?? null,
            $data['workspace_path'] ?? null,
            $data['workspace_fingerprint'] ?? null,
            $data['last_inventory_at'] ?? null,
            $data['last_synced_at'] ?? null,
            $data['last_verified_at'] ?? null,
            $data['last_error'] ?? null,
            $data['inventory_snapshot_json'] ?? null,
            $data['inventory_snapshot_at'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [
            'status',
            'google_account_email',
            'google_account_name',
            'access_token_encrypted',
            'refresh_token_encrypted',
            'token_scopes',
            'token_expires_at',
            'account_id',
            'account_name',
            'container_id',
            'container_name',
            'workspace_id',
            'workspace_name',
            'workspace_path',
            'workspace_fingerprint',
            'last_inventory_at',
            'last_synced_at',
            'last_verified_at',
            'last_error',
            'inventory_snapshot_json',
            'inventory_snapshot_at',
        ];

        $sets = [];
        $values = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "`{$field}` = ?";
                $values[] = $data[$field];
            }
        }

        if ($sets === []) {
            return;
        }

        $values[] = $id;
        $this->db->prepare('UPDATE gtm_connections SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
    }

    public function setDisconnected(int $siteId, ?string $message = null): void
    {
        $stmt = $this->db->prepare(
            'UPDATE gtm_connections SET status = ?, access_token_encrypted = NULL, refresh_token_encrypted = NULL, token_scopes = NULL, token_expires_at = NULL, last_error = ?, account_id = NULL, account_name = NULL, container_id = NULL, container_name = NULL, workspace_id = NULL, workspace_name = NULL, workspace_path = NULL, workspace_fingerprint = NULL, inventory_snapshot_json = NULL, inventory_snapshot_at = NULL WHERE site_id = ?'
        );
        $stmt->execute(['disconnected', $message, $siteId]);
    }
}
