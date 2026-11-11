<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/TrackingEventAnalyticsTest.php';

$tests = [];
$test = static function (string $name, callable $callback) use (&$tests): void {
    $tests[] = [$name, $callback];
};
$assert = static function (bool $condition, string $message = 'Assertion failed'): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

register_tracking_event_analytics_tests($test, $assert);
$failures = 0;
foreach ($tests as [$name, $callback]) {
    try {
        $callback();
        echo "PASS {$name}\n";
    } catch (Throwable $e) {
        $failures++;
        echo "FAIL {$name}: {$e->getMessage()}\n";
    }
}
echo count($tests) . ' tests, ' . $failures . " failures\n";
exit($failures === 0 ? 0 : 1);
