# UKOL: Refactoring šablonování na PHTML systém (7 fází)

Datum: 2026-07-17
Zadání: Přestat šablonovat přes HTML layouty s hardcodeovaným generováním HTML v PHP; implementovat čistý PHTML view systém s per-stránka/archetyp overridey a jedním sjednoceným render pipeline pro live i publish.

Hotovo když: Všech 7 fází (viz `TEMPLATE_REFACTOR_PLAN.md`) je implementováno a commitnuto; golden testy projdou nebo mají výslovně schválené rozdíly a visual QA na 390×844 i 1920×1080 neodhalí regresi v adminu, frontendu, publishi, tématech ani médiích.

## Kroky

### Fáze 0 — Golden-master testy [HOTOVO]

- [x] Golden runner pro 12 live rout a 14 publish cílů, normalizace dynamických hodnot a LCS diff.
- [x] Guard test bootstrapu v `public/index.php`; režimy `--update-goldens` a `--test-goldens` v `tests/run.php`.
- [x] Výchozí baseline vytvořena a ověřena; commit `88acda8` (`test(templates): add golden-master runner...`).

### Fáze 1 — View engine core [HOTOVO]

- [x] `App\View\ViewEngine`, `ViewResolver`, `ViewContext`, `LegacyLayoutView` a fixture/unit testy.
- [x] Resolver chrání před cestami mimo root; engine podporuje escaping, partials, sloty, layouty a atributy inline editoru.
- [x] Ověřeno bez aktivního použití v produkční cestě: 36 testů + 26 golden checků, 0 failures.
- [x] Commit `refactor(templates): add PHTML view engine core`.

### Fáze 2 — Sjednocení pipeline (PageComposer) [HOTOVO]

- [x] Sdílené renderovací funkce přesunuty z `public/index.php` a `PublishService` do `App\Services\Render\*`; `PageComposer` má módy live/publish.
- [x] Zachovány bezpečnostní hranice (žádné SQL v Render, CSRF/auth/realpath beze změny) a jediný kanonický zdroj přesunutých funkcí.
- [x] Golden 26/26 OK bez regenerace baseline; 36 testů + 26 golden checků, 0 failures.
- [x] Commit `0cc77c9` (`refactor(templates): unify live and publish render pipeline`).

### Fáze 3 — Layout a chrome do PHTML [HOTOVO]

- [x] `layouts/default.phtml` a partials pro nav, logo, footer, sociální sítě, hero, breadcrumb a cookie consent.
- [x] Sdílené CSS přesunuto byte-identicky do `app/Views/assets/shared-components.css`; `elegant-white3` používá `views/layout.phtml` přes `ViewFactory`/`ThemeLayoutRenderer`.
- [x] Golden 26/26 OK; vizuální QA homepage a `/cenik` na mobilu i desktopu bez regrese.
- [x] Post-commit review: bezpečnost, escaping, speciální routy, footer/nav právě jednou a 45 testů + 26 golden checků bez failures.
- [x] Commit `91b3b44` (`refactor(templates): move layout chrome to PHTML partials`).

### Fáze 4 — Speciální archetypy [HOTOVO]

- [x] PHTML/ViewModel refaktor: `faq`, `sluzby`, `cenik`, `hodnoceni`, seznam a detail blogu, seznam a detail portfolia.
- [x] Live/publish sdílejí relevantní render cesty; `cenik` používá `CenikPageRenderer::inject()` v `composeLive` i `composePublish`.
- [x] Portfolio a blog detail jsou live-only; samostatný `PortfolioPublishService::publishEntry()` nebyl měněn.
- [x] Nezávislé review potvrdilo správnější publish obsah, zachování bezpečnosti/inline editoru a testy 45 + 26 golden bez failures.
- [x] Visual QA `/faq`, `/sluzby`, `/cenik`, `/hodnoceni` na 390×844 a 1920×1080: HTTP 200, bez PHP warningů a bez layout regresí.

### Fáze 4b — post-commit review [SCHVÁLENO S OPRAVOU]

Nezávislé bezpečnostní/korektnostní review commitů `9c00b16` (blog-listing), `d0eb098` (blog-detail), `417013c` (portfolio-listing), `e3667a0` (portfolio-detail). Verdikt: **SCHVÁLENO S OPRAVOU**.

- [x] **Bod 1 — undefined `$cardsHtml` (POTVRZENO + OPRAVENO):** `public/index.php` `tryServeBlogListingPage()` fallback větev (`else` po `if ($themeActive)` / `elseif layout_html`) odkazovala na nedefinovanou `$cardsHtml` (zavlečeno `9c00b16` při náhradě card-loopu archetypem). Větev je nedosažitelná za současné konfigurace: `applyThemeTemplate()` vrací `$themeActive = true` vždy, když `ThemeTemplateRegistry::resolveForSite()` najde téma — aktivní `elegant-white3` se vždy resolvne, takže se bere `if ($themeActive)` větev. `else` by se spustila jen kdyby (a) žádné téma se neresolvlo A (b) `site.layout_html` byl prázdný → pak PHP 8 warning „Undefined variable“ + prázdný `.bl-grid` (blog bez článků). Opraveno stejným vzorem jako portfolio: dvě hardcoded `<section>` nahrazeny `{$pageContent}` (archetyp už renderuje hero+grid). Commit `fix(templates): fix undefined variable in blog listing fallback branch`.
- [x] **Bod 2 — `PortfolioPublishService` divergence (POTVRZENO — BLOCKER pro Fázi 6, neopraveno):** viz sekce Fáze 6 níže.
- [x] ViewModely (`BlogListingData`, `PortfolioListingData`, `PortfolioDetailData`, `BlogPostData`): jen skaláry/pole, žádné HTML, media přes `MediaUrlResolver::publicMediaUrl()` (root-relative); repozitáře (`BlogRepository`/`PortfolioRepository`/`MediaRepository`) používají prepared statements s bind parametry.
- [x] Archetypy: veškerý dynamický výstup přes `$this->e()`; `raw()` jen na `entry.description` (portfolio-detail) a `post.content` (blog-detail) — admin rich-text sanitizovaný dále v pipeline (`sanitizeFrontendHtml`), shodné s původním heredocem.
- [x] Inline editor klíče 1:1: blog-listing `hero_h1`/`hero_sub` + `blog:{id}:title`/`blog:{id}:excerpt`; portfolio-listing `portfolio:{id}:title` — ověřeno proti `9c00b16~1`/`417013c~1`.
- [x] Golden diffy (blog + nase-prace publish, CHANGED/schválené): směr správný — původní publish výstup měl artefakt `<?xml encoding="UTF-8">` a PRÁZDNÝ výpis (karty se generovaly jen v `public/index.php`, z `PublishService` nedosažitelné); nový výstup je čistý a obsahuje všechny karty. Bez ztráty obsahu.
- [x] Testy: `php tests/run.php` → 45 testů + 26 golden, 0 failures. Oprava bodu 1 nemění dosažitelný výstup (goldeny beze změny).
- [x] Visual QA (headless Chrome, 1920×1080 + 390×844): `/blog`, `/nase-prace`, blog detail, portfolio detail → HTTP 200, bez PHP warningů, profesionální vzhled bez layout regrese (téma elegant-white3, karty, hero, gold CTA). Pozn.: iframe videa na portfolio detailu blokuje síť headless prostředí (externí obsah, ne layout).

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
- [ ] **BLOCKER (z review Fáze 4b) — dvojí zapisovač `storage/published/nase-prace/index.html`:** `PortfolioPublishService::regenerateListingPage()` (`app/Services/PortfolioPublishService.php:146-206`, volaná z `publishEntry()` na `:120`) zapisuje TENTÝŽ soubor `storage/published/nase-prace/index.html` jako `PublishService::publishPage()` (`resolveFilePath('nase-prace')` → `storage/published/nase-prace/index.html`, zápis na `PublishService.php:193-196`), ale STARÝM bare `pl-tile`/`pl-grid` markupem bez theme chrome. `publishEntry()` (→ `regenerateListingPage()`) se spouští při admin CRUD na portfolio položkách se statusem `published`: `ApiController::createPortfolioEntry` (`:343`), `ApiController::updatePortfolioEntry` (`:407`), `PortfolioController` (`:78`, `:144`). **Dopad:** po Fázi 4b je `/nase-prace` sjednocené (elegant-white3 archetyp z `PublishService`), ale první reálná portfolio publish akce v adminu PŘEPÍŠE produkční `/nase-prace` zpět na starý bare vzhled („časovaná bomba“). Goldeny to nezachytí — golden runner publikuje `nase-prace` jen přes `PublishService`, `PortfolioPublishService` se v testech nespouští. Pozn.: divergence je pre-existing (oba zapisovače existovaly i před 4b; 4b jen zbohatil stranu `PublishService`), Fáze 4b `PortfolioPublishService` záměrně neměnila. **Řešení (vyžaduje architektonické rozhodnutí):** buď smazat `regenerateListingPage()` a delegovat regeneraci listingu na `PublishService::publishPage()` (potřebuje `pageId`+`userId` v CRUD kontextu), nebo přepsat `regenerateListingPage()` na `renderArchetypeContent`/PageComposer výstup. Stejně zvážit `buildTilesHtml()`/`syncHomepageTiles()` (homepage `portfolio-tiles` blok — jiný soubor, ale rovněž starý markup).
- [ ] Aktualizace `ARCHITECTURE.md`
- [ ] Finální golden + visual QA všech stránek

## Poznámky

- Golden pokrývá live routy `/`, `/sluzby`, `/cenik`, `/faq`, `/kontakt`, `/blog`, `/nase-prace`, `/hodnoceni`, `/podminky-uziti`, 404 a po jednom detailu blogu a portfolia; publish pokrývá všechny publikované stránky a `generate404()`.
- Golden testy vyžadují dostupnou a naplněnou testovací MySQL databázi (`.env`, `DemoContentSeeder`) a reálného demo uživatele pro `PublishService::publishPage()`.
- Golden soubory se aktualizují jen přes `--update-goldens` a až po ručním schválení změn se commitují.
- Spuštění golden testů skutečně přepublikuje `storage/published/*`; po testu bez zamýšleného publishi vrať změny pomocí `git checkout -- storage/published/`.
- `vendor/autoload.php` je ručně psaný PSR-4 shim `App\` → `app/`; nové třídy se načtou bez regenerace Composeru. `app/View/` jsou třídy a `app/Views/` jsou PHTML šablony.
- Opravený historický testovací problém: globální `ob_start()` v `tests/run.php` brání `headers already sent` při bootstrapu golden runneru.
- Údržba 2026-07-18: archiv hotových fází zkrácen. Otevřené fáze 5 a 6 zůstaly beze změny.

## Výsledek

Fáze 0–4 PHTML refaktoru jsou dokončeny, commitnuty a ověřeny golden testy i cílenou vizuální QA. K dokončení refaktoru zbývají pouze otevřené fáze 5 a 6 uvedené výše.
