<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\SiteRepository;
use App\Repositories\VisitAnalyticsRepository;
use App\Repositories\HealthCheckRepository;
use App\Repositories\TrackingEventRepository;
use App\Services\VisitAnalyticsService;
use App\Services\GtmEventCatalog;

/** GET /admin/analytics - overview of the first-party visit measurement (site_visits). */
final class VisitAnalyticsController extends Controller
{
    public function index(array $params): void
    {
        $this->requireAdmin();
        header('Cache-Control: no-store');

        $days = VisitAnalyticsService::normalizeRangeDays($_GET['days'] ?? 30);
        $view = strtolower(trim((string) ($_GET['view'] ?? 'overview')));
        if (!in_array($view, ['overview', 'health', 'visits', 'events'], true)) {
            $view = 'overview';
        }
        $site = (new SiteRepository())->findFirst();
        $summary = null;
        $healthSummary = null;
        $eventSummary = null;
        $loadError = null;
        $healthLoadError = null;
        $eventLoadError = null;
        if ($site && in_array($view, ['overview', 'visits'], true)) {
            try {
                $summary = (new VisitAnalyticsRepository())->summary((int) $site['id'], $days, new \DateTimeImmutable('today'));
            } catch (\PDOException $e) {
                \App\Core\Logger::error('visit-analytics', $e->getMessage());
                $loadError = 'Tabulka site_visits není dostupná. Spusťte migrace (109_create_site_visits.sql).';
            }
        }
        if ($site && in_array($view, ['overview', 'health'], true)) {
            try {
                $healthSummary = (new HealthCheckRepository())->summary(
                    (int) $site['id'],
                    $days,
                    new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
                );
            } catch (\PDOException $e) {
                \App\Core\Logger::error('health-check', $e->getMessage());
                $healthLoadError = 'Historie zdraví není dostupná. Spusťte migrace (110_create_health_checks.sql).';
            }
        }
        if ($site && in_array($view, ['overview', 'events'], true)) {
            try {
                $eventSummary = (new TrackingEventRepository())->summary((int) $site['id'], $days, new \DateTimeImmutable('today'));
                $catalog = [];
                foreach ((new GtmEventCatalog())->all() as $event) {
                    $catalog[(string) $event['event_key']] = $event;
                }
                foreach ($eventSummary['by_event'] as &$row) {
                    $definition = $catalog[$row['event_name']] ?? null;
                    $row['label'] = $definition['label'] ?? $row['event_name'];
                    $row['category'] = $definition['category'] ?? 'nezarazeno';
                }
                unset($row);
                foreach ($eventSummary['top_paths'] as &$row) {
                    $definition = $catalog[$row['event_name']] ?? null;
                    $row['label'] = $definition['label'] ?? $row['event_name'];
                }
                unset($row);
            } catch (\PDOException $e) {
                \App\Core\Logger::error('tracking-events', $e->getMessage());
                $eventLoadError = 'Historie událostí není dostupná. Spusťte migrace (122_create_tracking_events.sql).';
            }
        }

        $this->view('analytics.index', [
            'days' => $days,
            'activeView' => $view,
            'summary' => $summary,
            'healthSummary' => $healthSummary,
            'eventSummary' => $eventSummary,
            'loadError' => $loadError,
            'healthLoadError' => $healthLoadError,
            'eventLoadError' => $eventLoadError,
            'visitEnabled' => $site ? (int) ($site['visit_analytics_enabled'] ?? 1) === 1 : false,
            'eventEnabled' => $site ? (int) ($site['tracking_enabled'] ?? 1) === 1 : false,
            'hasSite' => (bool) $site,
        ]);
    }
}
