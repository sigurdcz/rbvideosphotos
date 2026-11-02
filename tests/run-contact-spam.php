<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/ContactSpamFilterTest.php';
require __DIR__ . '/ContactSpamNotificationTest.php';
require __DIR__ . '/ContactLegitimateCorpusTest.php';

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
register_contact_legitimate_corpus_tests($test, $assert);

if (in_array('--mysql', $argv, true)) {
    $test('migrations 112 through 118 extend stored profiles once and preserve settings', static function () use ($assert): void {
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
        // Exercise 116 against a genuinely old profile and an administrator-owned rule.
        $legacyProfile = $migrated;
        unset($legacyProfile['campaign_rules']);
        $db->prepare('UPDATE sites SET contact_spam_config_json=? WHERE id=1')->execute([json_encode($legacyProfile, JSON_THROW_ON_ERROR)]);
        $custom['campaign_rules'] = ['custom first && custom second'];
        $insert->execute([4, App\Services\ContactSpamConfig::encode($custom)]);
        $sql116 = file_get_contents(dirname(__DIR__) . '/database/migrations/116_add_combined_spam_campaign_rules.sql');
        $db->exec($sql116);
        $first116 = $db->query('SELECT contact_spam_config_json FROM sites WHERE id = 1')->fetchColumn();
        $customFirst116 = $db->query('SELECT contact_spam_config_json FROM sites WHERE id = 4')->fetchColumn();
        $db->exec($sql116);
        $assert($first116 === $db->query('SELECT contact_spam_config_json FROM sites WHERE id = 1')->fetchColumn(), 'Migration 116 must be idempotent for old profiles.');
        $assert($customFirst116 === $db->query('SELECT contact_spam_config_json FROM sites WHERE id = 4')->fetchColumn(), 'Custom campaign rules must not duplicate either.');
        // Freeze migration 116, rather than accidentally expecting future defaults from it.
        $rules116 = [
            'qualifies them and books the appointment && blue phone icon && 14 days',
            'read the news && per month && 50,000,000',
            'loveshop && shop1-biz && nft',
            'aiflipdomains && lifetime access && one-time',
            'verstorbenen mandanten && 50-50 && millionen us-dollar',
        ];
        $expected['campaign_rules'] = $rules116;
        $migrated = App\Services\ContactSpamConfig::fromStored($first116);
        $assert($migrated === $expected, 'Migration 116 must add only campaign rules to legacy profiles.');
        $customExpected = $custom;
        $customExpected['campaign_rules'] = array_merge($custom['campaign_rules'], $rules116);
        $assert(App\Services\ContactSpamConfig::fromStored($customFirst116) === $customExpected, 'Existing custom campaign rules and all settings must survive.');
        $rules117 = ['драгон мани && пополнение и вывод && подробно разобрано здесь', 'roberthip && kam dashur të di çmimin tuaj'];
        $bareProfile = $custom;
        unset($bareProfile['campaign_rules']);
        $insert->execute([5, json_encode($bareProfile, JSON_THROW_ON_ERROR)]);
        $sql117 = file_get_contents(dirname(__DIR__) . '/database/migrations/117_add_dragon_and_roberthip_campaign_rules.sql');
        $db->exec($sql117);
        $first117 = $db->query('SELECT contact_spam_config_json FROM sites WHERE id = 1')->fetchColumn();
        $customFirst117 = $db->query('SELECT contact_spam_config_json FROM sites WHERE id = 4')->fetchColumn();
        $db->exec($sql117);
        $assert($first117 === $db->query('SELECT contact_spam_config_json FROM sites WHERE id = 1')->fetchColumn(), '117 must be idempotent.');
        $assert($customFirst117 === $db->query('SELECT contact_spam_config_json FROM sites WHERE id = 4')->fetchColumn(), '117 must not duplicate custom rules.');
        $expected['campaign_rules'] = array_merge($expected['campaign_rules'], $rules117);
        $customExpected['campaign_rules'] = array_merge($customExpected['campaign_rules'], $rules117);
        $migrated = App\Services\ContactSpamConfig::fromStored($first117);
        $assert($migrated === $expected && App\Services\ContactSpamConfig::fromStored($customFirst117) === $customExpected, '117 must preserve every administrator setting and earlier rule.');
        $bareProfile['campaign_rules'] = $rules117;
        $expectedBare = App\Services\ContactSpamConfig::fromStored(json_encode($bareProfile, JSON_THROW_ON_ERROR));
        $assert(App\Services\ContactSpamConfig::fromStored($db->query('SELECT contact_spam_config_json FROM sites WHERE id = 5')->fetchColumn()) === $expectedBare, '117 must also initialize its own field on a bare older profile.');
        $rules118 = ['roberthip && achọrọ m ịmara ọnụahịa gị', 'roberthip && meg akartam tudni az árát', 'it handles questions && books the appointment && blue phone icon && 14 days'];
        $insert->execute([6, json_encode(array_diff_key($custom, ['campaign_rules' => true]), JSON_THROW_ON_ERROR)]);
        $priorDefaults = App\Services\ContactSpamConfig::defaults();
        $priorDefaults['campaign_rules'] = array_values(array_diff($priorDefaults['campaign_rules'], $rules118));
        $insert->execute([7, App\Services\ContactSpamConfig::encode($priorDefaults)]);
        $sql118 = file_get_contents(dirname(__DIR__) . '/database/migrations/118_add_archive_campaign_variants.sql');
        $db->exec($sql118);
        $first118 = $db->query('SELECT id, contact_spam_config_json FROM sites ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
        $db->exec($sql118);
        $assert($first118 === $db->query('SELECT id, contact_spam_config_json FROM sites ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR), '118 must be idempotent for all profiles.');
        $expected['campaign_rules'] = array_merge($expected['campaign_rules'], $rules118);
        $customExpected['campaign_rules'] = array_merge($customExpected['campaign_rules'], $rules118);
        $migrated = App\Services\ContactSpamConfig::fromStored($first118[1]);
        $assert($migrated === $expected && App\Services\ContactSpamConfig::fromStored($first118[4]) === $customExpected, '118 must preserve custom rules, switches, weights and threshold.');
        $bare118 = $custom;
        $bare118['campaign_rules'] = $rules118;
        $assert(App\Services\ContactSpamConfig::fromStored($first118[6]) === $bare118, '118 must initialize only its own rules on bare profiles.');
        $assert($db->query('SELECT contact_spam_config_json FROM sites WHERE id = 2')->fetchColumn() === null, 'NULL stays on defaults.');
        $assert($db->query('SELECT contact_spam_config_json FROM sites WHERE id = 3')->fetchColumn() === '', 'Empty default profile stays empty.');
        $migrated['enabled'] = true;
        $migrated['threshold'] = 7;
        $assert((new App\Services\ContactSpamFilter($migrated))->classify('Billyvep', 'bot@example.test', 'PXQRrbvideosphotos.czPXQR')['isSpam'], 'Migrated profile must detect signature.');
        $assert((new App\Services\ContactSpamFilter($migrated))->classify('Keenan', 'hello@firstleadgen.com', "You're reading this because I just used your contact form.")['isSpam'], 'Migrated profile must detect First Lead Gen.');
        $assert((new App\Services\ContactSpamFilter($migrated))->classify('Marie06', 'bot@example.fr', 'Voici un article tres interessant a lire pendant son temps libre.')['isSpam'], 'Migrated profile must detect French article campaign.');
        $assert((new App\Services\ContactSpamFilter($migrated))->classify('Николай', '4mk@mlzwq.ru', 'Расширенная информация в отношении физ лиц, ИП, организаций')['isSpam'], 'Migrated profile must detect personal data sales campaign.');
        $migratedFilter = new App\Services\ContactSpamFilter($migrated);
        foreach (['Ndewo, achọrọ m ịmara ọnụahịa gị.', 'Szia, meg akartam tudni az árát.'] as $variant) {
            $assert($migratedFilter->classify('Roberthip', 'any@example.test', $variant)['isSpam']);
        }
        $assert($migratedFilter->classify('Lesli', '', 'It handles questions and books the appointment. Tap the blue phone icon. You get 14 days free. https://boostleadgen.com')['isSpam']);
        $archiveSamples = json_decode(file_get_contents(__DIR__ . '/fixtures/contact-archive-spam-excerpts.json'), true, 32, JSON_THROW_ON_ERROR);
        $archiveFilter = new App\Services\ContactSpamFilter(App\Services\ContactSpamConfig::fromStored($first118[7]));
        foreach ($archiveSamples as $sample) {
            $assert($archiveFilter->classify($sample['name'], 'sender@example.test', $sample['message'])['isSpam'], 'Migrated archive miss #' . $sample['id']);
        }
        $assert($migratedFilter->classify('Roberthip', 'any@example.test', 'Hi, kam dashur të di çmimin tuaj')['isSpam']);
        $assert($migratedFilter->classify('JamesTralf', 'any@example.test', 'Пополнение и вывод в Драгон мани. Подробно разобрано здесь: https://dragon-pay.org')['isSpam']);
        $corpus = json_decode(file_get_contents(__DIR__ . '/fixtures/contact-legitimate-1000.json'), true, 32, JSON_THROW_ON_ERROR);
        foreach ($corpus as $row) {
            $assert(!$migratedFilter->classify($row['name'], $row['contact'], $row['message'])['isSpam'], 'Migrated-profile false positive: ' . $row['id']);
        }
    });
}
echo "$total tests, $failures failures\n";
exit($failures === 0 ? 0 : 1);
