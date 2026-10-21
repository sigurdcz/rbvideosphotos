<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\SiteRepository;
use App\Repositories\VisitAnalyticsRepository;
use App\Repositories\HealthCheckRepository;
use App\Services\VisitAnalyticsService;

/** GET /admin/analytics - overview of the first-party visit measurement (site_visits). */
final class VisitAnalyticsController extends Controller
{
    public function index(array $params): void
    {
        $this->requireAdmin();
        header('Cache-Control: no-store');

        $days = VisitAnalyticsService::normalizeRangeDays($_GET['days'] ?? 30);
        $view = strtolower(trim((string) ($_GET['view'] ?? 'overview')));
        if (!in_array($view, ['overview', 'health', 'visits'], true)) {
            $view = 'overview';
        }
        $site = (new SiteRepository())->findFirst();
        $summary = null;
        $healthSummary = null;
        $loadError = null;
        $healthLoadError = null;
        if ($site && $view !== 'health') {
            try {
                $summary = (new VisitAnalyticsRepository())->summary((int) $site['id'], $days, new \DateTimeImmutable('today'));
            } catch (\PDOException $e) {
                \App\Core\Logger::error('visit-analytics', $e->getMessage());
                $loadError = 'Tabulka site_visits není dostupná. Spusťte migrace (109_create_site_visits.sql).';
            }
        }
        if ($site && $view !== 'visits') {
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

        $this->view('analytics.index', [
            'days' => $days,
            'activeView' => $view,
            'summary' => $summary,
            'healthSummary' => $healthSummary,
            'loadError' => $loadError,
            'healthLoadError' => $healthLoadError,
            'enabled' => $site ? (int) ($site['visit_analytics_enabled'] ?? 1) === 1 : false,
            'hasSite' => (bool) $site,
        ]);
    }
}
