<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\SiteRepository;
use App\Repositories\VisitAnalyticsRepository;
use App\Services\VisitAnalyticsService;

/** GET /admin/analytics - overview of the first-party visit measurement (site_visits). */
final class VisitAnalyticsController extends Controller
{
    public function index(array $params): void
    {
        $this->requireAdmin();
        header('Cache-Control: no-store');

        $days = VisitAnalyticsService::normalizeRangeDays($_GET['days'] ?? 30);
        $site = (new SiteRepository())->findFirst();
        $summary = null;
        $loadError = null;
        if ($site) {
            try {
                $summary = (new VisitAnalyticsRepository())->summary((int) $site['id'], $days, new \DateTimeImmutable('today'));
            } catch (\PDOException $e) {
                \App\Core\Logger::error('visit-analytics', $e->getMessage());
                $loadError = 'Tabulka site_visits není dostupná. Spusťte migrace (109_create_site_visits.sql).';
            }
        }

        $this->view('analytics.index', [
            'days' => $days,
            'summary' => $summary,
            'loadError' => $loadError,
            'enabled' => $site ? (int) ($site['visit_analytics_enabled'] ?? 1) === 1 : false,
            'hasSite' => (bool) $site,
        ]);
    }
}
