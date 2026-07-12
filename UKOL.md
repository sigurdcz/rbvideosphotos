# UKOL: Finalni dorovnani bezova-lens-tale

Datum: 2026-07-12
Zadani: Dorovnat homepage sablony `bezova-lens-tale` podle ciloveho screenshotu a podrobneho komentare, primarne pouze v theme vrstve, s DB-driven obsahem a mobile-first chovanim.

## Kroky

- [x] Nacist oba komentare a porovnat cilovy a aktualni screenshot
- [x] Opravit hlavicku, jednu navigaci, monogram RB, hover a jednotny button system
- [x] Opravit hero: dva bile overlaye, levy obsah, dve CTA a plynule prechody
- [x] Prestavet sluzby/O nas na text + hlavni fotografii + horizontalni nabidku
- [x] Prestavet portfolio na levy editorialni uvod + pravou mozaiku
- [x] Opravit proces: levy uvod, ikony, osu, cisla a rozestupy
- [x] Opravit cenik: levy uvod, tri karty a label doporucene varianty
- [x] Opravit reference na tri karty, ovladani a indikatory
- [x] Opravit blog na dve citelne horizontalni karty s uvodem
- [x] Opravit kontakt na tri panely, FAQ a citelny footer
- [x] Prodlouzit a zjemnit storyline pres header i footer
- [x] Stabilizovat filmovy pas a pridat theme galerii po kliknuti
- [x] Overit mobilni layout, runtime assety, syntax a regresni testy
- [x] Provest Wiki Reflection a uzavrit vysledek

## Poznamky

Akceptace vychazi z prvniho screenshotu (cil) a nasledujicich screenshotu (aktualni stav). Spodni panel s Cancel/Finish se ignoruje jako cizi nahravaci UI.

Co se zachovava: pocet obsahovych sekci, DB obsah, pocet prvku a filmovy pas s fotografiemi. Co se opravuje: cela prezentacni kompozice, navigace, overlaye, hierarchie, gridy, interakce, barvy a responzivita.

Zakaz: nevkladat klientsky obsah natvrdo do theme. Core menit pouze pokud theme vrstva objektivne nedokaze spotrebovat existujici DB data.

Zjisteny nutny core kontrakt: legacy homepage nevkladala DB kontaktni formular ani FAQ do DOM pro tuto sablonu. `public/index.php` a `PublishService` proto symetricky pridavaji skryte `data-blt-*` zdroje z jiz existujicich DB bloku; zadny obsah ani vzhled se do core nepresouva.

Storyline byla presunuta z `<main>` na uroven celeho theme shellu, aby mohla bez preruseni prochazet headerem, obsahem i footerem.

Theme JavaScript byl prepsan jako idempotentni DB-driven kompozitor: skryva legacy navigaci, stabilne odvozuje monogram RB, radi kapitoly podle cile, sklada O nas/portfolio/proces/cenik/reference/blog/kontakt, strankuje recenze, spotrebovava skryty DB kontakt a FAQ a pridava pristupnou galerii filmoveho pasu. Film uz neni scroll transformovan, cimz odpada poskakovani.

Filmovy `<aside>` uz neni `aria-hidden`, protoze jeho policka jsou po inicializaci klavesove ovladatelna tlacitka galerie; ma vlastni pristupny popisek.

Finalni CSS vrstva sjednocuje gradientni tlacitka a shimmer hover, bile hero overlaye, editorialni dvousloupcove kapitoly, skutecne ikony procesu, featured cenik, recenzni carousel, citelne blogove karty, tri kontaktni panely, svetly footer a breakpointy 1040/680 px. Vsechny selektory jsou scoped pod `.theme-bezova-lens-tale` a jsou zamerne posledni kvuli legacy runtime specificite.

Hero bily generalni veil respektuje administracni `--cms-video-overlay-opacity`; samostatny levy gradient zustava druhou vrstvou. Hero ma vlastni uzel `01`, dalsi kapitoly se cisluji od `02`.

Pri cascade kontrole byl nalezen starsi `Target-composition correction` blok fyzicky za finalni vrstvou. Je explicitne deaktivovan v `@media (max-width: 0px)`, aby jeho historicka `!important` pravidla nemohla prepisovat novy design.

Overeni: `php -l` pro runtime i publish bez chyb; Windows JScript parser prijal theme JS; HTTP preview vratil 200 a obsahuje `data-blt-home-sources`; generovane CSS/JS obsahuji vsechny nove komponenty; `php tests/run.php` prosel 18/18; `git diff --check` bez obsahovych chyb. Breakpointy 1040/680 px byly overeny ve vygenerovanem CSS. Vestaveny browser se pred navigaci znovu zastavil na chybejicim `sandboxPolicy`, proto nebyl porizen novy desktop/mobile screenshot.

Dokumentace: README ani ARCHITECTURE se nemeni, protoze verejny theme kontrakt zustal DB-first; pouze se rozsiril existujici symetricky runtime/publish data-source mechanismus. Wiki Reflection: dotcene domeny architecture/pipelines/tools jsou `ok` a dossier uz explicitne pokryva DB theme data sources, runtime/publish symetrii, theme specificitu, idempotentni JS a mobilni navigaci. Verdikt SKIP.

## Vysledek

Dokoncena druha velka vizualni iterace `bezova-lens-tale`: jedna navigace, RB monogram, sjednocena animovana tlacitka, bile dvouvrstve hero, cilove poradi a kompozice vsech kapitol, DB kontakt/FAQ, carousel recenzi, filmova galerie, prubezna storyline, svetly footer a mobile-first breakpointy. Zbyva pouze obrazova pixelova kontrola po skutecnem renderu, kterou v tomto behu zablokovalo prostredi browseru.
