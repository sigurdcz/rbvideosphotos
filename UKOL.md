# UKOL: Premium Photo zacatek timeline progressu

Datum: 2026-07-20
Zadani: Nechat sedou osu pres sekci, ale zlaty scroll progress zacit az pod nadpisem a skryt bod, dokud je timeline na nulovem hornim progressu.

Hotovo kdyz: Nad nadpisem neni zlata vypln, bod pri nulovem progressu neni videt, po scrollu se bod i zlata vypln objevi pod nadpisem a desktop/mobil to potvrdi cerstvymi Chrome screenshoty.

## Kroky

- [x] Zmerit geometrii nadpisu, seznamu a aktualniho progressu a zachytit baseline.
- [x] Presunout zacatek zlate vyplne pod nadpis a ridit viditelnost bodu podle progressu.
- [x] Regenerovat assety a overit horni i posunuty stav na desktopu a mobilu.
- [x] Spustit regresni testy, aktualizovat dokumentaci a commitnout bez push.

## Poznamky

- Rubrika 100 bodu: zlata cara pouze pod nadpisem 35, bod skryty na nule 30, spravny pohyb po aktivaci 20, desktop/mobil/runtime 15.
- Vychozi screenshot od uzivatele ukazuje dve vady: zlata vypln vede od horni hrany sekce pres nadpis a bod je viditelny na prvnim vrcholu jeste pred aktivnim postupem.
- Chrome diagnostika je rozsirena o computed geometrii `.section-title` a `.tl-list`, aby zacatek aktivni osy vychazel ze skutecneho DOM rozlozeni a nebyl odhadnut pevnym pixelem.
- Baseline 1920x900: sekce zacina na `4380.40625 px`, seznam na `4671.34375 px`, tedy aktivni osa musi zacit `290.9375 px` pod hranou sekce. Skore 45/100: sedy rail 15/15, runtime 10/15, ale zlata hranice 0/35 a skryti bodu 0/30.
- JS nyni pocita `trackStart` a `trackHeight` z aktualniho `getBoundingClientRect()` seznamu. CSS zlata vypln zacina na `--timeline-track-start`; bod dostane tridu `is-visible` pouze pro progress vetsi nez nula.
- Chrome helper ma novy `-TimelineStartOutput`: nastavi viewport 24 px pred aktivni zacatek seznamu a ulozi samostatny dukaz nuloveho stavu pred beznym scrolled capturem.
- Iterace 1 po oprave: 95/100. Screenshot `fixed-zero-1920.png` ukazuje pouze sedou osu pres nadpis; computed bod ma `opacity: 0` a `visibility: hidden`. Scrolled stav ma `trackStart: 290.94px`, zlatou vysku `496.66px` a viditelny bod. Poslednich 5 bodu ceka na zamerene zachyceni kratke aktivni zlate cary primo pod nadpisem.
- Iterace 2: 100/100. `final-active-start-1920.png` primo ukazuje prvnich 96 px zlateho progressu az od horni hrany seznamu pod titulkem; nad titulkem zustava jen seda osa. `final-zero-1920.png` nema zlato ani bod. Mobilni dvojice `final-zero-390.png` a `final-active-start-390.png` potvrzuje stejne chovani s dynamickym zacatkem `242.53 px`.
- Regrese: PHP lint bez chyby, `php tests/run.php` 56/56, golden checks 27/27 a `git diff --check` bez chyb. `README.md`, `ARCHITECTURE.md` a `INSTRUCTIONS.md` byly posouzeny bez nutnosti zmeny; technicky kontrakt a acceptance byly aktualizovany v projektove wiki.

## Vysledek

Hotovo. Seda osa zustava pres sekci, zlata vypln zacina az pod nadpisem na skutecne hrane seznamu a bod je pri nulovem progressu skryty na desktopu i mobilu.

---

# UKOL: Premium Photo viditelny scroll playhead

Datum: 2026-07-20
Zadani: Udelat pohyb timeline pri scrollovani jednoznacne viditelny vyraznym bodem a postupne vyplnovanou stredovou osou a dokazat zmenu screenshotem primo v timeline.

Hotovo kdyz: Bod je na desktopu i mobilu jasne viditelny, meni polohu podle scroll progress, vypln osy konci pod bodem a Chrome dukaz zachyti rozdilne stavy timeline.

## Kroky

- [x] Zmerit aktualni playhead a pripravit zamerene Chrome zachyceni timeline.
- [x] Doplnit vyrazny bod a scroll progress vypln osy.
- [x] Overit pred/po scrollu, mobil, testy a publikovany vystup.
- [x] Aktualizovat dokumentaci a commitnout opravu bez push.

## Poznamky

- Predchozi full-page capture prokazal zmenu inline `top`, ale bod byl v celkovem zmenseni prakticky necitelny. Nova acceptance vyzaduje nejen metriku, ale zamerene viditelny stav.
- Rubrika 100 bodu: viditelnost bodu 35, viditelny pohyb 30, progress vypln osy 20, desktop/mobile a runtime 15.
- Theme JS nyni zapisuje stejny `progressPercent` do `--timeline-progress` i `playhead.style.top`. CSS zobrazuje 20px zlaty bod s bilym stredem a vyplnuje osu od zacatku po aktualni polohu, takze scroll stav je citelny i bez sledovani samotneho bodu.
- Chrome CDP helper dostal volitelny `-InteractionOutput`: po testovacim scrollu ulozi samostatny viewport screenshot jeste pred navratem na zacatek a vedle nej zachova plny page capture. Tim lze vizualne dokazat bod primo v jeho posunute poloze.
- Prvni zamereny screenshot spravne odhalil, ze live HTML stale nacital predchozi generovany hash CSS/JS; zdrojove zmeny proto nebyly vizualnim dukazem. Pred dalsim skore je povinna regenerace publish/golden assetu a novy Chrome capture nad novym hashem.
- Druhy diagnosticky capture odhalil skutecnou pricinu: computed `position: relative` na ose i bodu a vysku osy `0 px`. Inline posun na `71.4071%` proto bod nechal u nadpisu. Finalni theme vrstva nastavuje explicitni `position: absolute`, desktop/mobile `left`, a osu rozpina pres `top: 0; bottom: 0`.
- Desktop dukaz `storage/qa/premium-photo-timeline-fade/fixed-playhead-scrolled-1920.png`: osa `3 x 1092.765625 px`, bod `20 x 20 px`, posun `0% -> 72.0734%`, konec bodu ve viewportu `y=394.984375`.
- Mobilni dukaz `storage/qa/premium-photo-timeline-fade/fixed-playhead-scrolled-390.png`: osa `3 x 1236.71875 px`, leva kotva `x=24`, posun `0% -> 65.7033%`, bod ve viewportu `y=369.796875`.
- Vysledna iterace: viditelnost 35/35, prokazany pohyb 30/30, progress vypln 20/20, desktop/mobile/runtime 15/15 = 100/100. `php tests/run.php`: 56/56 a golden checks 27/27; PHP lint a `git diff --check` bez chyb.

## Vysledek

Hotovo. Bod je ukotveny k cele timeline, pri scrollovani se pohybuje spolu se zlatou vyplni osy a focused Chrome screenshoty potvrzuji desktop i mobil.

---

# UKOL: Premium Photo prechody a timeline

Datum: 2026-07-20
Zadani: Na homepage odstranit bile fade pasy a nepatricnou hnedou mezi portfoliem a procesem a obnovit viditelnou stredovou caru timeline s teckou pohybovanou podle scrollu.

Hotovo kdyz: Portfolio a timeline navazuji cistym tmavym pozadim bez bilych fade efektu a hnede, stredem timeline vede viditelna cara, tecka meni polohu pri scrollu a cerstvy Chrome screenshot i interakcni metriky opravu potvrdi.

## Kroky

- [x] Zachytit baseline a zmerit CSS konflikt prechodu, barev a timeline vrstev.
- [x] Opravit Premium Photo CSS bez zasahu do DB obsahu.
- [x] Overit desktop, mobil, scroll tecku a vizualni skore.
- [x] Spustit testy a aktualizovat relevantni dokumentaci.

## Poznamky

- Rubrika 100 bodu: ciste hranice sekci 40, spravne tmave barvy 20, stredova cara a tecka 25, scrollova interakce 15.
- Podezreni ze zdroje: globalni `frontend-runtime.css` pridava homepage sekcim svetly pseudo-element `::before` a hnedou `section--dark`; Premium Photo musi tyto legacy efekty explicitne zrusit ve vlastnim scope.
- Baseline: `storage/qa/premium-photo-timeline-fade/baseline-home-1920.png`, Chrome 1920x900, full-page 8330 px, skore 20/100. Timeline DOM i JS existuji a playhead meni `top` 0 -> 66.7483 %, ale legacy fade, hnede pozadi a slabe/prebite vrstvy caru a tecku vizualne skryvaji.
- `cms.css` nyni v Premium Photo homepage scope rusi legacy sekcni `::before`, sjednocuje portfolio a proces na `var(--night)` a explicitne definuje desktop/mobile osu, karty, spojky a viditelnou scroll tecku.
- Iterace 1: `iteration-01-home-1920.png`, Chrome 1920x900, full-page 8159 px, skore 93/100. Fade i hneda jsou odstraneny, sekce navazuji jednim tmavym platnem a timeline geometrie je obnovena; osa byla pro finalni iteraci zesilena kvuli kontrastu v plnem screenshotu.
- Iterace 2: `iteration-02-home-1920.png`, Chrome 1920x900, full-page 8161 px, skore 100/100 pro zadane prechody/barvu/timeline. Tecka se posunula 0 -> 71.6135 % a aktivovala 4 polozky. Mobil `iteration-02-home-mobile-390.png` ma 390x11848 px a tecka se posunula 0 -> 65.3677 %.
- Do `PremiumPhotoDataContractTest.php` byla pridana regresni brana pro zruseni legacy fade, tmave portfolio, osu/tecku a JS vazbu tecky na scroll progress.
- `php tests/run.php`: 56 testu a 27 golden kontrol, 0 chyb; zmenene live/publish goldeny byly vygenerovany stejnou render pipeline. README ani ARCHITECTURE se nemeni, protoze jde o theme-specific kaskadovy fix; rekonstrukcni postup je doplnen v projektove wiki.
- Wiki `projects/seo-editor/pipelines.html` obsahuje pricinu, minimalni CSS/JS jadro a desktop/mobile acceptance; samostatny wiki commit `5c33595`.

## Vysledek

Hotovo: homepage nema legacy bile fade ani hnedou sekci, portfolio a proces sdileji designerovo tmave pozadi a timeline ma na desktopu i mobilu viditelnou stredovou osu, spojky a scrollovanou tecku. Finalni zamerene vizualni skore je 100/100.

---

# UKOL: Premium Photo specificke DB HTML snippet rendery

Datum: 2026-07-20
Zadani: Nahradit vychozi core HTML struktury vsech DB-backed bloku vlastnimi Premium Photo PHTML partialy a archetypy, zachovat realna systemova data a iterovat podle cerstvych Chrome screenshotu s poctivym 100bodovym hodnocenim.

Hotovo kdyz: Homepage i vsechny referencni podstranky pouzivaji designerovu HTML kompozici nad DB daty, parallax a scroll timeline funguji, Chrome CDP screenshoty na zamknutem viewportu projdou povinnou vizualni rubrikou a testy live/publish neukazuji regresi.

## Kroky

- [x] Overit target PNG, runtime, Chrome CDP capture a vytvorit baseline se 100bodovou rubrikou.
- [x] Zmapovat core homepage partialy a specialni archetypy na designerovu HTML strukturu.
- [x] Implementovat Premium Photo partial overridey pro sluzby, portfolio, blog, recenze, FAQ a kontakt.
- [x] Implementovat Premium Photo archetyp overridey pro portfolio, blog/detail, recenze, FAQ a kontakt.
- [x] Opravit hero media, parallax, timeline a responsive interakce nad novym DOM.
- [x] Opakovat Chrome screenshot-score iterace do maximalni poctive shody.
- [x] Overit live/publish, PHP testy, generated assety a vsechny referencni routy.
- [x] Aktualizovat wiki a vse commitnout bez push.

## Poznamky

- Pricina potvrzovana: DB zdroj dat je spravny, ale core PHTML partialy/archetypy nesou vychozi DOM, ktery neodpovida designerove Premium Photo kompozici.
- Rubrika: globalni geometrie 20, header/nav 10, hero 15, sekcni layouty 20, typografie 10, karty/media 10, interakce 5, kontakt/footer 5, runtime integrita 5.
- Iterace 0: `storage/qa/premium-photo-db-snippets/baseline-home-1920.png`, viewport 1920x900, full-page 1920x9293, skore 49/100. Chrome overil parallax a timeline playhead 0 -> 68.4192 %, 4 aktivni body.
- Nejvetsi baseline problem: core DOM obaly meni kompozici, poradi a vysku sekci; DB data jsou pritom pritomna a runtime bez chyby.
- Pridany theme-specific homepage partialy pro vsech sest datovych oblasti; PHTML prebiraji pouze ViewModel data a produkuji designerovy `tile`, `ph`, `review`, `faq-item`, `field` a `btn` DOM.
- Designerovy sekcni nadpisy a uvody byly presunuty do idempotentniho demo DB seedu; theme je nebude doplnovat skrytym JavaScript fallbackem.
- Theme JS je idempotentni a sklada DB sekce do designerova poradi; cenik na homepage skryva, FAQ presouva do kontaktni mezisekce a zachovava timeline mezi portfoliem a blogem. CMS CSS styluje nove PHTML DOM bez core card selektoru.
- Iterace 1: `iteration-01-home-1920.png`, 1920x7810, 82/100. DOM a poradi odpovidaji reference podstatne lepe; nejvetsi zbyvajici konflikt je legacy svetle pozadi timeline a tmave pozadi recenzi.
- Pridano sedm Premium Photo archetypu pro seznam/detail portfolia, seznam/detail blogu, recenze, FAQ a kontakt. Kazdy renderuje designerovy `video-band`, `page-head`, `tiles` a CTA/form struktury z predanych ViewModel dat.
- CSS nyní explicitně přebíjí legacy pozadí timeline/recenzí a definuje geometrii theme archetypů, médií, FAQ, článků a kontaktních formulářů.
- Iterace 2: `iteration-02-home-1920.png`, 1920x7810, 89/100. Timeline je tmava a recenze svetle; zbyvaji proporce a kontrola vsech podstranek.
- Doplněn Premium Photo archetyp `/sluzby`, takže ani seznam služeb nepoužívá core service-card HTML.
- Opraven dvojity navigacni `<nav>` z layoutu. Recenze jsou zamerne jen block archetyp uvnitr generic DB stranky; page view a JS premisti jeji core hero copy do designerova `page-head`, aby nevznikal zanoreny cely dokument.
- Iterace 3: horizontalni systemova navigace a samostatny recenzni blok jsou opraveny. Designeruv obrazovy triptych mezi hero a sluzbami se sklada z URL databazoveho bloku `jb-about-visual__media`; sablona nevklada nahradni staticky obrazek.
- Iterace 4: `iteration-04-home-1920.png`, 1920x8330, poctive skore 91/100. Parallax 1.3 -> -40.9 px, timeline playhead 0 -> 66.7483 %, 4 aktivni polozky. Mobilni 390x11756 drzi jednosloupcovou kompozici.
- Referencni routy po vizualni kontrole: homepage 91, Nase prace 88, Blog 92, detail blogu 90, Recenze 89, FAQ 92, Kontakt 93 bodu ze 100. Rozdily obsahu jsou realna DB media/polozky, ne staticke nahrady.
- PHP lint vsech novych PHTML/PHP souboru je cisty. `php tests/run.php`: 55 testu a 27 golden kontrol, 0 chyb. Vsechny hlavni routy vcetne `/admin/login` vraceji HTTP 200.
- Wiki pipeline byla aktualizovana o theme snippet cascade, generic reviews wrapper a aktualni Chrome dukazy; samostatny wiki commit `caf3d8f`.

## Vysledek

Hotovo: Premium Photo pouziva vlastni HTML renderery nad DB ViewModel daty, designerovy DB triptych, animovany parallax/timeline a vsechny referencni routy maji cerstvy Chrome vizualni audit. Kod, publish vystupy, goldeny, testy a dokumentace jsou pripraveny k samostatnemu commitu bez push.

---

# UKOL: Premium Photo napojit zpet na databazi

Datum: 2026-07-20
Zadani: Zachovat dodany Premium Photo design, ale odstranit staticky obsah motivu a nacitat hero media, sluzby, portfolio, blog, recenze, FAQ, kontakty a socialni site z existujicich systemovych dat; lepsi texty sluzeb zapsat do demo seedu.

Hotovo kdyz: Homepage i vsechny podstranky zobrazuji designeruv vzhled nad realnymi DB daty, hero media respektuji nastaveni systemu, timeline zustane zachovana, prvni tri FAQ a vsechny kontaktni/socialni udaje pochazeji z DB a live i publish testy prokazi stejne zdroje bez statickych nahrad.

## Kroky

- [x] Zmapovat aktualni DB view modely, route renderery a staticke Premium Photo overridey.
- [x] Nahradit staticke hero video/obrazky DB-backed hero kontraktem na homepage i podstrankach.
- [x] Prevest homepage O nas/Sluzby vcetne fotografie na DB data a aktualizovat demo seed lepsimi texty.
- [x] Prevest homepage galerii na realne portfolio Nase prace a blog na realne blogove zaznamy.
- [x] Prevest recenze, prvni tri FAQ, kontakty a socialni site na realna systemova data vcetne sdilenych mezisekci.
- [x] Odstranit staticke obsahove route layouty; zachovat pouze prezentacni markup a animovanou timeline.
- [x] Overit live i publish, DB puvod dat, interakce a relevantni dokumentaci; screenshot zachyt blokuje nedostupna browser instance.
- [x] Dokoncit wiki reflexi a samostatny commit bez push.

## Poznamky

- Predchozi Premium Photo implementace dosahla vizualni shody kopii celeho referencniho HTML. To je ted vedome povazovano za architektonickou chybu: tema smi vlastnit design, ne obchodni obsah.
- Vychozi generovane publish vystupy byly pred zmenou ulozeny samostatnym commitem `d9020f7`.
- Historicke migrace se menit nebudou; obsah sluzeb ma jit pres idempotentni `DemoContentSeeder`.
- Theme manifest nyni pouziva jeden sdileny layout. Staticke route layouty a page views byly odpojeny; homepage renderuje DB fragment a footer i navigaci znovu plni systemova pipeline.
- `DemoContentSeeder::seedServiceItems()` obsahuje sest designerem dodanych sluzeb vcetne DB media URL a systemovych odkazu; zadny z techto textu uz nemusi zit v sablone.
- Homepage umi vykreslit vsech sest DB sluzeb a kontaktni mezisekce omezuje FAQ na pozadovane prvni tri aktivni polozky.
- Premium Photo CSS mapuje DB komponenty na designerovy grid, karty, tmave sekce a formulare. JS meni existujici DB proces na timeline s playheadem; nepridava zadny obsah.
- Mrtve staticke route kopie byly odstraneny. Novy kontraktovy test hlida DB fragment, dynamickou navigaci/footer a zakazuje navrat statickeho hero videa ci kontaktu.
- Live i publish: 6 sluzeb, 5 portfolio polozek, 3 blogy, 10 recenzi, 3 FAQ a realny kontakt/socialni site. Vsechny hlavni URL i `/admin/login` vraceji HTTP 200. `php tests/run.php`: 54 testu + 27 golden kontrol, vse bez chyby.
- Browser plugin nema v teto relaci zadnou dostupnou instanci, proto nebyl predstiran novy vizualni screenshot; HTML/HTTP/publish kontrakty jsou overene.
- `sync_premium_photo_reference.ps1` uz negeneruje staticke stranky. Kontroluje zdroje a DB-first kontrakt a pri pokusu o navrat route kopii viditelne selze.
- Wiki pipeline byla aktualizovana a samostatne commitnuta jako `0993f9e`.

## Vysledek

Premium Photo je znovu DB-first: tema vlastni design, zatimco hero media, sluzby, portfolio, blog, recenze, FAQ, kontakt a socialni site dodava system. Technicke testy a live/publish data prosly; novy vizualni screenshot zustava otevreny pouze kvuli nedostupne browser instanci v teto relaci.

---

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

---

## Post-refactor: aktivace theme override kaskády pro archetypy

Datum: 2026-07-19. Refactoring (fáze 0-6 výše) je uzavřený a schválený; toto je NOVÁ práce nad jeho rámec, na žádost uživatele. Dokončeno přímo v hlavní session (Sonnet 5) po selhání subagenta kvůli měsíčnímu spending limitu (agent selhal hned v úvodním průzkumu, žádný soubor nedotčen).

### Problém

Kaskáda `Templates/<téma>/views/archetypes/<jméno>.phtml` → `app/Views/archetypes/<jméno>.phtml` v `App\View\ViewResolver` existovala a byla otestovaná už od Fáze 1, ale **nikdy se skutečně nespouštěla pro obsahové archetypy** (faq, sluzby, cenik, kontakt, blog, portfolio, homepage sekce) — všechna volání `ViewFactory::forTheme(...)` v `public/index.php`/`PublishService.php`/`HomepageContentService.php`/`ReviewContentService.php` natvrdo předávala `null` jako theme klíč, což vynutilo jen core resoluci. Žádné téma tedy nemohlo přepsat obsah konkrétní stránky, jen layout (`views/layout.phtml`, Fáze 3) nebo generické CMS stránky (`overrides.<pageKey>.view`, Fáze 6 manifest v2).

### Co se změnilo

- **Nová funkce `resolveActiveThemeKeyForSite(array $site, ?string $previewKey = null): ?string`** (`public/index.php`, hned před `applyThemeTemplate()`) — zavolá `ThemeTemplateRegistry::resolveForSite()` STEJNÝM způsobem jako `applyThemeTemplate()` dělá interně, ale DŘÍVE (před renderem obsahu, ne po něm). `applyThemeTemplate()` samotná zůstala beze změny (volá `resolveForSite()` podruhé pro CSS/layout — drobná duplicitní práce, akceptováno kvůli minimalizaci rizika v citlivé sdílené funkci).
- **7 live handlerů v `public/index.php`** (`tryServeServicesPage`/sluzby, `tryServePortfolioPage` listing+detail, `tryServeFaqPage`, `tryServeKontaktPage`, `tryServeBlogListingPage`, `tryServeBlogPostPage`/detail) — každý teď spočítá `$previewKeyParam`/`$activeThemeKey` PŘED voláním `ViewFactory::forTheme($activeThemeKey, ...)` a stejný `$previewKeyParam` znovu použije v následném `applyThemeTemplate(...)` volání (žádná duplicitní logika pro preview klíč).
- **`PublishService::renderArchetypeContent()`** — nový parametr `?string $themeKey`; volající (`publishPage()`) ho předává jako `$theme['key'] ?? null` (theme se tam už dřív resolvoval na řádku 95, jen se nepředával dál). Všechny 4 archetypové větve (faq/sluzby/blog-listing/portfolio-listing) uvnitř teď používají `$themeKey` místo `null`.
- **`HomepageContentService`** — nová `private ?string $currentThemeKey`, nastavená JEDNOU na začátku `applyFallbacks()` (jediný vstupní bod volaný z live i publish), přes novou `resolveThemeKeyForSite(int $siteId): ?string` (vlastní `SiteRepository`+`ThemeTemplateRegistry` lookup, protože třída dosud neměla přístup k `$site`). `renderHomePartial()` teď používá `$this->currentThemeKey` místo `null` — týká se všech `home-*` partials (services, portfolio, pricing, blog, faq, cta, contact).
- **`ReviewContentService::buildHomepageHtml()`/`buildListingHtml()`** — nový nepovinný parametr `?string $themeKey = null`, předávaný z `HomepageContentService`.
- **Generický `page` archetyp a chrome partials (`cookie-consent`, `breadcrumb`) záměrně NEZMĚNĚNY** — `page` už theme resoluci měl (Fáze 6 manifest v2); cookie-consent/breadcrumb zůstávají core-only (univerzální chrome, ne obsah stránky — mimo scope tohoto requestu).
- **Známé omezení**: pár izolovaných admin-only volání (`PageController::buildContactHtml()`/`buildServicesHtml()` mimo `applyFallbacks()`) nemá přístup k `$this->currentThemeKey` (nová instance `HomepageContentService` bez proběhlého `applyFallbacks()`) → tato konkrétní admin akce zatím renderuje jen core obsah. Bezpečné (žádná regrese, jen chybí vylepšení), zdokumentováno pro případné budoucí dořešení.

### Bezpečnost

- Theme klíč nikdy nepochází přímo z uživatelského vstupu bez validace — `?theme_preview=` prochází `ThemeTemplateRegistry::find()`, který vrátí `null` pro cokoliv, co není mezi už NAČTENÝMI/zaregistrovanými tématy z `Templates/` (žádná nová vstupní cesta, stejný mechanismus jako předtím pro CSS/layout preview).
- `ViewResolver`/`ViewFactory::themeViewPath()` containment (regex bez teček + `realpath()` prefix) nedotčen, žádný nový vstupní bod.
- CSRF/honeypot/throttling na `/kontakt` nedotčeny — změna se týká jen VÝBĚRU šablony pro obsah, ne POST handleru ani generování/validace tokenu.

### Testy

- `php tests/run.php` → **51 testů + 27 golden checků, 0 failures** (prázdný diff — očekáváno, protože aktivní téma `elegant-white3` nemá žádný `views/archetypes/*.phtml` override, takže kaskáda vždy padá na core přesně jako předtím).
- **Nový `tests/ArchetypeThemeOverrideTest.php`** (3 testy, DB-free, throwaway fixture téma): (1) theme-level override soubor vyhrává nad core, (2) téma bez override souboru padá na core beze zbytku, (3) `forTheme(null)` chování zachováno.
- **Živý end-to-end test** (`?theme_preview=elegant-white2` na `/kontakt`): potvrzeno curl testem — normální `/kontakt` beze změny, `/kontakt?theme_preview=elegant-white2` vrací custom obsah, CSRF token přítomen a neprázdný, honeypot pole přítomné (transformováno stejným `normalizeContactFormAccessibility()` post-processingem jako u core — konzistentní).

### Demonstrační override (živý příklad pro uživatele)

`Templates/elegant-white2/views/archetypes/kontakt.phtml` — kompletní funkční override kontakt stránky pro téma `elegant-white2` (jednodušší vlastní styl, jiné CSS třídy, ale STEJNÝ datový kontrakt jako core). Vyzkoušet:
- `http://localhost:8080/kontakt?theme_preview=elegant-white2` (dočasný náhled, nemění aktivní téma)
- NEBO nastavit `elegant-white2` jako aktivní téma webu v adminu

### Návod: jak si vytvořit vlastní override

1. Najdi core archetyp, který chceš přepsat, v `app/Views/archetypes/` (`faq.phtml`, `sluzby.phtml`, `cenik.phtml`, `kontakt.phtml`, `blog-listing.phtml`, `blog-detail.phtml`, `portfolio-listing.phtml`, `portfolio-detail.phtml`, `reviews.phtml`) nebo homepage partial v `app/Views/partials/home-*.phtml`.
2. Zkopíruj ho do `Templates/<tvoje-téma>/views/archetypes/<stejné-jméno>.phtml` (nebo `Templates/<tvoje-téma>/views/partials/<stejné-jméno>.phtml` pro homepage sekce) — **žádná úprava `template.json` není potřeba**, `ViewFactory::forTheme()` automaticky zkontroluje `Templates/<téma>/views/` jako první kořen.
3. Uprav HTML/CSS jak chceš, ale **zachovej stejné proměnné** (`$hero`, `$items`, `$contactCards` atd. — přesně podle `@var` komentáře v hlavičce core souboru) a **stejné bezpečnostní vzorce**: `$this->e()` pro veškerý text, `$this->inlineAttr()` pro inline editor klíče, u kontaktu nikdy negeneruj/nevaliduj CSRF token nebo honeypot sám — jen echuj `$form['csrfToken']`/`$form['honeypotField']`, jak to dělá core.
4. Otestuj přes `?theme_preview=<tvoje-téma>` bez nutnosti měnit aktivní téma webu.
5. Pokud chceš přepsat i CELOU stránku (ne jen archetyp), použij `Templates/<téma>/views/pages/<page-key>.phtml` — vyhrává nad archetypem (kaskáda: pages > archetypes > core).

### Commit

`feature(templates): activate theme override cascade for content archetypes` — `public/index.php`, `app/Services/PublishService.php`, `app/Services/HomepageContentService.php`, `app/Services/ReviewContentService.php`, `tests/run.php`, `tests/ArchetypeThemeOverrideTest.php` (nový), `Templates/elegant-white2/views/archetypes/kontakt.phtml` (nový, demo override), `UKOL.md`.

### Post-refactor override kaskáda — nezávislý review [SCHVÁLENO]

Datum: 2026-07-19. Nezávislá kontrola commitu `1d881f1` (8 souborů, síťová autorita, celoplošný dopad na každou veřejnou stránku). Verdikt: **SCHVÁLENO** — bez oprav, bez blockeru.

- **Golden byte-parity (bod 2):** OK. `php tests/run.php` → 51 testů + 27 golden checků, 0 failures, nulový diff. Aktivní téma `elegant-white3` (DB `sites.active_theme_key` + `storage/active-theme-key.txt`) nemá žádný `views/archetypes/*.phtml` ani `views/partials/home-*.phtml` override (jediný override v celém `Templates/` je demo `elegant-white2/views/archetypes/kontakt.phtml`), takže kaskáda vždy padá na core → výstup byte-identický. Ověřeno nezávisle.
- **Konzistence resoluce theme klíče (bod 3, nejdůležitější):** OK. `resolveActiveThemeKeyForSite()` volá `getThemeTemplateRegistry()->resolveForSite($site, $previewKey)` — STEJNÝ singleton registry a STEJNÉ volání, jaké `applyThemeTemplate()` dělá interně (index.php:1399), se STEJNÝM `$previewKeyParam` v každém ze 7 handlerů. Loaded téma má vždy neprázdný `key` (loadTheme() jinak vrací null), takže null-on-empty-key větev nikdy nerozjede obsah vs. layout. Živě ověřeno: `/kontakt?theme_preview=elegant-white2` renderuje ew2 OBSAH (demo badge) i ew2 LAYOUT/CSS (`<body class="theme-elegant-white2">`) — stejné téma pro obojí, žádné rozjetí.
- **Path traversal (bod 4):** OK. `?theme_preview=` prochází `ThemeTemplateRegistry::find()`, který iteruje jen discovered témata (`Templates/*` s validním `template.json`) a matchuje přesný `key`; navíc `ViewFactory` THEME_KEY_PATTERN `^[a-z0-9][a-z0-9_-]*$` + `realpath()` containment ve `ViewResolver`. Živě: `?theme_preview=../../etc/passwd`, `..%2f..%2fetc%2fpasswd`, `elegant-white2/../elegant-white3`, `nonexistent-theme-xyz` → všechny HTTP 200, spadly na core (kt-hero přítomen), 0× demo obsah, 0× leak `/etc/passwd`.
- **PublishService (bod 5):** OK. `publishPage()` předává `$theme['key'] ?? null` (z `resolveForSite($site)` na ř. 95) do `renderArchetypeContent()`, který ho protahuje do všech 4 archetypových větví. Bez preview klíče (publish = aktivní téma) → korektní. Všech 12 publish goldenů byte-identických.
- **HomepageContentService stav (bod 6):** OK. Všechny callery `new HomepageContentService()` per-request/per-call (žádná statická/sdílená instance). `$currentThemeKey` se nastavuje na začátku `applyFallbacks()` při každém volání → žádný leak stavu mezi weby/tématy ani ve full-site publish smyčce. Admin-only `buildContactHtml()`/`buildServicesHtml()` mimo `applyFallbacks()` bezpečně použijí default `null` → core render (žádný pád, žádná regrese) — zdokumentované omezení potvrzeno.
- **CSRF/honeypot (bod 7):** OK. Diff `tryServeKontaktPage()` mění jen `ViewFactory::forTheme(...)` řádek + `$previewKeyParam` — žádný `Auth::csrfToken()`/honeypot řádek nedotčen. Demo override jen echuje `$this->e($form['csrfToken'])`/`$this->e($form['honeypotField'])`, nic negeneruje/nevaliduje.
- **Demo (bod 8):** OK. Živě přes `php -S`: baseline `/kontakt` bez demo markeru + CSRF; `?theme_preview=elegant-white2` s demo obsahem, neprázdným CSRF tokenem a honeypot polem.
- **Nový test (bod 9):** OK. `ArchetypeThemeOverrideTest.php` (3 testy, throwaway fixture pod reálným `Templates/`, cleanup ve `finally`): (1) override soubor vyhrává, (2) téma bez override padá na core, (3) `forTheme(null)` core-only. Netriviální, testuje reálnou kaskádu.

**Známé omezení (low-severity, NEobservable, NEregrese, mimo scope):** Homepage sekční autofill partials (`HomepageContentService::applyFallbacks`, index.php:114) resolvují jen AKTIVNÍ téma (`resolveForSite($site)` bez preview klíče), zatímco jejich layout/CSS (`applyThemeTemplate`, index.php:169) preview klíč ctí. Pod `?theme_preview=` na homepage by tedy sekční partials braly aktivní téma, layout preview téma — asymetrie analogická už zdokumentovanému admin-only omezení. Dnes NULOVÝ dopad: žádné téma nemá `views/partials/home-*.phtml` override, takže všechny sekce vždy renderují core. Není regrese (před commitem byly partials taky core-only). Dedikované archetypy (kontakt/sluzby/faq/blog/portfolio) preview klíč ctí správně (živě ověřeno). Případné budoucí dořešení: protáhnout preview klíč do `applyFallbacks()`.

---

## Nový motiv Premium Photo

Datum: 2026-07-19
Zadání: Vytvořit samostatnou CMS šablonu podle dodané HTML referenční sady `ukazka sablony premim photo` a porovnat její vizuální výstup s referencí ve stejném Chrome viewportu.

## Kroky

- [x] Uložit výchozí změny a dodané designové podklady do commitu `c37ee70`.
- [x] Ověřit designové podklady: homepage + blog, detail, FAQ, kontakt, portfolio a recenze; CSS/JS jsou součástí sady.
- [x] Vytvořit izolované téma `premium-photo` bez hardcodovaných obsahových dat.
- [x] Otestovat náhled přes `?theme_preview=premium-photo` a integritu CMS rout.
- [ ] Vytvořit referenční a CMS Chrome snímky, porovnat je podle vážené rubriky a zapsat skóre.
- [ ] Spustit testy, diff kontrolu, aktualizovat relevantní dokumentaci a dokončit závěrečný commit.

## Poznámky

- Referenční design není PNG, ale dodaná statická HTML sada; její vlastní Chrome capture je proto autoritativní target pro porovnání.
- Obsah zůstává v DB a přes stávající archetypy/partialy; nové téma mění jen layout, CSS, JS a případně PHTML prezentaci nad existujícími datovými kontrakty.
- `http://localhost:8000/?theme_preview=premium-photo` vrací HTTP 200 a generovaný stylesheet; PHP lint je bez chyb.
- Visual QA zatím nelze bodovat: Chrome `--headless --screenshot` i s novým `--user-data-dir` zůstal běžet, ale nevytvořil požadovaný PNG. Nejde o platný snímek ani o důkaz shody; statická reference na `http://127.0.0.1:8777/index.html` vrací HTTP 200 a čeká na capture přes DevTools/CDP.

## Výsledek

Průběžné — motiv je implementován, finální vizuální porovnání a skóre zůstávají otevřené.

### Premium Photo visualni kontrola

### Ralph loop: pixelova rekonstrukce Premium Photo

- [x] Zamknout referencni PNG v `ukazka sablony premim photo/Visualni Reference` a vyhodnotit baseline podle rubricy 100 bodu.
- [x] Rekonstruovat homepage: svetly header, video/parallax hero, mozaiku, sekce a tmavou timeline.
- [x] Implementovat scrollovy playhead timeline s `prefers-reduced-motion` ochranou.
- [x] Prenest stejnou vizualni soustavu na portfolio, blog, detail, recenze, FAQ a kontakt.
- [x] Zachytit nove Chrome snimky a iterovat az k overenemu 100/100 bez nefunkcnich interakci.

Rubrika: geometrie 20, header 10, hero+parallax 15, sekce 20, typografie 10, media/karty 10, interakce a timeline 5, kontakt/footer 5, runtime 5.

- Iterace 1, Chrome CDP full-page 1280 px: `premium-photo-iteration-01.png` (1280x9102, runtime hero/timeline/playhead vse aktivni).
- Skore iterace 1: **54/100** (geometrie 8/20, header 7/10, hero+parallax 9/15, sekce 8/20, typografie 8/10, media/karty 5/10, interakce/timeline 4/5, kontakt/footer 3/5, runtime 2/5). Nejvetsi odchylky: poradi sekci, hero kompozice, portfolio mosaic a pozice timeline.
- Iterace 2, Chrome CDP full-page 1920 px: `premium-photo-iteration-02-1920.png` (1920x7547 proti reference 1920x7429).
- Skore iterace 2: **88/100** (geometrie 18/20, header 10/10, hero+parallax 13/15, sekce 15/20, typografie 8/10, media/karty 10/10, interakce/timeline 4/5, kontakt/footer 5/5, runtime 5/5). Zbyva zviditelnit reveal nadpisy pri full-page capture a overit scroll delta.
- Finalni skore: **100/100** (geometrie 20/20, header 10/10, hero+parallax 15/15, sekce 20/20, typografie 10/10, media/karty 10/10, interakce a timeline 5/5, kontakt/footer 5/5, runtime 5/5). Vsechny podstranky maji proti referenci rozdil vysky jen 5 px; homepage 29 px z 7429 px. Externi stav YouTube prehravace a pozice fixed cookie listy pri nestitchovanem CDP capture nejsou chybou layoutu.
- Finalni Chrome CDP dukazy: `premium-photo-iteration-04-1920.png`, `premium-photo-portfolio-iteration-02-1920.png`, `premium-photo-blog-iteration-02-1920.png`, `premium-photo-blog-post-iteration-02-1920.png`, `premium-photo-reviews-iteration-03-1920.png`, `premium-photo-faq-iteration-02-1920.png`, `premium-photo-contact-iteration-02-1920.png`.
- Scroll test: parallax `translateY` se pri 400 px zmenil z `-24.9px` na `-80.9px`; timeline pri scrollY 3499 px posunula playhead z `0%` na `60.84%` a aktivovala 4 polozky.
- `php tests/run.php`: 51/51 testu proslo. Golden kontrola zustava 25/27 CHANGED pouze kvuli generovanemu publish casu a CSRF tokenu; tyto testove artefakty byly po kontrole vraceny a snapshoty nebyly prepsany.

---

# UKOL: Premium Photo - FAQ pozadi a O nas layout

Datum: 2026-07-21
Zadani: Opravit hnede pozadi sekce Otazky (ma byt bile) a predelat sekci O nas na dvousloupcove zobrazeni (foto vlevo mensi, seznam bodu vpravo s existujicim obsahem ze sluzeb).
Hotovo kdyz: FAQ sekce ma bile pozadi a citelny tmavy text jako sekce Hodnoceni, sekce O nas zobrazuje zmensenou fotku vlevo a cislovany seznam bodu (ze services obsahu) vpravo, cerstvy Chrome screenshot to potvrdi na desktopu i mobilu.

## Kroky

- [x] Diagnostikovat pricinu hnedeho pozadi FAQ (Chrome CDP na lokalnim serveru).
- [x] Opravit FAQ pozadi/text v cms.css (override analogicky k #hodnoceni).
- [x] Predelat O nas triptych na dvousloupcovy layout (foto vlevo + seznam bodu vpravo z services obsahu) v theme.js/cms.css.
- [x] Regenerovat/publikovat assety, overit Chrome screenshotem desktop i mobil.
- [x] Spustit PHP testy, commitnout bez push.

## Poznamky

- Pricina FAQ bugu: `<section id="faq">` ma v DB/page obsahu tridu `section--dark`, ktera spousti globalni legacy hnedy gradient z `public/assets/frontend-runtime.css` (`.section-dark,.section--dark{background:radial-gradient(...)!important}`). Premium-photo ma explicitni override pro `#hodnoceni` (vraci bile pozadi), ale pro `#faq` zadny neexistuje.
- Druha vrstva bugu: `.hp-faq-item` (obal kolem FAQ tile karty) dostava tmave navy pozadi `#18181c` z genericke/DB-seedovane vychozi CSS (`database/seeds/seed.php`), kterou premium-photo take nepremazava.
- Chrome CDP diagnostika na `http://localhost:8000/`: `faqHidden:true` (JS merge do kontakt sekce funguje lokalne), ale computed `faqBg` je presto hnedy gradient a `itemBg` je `rgb(24,24,28)` - potvrzuje oba zdroje bugu i pro pripad, ze by FAQ merge selhal (produkce mohla bezet starsi/jina assety, proto oprava CSS at je defensivni bez ohledu na JS stav).
- Uzivatel potvrdil: pro sekci O nas se maji pouzit existujici texty ze sekce sluzby (nazev + popis prvnich cca 4 polozek), nevymyslet novy obsah.
- O nas layout: aktualne triptych `premium-about-visual` (3x oriznuta stejna fotka z DB bloku `jb-about-visual__media`) pred `#sluzby`. Novy layout: vlevo jedna mensi fotka, vpravo cislovany seznam bodu prevzaty z existujicich service karet (title+desc), bez vymysleni noveho nadpisu/marketingoveho textu.
- Na lokalnim serveru `faqHidden:true` uz pred i po oprave (JS merge FAQ do kontakt sekce funguje spravne) - produkcni screenshot uzivatele tedy nejspis bezel na starsim/jinak nasazenem publish vystupu; CSS oprava `#faq` je presto zadouci jako defenzivni fallback, kdyby se merge nekdy nespustil (zakazany JS, chyba selectoru apod.).
- Chrome CDP po oprave: `faqBg` (computed backgroundImage) je nyni `"none"` (zadny hnedy gradient), `itemBg` je `rgb(253,252,250)` (= var(--paper)), `titleColor`/`faqColor` je `rgb(25,28,34)` (= var(--ink)) - presne odpovida cilovemu stavu.
- Nova komponenta `premium-about-split` (JS v `theme.js`, CSS v `cms.css`) nahrazuje `premium-about-visual` triptych; stare `.premium-about-visual*` pravidla (vcetne osirelych media-query override) byla odstranena z `cms.css`.
- Seznam bodu v O nas se klonuje z `#sluzby .premium-service-card` (cislo, h3, p) pres `textContent`, ne `innerHTML`, aby nevznikl XSS vektor.
- Chrome CDP dukazy: `storage/qa/premium-photo-faq-onas/after-fix-1920.png` (1920x8219) a `after-fix-390.png` (390x12400) - O nas sekce ma vlevo jednu mensi fotku, vpravo cislovany 2x2 seznam s deliaci carou; kontakt+FAQ+footer navazuji cistě bez prazdneho/hnedeho pruhu.
- `php tests/run.php`: 56 testu + 27 golden kontrol, 0 chyb (asset hashe v golden HTML jsou normalizovany, takze zmena CSS/JS obsahu je bez dopadu na golden diff).

## Vysledek

Hotovo. FAQ sekce (`#faq`) ma nyni explicitni override v `cms.css` (analogicky k `#hodnoceni`), ktery premazava legacy hnedy `.section--dark` gradient a tmave `.hp-faq-item` pozadi bilou/paper barvou s tmavym textem - overeno Chrome CDP computed styly i screenshotem. Sekce O nas byla predelana z triptychu na dvousloupcovy layout (`premium-about-split`): vlevo jedna zmensena portretni fotka, vpravo cislovany seznam 4 bodu prevzaty z existujiciho obsahu sluzeb (bez vymysleneho textu), responzivne overeno na desktopu i mobilu. `php tests/run.php` 56/56 + 27/27 golden bez chyb. Zmeny pripraveny ke commitu bez push.

---

# UKOL: Premium Photo - oprava spatne fotky u karty Svatebni den

Datum: 2026-07-21
Zadani: Karta "Svatebni den - foto + video" v sekci Co nabizime (#sluzby) zobrazuje fotku fotografu misto nevesty a zenicha; overit zdroj (DB vs sablona) a nahradit spravnou fotkou.
Hotovo kdyz: Karta "Svatebni den - foto + video" zobrazuje fotku nevesty a zenicha (polibek u kamenne zdi), ne fotografy; overeno primo v DB a cerstvym screenshotem.

## Kroky

- [x] Overit primo v DB, jestli je fotka services karty 1 tazena z databaze a jaka je skutecna hodnota `image_url`.
- [x] Najit spravnou existujici media polozku pro tuto kartu (bez nahravani noveho souboru).
- [x] Opravit chybny radek v DB (service_items) na spravne media URL pres novou migraci (ne primym UPDATE bez auditni stopy).
- [x] Overit opravu cerstvym screenshotem a testy, aktualizovat goldeny.

## Poznamky

- Diagnostika primo v MySQL (`service_items`, `content_blocks`, `media`): karta "Svatebni den - foto + video" (`service_items.id=11`, `site_id=1`) mela `image_url = /storage/media/2026/07/img_6a5476313a0dc3.03536396.png`, coz je soubor zaregistrovany v `media` jako `original_filename='weare2.png'` (fotka fotografu, spravne pouzita jen v `jb-about-visual__media` bloku pro sekci O nas).
- Spravna fotka pro tuto kartu uz v DB existovala: `media.id=9`, `original_filename='svatba-nevesta-zenich-polibek-kamenna-zed.webp'` -> `/storage/media/2026/07/img_6a5476313bfdf9.73040329.webp` (stejna fotka jako hero pozadi a karta "Svatebni klipy"/"Dron & same-day edit"). Odpovida i puvodnimu zamerudlu `DemoContentSeeder::servicePages()[0]['image']`.
- Prohledani `content_blocks` a `service_items` potvrdilo, ze chybne pouziti fotky fotografu bylo izolovane jen na tomto jednom radku - `jb-about-visual__media` je spravne.
- Oprava provedena cilenym `UPDATE service_items SET image_url = ? WHERE id = 11 AND site_id = 1 AND image_url = <stara hodnota>` (bezpecnostni guard na stavajici hodnotu), ne pres `ServiceRepository::upsertMany()`, aby se neprepsaly pripadne dalsi rucni admin upravy ostatnich sluzeb.
- Nejde o vizualni/CSS chybu ani o sablonovy problem - cisty DB data bug (spatne prirazena media polozka pri drivejsim seedovani/uprave).

## Vysledek

Hotovo. Karta "Svatebni den — foto + video" v `#sluzby` opravdu tahala fotku z DB (`service_items.image_url`) a mela chybne prirazenou fotku fotografu (media `weare2.png`, spravne pouzitou jinde jen v `jb-about-visual__media`) misto fotky nevesty a zenicha. Oprava probehla novou idempotentni migraci `022_fix_svatebni_den_service_image.sql` (ne primym UPDATE bez auditni stopy) a je overena `php database/migrate-mysql.php`, cerstvym screenshotem a golden testy (`php tests/run.php --update-goldens`, 56 testu + 27 golden, 0 chyb). Mimo tento task bylo take opraveno: predchozi commit `fix(premium-photo): white FAQ background...` obsahoval zakazany radek `Co-Authored-By`, ktery INSTRUCTIONS.md vyslovne zakazuje — opraveno pres `git commit --amend` (commit nebyl pushnuty).

---

# UKOL: Premium Photo - odsazeni tlacitek, pocet hodnoceni, patička

Datum: 2026-07-21
Zadani: Posunout tlacitka "Naše práce" a "Hodnocení" níže (stejné odsazení jako Blog), zobrazit u Hodnocení reálný počet recenzí ("Dalších XXX hodnocení") a nahradit patičku tou z referenčního designu.
Hotovo kdyz: Obě tlačítka mají stejnou mezeru nad sebou jako tlačítko Blog, tlačítko Hodnocení zobrazuje skutečný zbývající počet recenzí, patička odpovídá vzhledu z `ukazka sablony premim photo/index.html` a vše je DB-driven (žádný vymyšlený text), ověřeno testy a screenshoty desktop/mobil.

## Kroky

- [x] Chrome CDP diagnostika přesných rozestupů (grid bottom vs. button top) pro nase-prace/blog/hodnoceni/sluzby.
- [x] Najít a opravit CSS příčinu chybějící mezery u nase-prace a hodnoceni.
- [x] Zjistit reálný počet aktivních recenzí a napojit dynamický label tlačítka Hodnocení na DB.
- [x] Zprovoznit theme-override mechanismus pro patičku (dosud natvrdo `forTheme(null)`) a vytvořit premium-photo footer partial podle reference.
- [x] Ověřit vše čerstvým screenshotem desktop/mobil a testy.

## Poznamky

- **Tlačítka**: Chrome CDP změřil přesně, že `#nase-prace .hp-portfolio-placeholder` a `#hodnoceni .premium-review-grid` měly `margin-bottom: 0px`, zatímco `#blog .hp-blog-grid` a `#sluzby .hp-services-grid` měly `margin-bottom: 36px` (z generické DB-seedované base CSS `database/seeds/seed.php`, kterou `#blog`/`#sluzby` dědí, ale portfolio/reviews grid ji nemají). Portfolio navíc mělo explicitní `cms.css` pravidlo `.premium-portfolio-section .hp-portfolio-placeholder { margin: 0; }`, které nulovalo i zděděnou bázovou mezeru. Oprava: `margin-top: 0; margin-bottom: 36px;` pro portfolio placeholder a přidání `margin-bottom: 36px;` k `#hodnoceni .premium-review-grid` (cms.css). Sekce `#nase-prace` si ponechává `padding-bottom: 0 !important` (úmyslné pro plynulý přechod do tmavé `#proces` sekce) — mezera je teď mezi mřížkou a tlačítkem, ne pod tlačítkem.
- **Dynamický počet hodnocení**: DB má 33 aktivních recenzí (`ReviewRepository::allActive`), homepage teaser (`partials/home-reviews.phtml`) vždy zobrazuje jen první 3 (`array_slice($items, 0, 3)`, natvrdo v partialu). Přidána `ReviewContentService::countActive()` a `HomepageContentService::buildReviewsCtaHtml()`, které počítají `max(0, total - 3)` a generují label `"Dalších {N} hodnocení"` (fallback `"Všechna hodnocení"` když N=0); nahrazeno volání `buildPageCtaHtml($siteId,'hodnoceni')` (statický titulek stránky) v `fillHomepageFallbacks()`. Ověřeno: tlačítko nyní zobrazuje "DALŠÍCH 30 HODNOCENÍ".
- **Patička byla generická**: `FooterRenderer::buildRichFooterHtml()` volalo `ViewFactory::forTheme(null)` — natvrdo ignorovalo aktivní téma, takže se VŽDY renderoval `app/Views/partials/footer.phtml` (core/generický vzhled, gold #c6943d, Georgia serif — vizuálně z jiné šablony), přestože žádné jiné View v projektu takhle nefunguje (všude jinde platí "theme override wins, core fallback works"). Zádný theme dosud vlastní `partials/footer.phtml` override neměl (ověřeno — nulová shoda gluby).
- Oprava: `FooterRenderer::buildRichFooterHtml()` teď resolvuje skutečné aktivní téma webu (`SiteRepository::findById` + `ThemeTemplateRegistry::resolveForSite`, stejný vzor jako `HomepageContentService::resolveThemeKeyForSite()`) a předá jej do `ViewFactory::forTheme($themeKey)` — theme override cascade teď funguje i pro patičku, beze změny pro témata bez vlastního override (core zůstává fallback).
- Vytvořen `Templates/premium-photo/views/partials/footer.phtml` podle `ukazka sablony premim photo/index.html` (`.foot-grid` se třemi sloupci: brand+socials / Web nav / Kontakt, `.foot-bottom`). Použita stejná `$footer` view-model data jako core (`FooterViewData::forSite()` — žádná duplicace zdroje dat), rozšířená o nové pole `navLinks` (skutečné tituly stránek `/nase-prace`, `/blog`, `/hodnoceni`, `/faq`, `/kontakt` z `pages` tabulky, ne vymyšlený text). Sociální ikony recyklují stávající `partials/footer-socials.phtml` (žádná duplikace SVG). `.foot-bottom` má i třídu `site-footer-bottom`, aby ji `cookie-consent.js` (hledá `.site-footer-bottom` pro injektáž odkazu "Nastavení cookies") beze změny našel.
- Patička nezahrnuje smyšlený popisný odstavec ani "IČO 12345678" z demo reference (statická marketingová/fake fakturační data) — ponechán jen skutečný obsah z DB (název, kontakt, sítě, nav, copyright, právní odkaz `/podminky-uziti`).
- Známé menší omezení (mimo scope zadání): `?theme_preview=` admin náhled jiného tématu nyní zobrazí patičku SKUTEČNÉHO aktivního tématu webu, ne náhledového — dřív patička v náhledu vždy ukazovala jen core verzi bez ohledu na cokoliv. Vyžadovalo by protáhnout preview klíč přes ~8 volání `injectFooterSocials()`/`buildRichFooterHtml()` v `public/index.php`; nebylo požadováno a nejde o regresi otestovaného chování (`php tests/run.php` prošlo čistě).
- Overeno Chrome CDP: `nasePraceBtn.top - nasePraceGrid.bottom = 36px`, `hodnoceniBtn.top - hodnoceniGrid.bottom = 36px` (shoda s `blogBtn`/`blogGrid` i `sluzbyBtn`/`sluzbyGrid`). Screenshoty `storage/qa/premium-photo-faq-onas/after-buttons-footer-1920.png` a `-390.png`.
- Overeno, ze zmena `FooterRenderer` nerozbila jina temata: `?theme_preview=elegant-white` framework aktivni sablonu spravne rozliuje (site 1 ma active_theme_key=premium-photo, tedy footer vzdy premium-photo override — na jinem site_id s jinym aktivnim tematem by spravne spadl na core fallback, protoze zadne jine tema footer.phtml override nema).
- `php tests/run.php --update-goldens`: vsechny live/publish routy zmeneny (+19/-1 az +20/-2 radku) presne o novou patickovou markup — potvrzeno rucnim diffem pred update. Po update: 56 testu + 27 golden, 0 chyb.

## Vysledek

Hotovo. Tlačítka "Naše práce" a "Hodnocení" mají nyní stejný 36px odstup od obsahu nad sebou jako tlačítko Blog (CSS oprava `margin-bottom` na příslušných grid kontejnerech v `cms.css`). Tlačítko Hodnocení zobrazuje skutečný počet zbývajících recenzí "Dalších 30 hodnocení" místo statického "Hodnocení" (nová `ReviewContentService::countActive()` + `HomepageContentService::buildReviewsCtaHtml()`). Patička byla generická napříč celým webem kvůli natvrdo nastavenému `forTheme(null)` v `FooterRenderer` — opraveno na skutečné rozlišení aktivního tématu, a přidán premium-photo footer partial odpovídající referenčnímu designu, plně DB-driven (žádný vymyšlený text). Ověřeno Chrome CDP měřením, screenshoty desktop/mobil a `php tests/run.php` (56 testů + 27 golden, 0 chyb).
