<?php

declare(strict_types=1);

namespace App\Content;

final class CustomerReviewDataset
{
    /**
     * Canonical customer-provided dataset exported on 2026-07-30.
     *
     * @return list<array{author_name:string,context_label:string,rating:int,review_text:string,sort_order:int,is_active:int}>
     */
    public static function all(): array
    {
        $reference = [
            ['Václav Konopka', 'Super domluva, milý a profesionální přístup k focení. Zdena nám nafotil svatební obřad s poobřadním focením v zámeckém parku. Z kvality fotek jsme nadšení a byly dodány dříve než jsme byli domluveni, takže velká spokojenost. Doporučuju.'],
            ['Ellie Lachnitová', 'Focení s Bannym mě moc bavilo. Super přístup, atmosféra i fotky. Rozhodně doporučuji! :)'],
            ['Michael Látal', 'Velmi super přístup i fotky.'],
            ['Jan Jüngling', 'Kluci jsou naprostí profesionálové jak Doyle a Bodie.. Jsou to opravdu přátelští borci, kteří vám udělají fotky a videa podle vašich představ a ještě líp. Za mě TOP ve svojím oboru a přál bych klukům aby to mohli dělat jako 100% práci a nemuseli makat ve fabrikách. Nejdůležitějsí je, že je to baví a zajímá. Navíc investovali do kvalitního vybavení. Lepší přístup jsem nezažil. Děkujeme, že jste mohli být na naší svatbě!M+H <3'],
            ['Roman Vrána', 'Rychlost, super provedení, nádherné fotky, které rychle dodal upravené 11/10, ještě jednou moc děkujeme <3'],
            ['Tomáš Pavlat', 'Profesionalita, ochota, vstřícnost a hlavně poradí i se scénou. Chceš mít profi foto a nevíš komu říct a poradit s kompozicí? Tak teď už víš.'],
            ['Lucie Fedorová', 'Lepšího fotografa neznám. :) Úžasný přístup, nádherný fotky. Doporučuji.'],
            ['Zuzana Horáková', 'Perfektní. Focení velmi příjemné a fotky krásné. Doporučuji všemi deseti.'],
            ['Martina Bobková', 'Super fotky a super pokec :) doporučuji moc moc.'],
            ['Jana Dudková', 'Moc doporučuji, pan fotograf nám vyčaroval ty nejkrásnější rodinné fotky i s dětmi co neměli zrovna fotící náladu. Profesionální přístup a špičková kvalita. Už se těším na další focení :)'],
            ['Martina Šinclová', 'Krásná vzpomínka na náš významný den děkujeme kluci :)'],
            ['Darča Polášková', 'Super domluva, zábava a vtípky při samotném focení, fotky skvělé a je jich hodně :3 Rozhodně doporučuji :)'],
            ['Dana Kneblová', 'Bylo to zabavne a uplne pohodove foceni ;)'],
            ['Veronika Boková', 'Domluva skvělá, focení zábavné, takže z člověka hned odpadne nervozita a fotky parádní...Doporučuji! :)'],
            ['Michal Kučera', 'Skvělá komunikace Super Fotky jen doporučuji'],
            ['Dominika Ječmínková', 'Skvělá komunikace,skvělé fotky ^^ Kdykoliv se jde domluvit, aby to vyhovovalo oběma stranám :) Můžu jen doporučit.'],
            ['Rose De Clermont', 'Velká spokojenost! Dvakrát nám focení zrušilo špatné počasí, ale díky dobré komunikaci s fotografem se focení fakt uskutečnilo :) I po focení je parádní domluva, když chci ještě nějakou další z fotek upravit. Doporučuji :)'],
            ['Aneta Jenešová', 'Super fotky a skvělá domluva s panem fotografem doporučuji :)'],
            ['Jenny Molatová', 'Super fotky ^^'],
            ['Martin Jeneš', 'Vřele doporučuji. Perfektní práce pana fotografa.'],
            ['Vojtěch Vrána', 'Čerstvý klip z nové desky WE ARE HERE Jsme natáčeli s RB - Videos & Photos, jsou to šikovní pánové.'],
            ['Veronika Šášková', 'Díky klukům z rbvideosphotos jsem měla možnost vyzkoušet si celodenní focení v pronajatém apartmánu, kde se vystřídalo několik modelek. Byla to fajn zkušenost :)'],
            ['Diana Smidová', 'Děkujeme za nejkrásnější vzpomínku! Super práce.'],
            ['Clarissa cosplayer', 'Today is nice day. I already have photos from the photo shoot. Thank you so much to the photographer for the photos and video.'],
            ['Tomáš Fyfty Fiala', 'A je to venku. Klip k novému songu SVĚTLO. Velké díky klukům z RB - Videos & Photos.'],
            ['Kateřina Václavková', 'Super :) :)'],
            ['Radka Polýnková', 'Moc děkujeme za úžasné fotky <3'],
        ];

        $google = [
            ['Sigurd Gnezdilov', "Byl jsem už na několika svatbách, kde kluci z RB Videos Photos fotili a natáčeli, a pokaždé to byla fakt pecka. Působili úplně v pohodě, nenápadně, ale zároveň byli vždycky u všech důležitých momentů.\n\nVýsledek byl pokaždé super - krásné fotky, povedené video a celkove atmosféra celé svatby.\n\nZa mě velká spokojenost a určitě doporučuju.\n\n- dělají i vidoklipy a krátkometrážní filmek"],
            ['Barbora Žouželová', 'Skvělá zkušenost z focení, super domluva, fotograf je sympaťák a je moc ochotný, umí poradit a jde vidět, že ví co dělá, doporučuju. Výsledné fotky top.'],
            ['Petr Šincl', 'Není co vytknout, profesionální a přátelský přístup, pozitivní nálada a energie při jejich práci. Výsledek je určitě na profesionální úrovni, nejsou to žádný béčka , baví se tím v podstatě celý život a jde to znát. Určitě doporučuji kombinaci videa i focení, jsou sehraná dvojka ;)'],
            ['Jan Jungling', 'Kluci jsou naprostí profesionálové jak Bodie a Doyle.. 😄 Jsou to opravdu přátelští borci, kteří vám udělají fotky a videa podle vašich představ a ještě líp. Za mě TOP ve svojím oboru a přál bych klukům aby to mohli dělat jako 100% práci a …'],
            ['Мария Петрова', 'Perfektní ✨'],
            ['Ivana Nevěřilová', 'Kluci nás provázeli celým naším svatebním dnem a byla to s nimi naprostá bomba 🤩 Oba jsou neskutečně fajn a příjemní a jsme moc rádi, že s námi byli v náš velký den právě oni. Jejich práce je naprosto dokonalá jak v průběhu dne, tak pak i …'],
            ['NOMIC Novak', '​Banny (fotograf) a Tomáš (kameraman) jsou nejen naši skvělí kamarádi, ale hlavně naprostí profesionálové ve svém oboru. Mít je na svatbě byla ta nejlepší volba. Celý den s nimi byl naprosto uvolněný, plný smíchu a pohody, což se neskutečně …'],
            ['Marie Červenková', 'Kluci nám fotili a natáčeli svatbu a výsledek je prostě nádherný - máme přesně to, co jsme si vysnili. Celý náš den navíc díky nim proběhl v naprosté pohodě a skvělé náladě. Hodně jsme se nasmáli a díky jejich odlehčenému přístupu z nás okamžitě opadl všechen stres. Ještě jednou vám moc děkujeme!'],
            ['p.k.weddings Kolářová', 'Kluky z RB Videos Photos znám osobně i profesně jako svatební koordinátorka. Spolupracuje se s nimi naprosto skvěle a můžu je s čistým srdcem doporučit. Na place jsou to maximální profesionálové. Drží se harmonogramu, výborně …'],
            ['Pavlína M', 'Nemohli jsme si vybrat líp ✨ Kluci byli od první chvíle strašně fajn, přátelští a po celý den s nimi byla sranda, takže z nás opadl všechen stres. Zároveň byli naprostí profíci a výsledek …'],
            ['Vít Novák', 'Super kluk, poradil se vším. Jaké pózi kam se postavit atd. Takže nemusíte být modelky a modelové poradí vám bez problému. A co je super tak že u toho focení i sranda. Jo a super domluva. A ty fotky jsou parádní'],
            ['Viera Sedlackova', 'Nám dala pět hvězdiček na google recenzích. Moc děkujeme!'],
        ];

        $rows = [];
        foreach ($reference as $review) {
            $rows[] = self::row($review[0], 'RB reference', $review[1], count($rows) + 1);
        }
        foreach ($google as $review) {
            $rows[] = self::row($review[0], 'Google recenze · 5/5', $review[1], count($rows) + 1);
        }

        return $rows;
    }

    private static function row(string $author, string $context, string $text, int $order): array
    {
        return [
            'author_name' => $author,
            'context_label' => $context,
            'rating' => 5,
            'review_text' => $text,
            'sort_order' => $order,
            'is_active' => 1,
        ];
    }
}
