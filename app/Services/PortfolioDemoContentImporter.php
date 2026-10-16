<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\MediaRepository;
use App\Repositories\PortfolioRepository;
use PDO;
use RuntimeException;

final class PortfolioDemoContentImporter
{
    private const SOURCE_DIR = 'public/assets/demo/dalsifotkydoportfolia';
    private const SOURCE_DIR_MIX = 'public/assets/demo/mix-vseho-portfolio';
    /** Migrace 098: vraceni omylu z migrace 095 (weare5.jpg v galerii prvni prace). */
    private const MISTAKEN_COVER_SLUG = 'kamila-a-matej';
    private const MISTAKEN_COVER_FILENAME = 'weare5.jpg';
    /** Alt i title weare5.jpg z migrace 086 (DemoContentSeeder::replaceHomepageAboutVisual). */
    private const MISTAKEN_COVER_ORIGINAL_ALT = 'RB Videos & Photos - fotograf a kameraman';

    /** @var list<array<string,mixed>> */
    private const MANIFEST = [
        [
            'slug' => 'kamila-a-matej',
            'title' => 'Kamila a Matěj - svatební focení v krajině',
            'meta_title' => 'Kamila a Matěj - svatební focení v krajině | RB Videos & Photos',
            'alt_prefix' => 'Kamila a Matěj při svatebním focení v otevřené krajině',
            'date' => '2026-07-18',
            'excerpt' => 'Letní párové svatební focení v otevřené krajině a u rustikální dřevěné stavby, postavené na pohybu, blízkosti a přirozeném světle.',
            'photos' => ['18.7.2026 Kamila a Matěj.jpg', '18.7.2026 Kamila a Matěj (2).jpg'],
            'paragraphs' => [
                'U Kamily a Matěje jsem měl k dispozici dvě prostředí, která spolu fungují překvapivě dobře. Otevřenou louku a starší dřevěnou stavbu.',
                'Na louce jsem mohl nechat kolem nich dost prostoru. Nechtěl jsem fotografii stáhnout jen na samotný pár a úplně zahodit krajinu kolem. U jednoho portrétu jsme šli do výraznějšího pohybu, kdy Matěj drží Kamilu v záklonu. Takový záběr má energii sám o sobě a není potřeba ho dál komplikovat.',
                'U dřevěné stavby jsem postupoval opačně. Šel jsem blíž a nechal tmavší dřevo fungovat jako pozadí. Světlé oblečení se proti němu pěkně oddělilo a hlavní část fotografie stejně nakonec dělá jejich výraz. Oba se smějí, takže jsem neměl důvod je dál rovnat nebo nutit do jiné polohy.',
                'Při párovém focení se snažím nedržet lidi dlouho v jedné pozici. Většinou stačí ukázat směr, nechat je udělat pár kroků a sledovat, co z toho vznikne. Když se objeví dobrý moment, je lepší ho vyfotit než ho rozebrat dalšími instrukcemi.',
                'Stejně pracuji i při dalších svatbách pod značkou RB Videos & Photos. Jako svatební fotograf působím v Olomouci a vyrážím také na další místa po Moravě, kde se vždy snažím využít skutečný charakter prostředí.',
                'K fotografiím lze přidat také svatební video. Zachová pohyb a zvuk, zatímco fotografie umí jeden okamžik zastavit.',
                'Další svatební série jsou na blogu, možnosti focení a videa najdete v ceníku.',
            ],
        ],
        [
            'slug' => 'aneta-a-martin',
            'title' => 'Aneta a Martin - park, blízké portréty a architektura',
            'meta_title' => 'Aneta a Martin - svatební portréty v parku | RB Videos & Photos',
            'alt_prefix' => 'Aneta a Martin při svatebním focení v parku a u architektury',
            'date' => '2023-09-09',
            'excerpt' => 'Elegantní svatební portréty v zeleni a u historicky působící architektury, od široké procházky po blízké párové záběry.',
            'photos' => ['9.9.2023 Aneta a Martin.jpg', '9.9.2023 Aneta a Martin (2).jpg', '9.9.2023 Aneta a Martin (3).jpg'],
            'paragraphs' => [
                'U Anety a Martina jsou v podkladech tři fotografie a každá je postavená trochu jinak.',
                'Na prvním typu záběru jsem nechal v obraze hodně prostoru. Aneta a Martin jdou po cestě mezi stromy a nejsou nalepení přes celý snímek. Cesta, stromy a zeleň tam mají svoje místo. Kytice nad hlavou navíc do fotografie dostala pohyb, který bych při statickém portrétu neměl.',
                'Pak je tu mnohem bližší fotografie. Tam už okolí tolik řešit nemusím. Zeleň se ztratí do pozadí a zůstane dvojice, kytice, závoj a kontakt mezi nimi.',
                'Třetí snímek pracuje se světlou architekturou. Když má místo výrazné linie, není důvod je schovat. Naopak se snažím najít takový úhel, aby stavba pomohla kompozici a nepřebíjela lidi před ní.',
                'Při svatebním focení nechci vozit jednu univerzální šablonu na každou svatbu. Když mám k dispozici park, využiju prostor. Když mám zajímavou architekturu, pracuju s ní. A když funguje detail, jdu blíž.',
                'Na webu ukazujeme právě tento způsob práce. Svatební focení v Olomouci pro mě neznamená jen portréty v centru města; podle domluvy fotografuji také na dalších místech Moravy.',
                'Pokud pár chce vedle fotografií i svatební video, řešíme je jako samostatnou, ale sladěnou část. Tomáš se soustředí na pohyb, zvuk a situace, které fotografie zachytit neumí.',
                'Další ukázky jsou na blogu a zkušenosti klientů v hodnoceních.',
            ],
        ],
        [
            'slug' => 'lenka-a-lukas',
            'title' => 'Lenka a Lukáš - svatební reportáž v několika prostředích',
            'meta_title' => 'Lenka a Lukáš - svatební reportáž | RB Videos & Photos',
            'alt_prefix' => 'Lenka a Lukáš ve svatební reportáži z několika prostředí',
            'date' => '2023-07-07',
            'excerpt' => 'Rozmanitá svatební reportáž spojující přípravy, portréty v zeleni, historický areál, architektonické interiéry i uvolněnou zábavu na hostině.',
            'photos' => ['7.7.2023 Lenka a Lukáš.jpg', '7.7.2023 Lenka a Lukáš (2).jpg', '7.7.2023 Lenka a Lukáš (3).jpg', '7.7.2023 Lenka a Lukáš (4).jpg', '7.7.2023 Lenka a Lukáš (5).jpg', '7.7.2023 Lenka a Lukáš (6).jpg', '7.7.2023 Lenka a Lukáš (7).jpg'],
            'paragraphs' => [
                'U Lenky a Lukáše je série podstatně širší. Jsou v ní přípravy, portréty v zeleni, historická architektura, interiér i zábava. To je přesně ten typ svatebního focení, kde se fotografický přístup během práce několikrát mění.',
                'U příprav většinou nepotřebuju nikoho řídit. Když někdo upravuje motýlek nebo je nevěsta u zrcadla, situace už existuje. Mojí prací je spíš najít místo, odkud ji vyfotím bez toho, abych do ní lezl.',
                'Portréty jsou něco jiného. V zeleni můžu jít hodně blízko a nechat okolí změknout. U historické architektury se naopak vyplatí odstoupit. Točité schodiště funguje dobře z nadhledu, výhled do krajiny zase potřebuje prostor.',
                'Na jedné fotografii jsou i bílé holubice. Takové věci se nedají opakovat desetkrát stejně. Tam je důležité být připravený.',
                'A pak je tu hra s botami. Tam se ze mě zase stává čistě reportážní fotograf. Nikomu neříkám, jak se má tvářit. Jen sleduju, co se děje, a hlídám okamžik.',
                'Tohle je pro mě na svatbách zajímavé. Během jednoho dne člověk přechází od detailů k portrétům a od portrétů k situacím, které nemá pod kontrolou.',
                'Další ukázky svatebních reportáží najdete na RB Videos & Photos. Fotíme v Olomouci a jezdíme také po Moravě.',
                'K fotografiím je možné přidat svatební video. Tomáš se u videa soustředí na jinou část výsledku než já: video zachová pohyb a zvuk, fotografie zase konkrétní okamžik.',
                'Aktuální nabídka je v ceníku.',
            ],
        ],
        [
            'slug' => 'marie-a-miroslav',
            'title' => 'Marie a Miroslav - svatební portréty v zeleni',
            'meta_title' => 'Marie a Miroslav - svatba v zeleni | RB Videos & Photos',
            'alt_prefix' => 'Marie a Miroslav při svatebním portrétním focení v zeleni',
            'date' => '2024-06-01',
            'excerpt' => 'Komorní párové svatební portréty v husté zeleni s důrazem na blízkost, krajku, květinové detaily a práci s rozostřeným popředím.',
            'photos' => ['1.6.2024 Marie a Miroslav.jpg', '1.6.2024 Marie a Miroslav (2).jpg'],
            'paragraphs' => [
                'U Marie a Miroslava mám v podkladech dva portréty. Z toho se nedá poctivě popsat celý svatební den, takže se držím toho, co na fotografiích opravdu je.',
                'Hlavním prvkem je zeleň. U obou snímků jsem nechal listy dostat se i před objektiv. Nejen někam za pár. Díky tomu má fotografie několik vrstev a nevypadá jako člověk postavený před zelenou stěnu.',
                'Na takové focení nepotřebuju obrovskou lokaci. Často mi stačí malý kus místa, kde funguje světlo a kde si můžu najít čistý úhel.',
                'Dobře tam vycházejí i detaily. Světlé krajkové šaty, tmavší oblek, růžové květinové prvky a viditelné tetování. Nemá cenu kolem toho přidávat další věci, když už obraz funguje sám.',
                'U párových portrétů se snažím pracovat jednoduše. Blízkost, objetí, čelo na čelo. Většinou je to přirozenější než dlouhé vysvětlování, kam přesně dát každou ruku.',
                'Tahle série je pro mě připomínka, že dobrý svatební portrét nemusí vzniknout na známém místě. Někdy opravdu stačí pár metrů zeleně a správný úhel.',
                'Další ukázky jsou na našem webu. Svatby fotografuji v Olomouci i na dalších místech Moravy.',
                'Vedle fotografií je možné domluvit také svatební video. Přehled služeb je v ceníku.',
            ],
        ],
        [
            'slug' => 'martina-a-jaroslav',
            'title' => 'Martina a Jaroslav - prsteny, tenisky, most a portréty',
            'meta_title' => 'Martina a Jaroslav - svatební focení v parku | RB Videos & Photos',
            'alt_prefix' => 'Martina a Jaroslav při svatebním focení v parku',
            'date' => '2025-08-02',
            'excerpt' => 'Elegantní letní svatební série s detaily prstenů, moderními bílými teniskami, parkovou zelení, dřevěným mostem a výraznými portréty nevěsty i páru.',
            'photos' => ['2.8.2025 Martina a Jaroslav.jpg', '2.8.2025 Martina a Jaroslav (2).jpg', '2.8.2025 Martina a Jaroslav (3).jpg', '2.8.2025 Martina a Jaroslav (4).jpg', '2.8.2025 Martina a Jaroslav (5).jpg'],
            'paragraphs' => [
                'U Martiny a Jaroslava se mi líbí hlavně pestrost celé série. Nejsou tam jen portréty páru. Jsou tam prsteny, bílé tenisky, ruce, kytice, most, voda, samostatný portrét nevěsty a několik různých způsobů práce se zelení.',
                'Detail s teniskami a prsteny je jednoduchý, ale podobné fotografie mají v celé galerii svoje místo. Za pár let nemusí být zajímavé jen velké portréty. Často mají hodnotu i věci, které člověk v ten den skoro nevnímá.',
                'U detailu rukou už je práce úplně jiná. Na malé ploše je vidět každá drobnost a kompozice musí být přesná.',
                'Na mostě jsem naopak mohl odstoupit a nechat v obraze i vodu a okolní zeleň. Takové místo nemá smysl úplně vymazat rozostřením. Když okolí funguje, nechávám ho být součástí fotografie.',
                'U bližších portrétů jsem si zase pomohl vegetací v popředí. Listy před objektivem udělají měkkou vrstvu a obraz dostane větší hloubku.',
                'Samostatný portrét nevěsty je postavený hlavně na šatech, vlečce a světle. V takové chvíli už další prvky nepotřebuju.',
                'Při svatebním focení se snažím hlídat, aby výsledkem nebylo třicet podobných fotografií. Chci tam mít celek, detail i něco mezi tím.',
                'Další ukázky svatebního focení v Olomouci a okolí najdete na RB Videos & Photos. Pokud chcete fotografii společně se svatebním videem, obě služby lze domluvit v rámci jednoho týmu.',
                'Zkušenosti předchozích klientů jsou v hodnoceních.',
            ],
        ],
        [
            'slug' => 'simona-a-michal',
            'title' => 'Simona a Michal - jednoduchý svatební portrét v krajině',
            'meta_title' => 'Simona a Michal - svatební portrét v krajině | RB Videos & Photos',
            'alt_prefix' => 'Simona a Michal při svatebním portrétním focení v krajině',
            'date' => '2025-09-06',
            'excerpt' => 'Přírodní svatební portrét ve vysoké trávě s tlumenější oblohou, elegantním modrým oblekem, bílou kyticí a klidnou párovou kompozicí.',
            'photos' => ['6.9.2025 Simona a Michal.jpg'],
            'paragraphs' => [
                'U Simony a Michala mám k dispozici jednu fotografii. Takže nemá smysl psát, kde byl obřad, co se dělo před focením nebo jak vypadal zbytek svatby. Ty informace nemám.',
                'Na fotografii jdou vedle sebe vysokou trávou a drží se za ruce. V pozadí jsou stromy a obloha je spíš tlumená. Ten záběr mám rád právě kvůli jednoduchosti.',
                'Nemusí se pořád něco dít. Někdy je lepší nechat pár jen projít a nesnažit se z každé fotografie dělat velkou scénu.',
                'Tmavě modrý oblek se dobře odděluje od světlých šatů a kytice. Okolní krajinu jsem nechal v obraze, protože k fotografii patří.',
                'Když fotím v přírodě, nemám potřebu automaticky všechno kolem lidí rozmazat. Pokud prostředí funguje, nechávám ho čitelné.',
                'Svatby fotografuji v Olomouci, okolí a po domluvě i jinde na Moravě. Konkrétní místo této fotografie ale z podkladů potvrzené nemám, takže ho nebudu hádat.',
                'Další galerie jsou na blogu. V rámci RB Videos & Photos nabízíme také svatební video pro páry, které chtějí vedle fotografií zachovat pohyb a zvuk.',
            ],
        ],
        [
            'slug' => 'lubos-a-iva',
            'title' => 'Luboš a Iva - louka, závoj a portrétní focení',
            'meta_title' => 'Luboš a Iva - svatební focení v louce | RB Videos & Photos',
            'alt_prefix' => 'Luboš a Iva při svatebním focení v louce',
            'date' => '2026-06-06',
            'excerpt' => 'Svatební série od příprav nevěsty po uvolněné a kreativní portréty v louce, se závojem, růžovou kyticí a teplým přírodním světlem.',
            'photos' => ['6.6.2026 Luboš a Iva.jpg', '6.6.2026 Luboš a Iva (2).jpg', '6.6.2026 Luboš a Iva (3).jpg', '6.6.2026 Luboš a Iva (4).jpg', '6.6.2026 Luboš a Iva (5).jpg', '6.6.2026 Luboš a Iva (6).jpg'],
            'paragraphs' => [
                'U Luboše a Ivy je v sérii několik různých typů fotografií. Přípravy, chůze vysokou trávou, blízké portréty a kreativnější záběry se závojem.',
                'U fotografie z příprav nevěsty jsem neměl důvod situaci předělávat. Líčení probíhá samo a fotograf se tomu musí přizpůsobit. Raději změním místo já, než abych kvůli fotografii každou chvíli zastavoval ostatní.',
                'Na louce už se dá s pohybem pracovat víc. Luboš a Iva jdou vysokou trávou, drží se za ruce a na jiném snímku se smějí v objetí. To jsou pro mě často nejlepší chvíle. Ne chvíle, kdy pár dokonale splní přesnou instrukci, ale chvíle mezi tím.',
                'V sérii jsou pak i dva výraznější portréty se závojem. Na jednom je pohled shora na dvojici ležící v trávě. Světlý závoj tam vytváří velkou plochu a pomáhá celé kompozici. U druhého jsem závoj dostal přímo před objektiv a použil ho jako součást obrazu.',
                'Není to dodatečný efekt z počítače. Materiál je opravdu před fotoaparátem a světlo přes něj prochází jinak.',
                'Růžovo-bílá kytice se navíc opakuje na několika fotografiích a pomáhá celou sérii barevně propojit.',
                'Další svatební série z Olomouce i jiných míst najdete na našem webu. S Tomášem zároveň nabízíme svatební video, takže fotografii a film lze řešit společně.',
                'Varianty služeb jsou v ceníku.',
            ],
        ],
        [
            'slug' => 'lucie-a-jarek',
            'title' => 'Lucie a Jarek - svatební focení v otevřené krajině',
            'meta_title' => 'Lucie a Jarek - svatební focení v krajině | RB Videos & Photos',
            'alt_prefix' => 'Lucie a Jarek při svatebním focení ve venkovské krajině',
            'date' => '2026-07-25',
            'excerpt' => 'Uvolněné svatební portréty ve venkovské krajině, s poli, loukou, tetováním, šlemi a kreativními detaily prstenů i samotné nevěsty.',
            'photos' => ['25.72026 Lucie a Jarek.jpg', '25.72026 Lucie a Jarek (2).jpg', '25.72026 Lucie a Jarek (3).jpg', '25.72026 Lucie a Jarek (4).jpg'],
            'paragraphs' => [
                'U Lucie a Jarka je prostředí důležitá část fotografií. Louky, pole a vzdálená zástavba nejsou jen něco, co se náhodou ocitlo za nimi. U širších záběrů jsem s krajinou počítal jako s částí kompozice. Proto jsem kolem dvojice nechal dost prostoru.',
                'Kdybych všechno fotil jen zblízka, prostředí by se ztratilo a výsledná fotografie by mohla vzniknout prakticky kdekoliv.',
                'Zajímavé jsou i detaily oblečení. Jarek má světlé kalhoty, bílou košili, šle a motýlek. U Lucie jsou na fotografiích vidět bílé šaty a tetování.',
                'Pak je tu detail rukou a snubních prstenů proti slunci. Tam se celý přístup otočí. Krajina najednou není důležitá. Zůstávají ruce, prsteny, náramky, tetování a světlo mezi prsty.',
                'Samostatný portrét Lucie vleže v trávě zase funguje hlavně díky ploše bílých šatů a jinému úhlu pohledu.',
                'Tohle střídání mám u svateb rád. Široký záběr, detail, portrét, jiný úhel. Díky tomu galerie nepůsobí jako deset verzí jedné fotografie.',
                'Svatby fotografuji v Olomouci i dál po Moravě. Další série jsou na RB Videos & Photos.',
                'Pokud chcete vedle fotografií také svatební video, film řeší Tomáš a obě služby můžeme připravit v rámci jednoho týmu.',
                'Další galerie jsou na blogu a zkušenosti klientů v hodnoceních.',
            ],
        ],
        [
            'slug' => 'lea-foceni-pro-ritualni-saty',
            'title' => 'Lea - focení pro Rituální šaty',
            'meta_title' => 'Lea - portrétní focení pro Rituální šaty | RB Videos & Photos',
            'alt_prefix' => 'Lea v červených Rituálních šatech při portrétním focení v louce',
            'date' => null,
            'excerpt' => 'Letní lifestyle a editorial portréty v dlouhých červených šatech, focené v otevřené louce s důrazem na pohyb, klid i kontrast výrazné barvy s krajinou.',
            'photos' => ['Lea focení pro Rituální šaty.jpg', 'Lea focení pro Rituální šaty (2).jpg', 'Lea focení pro Rituální šaty (3).jpg'],
            'paragraphs' => [
                'Tady nejde o svatbu, ale o portrétní focení pro Rituální šaty.',
                'Hlavním prvkem jsou dlouhé červené šaty a otevřená krajina. Červená v zelené trávě funguje tak výrazně, že kolem ní není potřeba stavět další scénu.',
                'Na jedné fotografii Lea stojí ve vysoké trávě. Na druhé sedí. U třetího záběru se do fotografie dostává pohyb, zvednuté ruce a vlasy ve větru. Místo se přitom zásadně nemění.',
                'To je na portrétním focení zajímavé. Nemusím každých deset minut hledat novou lokaci. Stačí změnit vzdálenost, úhel nebo způsob, jakým se člověk před objektivem pohybuje.',
                'Výrazná barva šatů už sama táhne pozornost. Kdybych k ní přidal další výrazné prvky, spíš bych fotografii přeplnil. Proto jsem nechal fungovat jednoduchou kombinaci postavy, šatů, trávy a oblohy.',
                'Podobné portrétní focení je pro mě příjemná změna proti rychlejší svatební reportáži. Mám víc prostoru přemýšlet nad samotnou kompozicí a zkoušet různé varianty jednoho místa.',
                'Na RB Videos & Photos se věnujeme hlavně svatební fotografii a videu, ale portrétní práce je pořád důležitou částí mojí tvorby.',
                'Další portrétní a svatební fotografie z Olomouce i výjezdů po Moravě najdete na blogu. Pro svatby společně s Tomášem nabízíme také video.',
            ],
        ],
    ];

    /**
     * Video gallery: 12 supplied YouTube items (database/seeds/demo_assets/videa.txt,
     * rows 1 and 4-14 in their original order - Vimeo rows 2-3 already power the
     * "martina-a-jaroslav" and existing video credits elsewhere, and the trailing
     * car/DayZ rows 15-19 are unrelated personal footage, left out). No photos are
     * attached; the cover comes from the YouTube thumbnail (PortfolioRepository::
     * youtubeThumbnail()). Missing optional dates are left null, never guessed.
     *
     * @var list<array<string,mixed>>
     */
    private const VIDEO_MANIFEST = [
        [
            'slug' => 'svatba-pavlina-a-marek-krylovi',
            'title' => 'Pavlína a Marek Krylovi',
            'meta_title' => 'Pavlína a Marek Krylovi - svatební video | RB Videos & Photos',
            'excerpt' => 'Svatební video z 26. 9. 2025.',
            'date' => '2025-09-26',
            'video_url' => 'https://www.youtube.com/watch?v=5ZlVVcIu10U',
        ],
        [
            'slug' => 'svatba-lucie-a-miroslav-provazovi',
            'title' => 'Lucie a Miroslav Provazovi',
            'meta_title' => 'Lucie a Miroslav Provazovi - svatební video | RB Videos & Photos',
            'excerpt' => 'Svatební video z 7. 6. 2025.',
            'date' => '2025-06-07',
            'video_url' => 'https://www.youtube.com/watch?v=hYj2w3P5rK8',
        ],
        [
            'slug' => 'svatba-michaela-a-jan-junglingovi',
            'title' => 'Michaela a Jan Jünglingovi',
            'meta_title' => 'Michaela a Jan Jünglingovi - svatební video | RB Videos & Photos',
            'excerpt' => 'Svatební video z 27. 7. 2024.',
            'date' => '2024-07-27',
            'video_url' => 'https://www.youtube.com/watch?v=1z-qckQn5UU',
        ],
        [
            'slug' => 'svatba-marie-a-miroslav-video',
            'title' => 'Marie a Miroslav',
            'meta_title' => 'Marie a Miroslav - svatební video | RB Videos & Photos',
            'excerpt' => 'Svatební video z 1. 6. 2024.',
            'date' => '2024-06-01',
            'video_url' => 'https://www.youtube.com/watch?v=O5i9TzCms5c',
        ],
        [
            'slug' => 'svatebni-editorial-zimni',
            'title' => 'Zimní svatební editorial',
            'meta_title' => 'Zimní svatební editorial | RB Videos & Photos',
            'excerpt' => 'Svatební editorial.',
            'date' => null,
            'video_url' => 'https://www.youtube.com/watch?v=FzchuDWJ5Vg',
        ],
        [
            'slug' => 'svatba-ester-a-tomas-krupovi',
            'title' => 'Ester a Tomáš Krůpovi',
            'meta_title' => 'Ester a Tomáš Krůpovi - svatební video | RB Videos & Photos',
            'excerpt' => 'Svatební video z 14. 10. 2023.',
            'date' => '2023-10-14',
            'video_url' => 'https://www.youtube.com/watch?v=su0hKnBnCKw',
        ],
        [
            'slug' => 'svatba-marketa-a-petr-plaskovi',
            'title' => 'Markéta a Petr Pláškovi',
            'meta_title' => 'Markéta a Petr Pláškovi - svatební video | RB Videos & Photos',
            'excerpt' => 'Svatební video z 17. 6. 2023.',
            'date' => '2023-06-17',
            'video_url' => 'https://www.youtube.com/watch?v=DBWKJ46eM4c',
        ],
        [
            'slug' => 'svatba-nikola-a-jirka-kankovsti',
            'title' => 'Nikola a Jirka Kaňkovští',
            'meta_title' => 'Nikola a Jirka Kaňkovští - svatební video | RB Videos & Photos',
            'excerpt' => 'Svatební video z 20. 5. 2023.',
            'date' => '2023-05-20',
            'video_url' => 'https://www.youtube.com/watch?v=C1wL3QFZhn0',
        ],
        [
            'slug' => 'villa-grande-olomouc-behind-the-scenes',
            'title' => 'RB Videos Photos Villa Grande Olomouc behind the scenes video z natáčení',
            'meta_title' => 'Villa Grande Olomouc - behind the scenes | RB Videos & Photos',
            'excerpt' => 'Behind the scenes.',
            'date' => null,
            // Supplied as a /shorts/ link; normalized to the equivalent /watch?v= form
            // because PortfolioRepository::embedUrl()/youtubeThumbnail() only match
            // watch/youtu.be/embed URLs, not /shorts/. Same video ID, no data invented.
            'video_url' => 'https://www.youtube.com/watch?v=g2xbGhaUkpg',
        ],
        [
            'slug' => 'svatba-tomas-a-helena-hufovi',
            'title' => 'Tomáš a Helena Hufovi',
            'meta_title' => 'Tomáš a Helena Hufovi - svatební video | RB Videos & Photos',
            'excerpt' => 'Svatební video z 17. 9. 2022.',
            'date' => '2022-09-17',
            'video_url' => 'https://www.youtube.com/watch?v=GBKJ_VB_Nmw',
        ],
        [
            'slug' => 'svatba-stanislav-a-martina-sinclovi',
            'title' => 'Stanislav a Martina Šinclovi',
            'meta_title' => 'Stanislav a Martina Šinclovi - svatební video | RB Videos & Photos',
            'excerpt' => 'Svatební video z 23. 4. 2022.',
            'date' => '2022-04-23',
            'video_url' => 'https://www.youtube.com/watch?v=sc06FkCv_Mw',
        ],
        [
            'slug' => 'maturitni-vecirek-cajkovskeho-olomouc',
            'title' => 'Maturitní večírek Gymnázia Čajkovského Olomouc',
            'meta_title' => 'Maturitní večírek Gymnázia Čajkovského Olomouc | RB Videos & Photos',
            'excerpt' => 'Maturitní večírek z 19. 3. 2022.',
            'date' => '2022-03-19',
            'video_url' => 'https://www.youtube.com/watch?v=zviMaOQnxUY',
        ],
    ];

    /**
     * Photo galleries assembled 2026-09-06 from a supplied mixed photo batch
     * (public/assets/demo/mix-vseho-portfolio, 76 files across 15 galleries:
     * family, pregnancy, wedding, and portrait/model shoots). No product or
     * corporate-heavy content existed in the source batch beyond the two
     * business-portrait photos below. Grouped by visual coherence (same
     * couple/family/location/styling), not forced into a single narrative
     * where the source photos clearly come from different real sessions -
     * those galleries describe the style/approach in general terms instead
     * of inventing a specific couple or family name.
     *
     * @var list<array<string,mixed>>
     */
    private const MIX_MANIFEST = [
        [
            'slug' => 'rodina-levandulove-pole',
            'title' => 'Rodinné focení v levandulovém poli',
            'meta_title' => 'Rodinné focení v levandulovém poli | RB Videos & Photos',
            'alt_prefix' => 'Rodinné focení v levandulovém poli',
            'date' => null,
            'excerpt' => 'Letní rodinné focení v levandulovém poli, postavené na sourozenecké blízkosti a přirozeném pohybu mezi keři levandule.',
            'photos' => ['rodina-levandulove-pole-01.jpg', 'rodina-levandulove-pole-02.jpg', 'rodina-levandulove-pole-03.jpg', 'rodina-levandulove-pole-04.jpg', 'rodina-levandulove-pole-05.jpg'],
            'paragraphs' => [
                'Levandulové pole nabízí něco, co jinde těžko zopakuji - sytou barvu, která nese atmosféru fotografie sama o sobě, a zároveň dost prostoru na to, aby se děti mohly volně pohybovat a nemusely stát na jednom místě.',
                'První záběry stavím na tom nejjednodušším, co sourozenci sami nabídnou - chůzi vedle sebe, podanou ruku, cestu mezi keři. Nechávám je jít vlastním tempem a spíš sleduju, kde se řada levandule sama nabídne jako vodicí linie ke kraji záběru.',
                'Jakmile se objeví moment jako objetí nebo polibek na čelo, pracuju s ním rychle a bez velkých zásahů. Menší dítě drží starší sourozenec, a rozdíl ve výšce i v gestu dělá fotografii přirozenější, než kdybych oba stavěl vedle sebe do stejné pozice.',
                'Když se k dvojici dětí přidají i rodiče, kompozice se mění. Najednou pracuju se čtyřmi lidmi, kteří se objímají a smějí, a mým úkolem je najít úhel, ve kterém nikdo nikoho nezakrývá a levandule zůstává čitelná i za nimi.',
                'Poslední fáze focení bývá nejvolnější. Nechám celou rodinu lehnout přímo do porostu a objímat se bez pokynů. V takové poloze lidé přestávají hlídat, jak vypadají, a to je přesně ten druh uvolněnosti, který na fotografii chci mít.',
                'U dětí se snažím nedávat instrukce typu "usmívej se" - mnohem líp funguje, když je nechám dělat něco konkrétního, jít, obejmout, položit hlavu na rameno. Úsměv pak přijde sám jako reakce na to, co se skutečně děje.',
                'Barevně jsem stavěl na kontrastu mezi fialovými odstíny levandule a světlým oblečením rodiny, aby postavy z porostu vystoupily, ale zároveň bylo jasné, že pole je plnohodnotnou součástí fotografie, ne jen kulisou v pozadí.',
                'Podobně přistupuji k rodinným focením i pod značkou RB Videos & Photos v Olomouci a okolí, kde vyrážím za rodinami i dál po Moravě a hledám prostředí, které dětem i rodičům dovolí chovat se přirozeně.',
                'Další rodinné i svatební série najdete na blogu, přehled možností focení pak v ceníku. V případě zájmu o termín mě nejsnáz zastihnete přes kontakt.',
            ],
        ],
        [
            'slug' => 'rodina-na-louce',
            'title' => 'Rodinné focení na louce',
            'meta_title' => 'Rodinné focení na louce | RB Videos & Photos',
            'alt_prefix' => 'Rodinné focení na louce',
            'date' => null,
            'excerpt' => 'Letní rodinné focení na louce a u dřevěné konstrukce, založené na hravém kontaktu mezi rodiči a dítětem.',
            'photos' => ['rodina-na-louce-01.jpg', 'rodina-na-louce-02.jpg', 'rodina-na-louce-03.jpg'],
            'paragraphs' => [
                'Otevřená louka dává rodinám prostor, který ve studiu ani v interiéru nezískám - je tam kam utíkat, kam se schovat a hlavně dost místa na to, aby se rodiče s dětmi mohli pohybovat naplno, bez obav, že do něčeho vrazí.',
                'Záběr, kdy otec zvedá syna vysoko nad hlavu, je typický příklad momentu, který nejde zopakovat na povel. Čekám na okamžik těsně po zvednutí, kdy se oba ještě smějí ze samotného pohybu, a fotím spíš sled snímků než jeden vyčkávaný okamžik.',
                'U dřevěné konstrukce jsem šel opačným směrem - blíž a pomaleji. Trámy jsem využil jako přirozený rám kolem otce a dítěte, kteří se k sobě tulí tvářemi. Ten detail funguje lépe v klidu než v pohybu, proto jsem tady nechal oba chvíli jen tak být.',
                'Ve vysoké trávě jsem pak spojil všechny tři dohromady. Rodiče drží dítě na rukou a hrají si s ním způsobem, který sami znají nejlíp - já jen hlídám kompozici a světlo, aby tráva kolem nich zůstala čitelná a nezakrývala výrazy.',
                'Při focení rodin s malými dětmi se snažím dávat pokyny rodičům, ne dětem. Řeknu, kam jít nebo co udělat, a dítě samo zareaguje na to, co se kolem něj děje - výsledek pak působí mnohem přirozeněji, než kdybych se snažil řídit i to nejmenší.',
                'Letní zeleň louky funguje jako klidné, jednobarevné pozadí, které nechá vyniknout oblečení i výrazy ve tváři. Nesnažím se ji nijak přebít, spíš najít místo, kde světlo prochází stromy tak, aby na rodinu dopadalo měkce.',
                'Nejvíc mi u těchto focení záleží na pohybu a smíchu, ne na dokonale rovné pozici. Rozmazaná ruka nebo nakloněná hlava uprostřed hry většinou vypráví víc než upravený portrét, kde všichni stojí a čekají na spoušť.',
                'Podobně pracuji na rodinných foceních pod značkou RB Videos & Photos v Olomouci a dalších místech na Moravě, kam za rodinami rád vyrazím, pokud dá prostředí smysl.',
                'Přehled dalších rodinných sérií najdete na blogu, konkrétní varianty focení pak v ceníku. Termín si můžete domluvit přes kontakt.',
            ],
        ],
        [
            'slug' => 'rodina-vanocni-studio',
            'title' => 'Vánoční rodinné focení ve studiu',
            'meta_title' => 'Vánoční rodinné focení ve studiu | RB Videos & Photos',
            'alt_prefix' => 'Vánoční rodinné focení ve studiu',
            'date' => null,
            'excerpt' => 'Vánoční rodinné focení ve studiu s vyzdobenými stromečky, postavené na klidné atmosféře pohovky a sváteční dekorace.',
            'photos' => ['rodina-vanocni-studio-01.jpg', 'rodina-vanocni-studio-02.jpg'],
            'paragraphs' => [
                'Vánoční focení ve studiu řeším jinak než focení venku. Světlo si tady můžu dopředu nastavit a udržet stejné po celou dobu, což se hodí hlavně u rodin s malými dětmi, kde nikdy dopředu nevím, kolik času a trpělivosti budeme mít.',
                'Pohovka jako základní scéna funguje dobře právě proto, že je to místo, kde rodina běžně sedí pohromadě. Nikoho nemusím stavět do nepřirozené pozice - stačí, aby se usadili tak, jak jsou zvyklí, a odtamtud teprve doladit detaily.',
                'Se dvěma malými chlapci počítám s tím, že se pozornost bude ztrácet rychle. Proto se snažím mít scénu i světlo hotové dřív, než děti usednou, a samotné focení pak jde rychle, dokud je udrží zájem nebo legrace.',
                'Vánoční stromečky a dekorace v pozadí nejsou jen výplň prostoru - dávají fotografii konkrétní čas v roce a atmosféru, kterou pak rodina rozezná i za pár let. Snažím se je proto umístit tak, aby doplňovaly rodinu, ne aby ji přebily.',
                'U druhé scény s knihami a stromečky jsem pracoval s vrstvením - dekorace v popředí a pozadí, rodina uprostřed. Takové rozestavění dá fotce hloubku i ve studiu, kde jinak lehce hrozí, že bude všechno na jedné rovině.',
                'Barevně se u vánočních focení držím teplejších tónů, které ladí se stromečky i s náladou zimních měsíců. Oblečení rodiny se snažím sladit tak, aby zapadlo do scény, ale nesplynulo úplně s dekorací v pozadí.',
                'Oproti focení venku mi studio dává jistotu, že výsledek nezávisí na počasí ani na denním světle, což je u vánočních termínů, kdy je venku brzy tma, velká výhoda. Rodina tak může přijít i podvečer a mít stejně kvalitní fotky.',
                'Takové focení bere řada rodin i jako roční tradici - vánoční fotka na přání nebo do rámečku. Podobné sezónní i celoroční sezení nabízím pod značkou RB Videos & Photos v Olomouci, včetně možnosti doplnit fotky krátkým videem.',
                'Další rodinné série najdete na blogu, konkrétní varianty a ceny studiového focení pak v ceníku. Termín ve studiu si domluvíte přes kontakt.',
            ],
        ],
        [
            'slug' => 'tehotenske-ocekavani',
            'title' => 'Těhotenské focení a čekání na miminko',
            'meta_title' => 'Těhotenské focení a čekání na miminko | RB Videos & Photos',
            'alt_prefix' => 'Těhotenské focení páru v přírodě',
            'date' => null,
            'excerpt' => 'Těhotenské focení v přírodě zaměřené na blízkost páru, jemné světlo a přirozené gesto ruky na těhotenském bříšku.',
            'photos' => ['tehotenske-ocekavani-01.jpg', 'tehotenske-ocekavani-02.jpg', 'tehotenske-ocekavani-03.jpg'],
            'paragraphs' => [
                'Těhotenství a čekání na miminko patří mezi období, která si páry chtějí zachytit jinak než klasickým rodinným portrétem. Nejde o to nastavit dokonalou pózu, ale zachytit stav, ve kterém se ten pár právě nachází - něco mezi netrpělivostí, radostí a trochou nervozity z toho, co přijde.',
                'Většinu těchto focení dělám venku, v otevřené krajině nebo na louce, a čas volím tak, aby světlo bylo nízké a měkké. Večerní slunce dokáže postavu obtáhnout teplým obrysem a zbytek scény nechá v klidu, což se hodí právě k tématu, kde je hlavní jen pár lidí a jejich očekávání.',
                'Jeden z motivů, který se v těchto sériích často objevuje, je muž, který zvedá miminko vysoko nad hlavu proti zapadajícímu slunci. Je to gesto, které nikdo nemusí nacvičovat - přijde samo, jakmile dítě začne v náručí žvatlat nebo se smát, a fotograf má jen pár vteřin na to zareagovat.',
                'U těhotenských párů mě zajímá hlavně dotek. Ruka položená na břiše, pohled, který si dvojice vymění, aniž by věděla, že je právě fotografuji - tyhle drobnosti nesou víc než jakákoliv naaranžovaná pozice. Snažím se je najít a nechat je se přirozeně odehrát před objektivem.',
                'Když pár sedí, například on na lavičce a ona mu na klíně, hledám kompozici, která oba propojí do jednoho tvaru, ne dvě oddělené postavy vedle sebe. Blízkost těl a klid v obličeji jsou v takové chvíli důležitější než přesné nasvícení nebo dokonale rovné držení.',
                'Při těchto foceních nechávám hodně prostoru improvizaci. Řeknu párům, kam se mají posunout nebo na co se mají dívat, ale co se stane potom, nechávám na nich. Nejlepší záběry většinou vzniknou v okamžiku, kdy si pár přestane uvědomovat, že je fotografovaný.',
                'Stejný přístup držím i u dalších těhotenských a rodinných focení pod značkou RB Videos & Photos. Působím v Olomouci a jezdím i po dalších částech Moravy, kde využívám venkovní prostředí - louky, remízky nebo starší stavby - podle toho, co se k danému páru nejlépe hodí.',
                'Kromě fotografií nabízím i video, které dokáže zachytit pohyb a hlas dítěte v momentě, kdy fotografie zůstává jen u jednoho obrazu. Další rodinné a těhotenské série najdete na blogu, ceny jednotlivých balíčků pak v ceníku.',
            ],
        ],
        [
            'slug' => 'svatba-obilne-pole',
            'title' => 'Svatba v obilném poli za soumraku',
            'meta_title' => 'Svatba v obilném poli za soumraku | RB Videos & Photos',
            'alt_prefix' => 'Ženich a nevěsta v obilném poli při západu slunce',
            'date' => null,
            'excerpt' => 'Svatební focení v obilném poli za zlaté hodiny, postavené na tanci, objetí a pohledu do dálky v podvečerním světle.',
            'photos' => ['svatba-obilne-pole-01.jpg', 'svatba-obilne-pole-02.jpg', 'svatba-obilne-pole-03.jpg'],
            'paragraphs' => [
                'Svatba v obilném poli patří mezi má oblíbená venkovní prostředí pro podvečerní focení. Zlaté klasy dodají snímku barvu, kterou nemusím nijak upravovat, a otevřený prostor kolem páru umožňuje pracovat s pohybem, aniž by se do záběru pletlo cokoliv rušivého.',
                'Focení jsme načasovali na dobu těsně před západem slunce, kdy je světlo nejteplejší a stíny nejdelší. V takovém světle stačí málo - pár kroků, otočení, tanec - a fotografie získá dynamiku, kterou by uprostřed dne bylo těžké napodobit.',
                'Na jednom ze záběrů se novomanželé drží za ruce a pohybují se v tanci přímo uprostřed pole. Nechtěl jsem je zastavovat kvůli technicky přesné póze - šlo mi o to zachytit pohyb v okamžiku, kdy oba ještě nevědí, jak přesně bude fotografie vypadat, a právě proto působí uvolněně.',
                'Další fotografie vznikla při polibku, kdy nevěsta stále drží kytici u boku. Kytice v takové kompozici funguje jako přirozený doplněk - nemusí být uprostřed záběru, stačí, když je vidět, a hlavní pozornost tak zůstává na obou tvářích.',
                'Poslední záběr je klidnější. Novomanželé stojí objatí a dívají se do dálky, za nimi se táhne kopcovitá krajina prosvětlená večerním sluncem. Tady jsem cíleně ubral na dynamice a nechal mluvit hlavně světlo a hloubku scény za nimi.',
                'Obilné pole má tu výhodu, že se s ročním obdobím mění barva i výška klasů, takže žádné dvě takové svatby nevypadají stejně. Snažím se proto vždy nejdřív scénu obejít, najít nejlepší úhel vůči slunci a teprve pak páru řeknu, kam se má postavit.',
                'Podobně pracuji na svatbách po celé Moravě pod značkou RB Videos & Photos. Jako svatební fotograf a kameraman působím v Olomouci, ale rád vyjíždím i do okolní krajiny, pokud dává danému páru fotograficky smysl.',
                'Ke svatebním fotografiím nabízím i svatební video, které dokáže zachytit zvuk a pohyb tance v poli, jenž na fotografii zůstává jen jako jeden zastavený okamžik. Další svatební série najdete na blogu a přehled balíčků v ceníku.',
            ],
        ],
        [
            'slug' => 'svatba-cihlova-zed',
            'title' => 'Svatební portréty u cihlové zdi',
            'meta_title' => 'Svatební portréty u cihlové zdi | RB Videos & Photos',
            'alt_prefix' => 'Ženich a nevěsta u cihlové zdi',
            'date' => null,
            'excerpt' => 'Svatební portréty u cihlové zdi postavené na kontrastu textur, blízkosti páru a jemném doteku ruky o zeď.',
            'photos' => ['svatba-cihlova-zed-01.jpg', 'svatba-cihlova-zed-02.jpg'],
            'paragraphs' => [
                'Cihlová zeď je jedním z pozadí, které mám rád pro svatební portréty přímo ve městě. Textura starších cihel dodá fotografii charakter, aniž by odváděla pozornost od páru, a zároveň funguje v jakémkoliv ročním období.',
                'U tohoto páru jsme využili kontrast mezi tmavší, hrubou zdí a světlými svatebními šaty. Bílá látka a závoj se proti cihlám opticky oddělí, takže i bez dalšího svícení fotografie působí čistě a čitelně.',
                'Na prvním záběru ženich s nevěstou stojí vedle sebe, drží se za ruku a dívají se jeden na druhého. Nechal jsem mezi nimi přirozený odstup, aby bylo vidět gesto ruky i výraz obou tváří zároveň, ne jen detail.',
                'Druhá fotografie je blíž a intimnější - čelo na čele, ruka opřená o zeď vedle nich. Taková kompozice funguje dobře v okamžiku, kdy pár na chvíli přestane pózovat a jen tak spolu stojí, což se snažím vždy vyčkat, než spoušť skutečně zmáčknu.',
                'Městské prostředí a cihlové zdi obecně používám tam, kde chci portrét zjednodušit na dvě věci - gesto a světlo. Bez louky nebo krajiny v pozadí musí fotografii nést hlavně samotný pár, což klade větší nároky na výraz a držení těla než na okolí.',
                'Při focení u zdi obvykle hledám rovnou linku, podél které mohu páru navrhnout, kam se postavit, a pak už jen čekám na přirozený moment mezi jednotlivými pokyny. Většina dobrých fotografií u mě vznikne právě v těch mezerách, ne v okamžiku, kdy pár vědomě pózuje.',
                'Podobné portrétní série fotím i na dalších svatbách pod značkou RB Videos & Photos v Olomouci a okolí. Kromě klasické venkovní krajiny tak nabízím i tento městštější, jednodušší styl, pokud se k páru a lokalitě lépe hodí.',
                'Další svatební fotografie i informace o svatebním videu najdete na blogu, kompletní přehled služeb pak v ceníku.',
            ],
        ],
        [
            'slug' => 'svatba-lesni-kour',
            'title' => 'Svatební portréty v lese s kouřovým efektem',
            'meta_title' => 'Svatební portréty v lese s kouřovým efektem | RB Videos & Photos',
            'alt_prefix' => 'Svatební portréty v lese s kouřovým efektem',
            'date' => null,
            'excerpt' => 'Svatební portréty v lese, kde kouřový efekt dodává fotografiím atmosféru a jemný kontrast mezi párem a okolní přírodou.',
            'photos' => ['svatba-lesni-kour-01.jpg', 'svatba-lesni-kour-02.jpg', 'svatba-lesni-kour-03.jpg', 'svatba-lesni-kour-04.jpg'],
            'paragraphs' => [
                'Focení v lese patří k mým oblíbeným prostředím pro svatební portréty. Stromy dávají fotografii přirozený rám a když se do toho přidá kouřový efekt, obraz získá ještě jednu vrstvu, aniž by musel pár cokoliv předstírat.',
                'Na první fotce ženich zvedá nevěstu v bílých šatech a oba se u toho smějí. Takový moment se nedá úplně naaranžovat do detailu, jde spíš o to dát páru prostor pro pohyb a být připravený v okamžiku, kdy se to povede.',
                'Další záběr je naopak klidnější. Nevěsta a ženich se objímají a dívají si do očí, v pozadí se rozlévá kouř, který prostor za nimi jemně rozostří. Díky tomu vynikne hlavně jejich výraz, ne to, co se děje kolem.',
                'Na dřevěné lavičce jsem nechal nevěstu opřít hlavu o rameno ženicha. Nešlo o žádnou složitou pózu, jen o chvíli, kdy si pár mohl na moment odpočinout a nemusel nic hrát.',
                'Poslední fotka je blíž k detailu. Nevěsta v krajkových šatech objímá ženicha kolem krku a oba se dívají do očí. U krajky mi jde hlavně o to, aby byla vidět struktura látky, takže se snažím fotit tak, aby na ni dopadalo dost světla i pod korunami stromů.',
                'Kouřový efekt používám cíleně, ne na každém záběru. Funguje nejlépe tam, kde je za párem hloubka, třeba mezera mezi stromy, kam se kouř může rozptýlit. Bez toho by splynul jen do šedé plochy a ztratil smysl.',
                'Podobně přistupuji k lesním i venkovním svatbám obecně pod značkou RB Videos & Photos. Jako svatební fotograf působím v Olomouci a jezdím i na další místa po Moravě, kde hledám podobná přírodní zákoutí s dostatkem prostoru a světla.',
                'Ke svatebním fotografiím nabízím i svatební video, které dokáže zachytit pohyb a atmosféru chvíle, kterou fotografie jen naznačí.',
                'Další svatební série najdete na blogu, přehled možností focení a videa pak v ceníku.',
            ],
        ],
        [
            'slug' => 'svatba-auta-doprovod',
            'title' => 'Svatební auta a doprovod',
            'meta_title' => 'Svatební auta a doprovod | RB Videos & Photos',
            'alt_prefix' => 'Svatební auta a doprovod ženicha',
            'date' => null,
            'excerpt' => 'Pohled na svatební auta a doprovod ženicha, od moderních stříbrných vozů až po historický Aero, který se stal součástí portrétů v lese.',
            'photos' => ['svatba-auta-doprovod-01.jpg', 'svatba-auta-doprovod-02.jpg', 'svatba-auta-doprovod-03.jpg', 'svatba-auta-doprovod-04.jpg', 'svatba-auta-doprovod-05.jpg'],
            'paragraphs' => [
                'Svatební auto bývá na první pohled jen dopravní prostředek, ale při focení se z něj snadno stane další kulisa. Ukazuje se to i na této sérii, která spojuje dvě různé svatby a dva odlišné přístupy k autům jako součásti svatebního dne.',
                'Na první fotce stojí nevěsta v bílých šatech s kyticí spolu se třemi muži v oblecích před dvěma stříbrnými auty, Mercedesem a BMW, ozdobenými stuhami. Dramatická oblačná obloha nad nimi dodala záběru trochu napětí, které bych na jasné modré obloze nedostal.',
                'Na dalším snímku stojí nevěsta se závojem a ženich v obleku přímo mezi oběma vozy. Auta jsem zkomponoval tak, aby rámovala pár po stranách a nechala hlavní pozornost na nich, ne na lesku karoserie.',
                'Druhá dvojice fotek pochází z jiné svatby a jiného přístupu. Místo moderních aut se tu objevuje historický Aero v tmavě modré barvě, zaparkovaný v lese, kde jsem s párem strávil část focení.',
                'Nevěsta a ženich se opírají o kapotu vozu a smějí se, kytice leží položená přímo na kapotě. Historické auto tady nefunguje jako honosný doplněk, ale spíš jako součást přirozeně působící scény v lese.',
                'Ženich v košili s kšandami si sedl na kapotu a nechal se vyfotit s úsměvem, zatímco nevěsta v krajkových šatech je v popředí záměrně rozostřená. Tahle vrstva dopředu dodá snímku hloubku a trochu jiný úhel pohledu na stejnou scénu.',
                'Na posledním záběru se nevěsta v krajkových šatech opírá o kapotu a dívá se na ženicha. Šaty s hlubším výstřihem jsem nechal vyniknout tím, že jsem světlo směroval spíš zezadu a auto v pozadí nechal lehce potlačené.',
                'Ať jde o moderní vozy nebo historický kousek, auto beru hlavně jako plochu a linku v kompozici, ne jako hlavní téma fotografie. Důležitější než značka nebo stáří vozu je, jak se s ním dá pracovat se světlem a pozadím.',
                'Svatební auta a doprovod řeším podobně u většiny svateb, které fotím pod značkou RB Videos & Photos v Olomouci a na Moravě. Ke svatebním fotografiím nabízím i video, takže atmosféru odjezdu novomanželů lze zachytit i v pohybu. Další svatební série najdete na blogu, možnosti focení a videa pak v ceníku.',
            ],
        ],
        [
            'slug' => 'svatba-priprava-a-detail',
            'title' => 'Příprava ženicha a svatební detaily',
            'meta_title' => 'Příprava ženicha a svatební detaily | RB Videos & Photos',
            'alt_prefix' => 'Příprava ženicha a svatební detaily',
            'date' => null,
            'excerpt' => 'Příprava ženicha před obřadem, detail svatebních tenisek a portrét páru v poli při západu slunce.',
            'photos' => ['svatba-priprava-a-detail-01.jpg', 'svatba-priprava-a-detail-02.jpg', 'svatba-priprava-a-detail-03.jpg', 'svatba-priprava-a-detail-04.jpg'],
            'paragraphs' => [
                'Příprava ženicha bývá při svatbě mnohem klidnější než příprava nevěsty, ale i tady se dá najít dost momentů, které stojí za zaznamenání.',
                'Na první fotce si muž v bílé košili upravuje límeček u okna se závěsy. Světlo zvenku jsem nechal projít látkou závěsu, takže dopadá měkce a nevytváří tvrdé stíny na obličeji ani na košili.',
                'Na druhém záběru si zapíná manžetový knoflíček, tentokrát v tmavší místnosti. Tmavší pozadí tu funguje ve prospěch snímku, protože bílá košile se od něj čistě oddělí a pozornost padne přesně tam, kam má, na ruce a detail knoflíčku.',
                'Přípravu ženicha fotím podobně jako přípravu nevěsty, i když s mnohem menším množstvím rekvizit. Jde hlavně o to zachytit soustředění a drobné úkony, které se jinak při svatebním dni snadno přehlédnou.',
                'Mezi detaily nesmí chybět ani boty. Bílé svatební tenisky nevěsty a ženicha jsem doplnil ozdobnou stuhou s nakresleným motivem novomanželů. Takový detail dokáže potvrdit, o čí den vlastně jde, aniž by musel být v záběru kdokoliv z páru.',
                'Poslední fotka z této série vznikla už venku, v poli při západu slunce. Ženich v bílé košili s motýlkem a nevěsta v krajkových šatech stojí v nízkém večerním světle, které jsem využil hlavně kvůli teplému nasvícení a dlouhým stínům na poli kolem nich.',
                'Mám rád kombinaci klidných příprav v interiéru a otevřeného prostoru venku ve stejné sérii. Ukáže se tak celý oblouk dne, od prvních detailů až po portrét v krajině.',
                'Přípravu a detaily fotím jako standardní součást svatebního focení pod značkou RB Videos & Photos v Olomouci a na Moravě. Ke svatebním fotografiím lze přidat i video, které tyhle tiché momenty přípravy dokáže zachytit i se zvukem a pohybem.',
                'Další svatební série najdete na blogu, možnosti focení a videa pak v ceníku.',
            ],
        ],
        [
            'slug' => 'modelka-boho-a-kvetiny',
            'title' => 'Modelky v květinách - boho portrétní série',
            'meta_title' => 'Modelky v květinách - boho série | RB Videos & Photos',
            'alt_prefix' => 'Modelky při boho a andělském portrétním focení s květinami',
            'date' => null,
            'excerpt' => 'Tři romantické portrétní série různých modelek mezi živými květinami a v andělských křídlech, postavené na měkkém světle a přirozené stylizaci.',
            'photos' => ['modelka-boho-a-kvetiny-01.jpg', 'modelka-boho-a-kvetiny-02.jpg', 'modelka-boho-a-kvetiny-03.jpg', 'modelka-boho-a-kvetiny-04.jpg', 'modelka-boho-a-kvetiny-05.jpg', 'modelka-boho-a-kvetiny-06.jpg', 'modelka-boho-a-kvetiny-07.jpg', 'modelka-boho-a-kvetiny-08.jpg'],
            'paragraphs' => [
                'Tahle galerie spojuje tři samostatná portrétní focení se stejným tématem, květiny a jemně nadsazená andělská image. Každá z modelek dostala jinou stylizaci, ale všechny směřují ke stejné náladě, měkké světlo, romantický tón a hodně prostoru pro pohyb.',
                'První modelka pózovala navečer, kdy slunce stálo nízko a mohl jsem ji posadit tak, aby jí procházelo skrz vlasy a věnec z květin za hlavou. Protisvětlo v tuhle dobu samo vykreslí obrys a nemusím nic dalšího přisvětlovat, stačí najít správný úhel a nechat kameru dýchat.',
                'U puntíkovaných šatů jsem u ní chtěl jiný typ energie. Drží kytici nad hlavou a směje se se zakloněnou hlavou, takže záběr má pohyb, aniž by musela dělat cokoliv složitého. Stačí najít moment, kdy je smích skutečný, ne jen zahraný pro kameru.',
                'Druhá modelka fotila v šatech a věnci z živých květin u cihlové zdi, kde drsná struktura pozadí funguje jako kontrast k jemnosti oblečení. Detailní portrét jsem udělal tak, aby bylo vidět jak věnec, tak barevnost šatů, a nechal jsem stromy v pozadí rozostřit do světlých skvrn.',
                'Poslední záběr s ní vznikl u rybníka, kde vysoká tráva a odraz ve vodě dodávají scéně hloubku. Tady jsem záměrně ustoupil dál, aby bylo vidět celé prostředí a šaty z květin nepůsobily izolovaně, ale jako součást krajiny.',
                'Třetí modelka měla úplně jinou polohu. Bílá andělská křídla a bílé body jsem fotil mezi skalami v lese, tedy v prostředí, které je samo o sobě syrové a ostré. Právě ten kontrast mezi tvrdým kamenem a lehkostí křídel dělá fotku zajímavější, než kdyby stála jen na louce.',
                'Na louce jsem naopak nechal prostor kolem ní úplně otevřený. Rozpažené ruce a les v pozadí dávají záběru rozměr, křídla se rozevřou do stran a siluetka je čitelná i z větší vzdálenosti.',
                'Poslední fotka vznikla shora, leží v trávě a křídla jsou rozprostřená kolem ní jako rám. Tenhle úhel dělám rád na závěr focení, protože vyžaduje trpělivost, ale výsledek vypadá jinak než všechny předchozí záběry.',
                'Při portrétním focení tohoto typu se snažím kombinovat konkrétní rekvizity, věnec, květiny, křídla, s přirozeným prostředím, aby stylizace nepůsobila uměle. Každá modelka dostává jasný směr, ale finální výraz a pohyb nechávám na ní.',
                'Podobné portrétní a modelingové série fotím pod značkou RB Videos & Photos v Olomouci a na dalších místech na Moravě, ať už jde o venkovní přírodní lokace, nebo připravený koncept se stylingovými prvky. Další portrétní i svatební galerie najdete na blogu, možnosti focení pak v ceníku.',
            ],
        ],
        [
            'slug' => 'modelka-mestska-editorialni',
            'title' => 'Modelky ve městě - street styl',
            'meta_title' => 'Modelky ve městě - street styl | RB Videos & Photos',
            'alt_prefix' => 'Modelky při street stylovém portrétním focení ve městě',
            'date' => null,
            'excerpt' => 'Street editorialní série tří modelek v ulicích města, od každodenního casual stylingu až po hravější koncept s nákupním vozíkem.',
            'photos' => ['modelka-mestska-editorialni-01.jpg', 'modelka-mestska-editorialni-02.jpg', 'modelka-mestska-editorialni-03.jpg', 'modelka-mestska-editorialni-04.jpg', 'modelka-mestska-editorialni-05.jpg', 'modelka-mestska-editorialni-06.jpg', 'modelka-mestska-editorialni-07.jpg', 'modelka-mestska-editorialni-08.jpg', 'modelka-mestska-editorialni-09.jpg', 'modelka-mestska-editorialni-10.jpg'],
            'paragraphs' => [
                'Tahle galerie spojuje tři samostatná street focení přímo ve městě, bez velké přípravy scény. Chtěl jsem u každé modelky ukázat, jak funguje běžné casual oblečení v reálném prostředí, ulice, schody, řeka, boční uličky, a nechat charakter místa dělat část práce za mě.',
                'První modelka fotila na schodišti u kamenné zdi. Dívá se šikmo vzhůru a usmívá se, takže vznikl přirozený, trochu hravý moment bez potřeby cokoliv režírovat. Kamenná textura zdi navíc dodává fotce strukturu, kterou bych na hladkém pozadí nezískal.',
                'Stejnou modelku jsem pak v černé kožené bundě fotil na dalších místech, u řeky s mostem v pozadí, v sedu na betonové zídce pod stromem a při chůzi uličkou mezi staršími budovami. Líbí se mi sledovat, jak stejný kus oblečení působí jinak podle prostředí, jednou klidněji, podruhé s úsměvem a pohybem.',
                'Druhá modelka pózovala v bílém tričku a riflových kraťasech u zábradlí v ulici s projíždějícími auty. Pohled přes rameno dodává záběru dynamiku a ulice v pozadí zůstává rozmazaná, takže pozornost jde jasně na ni, ne na provoz kolem.',
                'S ní vznikl i poslední, záměrně jiný a hravější koncept s nákupním vozíkem uprostřed ulice. Sezení ve vozíku s nataženýma nohama nebo na jeho okraji láme vážnost předchozích fotek a ukazuje, že i městské portrétní focení může mít nadsázku a humor, pokud to sedí k povaze modelky.',
                'Třetí modelka měla jednoduchou pozici v džínové bundě a černých šatech, s rukama ve vlasech. Stromy v pozadí dodávají zelený kontrast k tmavému oblečení a gesto rukou přirozeně otevírá siluetu.',
                'Kousek dál v parku jsme s ní zpomalili tempo. Pletený béžový overal jsem fotil v klidnější poloze s pohledem dolů, kde si všímám i detailů jako chytré hodinky na zápěstí. Podobně uvolněný je i záběr v pleteném topu při západu slunce, kdy zaklání hlavu a listy rostliny v popředí rozostřím tak, aby dodaly snímku hloubku.',
                'Při focení ve městě raději chodím a hledám místa za pochodu, než abych si vše naplánoval dopředu od stolu. Schody, zídky, uličky i vozík u obchodu, všechno může fungovat jako kulisa, pokud světlo a kompozice sedí u dané modelky.',
                'Podobné street a lifestylové portréty fotím pod značkou RB Videos & Photos v Olomouci a dalších místech na Moravě. Kromě svatebního focení je tenhle typ modelingových sérií běžnou součástí mé práce, další ukázky jsou na blogu a podmínky spolupráce najdete v ceníku nebo přes kontakt.',
            ],
        ],
        [
            'slug' => 'modelka-cafe-a-cervene-saty',
            'title' => 'Modelky - kavárna a červené šaty',
            'meta_title' => 'Modelky - kavárna a červené šaty | RB Videos & Photos',
            'alt_prefix' => 'Modelky při lifestylovém a fashion focení v kavárně a v poli',
            'date' => null,
            'excerpt' => 'Lifestylová série jedné modelky v kavárně a fashion záběry druhé modelky v červených šatech mezi loukou a obilným polem.',
            'photos' => ['modelka-cafe-a-cervene-saty-01.jpg', 'modelka-cafe-a-cervene-saty-02.jpg', 'modelka-cafe-a-cervene-saty-03.jpg', 'modelka-cafe-a-cervene-saty-04.jpg', 'modelka-cafe-a-cervene-saty-05.jpg'],
            'paragraphs' => [
                'Tahle galerie spojuje dvě odlišná focení, komorní kavárenský lifestyle jedné modelky a otevřené fashion záběry druhé modelky v červených šatech venku v poli. Obě části spojuje stejný přístup, nechat modelku dělat běžnou věc a najít v ní ten správný okamžik.',
                'První modelka pózovala v kavárně u stolu s kávou a kyticí. Dívá se do strany, mimo objektiv, takže záběr působí jako zachycený moment, ne jako pozování přímo pro kameru.',
                'Na dalším záběru drží šálek kávy a telefonuje, kytice zůstává na stole jako opakující se prvek. Tenhle typ fotky funguje dobře jako lifestylová ukázka, kdy jde víc o atmosféru prostředí než o výraznou pózu.',
                'Druhá modelka fotila venku v poli s vysokou trávou, v červených šatech, které v zeleni okamžitě vystoupí. Upravuje si vlasy a dívá se do strany, takže gesto působí přirozeně a ne jako nastudovaná pozice.',
                'V sedu v trávě jsem s ní šel do klidnější polohy, kolena k tělu, hlava opřená o kolena a pohled přímo do objektivu. Tenhle záběr je záměrně tišší než ostatní a dává sérii jiné tempo.',
                'Poslední fotka vznikla v obilném poli s rozpaženýma rukama a rozevlátými vlasy pod otevřenou oblohou s obláčky. Chtěl jsem sérii zakončit největším a nejotevřenějším záběrem, který má oproti kavárně úplně jiné měřítko.',
                'Červená barva šatů je v obou venkovních lokacích, v trávě i v obilí, jasně čitelná i z dálky, takže jsem mohl pracovat s širšími záběry a nechat kompozici dýchat. V kavárně jsem naopak šel blíž, protože tam je hlavní hodnota v detailu a výrazu.',
                'Podobné kombinace lifestylového a fashion portrétního focení dělám pod značkou RB Videos & Photos v Olomouci i jinde na Moravě. Další portrétní série najdete na blogu, konkrétní možnosti a ceny focení pak v ceníku.',
            ],
        ],
        [
            'slug' => 'modelka-alternativni-gothic',
            'title' => 'Modelky - alternativní a gotický styl',
            'meta_title' => 'Modelky - alternativní a gotický styl | RB Videos & Photos',
            'alt_prefix' => 'Modelky v alternativním a gotickém stylu při portrétním focení',
            'date' => null,
            'excerpt' => 'Editorial focení v alternativním a gotickém stylu se čtyřmi modelkami, které kombinuje noční městské prostředí, krajkové kostýmy a dvě samostatné artové scény u vody.',
            'photos' => ['modelka-alternativni-gothic-01.jpg', 'modelka-alternativni-gothic-02.jpg', 'modelka-alternativni-gothic-03.jpg', 'modelka-alternativni-gothic-04.jpg', 'modelka-alternativni-gothic-05.jpg', 'modelka-alternativni-gothic-06.jpg', 'modelka-alternativni-gothic-07.jpg', 'modelka-alternativni-gothic-08.jpg', 'modelka-alternativni-gothic-09.jpg', 'modelka-alternativni-gothic-10.jpg', 'modelka-alternativni-gothic-11.jpg', 'modelka-alternativni-gothic-12.jpg', 'modelka-alternativni-gothic-13.jpg', 'modelka-alternativni-gothic-14.jpg', 'modelka-alternativni-gothic-15.jpg'],
            'paragraphs' => [
                'Tahle galerie spojuje čtyři samostatná focení v alternativním a gotickém stylu. Prostředí se měnilo, od klidné terasy přes noční ulice až po vodu, ale stylizace u všech čtyř modelek držela jednotnou linku, tmavé oblečení, výraznější make-up a klidnější, o něco temnější náladu než u běžného portrétu.',
                'První modelka má v galerii nejrozsáhlejší část. Denní focení jsem s ní stavěl na kontrastu ležérnosti a stylu, na terase i v parku šlo hlavně o přirozené světlo a jednoduché pozadí, ať už rozostřenou skleněnou budovu, nebo mrakodrap za zády. Černý outfit v takovém prostředí nepůsobí těžce, spíš čistě.',
                'S ní jsem pak přesunul focení do nočních ulic. Světla aut a lamp v pozadí se rozostřila do měkkých skvrn a portrét tak dostal atmosféru, kterou by studiové světlo nikdy nenahradilo. Stejný princip fungoval i na osvětlené lávce nad vodou. Několik záběrů jsem záměrně pojal hravěji, poskok na eskalátoru v metru nebo pohled zezadu na nástupišti mezi vlaky.',
                'Druhá modelka měla nejvýraznější gotickou polohu série. Pracoval jsem s krajkou, kovovou mříží brány a graffiti na dveřích jako pozadím v historické uličce. Krajkový korzet a síťované punčochy potřebují okolí, které jim barevně nekonkuruje, ale dotváří texturu, cihla, rez i popsaná zeď fungují přesně takhle.',
                'Třetí modelka vznikla jako samostatný vizuální koncept, fotografovaná v kruhové vaně s namodralou vodou. Záběr staví hlavně na barvě a kompozici a v galerii funguje jako klidnější, výtvarnější bod mezi ostrými večerními záběry.',
                'Čtvrtá modelka stojí po kolena ve vodě jezera s lesem v pozadí, v černých krajkových šatech. Podobně jako u scény ve vaně jde hlavně o barvu a kompozici, tenhle záběr galerii uzavírá jako druhý klidnější, artový moment.',
                'Při podobném focení mi jde hlavně o to, aby styling, prostředí a světlo táhly za jeden provaz. Alternativní a gotická stylizace snese víc kontrastu i tmy, ale pořád potřebuje světlo, které portrét čte, ne jenom zahaluje.',
                'Podobné editorialy fotím pod značkou RB Videos & Photos v Olomouci a okolí, focení lze domluvit i s dojezdem po dalších místech Moravy.',
                'Další portrétní a modelingové série najdete na blogu, rozsah a ceny jednotlivých focení pak v ceníku.',
            ],
        ],
        [
            'slug' => 'modelka-boudoir-interier',
            'title' => 'Boudoir portrétní focení v interiéru',
            'meta_title' => 'Boudoir portrétní focení v interiéru | RB Videos & Photos',
            'alt_prefix' => 'Modelky při boudoir portrétním focení v interiéru',
            'date' => null,
            'excerpt' => 'Komorní boudoir focení tří modelek, které střídá měkké okenní světlo, hotelový pokoj, industriální garáž i les s rezavou kovovou mříží.',
            'photos' => ['modelka-boudoir-interier-01.jpg', 'modelka-boudoir-interier-02.jpg', 'modelka-boudoir-interier-03.jpg', 'modelka-boudoir-interier-04.jpg', 'modelka-boudoir-interier-05.jpg'],
            'paragraphs' => [
                'Boudoir focení stojí hlavně na světle, které nemusí nic dokazovat, stačí, když je měkké a jde odněkud odshora nebo ze strany. Tahle galerie spojuje tři samostatná focení ve stejném stylu, v hotelovém pokoji, garáži a v lese, aby prostředí nepůsobilo furt stejně.',
                'První modelka je na úvodním záběru postavená na siluetě u prosvětleného okna se závěsy. Světlo zezadu obkresluje postavu a krajkový top v něm funguje spíš jako textura než jako detail, který má diváka zastavit.',
                'S ní vznikl i záběr v hotelovém pokoji, jinou cestou, blíž a klidněji. Malá lampička u postele dodává scéně teplé, trochu intimní světlo a pozice s koleny přitaženými k tělu působí přirozeně, ne jako nastudovaná póza.',
                'Poslední záběr s touhle modelkou je čistě kompoziční hříčka, fotil jsem shora, přes bílý fotorámeček, který drží v ruce, na dřevěnou podlahu. Rámeček uvnitř záběru vytváří obraz v obraze a dodává celé sérii lehčí, hravější závěr.',
                'Druhá modelka fotila v podzemní garáži, studené a industriální, a přesně to jsem chtěl využít. Auta v pozadí a tvrdší světlo stojí v kontrastu ke krajkovému oblečení, a ten kontrast dělá záběr zajímavější, než kdyby vznikl v klasickém studiu.',
                'Třetí modelka fotila v lese, kde jsem pracoval s rezavou kovovou mříží, o kterou se opírala. Podobné prvky beru jako hotovou kompozici, stačí najít úhel, kde mříž dorámuje postavu, a nemusím nic dalšího přidávat.',
                'U boudoir focení obecně dbám na to, aby si každá modelka prostředí i pozice sama vyzkoušela, než spustím spoušť. Nejlepší záběry vznikají, když je člověk uvolněný a ví, co se s ním v tu chvíli děje, ne když jen plní zadané pokyny.',
                'Boudoir a intimní portréty fotím pod značkou RB Videos & Photos v Olomouci, focení je možné domluvit i v jiných interiérech nebo lokacích po Moravě podle toho, co k tématu sedí.',
                'Další portrétní série najdete na blogu, podmínky a ceny focení pak v ceníku, případně mě rovnou kontaktujte.',
            ],
        ],
        [
            'slug' => 'firemni-portret',
            'title' => 'Firemní a business portrétní focení',
            'meta_title' => 'Firemní a business portrétní focení | RB Videos & Photos',
            'alt_prefix' => 'Firemní a business portrét v exteriéru',
            'date' => null,
            'excerpt' => 'Business portréty spojující profesionální působení s přirozeným výrazem, foceno venku u firemní budovy.',
            'photos' => ['firemni-portret-01.jpg', 'firemni-portret-02.jpg'],
            'paragraphs' => [
                'Firemní portrét má jeden úkol navíc oproti běžnému portrétu, musí obstát v mnoha různých použitích, na webu, v prezentaci i na vizitce, a přitom pořád vypadat jako fotografie konkrétního člověka, ne jako šablona.',
                'U prvního záběru jsem zvolil venkovní prostředí a světlý kostým. Dlažba a tmavý dřevěný nábytek v pozadí dodávají scéně strukturu, aniž by odváděly pozornost, a uvolněný úsměv dělá portrét přístupným, ne strojeným.',
                'Druhý portrét jde jinou cestou. Tmavé business šaty proti skleněné budově a zkřížené ruce působí sebejistěji a klidněji, spíš zamyšlený pohled do strany než úsměv do objektivu. Obě polohy z jednoho focení pak firma může použít podle toho, kam se portrét hodí, na web, do tiskové zprávy nebo na LinkedIn.',
                'Světlo u firemních portrétů řeším většinou venku, i když to firmy často nečekají. Přirozené světlo kolem budovy dá portrétu měkčí přechody, než jaké bych dostal z jednoho blesku ve stereotypní studiové kompozici, a zároveň nese kontext, kde firma sídlí.',
                'Při focení se snažím hodně mluvit a dávat konkrétní, drobné pokyny, o co níž bradu, váhu na druhou nohu, ruce jinak, protože strnulost je u business portrétu nejčastější problém. Většina lidí se před fotoaparátem přirozeně napne, a moje práce je hlavně to napětí sundat.',
                'Z jednoho focení obvykle vznikne víc variant, jiný úhel, jiný výraz, jiné pozadí, aby firma měla na výběr a nemusela se za pár měsíců vracet kvůli jedné konkrétní situaci, na kterou zrovna potřebuje portrét.',
                'Cílem není portrét, který vypadá jako z fotobanky, ale takový, který pořád nese něco osobního, i když jde o formální, firemní zadání.',
                'Firemní a business portréty fotím pod značkou RB Videos & Photos v Olomouci, ať už jde o jednotlivce nebo celý tým, focení lze domluvit i přímo v prostorách firmy nebo jinde po Moravě.',
                'Rozsah a ceny firemního focení najdete v ceníku, další ukázky portrétů pak na blogu, případně mě rovnou kontaktujte.',
            ],
        ],
    ];

    private PDO $db;
    private MediaIngestionService $ingestion;
    private MediaRepository $media;
    private PortfolioRepository $portfolio;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ingestion = new MediaIngestionService();
        $this->media = new MediaRepository();
        $this->portfolio = new PortfolioRepository();
    }

    /** @return array{entries:int,media:int} */
    public function import(int $siteId): array
    {
        $this->prepareBatch($siteId);
        $mediaCount = 0;
        for ($index = 0; $index < $this->batchCount(); $index++) {
            $mediaCount += $this->importBatchItem($siteId, $index)['media'];
        }
        return ['entries' => $this->batchCount(), 'media' => $mediaCount];
    }

    public function batchCount(): int
    {
        return count(self::MANIFEST) + count(self::MIX_MANIFEST) + count(self::VIDEO_MANIFEST);
    }

    /** Validate all files before deleting the old portfolio. */
    public function prepareBatch(int $siteId): void
    {
        $this->preflight();
        $this->removeAllPortfolioEntries($siteId);
    }

    public function preflight(): void
    {
        if (!extension_loaded('gd')) {
            throw new RuntimeException('PHP rozšíření GD není načtené; nové portfolio vyžaduje tvorbu obrazových variant.');
        }
        $sourceRoot = dirname(__DIR__, 2) . '/' . self::SOURCE_DIR;
        if (!is_dir($sourceRoot)) {
            throw new RuntimeException('Chybí zdroj fotografií nového portfolia: ' . $sourceRoot);
        }
        $sourceRootMix = dirname(__DIR__, 2) . '/' . self::SOURCE_DIR_MIX;
        if (!is_dir($sourceRootMix)) {
            throw new RuntimeException('Chybí zdroj fotografií smíšeného portfolia: ' . $sourceRootMix);
        }
        $this->validateManifest($sourceRoot, $sourceRootMix);
    }

    /** @return array{media:int,label:string} */
    public function importBatchItem(int $siteId, int $index): array
    {
        $photoCount = count(self::MANIFEST);
        $mixCount = count(self::MIX_MANIFEST);
        if (isset(self::MANIFEST[$index])) {
            return $this->importPhotoBatchItem($siteId, self::MANIFEST[$index], $index, self::SOURCE_DIR);
        }
        if (isset(self::MIX_MANIFEST[$index - $photoCount])) {
            return $this->importPhotoBatchItem($siteId, self::MIX_MANIFEST[$index - $photoCount], $index, self::SOURCE_DIR_MIX);
        }
        if (isset(self::VIDEO_MANIFEST[$index - $photoCount - $mixCount])) {
            return $this->importVideoBatchItem($siteId, self::VIDEO_MANIFEST[$index - $photoCount - $mixCount], $index);
        }
        throw new RuntimeException('Neplatný index dávky portfolia: ' . $index);
    }

    /** @param array<string,mixed> $record */
    private function importPhotoBatchItem(int $siteId, array $record, int $index, string $sourceDir): array
    {
        $sourceRoot = dirname(__DIR__, 2) . '/' . $sourceDir;
        // A lost HTTP response may repeat the same checkpoint. Replace only this
        // canonical slug so the retry is idempotent without touching other batches.
        foreach ($this->portfolio->allBySite($siteId) as $existingEntry) {
            if ((string)($existingEntry['slug'] ?? '') === $record['slug']) {
                $this->portfolio->delete((int)$existingEntry['id']);
            }
        }
        $mediaIds = [];
        foreach ($record['photos'] as $photoIndex => $filename) {
            $mediaIds[] = $this->ingestImage($siteId, $record, $sourceRoot . '/' . $filename, $photoIndex + 1);
        }
        $entryId = $this->portfolio->create([
            'site_id' => $siteId,
            'title' => $record['title'],
            'slug' => $record['slug'],
            'description' => $this->descriptionHtml($record),
            'video_url' => null,
            'status' => 'published',
            'published_at' => $record['date'] !== null ? $record['date'] . ' 12:00:00' : null,
            'sort_order' => $index + 1,
            'meta_title' => $record['meta_title'],
            'meta_description' => $record['excerpt'],
        ]);
        $this->portfolio->setMedia($entryId, $mediaIds);
        return ['media' => count($mediaIds), 'label' => $record['title']];
    }

    /** No photos: the cover comes from the YouTube thumbnail. @param array<string,mixed> $record */
    private function importVideoBatchItem(int $siteId, array $record, int $index): array
    {
        foreach ($this->portfolio->allBySite($siteId) as $existingEntry) {
            if ((string)($existingEntry['slug'] ?? '') === $record['slug']) {
                $this->portfolio->delete((int)$existingEntry['id']);
            }
        }
        $entryId = $this->portfolio->create([
            'site_id' => $siteId,
            'title' => $record['title'],
            'slug' => $record['slug'],
            'description' => '<p>' . htmlspecialchars((string)$record['excerpt'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>',
            'video_url' => $record['video_url'],
            'content_type' => 'video',
            'status' => 'published',
            'published_at' => $record['date'] !== null ? $record['date'] . ' 12:00:00' : null,
            'sort_order' => $index + 1,
            'meta_title' => $record['meta_title'],
            'meta_description' => $record['excerpt'],
        ]);
        $this->portfolio->setMedia($entryId, []);
        return ['media' => 0, 'label' => $record['title']];
    }

    /** Refresh only SEO titles and accessible media labels of the supplied portfolio. */
    public function refreshSeoMetadata(int $siteId): void
    {
        $entryStmt = $this->db->prepare(
            'SELECT id FROM portfolio_entries WHERE site_id = ? AND slug = ? LIMIT 1'
        );
        $titleStmt = $this->db->prepare(
            'UPDATE portfolio_entries SET meta_title = ?, meta_description = ?, updated_at = NOW() WHERE id = ?'
        );

        foreach (self::MANIFEST as $record) {
            $entryStmt->execute([$siteId, $record['slug']]);
            $entryId = (int)($entryStmt->fetchColumn() ?: 0);
            if ($entryId <= 0) {
                throw new RuntimeException('Portfolio galerie pro SEO opravu nebyla nalezena: ' . $record['slug']);
            }

            $titleStmt->execute([$record['meta_title'], $record['excerpt'], $entryId]);
            foreach ($this->portfolio->getMedia($entryId) as $index => $mediaRow) {
                $this->media->update((int)$mediaRow['id'], [
                    'alt_text' => $this->imageAlt($record, $index + 1),
                    'title' => $record['title'],
                ]);
            }
        }
    }

    /**
     * 2026-09-07 fix: the five "modelka-*" mixed-batch galleries actually show
     * two to four different models spliced together under one theme-based
     * slug. MIX_MANIFEST now writes them correctly, but these galleries were
     * already imported on existing sites before the fix, so already-seeded
     * rows keep the old one-model copy until this runs. Updates title,
     * description, meta title/description and photo alt/title text only -
     * slugs, photos and sort order stay untouched.
     */
    public function refreshMixManifestModelCopy(int $siteId): void
    {
        $entryStmt = $this->db->prepare(
            'SELECT id FROM portfolio_entries WHERE site_id = ? AND slug = ? LIMIT 1'
        );
        $updateStmt = $this->db->prepare(
            'UPDATE portfolio_entries SET title = ?, description = ?, meta_title = ?, meta_description = ?, updated_at = NOW() WHERE id = ?'
        );

        foreach (self::MIX_MANIFEST as $record) {
            if (!str_starts_with((string)$record['slug'], 'modelka-')) {
                continue;
            }

            $entryStmt->execute([$siteId, $record['slug']]);
            $entryId = (int)($entryStmt->fetchColumn() ?: 0);
            if ($entryId <= 0) {
                continue;
            }

            $updateStmt->execute([
                $record['title'],
                $this->descriptionHtml($record),
                $record['meta_title'],
                $record['excerpt'],
                $entryId,
            ]);
            foreach ($this->portfolio->getMedia($entryId) as $index => $mediaRow) {
                $this->media->update((int)$mediaRow['id'], [
                    'alt_text' => $this->imageAlt($record, $index + 1),
                    'title' => $record['title'],
                ]);
            }
        }
    }

    private function validateManifest(string $sourceRoot, string $sourceRootMix): void
    {
        $expected = [];
        $slugs = [];
        foreach (self::MANIFEST as $record) {
            if (isset($slugs[$record['slug']])) {
                throw new RuntimeException('Duplicitní slug nového portfolia: ' . $record['slug']);
            }
            $slugs[$record['slug']] = true;
            if ($record['photos'] === [] || $record['paragraphs'] === []) {
                throw new RuntimeException('Galerie nemá fotografie nebo text: ' . $record['slug']);
            }
            foreach ($record['photos'] as $filename) {
                if (isset($expected[$filename])) {
                    throw new RuntimeException('Fotografie je přiřazena více galeriím: ' . $filename);
                }
                $expected[$filename] = true;
                if (!is_file($sourceRoot . '/' . $filename)) {
                    throw new RuntimeException('Chybí fotografie nového portfolia: ' . $filename);
                }
            }
        }

        $expectedMix = [];
        foreach (self::MIX_MANIFEST as $record) {
            if (isset($slugs[$record['slug']])) {
                throw new RuntimeException('Duplicitní slug nového portfolia: ' . $record['slug']);
            }
            $slugs[$record['slug']] = true;
            if ($record['photos'] === [] || $record['paragraphs'] === []) {
                throw new RuntimeException('Galerie nemá fotografie nebo text: ' . $record['slug']);
            }
            if (count($record['paragraphs']) < 7) {
                throw new RuntimeException('Galerie smíšeného portfolia má méně než 7 odstavců: ' . $record['slug']);
            }
            foreach ($record['photos'] as $filename) {
                if (isset($expectedMix[$filename])) {
                    throw new RuntimeException('Fotografie je přiřazena více galeriím: ' . $filename);
                }
                $expectedMix[$filename] = true;
                if (!is_file($sourceRootMix . '/' . $filename)) {
                    throw new RuntimeException('Chybí fotografie smíšeného portfolia: ' . $filename);
                }
            }
        }

        foreach (self::VIDEO_MANIFEST as $record) {
            if (isset($slugs[$record['slug']])) {
                throw new RuntimeException('Duplicitní slug nového portfolia: ' . $record['slug']);
            }
            $slugs[$record['slug']] = true;
            if (trim((string)($record['video_url'] ?? '')) === '') {
                throw new RuntimeException('Video galerie nemá video_url: ' . $record['slug']);
            }
        }

        $actual = [];
        foreach (glob($sourceRoot . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [] as $path) {
            $actual[basename($path)] = true;
        }
        $unmapped = array_diff_key($actual, $expected);
        if ($unmapped !== []) {
            throw new RuntimeException('Zdroj obsahuje nepřiřazené fotografie: ' . implode(', ', array_keys($unmapped)));
        }
        if (count($expected) !== 33) {
            throw new RuntimeException('Nové portfolio musí obsahovat přesně 33 fotografií; manifest obsahuje ' . count($expected) . '.');
        }

        $actualMix = [];
        foreach (glob($sourceRootMix . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [] as $path) {
            $actualMix[basename($path)] = true;
        }
        $unmappedMix = array_diff_key($actualMix, $expectedMix);
        if ($unmappedMix !== []) {
            throw new RuntimeException('Zdroj smíšeného portfolia obsahuje nepřiřazené fotografie: ' . implode(', ', array_keys($unmappedMix)));
        }
        if (count($expectedMix) !== 76) {
            throw new RuntimeException('Smíšené portfolio musí obsahovat přesně 76 fotografií; manifest obsahuje ' . count($expectedMix) . '.');
        }
    }

    private function removeAllPortfolioEntries(int $siteId): void
    {
        foreach ($this->portfolio->allBySite($siteId) as $entry) {
            $this->portfolio->delete((int)$entry['id']);
        }
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM portfolio_entries WHERE site_id = ?');
        $stmt->execute([$siteId]);
        if ((int)$stmt->fetchColumn() !== 0) {
            throw new RuntimeException('Staré portfolio se nepodařilo kompletně odstranit.');
        }
    }

    /**
     * Migrace 098: vraci omyl migrace 095, ktera dala weare5.jpg (fotka fotografu)
     * na prvni misto galerie prace kamila-a-matej a prepsala alt/title media.
     * - z galerie odebere jen media s original_filename weare5.jpg; ostatni fotky
     *   zustanou v puvodnim poradi s cisly 0..n (stejny stav, jaky dava import),
     * - alt/title weare5 vrati na hodnoty z 086 jen tehdy, kdyz jsou porad ty z 095
     *   (admin editaci neprepise).
     * Prace se hleda podle slugu; bez weare5 v galerii je to no-op.
     */
    public function revertMistakenWeare5Cover(int $siteId): void
    {
        $record = null;
        foreach (self::MANIFEST as $candidate) {
            if ($candidate['slug'] === self::MISTAKEN_COVER_SLUG) {
                $record = $candidate;
                break;
            }
        }
        if ($record === null) {
            throw new RuntimeException('Manifest portfolia neobsahuje ' . self::MISTAKEN_COVER_SLUG . '.');
        }

        $stmt = $this->db->prepare('SELECT id FROM media WHERE site_id = ? AND original_filename = ?');
        $stmt->execute([$siteId, self::MISTAKEN_COVER_FILENAME]);
        $coverIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        if ($coverIds === []) {
            return;
        }

        foreach ($this->portfolio->allBySite($siteId) as $entry) {
            if ((string)($entry['slug'] ?? '') !== self::MISTAKEN_COVER_SLUG) {
                continue;
            }
            $entryId = (int)$entry['id'];
            $currentIds = array_map(static fn(array $m): int => (int)$m['id'], $this->portfolio->getMedia($entryId));
            $keptIds = array_values(array_filter($currentIds, static fn(int $id): bool => !in_array($id, $coverIds, true)));
            if ($keptIds !== $currentIds) {
                $this->portfolio->setMedia($entryId, $keptIds);
            }
        }

        $placeholders = implode(',', array_fill(0, count($coverIds), '?'));
        $this->db->prepare(
            "UPDATE media SET alt_text = ?, title = ?
             WHERE site_id = ? AND id IN ({$placeholders}) AND alt_text = ? AND title = ?"
        )->execute([
            self::MISTAKEN_COVER_ORIGINAL_ALT,
            self::MISTAKEN_COVER_ORIGINAL_ALT,
            $siteId,
            ...$coverIds,
            $this->imageAlt($record, 1),
            $record['title'],
        ]);
    }

    /** @param array<string,mixed> $record */
    private function ingestImage(int $siteId, array $record, string $sourcePath, int $position): int
    {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $originalFilename = sprintf('portfolio-%s-%02d.%s', $record['slug'], $position, $extension);
        $stmt = $this->db->prepare('SELECT id, storage_path FROM media WHERE site_id = ? AND original_filename = ? LIMIT 1');
        $stmt->execute([$siteId, $originalFilename]);
        $existing = $stmt->fetch();
        if ($existing) {
            $this->media->update((int)$existing['id'], [
                'alt_text' => $this->imageAlt($record, $position),
                'title' => $record['title'],
            ]);
            $this->assertThumbExists($siteId, (int)$existing['id'], (string)$existing['storage_path']);
            return (int)$existing['id'];
        }

        $alt = $this->imageAlt($record, $position);
        $result = $this->ingestion->ingestUploadedFile($siteId, $sourcePath, $originalFilename, $alt, $record['title'], false);
        $mediaId = (int)($result['media_id'] ?? 0);
        if ($mediaId <= 0) {
            throw new RuntimeException('Media Library nevrátila ID pro ' . basename($sourcePath));
        }
        $this->assertThumbExists($siteId, $mediaId, (string)($result['storage_path'] ?? ''));
        return $mediaId;
    }

    /** @param array<string,mixed> $record */
    private function imageAlt(array $record, int $position): string
    {
        $prefix = trim((string)($record['alt_prefix'] ?? $record['title']));
        return $position === 1 ? $prefix : $prefix . ' - fotografie ' . $position;
    }

    private function assertThumbExists(int $siteId, int $mediaId, string $storagePath): void
    {
        $root = dirname(__DIR__, 2);
        if ($storagePath === '' || !is_file($root . '/' . ltrim($storagePath, '/'))) {
            throw new RuntimeException('Chybí originální soubor média #' . $mediaId);
        }
        foreach ($this->media->variantsByMediaId($mediaId) as $variant) {
            if (($variant['format'] ?? '') === 'thumb' && is_file($root . '/' . ltrim((string)$variant['path'], '/'))) {
                return;
            }
        }
        $this->ingestion->regenerateImageVariants($siteId, $mediaId);
        foreach ($this->media->variantsByMediaId($mediaId) as $variant) {
            if (($variant['format'] ?? '') === 'thumb' && is_file($root . '/' . ltrim((string)$variant['path'], '/'))) {
                return;
            }
        }
        throw new RuntimeException('Chybí thumbnail derivát média #' . $mediaId);
    }

    /** @param array<string,mixed> $record */
    private function descriptionHtml(array $record): string
    {
        $html = '<p><strong>Autor: Zdeněk, fotograf</strong></p>';
        foreach ($record['paragraphs'] as $paragraph) {
            $html .= '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }
        return $html;
    }
}
