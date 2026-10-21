<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class HealthCheckRepository
{
    public function __construct(private ?PDO $db = null)
    {
        $this->db ??= Database::getInstance();
    }

    public function firstSiteId(): int
    {
        $id = $this->db->query('SELECT id FROM sites ORDER BY id ASC LIMIT 1')->fetchColumn();
        if (!$id) {
            throw new \RuntimeException('Health check cannot be stored because no site exists.');
        }
        return (int) $id;
    }

    public function insert(int $siteId, array $record): void
    {
        $sql = 'INSERT INTO health_checks
            (check_uuid, site_id, checked_at, status, http_status, response_time_ms, database_status, error_code, source)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE check_uuid = VALUES(check_uuid)';
        $this->db->prepare($sql)->execute([
            (string) $record['check_uuid'], $siteId, (string) $record['checked_at'], (string) $record['status'],
            $record['http_status'], (int) $record['response_time_ms'], (string) $record['database_status'],
            $record['error_code'], (string) ($record['source'] ?? 'cron'),
        ]);
    }

    public function purgeBefore(string $cutoff): int
    {
        $stmt = $this->db->prepare('DELETE FROM health_checks WHERE checked_at < ?');
        $stmt->execute([$cutoff]);
        return $stmt->rowCount();
    }

    public function summary(int $siteId, int $days, \DateTimeImmutable $now): array
    {
        $from = $now->modify('-' . max(0, $days - 1) . ' days')->format('Y-m-d 00:00:00');
        $to = $now->format('Y-m-d 23:59:59');
        $params = [$siteId, $from, $to];
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS checks_total,
                    COALESCE(SUM(status = \'healthy\'), 0) AS healthy_total,
                    COALESCE(SUM(status = \'unhealthy\'), 0) AS unhealthy_total,
                    COALESCE(ROUND(AVG(response_time_ms)), 0) AS average_ms
             FROM health_checks WHERE site_id = ? AND checked_at BETWEEN ? AND ?'
        );
        $stmt->execute($params);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $this->db->prepare(
            'SELECT * FROM health_checks WHERE site_id = ? ORDER BY checked_at DESC, id DESC LIMIT 1'
        );
        $stmt->execute([$siteId]);
        $latest = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $stmt = $this->db->prepare(
            'SELECT DATE(checked_at) AS day, COUNT(*) AS checks_total,
                    SUM(status = \'healthy\') AS healthy_total,
                    SUM(status = \'unhealthy\') AS unhealthy_total,
                    ROUND(AVG(response_time_ms)) AS average_ms
             FROM health_checks WHERE site_id = ? AND checked_at BETWEEN ? AND ?
             GROUP BY DATE(checked_at) ORDER BY day ASC'
        );
        $stmt->execute($params);
        $byDay = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $checks = (int) $row['checks_total'];
            $healthy = (int) $row['healthy_total'];
            $byDay[(string) $row['day']] = [
                'checks' => $checks,
                'healthy' => $healthy,
                'unhealthy' => (int) $row['unhealthy_total'],
                'uptime' => $checks > 0 ? round($healthy / $checks * 100, 2) : null,
                'average_ms' => (int) $row['average_ms'],
            ];
        }
        $daily = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = $now->modify('-' . $i . ' days')->format('Y-m-d');
            $daily[] = ['day' => $day] + ($byDay[$day] ?? [
                'checks' => 0, 'healthy' => 0, 'unhealthy' => 0, 'uptime' => null, 'average_ms' => 0,
            ]);
        }

        $total = (int) ($totals['checks_total'] ?? 0);
        $healthy = (int) ($totals['healthy_total'] ?? 0);
        $latestAt = $latest ? new \DateTimeImmutable((string) $latest['checked_at'], new \DateTimeZone('UTC')) : null;
        $stale = $latestAt === null || $latestAt < $now->modify('-' . \App\Services\HealthCheckService::STALE_AFTER_MINUTES . ' minutes');

        $stmt = $this->db->prepare(
            'SELECT checked_at, http_status, response_time_ms, database_status, error_code, source
             FROM health_checks WHERE site_id = ? AND status = \'unhealthy\' AND checked_at BETWEEN ? AND ?
             ORDER BY checked_at DESC, id DESC LIMIT 20'
        );
        $stmt->execute($params);
        $recentFailures = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'from' => substr($from, 0, 10), 'to' => substr($to, 0, 10),
            'checks' => $total, 'healthy' => $healthy,
            'unhealthy' => (int) ($totals['unhealthy_total'] ?? 0),
            'uptime' => $total > 0 ? round($healthy / $total * 100, 2) : null,
            'average_ms' => (int) ($totals['average_ms'] ?? 0),
            'latest' => $latest, 'stale' => $stale, 'daily' => $daily,
            'recent_failures' => $recentFailures,
        ];
    }
}
