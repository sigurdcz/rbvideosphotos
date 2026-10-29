# Frontend performance contract

Aktualizováno: 2026-10-29

## Opravy nacitani homepage bez zmen videa (2026-10-29)

- Report 2026-10-28: mobilni Performance 88, LCP 3,4 s, TBT 10 ms, CLS 0. LCP byl tymovy obrazek v sekci O nas: 1520 ms prodleva pozadavku, 60 ms stazeni, 970 ms prodleva vykresleni. Jde o dodany produkcni report, nikoli nove mereni po oprave.
- `GoldenBashAboutRenderer` sestavi fotografii z DB do finalni figure a copy sloupce jiz na serveru. Pouziva se pro obe Golden Bash varianty v live i publish ceste. Zachovava obsah a odkazy z DB, edit mode ponechava puvodni editorovou strukturu. JS rozpozna `data-about-visual-ready="1"` a strukturu znovu nesklada; stary publikovany markup nadale podporuje puvodni JS cesta.
- Obrazek ma `data-eager-image`, takze neni lazy ani hidden. `data-mobile-lcp` vytvori v head vysokoprioritni preload omezeny na `(max-width: 700px)` se shodnymi `href`, `imagesrcset` a `imagesizes`. Desktop si ponechava prioritni hero obrazek; mobil ma navic umyslne prioritni pozadavek pro zmereny LCP, nejde o globalni prioritizaci vsech obrazku.
- Aktivni navigace pouziva geometrii z `IntersectionObserverEntry`, nikoli synchronni `getBoundingClientRect()` po theme DOM zmenach. Opakovany callback pro stejnou aktivni sekci nemeni tridy; hash a bezne routy zustavaji podporovane.
- `MarkupNormalizer::extractInlineBodyScripts` bali jen klasicky JavaScript. Galerijni `application/json`, strukturovana data, moduly a ostatni typovane skripty zustavaji na puvodnim miste. Puvodni bundlovani galerijnich poli do executable JS zpusobovalo `Unexpected token ']'` a odstranovalo data potrebna lightboxem. Regresni test overuje i mix dat s opravdovym inline JS.
- Video, autoplay, jeho zdroje a velikost jsou v teto zmene nedotcene. Nereseny prenos videa muze dal dominovat celkovemu vykonu. CSS ani fonty se neodkladaji a jejich chovani se nemeni.
- Overeni: `php tests/run-homepage-performance.php` (vcetne stavajicich video regresi), `node tests/active-nav-performance.test.cjs`, PHP lint a `node --check`. Lokalni geometrie fotografie i sekce pred/po je shodna pri 390 a 1280 px. Lokalni DB ma chybejici media a neplatny externi videoodkaz uz v baseline; nove Lighthouse cislo by nebylo s produkci srovnatelne. Skutecny prinos a CLS je nutne zmerit na stagingu se spravnymi medii, trikrat za stejnych podminek.

## Cílové hodnoty

- Medián tří mobilních Lighthouse běhů: Performance alespoň 90, LCP pod 2,5 s, TBT pod 200 ms a CLS pod 0,1.
- Počáteční přenos homepage bez spuštěného videa: nejvýše 1,5 MiB.
- Počáteční HTML: žádný YouTube/Vimeo iframe, právě jeden prioritní hero `<img>` a případný explicitní mobilní LCP preload omezený atributem `media`.
- Accessibility alespoň 95 a SEO 100.

## Trvalé implementační zásady

- Obrázky se nahrávají pouze přes `MediaIngestionService`. Ten vytváří WebP varianty `thumb` 50 KiB, `small` 90 KiB, `medium` 72 KiB a `large` 240 KiB. Nesplnitelný limit je chyba, ne důvod k použití velkého originálu.
- Výchozí hero poster v seedech i produkčních datech používá `medium` variantu. Migrace jsou append-only; produkční oprava je v migraci `053_use_medium_variant_for_default_hero_poster.sql`.
- Existující týmová LCP fotografie se před publishem jednorázově přegeneruje přes application-compatible marker `054_optimize_homepage_lcp_media.sql`; krok nesahá na ostatní média ani historické migrace.
- `ResponsiveImageMarkupService` je společný pro live render i statický publish. Doplňuje `srcset`, `sizes`, rozměry a lazy-load a přiděluje jednu vysokou prioritu explicitnímu `data-lcp-candidate`; teprve bez něj použije hero obrázek nebo logo. Explicitní `data-eager-image` navíc dovoluje eager načtení fotografie nad mobilním ohybem; `data-mobile-lcp` doplňuje odpovídající responzivní preload jen pro mobil.
- Hero video používá poster facade. Externí iframe vznikne až po kliknutí a marketingovém souhlasu; YouTube používá `youtube-nocookie.com`.
- Tracking používá právě jeden primární loader. Přímé GA4 má přednost před GTM; primární loader čeká na první pointer/keyboard/scroll interakci nebo nejvýše 8 sekund, zatímco explicitní změna consentu jej spustí ihned. Clarity čeká na první interakci a analytický souhlas. Stav je dostupný v `window.cmsTrackingRuntime`.
- `/media/*` se obsluhuje před startem PHP session a má roční immutable cache, ETag a podporu 304. Obsahově hashované `/assets/generated/*` mají stejný cache kontrakt. Nehashované CSS/JS se revalidují.
- Material Symbols webfont se nesmí vrátit. Golden Bash používá lokální systémovou mapu; externí textové fonty zůstávají neblokující a omezené na používané řezy.
- Golden Bash hero musí mít finální SEO H1 i vizuální H2 už v serverovém HTML. JavaScript nesmí po prvním paintu měnit text H1 ani přidávat rozměrově významný nadpis.
- Aktuální samohostované fonty v `public/assets/fonts/fonts.css` používají `font-display: swap` a preload obou částí rodiny pro češtinu. Starší záznamy o `optional` níže popisují historický stav; tato oprava chování fontů nemění.
- Homepage náhledy označené `data-responsive-profile="homepage-card"` a `homepage-hero` smějí nabízet nejvýše 720px `medium` variantu; plný originál zůstává dostupný pouze pro detail/lightbox.

## Ověření před nasazením

1. Spustit `php tests/run.php`. Funkční testy musí mít nula chyb; známé golden rozdíly se nesmí automaticky přijmout.
2. Spustit `node --check` nad změněnými runtime a theme JavaScripty a `php -l` nad změněnými PHP/PHTML soubory.
3. Na lokální homepage ověřit: 0 počátečních iframe, právě 1 prioritní hero `<img>`, případný mobilní preload pouze pro shodné obrazové kandidáty, přítomné `srcset` a robustní accessible name pro `shooting_type`. V obou Golden Bash variantách musí být fotografie O nás viditelná v sekci už v počátečním HTML bez JavaScriptu.
4. Na `/media/*` ověřit `Cache-Control: public, max-age=31536000, immutable`, ETag, nulový `Set-Cookie`, HEAD a následný 304.
5. Po deployi spustit SEO smoke, tři mobilní a jeden desktopový Lighthouse běh. Aktuální hodnoty zapsat přímo do tohoto dokumentu; samostatný jednorázový baseline se již neudržuje.

První produkční iterace 2026-08-10: Performance 65, FCP 3,3 s, LCP 8,8 s, TBT 80 ms, CLS 0,002, Accessibility 95, Best Practices 100 a SEO 100. Druhá iterace opravila pozdní JavaScriptové vytvoření skutečného LCP obrázku, globální eager přepis a skrytý label selectu.

Baseline z reportu 2026-08-11 19:45:53: Performance 56, FCP 3,5 s, LCP 4,5 s, TBT 0 ms, CLS 0,479, Accessibility 97, Best Practices 100 a SEO 100. Následující oprava přesunula finální hero nadpis do serverového HTML, změnila font swap na optional, omezila homepage obrazové kandidáty na 720 px, odložila počáteční geometry read a opravila kontrast patičky. Nové produkční hodnoty je nutné měřit až po deployi a publishi.
