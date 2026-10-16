<?php

namespace App\Repositories;

use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

class SecurityTxtRepository
{
    private PDO $db;
    private ?bool $schemaAvailable = null;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function isSchemaAvailable(): bool
    {
        if ($this->schemaAvailable !== null) {
            return $this->schemaAvailable;
        }

        foreach (['security_txt_configs', 'security_txt_contacts'] as $table) {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND CONVERT(table_name USING utf8mb4) = ?'
            );
            $stmt->execute([$table]);
            if ((int) $stmt->fetchColumn() !== 1) {
                return $this->schemaAvailable = false;
            }
        }

        return $this->schemaAvailable = true;
    }

    public function findBySite(int $siteId): array
    {
        $empty = $this->emptyConfig($siteId);
        if (!$this->isSchemaAvailable()) {
            $empty['schema_available'] = false;
            return $empty;
        }

        $stmt = $this->db->prepare('SELECT * FROM security_txt_configs WHERE site_id = ? LIMIT 1');
        $stmt->execute([$siteId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return $empty;
        }

        $contacts = $this->db->prepare(
            'SELECT contact_uri FROM security_txt_contacts WHERE config_id = ? ORDER BY sort_order ASC, id ASC'
        );
        $contacts->execute([(int) $row['id']]);

        return [
            'schema_available' => true,
            'exists' => true,
            'id' => (int) $row['id'],
            'site_id' => (int) $row['site_id'],
            'enabled' => (int) $row['enabled'],
            'contacts' => array_map(static fn(array $item): string => (string) $item['contact_uri'], $contacts->fetchAll(PDO::FETCH_ASSOC)),
            'expires_at' => $this->toRfc3339($row['expires_at'] ?? null),
            'canonical_url' => (string) ($row['canonical_url'] ?? ''),
            'encryption_url' => (string) ($row['encryption_url'] ?? ''),
            'policy_url' => (string) ($row['policy_url'] ?? ''),
            'acknowledgments_url' => (string) ($row['acknowledgments_url'] ?? ''),
            'preferred_languages' => (string) ($row['preferred_languages'] ?? ''),
            'hiring_url' => (string) ($row['hiring_url'] ?? ''),
            'comments' => (string) ($row['comments'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    public function save(int $siteId, array $config): array
    {
        if (!$this->isSchemaAvailable()) {
            throw new RuntimeException('security.txt database tables are not available. Run migrations first.');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO security_txt_configs
                    (site_id, enabled, expires_at, canonical_url, encryption_url, policy_url, acknowledgments_url, preferred_languages, hiring_url, comments)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    enabled = VALUES(enabled), expires_at = VALUES(expires_at), canonical_url = VALUES(canonical_url),
                    encryption_url = VALUES(encryption_url), policy_url = VALUES(policy_url),
                    acknowledgments_url = VALUES(acknowledgments_url), preferred_languages = VALUES(preferred_languages),
                    hiring_url = VALUES(hiring_url), comments = VALUES(comments), updated_at = CURRENT_TIMESTAMP'
            );
            $stmt->execute([
                $siteId,
                !empty($config['enabled']) ? 1 : 0,
                $config['expires_db'] ?? null,
                $this->nullIfEmpty($config['canonical_url'] ?? ''),
                $this->nullIfEmpty($config['encryption_url'] ?? ''),
                $this->nullIfEmpty($config['policy_url'] ?? ''),
                $this->nullIfEmpty($config['acknowledgments_url'] ?? ''),
                $this->nullIfEmpty($config['preferred_languages'] ?? ''),
                $this->nullIfEmpty($config['hiring_url'] ?? ''),
                $this->nullIfEmpty($config['comments'] ?? ''),
            ]);

            $idStmt = $this->db->prepare('SELECT id FROM security_txt_configs WHERE site_id = ? LIMIT 1');
            $idStmt->execute([$siteId]);
            $configId = (int) $idStmt->fetchColumn();

            $this->db->prepare('DELETE FROM security_txt_contacts WHERE config_id = ?')->execute([$configId]);
            $insertContact = $this->db->prepare(
                'INSERT INTO security_txt_contacts (config_id, contact_uri, sort_order) VALUES (?, ?, ?)'
            );
            foreach (array_values($config['contacts'] ?? []) as $index => $contact) {
                $insertContact->execute([$configId, (string) $contact, $index]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $this->findBySite($siteId);
    }

    private function emptyConfig(int $siteId): array
    {
        return [
            'schema_available' => true,
            'exists' => false,
            'id' => null,
            'site_id' => $siteId,
            'enabled' => 0,
            'contacts' => [],
            'expires_at' => null,
            'canonical_url' => '',
            'encryption_url' => '',
            'policy_url' => '',
            'acknowledgments_url' => '',
            'preferred_languages' => '',
            'hiring_url' => '',
            'comments' => '',
            'updated_at' => '',
        ];
    }

    private function toRfc3339(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
        return $date === false ? null : $date->format('Y-m-d\TH:i:s\Z');
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
