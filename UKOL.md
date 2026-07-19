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

### Fáze 4c — kontakt archetyp (poslední, nejrizikovější) [HOTOVO]

Datum: 2026-07-18/19. Poslední z 9 archetypů — obsahuje CSRF, honeypot, throttling. Scope = JEN GET render `/kontakt`. Dokončeno přímo v hlavní session (Sonnet 5) po dvou po sobě jdoucích selháních subagentů kvůli měsíčnímu spending limitu (viz Poznámky) — pokračovalo se přesně tam, kde skončila rozdělaná práce (`kontakt.css` byl už hotový).

- [x] Ověřena skutečná architektura `tryServeKontaktPage()` (~400 ř. heredoc, plná stránka = Vzor A) a POST handler `/kontakt/odeslat` (samostatná větev v `public/index.php`, zcela mimo scope, beze změny)
- [x] `app/Views/assets/kontakt.css` — ověřeno bytově identické s původním heredoc CSS (po EOL normalizaci CRLF→LF, `diff` čistý)
- [x] `App\ViewModels\KontaktPageData::forSite(int $siteId, string $csrfToken, ?string $sentState): array` — čistá data: `contactCards` (phone/email/location/note karty s `inlineKey`), `mapsUrl`, `heroActions` (tel/email), `faqItems` (max 4 aktivní), `form` (csrfToken/honeypotField/sentState). CSRF token je parametr, **nikdy se negeneruje uvnitř ViewModelu**.
- [x] `app/Views/archetypes/kontakt.phtml` — loopy přes `contactCards`/`faqItems`, `$this->e()`/`$this->inlineAttr()`; identifikován a odstraněn `dead code` (starý heredoc počítal `$detailsHtml`/`$mapsHtml` DVAKRÁT — první `ci-row`/`ci-brand`/socials verze byla vždy okamžitě přepsána druhou `ci-card` verzí, tedy 100% nedosažitelná; ověřeno, že žádný jiný kód na ni neodkazuje, než byla vynechána)
- [x] Napojeno `tryServeKontaktPage()` na `ViewFactory::forTheme(null, ['editMode'=>...])->render('archetypes/kontakt', ...)`; smazán heredoc (−324 řádků / +21 řádků v `public/index.php`); POST handler (`/kontakt/odeslat`, CSRF verify, honeypot/throttling) **beze změny, nedotčen**
- [x] Golden: `kontakt live: OK` (prázdný diff), `kontakt publish: OK` (publish větev skutečně nezměněna — `PublishService` pro kontakt nebyl vůbec upraven, dle plánu z rozdělané práce)
- [x] Bezpečnostní regresní test (curl na živém serveru): CSRF token přítomen a neprázdný (`name="_csrf" value="<64hex>"`), honeypot přítomen (`name="website"` — post-processing `normalizeContactFormAccessibility` ho transformuje na `class="hp-honeypot"` stejně jako předtím, ověřeno shodné pro starý i nový render přes golden byte-parity), action URL `/kontakt/odeslat` beze změny, žádné PHP warningy/notice v HTML výstupu, HTTP 200
- [x] Inline editor sanity: přímý `ViewFactory::forTheme(null,['editMode'=>true])->render()` test — `data-inline-key` pro `hero_h1`/`hero_sub`/`contact:phone_1`/`contact:email`/`contact:address_1` všechny přítomné; s `editMode=false` žádný `data-inline-key` neuniká (ověřeno explicitně)
- [x] `php tests/run.php` → 45 testů + 26 golden checků, 0 failures
- [ ] Visual QA (390×844/1920×1080) — **NEPROVEDENO** v této session (žádný headless browser nástroj po ruce, jen curl/textové nástroje); vizuální riziko je nízké (live byte-identický golden = stejný markup/CSS třídy jako před refaktoringem, žádná nová CSS třída), ale doporučuji `visual-screenshot-score-loop` jako follow-up před nasazením na produkci
- [x] Commit `refactor(templates): migrate kontakt page to PHTML archetype`

**Newline gotcha (další příklad pro budoucí archetypy):** kde originál měl DVĚ samostatné řádky, z nichž jeden může být prázdný (`{$detailsHtml}` pak `{$mapsHtml}` na vlastním řádku), a nahrazuješ oba `<?php ... ?>`/`<?= ... ?>` bloky, PHP polkne newline po KAŽDÉM `?>` zvlášť — je potřeba kompenzovat PRO KAŽDÉ polknutí samostatně (zde 2× prázdný řádek, ne 1×). Odhaleno až golden diffem (+2-5 → +1-2 → 0), ne vizuální kontrolou — ukazuje, že golden byte-diff je citlivější než oko.

**BEZPEČNOSTNÍ SHRNUTÍ (pro navazující review):**
- CSRF: generuje se JEDNOU v `tryServeKontaktPage()` přes `\App\Core\Auth::csrfToken()` (nezměněné volání, jen přesunuté do proměnné `$csrfToken` předané ViewModelu/view jako data), validuje se v `/kontakt/odeslat` POST handleru přes `Auth::csrfVerify()` — tato větev nebyla vůbec dotčena.
- Honeypot: pole `name="website"` zachováno; server-side kontrola honeypotu (v POST handleru) nedotčena.
- Throttling: `RequestThrottle` (viz `use App\Security\RequestThrottle` v hlavičce) — použit v POST handleru, mimo scope této fáze, nedotčen.
- Scope striktně dodržen: změněn jen GET render `/kontakt`; `/kontakt/odeslat` POST handler nemá v diffu žádnou změnu (ověřeno `git diff` — beze změny řádků POST větve).

### Fáze 4c — post-commit bezpečnostní review [SCHVÁLENO]

Datum: 2026-07-19. Nezávislý přísný review commitu `f77049b` (nejcitlivější commit refactoringu — CSRF/honeypot/throttling). **Verdikt: SCHVÁLENO** (žádný BLOCKER, žádná oprava nutná). Výsledky všech 9 kontrolních bodů:

1. **Celý diff** — OK. 5 souborů: `UKOL.md` (+35), `KontaktPageData.php` (nový, 103 ř.), `kontakt.phtml` (nový, 68 ř.), `kontakt.css` (nový, 107 ř.), `public/index.php` (−324/+21). V `index.php` jen 2 hunky: import `use ...KontaktPageData` a přepis GET funkce `tryServeKontaktPage()`.
2. **CSRF** — OK. `\App\Core\Auth::csrfToken()` volán PŘESNĚ JEDNOU v `tryServeKontaktPage()` (`public/index.php:1940`, `$csrfToken = ...`); starý inline call ve form HTML odstraněn (jediný výskyt ve funkci). `KontaktPageData::forSite()` token jen převezme jako parametr → `form.csrfToken`, nikde negeneruje/nemodifikuje. `.phtml` token jen echuje `$this->e($form['csrfToken'])`. POST handler `/kontakt/odeslat` (`Auth::csrfVerify`) BYTOVĚ IDENTICKÝ (0 řádků diff přes 150 ř. — ověřeno `diff` pre vs post).
3. **Honeypot** — OK. Pole `name="website"` v `.phtml` přes `$form['honeypotField']='website'`; živý render potvrdil `name="website" class="hp-honeypot"`. Server-side kontrola `if (!empty($_POST['website']))` (POST handler `:2577`) nedotčená (byte-identická).
4. **Throttling** — OK. `RequestThrottle::isLocked`/`hit` (POST handler `:2582`,`:2612`) byte-identické, nedotčené.
5. **Scope** — OK. Jen 2 hunky v `index.php` (import + GET funkce). POST handler celý mimo změněný region, byte-identický. Žádný BLOCKER.
6. **Dead code / sociální sítě** — OK, tvrzení POTVRZENO nezávisle. V `f77049b~1:public/index.php` první blok (`:1942-1999`) počítá `$detailsHtml`(ci-brand/ci-row/socials)+`$mapsHtml`; řádek `:2030` `$detailsHtml = implode('', $contactCards)` a `:2031` `$mapsHtml = ...` je BEZPODMÍNEČNĚ přepíší. Mezi prvním přiřazením a přepisem se `$detailsHtml`/`$mapsHtml` jen `.=` (nikdy nečtou); `$socialsHtml` vložen jen do zahozeného prvního `$detailsHtml`. `inlineAttrs()` (`:953`) je ČISTÁ funkce bez vedlejších efektů. → sociální sítě byly už PŘED commitem 100% mrtvé (nikdy ve výstupu), odstranění = ŽÁDNÁ regrese. Definitivně potvrzeno golden byte-parity: commit se NEDOTKL žádného golden souboru a `kontakt live: OK` prochází → nový výstup byte-identický s předchozím goldenem.
7. **Testy & byte-parity** — OK. `php tests/run.php` → 45 testů, 0 failures; 26 golden, 0 failures; `kontakt live: OK` + `kontakt publish: OK`. `PublishService` NENÍ v diffu (3 výskyty „PublishService" jen v textu `UKOL.md`). Publish pipeline nedotčena.
8. **Bezpečnostní regrese (nezávisle)** — OK. Živý server `:8972`, curl `/kontakt` → HTTP 200, CSRF token přítomen a 64-hex, honeypot `name="website"` přítomen, action `/kontakt/odeslat` beze změny, NULA PHP warningů/notice v HTML, čistý server log. Inline editor: `$editMode = Auth::check() && !isset($_GET['cms_preview']) && $page !== null` je ekvivalentní staré `inlineAttrs()` gate — `PageRepository::findByUrlPath`/`findPublishedByUrlPath` vrací `$row ?: null`, tedy `$page` je jen `null` nebo neprázdné pole, takže `$page !== null` ≡ staré `!$page`. Anon režim → 0 úniků `data-inline-key` (ověřeno živě).
9. **Newline gotcha** — OK. Dočasné smazání OBOU kompenzačních prázdných řádků (`.phtml:26` a `:28`) → živý výstup CHANGED (karty/maps/`</div>` slity na jeden řádek, newline polknuty). Oba řádky nutné. Obnoveno `git checkout`, working tree čistý.

**Nálezy/opravy:** žádné. Kód je bezpečnostně korektní, byte-parita zachována, POST bezpečnostní větev prokazatelně nedotčená. Pozn.: golden běh republikoval `storage/published/*` (očekávaný, revertovatelný side-effect `git checkout -- storage/published/`).

### Fáze 4 — DOKONČENA (všech 9 archetypů), souhrn pro Fázi 5

Všech 9 archetypů migrováno na PHTML view systém: **faq, sluzby, cenik, reviews/hodnoceni** (dávka 4a), **blog-listing, blog-detail, portfolio-listing, portfolio-detail** (dávka 4b), **kontakt** (dávka 4c). `php tests/run.php` → 45 testů + 26 golden checků, 0 failures po celé fázi.

Hlavní lekce pro Fázi 5 (homepage + generické stránky):
1. **Dva platné vzory**: Vzor A (plná stránka nahrazující heredoc, jako faq/sluzby/blog/kontakt) vs. Vzor B (jen dynamická splice sekce nad generickou DB stránkou, jako cenik). Homepage bude pravděpodobně kombinace obojího (sekce jako services/portfolio/pricing/blog/faq/process/kontakt, každá potenciálně jiný vzor).
2. **Live/publish bariéra**: cokoliv, co je dnes "jen global funkce v `public/index.php`", je z `PublishService` nedosažitelné (jiný bootstrap/scope) — u homepage to bude pravděpodobně nejvýraznější, protože `injectHomepageMissingSections`/`injectHomepageThemeDataSources` (viz Fáze 2 poznámky) jsou přesně tento typ funkce.
3. **Golden diff je citlivější než oko** — vždy věřit byte-diffu, ne jen vizuální kontrole; newline-swallow chyby se projeví jen v golden testu.
4. **Otevřené BLOCKERy z předchozích fází, které Fáze 5/6 musí zohlednit:** `PortfolioPublishService` dvojí zapisovač `storage/published/nase-prace/index.html` (viz Fáze 4b review) — Fáze 5 se homepage `portfolio-tiles` bloku dotkne (`syncHomepageTiles()`/`buildTilesHtml()` v `PortfolioPublishService`), takže je vhodná příležitost vyřešit oboje najednou.
5. **Visual QA dluh**: fáze 4a/4c neměly headless browser dostupný v agent session — doporučeno spustit `visual-screenshot-score-loop` jednorázově na VŠECH 9 archetypech + homepage najednou po Fázi 5, než se přejde k Fázi 6 (úklid).

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
