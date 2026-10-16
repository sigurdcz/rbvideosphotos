# Frontend performance contract

Aktualizováno: 2026-08-11

## Cílové hodnoty

- Medián tří mobilních Lighthouse běhů: Performance alespoň 90, LCP pod 2,5 s, TBT pod 200 ms a CLS pod 0,1.
- Počáteční přenos homepage bez spuštěného videa: nejvýše 1,5 MiB.
- Počáteční HTML: žádný YouTube/Vimeo iframe a právě jeden `fetchpriority="high"` na skutečném explicitním LCP kandidátu.
- Accessibility alespoň 95 a SEO 100.

## Trvalé implementační zásady

- Obrázky se nahrávají pouze přes `MediaIngestionService`. Ten vytváří WebP varianty `thumb` 50 KiB, `small` 90 KiB, `medium` 72 KiB a `large` 240 KiB. Nesplnitelný limit je chyba, ne důvod k použití velkého originálu.
- Výchozí hero poster v seedech i produkčních datech používá `medium` variantu. Migrace jsou append-only; produkční oprava je v migraci `053_use_medium_variant_for_default_hero_poster.sql`.
- Existující týmová LCP fotografie se před publishem jednorázově přegeneruje přes application-compatible marker `054_optimize_homepage_lcp_media.sql`; krok nesahá na ostatní média ani historické migrace.
- `ResponsiveImageMarkupService` je společný pro live render i statický publish. Doplňuje `srcset`, `sizes`, rozměry a lazy-load a přiděluje jednu vysokou prioritu explicitnímu `data-lcp-candidate`; teprve bez něj použije hero obrázek nebo logo.
- Hero video používá poster facade. Externí iframe vznikne až po kliknutí a marketingovém souhlasu; YouTube používá `youtube-nocookie.com`.
- Tracking používá právě jeden primární loader. Přímé GA4 má přednost před GTM; primární loader čeká na první pointer/keyboard/scroll interakci nebo nejvýše 8 sekund, zatímco explicitní změna consentu jej spustí ihned. Clarity čeká na první interakci a analytický souhlas. Stav je dostupný v `window.cmsTrackingRuntime`.
- `/media/*` se obsluhuje před startem PHP session a má roční immutable cache, ETag a podporu 304. Obsahově hashované `/assets/generated/*` mají stejný cache kontrakt. Nehashované CSS/JS se revalidují.
- Material Symbols webfont se nesmí vrátit. Golden Bash používá lokální systémovou mapu; externí textové fonty zůstávají neblokující a omezené na používané řezy.
- Golden Bash hero musí mít finální SEO H1 i vizuální H2 už v serverovém HTML. JavaScript nesmí po prvním paintu měnit text H1 ani přidávat rozměrově významný nadpis.
- Externí Golden Bash fonty používají `display=optional`, aby pomalé mobilní připojení nevyvolalo pozdní výměnu metrik a layout shift.
- Homepage náhledy označené `data-responsive-profile="homepage-card"` a `homepage-hero` smějí nabízet nejvýše 720px `medium` variantu; plný originál zůstává dostupný pouze pro detail/lightbox.

## Ověření před nasazením

1. Spustit `php tests/run.php`. Funkční testy musí mít nula chyb; známé golden rozdíly se nesmí automaticky přijmout.
2. Spustit `node --check` nad změněnými runtime a theme JavaScripty a `php -l` nad změněnými PHP/PHTML soubory.
3. Na lokální homepage ověřit: 0 počátečních iframe, právě 1 vysokou prioritu na `data-lcp-candidate`, přítomné `srcset` a robustní accessible name pro `shooting_type`.
4. Na `/media/*` ověřit `Cache-Control: public, max-age=31536000, immutable`, ETag, nulový `Set-Cookie`, HEAD a následný 304.
5. Po deployi spustit SEO smoke, tři mobilní a jeden desktopový Lighthouse běh. Aktuální hodnoty zapsat přímo do tohoto dokumentu; samostatný jednorázový baseline se již neudržuje.

První produkční iterace 2026-08-10: Performance 65, FCP 3,3 s, LCP 8,8 s, TBT 80 ms, CLS 0,002, Accessibility 95, Best Practices 100 a SEO 100. Druhá iterace opravila pozdní JavaScriptové vytvoření skutečného LCP obrázku, globální eager přepis a skrytý label selectu.

Baseline z reportu 2026-08-11 19:45:53: Performance 56, FCP 3,5 s, LCP 4,5 s, TBT 0 ms, CLS 0,479, Accessibility 97, Best Practices 100 a SEO 100. Následující oprava přesunula finální hero nadpis do serverového HTML, změnila font swap na optional, omezila homepage obrazové kandidáty na 720 px, odložila počáteční geometry read a opravila kontrast patičky. Nové produkční hodnoty je nutné měřit až po deployi a publishi.
