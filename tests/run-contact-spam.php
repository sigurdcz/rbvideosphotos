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
    $test('migrations 112 through 115 extend stored profiles once and preserve settings', static function () use ($assert): void {
        App\Core\Env::load(dirname(__DIR__) . '/.env');
        $assert(App\Core\Env::get('DB_HOST') === '127.0.0.1', 'Migration tests require an explicitly local DB host.');
        // TEMPORARY sites shadows the real table for this connection only.
        $db = new PDO(
            'mysql:host=127.0.0.1;port=' . App\Core\Env::get('DB_PORT', '3306') . ';dbname=' . App\Core\Env::get('DB_NAME') . ';charset=utf8mb4',
            App\Core\Env::get('DB_USER'), App\Core\Env::get('DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $db->exec('CREATE TEMPORARY TABLE sites (id INT PRIMARY KEY, contact_spam_config_json LONGTEXT NULL)');
        $custom = App\Services\ContactSpamConfig::defaults();
        $custom['known_phrases'] = ['custom administrator phrase'];
        $custom['enabled'] = false;
        $custom['notify_recipients'] = true;
        $custom['threshold'] = 19;
        $custom['weights']['known_phrase'] = 12;
        $custom['allowed_domains'] = ['example.com'];
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
        // Freeze the historical migration contract; future defaults can grow.
        $expected['known_phrases'] = array_merge($custom['known_phrases'], [
            'pxqrrbvideosphotos.czpxqr', 'предлагаем кассовые чеки', 'отчетные закрывающие документы',
        ]);
        $assert($migrated === $expected, 'Custom lists, switches, weights and threshold must survive.');
        $sql113 = file_get_contents(dirname(__DIR__) . '/database/migrations/113_add_first_lead_gen_spam_phrase.sql');
        $db->exec($sql113);
        $first113 = $db->query('SELECT contact_spam_config_json FROM sites WHERE id = 1')->fetchColumn();
        $db->exec($sql113);
        $second113 = $db->query('SELECT contact_spam_config_json FROM sites WHERE id = 1')->fetchColumn();
        $assert($first113 === $second113, 'Migration 113 must also be idempotent.');
        $expected['known_phrases'][] = "you're reading this because i just used your contact form";
        $migrated = App\Services\ContactSpamConfig::fromStored($second113);
        $assert($migrated === $expected, 'Migration 113 must only append its signature and preserve all custom settings.');
        $sql114 = file_get_contents(dirname(__DIR__) . '/database/migrations/114_add_french_article_spam_phrase.sql');
        $db->exec($sql114);
        $first114 = $db->query('SELECT contact_spam_config_json FROM sites WHERE id = 1')->fetchColumn();
        $db->exec($sql114);
        $second114 = $db->query('SELECT contact_spam_config_json FROM sites WHERE id = 1')->fetchColumn();
        $assert($first114 === $second114, 'Migration 114 must be idempotent.');
        $expected['known_phrases'][] = 'voici un article tres interessant a lire pendant son temps libre';
        $migrated = App\Services\ContactSpamConfig::fromStored($second114);
        $assert($migrated === $expected, 'Migration 114 must preserve all custom settings and previous phrases.');
        $sql115 = file_get_contents(dirname(__DIR__) . '/database/migrations/115_add_personal_data_spam_phrase.sql');
        $db->exec($sql115);
        $first115 = $db->query('SELECT contact_spam_config_json FROM sites WHERE id = 1')->fetchColumn();
        $db->exec($sql115);
        $second115 = $db->query('SELECT contact_spam_config_json FROM sites WHERE id = 1')->fetchColumn();
        $assert($first115 === $second115, 'Migration 115 must be idempotent.');
        $expected['known_phrases'][] = 'расширенная информация в отношении физ лиц';
        $migrated = App\Services\ContactSpamConfig::fromStored($second115);
        $assert($migrated === $expected, 'Migration 115 must preserve custom settings and all previous phrases.');
        $assert($db->query('SELECT contact_spam_config_json FROM sites WHERE id = 2')->fetchColumn() === null, 'NULL stays on defaults.');
        $assert($db->query('SELECT contact_spam_config_json FROM sites WHERE id = 3')->fetchColumn() === '', 'Empty default profile stays empty.');
        $migrated['enabled'] = true;
        $migrated['threshold'] = 7;
        $assert((new App\Services\ContactSpamFilter($migrated))->classify('Billyvep', 'bot@example.test', 'PXQRrbvideosphotos.czPXQR')['isSpam'], 'Migrated profile must detect signature.');
        $assert((new App\Services\ContactSpamFilter($migrated))->classify('Keenan', 'hello@firstleadgen.com', "You're reading this because I just used your contact form.")['isSpam'], 'Migrated profile must detect First Lead Gen.');
        $assert((new App\Services\ContactSpamFilter($migrated))->classify('Marie06', 'bot@example.fr', 'Voici un article tres interessant a lire pendant son temps libre.')['isSpam'], 'Migrated profile must detect French article campaign.');
        $assert((new App\Services\ContactSpamFilter($migrated))->classify('Николай', '4mk@mlzwq.ru', 'Расширенная информация в отношении физ лиц, ИП, организаций')['isSpam'], 'Migrated profile must detect personal data sales campaign.');
    });
}
echo "$total tests, $failures failures\n";
exit($failures === 0 ? 0 : 1);
