<?php

declare(strict_types=1);

use App\Services\ContactSpamFilter;
use App\Services\ContactSpamConfig;

function register_contact_spam_filter_tests(callable $test, callable $assert): void
{
    $test('static contact spam filter blocks supplied bot examples and preserves real inquiries', function () use ($assert): void {
        $filter = new ContactSpamFilter();

        $boilerplate = $filter->classify(
            'Thank you very much for your inquiry We appreciate you 429757 https://youtube.com faceapple !',
            'cwpsk@owhvwa.eb sxst w286...',
            'Thank you very much for your inquiry We appreciate you 9181331 https://youtube.com faceapple !'
        );
        $assert($boilerplate['isSpam'] === true, 'Known inquiry boilerplate must be spam.');
        $assert(in_array('known-bot-boilerplate', $boilerplate['reasons'], true), 'Boilerplate reason must be explicit.');

        $casino = $filter->classify(
            'Devinzc',
            'nraqk@snpgrl.uj nkaq u251...',
            'No luck? No problem. Reel Cash Vault wins fast. Spin once and believe! Play Now Here -> psee.io/8rgz3p'
        );
        $assert($casino['isSpam'] === true, 'Casino link promotion must be spam.');
        $assert(in_array('gambling-promotion', $casino['reasons'], true), 'Casino reason must be explicit.');

        $shortLegitimate = $filter->classify('Sigurd Gnezdilov', 'x-goner@seznam.cz', 'kolkik to stoji');
        $assert($shortLegitimate['isSpam'] === false, 'Short Czech inquiry must pass.');

        $wedding = $filter->classify(
            'Barbora Maršálková',
            'barbora.marsalkova@icloud.com',
            'Dobrý den, ozvěte se mi prosím ohledně informací k foto+video na svatbu 15.06.2027. Moc děkuji.'
        );
        $assert($wedding['isSpam'] === false, 'Detailed wedding inquiry must pass.');

        $venueLink = $filter->classify(
            'Jan Novák',
            'jan@example.cz',
            'Dobrý den, místo svatby je https://example.com/stodola a prosím o cenovou nabídku.'
        );
        $assert($venueLink['isSpam'] === false, 'A legitimate venue link alone must not block.');

        $custom = ContactSpamConfig::defaults();
        $custom['threshold'] = 4;
        $custom['weights']['link_in_message'] = 4;
        $custom['allowed_domains'] = ['trusted.example.com'];
        $filter = new ContactSpamFilter($custom);
        $blockedLink = $filter->classify('Jan Novák', 'jan@example.cz', 'Podívejte se na https://bad.example.net/nabidka');
        $assert($blockedLink['isSpam'] === true && $blockedLink['score'] === 4, 'Saved threshold and link weight must drive classification.');
        $allowedLink = $filter->classify('Jan Novák', 'jan@example.cz', 'Místo je https://photos.trusted.example.com/stodola');
        $assert($allowedLink['isSpam'] === false && $allowedLink['score'] === 0, 'Allowed domains and subdomains must not add link points.');

        $disabled = $custom;
        $disabled['enabled'] = false;
        $disabledResult = (new ContactSpamFilter($disabled))->classify('https://spam.example', 'junk@example.test xx', 'casino bonus play now https://bad.example');
        $assert($disabledResult === ['isSpam' => false, 'score' => 0, 'reasons' => []], 'Disabled filter must classify transparently without hidden partial rules.');
    });

    $test('contact spam configuration validates admin input and rejects corrupt storage', function () use ($assert): void {
        $post = [
            'contact_spam_enabled' => '1', 'contact_spam_threshold' => '9',
            'contact_spam_weight_known_phrase' => '8', 'contact_spam_weight_url_in_name' => '7',
            'contact_spam_weight_promotion_terms' => '6', 'contact_spam_weight_call_to_action' => '3',
            'contact_spam_weight_link_in_message' => '2', 'contact_spam_weight_contact_junk' => '2',
            'contact_spam_known_phrases' => "Bot phrase\nBOT PHRASE",
            'contact_spam_promotion_terms' => "casino\nbonus",
            'contact_spam_calls_to_action' => "click here\nplay now",
            'contact_spam_allowed_domains' => "Example.COM\nsub.example.org",
        ];
        $config = ContactSpamConfig::fromPost($post);
        $assert($config['enabled'] === true && $config['threshold'] === 9);
        $assert($config['known_phrases'] === ['bot phrase'], 'Lists must normalize and deduplicate.');
        $assert($config['allowed_domains'] === ['example.com', 'sub.example.org'], 'Domains must normalize.');
        $assert(ContactSpamConfig::fromStored(ContactSpamConfig::encode($config)) === $config, 'Stored config must round-trip exactly.');

        foreach (['{bad', json_encode(['version' => 99])] as $invalid) {
            $threw = false;
            try { ContactSpamConfig::fromStored((string)$invalid); } catch (InvalidArgumentException) { $threw = true; }
            $assert($threw, 'Corrupt or unsupported stored config must fail visibly.');
        }

        $post['contact_spam_threshold'] = '0';
        $threw = false;
        try { ContactSpamConfig::fromPost($post); } catch (InvalidArgumentException) { $threw = true; }
        $assert($threw, 'Out-of-range thresholds must be rejected.');
    });

    $test('contact spam admin and runtime wiring remain connected', function () use ($assert): void {
        $root = dirname(__DIR__);
        $settings = (string)file_get_contents($root . '/admin/views/settings/index.php');
        $controller = (string)file_get_contents($root . '/app/Controllers/SettingsController.php');
        $router = (string)file_get_contents($root . '/admin/index.php');
        $public = (string)file_get_contents($root . '/public/index.php');
        $migration = (string)file_get_contents($root . '/database/migrations/111_add_contact_spam_config.sql');
        foreach (['contact_spam_enabled', 'contact_spam_threshold', 'contact_spam_known_phrases', 'contact_spam_allowed_domains', '/admin/settings/spam-test'] as $needle) {
            $assert(str_contains($settings . $router, $needle), 'Missing antispam admin control: ' . $needle);
        }
        $assert(str_contains($controller, 'ContactSpamConfig::fromPost($_POST)') && str_contains($controller, 'function testSpam'));
        $assert(str_contains($public, "ContactSpamConfig::fromStored(\$site['contact_spam_config_json'] ?? null)"));
        $assert(str_contains($migration, 'contact_spam_config_json') && str_contains($migration, 'information_schema.columns'));
    });
}
