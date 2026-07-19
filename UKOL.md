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

### Fáze 5 — Homepage a generické stránky [HOTOVO]

Datum: 2026-07-19. Homepage autofill (HTML → data + PHTML) a generický `page` archetyp. `php tests/run.php` → 45 testů + 26 golden, 0 failures po celé fázi (live i publish home byte-identické).

- [x] **`App\ViewModels\HomepageData`** (nový, `app/ViewModels/HomepageData.php`) — čistý data provider pro homepage sekce. Metody vrací JEN skaláry/pole, žádné HTML, žádné DB dotazy (media URL už přeložené předává volající). Kontrakt: `serviceCard($service,$index,$imageUrl,$iconUrl,$buttonText,$buttonUrl)` → `{title,description,imageUrl,iconUrl,hasIcon,num,buttonText,buttonUrl}`; `pricingCards($map)` → `list<{title,price,summary,featured}>` (summary = 1. odstavec popisu); `blogCard($post,$imageUrl)` → `{slug,title,excerpt(mb_strimwidth 120),imageUrl}`; `faqItem($row)` → `{question,answer}`; `portfolioTile($entry,$thumbUrl,$hasVideo,$photos)` → `{href,title,thumbUrl,hasVideo,photos:[{url,alt}]}`; `reviewCards($items)` → `list<{stars,review_text,author_name,context_label}>`; `sectionCta($url,$label)` → `{url,label}`; `contact($contact,$csrfToken,$cta)` → `{lines,map,cta,form:{csrfToken,showMissingEmailNote}}`.
- [x] **Homepage section partials** (nové, `app/Views/partials/home-*.phtml`): `home-service-card`, `home-portfolio`, `home-pricing`, `home-blog-card`, `home-faq-item`, `home-reviews`, `home-section-cta`, `home-contact`. Každý byte-identický s původním builderem (ověřeno golden). Newline konvence jako `cenik.phtml` (header `?>` a koncový `<?php ?>` polykají obklopující newline). Portfolio partial má vnitřní newliny tile (původně heredoc) — reprodukováno přesně.
- [x] **Co bylo nahrazeno** — `HomepageContentService` buildery přepsány z string-concat na `HomepageData` + `renderHomePartial()` (nová privátní metoda → `ViewFactory::forTheme(null)->render('partials/home-*')`): `buildServiceCardHtml`, `buildPortfolioHtml`, `buildPricingHtml`, `buildBlogCardHtml`, `buildFaqItemHtml`, `buildContactHtml`, `buildPageCtaHtml`. `ReviewContentService::buildHomepageHtml` — grid přes `home-reviews` partial (inline `<style>` z `homepageCss()` zůstává byte-identicky v service, přesun do assetu je Fáze-6 úklid). **Klíčový poznatek:** homepage NENÍ heredoc stránka (Vzor A) — je to generická DB stránka (`tryServeDatabasePage`), sekční obaly/labely/pořadí jdou z theme-specific `templates.html_source`, autofill plní jen VNITŘNÍ HTML `data-block` slotů. Block content pak prochází `PageRenderer` DOM (entity-encoding diakritiky) → partials produkují jen UTF-8 jako buildery, zbytek řeší pipeline. `injectHomepageMissingSections` je pro aktivní téma no-op (sekce už v šabloně); `injectHomepageThemeDataSources`/`injectHomepageAboutVisualSource` fungují dál beze změny.
- [x] **Žádné monolitické `archetypes/homepage.phtml`** — vědomé rozhodnutí. Homepage sekce se injektují blok-po-bloku do editovatelné DB šablony; monolitický archetyp by (a) nešlo udržet byte-identický na nejnavštěvovanější stránce a (b) zničil by theme/DB editovatelnost sekčních obalů/labelů. „archetypes/homepage.phtml se sekcemi" z plánu je realizován jako homepage section **partials** (princip „snippety = partials", plán 2.1) + homepage teče přes `archetypes/page` jako každá DB stránka. To je přesně kombinace vzorů předvídaná Fází 4 lekce 1.
- [x] **`archetypes/page.phtml`** (nový) — generický CMS archetyp. Obaluje LEGACY `PageRenderer::render()` fragment (`<?= $this->raw($fragment) ?>`, byte-for-byte passthrough). Napojeno v `tryServeDatabasePage()` (`public/index.php`, live) i `PublishService::publishPage()` (`$archetypeHtml ?? render('archetypes/page', ...)`, publish). Homepage i generické stránky teď tečou přes tentýž wrapper; data-block/inline-editor mechanika beze změny (ověřeno přímým passthrough testem: fragment s `data-inline-key` projde byte-identicky). Dedikované archetypy (faq/sluzby/…) mají v publishi přednost přes `$archetypeHtml`.
- [x] **Golden** — `home live: OK` + `home publish: OK` (prázdný diff) po KAŽDÉ dílčí sekci (faq/cta/pricing → reviews → services → blog → portfolio → contact → page.phtml). Žádná regenerace baseline. Celý běh 45 testů + 26 golden, 0 failures.
- [x] **Bezpečnost** — HomepageData 0 DB dotazů (media resolution zůstává v `HomepageContentService` přes repozitáře; `contact()` používá jen konstantu `ContactRepository::DEFAULT_FORM_EMAIL`). Escaping default (`$this->e()`); `raw()` jen na už-renderované CTA HTML a na `page` fragment (trusted DB HTML, downstream `sanitizeFrontendHtml`). **CSRF:** homepage kontaktní formulář — token generován PŘESNĚ JEDNOU v `HomepageContentService::buildContactHtml()` (`Auth::csrfToken()`), předán jako data, ve view jen escaped echo (`$this->e($form['csrfToken'])`), NIKDY negenerován ve view/ViewModelu. Honeypot `name="website"` zachován; POST handler `/kontakt/odeslat` nedotčen. Ověřeno: token escaping (`TOKEN"<>&` → `&quot;&lt;&gt;&amp;`), honeypot, action, tel-normalizace, social ucfirst. ViewResolver realpath containment nedotčen; žádné shell_exec/exec/system.
- [x] **Visual QA** (headless Chrome, dostupný v této session): homepage a `/podminky-uziti` na 1920×1080 i 390×844 → HTTP 200, 0 PHP warningů, plné elegant-white3 stylování (nav, hero+bg, gold CTA, services/portfolio/pricing/blog/reviews/contact grids), mobil responzivní (hamburger, stacked CTA). Generická stránka přes nový `page.phtml` wrapper renderuje obsahové karty korektně. Bez layout regrese. (Pozn.: byte-identický live golden + žádný přesun CSS ⇒ vizuální regrese pro aktivní téma není možná; screenshoty to potvrdily.)
- [x] Commity: viz níže.

**Rozhodnutí o `PortfolioPublishService` blockeru → ODLOŽENO na Fázi 6 (pragmaticky):** `syncHomepageTiles()`/`buildTilesHtml()` (homepage `portfolio-tiles` blok) i `regenerateListingPage()` (`/nase-prace`) zůstávají BEZE ZMĚNY. Důvody: (1) golden runner NIKDY nespouští `PortfolioPublishService` → jakákoli změna je mimo záchrannou síť, což je přesně situace, kdy se na kódu sousedícím s network-authority stránkou nemá sahat naslepo; (2) je to už zdokumentovaný Fáze-6 BLOCKER (dvojí zapisovač) vyžadující architektonické rozhodnutí (bake-on-CRUD vs autofill-on-render), patří k manifest-v2/úklidu; (3) divergence je pre-existing — Fáze 5 obohatila autofill stranu (`buildPortfolioHtml` → PHTML) přesně jako Fáze 4b obohatila `PublishService` stranu `nase-prace`, NEZHORŠILA to. **Konkrétní plán pro Fázi 6 (teď snazší):** protože `buildPortfolioHtml` je nově PHTML (`home-portfolio` partial), ideální fix je smazat `syncHomepageTiles()`/`buildTilesHtml()` a nechat homepage tiles čistě na autofill cestě (`applyFallbacks` regeneruje na každý render), případně `syncHomepageTiles` přesměrovat na veřejnou obálku `HomepageContentService`u. Stejně `regenerateListingPage()` → delegovat na `PublishService::publishPage()` / archetyp.

### Fáze 5 — poznámky pro Fázi 6

1. **Dead code po Fázi 5** (k úklidu): `ReviewContentService::buildGridMarkup()` je nyní nepoužitá (jen `buildListingHtml` a `buildHomepageHtml` volaly grid, oba už přes PHTML) — ponechána kvůli doc odkazům; smazat. `HomepageContentService::DEFAULT_FORM_EMAIL` privátní konstanta je po přesunu do `HomepageData` osiřelá (jediné užití bylo `buildContactHtml`); smazat. `HomepageContentService::buildServiceDetailHtml()` — mimo scope Fáze 5 (jiný `hp-service-detail` komponent, ne homepage autofill), ověřit dead/živý a případně migrovat/smazat.
2. **Inline `<style>` k přesunu do sdílených assetů** (byte-neutrální, golden normalizuje generated CSS hash): `ReviewContentService::homepageCss()` (`.hp-reviews-*`). Přesun do `shared-components.css` je vizuálně bezpečný, ale golden to nezachytí → nutná vizuální kontrola.
3. **`PortfolioPublishService` blocker** — viz rozhodnutí výše; konkrétní plán připraven, `buildPortfolioHtml` už PHTML.
4. **Theme override hook** — homepage section partials i `archetypes/page` teď tečou přes `ViewFactory::forTheme(null)` (core-only). Manifest v2 (`views_dir`) může téma nechat přepsat `partials/home-*` i `archetypes/page` bez zásahu do core (kaskáda už existuje, jen se nepoužívá theme root pro tyto).

### Fáze 5 — post-commit review [SCHVÁLENO]

Datum: 2026-07-19. Nezávislý review commitů `48c1c7e` (homepage) a `d64f93b` (generic page) proveden přímo v hlavní session (Sonnet 5) po selhání subagenta kvůli měsíčnímu spending limitu (agent byl ještě v read-only průzkumu, žádný soubor nedotčen). **Verdikt: SCHVÁLENO**, žádný BLOCKER, žádná oprava nutná.

- [x] **Architektonické tvrzení ověřeno**: `archetypes/page.phtml` docblock potvrzuje, že homepage i generické stránky dál tečou přes legacy `PageRenderer::render()` fragment (`templates.html_source`+`content_blocks`); partials jsou správná realizace „snippety = partials", ne monolitický archetyp — rozhodnutí je zdokumentované a odůvodněné (byte-parity + zachování theme/DB editovatelnosti sekcí).
- [x] **CSRF** — OK. `HomepageContentService::buildContactHtml()` řádek 466: `$csrfToken = \App\Core\Auth::csrfToken();` — generováno PŘESNĚ JEDNOU, předáno do `HomepageData::contact()` jako parametr (ověřeno: metoda ho jen ukládá do vráceného pole `form.csrfToken`, nikde nemodifikuje/negeneruje). `home-contact.phtml` ho jen echuje `$this->e($form['csrfToken'])`. Honeypot (`name="website" value="" autocomplete="off"`) a form action (`/kontakt/odeslat`) beze změny.
- [x] **`raw()` použití ověřeno** — 2 místa, obě oprávněná: (a) `$this->raw($cta)` v `home-contact.phtml` — `$cta` pochází z `buildPageCtaHtml()`, který sám renderuje přes `home-section-cta` partial (tedy už escapovaný nested-partial výstup, ne user input); (b) `$this->raw($fragment)` v `page.phtml` — trusted DB HTML z `PageRenderer::render()`, sanitizovaný dále v pipeline (`sanitizeFrontendHtml`) — stejný precedens jako u ostatních archetypů.
- [x] **Dead code potvrzen grepem**: `ReviewContentService::buildGridMarkup()` — 0 reálných volání (jen definice + 2 doc-komentáře). `HomepageContentService::DEFAULT_FORM_EMAIL` — jen deklarace konstanty, 0 použití v souboru. Bezpečné ponechat do Fáze 6 (jak agent navrhl).
- [x] **`HomepageData.php`** — žádné SQL (grep na SELECT/INSERT/UPDATE/DELETE/PDO/query/prepare čistý), jen datové transformace.
- [x] **Testy**: `php tests/run.php` → 45 testů + 26 golden checků, 0 failures (`home live: OK`, `home publish: OK`).
- [x] **Živá kontrola** (`php -S`, port 8973): `/` → HTTP 200, 0 PHP warningů/notice/fatal error v HTML, CSRF token přítomen a validní 64-hex formát, homepage vrací plný obsah (32 KB).
- [x] `PortfolioPublishService` odklad na Fázi 6 — rozhodnutí i zdůvodnění ověřeno jako rozumné (golden runner tuto službu nikdy nespouští, takže jakákoli změna by byla mimo záchrannou síť).

**Nálezy/opravy:** žádné.

### Fáze 6 — Theme overridy a úklid
- [ ] Delegovat na subagenta
- [x] **Manifest v2 (`views_dir`, `overrides.<pageKey>.view`) — HOTOVO.**
  - **Parsing** (`ThemeTemplateRegistry::loadTheme`): nové volitelné klíče `views_dir` (string) a per-override `view` (string). Zpětně kompatibilní — chybějící klíče → `''`, staré chování beze změny.
  - **Accessory:** `getViewsDir()`, `hasViewsCascade()` (opt-in aktivace kaskády = `views_dir !== ''`, mirror precedentu `ThemeLayoutRenderer::hasPhtmlLayout()` z fáze 3, ale řízený manifestem), `getPageViewOverride()`.
  - **Resolver:** `pageContentView(?array $theme, string $pageKey): ?array` vrací `['themeKey','view','viewsDir']` když téma deklaruje `views_dir` A poskytuje content view pro pageKey (explicitní `overrides.<pageKey>.view` NEBO `<views_dir>/pages/<pageKey>.phtml`); jinak `null`. `pageContentViewForSite()` = varianta pro live seam (resolvuje aktivní téma ze `$site`). `normalizeViewLogicalName()` akceptuje logický název (`pages/cenik`) i manifest path-form (`views/pages/cenik.phtml`).
  - **ViewFactory:** `roots()`/`forTheme()` mají nový volitelný param `?string $viewsDir` (default `views` → beze změny pro stávající callery). Nová `themeViewPath($themeKey, $view, $viewsDir)` = zobecnění `themeLayoutPath` na libovolnou view s realpath containmentem; `themeLayoutPath` na ni deleguje.
  - **Consumption (aktivace):** oba generic-page seamy — live `public/index.php::tryServeDatabasePage` (:145) i publish `PublishService::publishPage` (:112) — teď preferují theme content-view override, jinak fallback na core `archetypes/page`. Kontrakt override view: `['fragment','pageKey']` (drop-in wrapper). **KRITICKÉ pro zpětnou kompatibilitu:** aktivní téma elegant-white3 (bez `views_dir`) → `pageContentView` = `null` → core wrapper → BYTOVĚ IDENTICKÉ. Ověřeno: 26 goldenů beze změny (oba seamy jsou golden-kryté: home/generic-page/všechny publish targety).
  - **Testy:** `tests/ManifestV2Test.php` (3 nové testy) — fixture téma pod reálným `Templates/` (jediné místo, odkud ViewFactory resolvuje theme views), robustní cleanup ve `finally`. Ověřuje: parsing, explicit i path-form override, implicit `pages/<key>` fallback, `null` pro chybějící, skutečný render přes kaskádu, containment (`../../evil` → null), custom views_dir root, backward-compat (elegant-white3 → prázdné/null).
  - Aktivní téma NEBYLO měněno (žádný `views_dir` přidán do elegant-white3 — funguje dál přes file-existence layout detekci z fáze 3).
- [x] **Migrace theme hacků z core do témat — HOTOVO (CSS hacky), STRUKTURÁLNÍ podmínky ODLOŽENY s odůvodněním.**
  - **`getJemnaBezovaHomepageCssOverrides()` (ThemeTemplateRegistry ~70 ř. CSS heredoc) → `Templates/jemna-bezova/assets/homepage-overrides.css`**, napojeno přes manifest `overrides.homepage.assets.css`; theme-specific větev v `getCssForPage()` SMAZÁNA. **Ověřeno neutrální:** skript porovnal `getCssForPage('jemna-bezova','homepage')` před/po → diff = JEDEN kosmetický prázdný řádek navíc (77467→77468 B, separátor mezi theme.css a overrides `\n\n`→`\n\n\n` kvůli collectAssets join sémantice); CSS PRAVIDLA bytově identická, funkčně 0 změna.
  - **`injectEditorialGoldOverrides()` → `Templates/editorial-gold/assets/overrides.css`** (base `assets.css`, načítá se na všech stránkách jako původní all-page inject). SMAZÁNO z core: `PublishService::injectEditorialGoldOverrides()` (metoda), `PageComposer::composePublish` volání, `PublishPageChrome` interface metoda, `public/index.php::injectEditorialGoldOverrides()` (byl to LIVE NO-OP — `return $html`!) + volání v `sanitizeFrontendHtml`, a mrtvá extraction-exclusion v `MarkupNormalizer::extractInlineStyleBlocks` (`.theme-editorial-gold [data-block="reviews_cta"]`). **Objev:** live cesta byla no-op → editorial-gold overridy se aplikovaly JEN v publishi = pre-existing live/publish divergence; migrace na theme asset ji SJEDNOTÍ (live nově dostane fixy přes bundle). **Ověřeno:** CSS obsah bytově zkopírován; `getCssForPage('editorial-gold', homepage|sluzby)` nově obsahuje fixy; aktivní téma elegant-white3 nikdy nematchuje `theme-editorial-gold` → všechna odstranění jsou pro něj no-op → 26 goldenů beze změny.
  - **CAVEAT (editorial-gold, nezamlčeno):** cascade pozice se změnila z late-inline `<style>` (po celém page-css) na bundle asset. Původní design ho DRŽEL mimo bundle (extraction-exclusion) záměrně kvůli `!important` kaskádě. Selektory jsou vysoce-specifické `.theme-editorial-gold ... .btn-secondary` + `!important`, žádné konkurenční pravidlo pozdějc v head → riziko nízké, ale editorial-gold NENÍ aktivní téma a NEMÁ golden → **doporučen vizuální QA editorial-gold před produkčním použitím.** Live/publish parita je nově korektní (dřív rozbitá).
  - **STRUKTURÁLNÍ `theme-*` podmínky v `HomepageSectionRenderer` — VĚDOMĚ ODLOŽENY:** `injectHomepageMissingSections` (`theme-elegant-white2?` FAQ sekce, :103), `injectHomepageThemeDataSources` (`theme-bezova-lens-tale` :163, `theme-elegant-white2?` :181). Důvody: (1) jsou to STRUKTURÁLNÍ HTML section/data-source injekce, ne CSS — jejich přesun vyžaduje per-theme homepage-section override infrastrukturu, která NEEXISTUJE (manifest v2 řeší content-view override generických stránek, ne section-splice do existující homepage šablony); (2) cílí na NEAKTIVNÍ témata (elegant-white/elegant-white2/bezova-lens-tale; aktivní je elegant-white3, žádné nematchuje — goldeny to potvrzují); (3) žádné golden pokrytí těchto témat → přesun = neověřitelná strukturální změna mimo záchrannou síť; (4) odstranění (ne přesun) by ROZBILO ta témata (chybějící sekce). Konzistentní s disciplínou projektu (odklad neověřitelných změn, viz Fáze 5 PortfolioPublishService). Viz otevřená rizika.
- [x] **Smazat mrtvé `inject*`/`build*` funkce a duplicitní kopie — HOTOVO (grep-ověřeno).**
  - `ReviewContentService::buildGridMarkup()` (25 ř.) — 0 callerů (jen doc odkazy) → SMAZÁNO; doc odkazy v `HomepageData`/`ReviewsPageData` aktualizovány.
  - `HomepageContentService::DEFAULT_FORM_EMAIL` a `PublishService::DEFAULT_FORM_EMAIL` (osiřelé privátní konstanty, 0 `self::` užití) → SMAZÁNY; odstraněn nepoužitý `use ContactRepository` z PublishService. (Kanonická `ContactRepository::DEFAULT_FORM_EMAIL` zůstává.)
  - `HomepageContentService::buildServiceDetailHtml()` (22 ř., 0 callerů) → SMAZÁNO.
  - `public/index.php::buildFooterSocialsHtml()` (38 ř., 0 callerů) → SMAZÁNO.
  - **`PublishService`: 23 mrtvých privátních delegujících wrapperů** na `App\Services\Render\*` (ponechané přes fáze 2-5) → SMAZÁNY. Ověřeno: privátní + 0 `$this->` callerů + žádný dynamic dispatch; composePublish volá `Render\*` PŘÍMO. (`blockContent`, `injectHomepage*`×3, `buildVideoEmbedUrl`, `injectRichFooter`, `injectSharedFrontendAssets`, `normalizeLegacyFrontendMarkup`, `externalizeBodyCssVariables`, `extractInline*`×2, social×3, phone×4, contact×2, `buildRichFooterHtml`, `injectBodyPageKey`, `resolveDemoMediaUrlByFilename`.) Ponechány užité interface metody + jejich helpery.
  - `public/index.php` globální funkce: scan (vč. `\func()` z PageComposer) → 0 dalších mrtvých.
  - Ověřeno: `php tests/run.php` → 48 testů + 27 golden, 0 failures (publishPage plně golden-krytý).
  - **Nedotčeno:** `ReviewContentService::homepageCss()` inline `<style>` (živý; přesun by změnil golden home, volitelný dovětek). `DemoContentSeeder` HTML zjednodušení (volitelný dovětek) NEPROVEDENO — velký demo generátor mimo render pipeline, riziko/hodnota.
- [x] **BLOCKER VYŘEŠEN — dvojí zapisovač `storage/published/nase-prace/index.html` odstraněn.**
  - **Řešení:** `PortfolioPublishService::regenerateListingPage()` (bare `pl-grid`/`pl-tile` markup) SMAZÁN. Nahrazen novou veřejnou metodou `regenerateListing(array $site, int $userId = 0)`, která deleguje na jediný themed pipeline `PublishService::publishPage()` (→ `renderArchetypeContent('nase-prace')`). Guard `PageRepository::findPublishedByUrlPath('nase-prace')` + kontrola `site_id` ⇒ no-op, dokud nase-prace stránka neexistuje / není published (draft se nikdy force-nepublishne jako vedlejší efekt editace portfolia).
  - **`syncHomepageTiles()` + `buildTilesHtml()` SMAZÁNY** (homepage `portfolio-tiles` blok se starým `pt-grid` markupem). Ověřeno grepem: homepage autofill (`HomepageContentService::fillHomepageFallbacks` → `applyBlockFallback('portfolio-tiles', buildPortfolioHtml)`) regeneruje sekci na KAŽDÝ render (in-memory, nepersistuje) přes nové PHTML `home-portfolio` partial. `syncHomepageTiles` byl navíc škodlivý — persistoval starý markup do DB bloku, čímž autofill (jen blank/placeholder) přeskočil, a přepisoval i ruční editace bloku. Poznámka: pre-existující produkční DB, kde `syncHomepageTiles` už zapsal blok, si stálý obsah ponechá dokud se blok nevyprázdní (autofill respektuje ne-blank bloky — CMS „ruční edit vyhrává"); nový kód jen zastaví budoucí klobrování.
  - **Callery přepnuty** (`syncHomepageTiles` → `regenerateListing`): `ApiController::createPortfolioEntry`/`updatePortfolioEntry`/`deletePortfolioEntry` (API-key kontext → `regenerateListing($site)`, userId 0 → fallback na `UserRepository::firstId()`), `PortfolioController::store`/`update`/`delete` (session kontext → `regenerateListing($site, $this->currentUserId())` = přihlášený admin). Delete NOVĚ také regeneruje listing (dřív ne — smazaná položka zůstávala ve statickém listingu do republish).
  - **userId** pro `publish_history` řádek řeší `resolvePublisherId()`: preferuje předaný id (session admin), fallback `UserRepository::firstId()` (nová metoda, mirror golden runneru) pro API kontext bez session.
  - **Test (golden ho nepokrývá):** rozšířen `GoldenMasterRunner` o check `nase-prace portfolio-crud regen` — spustí `PortfolioPublishService::regenerateListing()` a porovná normalizovaný `storage/published/nase-prace/index.html` s `nase-prace` publish goldenem (themed). Meaningfulness ověřena: dočasná injekce bare markupu → check `CHANGED (+1 -135)` FAIL; po reálném kódu `OK`. Navíc grep-ověřeno: regenerovaný soubor obsahuje `data-generated-page-css` + `theme-elegant-white3`, NULA bare signatur (`.pl-nav{background:#111`, `class="pl-hero"`).
  - **Trade-off (přijatý):** každý portfolio CRUD teď spustí plný `publishPage` (regeneruje sitemap/robots/llms + zapíše `publish_history` řádek „Published: Naše práce"). Sémanticky korektní (listing byl skutečně republishnut), ale generuje historii-šum při bulk editaci. Alternativa (lehčí zápis jen souboru) by znovu zavedla duplicitní pipeline — přesně to, co Fáze 6 odstraňuje. Viz otevřená rizika.
  - Testy: `php tests/run.php` → 45 testů + 27 golden (vč. nového), 0 failures.
- [ ] ~~BLOCKER (z review Fáze 4b) — dvojí zapisovač `storage/published/nase-prace/index.html`:~~ (VYŘEŠENO výše) `PortfolioPublishService::regenerateListingPage()` (`app/Services/PortfolioPublishService.php:146-206`, volaná z `publishEntry()` na `:120`) zapisuje TENTÝŽ soubor `storage/published/nase-prace/index.html` jako `PublishService::publishPage()` (`resolveFilePath('nase-prace')` → `storage/published/nase-prace/index.html`, zápis na `PublishService.php:193-196`), ale STARÝM bare `pl-tile`/`pl-grid` markupem bez theme chrome. `publishEntry()` (→ `regenerateListingPage()`) se spouští při admin CRUD na portfolio položkách se statusem `published`: `ApiController::createPortfolioEntry` (`:343`), `ApiController::updatePortfolioEntry` (`:407`), `PortfolioController` (`:78`, `:144`). **Dopad:** po Fázi 4b je `/nase-prace` sjednocené (elegant-white3 archetyp z `PublishService`), ale první reálná portfolio publish akce v adminu PŘEPÍŠE produkční `/nase-prace` zpět na starý bare vzhled („časovaná bomba“). Goldeny to nezachytí — golden runner publikuje `nase-prace` jen přes `PublishService`, `PortfolioPublishService` se v testech nespouští. Pozn.: divergence je pre-existing (oba zapisovače existovaly i před 4b; 4b jen zbohatil stranu `PublishService`), Fáze 4b `PortfolioPublishService` záměrně neměnila. **Řešení (vyžaduje architektonické rozhodnutí):** buď smazat `regenerateListingPage()` a delegovat regeneraci listingu na `PublishService::publishPage()` (potřebuje `pageId`+`userId` v CRUD kontextu), nebo přepsat `regenerateListingPage()` na `renderArchetypeContent`/PageComposer výstup. Stejně zvážit `buildTilesHtml()`/`syncHomepageTiles()` (homepage `portfolio-tiles` blok — jiný soubor, ale rovněž starý markup).
- [x] **Aktualizace `ARCHITECTURE.md` — HOTOVO.** Nová sekce „Render vrstva (PHTML view system)" (komponenty `App\View\*`/`App\ViewModels\*`/`App\Services\Render\*`, ViewResolver kaskáda + bezpečnost, PageComposer live/publish, archetypy, bezpečnostní kontrakt view vrstvy). Rozšířena sekce „Temata" o manifest v2 (`views_dir`, `overrides.<pageKey>.view`, PHTML layout). Moduly tabulka doplněna o `app/Services/Render`, `app/View`, `app/ViewModels`, `app/Views`. Přepsány zastaralé inject-based popisy (CMS stránky → archetypy+PageComposer; publish pipeline → sjednocený PageComposer + `PortfolioPublishService::regenerateListing` bez dvojího zapisovače; shared CSS → `app/Views/assets/shared-components.css`).
- [x] **Finální golden + visual QA všech stránek — HOTOVO.**
  - Golden: `php tests/run.php` → 48 testů + 27 golden checků (vč. nového `nase-prace portfolio-crud regen`), 0 failures.
  - Route smoke test (živý `php -S`): `/`, `/faq`, `/sluzby`, `/cenik`, `/kontakt`, `/blog`, `/nase-prace`, `/hodnoceni`, `/podminky-uziti`, blog detail, portfolio detail → HTTP 200; neexistující → 404; NULA PHP warningů/notice/fatal ve všech výstupech.
  - Visual QA (headless Chrome, 1920×1080 desktop + 390×844 mobil): homepage (desktop+mobil), `/nase-prace`, `/kontakt`, `/cenik` vizuálně ověřeny — plné elegant-white3 stylování (nav, hero+bg, gold CTA, services/portfolio grid, pricing karty s NEJOBLÍBENĚJŠÍ badge, kontaktní formulář + proces sekce), mobil responzivní (hamburger, stacked CTA), 0 layout regrese. `/nase-prace` (BLOCKER-fixed) renderuje kompletní themed archetyp. Zbytek (faq/sluzby/blog/generic) je pro aktivní téma byte-identický golden → vizuální regrese vyloučena.
  - Pozn.: mobilní hero CTA tlačítka mírně přesahují k pravému okraji — PRE-EXISTING theme CSS (live golden byte-identický, žádná CSS změna z fáze 6), ne regrese refactoringu.

## Fáze 0-6 — REFACTORING DOKONČEN

Sedmifázový refactoring šablonovacího systému z hardcoded HTML v PHP na čistý PHTML view systém je **kompletní, commitnutý a ověřený**. `php tests/run.php` → 48 testů + 27 golden checků, 0 failures.

### Přehled fází

| Fáze | Obsah | Stav |
| --- | --- | --- |
| 0 | Golden-master záchranná síť (12 live rout + 14 publish cílů, normalizace, LCS diff) | HOTOVO |
| 1 | View engine core (`ViewEngine`, `ViewResolver`, `ViewContext`, `LegacyLayoutView`) | HOTOVO |
| 2 | Sjednocení pipeline — `PageComposer` (live/publish), extrakce do `App\Services\Render\*` | HOTOVO |
| 3 | Layout + chrome do PHTML (`layouts/default.phtml`, partials, shared-components.css, `ThemeLayoutRenderer`) | HOTOVO |
| 4 | 9 speciálních archetypů (faq, sluzby, cenik, reviews, blog×2, portfolio×2, kontakt) + ViewModely | HOTOVO |
| 5 | Homepage autofill (HTML→data + `home-*` partials) + generický `archetypes/page` | HOTOVO |
| 6 | BLOCKER fix, manifest v2, migrace theme CSS hacků, dead-code úklid, ARCHITECTURE.md | HOTOVO |

### Hlavní architektonické změny

1. **Jeden render pipeline** pro live i publish (`PageComposer` MODE_LIVE/MODE_PUBLISH) místo dvou–tří duplikovaných kopií ~50 funkcí.
2. **PHTML view systém**: `App\View\*` (engine/resolver/context/factory), `App\ViewModels\*` (čistá data), `app/Views/*.phtml` (layouts/archetypes/partials/assets), `App\Services\Render\*` (sdílené renderery).
3. **Override kaskáda**: theme views → core views (ViewResolver, realpath containment); manifest v2 `views_dir` + `overrides.<pageKey>.view` pro per-stránka content override.
4. **Odstraněn dvojí zapisovač** `/nase-prace` (produkční časovaná bomba) — `PortfolioPublishService` deleguje na jediný themed publish pipeline.
5. **Theme hacky ven z core** (CSS), **~350+ řádků mrtvého kódu smazáno** (23 dead wrapperů v PublishService, dead build/inject funkce, osiřelé konstanty).

### Co zbylo jako budoucí vylepšení (nezamlčeno)

- **editorial-gold cascade caveat**: CSS přesunuto z late-inline do bundle; vysoce-specifické `!important` selektory → riziko nízké, ale téma není aktivní a nemá golden → doporučen vizuální QA před produkčním použitím. Live/publish parita nově korektní.
- **STRUKTURÁLNÍ `theme-*` podmínky v `HomepageSectionRenderer`** (`theme-elegant-white2?`, `theme-bezova-lens-tale`) záměrně ponechány v core — strukturální section-injekce vyžadují per-theme override infrastrukturu, cílí na neaktivní/negolden témata (odstranění by je rozbilo).
- **Per-entry portfolio DETAIL** (`storage/published/nase-prace/<slug>/index.html`) stále bare `pf-*` markup — mimo scope BLOCKERu (jen listing), portfolio detail je jinak live-only bez publish goldenu. Vhodné jako navazující unifikace (portfolio-detail publish archetyp).
- **`PortfolioPublishService::regenerateListing` side-effecty**: každý portfolio CRUD spustí plný `publishPage` (sitemap/robots/llms regen + `publish_history` řádek). Sémanticky korektní, ale historie-šum při bulk editaci — případná optimalizace bez znovuzavedení duplicitní pipeline.
- **`ReviewContentService::homepageCss()`** inline `<style>` + `DemoContentSeeder` HTML — volitelné dovětky (byte/golden citlivé), neprovedeno.
- **Legacy struktury**: `PageRenderer::render()` (DOM bloky) žije dál jako fragment renderer uvnitř `archetypes/page`; `layout.html` legacy layout přes adaptér — dlouhodobě deprecated, ne blocker.

## Poznámky

- Golden pokrývá live routy `/`, `/sluzby`, `/cenik`, `/faq`, `/kontakt`, `/blog`, `/nase-prace`, `/hodnoceni`, `/podminky-uziti`, 404 a po jednom detailu blogu a portfolia; publish pokrývá všechny publikované stránky a `generate404()`.
- Golden testy vyžadují dostupnou a naplněnou testovací MySQL databázi (`.env`, `DemoContentSeeder`) a reálného demo uživatele pro `PublishService::publishPage()`.
- Golden soubory se aktualizují jen přes `--update-goldens` a až po ručním schválení změn se commitují.
- Spuštění golden testů skutečně přepublikuje `storage/published/*`; po testu bez zamýšleného publishi vrať změny pomocí `git checkout -- storage/published/`.
- `vendor/autoload.php` je ručně psaný PSR-4 shim `App\` → `app/`; nové třídy se načtou bez regenerace Composeru. `app/View/` jsou třídy a `app/Views/` jsou PHTML šablony.
- Opravený historický testovací problém: globální `ob_start()` v `tests/run.php` brání `headers already sent` při bootstrapu golden runneru.
- Údržba 2026-07-18: archiv hotových fází zkrácen. Otevřené fáze 5 a 6 zůstaly beze změny.
- 2026-07-19: mezi commity `d64f93b` a nynějškem proběhla paralelní/jiná session s vlastním úkolem (email manager — `EMAIL_MANAGER_PLAN.md`, commity `f105622`/`58c9a7b`/`e1090d1`), která dočasně přepsala tento soubor vlastním trackingem. Kód refactoringu nebyl tou session dotčen (jen `EMAIL_MANAGER_PLAN.md` a `UKOL.md`). Tento soubor je obnoven ze stavu commitu `d64f93b`, aby pokračovalo sledování PHTML refaktoringu; email-manager plán zůstává dostupný v `EMAIL_MANAGER_PLAN.md` a v git historii (`e1090d1` a starší).

## Výsledek

Všech 7 fází PHTML refaktoru (0–6) je dokončeno, commitnuto a ověřeno golden testy i vizuální QA. Fáze 6 commity: `7ea4678` (BLOCKER), `ac85ca6` (manifest v2), `8ef9c36` (theme CSS migrace), `dc7e73e` (dead code), `acd6d1f` (ARCHITECTURE.md). Refaktoring čeká na finální nezávislý review celého 7fázového celku (viz „Fáze 0-6 — REFACTORING DOKONČEN" výše, sekce otevřených rizik).

## Fáze 6 — finální nezávislý review [SCHVÁLENO]

Datum: 2026-07-19. Nezávislý review 6 commitů fáze 6 (`7ea4678`, `ac85ca6`, `8ef9c36`, `dc7e73e`, `acd6d1f`, `f8c6490`) — nejvyšší riziko celého 7fázového refaktoru. **Verdikt: SCHVÁLENO. Žádný BLOCKER, žádná oprava nebyla nutná.**

### Priorita 1 — `PortfolioPublishService` BLOCKER fix (ověřeno DŮKLADNĚ)

- **Dvojí zapisovač odstraněn**: `regenerateListingPage()` (bare `pl-grid`/`pl-tile` markup) smazán; nahrazen `regenerateListing(array $site, int $userId = 0)` delegující na `PublishService::publishPage()` → `renderArchetypeContent('nase-prace')`. Ověřeno v `git show 7ea4678` a v aktuálním kódu (`app/Services/PortfolioPublishService.php:143`).
- **First-user fallback = pouze atribuce, ŽÁDNÁ bezpečnostní díra.** `resolvePublisherId()` → `UserRepository::firstId()` se předává do `publishPage($pageId, $userId)`, kde se `$userId` používá VÝHRADNĚ na `publish_history.user_id` (`PublishService.php:229`, `'user_id' => $userId`). Neovlivňuje autorizaci ani oprávnění. API-key autentizace je nedotčená: `ApiController::dispatch()` volá `checkAuth()` (jediný globální `API_KEY`, `hash_equals`, `ApiController.php:582-596`) na řádku 32 PŘED jakýmkoli routingem, tedy i před `regenerateListing()`. API nemá per-user oprávnění → fallback nemůže eskalovat práva.
- **Guard proti draft publikaci potvrzen**: `PageRepository::findPublishedByUrlPath('nase-prace')` filtruje `status = 'published'` (`PageRepository.php:62-73`) + kontrola `site_id` → no-op, dokud stránka neexistuje / není published. Draft se nikdy force-nepublishne.
- **Nový golden test ověřen strukturálně**: `GoldenMasterRunner::portfolioListingRegenerationCheck()` (`tests/GoldenMasterRunner.php:181`) volá REÁLNÝ `PortfolioPublishService::regenerateListing($site, firstAdminUserId())`, čte skutečný `storage/published/nase-prace/index.html` a porovnává s `nase-prace.normalized.html` publish goldenem. Žádný mock. Běží a prochází (`nase-prace portfolio-crud regen: OK`).
- **Všech 6 callerů přepojeno** (grep-ověřeno): `ApiController` 3× (`:345,:410,:434`), `PortfolioController` 3× (`:80,:147,:171`) → `regenerateListing`. NULA zbylých odkazů na `regenerateListingPage`/`syncHomepageTiles`/`buildTilesHtml` (jen komentáře/docs).

### Priorita 2 — smazání 23 dead wrapperů v `PublishService.php`

- **0 zbylých callerů** — grep `$this->(všech 23 metod)\(` přes celý codebase: jediný match je `PageRenderer::buildVideoEmbedUrl` (JINÁ třída s vlastní privátní definicí na `:201`, ne zlomený odkaz). Všech 23 byly PRIVATE → externě nevolatelné, žádná admin/cron/webhook cesta je nedosáhne.
- `buildFooterSocialsHtml()`, `buildGridMarkup()`, `buildServiceDetailHtml()`: 0 callerů (jen doc/UKOL).
- **Žádný dynamic dispatch** v PublishService (`call_user_func`/`$this->$method`/`method_exists`: 0 výskytů → wrappery nešly zavolat nepřímo).
- Všech 9 dotčených PHP souborů `php -l` čisté.
- **Testy nezávisle spuštěny**: `php tests/run.php` → **48 testů + 27 golden checků (vč. `nase-prace portfolio-crud regen`), 0 failures** — přesně dle tvrzení agenta.

### Priorita 3 — Theme manifest v2 bezpečnost

- `ViewFactory::themeViewPath()` (`app/View/ViewFactory.php:86`): theme key regex `~^[a-z0-9][a-z0-9_-]*$~D`, view regex `~^[a-z0-9][a-z0-9/_-]*$~D` (BEZ tečky → `..` traversal nemožný už na úrovni regexu), `viewsDir` normalizován (strip `\`/NUL) + validován, navíc `realpath()` prefix containment. Přísnost odpovídá ViewResolveru z fáze 1. (Minor: `strncmp` bez trailing separátoru je teoreticky volný prefix-match, ale no-dots regex garantuje containment konstrukčně — ne blocker.)
- **Zpětná kompatibilita potvrzena**: aktivní téma `elegant-white3` NEMÁ `views_dir` → `getViewsDir === ''`, `hasViewsCascade === false`, `pageContentView === null` (core archetyp) → výstup byte-identický, goldeny beze změny.
- `tests/ManifestV2Test.php` pokrývá containment: `themeViewPath(key, '../../evil')` → null, `themeViewPath('../evil', ...)` → null, invalid `views_dir '../evil'` → fallback na default. Path traversal pokus skutečně selže.

### Priorita 4 — Standardní kontroly

- **Kontakt formulář (CSRF/honeypot/throttling) MIMO SCOPE — potvrzeno**: grep celého code-diffu fáze 6 (`git diff 3aa5eb2 f8c6490 -- public/index.php app/`) na `csrf|honeypot|throttle|handleContact|contact_submissions` → 0 matchů. Žádný soubor/řádek kontakt formuláře se nedotčen.
- **Theme CSS přesun**: `jemna-bezova` + `editorial-gold` (obě NEAKTIVNÍ, aktivní = elegant-white3). Přesunuté CSS soubory byte-věrné odstraněným heredocům (ověřeno čtením `Templates/jemna-bezova/assets/homepage-overrides.css` a `Templates/editorial-gold/assets/overrides.css`). PHP odstranění koordinovaná (interface `PublishPageChrome`, `PageComposer` cascade, `MarkupNormalizer` extraction exclusion) — žádná HTML/PHP logika omylem smazána.
- **ARCHITECTURE.md věcně správný**: ViewResolver containment (`:163`), PageComposer jeden pipeline (`:167`), `regenerateListing` bez druhého zapisovače (`:206`), CSRF/honeypot design (`:178`) — vše odpovídá reálnému kódu.
- **Finální visual QA (headless Chrome, threaded server)**: homepage, `/nase-prace` (BLOCKER target), `/kontakt` na 1920×1080 i 390×844. Všechny renderují plný themed elegant-white3 layout (nav+active state, hero, gold CTA, card grid, contact card) BEZ layout regrese. `/nase-prace` = kompletní themed archetyp (vizuální důkaz fixu — žádný bare markup). Jeden capture-artefakt (homepage mobil CSS race) potvrzen jako transientní opravným záběrem; `/kontakt` černý box vpravo = nenačtený externí map-iframe (offline capture), ne regrese.

**Závěr:** Refactoring všech 7 fází je bezpečný a kompletní. BLOCKER fix je korektní a plně otestovaný, dead-code úklid bez zbylých callerů, manifest v2 s pevným path-containmentem, aktivní téma byte-identické. Otevřené položky (editorial-gold visual QA, portfolio-detail publish archetyp, regenerateListing side-effecty) jsou nezamlčené budoucí vylepšení, ne blockery.
