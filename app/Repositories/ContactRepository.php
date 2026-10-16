<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ContactRepository
{
    private PDO $db;
    public const DEFAULT_FORM_EMAIL = 'sigurd.gnezdilov@gmail.com';
    public const DEFAULT_REVIEWS_URL = 'https://maps.app.goo.gl/DN9Y1ohrSgAePg3n9';

    private const FIELDS = [
        'phone_1', 'phone_2', 'phone_3', 'email',
        'address_1', 'address_2', 'maps_url', 'reviews_url',
        'social_1_type', 'social_1_url',
        'social_2_type', 'social_2_url',
        'social_3_type', 'social_3_url',
        'social_4_type', 'social_4_url',
        'social_5_type', 'social_5_url',
        'form_email',
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findBySite(int $siteId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM contact_info WHERE site_id = ? LIMIT 1');
        $stmt->execute([$siteId]);
        return $stmt->fetch() ?: null;
    }

    public function upsert(int $siteId, array $d, bool $allowRecoveryRecipientChange = false): void
    {
        $existing = $this->findBySite($siteId);
        if (!$allowRecoveryRecipientChange && array_key_exists('form_email', $d)
            && trim((string) $d['form_email']) !== trim((string) ($existing['form_email'] ?? ''))) {
            throw new \RuntimeException('Adresu pro obnovu přístupu smí měnit pouze administrátor.');
        }
        $fields = $allowRecoveryRecipientChange ? self::FIELDS : array_values(array_diff(self::FIELDS, ['form_email']));
        if (!array_key_exists('form_email', $d)) { $d['form_email'] = $existing['form_email'] ?? null; }
        $vals = [];
        foreach ($fields as $f) {
            $v = trim((string)($d[$f] ?? ''));
            $vals[$f] = $v !== '' ? $v : null;
        }

        if ($existing) {
            $sets = implode(', ', array_map(fn($f) => "{$f}=?", $fields));
            $stmt = $this->db->prepare("UPDATE contact_info SET {$sets} WHERE site_id=?");
            $stmt->execute([...array_values($vals), $siteId]);
        } else {
            $cols = implode(', ', $fields);
            $phs  = implode(', ', array_fill(0, count($fields), '?'));
            $stmt = $this->db->prepare("INSERT INTO contact_info (site_id, {$cols}) VALUES (?, {$phs})");
            $stmt->execute([$siteId, ...array_values($vals)]);
        }
    }

    /** Return empty array if table doesn't exist yet (migration not run). */
    public function findBySiteSafe(int $siteId): array
    {
        try {
            return $this->findBySite($siteId) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
