# UKOL: SEO, accessibility a breadcrumbs

Datum: 2026-07-03
Zadani: Provest relevantni kroky z auditu v `UKOL.md` a pridat drobeckovou navigaci na vsechny verejne stranky krome homepage.

## Cil

Web ma zustat vizualne stejny nebo lepsi, ale musi byt technicky citelnejsi pro vyhledavace, AI agenty, ctecky obrazovky a Lighthouse. Neupravovat design od nuly, neprepisovat web na jiny framework a nevymyslet neexistujici obsah.

## Kroky

- [x] 1. Inventura projektu a zdroju pravdy
  Popis: Zmapovat verejny render, publish pipeline, sablony, theme CSS/JS, admin formulare, media pipeline, sitemap, robots, llms, SEO renderer a JSON-LD.
  Hotovo kdyz: Je jasne, ktere soubory ovlivnuji homepage, sluzby, nase prace, blog, cenik, FAQ, kontakt, sitemap, robots a llms.

- [x] 2. Baseline audit pred upravami
  Popis: Ziskat aktualni stav hlavni produkcni stranky a dulezitych URL z lokalniho kodu.
  Hotovo kdyz: Existuje seznam realnych problemu rozdeleny na SEO, accessibility, performance, strukturovana data, crawlability a agentic browsing.

- [x] 3. Semanticka struktura HTML a drobeckova navigace
  Popis: Pridat skip link, stabilni `main` kotvu a drobeckovou navigaci na vsechny verejne stranky mimo homepage.
  Hotovo kdyz: Homepage drobecky nema, ostatni stranky maji viditelne breadcrumbs a BreadcrumbList JSON-LD.

- [x] 4. Navigace, odkazy a interni prolinkovani
  Popis: Zkontrolovat dulezite odkazy a doplnit bezpecne `rel` pro externi odkazy otevrene v novem okne.
  Hotovo kdyz: Externi odkazy s `target="_blank"` maji `noopener noreferrer`.

- [x] 5. Accessibility tree a klavesnice
  Popis: Zlepsit programove nazvy, `aria` atributy, skip link a ovladani modal/lightbox prvku.
  Hotovo kdyz: Interaktivni prvky maji jasny nazev a web jde zakladne ovladat klavesnici.

- [x] 6. Formulare pro lidi i AI agenty
  Popis: U kontaktniho formulare doplnit `label for/id`, `name`, `autocomplete`, typy poli a popisky bez zhorseni UX.
  Hotovo kdyz: Agent i ctecka poznaji ucel formulare, povinna pole, kontaktni udaje, typ sluzby, termin, zpravu, souhlas a vysledek po odeslani.

- [x] 7. SEO metadata a JSON-LD
  Popis: Doplnit BreadcrumbList JSON-LD podle realne viditelne drobeckove navigace.
  Hotovo kdyz: JSON-LD odpovida realnemu obsahu a nic si nedomysli.

- [x] 8. Validace po upravach
  Popis: Spustit dostupne lokalni kontroly: PHP syntax, diff check a kontrolu zmen.
  Hotovo kdyz: Existuje seznam provedenych kontrol a jasne oznacene rucni kontroly, ktere nelze automaticky overit lokalne.

- [x] 9. Commit a push
  Popis: Zmeny rozdelit podle kontextu, commitnout a pushnout.
  Hotovo kdyz: `git status` je cisty a zmeny jsou na `origin/master`.

## Poznamky

- Priorita pro tento implementacni pruchod: drobeckova navigace, BreadcrumbList schema, skip link, kontaktni formular, lightbox ovladani a bezpecne odkazy.
- Nezavadet skryte fallbacky, ktere maskuji chybu. Pokud chybi data pro schema, cenu, lokalitu nebo kontakt, oznacit to jako TODO misto domysleni.
- Zachovat brand, barvy a layout. Upravovat hlavne HTML strukturu, atributy, metadata, schema a drobne CSS pro pristupnost.
- `public/index.php`: doplneny centralni helpery pro skip link, `main-content`, bezpecne `rel`, BreadcrumbList JSON-LD a viditelne breadcrumbs pro verejne stranky mimo homepage.
- `public/index.php`: kontaktni formular se normalizuje na propojene label/input atributy, autocomplete, pojmenovany consent checkbox a skryty honeypot s `aria-hidden`.
- `public/index.php`: blogovy lightbox pouziva `button` prvky s aria popisky misto klikacich `span`.
- `public/assets/frontend-runtime.css`: doplnen styl pro skip link a breadcrumbs vcetne mobilni sirky.
- Overeno: `php -l public/index.php` bez chyb.
- Overeno: `git diff --check` bez chyb; Git pouze hlasi standardni CRLF warningy pro Windows checkout.

## Vysledek

Implementace je hotova a pripravena v commitu/pushi.
