<?php

declare(strict_types=1);

use App\Repositories\TrackingEventRepository;
use App\Services\TrackingEventService;

function register_tracking_event_analytics_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);

    $test('tracking event payload accepts only catalog events and public paths', function () use ($assert): void {
        $payload = TrackingEventService::parsePayload('{"e":"phone_click","p":"/kontakt?x=1","a":1}');
        $assert($payload === ['e' => 'phone_click', 'p' => '/kontakt', 'a' => true]);
        foreach ([
            '', '{}', '[]', '{"e":"made_up","p":"/","a":1}',
            '{"e":"phone_click","p":"/admin/settings","a":1}',
            '{"e":"phone_click","p":"/","a":"yes"}',
        ] as $body) {
            $assert(TrackingEventService::parsePayload($body) === null, 'Invalid payload must be rejected: ' . $body);
        }
        $assert(!in_array('generate_lead', TrackingEventService::allowedEventNames(), true), 'Derived GA event must not duplicate the source event locally.');
    });

    $test('tracking event record stores no raw IP or event parameters', function () use ($assert): void {
        $record = TrackingEventService::buildRecord(
            3,
            ['e' => 'email_click', 'p' => '/kontakt', 'a' => true],
            '203.0.113.50',
            'Mozilla/5.0 Chrome/130',
            'server-secret',
            new DateTimeImmutable('2026-11-11 10:30:00')
        );
        $assert($record['event_name'] === 'email_click' && $record['path'] === '/kontakt');
        $assert($record['occurred_on'] === '2026-11-11' && $record['consent_analytics'] === 1);
        $assert(!in_array('203.0.113.50', array_map('strval', $record), true));
        foreach (['event_label', 'event_category', 'params', 'visitor_id'] as $forbidden) {
            $assert(!array_key_exists($forbidden, $record), 'Stored record must exclude ' . $forbidden);
        }
    });

    $test('tracking event repository aggregates event, day and page counts', function () use ($assert): void {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException('pdo_sqlite is required for tracking event repository test.');
        }
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('CREATE TABLE tracking_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT, site_id INTEGER NOT NULL,
            occurred_at TEXT NOT NULL, occurred_on TEXT NOT NULL, event_name TEXT NOT NULL,
            path TEXT NOT NULL, visitor_hash TEXT NOT NULL, consent_analytics INTEGER NOT NULL,
            is_bot INTEGER NOT NULL
        )');
        $repository = new TrackingEventRepository($db);
        foreach ([
            ['2026-11-10', 'phone_click', '/kontakt', 'aaa', 1, 0],
            ['2026-11-10', 'phone_click', '/kontakt', 'aaa', 1, 0],
            ['2026-11-11', 'gallery_open', '/nase-prace', 'bbb', 0, 0],
            ['2026-11-11', 'phone_click', '/kontakt', 'bot', 0, 1],
        ] as [$day, $event, $path, $hash, $consent, $bot]) {
            $repository->insert([
                'site_id' => 1, 'occurred_at' => $day . ' 10:00:00', 'occurred_on' => $day,
                'event_name' => $event, 'path' => $path, 'visitor_hash' => str_pad($hash, 32, '0'),
                'consent_analytics' => $consent, 'is_bot' => $bot,
            ]);
        }
        $summary = $repository->summary(1, 2, new DateTimeImmutable('2026-11-11'));
        $assert($summary['events'] === 3 && $summary['daily_uniques'] === 2 && $summary['consented_events'] === 2);
        $assert($summary['by_event'][0]['event_name'] === 'phone_click' && $summary['by_event'][0]['events'] === 2);
        $assert($summary['daily'] === [
            ['day' => '2026-11-10', 'events' => 2, 'daily_uniques' => 1, 'consented_events' => 2],
            ['day' => '2026-11-11', 'events' => 1, 'daily_uniques' => 1, 'consented_events' => 0],
        ]);
        $assert($summary['by_path'][0] === ['path' => '/kontakt', 'events' => 2, 'daily_uniques' => 1]);
        $assert($summary['by_hour'][10] === 3 && array_sum($summary['by_hour']) === 3);
        $assert($summary['top_paths'][0] === ['path' => '/kontakt', 'event_name' => 'phone_click', 'events' => 2]);
    });

    $test('tracking event endpoint migration and analytics view stay wired', function () use ($assert, $root): void {
        $index = (string) file_get_contents($root . '/public/index.php');
        $eventRoute = strpos($index, 'if (tryRecordTrackingEvent($earlyUri))');
        $auth = strpos($index, "Auth::start();\n");
        $assert($eventRoute !== false && $eventRoute < $auth, 'Event endpoint must run before session start.');
        foreach (['VisitAnalyticsService::isSameOrigin', 'TrackingEventService::parsePayload', 'http_response_code(503)', 'TrackingEventRepository'] as $needle) {
            $assert(str_contains($index, $needle), 'Endpoint missing contract: ' . $needle);
        }
        $eventFunction = substr($index, (int) strpos($index, 'function tryRecordTrackingEvent'));
        $assert(str_contains($eventFunction, "\$site['tracking_enabled']"), 'Event endpoint must use tracking_enabled.');
        $visitFunction = substr($index, (int) strpos($index, 'function tryRecordVisitBeacon'), (int) strpos($index, 'function tryRecordTrackingEvent') - (int) strpos($index, 'function tryRecordVisitBeacon'));
        $assert(str_contains($visitFunction, "\$site['visit_analytics_enabled']"), 'Visit endpoint must keep its independent switch.');
        $migration = (string) file_get_contents($root . '/database/migrations/122_create_tracking_events.sql');
        $schema = (string) file_get_contents($root . '/database/schema.sql');
        $assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS tracking_events'));
        $assert(str_contains($schema, 'CREATE TABLE IF NOT EXISTS `tracking_events`'));
        $assert(str_contains($migration, 'event_name VARCHAR(64)') && str_contains($schema, '`event_name`        VARCHAR(64)'));
        $assert(str_contains($migration, 'tracking_events_site_event_day') && str_contains($schema, '`tracking_events_site_event_day`'));
        $assert(!str_contains($migration, 'event_label') && !str_contains($migration, 'event_params'));

        $days = 30;
        $activeView = 'events';
        $visitEnabled = true;
        $eventEnabled = true;
        $hasSite = true;
        $loadError = $healthLoadError = $eventLoadError = null;
        $summary = $healthSummary = null;
        $eventSummary = [
            'from' => '2026-10-13', 'to' => '2026-11-11', 'events' => 4,
            'daily_uniques' => 2, 'consented_events' => 3,
            'daily' => [['day' => '2026-11-11', 'events' => 4, 'daily_uniques' => 2, 'consented_events' => 3]],
            'by_event' => [['event_name' => 'phone_click', 'label' => 'Kliknutí na telefonní číslo', 'category' => 'mikro-konverze', 'events' => 4, 'daily_uniques' => 2]],
            'by_path' => [['path' => '/kontakt', 'events' => 4, 'daily_uniques' => 2]],
            'by_hour' => array_replace(array_fill(0, 24, 0), [10 => 4]),
            'top_paths' => [['path' => '/kontakt', 'event_name' => 'phone_click', 'label' => 'Kliknutí na telefonní číslo', 'events' => 4]],
        ];
        $_user = ['role' => 'admin', 'email' => 'admin@example.test'];
        $_csrfToken = 'test';
        $_SERVER['REQUEST_URI'] = '/admin/analytics?view=events&days=30';
        ob_start();
        include $root . '/admin/views/analytics/index.php';
        $html = (string) ob_get_clean();
        foreach (['GTM události', 'phone_click', '/kontakt', 'Události celkem', 'Potvrzují odeslání z webu', 'Vývoj událostí', 'Rozdělení událostí', 'Aktivita během dne', 'Nejaktivnější stránky'] as $needle) {
            $assert(str_contains($html, $needle), 'Events analytics missing: ' . $needle);
        }
        $assert(str_contains($html, '<svg class="analytics-line-chart"'), 'Events analytics must render the accessible SVG trend chart.');
        $css = (string) file_get_contents($root . '/admin/assets/admin.css');
        foreach (['.analytics-event-grid', '.analytics-line-chart', '.analytics-hour-chart', '@media(max-width:1000px)'] as $needle) {
            $assert(str_contains($css, $needle), 'Events analytics CSS missing: ' . $needle);
        }
    });
}
