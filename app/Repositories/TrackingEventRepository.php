<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class TrackingEventRepository
{
    private const COLUMNS = [
        'site_id', 'occurred_at', 'occurred_on', 'event_name', 'path',
        'visitor_hash', 'consent_analytics', 'is_bot',
    ];

    public function __construct(private ?PDO $db = null)
    {
        $this->db ??= Database::getInstance();
    }

    public function insert(array $record): void
    {
        $values = array_map(static fn(string $column): mixed => $record[$column] ?? null, self::COLUMNS);
        $this->db->prepare(
            'INSERT INTO tracking_events (`' . implode('`, `', self::COLUMNS) . '`) VALUES ('
            . implode(', ', array_fill(0, count(self::COLUMNS), '?')) . ')'
        )->execute($values);
    }

    public function purgeBefore(string $cutoffDay): int
    {
        $stmt = $this->db->prepare('DELETE FROM tracking_events WHERE occurred_on < ?');
        $stmt->execute([$cutoffDay]);
        return $stmt->rowCount();
    }

    public function summary(int $siteId, int $days, \DateTimeImmutable $today): array
    {
        $from = $today->modify('-' . max(0, $days - 1) . ' days')->format('Y-m-d');
        $to = $today->format('Y-m-d');
        $range = [$siteId, $from, $to];
        $where = ' WHERE site_id = ? AND occurred_on BETWEEN ? AND ? AND is_bot = 0';

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS events, COUNT(DISTINCT visitor_hash) AS daily_uniques,
                    COALESCE(SUM(consent_analytics), 0) AS consented_events
             FROM tracking_events' . $where
        );
        $stmt->execute($range);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $this->db->prepare(
            'SELECT event_name, COUNT(*) AS events, COUNT(DISTINCT visitor_hash) AS daily_uniques
             FROM tracking_events' . $where . '
             GROUP BY event_name ORDER BY events DESC, event_name ASC'
        );
        $stmt->execute($range);
        $byEvent = array_map(static fn(array $row): array => [
            'event_name' => (string) $row['event_name'],
            'events' => (int) $row['events'],
            'daily_uniques' => (int) $row['daily_uniques'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));

        $stmt = $this->db->prepare(
            'SELECT occurred_on, COUNT(*) AS events,
                    COUNT(DISTINCT visitor_hash) AS daily_uniques,
                    COALESCE(SUM(consent_analytics), 0) AS consented_events
             FROM tracking_events' . $where . '
             GROUP BY occurred_on ORDER BY occurred_on ASC'
        );
        $stmt->execute($range);
        $byDay = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byDay[(string) $row['occurred_on']] = [
                'events' => (int) $row['events'],
                'daily_uniques' => (int) $row['daily_uniques'],
                'consented_events' => (int) $row['consented_events'],
            ];
        }
        $daily = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = $today->modify('-' . $i . ' days')->format('Y-m-d');
            $daily[] = ['day' => $day] + ($byDay[$day] ?? [
                'events' => 0,
                'daily_uniques' => 0,
                'consented_events' => 0,
            ]);
        }

        $stmt = $this->db->prepare(
            'SELECT path, COUNT(*) AS events, COUNT(DISTINCT visitor_hash) AS daily_uniques
             FROM tracking_events' . $where . '
             GROUP BY path ORDER BY events DESC, path ASC LIMIT 15'
        );
        $stmt->execute($range);
        $byPath = array_map(static fn(array $row): array => [
            'path' => (string) $row['path'],
            'events' => (int) $row['events'],
            'daily_uniques' => (int) $row['daily_uniques'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));

        $stmt = $this->db->prepare(
            'SELECT SUBSTR(occurred_at, 12, 2) AS event_hour, COUNT(*) AS events
             FROM tracking_events' . $where . '
             GROUP BY SUBSTR(occurred_at, 12, 2) ORDER BY event_hour ASC'
        );
        $stmt->execute($range);
        $byHour = array_fill(0, 24, 0);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $hour = filter_var($row['event_hour'], FILTER_VALIDATE_INT);
            if ($hour !== false && $hour >= 0 && $hour <= 23) {
                $byHour[$hour] = (int) $row['events'];
            }
        }

        $stmt = $this->db->prepare(
            'SELECT path, event_name, COUNT(*) AS events FROM tracking_events' . $where . '
             GROUP BY path, event_name ORDER BY events DESC, path ASC, event_name ASC LIMIT 20'
        );
        $stmt->execute($range);
        $topPaths = array_map(static fn(array $row): array => [
            'path' => (string) $row['path'],
            'event_name' => (string) $row['event_name'],
            'events' => (int) $row['events'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));

        return [
            'days' => $days,
            'from' => $from,
            'to' => $to,
            'events' => (int) ($totals['events'] ?? 0),
            'daily_uniques' => (int) ($totals['daily_uniques'] ?? 0),
            'consented_events' => (int) ($totals['consented_events'] ?? 0),
            'by_event' => $byEvent,
            'daily' => $daily,
            'by_path' => $byPath,
            'by_hour' => $byHour,
            'top_paths' => $topPaths,
        ];
    }
}
