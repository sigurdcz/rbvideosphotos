# UKOL: Fáze 0 — Golden-master testy (záchranná síť)

Datum: 2026-07-17
Zadání: Vytvořit golden-master testovací systém (`GoldenMasterRunner` v `tests/`), který zachytí normalizované HTML všech veřejných rout a publish výstupu jako baseline pro ověřování byte-ekvivalence během fází 1–6 refactoringu šablonování.
Hotovo když: `php tests/run.php` projde (existující testy + goldeny, pokud existují), `php tests/run.php --update-goldens` vygeneruje kompletní baseline pro live routy i publish výstup, `php tests/run.php --test-goldens` je porovná a reportuje OK/CHANGED/MISSING, goldeny nejsou automaticky commitnuté (čeká se na schválení uživatele přes `git diff tests/goldens/`).

## Kroky

- [x] Prozkoumat `public/index.php` (routing dispatch, `tryServe*` funkce, závislosti) a `app/Services/PublishService.php`
- [x] Zjistit skutečná demo data v DB (slugy stránek, blog postů, portfolio položek) pro výběr golden rout
- [x] Identifikovat všechny nedeterministické prvky ve výstupu (publish timestamp komentář, CSRF token na `/kontakt`, `date('Y')` copyright, sha1 asset hashe, `data-inline-*` v edit módu)
- [x] Navrhnout minimální bezpečnou úpravu `public/index.php` (guard kolem finálního dispatch bloku přes `return;` když je definovaná testovací konstanta) — bez dopadu na produkční chování
- [x] Implementovat `tests/GoldenMasterRunner.php`: bootstrap `public/index.php` jednou, `renderLiveRoute()`, `renderPublishRoute()`, `normalizeHtmlForGolden()`, update/test/report logika s řádkovým diffem
- [x] Upravit `public/index.php` — přidat guard `if (defined('SEO_CMS_GOLDEN_BOOTSTRAP')) { return; }` před finální try/catch dispatch
- [x] Rozšířit `tests/run.php` o parsování `--update-goldens` / `--test-goldens`, výchozí režim spouští staré testy + golden test (pokud goldeny existují)
- [x] Vytvořit `tests/goldens/live/` a `tests/goldens/publish/` strukturu
- [x] Spustit `php tests/run.php` (ověřit, že staré testy stále procházejí) a `php tests/run.php --update-goldens` (vygenerovat baseline)
- [x] Spustit `php tests/run.php --test-goldens` (ověřit, že čerstvě vygenerované goldeny projdou beze změny)
- [x] Zkontrolovat normalizované goldeny ručně (žádné zbytkové timestampy/tokeny/hashe)
- [x] Nechat uživatele schválit baseline (`git diff tests/goldens/`) — necommitovat automaticky
- [ ] Po schválení uživatelem: commit `test(templates): add golden-master runner for live routes and publish output`

## Poznámky

- Live routy: `/`, `/sluzby`, `/cenik`, `/faq`, `/kontakt`, `/blog`, `/nase-prace`, `/hodnoceni`, 1× blog detail (slug z DB), 1× portfolio detail (slug z DB), `/podminky-uziti` jako generická stránka (náhrada za neexistující `/o-nas` v demo datech), 404.
- Publish výstup: všechny publikované stránky ze `pages` tabulky (přes `PublishService::publishPage`) + `generate404()` — reálně přepisuje `storage/published/*` (to je zamýšlený efekt, jde o skutečný publish pipeline, ne mock).
- `PublishService::publishPage()` vyžaduje `$userId` — použit reálný demo uživatel z `users` tabulky.
- DB (`test` schema, MySQL, `.env`) musí být dostupná a naplněná (`DemoContentSeeder`) — bez ní golden testy selžou; existující testy (security.txt apod.) DB nepotřebují a musí projít i bez ní.
- Golden soubory se negenerují automaticky do gitu — pouze přes `--update-goldens`, commit až po ručním schválení uživatelem.
- Nalezený a opravený bug: ve výchozím režimu (`php tests/run.php` bez flagů) staré testy vypisují PASS/FAIL řádky přímo (bez output bufferingu) ještě před tím, než golden runner poprvé `require`ne `public/index.php` — a to způsobovalo "headers already sent" varování při `session_start()`/`header()` uvnitř bootstrapu. Opraveno jedním `ob_start()` na začátku `tests/run.php`, které obalí celý běh (PHP automaticky flushne buffer při ukončení skriptu).
- Každé spuštění golden testů (default i `--test-goldens`) reálně znovu publikuje všechny stránky (`PublishService::publishPage`), takže dočasně "zašpiní" `storage/published/*.html` (nový timestamp + nový CSRF token ve fallback kontakt bloku na homepage/kontaktu). To je funkční požadavek úkolu (golden musí ověřovat aktuální kód, ne jen soubory na disku), ne chyba — po každém běhu testů je proto potřeba `git checkout -- storage/published/`, pokud nemá jít o skutečnou publikaci.

## Výsledek

Implementován golden-master testovací systém pro Fázi 0 refactoringu šablonování:

- `tests/GoldenMasterRunner.php` (nový): bootstraps `public/index.php` jednou (bez spuštění routing dispatch), renderuje 12 live rout (`home`, `sluzby`, `cenik`, `faq`, `kontakt`, `blog`, `nase-prace`, `hodnoceni`, `generic-page` = `/podminky-uziti`, `404`, `blog-detail` a `portfolio-detail` — poslední dva se slugem dynamicky vybraným z DB) přímým voláním `tryServe*()` funkcí, a 14 publish cílů (všechny publikované stránky z `pages` tabulky přes `PublishService::publishPage()` + `generate404()`). Obsahuje `normalizeHtmlForGolden($html, $editMode = false)`, `update()`, `test()` (s LCS řádkovým diffem pro `+N -M` report) a `compareOne()`.
- `public/index.php`: přidán 4řádkový guard `if (defined('SEO_CMS_GOLDEN_BOOTSTRAP')) { return; }` před finální routing dispatch — bez dopadu na produkční chování (konstanta se mimo test harness nikdy nedefinuje).
- `tests/run.php`: nové CLI režimy `--update-goldens` a `--test-goldens`; výchozí režim (bez flagů) spouští původní testy a navíc — pokud `tests/goldens/` existuje — golden porovnání, vše sečtené do jednoho `$failures` čítače a exit kódu. Přidán globální `ob_start()` kvůli opravě popsané výše.
- `tests/goldens/live/*.normalized.html` (12 souborů) a `tests/goldens/publish/*.normalized.html` (14 souborů) — vygenerované baseline, UTF-8, LF, bez zbytkových timestampů/CSRF tokenů/asset hashů (ověřeno ručně přes grep).
- Ověřeno: `php tests/run.php` (27 starých testů + 26 golden checků, exit 0), `php tests/run.php --update-goldens` (26/26 OK), `php tests/run.php --test-goldens` (26/26 OK), a manuálně simulované scénáře `CHANGED (+N -M)` a `MISSING` reportu (dočasnou korupcí/smazáním golden souboru a jeho obnovením).
- `storage/published/*` ponechán v původním (committed) stavu — republikace během testů byla po ověření vrácena přes `git checkout`.

Čeká se na schválení baseline uživatelem (`git diff tests/goldens/` — v tomto případě nové netrackované soubory, tedy spíš `git status`/prohlédnutí obsahu `tests/goldens/`) a následný commit `test(templates): add golden-master runner for live routes and publish output`.
