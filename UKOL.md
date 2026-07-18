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

### Fáze 1 — View engine core (žádná změna chování) [HOTOVO]
- [x] Delegovat na subagenta (model dle model-check) — běží na Opus 4.8 jako dedikovaný agent
- [x] `App\View\ViewEngine`, `ViewResolver`, `ViewContext`, `LegacyLayoutView`
- [x] Fixture views v `tests/fixtures/views/` (app/Views zůstává prázdný)
- [x] Unit testy: escaping, kaskáda resoluce, realpath containment, slot/partial, inlineAttr, LegacyLayoutView parita
- [x] Ověřit: nic zatím nevolá nový engine, golden testy beze změny (`php tests/run.php` → 36 testů + 26 golden, exit 0)
- [x] Commit `refactor(templates): add PHTML view engine core`

### Fáze 2 — Sjednocení pipeline (PageComposer) [HOTOVO]
- [x] Delegovat na subagenta (vyšší náročnost — duplicitní extrakce)
- [x] Extrakce sdílených funkcí z `public/index.php` + `PublishService` do `App\Services\Render\*`
- [x] `PageComposer` s módy live/publish
- [x] Golden diff prázdný nebo schválený (26/26 OK bez regenerace baseline)
- [x] Commit `refactor(templates): unify live and publish render pipeline` (0cc77c9)

#### Fáze 2 — review před commitem [HOTOVO]

- [x] Projít celý diff (`public/index.php`, `PublishService.php`, `PortfolioPublishService.php`) + přečíst všech 9 souborů `app/Services/Render/*`
- [x] Bezpečnostní kontrakt: žádné SQL v Render/* (jen repozitáře), CSRF /kontakt netknuto (generování index.php:1062/2652, validace :3212), `requireAuth()` v PublishController na místě (v PublishService nikdy nebyl), realpath kontroly netknuty, escaping přesunut spolu s funkcemi, MediaUrlResolver root-relative bez hardcoded domény, žádný shell exec/eval
- [x] Korektnost: funkce přesunuty (grep ověřil jediné kanonické kopie; staré funkce jsou tenké delegující wrappery — smazání ve Fázi 6), pořadí volání v PageComposer 1:1 s originály, sjednocení injectFooterSocials/injectRichFooter ověřeno (viz poznámky níže)
- [x] `php tests/run.php` → 36 testů + 26 golden checků, 0 failures; poté `git checkout -- storage/published/`
- [x] Rozsah: nic mimo Fázi 2 (žádné PHTML views/ViewModely, testy/šablony netknuty)
- [x] Verdikt SCHVÁLENO + commit (jen soubory Fáze 2) + aktualizace UKOL.md

Pozn.: wip-commit krok ze skillu /zadani přeskočen záměrně — necommitnuté změny byly předmětem review, commit až po verdiktu.

### Fáze 2 — poznámky pro Fázi 3

Vytvořené služby v `app/Services/Render/` (namespace `App\Services\Render`, `declare(strict_types=1)`, statické metody):

- `PageComposer` — jediný orchestrátor render kaskády; `compose(array $ctx)` s módy `MODE_LIVE`/`MODE_PUBLISH`. Live-only kroky (cenik layout, homepage hero, breadcrumb, sanitizeFrontendHtml, editor chrome) volá jako globální funkce z `public/index.php`; publish-divergentní kroky přes injektované `PublishPageChrome`. Pořadí kroků obou módů je byte-ekvivalentní s původními inline sekvencemi.
- `PublishPageChrome` (interface) — kontrakt pro publish-only chrome, které zůstává záměrně divergentní: `injectSiteLogo`, `injectPageHeroImage`, `injectPageHeroVideo`, `injectGlobalVideoOverlayStyles`, `injectEditorialGoldOverrides`. Implementuje `PublishService` (metody změněny private→public). Sjednocení s live variantami (webp `<picture>`, optimalizovaná média) je plán Fáze 3/6.
- `HomepageSectionRenderer` — `blockContent()`, `injectHomepageMissingSections()` (autofill cenik/blog/faq/proces sekcí), `injectHomepageThemeDataSources()` (bezova-lens-tale + elegant-white data sources), `injectHomepageAboutVisualSource()`. Publish varianta about-visual získala try/catch+log z live (graceful superset, dřív by výjimka shodila publish).
- `MarkupNormalizer` — legacy markup normalizace + externalizace inline stylů/skriptů: `normalizeLegacyFrontendMarkup($html, $withContactAccessibility=false)`, `normalizeContactFormAccessibility()`, `normalizeLegacyTemplateSource()`, `externalizeBodyCssVariables()`, `extractInlineStyleBlocks()`, `extractInlineBodyScripts()`, `injectBodyCssVariableToken()`, `injectBodyPageKey()`, `injectSharedFrontendAssets()`. Divergence: live volá contact-accessibility pass (flag true), publish ne — sjednocení až v kontakt archetypu (Fáze 4). Publish nově získal i pravidlo `rgba(255,255,255,.4)` → `frontend-note` (dřív jen live) — golden ověřeno beze změny výstupu.
- `FooterRenderer` — `buildRichFooterHtml(int $siteId)` + `injectFooterSocials()`. **Řešení divergence injectFooterSocials/injectRichFooter:** kanonickou variantou se stala live verze (bohatší: `__logoBox` wrapper loga, grid `minmax(300px,1fr)`, kontaktní položky `grid-template-columns:86px ...`). Bezpečné, protože (a) publish stejně footer `<style data-cms-rich-footer>` odstraňuje v `extractInlineStyleBlocks` (skip-list blok se z HTML vyřadí a nereinjektuje — pre-existující chování), (b) markup obou variant je shodný pro web bez `logo_url` (demo). Zanikl publish fallback obalující footer do `<footer>` při jeho absenci v HTML (edge case, golden beze změny). `PublishService::injectRichFooter` nyní deleguje na `FooterRenderer::injectFooterSocials`.
- `ContactNormalizer` — `normalizePhoneForHref()`, `formatPhoneForDisplay()`, `phoneDedupKey()`, `extractPhoneNumbersFromText()`, `enrichContactWithLegacyBlocks()` (e-mail/telefony/adresa/sociální sítě z legacy bloků), `ensureRbDemoContactAddress()` (no-op, zachováno). Sjednocen default form e-mail na `ContactRepository::DEFAULT_FORM_EMAIL` — ověřeno identické s live `defaultContactFormEmail()`.
- `SocialLinkRenderer` — `normalizeSocialType()`, `socialLabel()`, `socialIconSvg()` (inline SVG ikony).
- `HeroMediaRenderer` — `buildHeroImageHtml()`, `buildHeroVideoHtml()`, `buildVideoEmbedUrl()` (YouTube/Vimeo embed), `ensureHeroVideoStyle()`, `hasHeroVideoElement()`.
- `MediaUrlResolver` — `normalizePublicAssetUrl()`, `publicMediaUrl()` (root-relative `/media/` mapování, žádná hardcoded doména), `resolveDemoMediaUrlByFilename()`. Deleguje sem i `PortfolioPublishService::publicMediaUrl`.

Pro Fázi 3: staré funkce v `public/index.php`/`PublishService` zůstaly jako tenké delegující wrappery (kvůli ostatním callsites, např. `sanitizeFrontendHtml` je volá) — jejich smazání je naplánováno ve Fázi 6. `sanitizeFrontendHtml` (index.php:322) zůstává live-only a volá se z `PageComposer::composeLive` jako globální funkce.

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

### Fáze 1 — poznámky pro Fázi 2

- Načítání tříd: `vendor/autoload.php` je ručně psaný PSR-4 shim (`App\ → app/`), ne skutečný Composer classmap — nové soubory se načtou automaticky, bez regenerace. `App\View\ViewEngine` → `app/View/ViewEngine.php` (pozor: `app/View/` = PHP třídy, budoucí `app/Views/` = .phtml šablony; na Windows nekolidují, liší se koncovým `s`).
- Vzniklé soubory (namespace `App\View`, `declare(strict_types=1)`):
  - `app/View/ViewResolver.php` — kaskádová resoluce přes seřazené rooty (téma → core). API: `resolve(name): ?string` (null když nenalezeno), `resolveOrFail()`, `assertValidName()`, `roots()`. Bezpečnost: regex `~^[a-z0-9][a-z0-9/_-]*$~D` (modifikátor `D` blokuje trailing newline), explicitní guardy na `\0` a `..`, `realpath()` + case-aware prefix containment na Windows. Neplatné/malicious jméno → `InvalidArgumentException` (ne resoluce mimo root).
  - `app/View/ViewContext.php` — `$this` v šabloně: `e()`, `raw()`, `partial()`, `block()`, `inlineAttr()`, `slot()/endSlot()/renderSlot()`, `layout()`, `asset()`, `mediaUrl()`. Žádné DB dotazy. `contentBlocks`, `editMode`, `assetBaseUrl` přicházejí jako sdílené options enginu.
  - `app/View/ViewEngine.php` — `render(view, data): string`. `$this` binding: template se `include`uje uvnitř closure definované v `ViewContext::includeTemplate()` a volané přes `->call($this)`, takže `$this` ve `.phtml` == ViewContext; `ob_start` + `extract($data, EXTR_SKIP)` (interní `$__file/$__data` nelze přepsat daty). Layout se renderuje ve STEJNÉM kontextu (aby `renderSlot()` i extrahované slot-proměnné fungovaly); výstup stránky se stává implicitním slotem `content`.
  - `app/View/LegacyLayoutView.php` — bytová kopie `PageRenderer::applyLayout()` (`{page_content}` → `{nav}` → `{site_name}` escaped `ENT_QUOTES,UTF-8`, v tomto pořadí). POZOR pro Fázi 2/3: prázdný `$nav` `{nav}` ODSTRANÍ (str_replace bezpodmínečně), doc-komentář „pass '' to leave unreplaced" je nepřesný — parita s reálným chováním ověřena testem.
- Testy: `tests/ViewEngineTest.php` (9 testů) — registrují se do `tests/run.php` přes `register_view_engine_tests($test,$assert)`; soubor je i samostatně spustitelný (`php tests/ViewEngineTest.php`). Fixtures v `tests/fixtures/views/{core,theme}/…` (každá končí `?>` aby případný trailing newline pohltil PHP). `app/Views/` zůstal ve Fázi 1 nezaložený (přijde ve fázích 3–5).
- `mediaUrl()` volá globální `publicMediaUrl()` když je načtená (live/publish), jinak bytově shodná privátní kopie — Fáze 2 sjednotí zdroj (extrakce do sdílené služby).
- Ověřeno: `php tests/run.php` → 36 testů (27 původních + 9 nových) + 26 golden checků, 0 failures, exit 0. Golden výstup beze změny (nový engine nikdo nevolá).

## Výsledek

Implementován golden-master testovací systém pro Fázi 0 refactoringu šablonování:

- `tests/GoldenMasterRunner.php` (nový): bootstraps `public/index.php` jednou (bez spuštění routing dispatch), renderuje 12 live rout (`home`, `sluzby`, `cenik`, `faq`, `kontakt`, `blog`, `nase-prace`, `hodnoceni`, `generic-page` = `/podminky-uziti`, `404`, `blog-detail` a `portfolio-detail` — poslední dva se slugem dynamicky vybraným z DB) přímým voláním `tryServe*()` funkcí, a 14 publish cílů (všechny publikované stránky z `pages` tabulky přes `PublishService::publishPage()` + `generate404()`). Obsahuje `normalizeHtmlForGolden($html, $editMode = false)`, `update()`, `test()` (s LCS řádkovým diffem pro `+N -M` report) a `compareOne()`.
- `public/index.php`: přidán 4řádkový guard `if (defined('SEO_CMS_GOLDEN_BOOTSTRAP')) { return; }` před finální routing dispatch — bez dopadu na produkční chování (konstanta se mimo test harness nikdy nedefinuje).
- `tests/run.php`: nové CLI režimy `--update-goldens` a `--test-goldens`; výchozí režim (bez flagů) spouští původní testy a navíc — pokud `tests/goldens/` existuje — golden porovnání, vše sečtené do jednoho `$failures` čítače a exit kódu. Přidán globální `ob_start()` kvůli opravě popsané výše.
- `tests/goldens/live/*.normalized.html` (12 souborů) a `tests/goldens/publish/*.normalized.html` (14 souborů) — vygenerované baseline, UTF-8, LF, bez zbytkových timestampů/CSRF tokenů/asset hashů (ověřeno ručně přes grep).
- Ověřeno: `php tests/run.php` (27 starých testů + 26 golden checků, exit 0), `php tests/run.php --update-goldens` (26/26 OK), `php tests/run.php --test-goldens` (26/26 OK), a manuálně simulované scénáře `CHANGED (+N -M)` a `MISSING` reportu (dočasnou korupcí/smazáním golden souboru a jeho obnovením).
- `storage/published/*` ponechán v původním (committed) stavu — republikace během testů byla po ověření vrácena přes `git checkout`.

Čeká se na schválení baseline uživatelem (`git diff tests/goldens/` — v tomto případě nové netrackované soubory, tedy spíš `git status`/prohlédnutí obsahu `tests/goldens/`) a následný commit `test(templates): add golden-master runner for live routes and publish output`.
