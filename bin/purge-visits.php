<?php
declare(strict_types=1);

// Deletes site_visits rows older than the 14-month retention window. The public
// beacon already does this lazily (about 1 % of requests); this script is for cron
// or manual cleanup. Usage: php bin/purge-visits.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Config;
use App\Core\Env;
use App\Repositories\VisitAnalyticsRepository;
use App\Services\VisitAnalyticsService;

Env::load(dirname(__DIR__) . '/.env');
date_default_timezone_set(Config::get('app', 'timezone', 'Europe/Prague'));

$cutoff = VisitAnalyticsService::retentionCutoff(new DateTimeImmutable('today'));
$deleted = (new VisitAnalyticsRepository())->purgeBefore($cutoff);
echo sprintf("Deleted %d site_visits row(s) older than %s.\n", $deleted, $cutoff);
