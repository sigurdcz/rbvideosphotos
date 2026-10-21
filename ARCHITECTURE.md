# Architektura SEO Mini CMS

Tento dokument popisuje aktualni technicky stav projektu a deli odpovednosti mezi hlavni systemove casti. Pro setup a onboarding pouzij `README.md`. Pro API kontrakty pouzij `API.md`. Pro obsluhu GTM administrace pouzij `GTM_ADMIN.md`.

## Zamer projektu

SEO Editor je univerzalni CMS pro male prezencni weby s durazem na:

- editaci obsahu bez framework overheadu
- SEO vystupy a publish pipeline
- spravu blogu, portfolia a temat
- consent-aware tracking a GTM konfiguraci

## Stack

- PHP 8+
- MySQL / MariaDB
- PDO bez ORM
- Vanilla JS bez buildu
- Composer autoload pro namespace `App\`

## Moduly systemu

| Oblast | Odpovednost |
| --- | --- |
| `app/Core` | Router, auth, DB bootstrap, helpery |
| `app/Controllers` | Admin controllery, API dispatch, settings a GTM flow |
| `app/Repositories` | DB pristup a mapovani tabulek |
| `app/Services` | Render, publish, media, themes, tracking, GTM orchestrace |
| `app/Services/Render` | Sdilena render vrstva (PageComposer, section/hero/footer renderery, normalizery) pro live i publish |
| `app/View` | PHTML view engine: `ViewEngine`, `ViewResolver`, `ViewContext`, `ViewFactory` |
| `app/ViewModels` | Ciste datove providery pro archetypy (vraci skalary/pole, nikdy HTML) |
| `app/Views` | PHTML sablony: `layouts/`, `archetypes/`, `partials/`, `assets/` |
| `admin/` | Admin router, PHP view sablony a assety |
| `public/` | Verejny front controller, assety a tracking runtime |
| `Templates/` | Predpripravena vizualni temata |
| `database/` | Migrace, schema a seedy |
| `storage/` | Media, logy a publikovane vystupy |

## Kde co lezi (mapa pro rychlou orientaci)

Tabulka existuje proto, aby se pred kazdou zmenou nemuselo repo prohledavat znovu. Mereni
z 20. 9. 2026 ukazalo, ze samotne hledani v kodu bylo nejdrazsi polozkou prace (27,5 % objemu).
Kdyz se neco z teto mapy zmeni, uprav ji ve stejnem commitu.

| Ukol | Kde zacit |
| --- | --- |
| Poradi vypisu (prace, clanky) | `app/Repositories/PortfolioRepository.php` a `BlogRepository.php` - `allBySite()`, `allPublished()` |
| Michani fotek a videi na /nase-prace | `PortfolioRepository::interleaveByContentType()` - striktne podle cisla (`sort_order`), michani jen uvnitr jednoho cisla; klic `sort_order` dodava `app/ViewModels/PortfolioListingData.php` |
| Michani fotek a videi v "ochutnavce" na homepage | `PortfolioRepository::interleaveTypes()` volany z `HomepageContentService::buildPortfolioHtml()` - zipuje oba typy podle pomeru poctu a cisla mezi typy ignoruje |
| Hlavni obrazek (cover) prace | neni samostatny sloupec: listing, homepage ochutnavka, hero a OG detailu berou PRVNI `portfolio_media` (`sort_order, id`), tedy prvni fotku galerie z manifestu `PortfolioDemoContentImporter`. Migrace `095` dala omylem `weare5.jpg` do galerie `kamila-a-matej`; jeji handler je dnes no-op a stav vraci `098` (`revertMistakenWeare5Cover()`) |
| Poradi fotek v galerii | `setMedia()` / `setGallery()` (+ mapa cisel z formulare), cte `getMedia()` / `getGallery()` |
| Videa u prace | tabulka `portfolio_videos`, `setVideos()` / `getVideos()`; `portfolio_entries.video_url` je zrcadlo PRVNIHO videa |
| Hero media na homepage | `Templates/golden-bash-diagonal/assets/theme.css` - blok "Golden Bash Diagonal" na konci souboru; `app/Views/partials/hero-video.phtml` |
| Ktere hero video se prehraje | `HeroMediaRenderer::buildResponsiveHeroVideoHtml()`; Vimeo/YouTube vybira podle sirky `pickEmbedUrl()` v `public/assets/frontend-runtime.js`, soubor `<source media>` v `hero-video.phtml`; smisena sada = oba prehravace + `<style data-hero-video-widths>` |
| Rozmery, poster a verze lokalniho videa | `app/Services/HeroVideoMediaLookup.php` (`ratios()`, `lightPoster()`, `versionedUrl()`) |
| Nahrani videa do Medii | `MediaController::upload` + `MediaIngestionService::attachVideoDetails()` + `VideoMediaInfo`; metadata a miniaturu cte prohlizec (`probeVideo()` v `admin/views/media/index.php`), server nema ffmpeg |
| Vyber videa z Medii | sdileny picker v `admin/assets/admin.js` (zalozky Vse/Obrazky/Video, `lockKind`), tlacitko `[data-hero-video-media]` v admin/views/pages/form.php a settings/index.php |
| Odkud se bere URL hero videa | dva duplikatni injektory: `public/index.php::injectPageHeroVideo()` (live) a `PublishService::injectPageHeroVideo()` (publish) - menit VZDY oba |
| Hero na podstrankach, overlay | `app/Services/Render/HeroMediaRenderer.php`, `public/assets/frontend-runtime.css` |
| Karty blogu na homepage | `HomepageContentService::buildBlogCardHtml()`; pri ulozeni clanku je prepisuje `BlogSyncService` |
| Vypis a detail prace | `app/ViewModels/PortfolioListingData.php`, `PortfolioDetailData.php`, sablony `Templates/*/views/archetypes/portfolio-*.phtml` |
| Publikovane staticke stranky | `app/Services/PublishService.php`, pro detail prace `PortfolioPublishService.php`, vystup v `storage/published/` |
| Admin formulare | `admin/views/portfolio/form.php`, `admin/views/blog/form.php`; sdilene JS komponenty v `admin/assets/admin.js` |
| Schema a data | `database/migrations/` (append-only), dokumentacni `database/schema.sql`, seedy `database/seeds/` |
| Testy a goldeny | `php tests/run.php`, regenerace `--update-goldens`, soubory `tests/goldens/{live,publish}/` |
| Poradi a stitek karet na /cenik | cislo slotu `cenik_card_<n>_*` (`CenikPageData::fromBlocks()`), slot -> `data-package-id` v `PricingPackageCatalog::SLOT_IDS`; preusporadani viz migrace `094` |
| Editovatelne texty stranek o-nas / blog / nase-prace / cenik | bloky v `content_blocks` podle konvence nize; sablona ma stejny text jako fallback, takze klic najdes hledanim viditelneho textu v `Templates/golden-bash-diagonal/views/` |

**Konvence klicu pro texty sablon.** Kazdy viditelny text ma vlastni radek v `content_blocks`
(plochy klic, zadna nova tabulka). Sablona ho cte pres lokalni pomocnik
`$t('klic', 'dnesni text')` - DB vyhrava, literal je zaloha - a oznaci ho
`$this->inlineAttr('klic')` pro inline editor. Prefixy:

- `about_*` - stranka o-nas (`about_photographer_quote`, `about_trust_link_3`, `about_cta_heading`, ...).
- `listing_kicker`, `blog_*`, `portfolio_*` - vypisy blogu a praci.
- `route_cta_*` - spolecny tvar zaverecneho CTA pruhu (stitek / nadpis / text / tlacitko);
  stejne klice na vice strankach, protoze bloky jsou vzdy vazane na jednu stranku.
- `cenik_*` - ceník (`cenik_card_<1-8>_{title,category,badge,price,unit,desc,cta_label,cta_url}`,
  `cenik_faq_<1-5>_{question,answer}`, `cenik_packages_kicker`, `cenik_faq_heading`, ...).

Novy text = migrace s `INSERT ... ON DUPLICATE KEY UPDATE` (bez `content`, aby neprepsala
admin editaci, viz `074`-`077`) + stejny zaznam v `database/seeds/seed.php`; u o-nas navic
`PageController::ABOUT_COPY_BLOCKS`, ktery radky doplni pri otevreni stranky v adminu.

### Pasti, ktere stoji nejvic hledani

- **Kdo vyhrava v CSS:** `public/assets/frontend-runtime.css` ma pravidla s `!important`, ktera
  prebijeji temata (napr. geometrii hero iframu). Pravidlo v `theme.css` bez `!important` se
  nemusi vubec projevit - overuj vzdy vypoctenym stylem v prohlizeci, ne ctenim souboru.
- **Skutecna geometrie hera** neni na radku 64 `theme.css` (panel 62 %), ale az v bloku
  "Golden Bash Diagonal" na konci souboru, ktery panel roztahne na celou sirku a maskuje ho.
- **`Templates/golden-bash-diagonal` musi byt soubor-po-souboru kopie `golden-bash`** krome
  `template.json`, `layout.html`, `assets/theme.css` a `assets/theme.js` - hlida `tests/GoldenBashThemeTest.php`.
  Zmena ve sdilene sablone se tedy dela v obou temacich stejne.
- **Nova migrace potrebuje "already satisfied" vetev ve dvou runnerech**
  (`database/migrate-mysql.php` i `app/Services/SetupEnvironmentService.php`), jinak databaze,
  ktera uz sloupec ma, spadne na "Duplicate column" a zablokuje vsechny dalsi migrace.
- **`sort_order = 0` znamena "bez rucniho cisla"** a radi se az za ocislovane polozky; v galeriich
  je ale 0 legacy pozice z poradi vyberu, proto tam plati prosty `sort_order ASC`.
- **Hero video: kazda sirka okna ma vlastni zdroj a nic se nededi.** Pole `hero_video`,
  `hero_video_tablet`, `hero_video_mobile` (u stranky bloky, ve vychozim nastaveni
  `sites.default_hero_video_url[_tablet|_mobile]`) znamenaji desktop (nad 1050 px), tablet
  (701-1050 px) a mobil (do 700 px). **Prazdne pole = na dane sirce zadne video** (jen poster),
  nic se neprebira z jineho zarizeni. Poradi zdroju (live i publish stejne): vlastni sada stranky,
  na homepage vychozi sada z Nastaveni, hero foto stranky, poster, vychozi sada z Nastaveni.
  Sada se bere CELA (`HeroMediaRenderer::hasHeroVideo()`), sirky dvou sad se nemicha.
  Kazda sirka muze byt jineho druhu (Vimeo/YouTube nebo soubor `/media/...`); smisena sada
  vykresli oba prehravace oznacene `data-hero-widths` a malym `<style>` se zobrazi jen ten, ktery
  ma na sirce video. Soubory pouzivaji `<source media>` s rozsahy bez mezery (`max-width:700px`,
  `min-width:700.02px` a `max-width:1050px`, `min-width:1050.02px`), embedy `pickEmbedUrl()`,
  ktery pri zmene sirky prehravac vlozi, vymeni nebo odstrani. Plain `<video>` se pri prechodu
  hranice nacte znovu (`initNativeHeroVideos()`) a spusti se rucne, jakmile umi hrat (autoplay
  se u souboru s vyssim datovym tokem nez rychlost pripojeni sam nespusti).
  Staticky publish je jedna HTML pro vsechny, takze server o zarizeni rozhodovat nesmi.
  Vychozi video webu se tedy pouziva jako sada (ne po sirkach); u produkcni DB je desktop=Vimeo,
  tablet prazdny, mobil vlastni zdroj. Migrace 100 tablet vycistila; nevyplnovat ho zpet stejnou URL.
- **Video z Medii: kvalita, cache a Range.**
  - Doporuceni: smycka 10-15 s, MP4 H.264, bez zvuku a bez timecode stopy, faststart, desktop
    1920x1080 (3-5 MB), tablet 1280x720 (2-3 MB), mobil 720x1080 (do 1,5 MB). Server video
    neprekoduje (zadny ffmpeg); admin jen upozorni (`VideoMediaInfo`: nad Full HD, nad 25 MB, na vysku).
  - Rozmery se ukladaji do `media.width/height` a pouzivaji pro `aspect-ratio` plain `<video>`
    (`HeroVideoMediaLookup::ratios()`); jinak se element po prichodu metadat prepocita z pomeru
    posteru na pomer videa a posune celou stranku (CLS). Pouziva se presne `W / H`, ne `auto W / H`
    (to by do prichodu metadat preferovalo pomer posteru).
  - `<video poster>` je jedna obycejna URL, proto se pouziva lehka varianta `medium`/`small` z knihovny
    (`lightPoster()`), ne originalni PNG o nekolika MB. Embed si ponechava puvodni poster (dostane srcset).
  - `preload="metadata"`, ne `auto`: `auto` stahne cely soubor a zdrzuje udalost `load`.
  - Lokalni videa dostavaji `?rev=<mtime souboru>` (`versionedUrl()`): media maji `immutable` na rok,
    vcetne cache pred PHP, a jednou zapamatovana stara odpoved (bez Range, octet-stream) by se
    vracela donekonecna. **Parametr se NESMI jmenovat `v`:** YouTube detektor (`[?&]v=<id>`)
    z toho udelal iframe neexistujiciho videa. YouTube `?v=` se ted bere jen na hostech YouTube.
  - Pri vymene souboru se `rev` zmeni sam (jina mtime); stare adresy bez `rev` mohou cache vracet
    po zmene servirovani stale po dobu platnosti.
- **Legacy sablony s `<video data-block="hero_video">`** (stare seedy) si hero video vykresli
  samy pres `PageRenderer`, injektor je pak preskoci - varianty se na takovych strankach
  neprojevi. Aktivni tema tudy nejde.
- **Hranice 700 a 1050 px zijou na trech mistech** (`theme.css`, `critical-above-fold.css`,
  `frontend-runtime.js`) - media query nejde sdilet s JS bez buildu, shodu hlida
  `tests/ResponsiveHeroVideoTest.php`.
- **Goldeny se meni i pri zmene poradi obsahu.** Pred regeneraci zkontroluj, ze se zmenily jen
  ocekavane soubory; ulozeni clanku nebo prace v adminu prepise demo data a tim i goldeny.
- **Goldeny porovnavaji obsah bajt po bajtu, vcetne konce radku.** `git checkout -- tests/goldens`
  je na Windows prepise na CRLF a vsechny dotcene kontroly pak hlasi CHANGED (+N -N) bez jedine
  skutecne zmeny obsahu. Obnovuj je tedy `php tests/run.php --update-goldens`, ne z gitu.
- **`BlockRepository::updateContent()` byl driv jen UPDATE** a na klic bez radku tise neudelal nic -
  inline editor hlasil "Ulozeno" a text zahodil. Dnes vraci `bool` a `InlineController` chybejici
  radek zalozi. Kdyz pridavas novy `inlineAttr()` klic, radek stejne zaloz migraci; upsert je
  zachranna brzda, ne nahrada migrace (jinak vznikne blok s labelem = klic).
- **`CenikPageRenderer::inject()` dostava `editMode` jako parametr**, protoze bezi az v post-render
  kaskade `PageComposer` a zadny ViewFactory kolem nej o prihlasenem adminovi nevi. `composeLive`
  ho predava, `composePublish` zamerne ne. Bez toho na /cenik zadny inline atribut nevznikne,
  at si sablona zada co chce.
- **Sekce `gb-about-process` ("Jak pracujeme") v `views/pages/o-nas.phtml` je mrtvy kod.**
  `SharedProcessRenderer::inject()` cely `<section class="...gb-about-process">` pri renderu
  nahradi obsahem ze sdilenych sekci, takze upravy v sablone (vcetne pridani `inlineAttr`)
  se na strance nikdy neprojevi.
- **Inline editor uklada `textContent` cele oznacene elementu.** Klic proto patri na element,
  ktery obsahuje JEN ten jeden text - ne na `<a>` se sipkou nebo `<div>` s cenou i jednotkou,
  jinak by se dekorace ulozila do textu a pri dalsim renderu zdvojila. Takove texty jsou
  editovatelne jen v adminu (napr. `blog_more_label`, `cenik_card_*_price`).

## Request flow

### Admin

```text
request -> admin/index.php -> Core\Router -> Controller -> Repository/Service -> View
```

### Verejny web

`public/index.php` odbavuje requesty v tomto poradi:

```text
1. GET/HEAD /api/health -> HealthCheckHttpResponder, application + DB readiness, 200/503
2. /.well-known/security.txt a /security.txt -> SecurityTxtHttpResponder
3. /assets/*, /media/*  -> tryServePublicAssetWithoutSession(): realpath ochrana, typy vcetne mp4/webm/mov,
   `Accept-Ranges`, `Range`->206/416 a `If-Range` (ETag i datum Last-Modified, kvuli Safari),
   soubor nad 4 MB nebo cast se posila po kouscich (`fread`), ne `readfile`
4. POST /api/visit      -> tryRecordVisitBeacon(), vlastni mereni navstevnosti (pred Auth::start)
   -- Auth::start() --
5. /api/v1/*            -> ApiController::dispatch()
6. legacy /blog/* nebo /nase-prace/* -> 301 redirect na prime slugy
7. /sluzby              -> tryServeServicesPage() z `service_items`
8. /nase-prace          -> tryServePortfolioPage() listing
9. /blog                -> tryServeBlogListingPage()
10. /faq                -> tryServeFaqPage() z `faq_items`
11. /kontakt            -> tryServeKontaktPage() z `contact_info`
12. POST /kontakt/odeslat -> CSRF, honeypot, throttling, archiv v DB a SMTP; veřejný kontrakt přijímá jméno, kontakt, typ focení, místo, zprávu a volitelný enum-like `selected_package_id`, nikoli název, cenu ani datum akce. `PricingPackageCatalog` identifikátor ověří proti serverovému whitelistu a teprve z interní definice získá název a cenu pro e-mail. Neznámé ID blokuje odeslání se stavem `invalid-package`; redirect zachovává pouze ověřené ID v query `balicek`.
13. CMS stranka z DB    -> tryServeDatabasePage()
14. Blog detail         -> tryServeBlogPostPage()
15. Portfolio detail    -> tryServePortfolioPage(..., true)
16. storage/published/* -> explicitni soubory podle pripony (napr. sitemap, robots, llms.txt a ikony)
17. 404
```

Security endpointy jsou zamerne pred `Auth::start()`: verejny strojovy soubor nesmi zakladat PHP session ani posilat session cookie.
Totez plati pro `POST /api/visit`: beacon nesmi zakladat session a lezi mimo `/api/v1/*`, takze nevyzaduje API klic.
`/api/health` je jeste drive: nezaklada session ani visit, neni v sitemap/robots a vraci `X-Robots-Tag: noindex, nofollow, noarchive`. Odpoved zverejni jen `status`, UTC cas a agregovane `application/database` checks. DB chyba je JSON s HTTP503, nikoli tichy HTTP200.

### Health monitoring pipeline

`bin/health-check.php` vola pres verejnou domenu `/api/health`, zmeri odezvu a ulozi kazdy beh do `health_checks` (migrace110). HTTP chyba, timeout, neplatny JSON i database=error jsou `unhealthy`. Runner konci 0 jen pri zdravem kontraktu, 1 pri nezdravem stavu a 2 pri konfiguracni nebo dvojite persistencni chybe. Pokud zapis do DB neni mozny, stejny bezpecny record s UUID jde do `storage/logs/health-check-pending.jsonl`; dalsi beh po obnove DB soubor pod exkluzivnim zamkem prehraje a az po uspesnem vlozeni vyprazdni. UNIQUE UUID dela retry idempotentni. Neplatny radek se nesmaze ani nepreskoci.

`HealthCheckRepository::summary()` napaji existujici `/admin/analytics`: aktualni stav, uptime pouze z provedenych kontrol, response time a denni graf. Den bez dat je sedy, ne 100% healthy. Posledni zaznam starsi nez 20 minut je `stale`/„Monitoring neběží“, aby zastaveny cron nebyl tise vydavan za dostupnost. Historie ma retenci 14 mesicu stejne jako navstevnost.

## Obsahovy model

### CMS stranky

- `pages` drzi bezne CMS stranky navazane na `templates`
- `content_blocks` drzi editovatelne bloky podle `data-block="key"`
- specialni stranky `faq`, `kontakt`, `cenik`, `sluzby`, `blog`, `nase-prace` a `hodnoceni` se renderuji pres dedikovany archetyp (ViewModel + `archetypes/<key>.phtml`), viz "Render vrstva"
- specialni verejne stranky musi porad cist obsah z DB tabulek (`service_items`, `faq_items`, `contact_info`, `blog_posts`, `portfolio_entries`, `reviews`) a nesmi doplnovat obsahova data ze sablon
- vysledny archetype/fragment obsah dal prochazi `PageComposer` (mody live/publish) a aktivni tema vzdy dostane stejny layout, navigaci, footer a theme assety jako ostatni verejne stranky

### Blog a portfolio

Blog a portfolio sdileji layout a navigaci, ale maji vlastni tabulky a renderer:

| Oblast | Blog | Portfolio |
| --- | --- | --- |
| Tabulky | `blog_posts`, `blog_media` | `portfolio_entries`, `portfolio_media` |
| Listing URL | `/blog` | `/nase-prace` |
| Detail URL | `/{slug}` | `/{slug}` |
| Detail renderer | `tryServeBlogPostPage()` | `tryServePortfolioPage(..., true)` |

Prime slugy bez `/blog/` a `/nase-prace/` prefixu jsou zamerne. `tryRedirectLegacyContentPath()` drzi 301 redirect ze starych cest.

### Media

- DB uchovava interni `storage_path` jako `storage/media/YYYY/MM/file.webp`
- verejny frontend i API vraci root-relative URL `/media/YYYY/MM/file.webp`
- `MediaIngestionService` validuje MIME i priponu, uklada original a pres GD generuje varianty `webp`, `medium`, `thumb` a `og`

### Temata

Temata ziji v `Templates/<key>/` a pouzivaji:

- `layout.html` (legacy) nebo `views/layout.phtml` (PHTML layout)
- `assets/theme.css`
- volitelne `assets/theme.js`
- `template.json` (manifest v1 nebo v2)

`ThemeTemplateRegistry` nacte metadata tematu, aplikuje layout/soubory do DB a nastavuje `sites.active_theme_key`. Aplikace tematu prepisuje layout v databazi, neni to jen kosmeticky prepinac.

Regresni test `ThemeSwitchPublishIntegrationTest` proto neoveruje pouze registry
metadata. Nad skutecnym webem postupne aktivuje Golden Bash a Elegant White,
po kazdem prepnuti spusti kompletní `PublishService::publishSite()` a v `finally`
obnovi i znovu publikuje puvodni sablonu. Test pouziva existujiciho publisher
uzivatele, protoze publikacni historie ma cizi klic do `users`.

Kanonicky vychozi motiv je `golden-bash-diagonal`. Resolver, obecny schema snapshot,
seed SQL i vsechny PHP seedy cteji `ThemeTemplateRegistry::DEFAULT_THEME_KEY`.
Historicke migrace 021 a 038 zustavaji nemenene. Append-only migrace
`066_set_golden_bash_diagonal_default_theme.sql` meni schema default a prevadi
weby s prazdnym `active_theme_key` nebo presnym drivejsim defaultem
`golden-bash`. Explicitne zvoleny jiny motiv se migraci neprepisuje a puvodni
`golden-bash` zustava v registru pro vedomou pozdejsi volbu.

`golden-bash-diagonal` je plny izolovany duplikat vychoziho motivu. `ThemeTemplateRegistry::GOLDEN_BASH_THEME_KEYS` a `isGoldenBashFamily()` zachovavaji pro oba klice stejny obsahovy a renderovaci kontrakt: stabilizaci hero titulku, vsechny homepage portfolio galerie a sdilenou procesni sekci. Layout duplikatu ponechava kompatibilitni tridu `theme-golden-bash`; registry k ni doplni vlastni `theme-golden-bash-diagonal`, takze kopie CSS/JS muze zustat shodna mimo explicitne ohraniceny hero override. Test `GoldenBashThemeTest` porovnava seznam 16 souboru a SHA-256 hashe vsech souboru mimo manifest, layout a jediny povoleny CSS/JS hero rozdil.

### Theme manifest a override kaskada

`template.json` je zpetne kompatibilni. Manifest v1 klice (`layout_html`, `assets.css/js`, `overrides.<pageKey>.layout_html`, `overrides.<pageKey>.assets`) fungujou dal. Manifest v2 pridava opt-in PHTML view kaskadu:

```json
{
  "layout_html": "layout.html",
  "views_dir": "views",
  "overrides": {
    "cenik": {
      "view": "views/pages/cenik.phtml",
      "assets": { "css": ["assets/cenik.css"] }
    }
  }
}
```

- `views_dir` aktivuje theme PHTML kaskadu (prazdne/chybejici = legacy chovani beze zmeny). `ThemeTemplateRegistry::getViewsDir()` / `hasViewsCascade()`.
- `overrides.<pageKey>.view` prepise CONTENT view genericke stranky (ne jen layout). `ThemeTemplateRegistry::pageContentView()` ho resi (bud explicitni `view`, nebo implicitne `<views_dir>/pages/<pageKey>.phtml`) a oba genericke render seamy (live `tryServeDatabasePage`, publish `publishPage`) ho preferuji pred core `archetypes/page`.
- PHTML layout: kdyz tema veze `views/layout.phtml`, layout shell se renderuje pres `ThemeLayoutRenderer` misto `layout.html` str_replace. Aktivni tema `elegant-white3` je referencni implementace.

Temata nesmi byt zdrojem obsahovych dat -- viz nize.

Temata nesmi byt zdrojem obsahovych dat. Layouty maji obsahovat jen strukturni placeholdery jako `{site_name}`, `{nav}` a `{page_content}` a tema CSS/JS ma resit pouze prezentaci. Texty, media, CTA obsah, kontakt, cenik, sluzby, blog, portfolio a SEO metadata maji pochazet z DB nebo z DB-driven renderovacich sluzeb.

## Render vrstva (PHTML view system)

Render byl preveden z hardcodovaneho HTML generovaneho v PHP (heredocy, string concat, ~20 post-inject prubehu nad hotovym stringem) na PHTML view system se sdilenym `PageComposer` pipeline pro live frontend i publish. Aktualni kontrakt je popsany primo v teto kapitole; jednorazovy implementacni plan byl po dokonceni odstranen.

### Komponenty

| Vrstva | Trida / adresar | Odpovednost |
| --- | --- | --- |
| View engine | `App\View\ViewEngine` | `render(view, data)`: `ob_start` + `extract` + `include` PHTML souboru; escaping je default |
| Resolver | `App\View\ViewResolver` | Kaskadova resoluce view souboru s `realpath()` containmentem a regexem jmena view (`^[a-z0-9][a-z0-9/_-]*$`) |
| Kontext | `App\View\ViewContext` | `$this` uvnitr sablony: `e()`, `raw()`, `partial()`, `block()`, `inlineAttr()`, `slot()/endSlot()`, `layout()`, `asset()`, `mediaUrl()` |
| Factory | `App\View\ViewFactory` | Sestavi `ViewEngine` s poradim rootu tema -> core; `roots(themeKey, viewsDir)`, `forTheme()`, `themeViewPath()`, `themeLayoutPath()` |
| ViewModely | `App\ViewModels\*` | Ciste datove providery (`FaqPageData`, `SluzbyPageData`, `CenikPageData`, `BlogListingData`, `BlogPostData`, `PortfolioListingData`, `PortfolioDetailData`, `ReviewsPageData`, `KontaktPageData`, `HomepageData`, ...). Vraci jen skalary/pole, nikdy HTML; media URL uz prelozene na `/media/...`; zadne SQL |
| Sablony | `app/Views/*.phtml` | `layouts/default.phtml`, `archetypes/<key>.phtml`, `partials/<name>.phtml`, `assets/*.css` (vc. `shared-components.css`) |
| Sdilena render vrstva | `App\Services\Render\*` | `PageComposer` (orchestrator), `HomepageSectionRenderer`, `CenikPageRenderer`, `FooterRenderer`, `HeroMediaRenderer`, `MarkupNormalizer`, `ContactNormalizer`, `SocialLinkRenderer`, `MediaUrlResolver`, `ThemeLayoutRenderer`, kontrakt `PublishPageChrome` |

### ViewResolver kaskada

Pro dane logicke jmeno view (napr. `archetypes/cenik`, `partials/hero`) se hleda v tomto poradi (prvni nalezeny vyhrava):

```text
1. Templates/<aktivni tema>/<views_dir>/<view>.phtml   (theme override)
2. app/Views/<view>.phtml                              (core default, musi existovat)
```

`ViewFactory::roots()` sklada tyto rooty; theme root se pridava jen kdyz je klic tematu validni proti strikni masce. `ViewResolver` navic dela `realpath()` prefix kontrolu, takze uzivatelsky vstup nikdy neresolvuje cestu mimo povolene rooty. Jmeno view nikdy nepochazi z user inputu -- `pageKey` se odvozuje z DB `url_path`.

### PageComposer

`App\Services\Render\PageComposer::compose($ctx)` je jediny orchestracni domov post-render kaskady, sdileny live frontendem (`public/index.php::tryServeDatabasePage`) i publishem (`PublishService::publishPage`). Mody `MODE_LIVE` / `MODE_PUBLISH` sdileji stejnou sekvenci (homepage sekce, cenik splice, assety, SEO head, footer, hero media, breadcrumb, sanitize) pres `App\Services\Render\*` sluzby; divergentni leafy (site logo, hero image/video, video overlay, live-only editor chrome) jsou zamerne oddelene. Live leafy jsou globalni funkce z `public/index.php`; publish leafy prichazeji z injektovaneho `PublishPageChrome` (implementuje `PublishService`).

### Archetypy

- **Dedikovane archetypy** (`faq`, `sluzby`, `cenik`, `reviews`/`hodnoceni`, `blog-listing`, `blog-detail`, `portfolio-listing`, `portfolio-detail`, `kontakt`): stranka se renderuje pres ViewModel provider + `archetypes/<key>.phtml`. Live i publish sdileji relevantni render cesty (napr. publish je resi pres `PublishService::renderArchetypeContent()`).
- **Cenikovy datovy kontrakt**: `CenikPageData` cte az osm `cenik_card_N_*` bloku (nazev, kategorie, cena, jednotka, popis/body, badge a CTA), dopravu a pet `cenik_faq_N_*` dvojic. Theme archetyp pouze escapuje a sklada tyto DB hodnoty; presne ceny ani texty nesmi byt natvrdo v theme. Kazdy slot ma v `App\Domain\PricingPackageCatalog::SLOT_IDS` stabilni `data-package-id` (vyber balicku pro kontaktni formular); novy slot = novy zaznam v katalogu, smycky v `CenikPageData` a `admin/views/pages/form.php` a migrace (vzor `093`). Poradi karet na /cenik (i zalozek "Karta N" v adminu) je cislo slotu; preradit karty = migrace, ktera prejmenuje radky `cenik_card_<stary>_*` na novy slot (block_key, label, sort_order - obsah vcetne admin editaci jde s kartou), preusporada legacy HTML `pricing_packages`/`pricing_body` a ve stejne zmene se preusporada `SLOT_IDS`, aby `data-package-id` / `?balicek=` zustal u sveho balicku (vzor `094`: jen single-table UPDATE, guardy predem do uzivatelske promenne, kazda faze vlastni guard, takze rerun je no-op a prerusena migrace se pri opakovani dotahne). Stitek (`cenik_card_N_badge`) ridi `.ckp-card--featured` i autoscroll v `initCenikAutoScroll()`. Homepage teaser (`HomepageData::pricingCards()`) vybira tri balicky podle id (`HOMEPAGE_PRICING_PACKAGES`: Celodenni svatebni foceni / Kompletni balicek foto + video / Celodenni svatebni video, drive Jen natacení celodenni svatby - prejmenovano migraci `099`, id `svatebni-video-jen-natoceni`, slot a CTA zustaly; migrace 099 meni jen `cenik_card_*_title` rovny puvodnimu nazvu a `<h3 class="ckp-title">` v legacy `pricing_packages`/`pricing_body`, stare migrace 093/094 se nemeni), ne podle slotu, takze ho preusporadani ceniku nemeni. Kategorie (`cenik_card_N_category`, `.gb-price-type`) a stitek (`cenik_card_N_badge`) se na homepage berou z DB karty /cenik; featured karta (`hp-cenik-card--featured` + server-renderovany `.hp-cenik-card-badge`) je ta, ktera ma neprazdny stitek, takze se nezdvojuje s JS fallbackem v `theme.js`. Desktopovy grid ceniku (od 901 px) je v obou Golden Bash `theme.css` sestisloupcovy (sirky 0,92/1,16/0,92 jako `frontend-runtime.css`, karta = 2 sloupce), aby se neuplny posledni radek (7 nebo 8 karet) vycentroval.
- **Homepage cenikovy teaser**: `HomepageData::pricingCards()` z cenikovych DB bloku pouziva jen titul a maximalne ctyri popisne body. Cenu nevraci a z bodu odstranuje i texty obsahujici menovou castku; uplne ceny zustavaji pouze na `/cenik`.
- **Logo v theme headeru**: Golden Bash pouziva na homepage i vnitrnich route stejny layoutovy `.gb-header>.gb-brand`; `layout_nav_home` a `layout_nav_sub` meni pouze sadu odkazu uvnitr `.gb-nav`. Mobilni rozmery loga proto musi byt scopeovane na sdileny header, nikoli na pritomnost route-specific `.hp-hero`.
- **SEO title precedence**: pokud `SeoRenderer` doda strankovy `<title>`, `PageRenderer::injectSeo()` nejprve odstrani layoutovy fallback `<title>{site_name}</title>`. Vysledny dokument tak obsahuje prave jeden title; bez strankoveho SEO title zustava layoutovy fallback zachovan.
- **Social image precedence**: administrace uklada tri validovana media ID stejneho webu. `sites.default_og_image_id` je Facebook/Open Graph obrazek (1200x630), `default_twitter_image_id` je Twitter/X obrazek (1200x600) a `default_instagram_image_id` je exportni Instagram podklad (1080x1080 nebo portrait 1080x1350). Instagram nema standardni webovy OG meta tag, proto se jeho pole zamerne nevklada do `<head>`. Open Graph poradi je strankovy OG -> globalni OG. Twitter poradi je explicitni strankovy Twitter -> globalni Twitter -> strankovy OG -> globalni OG, aby platformni globalni obrazek nebyl prebit pouze obecnym OG fallbackem. Migrace 027 nastavi na `NULL` jen stare Twitter hodnoty automaticky zduplikovane z OG a jen pokud web uz ma globalni Twitter obrazek; skutecny odlisny strankovy override zachova. `resolveSocialImageUrl()` preferuje variantu `og` a sklada absolutni URL. Stejny kontrakt plati pro generic live render, specialni routy, portfolio listing/detail i `PublishService`.
- **JSON-LD image synchronization**: `SeoRenderer` normalizuje strankovy nebo globalni OG obrazek na absolutni URL a pouzije jej v `og:image`, v obsahovem `LocalBusiness` schema uzlu i v samostatnem Organization/LocalBusiness uzlu. Bez vyreseneho OG obrazku se `image` nedosazuje z hero ani jineho defaultu. `MediaUrlResolver::absolutePublicMediaUrl()` pred doplnenim domeny vzdy prevadi fyzickou `storage/media/...` cestu na verejny endpoint `/media/...`; primy produkcni `/storage/media/` je chraneny a vraci 403. `sanitizeFrontendHtml()` tokenizuje cele `application/ld+json` skripty a socialni meta tagy, aby absolutni verejne URL pri dalsi normalizaci zustaly nedotcene. Dekorativni `welcome-*` obrazky nejsou v `<head>` preloadovane a nemohou se plest se socialnimi metadaty.
- **WWW canonical redirect**: root a public `.htaccess` maji pred front controllerem jedine `mod_rewrite` pravidlo, ktere reaguje pouze na `HTTP_HOST` zacinajici `www.`, odebere tento prefix a vrati 301 se zachovanim `REQUEST_SCHEME`, portu, `REQUEST_URI` a puvodnich query parametru. `PublishService::generateHtaccess()` generuje stejny blok pro staticky web. Ostatni subdomeny a non-www hosty se nepresmeruji.
- **Golden Bash service icons**: homepage sekce O nas nacita Google Fonts `Material Symbols Outlined` a sest sluzeb mapuje na ligatury `favorite`, `movie`, `diamond`, `celebration`, `business_center` a `flight`. Ikony jsou dekorativni (`aria-hidden`) a text karty zustava pristupnym nazvem.
- **Demo svatebni blog**: `WeddingBlogDemoContentImporter` nacita 12 schvalenych Markdown podkladu z `Data pro clanky/RB_Videos_Photos_NASE_PRACE/nase-prace-md`, upsertuje je podle slugu do `blog_posts` a 24 explicitne naparovanych fotografii posila pres `MediaIngestionService`. Prvni fotografie je `featured_image_id`, druha se zapisuje do `blog_media`; `BlogPostData` pak featured medium znovu vlozi jako prvni galerijni zaznam, aby bylo dostupne v hero i kompletnim seznamu. Opakovany seed odstrani stejne slugy z drive chybne pouzitych `portfolio_entries`; publikovane blogy pak standardne vstupuji do sitemap. Seed vyzaduje fyzicky original i `thumb` variantu a pri chybejicim derivatu skonci chybou.
- **Demo portfolio**: `PortfolioDemoContentImporter` vlastni tri oddelene manifesty. `MANIFEST` (9 kuratorovanych fotogalerii, 33 fotografii z `public/assets/demo/dalsifotkydoportfolia`). `MIX_MANIFEST` (15 fotogalerii, 76 fotografii z `public/assets/demo/mix-vseho-portfolio`, sestavene 2026-09-06 z dodaneho smiseneho fotobalicku - rodina, tehotenske, svatba a modelka/portret; produktova ani vyrazne firemni fotografie ve zdroji nebyly, proto tyto kategorie chybi). `VIDEO_MANIFEST` (12 video polozek, `content_type=video`, bez fotografii) z `database/seeds/demo_assets/videa.txt` - Vimeo radky uz pouzivaji dve fotogalerie a nesouvisejici auto/DayZ radky na konci zdroje jsou vynechany. Pred mutaci overi unikatni slugy napric vsemi tremi manifesty, neprazdne texty a `video_url` u videopolozek, existenci vsech mapovanych souboru v prislusnem zdrojovem adresari, absenci neprirazenych obrazku a presny pocet fotografii (33 pro `MANIFEST`, 76 pro `MIX_MANIFEST`). Pak pres `PortfolioRepository::delete()` odstrani vsechny stare zaznamy webu, nulovy zbytek overi SQL kontrolou a nove fotografie importuje vyhradne pres `MediaIngestionService::ingestUploadedFile()`; video polozky nemaji zadne medium a jejich nahledovy obrazek dodava za behu `PortfolioRepository::youtubeThumbnail()` z `video_url`. Import je idempotentni podle kanonickeho `original_filename=portfolio-<slug>-NN.<ext>` (foto) resp. slugu (video); existujici medium se znovu pouzije jen tehdy, kdyz fyzicky existuje original i `thumb`, jinak proces skonci viditelnou chybou nebo derivat regeneruje pres standardni media sluzbu. Vystupem je 24 fotogalerii (109 vazeb `portfolio_media`) a 12 videi, celkem 36 publikovanych `portfolio_entries`; `bin/verify-demo-portfolio.php` navic potvrdi nulovy pocet peti starych generickych slugu. Galerie Lea a nekolik `MIX_MANIFEST`/video polozek nemaji ve zdrojovych podkladech datum, proto pouzivaji `published_at=NULL`, nikoli vymysleny fallback. Jeden dodany `/shorts/` odkaz je normalizovan na rovnocenny `/watch?v=` tvar, protoze `PortfolioRepository::embedUrl()`/`youtubeThumbnail()` tvar `/shorts/` nerozpoznavaji. Galerie slozene z fotek ruznych realnych session (napr. `svatba-auta-doprovod` kombinuje dve odlisne svatby se svatebnimi auty) nemaji vymyslene jmeno konkretniho paru/rodiny - popisuji styl a pristup obecne, aby nevznikl falesny dojem jedne kontinualni udalosti.
- **Golden Bash listing grid pro >9 fotogalerii**: `.gb-work-listing .pt-grid` (desktop, `/nase-prace`) ma rucne vyskladany 4x4 mozaikovy vzor pres `:nth-child(1)` az `:nth-child(9)` a zadny fallback pro dalsi polozky. Bez `grid-auto-rows` kolabuji karty `:nth-child(10)` a dal na nulovou vysku, protoze `.pt-card__media` je `position:absolute` (neprispiva vlastni vyskou) a `.pt-card` ma explicitni `min-height:0` - karty jsou v DOM (SEO/pristupnost v poradku), ale vizualne zmizi. Objevilo se to az po rozsireni `MIX_MANIFEST` z 9 na 24 fotogalerii. Oprava: pridat `grid-auto-rows:230px!important` do zakladniho pravidla (bez media query) v obou tematech; tabletovy (800px) a mobilni (700px) breakpoint uz `grid-auto-rows` mely, takze tam byl bug latentni jen na sirokem desktopu. Homepage mozaika (`.gb-portfolio-mosaic`) stejny problem nema - `initHomepagePortfolioLoadMore()` v `theme.js` zobrazuje jen prvnich 5 karet a kazdou dalsi davku odhaluje az po kliknuti na "Dalsi" s `grid-auto-rows` nastavenym pres `.gb-portfolio-load-more-active`.
- **Casovy kontrakt Demo seedu v administraci**: verejna administrace nikdy nevola kompletni `DemoContentSeeder::run()` ani nezveda `set_time_limit`. `DemoContentBatchService` rozlozi referencni obnovu na 100 persistentnich jednotek: preflight, 41 jednotlivych zakladnich medii, 12 svatebnich clanku po dvou fotografiich, 36 portfolio polozek (24 fotogalerii s 109 fotografiemi + 12 videi bez medii), kratke obsahove faze, publish a fingerprint. `demo_seed_jobs` (append-only migrace 063) uklada fazi, cursor, procenta, chybu a petiminutovy lease; unikatni `site_id` zakazuje soubezne ulohy. Admin endpointy `start`, `step`, `status` a `retry` jsou frontendem volany sekvencne, po reloadu pokracuji z DB checkpointu a selhani zustava viditelne. Importy jsou retry-safe: media se deduplikuji kanonickym nazvem, blog pouziva upsert a portfolio pred opakovanim jednotky nahrazuje pouze stejny slug.
- **Obnova demo media variant**: svatebni importer kontroluje dostupnost PHP GD pred prvnim zapisem, aby nevytvoril castecny dataset bez thumbnailu. Pokud opakovany beh najde drive zaregistrovany original bez fyzicke `thumb` varianty, zavola `MediaIngestionService::regenerateImageVariants()` a variantu doplni; chybejici GD, cizi site ID, video nebo necitelny original zustavaji viditelnou chybou.
- **Nahledy clanku v Premium Photo**: homepage portfolio/blog a listingy `/nase-prace` a `/blog` pouzivaji stejny `premium-media--film` kontrakt. URL media patri pouze do jednoho `<img>`; nesmi se soucasne opakovat v inline `background-image`. CSS roztahne obraz pres celou sirku mezi 20px cernou horni a spodni filmovou listou a caption vykresli jako absolutni overlay.
- **Sdilena detailni galerie Premium Photo**: `assets/theme.js` rozpozna `.bp-hero`, `.pf-detail-hero` a `.svc-hero`, prevezme pouze skutecna media z hero a DB-renderovaneho obsahu a sestavi jednu `.detail-media-gallery`. Hero medium je vzdy prvni polozkou seznamu; stejnou URL z nasledneho DB gallery bloku runtime deduplikuje, ale ze zdrojoveho seznamu se uz neorezava. Blog hero pouziva originalni `media.storage_path`, zatimco `thumb_path`/`medium_path` zustavaji pouze pro nahledy. Portfolio a sluzby uz predavaji full/original URL. Obrazky, nativni video a YouTube/Vimeo embed pouzivaji stejny track. Ovlada se sipkami, teckami, Left/Right/Home/End a horizontalnim swipem. Aktivni tecka se zvetsi a CSS respektuje `prefers-reduced-motion`. Pokud stranka nema zadne medium, komponenta se nevytvori a nevklada placeholder.
- **Modalni galerie karet Golden Bash**: `HomepageData` a `PortfolioListingData` predavaji kartam skutecne `published_at`, zkraceny DB popis a originalni URL vsech `portfolio_media`. Partialy je zapisuji jako `data-gallery-*` a skryty `.pt-photos`; tema proto pri otevreni karty neprovadi dodatecny request ani nevymysli obsah. Jeden dialog v `Templates/golden-bash/assets/theme.js` obsluhuje homepage `.pt-tile` i listing `.pt-card`, cykli index modulo, podporuje sipky, klik na hlavni obraz, Left/Right, Escape, backdrop close a vraceni fokusu. Odkaz `Vice info` je jedina cesta z galerie na detail. Projekt bez galerie pouzije skutecny cover jako jedinou polozku; bez galerie i coveru se modal neotevre. Detail portfolia nesmi zvetsovat galerijni thumbnail: `PortfolioDetailData` nastavi hero na `medium`, theme jej renderuje jako prioritni `<img class="hero-bg" data-responsive-profile="portfolio-hero">` a `ResponsiveImageMarkupService` nabidne 480/720/1280px varianty. `sizes` je na mobilu sirka viewportu minus 32 px a na desktopu 62vw, takze Chrome pro zhruba 930px plochu zvoli 1280px/81KB WebP misto 300px thumbnailu nebo neomezeneho originalu.
- **Mobilni Golden Bash homepage**: breakpoint do 700 px rezervuje v hero pevny prostor nad absolutnim benefit pruhem pro jedine viditelne CTA; runtime vytvorene `Ukazka prace` zustava v DOM, ale `.gb-hero-work` ji skryva pres `display:none`. Mobilni fixed hlavicka ma u horni hrany 72 px a po scrollu nad 8 px JS prepne `gb-mobile-header-compact`, ktera plynule zmensi hlavicku i rezervu shellu na 42 px; `prefers-reduced-motion` animaci vypina. Prechod profilu sluzeb do `Nase prace` pouziva zmenseny spodni a horni padding; desktopove rozmery sekci se tim nemeni.
- **Golden Bash Diagonal hero**: duplikat `Templates/golden-bash-diagonal/` meni pouze geometrii homepage media vrstvy. SVG clip je primy polygon `M .27 0 H 1 V 1 H .55 Z` a zlaty okraj pouziva shodnou primku `M270 0 L550 1000`; media vrstva ma na desktopu/tabletu plnou sirku, proto je videt vetsi cast videa. Do 700 px se clip vypina a zustava puvodni mobilni full-bleed kompozice. Ostatni soubory a assety jsou fyzicke kopie, aby pozdejsi uprava jedne sablony skryte nemenila druhou.
- **Dlazdicovy listing Nase prace v Golden Bash**: `views/archetypes/portfolio-listing.phtml` zachovava jednu semantickou full-link kartu pro kazdy skutecny zaznam z `PortfolioListingData`, vcetne H2, data atributu a skryte galerie. Theme CSS meni pouze prezentaci: aktualni devitipolozkovy desktop pouziva asymetrickou mozaiku 4 sloupce x 4 radky s explicitni pozici `nth-child(1..9)`, obraz pres celou dlazdici a kontrastni gradientovy titulek; do 800 px prechazi na dva sloupce a do 700 px na kompaktni 220px radky bez desktopoveho row-spanu. Explicitni pozice jsou nutne, protoze puvodni 4x2 geometrie vyplnila vsech osm bunek prvnimi peti kartami a dalsi zaznamy padaly do implicitnich radku nulove vysky. Perex zustava v DOM pro datovy a modalni kontrakt, ale vizualne se skryva. Cela dlazdice je odkaz a ma explicitni `:focus-visible` obrys; live i publish sdileji stejny theme archetyp. Homepage je zamerne teaser: `HomepageContentService::HOMEPAGE_PORTFOLIO_LIMIT` omezuje zdroj na prvnich pet publikovanych galerii a Golden Bash zobrazi kazdou z nich prave jednou v petidilne mozaice. Dalsi fotografie zustavaji pouze ve skrytem `.pt-photos` seznamu jedine karty a oteviraji se v jejim lightboxu; klient z nich nesmi klonovat dalsi karty. Samostatny `PortfolioListingData::forSite()` tento limit nepouziva a `/nase-prace` proto vzdy vypise vsechny publikovane galerie.
- **Homepage O nas v Premium Photo**: `assets/theme.js` sklada `.premium-about-split` ze skryteho DB markeru `.jb-about-visual__source` a prvnich ctyr sluzeb. Kompozice podporuje jak theme PHTML wrapper `.premium-photo-home` s `.premium-service-card`, tak publikovany genericky homepage DOM v `main` s `.hp-svc-card`; produkcni sekce proto nezavisi na tom, zda byl staticky vystup vygenerovan pred nebo po zapnuti manifest-v2 homepage wrapperu.
- **Golden Bash O nas media a odkazy**: append-only migrace 086 a Demo seeder importuji aktualni `weare5.jpg` standardni Media Library pipeline a vazou jej na homepage blok `jb-about-visual__media`; historicka migrace 085 a jeji `weare4.jpg` zustavaji beze zmeny. Administrator muze v editoru homepage vybrat existujici medium nebo primo nahrat novou fotografii s povinnym ALT textem; image-only upload prochazi `MediaController` a po ulozeni stranky prepise pouze tento DB blok. `HomepageSectionRenderer` predava optimalizovanou variantu jako skryty DB marker a theme stejny serverem vyrenderovany obrazek presune do tymove kompozice bez druheho requestu. Golden Bash fotografii zobrazuje s radiusem 18 px, bez bile vinetace a bez vnejsiho glow; spodni cerny gradient od 38 % vysky zesiluje k 90% cerne u spodni hrany a oba radky popisku maji samostatny textovy stin. Original fotografie zustava beze zmeny. Samostatne bloky `jb-about-photographer__media` a `jb-about-cameraman__media` nadale vlastni dva portrety pro ostatni kompozice a `weare2.png` zustava dostupny pro sve oddelene SEO pouziti; prvni karta sluzeb ("Svatební den - foto + video" na `/sluzby` i v homepage `hp-services-grid`) od migrace 097 pouziva `weare5.jpg` (`DemoContentSeeder::FIRST_SERVICE_VISUAL`, seed i handler `replaceFirstServiceVisualWithWeare5()` hledaji sluzbu podle titulku/detail URL a medium podle `original_filename`, jinak ingest pres `MediaIngestionService`). Vsechny odkazy v homepage kompozici O nas, vcetne prekryvnych odkazu sest sluzeb a CTA, smeruji na `/#kontakt`.
- **Tomasova video karta O nas**: poster/fallback uz nepouziva genericke `black.png`. Cisty seed i Demo seed ingestuji `database/seeds/demo_assets/fallback tomáš.png` standardni `MediaIngestionService` pipeline a ukladaji vyslednou Media Library URL do bloku `about_cameraman_image`. Existujici weby opravuje append-only application-compatible migrace `101_replace_about_cameraman_video_fallback.sql`; chybejici zdroj nebo neplatna media cesta migraci viditelne zastavi.
- **Hero stranky Nase prace**: cisty seed i `DemoContentSeeder::seedHeroBackgrounds()` mapuji `/nase-prace` na `database/seeds/demo_assets/Snímek obrazovky 2026-10-06 132148.png`. Soubor se vzdy ingestuje standardni `MediaIngestionService` pipeline a blok `hero_bg` dostava Media Library URL, nikoli cestu primo do seed assetu. Existujici weby opravuje append-only application-compatible migrace `102_replace_work_listing_hero.sql` pres sdileny writer `replaceWorkListingHero()`; chybejici stranka, soubor nebo media cesta je viditelna chyba.
- **O nas pribehy z blogu**: `AboutStoriesData` vezme publikovane clanky z kanonickeho `BlogListingData`, seradi je stabilnim SHA-256 hashem `siteId:slug` a vybere tri. Stabilni pseudo-nahoda udrzuje shodny live a publish vystup. Pro male karty dohleda medium/thumbnail variantu featured media; `o-nas.phtml` vykresli skutecny titulek, perex, obrazek, alt a detailovou URL. Pri prazdnem datasetu se cela sekce nevykresli a nevznika fake fallback.
- **Homepage kontaktni FAQ v Golden Bash**: `.hp-faq` zarovnava grid obsah k zacatku a pouziva `max-content` radky, aby pevna vyska desktopove kontaktni kompozice nikdy neroztahovala mezery mezi otazkami. Kazda polozka sdili s `/kontakt` kompaktni bilou kartu, jemny border, radius a 52px minimalni vysku otazky. `.gb-contact-faq .hp-faq-question` se za behu obohati o klavesnicove ovladani, `aria-controls` a `aria-expanded`; odpoved dostane stabilni `id` a `aria-hidden`. Rozbaleni meri skutecny `scrollHeight`, animuje vysku i opacity a po dokonceni prejde na `height:auto`, aby zustalo odolne vuci zalamovani textu. `prefers-reduced-motion` animaci vypina bez ztraty funkcnosti.
- **Sdileny proces spoluprace Golden Bash**: jedinym zdrojem markup a textu je core partial `app/Views/partials/process-cooperation.phtml`. `SharedProcessRenderer` v live i publish ceste nahradi historicke `.hp-process`, `.kt-process`, `.gb-services-process` a `.gb-about-process`; na ostatnich routach rodiny Golden Bash vlozi stejnou sekci pred route CTA nebo konec `main`. Renderer je idempotentni a pro motivy mimo rodinu Golden Bash je no-op. Finalni `sanitizeFrontendHtml()` jej vola i pro specialni routy, ktere neprochazeji `PageComposer`. Kazda routa proto obsahuje prave jednu `.gb-shared-process`, nikdy route-specific kopii.
- **FAQ listing Golden Bash**: svetla route vynucuje tmavy titulek, otazky i odpovedi, ma vlastni editorialni intro a cislo kazde otazky. Filtracni chipy se nevykresluji, protoze seznam nema klientskou filtracni logiku. Styly jsou scopeovane na `data-cms-page-key="faq"`, aby neprepisovaly kontaktni accordion.
- **Firemni ICO**: append-only migrace 039 pridava `sites.company_registration_id`. Administrace prijima pouze osm cislic; `KontaktPageData` vytvori firemni kontaktni kartu jen pri skutecne vyplnene hodnote. Kanonicky seed a opravna migrace 087 nastavuji schvalene ICO `29719194`; nejde o runtime fallback.
- **Golden Bash motion a hero crop**: homepage loader pri dokonceni pouze rychle snizi opacity vrstvy a nemeni jas ani opacity webu pod ni, cimz se odstranuje bliknuti. Route hero obrazky pouzivaji horni `object-position`/`background-position`, aby kratky orez prioritne zachoval hlavy a horni cast portretu.
- **Podminky uziti Golden Bash**: generic DB stranka pouziva theme view `views/pages/podminky-uziti.phtml`. View nemeni ani neduplikuje obsah bloku; z rendered fragmentu doplni pouze stabilni kotvy, poradi kapitol a server-renderovany obsah. Chybejici `.legal-card` je viditelna runtime chyba. Route-scoped CSS prepisuje historicky tmavy textovy kontrakt seed sablony na citelne svetle karty, desktopovou rychlou orientaci a kompaktni mobilni seznam.
- **Genericke CMS stranky a homepage**: tecou pres `archetypes/page.phtml`, ktery obaluje legacy `PageRenderer::render()` fragment (`templates.html_source` + `content_blocks`) byte-for-byte. Data-block a inline-editor mechanika je nezmenena. Homepage sekce (services, portfolio, pricing, blog, faq, reviews, contact) se doplnuji autofillem (`HomepageContentService::applyFallbacks`) pres `partials/home-*.phtml` -- data, ne konkatenovane HTML. Autofill `reviews_cta` zachova interni odkaz na `/hodnoceni` a volitelne prida bezpecny externi odkaz z kanonickeho `contact_info.reviews_url`; pri `autofill_disabled` se rucne spravovany blok neprepise.

### Bezpecnostni kontrakt view vrstvy

- Escaping default (`e()` = `htmlspecialchars ENT_QUOTES|ENT_SUBSTITUTE, UTF-8`); `raw()` jen pro duveryhodne DB HTML bloky, ktere dal prochazi `sanitizeFrontendHtml`.
- Zadne SQL ve views ani ViewModelech neprochazi mimo Repositories (prepared statements).
- Formularove CSRF tokeny prichazeji do view jako data (generovany presne jednou v controlleru/service, nikdy ve view/ViewModelu); honeypot a throttling zustavaji v POST handleru.

## Layout, branding a hero media

Layout je ulozen v `sites.layout_html` a pracuje s placeholdery:

- `{nav}` -> `layout_nav_home` nebo `layout_nav_sub`
- `{page_content}` -> vyrendrovany obsah stranky
- `{site_name}` -> `sites.name`

Tabulka `sites` dale drzi:

- `logo_url`, `favicon_*`, `apple_touch_icon_url`
- `default_og_image_id`, `default_instagram_image_id`, `default_twitter_image_id` (FK do `media`, `ON DELETE SET NULL`)
- `default_hero_video_url`
- `default_hero_poster_url`
- `video_overlay_opacity`

Verejny web je **jen ve svetlem rezimu**. `MarkupNormalizer::injectLightOnlyColorScheme()` vlozi za `<meta charset>`
`<meta name="color-scheme" content="light only">` + `<meta name="supported-color-schemes" content="light">` - centralne,
ne v `Templates/*/layout.html`: live i publish ho dostanou pres `injectSharedFrontendAssets()`, detail prace
(`PortfolioPublishService`) a publikovana `404.html` (`generate404()`) maji vlastni `<head>` a volaji ho primo.
`critical-above-fold.css` zacina `html{color-scheme:light}:root{color-scheme:light only}`. Zadne
`prefers-color-scheme: dark` pravidlo ve verejnych CSS/JS nepridavat; hlida `tests/LightOnlyColorSchemeTest.php`.
Admin (`/admin`) ma vlastni vzhled a pravidlo se ho netyka.

Stranka muze mit vlastni `hero_video`, `hero_video_tablet` a `hero_video_mobile`. Pokud nema zadne, frontend pouzije vychozi sadu ze `sites` (kazda sirka zvlast, prazdne pole = zadne video; podrobnosti v sekci o pastech). Hlavni a Demo seed nastavuji `default_hero_poster_url` na nahranou fotografii `svatba-nevesta-zenich-polibek-kamenna-zed.webp`; homepage default video pouziva Vimeo `1229273025` pro desktop i tablet a Vimeo `1228952734` do 700 px; tabletova varianta (`default_hero_video_url_tablet`) je zamerne prazdna (NULL), takze tablet pada na desktopove video (seed, Demo seed, handler migrace 087 i migrace 100). Priorita je strankove video, homepage default video, strankova foto, site default poster a nakonec site default video.

## Publish pipeline

Publish vrstva dela vic nez jen ulozeni textu:

- `PublishService::publishPage()` renderuje stranku pres stejny `PageComposer` (mod publish) a stejne archetypy/ViewModely jako live, takze live a publish sdileji jeden zdroj pravdy (byte-ekvivalentni vystup hlida golden runner)
- regeneruje hashovane CSS/JS do `public/assets/generated/`
- cisti stare generovane assety
- generuje `sitemap.xml`, `robots.txt`, `llms.txt` a aktivni `.well-known/security.txt`; sitemap sklada publikovane stranky, vestavene routy, `blog_posts` i `portfolio_entries` a kanonicke cesty pred XML deduplikuje

`PortfolioPublishService` orchestruje staticky export portfolia: `publishEntry()` zapise per-entry detail do `storage/published/nase-prace/<slug>/`, doplni pres `SeoRenderer` absolutni obsahovy OG obrazek a globalni Twitter obrazek a pri media sanitaci oba tagy chrani tokeny. `regenerateListing()` regeneruje `/nase-prace` listing delegaci na `PublishService::publishPage()` (tedy stejny themed vystup jako pri publishi stranky -- zadny druhy zapisovac). Homepage `portfolio-tiles` blok se regeneruje autofillem pri kazdem renderu.

`FrontendAssetManager` minifikuje layout CSS/JS pres `AssetMinifier` a zapisuje hashovane soubory. `public/assets/generated/` neni verzovany v gitu.

Shared theme komponenty z `ThemeTemplateRegistry::getSharedComponentCss()` (`app/Views/assets/shared-components.css`) drzi spolecne styly pro automaticky renderovane DB bloky. Generator obsahu nema vkladat velke inline `<style>` bloky do HTML obsahu, protoze by se tim michala data a prezentace.

## Security.txt

RFC 9116 konfigurace je DB-driven a neni soucasti sablony. `SecurityTxtRepository` nacita jeden config webu a serazene kontakty, `SecurityTxtValidator` provadi serverovou validaci, `SecurityTxtGenerator` sklada deterministicky UTF-8/LF dokument a `SecurityTxtService` koordinuje data s uzce zamerenou souborovou cache v `storage/cache/security_txt/`.

Verejny `SecurityTxtHttpResponder` obsluhuje GET a HEAD, conditional requesty pres ETag/Last-Modified a stavy 200/304/404. `/security.txt` presmeruje na `/.well-known/security.txt` jen kdyz je konfigurace zapnuta, platna a neexpirovana; jinak obe URL vraceji 404. Vystup je vzdy `text/plain; charset=utf-8` a neprochazi HTML layoutem.

Administrace `/admin/security-txt` vyzaduje roli admin a CSRF. Po ulozeni se invaliduje cache, synchronizuje staticky publish soubor a obnovi `.htaccess`. Chybejici migrace se nezakryva defaultem: repository vrati `schema_available=false` a verejny endpoint zustane vypnuty.

## Inline editor

Prihlaseny uzivatel vidi admin bar primo na frontendu:

- blokove editace pres `data-block="key"`
- AJAX update na `/admin/inline/update`
- media upload na `/admin/inline/upload`

Inline editor umi i procedualni klice jako `faq:ID:question`, `blog:ID:title`, `portfolio:ID:title` nebo `contact:phone_1`; tyto hodnoty nejdou do `content_blocks`, ale do prislusnych tabulek.

## Tracking, consent a GTM hranice

### Consent model

- `sites.cookie_consent_enabled` zapina consent listu a kategorizaci souhlasu
- `necessary` je vzdy aktivni
- `analytics` a `marketing` se spousteji az po souhlasu
- pri vypnutem consent modu se analyticke loadery chovaji jako povolene podle globalni konfigurace
- hlavni seed explicitne nastavuje `cookie_consent_enabled=1` a migrace `064_enable_cookie_consent_summary.sql` opravuje existujici weby; administrace jej muze nasledne vedome vypnout
- bez ulozene volby se zobrazi pouze neblokujici kompaktni lista bez backdropu a blur; detailni modal s kategoriemi se otevre az explicitnim tlacitkem `Nastaveni`
- zavreni detailu bez volby vrati uzivatele na kompaktni listu, zatimco prijeti, odmitnuti nebo ulozeni nastaveni listu skryje

### Runtime tracking

`public/assets/tracking.js` emituje udalosti jako:

- `lead_form_submit`
- `lead_thank_you_page`
- `phone_click`
- `email_click`
- `cta_click`
- `social_click`
- `gallery_open`
- `video_play`
- `pricing_click`
- `scroll_90`

Zapnuti/vypnuti jednotlivych eventu se ridi `sites.tracking_events_json`.

### Vlastni mereni navstevnosti (zaloha k GA4)

Nezavisle na GA4/GTM a `tracking.js`; jejich chovani nemeni (ani v rezimu `direct-ga`).

- `SeoRenderer::renderConsentRuntime()` pripoji za consent runtime `<script ... defer data-cms-visit-analytics-js>` s hashovanym `public/assets/visit-analytics.js`, pokud `sites.visit_analytics_enabled` = 1 (vychozi; prepinac v Nastaveni, karta Cookies, consent a analytics).
- Skript posle pri nacteni stranky jeden beacon (`navigator.sendBeacon`, fallback `fetch` keepalive) s `{p, r, s, m, c, w, v, a}`. Preskoci `navigator.webdriver`, prihlaseneho editora (`#cms-adminbar`) a bfcache obnovu; prerender pocita az po zobrazeni.
- Bez vyslovneho souhlasu nic neuklada v prohlizeci (zadna cookie ani web storage) a pripadnou starou `cms_vid` smaze. Cookie `cms_vid` (32 hex, `Max-Age=34128000`, `SameSite=Lax`, `Secure` na https) vznika jen kdyz `cmsCookieConsent.enabled && canLoadAnalytics()` - pri vypnute liste `canLoadAnalytics()` vraci true bez volby uzivatele, proto je nutne i `enabled`. Udalost `cms-cookie-consent:updated` cookie nastavi/smaze bez dalsiho beaconu.
- `tryRecordVisitBeacon()` v `public/index.php`: jen POST (jinak 405), same-origin (`VisitAnalyticsService::isSameOrigin()`), tajny klic, throttle, telo max 4 KB JSON. Odpoved je vzdy bez tela, `204` (prijato i zahozeno), `429` pri zahlceni, `Cache-Control: no-store`.
- Same-origin je zamerne konzervativni: `Sec-Fetch-Site` jine nez `same-origin` (tedy i `same-site` = sesterska subdomena, napr. staging) odmita; Origin (bez nej Referer) musi presne odpovidat `Host` vcetne portu, vychozi porty se ignoruji na obou stranach (`Host: example.com:443` = `https://example.com`); `Origin: null` projde jen se `Sec-Fetch-Site: same-origin`; `www.example.com` a `example.com` jsou ruzne originy (stranky obsluhuje jen jeden z nich, druhy presmerovava), beacon z druhe varianty se nezapocita.
- Tajny klic: bez `VISIT_ANALYTICS_SALT` i `API_KEY` se nic neuklada ani nezapisuje throttle stav (verejny vychozi klic by umoznil z otisku zpetne dopocitat IPv4 hrubou silou).
- Throttle `visit_beacon` 60/min (zamek 300 s) na sit, ne na adresu: IPv4 /24, IPv6 /48 (IPv4-mapped IPv6 se bere jako IPv4). Stare soubory `storage/cache/throttle/visit_beacon-*.json` (posledni zapis > 1 h) maze zhruba 1 % beaconu (max 200 za beh) a `bin/purge-visits.php`; ostatni scope (login apod.) se nemeni.
- Zahozene beacony se loguji agregovane bez IP a payloadu: `[warning] [visit-analytics] Visit beacon dropped {"reason":..., "count_since_last_log":N}`, nejvys jednou za hodinu na duvod (`foreign_origin`, `missing_secret`, `throttled`, `invalid_payload`, `other`); citace v `storage/cache/throttle/visit_reject-<duvod>.json`.
- Cesta `p` se normalizuje, ne zahazuje: query/fragment se orizne, znaky mimo RFC 3986 pchar (a osamele `%`) se percent-enkoduji, max 255 B bez rozdeleni `%XX`; ne-absolutni cesta = `/`. Zahazuje se jen `/admin` a `/admin/...` (i enkodovane), `/administrativa` se meri.
- Logika (validace, hash, bot/zarizeni, sanitizace) je v `App\Services\VisitAnalyticsService`, SQL v `App\Repositories\VisitAnalyticsRepository`.
- `visitor_hash` = prvnich 32 hex z `HMAC-SHA256(IP|UA|den, VISIT_ANALYTICS_SALT || API_KEY)`. Den je soucasti vstupu, proto se otisk denne meni a navstevy nejdou spojit mezi dny. IP adresa se do `site_visits` neuklada. Boti (UA vzor nebo prazdny UA) se ukladaji s `is_bot=1` a v prehledu se pocitaji zvlast.
- Pozor: obecny traffic log (`Runtime::startRequest()`/`finishRequest()` -> `storage/logs/traffic-*.log`) zapisuje u kazdeho requestu vcetne `POST /api/visit` IP, User-Agent a Referer. „IP se neuklada“ tedy plati jen pro `site_visits`; zasady a retence logu jsou otevrene v BACKLOG.md (bod 5).
- Za CDN / reverse proxy (napr. Cloudflare) je `REMOTE_ADDR` adresa proxy: bez seznamu duveryhodnych proxy (a cteni `CF-Connecting-IP` / `X-Forwarded-For` jen od nich) by vsichni navstevnici sdileli jeden otisk i jeden throttle bucket. Aplikace takovy seznam zatim nema (`RequestThrottle::currentIp()` cte jen `REMOTE_ADDR`).
- Cerstva instalace jen ze `database/schema.sql` bez migrace 109: tabulka `sites` tam sloupec `visit_analytics_enabled` nema, `SiteRepository` neznamy sloupec tise preskoci, takze prepinac v Nastaveni se neulozi a mereni zustava zapnute (renderer bere chybejici sloupec jako 1). Po instalaci spustit migrace.
- Retence 14 celych mesicu + bezici mesic (cutoff `first day of -14 months`, tj. prvni den mesice; prosty `-14 months` pretekal na konci mesice): zhruba 1 % beaconu smaze starsi radky, rucne/cron `php bin/purge-visits.php` (smaze i stare throttle soubory).
- Prehled `/admin/analytics` (jen role admin): navstevy, unikatni (soucet dennich otisku), unikatni se souhlasem (`visitor_id`), podil se souhlasem, denni rada, top stranky, referrery, utm_source a zarizeni; obdobi `?days=7|30|90|365`.

### GTM hranice

`/admin/gtm` slouzi ke sprave GTM entit, inventare, plan/apply workflow a verzovani kontejneru. Neresi samotne emitovani runtime eventu na webu. Operacni workflow a env setup jsou samostatne v `GTM_ADMIN.md`.

## GTM vrstva

Hlavni sluzby:

- `GtmApiClient`
- `GtmConnectionService`
- `GtmInventoryService`
- `GtmEventCatalog`
- `GtmManifestCompiler`
- `GtmPlanService`
- `GtmApplyService`
- `GtmVersionService`

Hlavni tabulky:

- `gtm_connections`
- `gtm_event_definitions`
- `gtm_managed_entities`
- `gtm_sync_runs`
- `gtm_sync_operations`
- `gtm_container_versions`

`017_add_gtm_inventory_snapshot.sql` pridava inventory snapshot vrstvu, takze plan/apply necte live GTM list endpointy pri kazdem behu.

## Databazove schema

Schema je rizene append-only SQL migracemi v `database/migrations/`. Stav aplikovanych migraci drzi `schema_migrations`.

### Navigace, reference a lokalni SEO

Verejna i staticky publikovana navigace prochazi
`App\Services\Render\NavigationNormalizer`. Normalizer idempotentne vlozi odkaz
`/hodnoceni` pred kontaktni CTA, takze odkaz Reference zustane dostupny i u
starsich navigacnich fragmentu ulozenych v databazi.

Google Business URL se neuklada do sablony. Je soucasti `contact_info.reviews_url`
a spravuje se v administraci kontaktu. Stranka hodnoceni odkaz vykresli jen
tehdy, kdyz je skutecne vyplneny.

`SeoRenderer` obohacuje pouze JSON-LD uzly typu `LocalBusiness` o overena
kontaktni data z `contact_info`: telefon, e-mail, `PostalAddress` a platne
socialni profily v `sameAs`. Chybejici hodnoty se nedoplnuji smyslenym
fallbackem.

Pri plnem publikovani se do `sitemap.xml` zaradi jen publikovane CMS stranky s
`robots_index = 1`; blogove clanky a realizace se pridavaji vlastnimi
publikovacimi zdroji. Apache konfigurace nejdrive kanonizuje `www` a HTTP na
HTTPS variantu bez `www`, ale lokalni hosty `localhost` a `127.0.0.1` z
protokoloveho redirectu vynechava.

Post-deploy kontrakt hlida `bin/seo-smoke.php` pres `SeoSmokeChecker`. Povinne
routy i vsechny URL ze sitemap musi vratit 200, prave jeden neprázdny title,
description a H1, explicitni `index`, self-canonical, zakladni OG/Twitter tagy,
tracking runtime a parsovatelne JSON-LD. Checker soucasne overi duplicity a
uplnost sitemap vuci povinnym routam a stav vsech internich HTML odkazu. Kazdy
nalez nebo transportni chyba vraci nenulovy exit code. Na Windows pouziva
nativni CA store; vlastni CA bundle lze dodat explicitne, nikdy se nevypina TLS
peer ani hostname verifikace.

Vnitrni hero pozadi se seeduji ze zdrojovych fotografii v
`database/seeds/demo_assets` a vzdy prochazeji `MediaIngestionService`, ktery
registruje original a vytvari varianty `webp`, `thumb`, `medium` a `og`.
Aktualni cream sada mapuje samostatna media na `faq`, `cenik`, `kontakt`,
`blog`, `sluzby`, `o-nas` a `hodnoceni`; `DemoContentSeeder::seedHeroBackgrounds()`
a zakladni `seed.php` musi tuto mapu drzet shodnou. `SluzbyPageData` dostava
strankovy `hero_bg` explicitne z live i publish vetve a pro hero pouzije jeho
medium variantu, nikdy obrazek prvni service karty. Chybejici zdroj nebo page
hero se nesmi skryte nahrazovat jinym obrazkem.

Stejny demo media pruchod registruje pet PNG favicon variant
(`android-chrome-*`, `apple-touch-icon`, `favicon-16x16`, `favicon-32x32`) a tri
PNG varianty loga `rb-logo-*`. Format ICO a soubor `site.webmanifest` zustavaji
staticke webove assety: konfigurace uploadu je nepovoluje a GD pro ne negarantuje
standardni rozmery a varianty. Seed je proto nesmi vydavat za zaznam Media Library.

Hlavni tabulky:

| Tabulka | Ucel |
| --- | --- |
| `users` | admin/editor ucty |
| `sites` | konfigurace webu, layout, tracking, brand |
| `templates` | typy CMS stranek |
| `pages` | CMS stranky |
| `content_blocks` | editovatelne bloky |
| `seo_settings` | SEO metadata |
| `media`, `media_variants` | uploady a varianty |
| `blog_posts`, `blog_media` | blog |
| `portfolio_entries`, `portfolio_media` | portfolio |
| `faq_items`, `service_items`, `contact_info`, `reviews` | specialni obsahove oblasti |

Recenze pro klientsky web maji jeden kanonicky zdroj
`App\Content\CustomerReviewDataset`: 27 referenci z webu a 12 petihvezdickovych
Google recenzi v pevnem poradi 1-39. Vsechny demo seedery tento dataset pouze
spotrebovavaji. Append-only migrace `025_replace_placeholder_reviews.sql` smaze
predchozi placeholder radky a pro kazdy existujici web vlozi presne tento
dataset; JSON je v SQL ulozen hexadecimalne, aby MariaDB nezmenila Unicode ani
JSON escape sekvence.
| `redirects` | URL presmerovani |
| `publish_history` | historie publishu |
| `security_txt_configs`, `security_txt_contacts` | RFC 9116 konfigurace a serazene kontaktni URI |
| `site_visits` | vlastni mereni navstevnosti (migrace 109), bez IP, bez FK |

Klicove skupiny sloupcu v `sites`:

| Skupina | Sloupce |
| --- | --- |
| Zakladni | `name`, `domain`, `default_language` |
| Analytics | `google_analytics_id`, `google_tag_manager_id`, `clarity_id`, `meta_pixel_id` |
| Scripts | `head_scripts`, `body_scripts`, `analytics_*`, `marketing_*` |
| Layout | `layout_html`, `layout_css`, `layout_js`, `layout_nav_home`, `layout_nav_sub`, `active_theme_key` |
| Brand | `logo_url`, `favicon_ico_url`, `favicon_png_url`, `apple_touch_icon_url` |
| Hero | `default_hero_video_url`, `default_hero_poster_url`, `video_overlay_opacity` |
| Consent | `cookie_consent_enabled`, `cookie_consent_analytics_enabled`, `cookie_consent_marketing_enabled` |
| Tracking | `tracking_enabled`, `tracking_require_consent`, `tracking_debug`, `tracking_events_json` |

Tracking runtime je jednotne doplnen i do specialnich verejnych rout (FAQ,
kontakt, blog, portfolio a hodnoceni). Kanonicka slovni zasoba GA4 udalosti je:
`generate_lead`, `click_phone`, `click_email`, `select_package`,
`video_start`, `video_complete` a `view_google_reviews`.

SEO render doplnuje na kazdou verejnou stranku entitu `Organization`.
Portfolio detail s vyplnenym `video_url` navic generuje `VideoObject`.
`sanitizeFrontendHtml()` doplnuje obrazkum chybejici `loading`, `decoding` a
rozmery ze skutecneho lokalniho souboru; hero a logo zustavaji eager/high
priority, podlimitni obsah pouziva lazy loading.

`ImageVariantService::createWebP()` pred kodovanim kontroluje
`imageistruecolor()`. Paletove PNG/GIF (napriklad demo logo `weare2.png`)
kopiruje na pruhledny true-color canvas a teprve ten predava `imagewebp()`.
Bez tohoto kroku GD vypise `Palette image not supported by webp` a seed sice
muze pokracovat, ale derivat chybi. Warning se zamerne nepotlacuje; regresni
test jej prevadi na vyjimku a vyzaduje citelny WebP se stejnymi rozmery.

`SiteRepository::writableColumns()` drzi explicitni allowlist zapisovatelnych sloupcu a filtruje je pres `supportsColumn()`.

## Security pravidla

- `readfile()` vetve musi pouzivat `realpath()` a kontrolu prefixu
- admin routes maji volat `requireAuth()` nebo `requireAdmin()`
- POST formulare musi overovat CSRF token
- SQL jde pres prepared statements
- `MIGRATE_TOKEN` a `API_KEY` patri do `.env`

## Seed a migrace

### Seed

`database/seeds/seed.php` je genericky a idempotentni:

1. vycisti data bez dropu schema
2. vytvori admin ucet
3. vytvori vychozi web, layout a navigaci
4. vytvori zakladni sadu stranek
5. publikuje web

Zakladni seed je kanonicky vlastnik vsech stranek, ktere musi prezit Armagedon,
vcetne `/o-nas`. Samotna datova migrace k tomu nestaci: reset nejprve migrace
aplikuje, ale nasledny `seed.php` obsah tabulek vycisti a vytvori znovu. Nova
baseline stranka proto musi mit v seedu sablonu, stranku, obsahove bloky i SEO.
CLI seed drzi vlastni output buffer az do dokonceni publikace, aby prubezny log
nezpusobil `headers already sent` pri pozdejsi inicializaci session.

Klientske demo seed varianty patri do samostatnych souboru, napriklad `seed_photographer.php`.

Golden Bash ma pro `/o-nas` vlastni `views/pages/o-nas.phtml`, ale obsahovou
pravdu zachovava kanonicky CMS fragment a kontaktni data. Kompozice pridava
editorialni hero, role fotografa a kameramana, hodnoty, reference, proces,
duveryhodnostni odkazy, pribehy a CTA bez nedolozenych statistik. Hero a
odvozene fotograficke plochy pouzivaji skutecny blok `hero_bg`; chybejici
zdroj se nenahrazuje jinou fotografii. Footer vzdy cte `FooterViewData` a
zobrazuje brand, vsechny telefony, e-mail, adresu, navigaci, socialni site a
pravni odkazy. `PageComposer` predava `themeKey` do `FooterRenderer`, aby
preview a publikovany motiv pouzily stejnou tematickou paticku.

Verejna NAP adresa je `Mozartova 1113/65, 779 00 Olomouc, Nova Ulice`.
Zakladni seed, demo seed i opravna append-only migrace musi zustat shodne;
jinak by dalsi Armagedon vratil zastaraly kontakt.

Kontaktni tabulka `contact_info` je jedinym runtime zdrojem telefonu, e-mailu,
adresy a socialnich profilu pro homepage, kontakt, paticku a `LocalBusiness`
JSON-LD. Demo SEO generator nesmi mit vlastni telefon ani adresu a nevklada
nedolozene `priceRange`. Seedovany longform obsah neuklada shodny perex znovu
jako prvni odstavec a interni odkazy na sluzbu, cenik nebo kontakt jsou
explicitni soucasti dat konkretniho clanku ci realizace.

### Deterministicky referencni reset

Admin `Armagedon` i lokalni prikaz `php bin/reset-demo-local.php --confirm-local` pouzivaji `SetupEnvironmentService`. Poradi je pevne:

1. smazat `storage/media`, `storage/published`, `storage/cache`, `public/assets/generated` a `storage/active-theme-key.txt`
2. shodit vsechny tabulky aktualni databaze
3. aplikovat serazene append-only schema a bezne SQL migrace; application-compatible migrace 048, 050, 051, 052 a 054 zatim ponechat nezapsane jako odlozene; obsahovou migraci 055 pri cistem resetu zapsat pres bezvysledkovy SQL marker, protoze aktualni Demo seed je jeji kanonicky vlastnik
4. nacist vyhradne zakladni `database/seeds/seed.php`, aby existoval web a jeho zakladni data
5. spustit odlozene migrace pres `ApplicationCompatibleMigrationService` a kazdou zapsat do `schema_migrations` az po uspesnem PHP handleru
6. pri Demo obsahu overit a aktivovat vychozi `Golden Bash Diagonal` (`golden-bash-diagonal`) v DB i stavovem souboru
7. publikovat a vytvorit kanonicky fingerprint

Historicka migrace 048 obsahuje `CREATE TEMPORARY TABLE`, pro ktere produkcni Webzdarma ucet nema privilege. Proto ji Armagedon nesmi spustit jako SQL ani ji predcasne oznacit za hotovou. Po seedu zavola sdileny handler `restoreRealPricingContent()`; stejnou politiku pouziva `migrate-util.php`. Neznama kompatibilni migrace, chybejici web nebo chyba handleru jsou viditelne chyby a marker se nezapise.

Migrace `055_apply_content_seo_remediation.sql` ma dva explicitni rezimy. Cisty Armagedon provede pouze `SET @content_seo_remediation_marker = 1`, zapise marker a nasledny aktualni `DemoContentSeeder::run()` vytvori cely novy obsah. Produkcni maintenance nad existujicim Demo webem ji naopak dispatchuje do `restoreContentSeoRemediation()`. Handler pred prvnim zapisem overi potrebna realna media, vsechny DML zmeny provede atomicky, zakaze generovane obrazkove placeholdery, aktualizuje servisni a blogovy obsah, SEO metadata a portfolio alt texty a vytvori pet idempotentnich 301 redirectu. Chybejici media nebo galerie zpusobi rollback a viditelnou chybu.

Produkční maintenance záměrně nemá automatickou recovery ani reset větev. Aplikuje čekající migrace přes `ApplicationCompatibleMigrationService`, provede úplný publish a při chybě skončí fail-closed. Havarijní fázovaná obnova z 10. 8. 2026 byla dočasná: Webzdarma odmítl prodloužit 60sekundový PHP limit, proto commit `a1dd33e` rozdělil úplný Demo reset na requesty `base`, `core`, `portfolio`, `content` a `finalize`. Po zeleném Actions běhu `31429204277`, 9 obnovených galeriích a produkčním SEO smoke pro 41 URL byly incident generation, fázovaný endpoint i recovery policy odstraněny. Běžný deploy tak nemůže později skrytě smazat produkční obsah.

Acceptance resetu musi overit verejnou routu, ne pouze zaznam migrace. Pro
`/o-nas` se kontroluje HTTP 200, prave jeden H1, canonical, OG obrazek,
`AboutPage` JSON-LD, navigacni odkaz a pritomnost v sitemap.

Golden Bash view `/o-nas` dostava z verejneho rendereru kolekci `content_blocks`.
Stejnou kolekci musi view dostat take z `PublishService`; aktivace tematu publikuje
cely web a chybejici publish data by jinak zablokovala samotne prepnuti sablony.
Klice `about_photographer_image` a `about_cameraman_image` jsou povinne CMS media
kontrakty: pri chybejici hodnote render selze viditelne a nesaha po nesouvisejicim
hero obrazku. Idempotentni `database/seeds/import_about_team_media.php` registruje
oba portrety v Media Library a append-only migrace
`037_about_team_media_blocks.sql` je navaze na stranku. Opravna migrace 087 importuje
`aboutuszdenek.jpg` pres `MediaIngestionService`, prepise jim `about_photographer_image`
a navazujici migrace 088 nastavi `about_cameraman_video` na Vimeo `1228181473`.
Obe karty tymu maji stejny media kontrakt: `about_<osoba>_image` (image, admin picker
"Vybrat z medii") a volitelny `about_<osoba>_video` (YouTube/Vimeo URL, migrace 068).
Prazdne video = puvodni portret beze zmeny HTML; vyplnene = facade s portretem jako
posterem a autoplay podle nastaveni webu. `PageController::ABOUT_TEAM_VIDEO_BLOCKS`
pri otevreni/ulozeni stranky doplni chybejici video radek prazdny, existujici nemeni.

`DemoStateFingerprintService` porovnava theme state, layout, sablony, stranky, bloky, SEO, demo entity, vazby medii a hash originalnich souboru. Pred hashem normalizuje `APP_URL` a nahodne storage cesty medii a vynechava publikacni casy. Rozdil hashe je chyba datove parity a nesmi byt nahrazen vizualnim odhadem.

### Migrace

- stare migrace se neupravuji ani nemazou
- kazda schema nebo datova zmena jde pres novou migraci
- cislovani musi navazovat na nejvyssi existujici migraci
- migrace maji byt idempotentni, kdyz je to technicky mozne
- MySQL/MariaDB DDL se v runneru neobaluje falesnou transakci, protoze DDL provadi implicitni commit; zaznam do `schema_migrations` vznikne az po uspesnem SQL
- migrace vyzadujici application-compatible PHP handler maji jediny registr v `ApplicationCompatibleMigrationService`; bezne handlery Armagedon aplikuje po zakladnim seedu, zatimco migrace oznacena `requiresExistingDemoContent()` pri cistem resetu pouzije vlastni SQL marker a aktualni data vytvori az navazujici Demo seed; maintenance runner proti existujicimu webu vzdy spusti plny handler

### Obsahovy SEO kontrakt

- titulky, H1, popisy, servisni texty, blogove clanky a portfolio metadata vlastni `DemoContentSeeder`, `WeddingBlogDemoContentImporter` a `PortfolioDemoContentImporter`; sablona smi vykreslit pouze DB hodnotu
- `SeoRenderer` zamerne nevykresluje zastaraly `meta name="keywords"`; interni tematicka pole mohou v DB zustat jako redakcni podklad
- detail blogu a portfolia nedostava globalni `SharedProcessRenderer`; oba archetypy maji kratke kontextove CTA, aby se na kazde URL neopakoval stejny tematicky text
- pet zkracenych svatebnich slugu je kanonickych v Markdown zdrojich, DB i sitemap; `WeddingBlogDemoContentImporter::migrateLegacySlugs()` zachova stare cesty v tabulce `redirects` se stavem 301
- dynamicky frontend cte tabulku `redirects` pres `RedirectRepository::findByPath()` a staticky publish generuje stejna pravidla do `.htaccess`; interni cil musi zacinat `/`, nesmi tvorit smycku a zachovava puvodni query string
- homepage nesmi zobrazovat ceny; cenove informace vlastni pouze `/cenik`

## Deploy

Podrobný produkční postup a incident s omylem zavedeným povinným GitHub `MIGRATE_TOKEN` jsou v `DEPLOYMENT.md`.

- `master` -> produkce
- `dev` -> staging
- GitHub Actions buildi Composer artifact bez dev zavislosti a nasazuje pres FTPS
- `.env` se nedeployuje
- `storage/media`, `storage/published`, `storage/active-theme-key.txt` a `public/assets/generated` jsou runtime state ciloveho prostredi a z rsync artefaktu i FTPS synchronizace se explicitne vylucuji
- workflow vytvori pouze prazdne potrebne runtime adresare; jejich obsah nesmi Git deploy prepsat ani smazat

## Zdroje pravdy

Pri rozporu plati toto poradi:

1. aktualni kod
2. `.env` a runtime konfigurace
3. tato dokumentace a specializovane reference (`API.md`, `GTM_ADMIN.md`)

## Sdilene sekce a portfolio typy

Opakovany marketingovy obsah patri do `shared_sections` (`site_id`, stabilni `section_key`, `title`, validovany `content_json`, `is_active`). Migrace 065 zaklada centralni `process_cooperation`; `SharedSectionRepository` dekoduje JSON a pri neplatnych datech viditelne selze. `SharedProcessRenderer` nahradi historicke route-specific kopie jedinym DB-driven partialem. Jednorazovy obsah stranky nadale patri do `pages` a `content_blocks`.

`portfolio_entries.content_type` rozlisuje `photo` a `video`. Live i publish listing tvori samostatnou Fotogalerii a Videogalerii a radi `content_type ASC, sort_order ASC, id ASC`. Migrace odvozuje typ existujicich zaznamu z `video_url`, takze obsah neztraci.

Golden Bash listing zobrazuje obe skupiny jako jeden pristupny tabovy celek. Server vyrenderuje dva odkazy s `role="tab"`, vazbou `aria-controls` a dva existujici obsahove bloky s `role="tabpanel"`; bez JavaScriptu proto zustane dostupny cely obsah. Theme JS po inicializaci vybere panel podle URL hashe, ostatni skryje, prepina je 220ms opacity/translate animaci, aktualizuje `aria-selected`, roving `tabindex` a hash pres `history.replaceState`. Klavesnice podporuje sipky, Home a End. Chybejici panel je poruseni kontraktu a inicializace se nesmi tvarit jako uspesna.

Homepage rodiny Golden Bash (`golden-bash`, `golden-bash-diagonal`) pouziva stejnou databazovou kolekci bez petipolozkoveho zdrojoveho rezu. `HomepageContentService::refreshPortfolioTiles()` pri povolenem autofillu vzdy znovu sestavi `portfolio-tiles` z aktualnich publikovanych galerii, aby existujici web nezustal na starem HTML snapshotu; explicitni `autofill_disabled` zachovava editorsky vlastneny obsah. Ostatni temata zustavaji omezena konstantou `HOMEPAGE_PORTFOLIO_BATCH_SIZE = 5`. Theme JS po inicializaci ponecha prvnich pet karet ve dvouradkove mozaice a dalsi odkryva po davkach peti tlacitkem `Dalsi`. Kazdy klik animuje vysku gridu a vstup karet, aktualizuje live status a po posledni davce tlacitko skryje a presune fokus na prvni nove odkrytou galerii. Bez JavaScriptu zustavaji vsechny server-renderovane galerie dostupne.

Golden Bash header a footer pouzivaji jediny branding z `sites.name`; theme nevklada route-specific klientsky slogan. Prime hero video se renderuje nativnim `<video autoplay muted playsinline loop>`, YouTube/Vimeo zustavaji consent-aware embed a poster se pouziva jen jako fallback/facade.

Specialni live route handlery (`Sluzby`, portfolio listing/detail, FAQ, Kontakt a blog listing/detail) obchazeji `PageComposer::composeLive()`, ale pred sanitizaci musi explicitne volat stejny `injectSiteLogo()` jako obecny DB render. Jinak v theme layoutu zustane placeholder `gb-brand__mark` a `<strong>` misto databazoveho `site-logo-img` + `site-logo-text`, coz meni logo i font-weight podle routy.

Popisky portfolio lightboxu normalizuje `PortfolioCaptionFormatter`. HTML popisu z CMS zachova hranice odstavcu jako nove radky v plain textu, zatimco samostatny odstavec `<strong>Autor: ...</strong>` prevede do oddeleneho `gallery_author`. Homepage i listing portfolia predavaji popis a autora sdilenemu Golden Bash lightboxu zvlast, aby `strip_tags()` neslepoval sousedni odstavce.

Hero obsahovy container je ve vyssi vizualni vrstve nez video, ale jeho neinteraktivni plocha musi mit `pointer-events: none`; odkazy, tlacitka a formularove prvky uvnitr explicitne pouzivaji `pointer-events: auto`. Jinak container zachyti klik urceny consent-aware play tlacitku video fasady a iframe se nikdy nevytvori.

## Archiv pošty

Kontaktní POST nejdříve validuje CSRF, honeypot, limit a vstup. `MailArchiveService` pak uloží obsah do `mail_archive` (migrace 104) a až poté zavolá `SmtpMailer` (PHPMailer 7.1.1, povinná autentizace a SSL/TLS nebo STARTTLS). PHP `mail()` se nepoužívá. Stav `accepted` znamená předání transportu, ne doručení; `failed` ponechává obsah pro administrátora, `stored` značí nepotvrzený pokus. Při chybě INSERT se mail neodesílá. `MessagesController` zpřístupňuje stránkovaný archiv na `/admin/messages` pouze roli admin.

Obnova hesla používá stejnou službu, ale ukládá pouze metadata bez těla a tajného tokenu. Automatický retry není zaveden. Nastavení, limity a ověřovací příkazy jsou v `DEPLOYMENT.md`.

SMTP konfigurace je v serverovém `.env` (`SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `MAIL_FROM`). From odpovídá účtu, příjemce pochází z CMS. Výjimky se převádějí na bezpečné kódy bez uchování SMTP textu nebo tajných údajů.

## Vlastní heslo a mailový přehled administrace

`AccountController` používá Auth, CSRF a RequestThrottle; identitu bere pouze ze session. `AccountPasswordService` transakčně zamkne users řádek, ověří současný bcrypt hash, uloží nové heslo a odstraní reset tokeny účtu. Selhání odstranění tokenů vrací celou transakci zpět. Controller po úspěchu odhlašuje aktuální relaci, stávající Auth fingerprint odmítne ostatní staré relace při příštím požadavku.

`MessagesController` vyžaduje roli admin a deleguje SQL na `MailArchiveRepository`. Parametrizované filtry mají allowlist status/kind, stránka je kladné číslo nejvýše 1 000 000, limit je 50. Počty za celý archiv jsou oddělené od filtrovaného výsledku. Zobrazení HTML escapuje a nesmí obsahovat resetovací tokeny.

## Centrální recovery příjemce

`PasswordResetService` používá users.email jen jako login. Pro existující účet načte form_email z contact_info prvního sites řádku, vyžaduje platnou explicitně uloženou adresu a pošle na ni odkaz vázaný na dané user_id. Neexistuje fallback na login ani veřejný kontaktní e-mail.

`ContactRepository::upsert` má chráněné pole form_email a opt-in `$allowRecoveryRecipientChange=false`. Běžné aktualizace chráněný sloupec v SQL vynechávají; změněná hodnota bez oprávnění selže. Controller gate `mayChangeRecoveryRecipient` odmítne editora HTTP403 před zápisem. Settings a trusted seed mají explicitní opt-in. Tím editor veřejných kontaktů nemůže přesměrovat obnovu administrátorského účtu.

## Dědění globálních social obrázků

Po datové migraci 105 mají všechny dosavadní stránky NULL `og_image_id`/`twitter_image_id`. Obvyklý renderer i publish používají globální Nastavení. `injectPageHeadMetadata` dodržuje prioritu explicitní SEO stránky > globální platformní obrázek > obsahová fotografie při absenci globálu. Twitter bez vlastního globálu dědí vybraný OG obrázek. `PortfolioPublishService` také upřednostňuje globální OG a pro globální obrázky používá společný MediaUrlResolver, aby export neodkazoval na interní storage cestu.

Aktuální demo/base seedy vkládají page sharing image ID jako NULL. Schema.org explicitní obrázek je jiná vlastnost a migrace jej nemění; automatický Organization image nadále sleduje vybraný OG obrázek.

## Hero cover a nacitani brand fontu

Golden Bash Diagonal pouziva height-fit Vimeo iframe ze skutecnych metadat getVideoWidth/getVideoHeight: vyska=heroHeight, sirka=heroHeight*(videoWidth/videoHeight), pravý okraj0 bez transformace. Sirsi video presahuje vlevo, uzsi muze nechat vlevo prazdne misto. frontend-runtime.initHeroVideoCover prijima zpravy jen z https://player.vimeo.com a konkretniho contentWindow; ResizeObserver prepocita CSS promenne --hero-video-cover-width/height. Provizorni pomer16:9 je vyznamove oznacen, pri nedostupnych metadatech po15s vznikne console warning a data-video-cover-error. Neni to potvrzeni prehravani. Poster se po vlozeni iframe skryje, aby neprosvital vedle uzsiho videa; vadny iframe zustava viditelny. Mobil<=700, tablet701–1050, desktop>=1051. Gradient30–58% zustava, starsi horizontalni offset je nahrazen presnym pravym zarovnanim.

Font CSS pouziva swap a same-origin preloady. Kurziva Cormorant Garamond v4.001 je kompletni Regular400, nikoli stary Light outline deklarovany jako400. Novy immutable filename a licence/provenance jsou v public/assets/fonts/README.md; reprodukce scripts/build_cormorant_italic.py vyzaduje presny upstream SHA256. Runtime nema Python/FontTools zavislost. Pozdni swap muze zpusobit layout shift, ktery je treba overovat pri pomale siti.

Burger animace obou Golden Bash motivu plati do1050px a zachovava aria-expanded, Escape/focus, transparentni hover a reduced-motion. Overeni a otevrene brany: QA-BACKLOG-2026-10-16.md; konecna QA videa vyzaduje skutecne prehravani, metadata fixture dokazuje pouze geometrii.
# Galerie v editaci stránky Naše práce

Editace stránky s URL cestou `nase-prace` obsahuje záložku „Foto a video galerie“.
PageController načítá portfolio jen pro web této stránky. Přehled rozlišuje
fotografie a videa podle `content_type`, nikoli podle přítomnosti video odkazu.
Přidání a úprava otevírají existující editor portfolia v nové kartě, takže
rozepsané bloky stránky zůstávají zachované. Odkaz přidání předvolí typ galerie;
ukládání, CSRF, média, pořadí a publikování zůstávají v PortfolioController.
Panel je mimo formulář stránky a sám neprovádí zápisy. Mazání je dostupné
přes odkaz Kompletní správa galerií. Po uložení galerie obnovte přehled stránky.
Ověření: `php tests/PagePortfolioGalleryTest.php`. Nová migrace není potřeba.
# Nepovinná kopie kontaktních zpráv

Migrace107 přidává nullable `contact_info.form_email_cc` a `mail_archive.cc_recipient`;
append-only migrace108 je rozšiřuje z `VARCHAR(255)` na `TEXT`. `EmailRecipientList`
je společný parser pro čárky a bílé znaky včetně nových řádků, validuje každou adresu,
odstraňuje duplicity bez ohledu na velikost písmen a ukládá kanonický seznam oddělený
čárkou a mezerou. SettingsController validuje celý nepovinný seznam před zápisy.
ContactRepository chrání hlavního příjemce i CC proti změnám mimo admin cestu,
zachovává vynechané pole při jiných úpravách kontaktu a prázdnou hodnotu ukládá jako
NULL.
Kontaktní POST načte uložené CC a předá je šestým argumentem MailArchiveService::send
(pátý zůstává kompatibilní headers). Archiv uloží CC a tělo před odesláním.
SmtpMailer přidává každé CC přes PHPMailer::addCC a každou zprávu začíná
clearAllRecipients. CC shodné s hlavním příjemcem se potlačí. Jediná neplatná adresa
odmítne celý seznam; MailArchiveService přitom zachová kontaktní zprávu i zadaný CC
text a uloží `invalid_cc_recipient`. Password reset kopii nepoužívá a archivní služba
explicitní CC u resetu odmítá. SMTP přijetí stále neznamená prokázané doručení.
