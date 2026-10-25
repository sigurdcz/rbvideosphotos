<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/ContactSpamFilterTest.php';
require __DIR__ . '/ContactSpamNotificationTest.php';

$failures = 0;
$total = 0;
$test = static function (string $name, callable $callback) use (&$failures, &$total): void {
    $total++;
    try {
        $callback();
        echo "PASS: $name\n";
    } catch (Throwable $e) {
        $failures++;
        echo "FAIL: $name: {$e->getMessage()}\n";
    }
};
$assert = static function (bool $condition, string $message = 'Assertion failed'): void {
    if (!$condition) throw new RuntimeException($message);
};
register_contact_spam_filter_tests($test, $assert);
register_contact_spam_notification_tests($test, $assert);

if (in_array('--mysql', $argv, true)) {
    $test('migration 112 extends stored profiles once and preserves settings', static function () use ($assert): void {
        // Explicitly local, disposable TEMPORARY table; never load deployment credentials.
        $db = new PDO('mysql:host=127.0.0.1;dbname=mysql;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db->exec('CREATE TEMPORARY TABLE sites (id INT PRIMARY KEY, contact_spam_config_json LONGTEXT NULL)');
        $custom = App\Services\ContactSpamConfig::defaults();
        $custom['known_phrases'] = ['custom administrator phrase'];
        $custom['enabled'] = false;
        $custom['notify_recipients'] = true;
        $custom['threshold'] = 19;
        $custom['weights']['known_phrase'] = 12;
        $insert = $db->prepare('INSERT INTO sites VALUES (?, ?)');
        $insert->execute([1, App\Services\ContactSpamConfig::encode($custom)]);
        $insert->execute([2, null]);
        $insert->execute([3, '']);
        $sql = file_get_contents(dirname(__DIR__) . '/database/migrations/112_extend_contact_spam_phrases.sql');
        $db->exec($sql);
        $first = $db->query('SELECT contact_spam_config_json FROM sites WHERE id = 1')->fetchColumn();
        $db->exec($sql);
        $second = $db->query('SELECT contact_spam_config_json FROM sites WHERE id = 1')->fetchColumn();
        $assert($first === $second, 'Repeated migration must not duplicate phrases.');
        $migrated = App\Services\ContactSpamConfig::fromStored($second);
        $expected = $custom;
        $expected['known_phrases'] = array_merge($custom['known_phrases'], array_slice(App\Services\ContactSpamConfig::defaults()['known_phrases'], 1));
        $assert($migrated === $expected, 'Custom lists, switches, weights and threshold must survive.');
        $assert($db->query('SELECT contact_spam_config_json FROM sites WHERE id = 2')->fetchColumn() === null, 'NULL stays on defaults.');
        $assert($db->query('SELECT contact_spam_config_json FROM sites WHERE id = 3')->fetchColumn() === '', 'Empty default profile stays empty.');
        $migrated['enabled'] = true;
        $migrated['threshold'] = 7;
        $assert((new App\Services\ContactSpamFilter($migrated))->classify('Billyvep', 'bot@example.test', 'PXQRrbvideosphotos.czPXQR')['isSpam'], 'Migrated profile must detect signature.');
    });
}
echo "$total tests, $failures failures\n";
exit($failures === 0 ? 0 : 1);
