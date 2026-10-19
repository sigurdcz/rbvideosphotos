<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Storage and admin aggregates of the first-party visit measurement (site_visits).
 * Row building and validation live in App\Services\VisitAnalyticsService.
 */
final class VisitAnalyticsRepository
{
    private const COLUMNS = [
        'site_id', 'visited_at', 'visited_on', 'path', 'referrer_host',
        'utm_source', 'utm_medium', 'utm_campaign', 'device_type',
        'visitor_hash', 'visitor_id', 'consent_analytics', 'is_bot',
    ];

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function insert(array $record): void
    {
        $values = [];
        foreach (self::COLUMNS as $column) {
            $values[] = $record[$column] ?? null;
        }
        $this->db->prepare(
            'INSERT INTO site_visits (`' . implode('`, `', self::COLUMNS) . '`) VALUES ('
            . implode(', ', array_fill(0, count(self::COLUMNS), '?')) . ')'
        )->execute($values);
    }

    /** Deletes rows older than $cutoffDay (Y-m-d, exclusive). */
    public function purgeBefore(string $cutoffDay): int
    {
        $stmt = $this->db->prepare('DELETE FROM site_visits WHERE visited_on < ?');
        $stmt->execute([$cutoffDay]);
        return $stmt->rowCount();
    }

    /**
     * Aggregates for /admin/analytics over the last $days days including $today.
     * Human traffic only (is_bot = 0); bots are counted separately.
     */
    public function summary(int $siteId, int $days, \DateTimeImmutable $today): array
    {
        $from = $today->modify('-' . max(0, $days - 1) . ' days')->format('Y-m-d');
        $to = $today->format('Y-m-d');
        $range = [$siteId, $from, $to];
        $where = ' WHERE site_id = ? AND visited_on BETWEEN ? AND ?';

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS visits,
                    COUNT(DISTINCT visitor_hash) AS uniques,
                    COUNT(DISTINCT visitor_id) AS consented_uniques,
                    COALESCE(SUM(consent_analytics), 0) AS consented_visits
             FROM site_visits' . $where . ' AND is_bot = 0'
        );
        $stmt->execute($range);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM site_visits' . $where . ' AND is_bot = 1');
        $stmt->execute($range);
        $bots = (int) $stmt->fetchColumn();

        $stmt = $this->db->prepare(
            'SELECT visited_on, COUNT(*) AS visits, COUNT(DISTINCT visitor_hash) AS uniques
             FROM site_visits' . $where . ' AND is_bot = 0
             GROUP BY visited_on ORDER BY visited_on ASC'
        );
        $stmt->execute($range);
        $byDay = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byDay[(string) $row['visited_on']] = ['visits' => (int) $row['visits'], 'uniques' => (int) $row['uniques']];
        }
        $daily = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = $today->modify('-' . $i . ' days')->format('Y-m-d');
            $daily[] = ['day' => $day] + ($byDay[$day] ?? ['visits' => 0, 'uniques' => 0]);
        }

        $visits = (int) ($totals['visits'] ?? 0);
        $consentedVisits = (int) ($totals['consented_visits'] ?? 0);

        return [
            'days' => $days,
            'from' => $from,
            'to' => $to,
            'visits' => $visits,
            'uniques' => (int) ($totals['uniques'] ?? 0),
            'consented_uniques' => (int) ($totals['consented_uniques'] ?? 0),
            'consented_visits' => $consentedVisits,
            'consent_share' => $visits > 0 ? round($consentedVisits / $visits * 100, 1) : 0.0,
            'bots' => $bots,
            'daily' => $daily,
            'top_paths' => $this->topList('path', $where, $range),
            'top_referrers' => $this->topList('referrer_host', $where . ' AND referrer_host IS NOT NULL', $range),
            'top_sources' => $this->topList('utm_source', $where . ' AND utm_source IS NOT NULL', $range),
            'devices' => $this->topList('device_type', $where, $range),
        ];
    }

    /** @return list<array{label:string,visits:int}> */
    private function topList(string $column, string $where, array $params): array
    {
        // $column is one of the fixed identifiers passed by summary(), never user input.
        $stmt = $this->db->prepare(
            'SELECT `' . $column . '` AS label, COUNT(*) AS visits FROM site_visits' . $where
            . ' AND is_bot = 0 GROUP BY `' . $column . '` ORDER BY visits DESC, label ASC LIMIT 10'
        );
        $stmt->execute($params);

        return array_map(
            static fn(array $row): array => ['label' => (string) $row['label'], 'visits' => (int) $row['visits']],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }
}
