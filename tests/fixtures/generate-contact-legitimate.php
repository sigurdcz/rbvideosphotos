<?php
declare(strict_types=1);

// Synthetic messages, never submissions. 20 categories x 5 reviewed templates
// x 10 concrete contexts = 1,000 distinct inquiry bodies. No production data.
$templates = [
    'formal' => [
        'Vážený týme, rádi bychom Vás požádali o cenovou nabídku svatebního fotografování dne {date} v {venue}.',
        'Dobrý den, dovolujeme si poptat Vaše fotografické služby. Akce se koná {date}, místo {venue}. Prosíme o dostupnost a podmínky.',
        'Dobrý den, zaujalo nás Vaše portfolio. Bylo by možné sjednat osobní konzultaci v {city} k termínu {date}? Děkujeme.',
        'Prosím o informaci, zda poskytujete foto a video v {venue} dne {date}. Uvítáme orientační kalkulaci včetně dopravy.',
        'Ráda bych ověřila možnost rezervace termínu {date} pro rodinné portréty v {city}. Předem děkuji za odpověď.',
    ],
    'casual' => [
        'Ahoj, líbí se nám vaše fotky. Máte {date} čas na focení v {city}?',
        'Zdravím, chystáme malou svatbu v {venue} dne {date}. Mohli byste nám ji nafotit?',
        'Ahojky, šlo by si s vámi zavolat? Řešíme svatbu {date}, místo {venue}.',
        'Dobrý den :) V {city} budeme {date} celá rodina pohromadě. Máte čas nás vyfotit?',
        'Díky za krásné portfolio! Hledáme kameramana na {date} do {venue}. Ozvete se?',
    ],
    'ascii' => [
        'dobry den mame zajem o foto a video {date} v {city}, mate volno?',
        'kolik by stalo foceni rodiny v {venue} {date}? diky',
        'cau, svatba {date}, {city}, asi 40 hostu, slo by to?',
        'potrebujem kameramana {date} do {venue}. poslete prosim nabidku',
        'vas web je super. muzem prijit na schuzku v {city} pred {date}?',
    ],
    'typos' => [
        'dobri den chtela bich foceni v {city} {date}, jake sou ceny?',
        'mate prosim volno na svadbu {date} v {venue}? dekujuu',
        'ahoj prosiim muzete nam udelad fotky {date} {city}, sme 4',
        'chtel bysem se zeptad na video ze svatby {date} {venue}',
        'kolyk stoji rodine fotceni {city} {date}? pisu s mobilu dik',
    ],
    'short' => [
        'cena {city}?', '{date} volno?', 'rodinka {city} kolik?',
        'foto+video {date}?', 'portrét {city} prosím?',
    ],
    'phone' => [
        'Prosím zavolejte mi, potřebuji fotografa v {city} na {date}.',
        'Nemám teď čas psát podrobnosti. Můžete mi poslat SMS ohledně focení {date} v {venue}?',
        'Dobrý den, preferuji odpověď e-mailem. Jde o termín {date}, {city}.',
        'Ahoj, telefon mívám vypnutý, můžeme se domluvit přes WhatsApp na focení v {venue} {date}?',
        'Kdy se vám hodí zavolat? Poptávám společné foto a video {date}, {city}.',
    ],
    'wedding' => [
        'Svatba {date} v {venue}, obřad 13:00, cca 55 hostů. Umíte zůstat až do prvního tance?',
        'Budeme se brát {date} v {city}. Potřebujeme jen obřad a společné fotky, ne celý den.',
        'Máte {date} v {venue} možnost dvou fotografů? Chceme zachytit současně přípravy obou.',
        'Poptávám svatbu {date} v {city}, ale termín ještě možná posuneme. Jak funguje změna rezervace?',
        'Měli jsme mít svatbu {date} v {venue}, fotograf nám odřekl. Nemáte prosím volno?',
    ],
    'family' => [
        'Máme malé děti a psa, zvládneme společné focení v {city} {date}?',
        'Těhotenské focení {date} v {venue}, jsem poprvé před objektivem. Poradíte s oblečením?',
        'Dobrý den, chtěla bych poukaz pro babičku v {city}, předání {date}. Jak dlouho platí?',
        'Focení miminka doma {date}, {city}. Je možné přijet k nám a nebudit ho?',
        'Jsme dvě maminky a tři děti, rády bychom fotky v {venue} dne {date}.',
    ],
    'business' => [
        'Dobrý den, hledáme firemní portréty pro 12 kolegů {date} v {city}, lze fotit v kanceláři?',
        'Potřebujeme produktové fotografie a krátké reels z provozu {date} v {venue}.',
        'Pořádáme benefiční večer {date}, {city}. Můžete vystavit fakturu na spolek?',
        'Poptáváme dokumentaci školního plesu v {venue} dne {date}. Souhlasy rodičů zajistíme.',
        'Firemní akce {date} v {city}: potřebujeme fotky na web i několik na tisk, v jakém rozlišení je dodáte?',
    ],
    'own_website_links' => [
        'Dobrý den, na {url} se mi líbí vaše práce. Mohu poprosit o podobné focení {date} v {city}?',
        'Koukám na {url} a nerozumím rozdílu balíčků. Co byste doporučili na svatbu {date} v {venue}?',
        'Na telefonu mi {url} nejde otevřít. Pošlete mi prosím informace o focení v {city} na {date}?',
        'Je cena uvedená na {url} aktuální i pro {city} a termín {date}?',
        'Posílám odkaz na váš web {url}, přesně tenhle styl bychom chtěli pro {venue} {date}.',
    ],
    'external_links' => [
        'Tady se bude konat svatba: {url}. Můžete {date} přijet do {city}?',
        'Náš firemní web je {url}, potřebujeme nové fotografie {date} v {city}.',
        'Inspirace na barevnost: {url}. Nechceme kopii, jen podobnou náladu v {venue} dne {date}.',
        'Místo {venue} má podrobnosti tady {url}. Jakou dopravu budete účtovat {date}?',
        'Prosím o fotky našeho týmu v {city} dne {date}. Odkaz pro představu: {url}.',
    ],
    'video' => [
        'Je na {date} do {city} možné jen svatební video bez focení?',
        'Líbí se nám delší dokument, nejen krátký klip. Umíte ho natočit v {venue} {date}?',
        'Pošlete prosím ukázku videa se zvukem slibů. Obřad máme {date}, {city}.',
        'Můžeme ve videu z {venue} {date} použít vlastní hudbu, nebo řešíte licenci?',
        'Budete {date} v {city} natáčet i dronem, pokud počasí dovolí?',
    ],
    'invoices' => [
        'Prosím o zálohovou fakturu za rodinné focení v {city} dne {date}.',
        'Dobrý den, potřebujeme účtenku za firemní fotky {date}, {venue}. Je to možné?',
        'Kdy se doplácí svatební video z {city} ze dne {date}? Je možný převod?',
        'Dobrý den, údaje na faktuře k focení v {venue} {date} prosím pošlu e-mailem.',
        'Platíme hotově, lze vystavit doklad za focení {date} v {city}?',
    ],
    'pricing' => [
        'Na svatbu {date} v {venue} máme rozpočet 25 000 Kč. Co do něj zvládnete?',
        'Prosím kolik stojí hodina portrétního focení {date} v {city}, včetně úprav?',
        'Kolik fotografií za tu cenu dostaneme z rodinného focení v {venue} {date}?',
        'Je doprava do {city} {date} v ceně, nebo se platí zvlášť?',
        'Zahrnuje balíček na {date} v {venue} také tisk fotografií a online galerii?',
    ],
    'english' => [
        'Hello, could you photograph our small wedding in {city} on {date}?',
        'Can we pay cash and receive a bonus print with our family photos in {city} on {date}? Our venue: {url}',
        'Hi, we run a casino and need staff portraits in {city} on {date}. Website: {url}',
        'Our AI team needs corporate photos in {city} on {date}. This is our website: {url}',
        'Please call me about your photo and video package in {venue} on {date}. Thank you!',
    ],
    'german' => [
        'Guten Tag, was kostet eine Familienfotosession in {city} am {date}?',
        'Wir heiraten am {date} in {venue}. Haben Sie noch einen Termin frei?',
        'Können Sie uns bitte ein Angebot für Fotos und Video in {city} am {date} senden?',
        'Wir sprechen ein wenig Tschechisch. Ist ein Treffen in {city} vor dem {date} möglich?',
        'Unsere Location ist hier: {url}. Können Sie die Hochzeit am {date} begleiten?',
    ],
    'slovak' => [
        'Dobrý deň, máte voľno na svadbu {date} v {city}?',
        'Prosím o cenu rodinného fotenia v {venue} dňa {date}.',
        'Chceli by sme svadobné video z {city}, termín {date}. Môžeme si zavolať?',
        'Na vašom webe {url} sa nám páčia fotografie. Prídete do {city} {date}?',
        'Dobrý deň, potrebujeme darčekový poukaz na portrét v {city}, odovzdáme ho {date}.',
    ],
    'polish' => [
        'Dzień dobry, szukamy fotografa na ślub w {city} dnia {date}.',
        'Ile kosztuje sesja rodzinna w {venue} w dniu {date}?',
        'Czy mają Państwo wolny termin {date} w {city}? Dziękujemy.',
        'Prosimy o ofertę zdjęć i filmu ze ślubu w {venue} dnia {date}.',
        'Podoba nam się Państwa portfolio: {url}. Nasz ślub odbędzie się {date} w {city}.',
    ],
    'russian' => [
        'Здравствуйте! Сколько стоит семейная фотосессия в {city} {date}?',
        'Добрый день, ищем фотографа на свадьбу {date}, место {venue}.',
        'Можно заказать видео свадьбы в {city} на {date}?',
        'У нас будет корпоративная фотосъёмка {date} в {city}, нужен счёт за работу.',
        'Понравились ваши фотографии: {url}. Есть время для нас {date} в {city}?',
    ],
    'albanian' => [
        'Përshëndetje, sa kushton një sesion fotografik në {city} më {date}?',
        'Hi, kam dashur të di çmimin tuaj. Dasma në {city}, {date}.',
        'A jeni të lirë për fotografi dasme në {venue} më {date}?',
        'Dëshirojmë fotografi familjare në {city}, {date}. Faleminderit.',
        'Na pëlqen faqja juaj {url}. Kërkojmë foto dhe video në {city}, {date}.',
    ],
];
$cities = ['Olomouc', 'Brno', 'Praha', 'Prostějov', 'Přerov', 'Šternberk', 'Zlín', 'Ostrava', 'Litovel', 'Uničov'];
$names = ['Jan Novák', 'Eliška Veselá', 'Tomáš Dvořák', 'Petra Malá', 'Martin Černý', 'Anna Horáková', 'Lukáš Němec', 'Zuzana Bílá', 'Adam Krátký', 'Eva Novotná'];
$paths = ['/', '/nase-prace', '/cenik', '/kontakt', '/sluzby', '/o-nas', '/faq', '/blog', '/hodnoceni', '/cenik?balicek=foto%2Bvideo'];
$locatives = ['Olomouci', 'Brně', 'Praze', 'Prostějově', 'Přerově', 'Šternberku', 'Zlíně', 'Ostravě', 'Litovli', 'Uničově'];
$genitives = ['Olomouce', 'Brna', 'Prahy', 'Prostějova', 'Přerova', 'Šternberka', 'Zlína', 'Ostravy', 'Litovle', 'Uničova'];
$external = ['https://maps.google.com/?q=Olomouc', 'https://www.instagram.com/reel/Example01/',
    'https://vimeo.com/123456789', 'https://www.youtube.com/watch?v=ExampleVideo',
    'https://drive.google.com/file/d/ExampleFolder', 'https://www.pinterest.com/example/inspiration/',
    'https://www.facebook.com/example.venue/', 'https://venue.example.test/svatba',
    'https://photos.google.com/share/ExampleAlbum', 'https://example.test/galerie?name=Rodina%20Novak'];
$rows = [];
foreach ($templates as $category => $items) {
    foreach ($items as $index => $template) {
        foreach ($cities as $context => $city) {
            $url = in_array($category, ['own_website_links', 'slovak'], true)
                ? ($context % 3 === 0 ? 'www.rbvideosphotos.cz' : 'https://rbvideosphotos.cz') . $paths[$context]
                : $external[$context];
            $naturalTemplate = in_array($category, array_slice(array_keys($templates), 0, 14), true)
                ? strtr($template, ['v {city}' => 'v {loc}', 'do {city}' => 'do {gen}', 'z {city}' => 'z {gen}', 'v {venue}' => 've {venueLoc}', 'do {venue}' => 'do {venueGen}']) : $template;
            $message = strtr($naturalTemplate, ['{date}' => sprintf('%02d.%02d.2027', $context + 10, $index + 5),
                '{city}' => $city, '{loc}' => $locatives[$context], '{gen}' => $genitives[$context],
                '{venue}' => 'Stodola u ' . $genitives[$context], '{venueLoc}' => 'Stodole u ' . $genitives[$context],
                '{venueGen}' => 'Stodoly u ' . $genitives[$context], '{url}' => $url]);
            $rows[] = ['id' => sprintf('legit-%04d', count($rows) + 1), 'category' => $category,
                'name' => $names[$context], 'contact' => ($context % 3 === 0 ? '+420 777 000 ' . (100 + $context) : 'poptavka.' . ($context + 1) . '@example.test'),
                'message' => $message];
        }
    }
}
if (count($rows) !== 1000 || count(array_unique(array_column($rows, 'message'))) !== 1000) {
    throw new RuntimeException('Expected exactly 1,000 distinct inquiry messages.');
}
$output = __DIR__ . '/contact-legitimate-1000.json';
file_put_contents($output, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
echo "Generated 1,000 synthetic legitimate messages in 20 categories: $output\n";
