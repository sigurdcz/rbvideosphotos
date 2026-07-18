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

### Fáze 3 — Layout a chrome do PHTML [HOTOVO]
- [x] Delegovat na subagenta (běží na Opus 4.8 jako dedikovaný agent)
- [x] `layouts/default.phtml` + partials (nav, logo, footer, footer-socials, hero, hero-video, breadcrumb, cookie-consent)
- [x] Přesun `getSharedComponentCss()` do `app/Views/assets/shared-components.css` (byte-identické, md5 ověřeno)
- [x] `elegant-white3` dostane `views/layout.phtml` (přes `ViewFactory`/`ThemeLayoutRenderer`)
- [x] Golden (26/26 OK) + visual QA (390×844, 1920×1080 — homepage + /cenik, bez regrese)
- [x] Commit `refactor(templates): move layout chrome to PHTML partials`

### Fáze 3 — poznámky pro Fázi 4

**Jak funguje rozlišení legacy-vs-PHTML téma (KLÍČOVÉ pro Fázi 4):**
Rozlišení NENÍ v `PageComposer`, ale UPSTREAM od něj — na kroku aplikace layoutu:
- Live: `applyThemeTemplate()` (public/index.php), publish: `PublishService::publishPage()`.
- Oba místa nově volají `App\Services\Render\ThemeLayoutRenderer::hasPhtmlLayout($themeKey)`. Pokud téma má `Templates/<key>/views/layout.phtml` → `ThemeLayoutRenderer::render()` (přes `ViewEngine`); jinak legacy `PageRenderer::applyLayout(layout.html, …)`. Per-téma opt-in, žádný big-bang. `stripInlineStyles()` + `injectBodyClass()` běží po obou cestách stejně (PHTML výstup je byte-ekvivalentní s applyLayout).
- Detekce: `ViewFactory::themeLayoutPath($key)` = `is_file(Templates/<key>/views/layout.phtml)`. Klíč tématu validován regexem před sestavením cesty.
- `PageComposer` inject kaskáda je ZÁMĚRNĚ nezměněná pro obě cesty (docblock to vysvětluje). Důvod viz níže.

**Vytvořené komponenty:**
- `app/View/ViewFactory.php` — první produkční vstup do ViewEngine. `forTheme($key, $opts)` → `ViewEngine` s rooty `[Templates/<key>/views, app/Views]` (téma vyhrává). `roots()`, `themeLayoutPath()`. Bezpečnost: theme-key regex `^[a-z0-9][a-z0-9_-]*$`, neplatný klíč = jen core root (ViewResolver navíc dělá realpath containment).
- `app/Services/Render/ThemeLayoutRenderer.php` — `hasPhtmlLayout()`, `render($key, $content, $siteName, $nav)` (view name `layout`).
- `app/Services/Render/FooterViewData.php` — `forSite(int $siteId): array` — provider dat pro footer partial (brand, phones, email, addressLines, socials{type,url,label,iconSvg}, year). Žádné HTML kromě trusted icon SVG (raw v partialu).

**Vytvořené partials + kontrakt (data, která očekávají):**
- `Templates/elegant-white3/views/layout.phtml` — referenční layout aktivního tématu. Byte-reprodukuje `layout.html`. Data: `siteName`(string), `nav`(string), `content`(string raw). Pozn.: `<?= ?>` u content potřebuje kompenzační newline (PHP polyká 1 newline za `?>`) — v souboru je prázdný řádek za content echem.
- `app/Views/layouts/default.phtml` — core fallback layout (v Fázi 3 nikdo přímo nepoužívá; elegant-white3 má vlastní). Stejný kontrakt (siteName/nav/content).
- `partials/nav.phtml` — data `nav`(string), `siteName`(string). Raw passthrough + nahrazuje legacy `{site_name}` placeholder v nav HTML escapovaným jménem (applyLayout to dělal globálním str_replace — nav ho reálně obsahuje).
- `partials/logo.phtml` — data `siteName`(string). Escapovaný brand text. Logo OBRÁZEK (když `logo_url` != '') zatím dál řeší `injectSiteLogo` v post-render (aktivní web má prázdný logo_url → no-op). Sjednocení do partialu = Fáze 6.
- `partials/footer.phtml` — data `footer`(array z FooterViewData). Rich footer vč. inline `<style data-cms-rich-footer>` (extractInlineStyleBlocks ho pak skip-listuje). Socials sekci renderuje přes sub-partial.
- `partials/footer-socials.phtml` — data `socials`(array). Položky nebo fallback `Doplnte socialni site`. (Nahrazuje koncept z mrtvé `buildFooterSocialsHtml`, kterou jsem NErušil — dead code, smazání Fáze 6.)
- `partials/cookie-consent.phtml` — bez dat (statické). Přesná kopie heredocu z `injectCookieConsent`. injectCookieConsent nyní renderuje tento partial.
- `partials/hero.phtml` — data `imageUrl`(string, prázdný → prázdný výstup). Zdroj pravdy pro `HeroMediaRenderer::buildHeroImageHtml`.
- `partials/hero-video.phtml` — data `embedUrl`(string, prázdný → nic), `posterUrl`(string). `buildVideoEmbedUrl` (logika) zůstává v `HeroMediaRenderer::buildHeroVideoHtml`, který partial renderuje.
- `partials/breadcrumb.phtml` — data `items`([['label','url'], …] s už normalizovanými url). `renderBreadcrumbNav` (index.php) normalizuje url a renderuje partial.

**Co zůstalo BEZE ZMĚNY a PROČ (otevřené riziko/rozhodnutí pro reviewera):**
- `PageComposer::composeLive/composePublish` inject kaskáda je NEZMĚNĚNÁ — inject* volání (injectFooterSocials, hero, breadcrumb, cookie via sanitize) se NEODSTRANILA ani pro aktivní téma, ačkoliv zadání bod 6 to zmiňuje. Důvod (záměrné, zdokumentované rozhodnutí, ne workaround):
  1. **Ordering/externalizace:** rich footer `<style data-cms-rich-footer>` MUSÍ vzniknout AŽ PO `stripInlineStyles()` (jinak by ho stripInlineStyles smazal místo pozdějšího skip-listu v extractInlineStyleBlocks). Layout.phtml běží PŘED stripInlineStyles, takže footer do něj vložit nelze bez reorderingu → riziko rozbití CSS externalizace.
  2. **Live speciální stránky (faq/kontakt/sluzby/blog/portfolio/hodnoceni) NEJDOU přes PageComposer** — mají vlastní inject sekvenci a taky volají `applyThemeTemplate` (tj. i ony teď jedou přes PHTML layout). Kdyby layout renderoval rich footer a PageComposer injectFooterSocials vynechal, publish (jde přes PageComposer) a live speciálky by se rozešly / dvojitý footer. Bezpečné odstranění injectů = až Fáze 4, kdy se tyto routy migrují na archetypy.
  3. Aktivní web má **prázdný `logo_url`** → `injectSiteLogo` je no-op; odstranění by nic neušetřilo a rozbilo by ostatní témata/stránky.
  Takže Fáze 3 přesouvá do PHTML **layout shell** (první produkční ViewEngine caller) a **HTML fragmenty chrome** (jako zdroj pravdy přes partials), ale ZACHOVÁVÁ inject pořadí. Odstranění injectů pro aktivní téma je připravené (rozlišení existuje) a je bezpečné až po Fázi 4.
- Legacy témata (bez `views/layout.phtml`): beze změny, dál přes `applyLayout(layout.html)`.

**EOL pozn.:** `public/index.php` je CRLF, nové .phtml partials jsou LF; golden runner normalizuje EOL (`str_replace(["\r\n","\r"], "\n")`), takže výstup je byte-ekvivalentní PO normalizaci (což je kontrakt tohoto projektu). Repo má `core.autocrlf=true` (git warning je očekávaný). cookie-consent partial má vnitřní newliny → po fresh checkoutu CRLF → golden normalizuje → OK.

**Nové testy:** `tests/Phase3PartialsTest.php` (`register_phase3_partials_tests`) — 9 DB-free testů (partials hero/hero-video/breadcrumb/nav/logo/footer-socials, ThemeLayoutRenderer detekce + byte-parita s applyLayout, ViewFactory root ordering). Registrováno v `tests/run.php`.

**Visual QA výsledek:** headless Chrome (ne VS Code integrated browser skript — ten je session-specific/brittle), 390×844 + 1920×1080, homepage + /cenik. Vše renderuje korektně (brand/logo, nav desktop bar i mobil hamburger s vyřešeným {site_name}, hero, obsah, pricing karty). Bez layout/spacing/color regrese. Vizuální identita je navíc dokázána konstrukčně: served HTML byte-identické (26/26 golden) + shared-components.css byte-identické (md5).

**Ověřeno:** `php tests/run.php` → 45 testů + 26 golden checků, 0 failures. Po testech `git checkout -- storage/published/` (republish šum).

### Fáze 3 — post-commit review (91b3b44)

- [x] Projít plný diff commitu (partials, layout.phtml, ViewFactory, ThemeLayoutRenderer, index.php, PublishService)
- [x] Ověřit delegaci builderů na partials (jediný zdroj pravdy, ne duplikace)
- [x] Bod 2 rizika: speciální stránky (faq/kontakt/…) — PHTML layout + staré injecty → žádný dvojitý/chybějící footer
- [x] Bezpečnost: escaping v partials, ViewResolver containment přes ViewFactory, žádné nové SQL, CSRF netknuto
- [x] `php tests/run.php` → 45 testů + 26 golden, 0 failures (+ `git checkout -- storage/published/`)
- [x] Manuální ověření /faq a dalších 5 speciálních rout + homepage přes lokální server (footer/nav 1×, ne 2×)
- [x] CRLF/LF poznámka — ověřena neškodnost
- [x] Verdikt + aktualizace UKOL.md (bez opravného commitu — žádná chyba nenalezena)

**Verdikt review: SCHVÁLENO** (žádná oprava potřeba). Ověření:

- **Dvojitý footer vyloučen konstrukčně i empiricky.** `FooterRenderer::injectFooterSocials` NAHRAZUJE obsah prvního `<footer>` (preg_replace, ne append); `layout.phtml` renderuje jen placeholder footer byte-shodný s `layout.html`; rich partial `partials/footer` se renderuje výhradně přes `buildRichFooterHtml` (grep: žádný jiný caller, layouty ho nevolají). Curl na živém serveru (/faq, /kontakt, /sluzby, /blog, /hodnoceni, /nase-prace, /): všude přesně 1× `<footer>`, 1× `site-footer-rich`, 1× nav, 1× breadcrumbs, 1× cookie-consent, 0× zbylý `{site_name}`.
- **Delegace ověřena:** jediné kopie markup fragmentů jsou v `.phtml`; zbylé výskyty class names v PHP jsou jen CSS selektory/DOM matchery. `buildFooterSocialsHtml` (index.php:1432) je mrtvý kód bez callerů (smazání Fáze 6).
- **Bezpečnost:** `raw()` jen pro (a) `$content` (sanitizace dál v pipeline, jako legacy str_replace), (b) nav markup z DB (stejná důvěra jako legacy `applyLayout`), (c) hardcoded icon SVG. Vše ostatní `e()`. ViewFactory validuje theme key regexem PŘED sestavením rootu; ViewResolver containment neobejit. Žádné nové SQL (FooterViewData jde přes existující repozitáře). CSRF /kontakt: `_csrf` input + honeypot na místě.
- **EOL:** nové soubory jsou v repu LF, `file` nepotvrdil žádné CRLF; golden runner EOL normalizuje; případný CRLF po fresh checkoutu (autocrlf=true, žádný .gitattributes) je pro CSS/HTML neškodný.
- **Drobné poznámky (ne chyby) pro Fázi 4/6:**
  1. Legacy `applyLayout` nahrazoval `{site_name}`/`{nav}` globálně (i uvnitř page contentu); PHTML cesta jen v nav partialu a fixních slotech. Divergence nastane jen pokud by DB obsah obsahoval placeholder — dnes nenastává (goldeny byte-shodné).
  2. `applyThemeTemplate`/`publishPage` teď aplikují PHTML layout nezávisle na `getLayoutHtmlForPage()` — pro elegant-white3 shodné chování (manifest má vždy layout.html), ale kdyby se layout.html smazal, PHTML cesta se stane jediným zdrojem (zamýšlené).
  3. **Deploy:** FTP deploy MUSÍ nově zahrnovat `app/Views/**` (partials + assets) a `Templates/elegant-white3/views/**`. Chybějící partial = výjimka `View not found` (500 na webu); chybějící `shared-components.css` = tichá ztráta shared CSS (`getSharedComponentCss` vrací ''). **Ověřeno (2026-07-18):** `.github/workflows/deploy.yml` kopíruje `rsync ./ _deploy/` s explicitním exclude listem, který nezmiňuje `app/Views/` ani `Templates/*/views/` — nové soubory se nasadí automaticky, žádná změna CI potřeba.

### Fáze 4 — Speciální archetypy (9 samostatných commitů)

**KOREKCE premisy zadání Fáze 4a:** Zadání předpokládalo, že faq/sluzby/cenik/hodnoceni všechny mají vlastní `tryServe*Page()` heredoc funkce. Ověřeno v kódu: platí jen pro **faq** (`tryServeFaqPage`) a **sluzby** (`tryServeServicesPage`) — ty skutečně kompletně obchází `templates.html_source`/`PageRenderer`/`PageComposer` a skládají celou stránku heredocem. **cenik** a **hodnoceni** už procházejí generickým `tryServeDatabasePage()` → `PageComposer` pipeline; cenik má jen post-render regex-splice krok (`injectCenikPageLayout`, live-only global funkce volaná z `PageComposer::composeLive`) a hodnoceni je čistě `content_blocks` autofill (`HomepageContentService::fillReviewFallbacks` → `ReviewContentService::buildListingHtml`, sdíleno live+publish už dnes). Detaily a dopad viz poznámky u cenik/reviews níže.

**Zásadní zjištění (vysvětluje proč live/publish divergují u faq/sluzby/cenik):** `PublishService::publishPage()` běží z admin vstupního bodu a NEMŮŽE volat globální funkce definované v `public/index.php` (jiný bootstrap/scope). Proto `injectCenikPageLayout`/heredoc FAQ/sluzby logika v `public/index.php` byla `PublishService` nedosažitelná — publish pro tyto stránky vždy padal zpět na generický `content_blocks`/`html_source` render, který obsahuje zastaralý/odlišný obsah (viz FAQ níže — publikovaný FAQ obsahoval placeholder "Doplňte první otázku a odpověď." a jen 5 z 12 reálných otázek, BEZ accordion JS). Přesun logiky do `App\ViewModels\*`/`App\View\ViewFactory` (autoloadované, dostupné odevšad) toto řeší při zdroji — to je skutečný smysl Fáze 4, ne jen kosmetika.

- [x] **faq**: `App\ViewModels\FaqPageData::forSite($siteId)` → `['items'=>[['id','question','answer']]]` z `FaqRepository::allActive()`. `app/Views/archetypes/faq.phtml` (kompaktní styl bez zbytečných newlines — priorita byl prázdný live golden diff, ne plán-ukázkový hezčí multi-line foreach). `app/Views/assets/faq.css` (přesun `$faqCss` heredocu, načítá se `file_get_contents()` + `rtrim`). `tryServeFaqPage()` v `public/index.php`: heredoc `$pageContent`/`$faqHtml`/`$faqCss` nahrazeny voláním `FaqPageData::forSite()` + `ViewFactory::forTheme(null,['editMode'=>...])->render('archetypes/faq', [...])`; degenerovaná fallback větev (žádné téma ANI site layout_html) zjednodušena na reuse `$pageContent` místo třetí kopie markupu. `PublishService::publishPage()`: nová `renderArchetypeContent($pageKey,...)` — pro `pageKey==='faq'` obchází generický `$this->renderer->render(html_source,...)` a volá STEJNÝ `FaqPageData`+`ViewFactory` (přes plně kvalifikované třídy, editMode vždy false → inline atributy se nikdy nevyrenderují na publish). Hero title/sub na publish straně řeší nová sdílená `HomepageSectionRenderer::firstBlockValue()` (extrahováno z globální `firstBlockValue()` v index.php, která teď deleguje — live beze změny chování). CSS vrstvení: nový `$archetypeCss` se prependuje k `$css` jen když neprázdný (no-op pro všechny ostatní stránky).
  - **Golden:** live `faq: OK` (beze změny, 0 diff). Publish `faq: CHANGED (+8 -12)` → schváleno a aktualizováno (`--update-goldens`). **Toto je zamýšlená oprava obsahového bugu, ne regrese**: staré publikované `/faq` ukazovalo jen 5 z 12 reálných otázek, včetně doslovného placeholderu "Doplňte první otázku a odpověď." místo skutečné otázky, HTML-entity-encoded text (`&#268;ast&eacute;...`) místo UTF-8, a ŽÁDNÝ accordion JS (otázky byly staticky vypsané, ne collapse/expand). Nově: všech 12 otázek, stejný markup (`faq-item`/`faq-q`/`faq-a`) a chování jako live, UTF-8 text. CSS třídy beze změny → žádná vizuální regrese, jen doplnění chybějícího obsahu/chování.
  - `php tests/run.php` → 45 testů + 26 golden, 0 failures. `git checkout -- storage/published/` po testech.
  - **Visual QA:** live route je byte-identická (ověřeno goldenem) → žádné vizuální riziko. Publish route nově používá STEJNÉ CSS třídy jako live (které už prošly Fázi 3 visual QA), jen s více položkami. V tomto agent sezení NEBYL k dispozici headless browser/screenshot nástroj (jen textové nástroje) — pixel-diff screenshot 390×844/1920×1080 nebyl proveden přímo; doporučeno spustit `visual-screenshot-score-loop` skill jako follow-up před mergem, i když riziko je nízké (žádné nové CSS třídy, žádná změna existující).
  - **Inline editor sanity:** ověřeno přímým voláním `ViewEngine::render()` s `editMode=true` — výstup obsahuje `data-inline-key="hero_h1"`, `data-inline-key="faq:{id}:question"`, `data-inline-key="faq:{id}:answer"` atd., přesně stejný formát jako starý `inlineAttrs()` helper (`ViewContext::inlineAttr()` byl navržen jako bytový ekvivalent už ve Fázi 1).
- [x] **sluzby**: `App\ViewModels\SluzbyPageData::forSite($siteId)` → `['items'=>[['id','title','description','image_url','button_text','button_url']], 'heroImage'=>string]` z `ServiceRepository::allActive()` (heroImage = první položka s neprázdným obrázkem, stejná logika jako původní heredoc). `app/Views/archetypes/sluzby.phtml` + `app/Views/assets/sluzby.css` (přesun `$css` heredocu). `tryServeServicesPage()`: heredoc `$pageContent`/`$cards`/`$css` nahrazeny `SluzbyPageData::forSite()` + `ViewFactory::forTheme(null,['editMode'=>...])->render('archetypes/sluzby', [...])`; degenerovaná fallback větev (bez tématu) beze změny — už předtím správně reużívala `$pageContent` proměnnou, teď jen sourced jinak. `PublishService::renderArchetypeContent()` rozšířen o `pageKey==='sluzby'` větev (stejný vzor jako faq — `HomepageSectionRenderer::firstBlockValue()` pro hero, `SluzbyPageData`+`ViewFactory` pro obsah, `sluzby.css` vrstvený přes `$archetypeCss`).
  - **Newline gotcha zaznamenaná pro příští archetypy:** `<?= $expr ?>` na konci řádku před řádkem, který MUSÍ zůstat samostatný (`{$heroBg}` placeholder mezi dvěma sekcemi), PHP polyká 1 newline za `?>` → bez kompenzace by se `<img ...>` a `<div class="container">` slily na jeden řádek. Řešeno stejným vzorem jako `layout.phtml` z Fáze 3 (`Templates/elegant-white3/views/layout.phtml`) — jeden prázdný řádek navíc v souboru hned za `<?= ?>` kompenzuje polknutý newline. Ověřeno přímým renderem přes `ViewEngine` před spuštěním golden testů (levnější iterace než čekat na golden diff).
  - **Golden:** live `sluzby: OK` (beze změny). Publish `sluzby: CHANGED (+7 -7)` → schváleno, `--update-goldens`. Stejný vzorec jako FAQ: staré publikované `/sluzby` používalo JINÝ, homepage-orientovaný builder (`HomepageContentService::buildServicesGridHtml`/`buildServiceCardHtml`, třídy `hp-svc-card`/`svc-grid`, entity-encoded text, navíc extra "Kontaktujte nás" CTA co live vůbec nemá) — teď stejné třídy/markup/CTA jako live (`service-card`/`services-grid`, žádná extra CTA), plus chybějící `<img class="hero-bg">` vrstva teď přítomná (live ji vždy měl). CSS třídy `.service-card`/`.services-grid` teď skutečně dodané (`sluzby.css` přes `$archetypeCss`) — dřív publish spoléhal na `.hp-svc-card` styly ze sdíleného/tématového CSS.
  - `php tests/run.php` → 45 testů + 26 golden, 0 failures. `git checkout -- storage/published/` po testech.
  - **Visual QA:** live beze změny (byte-identický golden). Publish nově používá stejné, už vizuálně ověřené CSS třídy jako live. Bez headless browseru v tomto sezení — stejné omezení/doporučení jako u FAQ (viz výše).
  - **Inline editor:** sluzby archetyp nemá procedurální per-item inline klíče (na rozdíl od FAQ) — původní `tryServeServicesPage` NIKDY nevolal `inlineAttrs()` pro jednotlivé karty služeb (jen pro hero title/sub), takže `sluzby.phtml` žádné per-item `data-inline-key` neemituje — to je zachování původního chování, ne opomenutí.
- [x] **cenik**: ARCHITEKTONICKY JINÝ vzor než faq/sluzby (viz korekce premisy výše) — `/cenik` zůstává generickou DB stránkou (`tryServeDatabasePage()`/`PageRenderer`/`PageComposer`, html_source + content_blocks pro hero/breadcrumb/SEO beze změny); nahrazena jen dynamická "packages+CTA" sekce, která byla dřív live-only regex splice (`injectCenikPageLayout`/`buildCenikPackageCardsHtml`, global funkce v `public/index.php`). `App\ViewModels\CenikPageData` — `forSite($siteId)` (vlastní `PageRepository::findByUrlPath('cenik')`+`BlockRepository::allByPage()` lookup, konzistentní se signaturou ostatních 3 archetypů) i `fromBlocks($blocks)` (bez DB dotazu — `PageComposer` už má blocks pro aktuální stránku načtené, použito v produkční cestě). Vrací `packages[]` (title/price/summary/features[]/featured) parsované z `cenik_card_1..3_title/price/desc` (stejná parse logika jako originál — rozdělení popisu na souhrn+odrážky podle prázdného řádku, filtr "Obsahuje:" nadpisu) + heading/intro/other/cta textová pole. `app/Views/archetypes/cenik.phtml` — reprezentuje JEN dynamickou sekci (ne celou stránku jako faq/sluzby) — jedna kompaktní řádka beze změny whitespace. `App\Services\Render\CenikPageRenderer::inject($html, $blocks, $pageKey)` — nová sdílená třída (namespace `App\Services\Render`, tedy autoloadovaná odevšad) nahrazující obě staré global funkce; zachovává STEJNÝ regex-splice mechanismus (nahrazuje `<section class="page-hero">...</section>` až po `<footer>`) a stejné "no-op když packages===[]" chování. `PageComposer::composeLive` I `composePublish` teď volají `CenikPageRenderer::inject()` na stejném místě v pořadí (dřív jen composeLive) — **žádná zvláštní větev v `PublishService` nebyla potřeba** (na rozdíl od faq/sluzby), protože `PageComposer::composePublish()` je už volané z `publishPage()` a cenik prochází standardní `$this->renderer->render(html_source,...)` cestou beze změny — jen následná splice sekce je teď sdílená.
  - **Smazáno:** `buildCenikPackageCardsHtml()` a `injectCenikPageLayout()` (global funkce, `public/index.php` řádky ~429-524) — ověřeno grepem, žádní jiní callers.
  - **Golden:** live `cenik: OK` (beze změny — splice mechanismus i výstup identické). Publish `cenik: CHANGED (+1 -66)` → schváleno, `--update-goldens`. **Stejný vzorec jako faq/sluzby**: staré publikované `/cenik` používalo statický, zastaralý `pricing_body`/`pricing_packages` block (HTML-entity-encoded, BEZ diakritiky — "Zakladni svatba"/"Nejoblibenejsi" misto "Základní"/"Nejoblíbenější", jiné, kratší popisy), zatímco live vždy ukazoval bohatší verzi z `cenik_card_*` bloků (skutečná diakritika, delší popisy, badge). Po sjednocení: `diff live publish` u cenik ukazuje, že packages+CTA sekce je nyní BYTE-IDENTICKÁ mezi live a publish (ověřeno ručním diffem obou goldenů) — zbylé rozdíly jsou stejné pre-existující, nesouvisející chrome divergence jako u všech ostatních stránek (og:image absolutní URL, skip-link, hero-video vs hero-img div, cookie-consent placement, breadcrumb jen live).
  - `php tests/run.php` → 45 testů + 26 golden, 0 failures. `git checkout -- storage/published/` po testech.
  - **Visual QA:** live beze změny (byte-identický golden). Publish nově produkuje BYTE-IDENTICKÝ packages markup jako live (ověřeno diffem), který byl vizuálně schválen ve Fázi 3. Bez headless browseru v tomto sezení — stejné omezení jako u FAQ/sluzby výše.
  - **Inline editor:** archetyp `cenik.phtml` nevypouští žádné `data-inline-key` atributy — originál (`buildCenikPackageCardsHtml`) je taky nikdy nevypouštěl. Cenové karty na `/cenik` nemají inline-overlay editaci; editují se přes standardní admin block editor na `cenik_card_*` klíčích — zachováno beze změny.
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
