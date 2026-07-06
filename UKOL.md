# UKOL: Dokonceni DB-driven kontaktu a Local SEO

Datum: 2026-07-06
Zadani: Dokoncit zbyvajici DB-driven contact/SEO data, aby verejne sablony nepouzivaly fallback adresy a vychazely jen z DB.

## Kroky

- [x] 1. Zmapovat sablony, frontend runtime a admin/content flow kvuli hardcoded datum mimo DB
- [x] 2. Najit kriticke rozpory s cilem SEO editor/content manager a vybrat opravitelne zmeny s nejvetsim dopadem
- [x] 3. Dokoncit DB-driven kontakt a lokalni SEO data bez fallback adresy
- [x] 4. Overit syntax, diff, zakladni runtime predpoklady a markdown dopady
- [x] 5. Dodat kriticke CR/report s nalezy, provedenymi zmenami a zbyvajicimi riziky

## Poznamky

- Success signal: sablony nenesou klientsky/demo obsah, hlavni obsahove texty a media jsou DB-driven, CSS/JS/PHP zmeny nezmensuji schopnost editoru spravovat obsah.
- Pred startem byly v pracovnim stromu pending dokumentacni zmeny z predchozi konsolidace MD souboru; jsou souvisejici s aktualnim CR a zustavaji v rozsahu.
- Nalezeno: nejvetsi rozpor s cilem CMS byl v `HomepageContentService`, kde PHP doplnovalo fotograficke homepage texty, hardcoded CTA labely a vkladalo inline CSS do generovaneho obsahu.
- Upraveno: homepage marketingove textove fallbacky byly odstraneny, CTA fallbacky berou text z titulku DB stranek a styly karet/kontaktu/portfolia se presunuly do shared theme CSS.
- Upraveno: layouty temat uz ve footeru nepublikuji nazvy sablon jako obsah, ale pouze `{site_name}`.
- Upraveno: `jemna-bezova` a `editorial-gold` JS uz nepridavaji vlastni marketingove sekce, staty, proces ani kontaktní copy mimo DB.
- Overeno: `php -l` pro `HomepageContentService` a `ThemeTemplateRegistry` prosel, `git diff --check` nema chyby, cilene `rg` na odstranene marketingove texty je ciste. JS syntax check pres `node --check` nesel spustit, protoze `node` neni v prostredi dostupny.
- Prave reseno: verejne footer/contact bloky uz nemaji inventovanou lokalitu, `DemoContentSeeder` seeda DB kontakt bez falešne adresy a admin kontaktní obrazovky ukazuji varovani, kdyz adresa chybi.
- Ted dovrsuji: doplnit skutecnou adresu do DB seedu a odstranit i posledni fallbacky kolem lokalit, aby Local SEO sedelo na realnem obsahu.
- Bloker: CLI reseed `php database/seeds/seed_photographer.php` skoncil `Database connection failed`, takze nelze v tomto prostredi provest live republish ani aktualizovat `storage/published`.

## Vysledek

Kod a dokumentace jsou upravene pro prisnejsi pravidlo: sablony a theme assety nesmi nest klientsky obsah, ale jen prezentovat DB-driven data. Zbyva dokoncit a znovu overit Local SEO adresu v DB.
