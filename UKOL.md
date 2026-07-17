# UKOL: Refactoring šablonování na PHTML systém (7 fází)

Datum: 2026-07-17
Zadání: Přestat šablonovat přes HTML layouty s hardcodeovaným generováním HTML v PHP; implementovat čistý PHTML view systém (jako Blade v Laravelu) s per-stránka/archetyp overridey a jedním sjednoceným render pipeline pro live i publish. Orchestrace přes subagenty s modelem podle náročnosti operace.
Hotovo když: Všech 7 fází (viz `TEMPLATE_REFACTOR_PLAN.md`) implementováno a commitnuto, golden testy (`php tests/run.php --test-goldens`) projdou nebo mají výslovně schválené rozdíly, visual-screenshot-score-loop QA ✓ na 390×844 a 1920×1080 po každé fázi, bez regresí v admin/frontend/publish/tématech/médiích.

## Kroky

### Fáze 0 — Golden-master testy (záchranná síť) [HOTOVO]
- [x] Prozkoumat `public/index.php` (routing, `tryServe*`) a `PublishService.php`
- [x] Identifikovat nedeterministické prvky výstupu (timestamp, CSRF, asset hashe, copyright rok)
- [x] Implementovat `tests/GoldenMasterRunner.php` (update/test/report, LCS diff)
- [x] Guard v `public/index.php` pro test bootstrap bez dopadu na produkci
- [x] Rozšířit `tests/run.php` o `--update-goldens` / `--test-goldens`
- [x] Vygenerovat baseline: 12 live rout + 14 publish cílů
- [x] Ověřit `php tests/run.php` (27 testů + 26 golden checků, exit 0)
- [x] Commit `test(templates): add golden-master runner...` (88acda8)

### Fáze 1 — View engine core (žádná změna chování)
- [ ] Delegovat na subagenta (model dle model-check)
- [ ] `App\View\ViewEngine`, `ViewResolver`, `ViewContext`, `LegacyLayoutView`
- [ ] Unit testy: escaping, kaskáda resoluce, realpath containment, slot/partial
- [ ] Ověřit: nic zatím nevolá nový engine, golden testy beze změny
- [ ] Commit `refactor(templates): add PHTML view engine core`

### Fáze 2 — Sjednocení pipeline (PageComposer)
- [ ] Delegovat na subagenta (vyšší náročnost — duplicitní extrakce)
- [ ] Extrakce sdílených funkcí z `public/index.php` + `PublishService` do `App\Services\Render\*`
- [ ] `PageComposer` s módy live/publish
- [ ] Golden diff prázdný nebo schválený
- [ ] Commit `refactor(templates): unify live and publish render pipeline`

### Fáze 3 — Layout a chrome do PHTML
- [ ] Delegovat na subagenta
- [ ] `layouts/default.phtml` + partials (nav, footer, hero, breadcrumb, cookie-consent)
- [ ] Přesun `getSharedComponentCss()` do assets
- [ ] `elegant-white3` dostane `views/layout.phtml`
- [ ] Golden + visual QA (390×844, 1920×1080)
- [ ] Commit `refactor(templates): move layout chrome to PHTML partials`

### Fáze 4 — Speciální archetypy (9 samostatných commitů)
- [ ] faq: ViewModel + `archetypes/faq.phtml`, smazat heredoc, golden + visual QA
- [ ] sluzby: totéž
- [ ] cenik: totéž
- [ ] reviews/hodnoceni: totéž
- [ ] blog-listing: totéž
- [ ] blog-detail: totéž
- [ ] portfolio-listing: totéž
- [ ] portfolio-detail: totéž
- [ ] kontakt (poslední — CSRF, honeypot, throttling): totéž

### Fáze 5 — Homepage a generické stránky
- [ ] Delegovat na subagenta
- [ ] `HomepageData` + `archetypes/homepage.phtml` (sekce jako data, ne HTML)
- [ ] `archetypes/page.phtml` pro generické CMS stránky (legacy fragment)
- [ ] Golden + visual QA

### Fáze 6 — Theme overridy a úklid
- [ ] Delegovat na subagenta
- [ ] Manifest v2 (`views_dir`, `overrides.<pageKey>.view`)
- [ ] Migrace theme hacků z core do témat
- [ ] Smazat mrtvé `inject*`/`build*` funkce a duplicitní kopie
- [ ] Aktualizace `ARCHITECTURE.md`
- [ ] Finální golden + visual QA všech stránek

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
