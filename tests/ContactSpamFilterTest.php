<?php

declare(strict_types=1);

use App\Services\ContactSpamFilter;

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
    });
}
