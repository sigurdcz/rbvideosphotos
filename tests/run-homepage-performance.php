<?php

declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/HomepagePerformanceTest.php';
require __DIR__ . '/ResponsiveHeroVideoTest.php';
$tests = [];
$test = static function (string $name, callable $callback) use (&$tests): void { $tests[] = [$name, $callback]; };
$assert = static function (bool $value, string $message = 'Assertion failed'): void { if (!$value) throw new RuntimeException($message); };
register_homepage_performance_tests($test, $assert);
register_responsive_hero_video_tests($test, $assert);
$failures = 0;
foreach ($tests as [$name, $callback]) {
    try { $callback(); echo "PASS: $name\n"; }
    catch (Throwable $e) { $failures++; echo "FAIL: $name: {$e->getMessage()}\n"; }
}
echo count($tests) . " tests, $failures failures\n";
exit($failures === 0 ? 0 : 1);
