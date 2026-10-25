<?php

declare(strict_types=1);

use App\Services\ContactSpamFilter;
use App\Services\ContactSpamConfig;

function register_contact_spam_filter_tests(callable $test, callable $assert): void
{
    $test('First Lead Gen sales pitch is spam while ordinary English inquiries pass', function () use ($assert): void {
        $message = <<<'MESSAGE'
Hi team,

You're reading this because I just used your contact form. Imagine I'm a new customer: how long before you call me?

A study of 433 companies found that only 7% answered a web form within 5 minutes. More than half hadn't replied after 5 business days. We put you in that 7%.

The moment someone fills in a form on your site, we call them in about 30 seconds, and you decide who they talk to:

1. You or your staff. We ring your phone and connect you while they're still on your website. If nobody answers, our AI takes the call.

2. Our AI, on every call. It handles questions, qualifies them and books the appointment, 24/7, then sends you a full transcript.

Hear it for yourself: go to https://firstleadgen.com, tap the blue phone icon in the bottom corner and enter your number. It calls you. You'll hear the AI option.

Our Basic plan needs no credit card. The first 14 days are free. A real person helps you get set up.

Talk soon,
Keenan, First Lead Gen

Not interested? Opt out here: https://firstleadgen.com/unsubscribe.aspx?d=rbvideosphotos.cz
MESSAGE;
        $filter = new ContactSpamFilter();
        $result = $filter->classify('Keenan', 'hello@firstleadgen.com', $message);
        $assert($result['isSpam'] && $result['score'] === 10, 'Supplied campaign must score 8 for signature and 2 for URL.');
        $assert(in_array('known-bot-boilerplate', $result['reasons'], true), 'Campaign signature reason must be explicit.');
        $encoded = str_replace("You're reading this because I just used your contact form", "YOU&#039;RE reading this\n because I just used your contact form", $message);
        $assert($filter->classify('Keenan', 'hello@firstleadgen.com', $encoded)['isSpam'], 'Entity, case and whitespace normalization must preserve detection.');
        foreach ([
            'Hi team, can you photograph our corporate event in Le Grand-Quevilly? Please call me. Our website is https://firstleadgen.com.',
            'I just used your contact form to ask about family photos. Could you send your pricing?',
            'Can you photograph our AI conference? It runs 24/7 and includes a demo with a 14 day free trial.',
        ] as $inquiry) {
            $assert(!$filter->classify('Keenan', 'hello@firstleadgen.com', $inquiry)['isSpam'], 'Name, domain, English and AI vocabulary alone must not block.');
        }
        $disabled = ContactSpamConfig::defaults();
        $disabled['enabled'] = false;
        $assert(!(new ContactSpamFilter($disabled))->classify('Keenan', 'hello@firstleadgen.com', $message)['isSpam'], 'Disabled filter must respect the new signature.');
    });

    $test('new bot signatures block Billyvep and receipt advertising without language bans', function () use ($assert): void {
        $filter = new ContactSpamFilter();
        $samples = [
            ['Billyvep', 'rcrxo@mkrqxw.ms tenm q185...', 'Gunnyicz PXQRrbvideosphotos.czPXQR lxxiaya rdezpqsyl kcjz www.yahoo.com/tr1', 12],
            ['Кира', '3lu@nnpzl.ru', '!!! Повторно, вылечили TG и обратную связь. Предлагаем кассовые чеки, отчетные закрывающие документы для бухучета и налоговой. По любым интересующим Вас товарам или услугам, суммам. В некоторых случаях возможно задними числами. Легально - чеки можно проверить через официальный портал ФНС. Без предоплат, гарантия 3 года. Подробнее и связь: https://ru-inform-gos.ru/tg-receipts-ezqGsHnWhE', 10],
        ];
        foreach ($samples as [$name, $contact, $message, $score]) {
            $result = $filter->classify($name, $contact, $message);
            $assert($result['isSpam'] && $result['score'] === $score, 'Supplied spam must reach its expected score.');
            $assert(in_array('known-bot-boilerplate', $result['reasons'], true), 'Signature reason must be visible.');
        }
        foreach ([
            ['Кира', '3lu@nnpzl.ru', 'Здравствуйте! Хотим заказать семейную фотосессию. Сколько стоит?'],
            ['Jan Novák', 'jan@example.cz telefon +420 777 123 456', 'Dobrý den, rodinné focení prosím. Inspirace: www.yahoo.com/tr1'],
            ['Jan Novák', 'jan@example.cz', 'Prosím o fakturu a účetní doklady za rodinné focení.'],
            ['Кира', '3lu@nnpzl.ru', 'Нужен фотограф для семьи и чек за фотосессию.'],
        ] as [$name, $contact, $message]) {
            $assert(!$filter->classify($name, $contact, $message)['isSpam'], 'Language, contact, link or receipt request alone must pass.');
        }
        $disabled = ContactSpamConfig::defaults();
        $disabled['enabled'] = false;
        $assert(!(new ContactSpamFilter($disabled))->classify(...array_slice($samples[0], 0, 3))['isSpam'], 'New signatures respect the disabled switch.');
    });

    $test('static contact spam filter blocks supplied bot examples and preserves real inquiries', function () use ($assert): void {
        $filter = new ContactSpamFilter();

        $boilerplate = $filter->classify(
            'Thank you very much for your inquiry We appreciate you 429757 https://youtube.com faceapple !',
            'cwpsk@owhvwa.eb sxst w286...',
            'Thank you very much for your inquiry We appreciate you 9181331 https://youtube.com faceapple !'
        );
        $assert($boilerplate['isSpam'] === true, 'Known inquiry boilerplate must be spam.');
        $assert(in_array('known-bot-boilerplate', $boilerplate['reasons'], true), 'Boilerplate reason must be explicit.');

        $russianCasino = $filter->classify(
            'JamesTralf',
            'edrmx@ksrkjо.th hmpb e181...',
            'Здарова! Коротко расскажу про казино Драгон мани. Подробности — <a href=https://dragonmoney-obzor.org>https://dragonmoney-obzor.org</a>. Играть стоит в удовольствие.'
        );
        $assert($russianCasino['isSpam'] === true, 'Russian casino promotion with a link must be spam.');
        $assert(in_array('gambling-promotion', $russianCasino['reasons'], true), 'Russian casino promotion reason must be explicit.');

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
            'contact_spam_enabled' => '1', 'contact_spam_notify_recipients' => '1', 'contact_spam_threshold' => '9',
            'contact_spam_weight_known_phrase' => '8', 'contact_spam_weight_url_in_name' => '7',
            'contact_spam_weight_promotion_terms' => '6', 'contact_spam_weight_call_to_action' => '3',
            'contact_spam_weight_link_in_message' => '2', 'contact_spam_weight_contact_junk' => '2',
            'contact_spam_known_phrases' => "Bot phrase\nBOT PHRASE",
            'contact_spam_promotion_terms' => "casino\nbonus",
            'contact_spam_calls_to_action' => "click here\nplay now",
            'contact_spam_allowed_domains' => "Example.COM\nsub.example.org",
        ];
        $config = ContactSpamConfig::fromPost($post);
        $assert($config['enabled'] === true && $config['notify_recipients'] === true && $config['threshold'] === 9);
        $assert($config['known_phrases'] === ['bot phrase'], 'Lists must normalize and deduplicate.');
        $assert($config['allowed_domains'] === ['example.com', 'sub.example.org'], 'Domains must normalize.');
        $assert(ContactSpamConfig::fromStored(ContactSpamConfig::encode($config)) === $config, 'Stored config must round-trip exactly.');
        $legacy = $config;
        unset($legacy['notify_recipients']);
        $assert(ContactSpamConfig::fromStored(json_encode($legacy, JSON_THROW_ON_ERROR))['notify_recipients'] === false, 'Older version 1 configuration must keep notifications disabled.');

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
        foreach (['contact_spam_enabled', 'contact_spam_notify_recipients', 'contact_spam_threshold', 'contact_spam_known_phrases', 'contact_spam_allowed_domains', '/admin/settings/spam-test'] as $needle) {
            $assert(str_contains($settings . $router, $needle), 'Missing antispam admin control: ' . $needle);
        }
        $assert(str_contains($controller, 'ContactSpamConfig::fromPost($_POST)') && str_contains($controller, 'function testSpam'));
        $assert(str_contains($public, "ContactSpamConfig::fromStored(\$site['contact_spam_config_json'] ?? null)"));
        $assert(str_contains($public, "!empty(\$spamConfig['notify_recipients'])"));
        $assert(str_contains($migration, 'contact_spam_config_json') && str_contains($migration, 'information_schema.columns'));
    });
}
