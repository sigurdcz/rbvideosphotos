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
| `admin/` | Admin router, PHP view sablony a assety |
| `public/` | Verejny front controller, assety a tracking runtime |
| `Templates/` | Predpripravena vizualni temata |
| `database/` | Migrace, schema a seedy |
| `storage/` | Media, logy a publikovane vystupy |

## Request flow

### Admin

```text
request -> admin/index.php -> Core\Router -> Controller -> Repository/Service -> View
```

### Verejny web

`public/index.php` odbavuje requesty v tomto poradi:

```text
1. /.well-known/security.txt a /security.txt -> SecurityTxtHttpResponder
2. /api/v1/*            -> ApiController::dispatch()
3. /assets/*            -> readfile s realpath ochranou
4. /storage/media/*     -> readfile s realpath ochranou
5. legacy /blog/* nebo /nase-prace/* -> 301 redirect na prime slugy
6. /sluzby              -> tryServeServicesPage() z `service_items`
7. /nase-prace          -> tryServePortfolioPage() listing
8. /blog                -> tryServeBlogListingPage()
9. /faq                 -> tryServeFaqPage() z `faq_items`
10. /kontakt            -> tryServeKontaktPage() z `contact_info`
11. POST /kontakt/odeslat -> CSRF, honeypot, throttling a `mail()`
12. CMS stranka z DB    -> tryServeDatabasePage()
13. Blog detail         -> tryServeBlogPostPage()
14. Portfolio detail    -> tryServePortfolioPage(..., true)
15. storage/published/* -> explicitni soubory podle pripony (napr. sitemap, robots, llms.txt a ikony)
16. 404
```

Security endpointy jsou zamerne pred `Auth::start()`: verejny strojovy soubor nesmi zakladat PHP session ani posilat session cookie.

## Obsahovy model

### CMS stranky

- `pages` drzi bezne CMS stranky navazane na `templates`
- `content_blocks` drzi editovatelne bloky podle `data-block="key"`
- specialni layouty `faq`, `kontakt`, `cenik`, `sluzby`, `blog` a `nase-prace` maji cast logiky mimo generic bloky
- specialni verejne stranky musi porad cist obsah z DB tabulek (`service_items`, `faq_items`, `contact_info`, `blog_posts`, `portfolio_entries`) a nesmi doplnovat obsahova data ze sablon
- specialni renderery predavaji hotovy DB-driven `pageContent` pres `applyThemeTemplate()`; plati to i pro `/sluzby`, aby aktivni tema vzdy dostalo stejny layout, navigaci, footer a theme assety jako ostatni verejne stranky

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

- `layout.html`
- `assets/theme.css`
- volitelne `assets/theme.js`
- `template.json`

`ThemeTemplateRegistry` nacte metadata tematu, aplikuje layout/soubory do DB a nastavuje `sites.active_theme_key`. Aplikace tematu prepisuje layout v databazi, neni to jen kosmeticky prepinac.

Temata nesmi byt zdrojem obsahovych dat. Layouty maji obsahovat jen strukturni placeholdery jako `{site_name}`, `{nav}` a `{page_content}` a tema CSS/JS ma resit pouze prezentaci. Texty, media, CTA obsah, kontakt, cenik, sluzby, blog, portfolio a SEO metadata maji pochazet z DB nebo z DB-driven renderovacich sluzeb.

## Layout, branding a hero media

Layout je ulozen v `sites.layout_html` a pracuje s placeholdery:

- `{nav}` -> `layout_nav_home` nebo `layout_nav_sub`
- `{page_content}` -> vyrendrovany obsah stranky
- `{site_name}` -> `sites.name`

Tabulka `sites` dale drzi:

- `logo_url`, `favicon_*`, `apple_touch_icon_url`
- `default_hero_video_url`
- `default_hero_poster_url`
- `video_overlay_opacity`

Stranka muze mit vlastni `hero_video` blok. Pokud chybi, frontend pouzije default hodnoty ze `sites`.

## Publish pipeline

Publish vrstva dela vic nez jen ulozeni textu:

- regeneruje hashovane CSS/JS do `public/assets/generated/`
- cisti stare generovane assety
- publikuje portfolio listing/detail do `storage/published/nase-prace`
- generuje `sitemap.xml`, `robots.txt`, `llms.txt` a aktivni `.well-known/security.txt`

`FrontendAssetManager` minifikuje layout CSS/JS pres `AssetMinifier` a zapisuje hashovane soubory. `public/assets/generated/` neni verzovany v gitu.

Shared theme komponenty z `ThemeTemplateRegistry::getSharedComponentCss()` drzi spolecne styly pro automaticky renderovane DB bloky. Generator obsahu nema vkladat velke inline `<style>` bloky do HTML obsahu, protoze by se tim michala data a prezentace.

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

- `sites.cookie_consent_enabled` zapina modal a kategorizaci souhlasu
- `necessary` je vzdy aktivni
- `analytics` a `marketing` se spousteji az po souhlasu
- pri vypnutem consent modu se analyticke loadery chovaji jako povolene podle globalni konfigurace

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
| `redirects` | URL presmerovani |
| `publish_history` | historie publishu |
| `security_txt_configs`, `security_txt_contacts` | RFC 9116 konfigurace a serazene kontaktni URI |

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

Klientske demo seed varianty patri do samostatnych souboru, napriklad `seed_photographer.php`.

### Migrace

- stare migrace se neupravuji ani nemazou
- kazda schema nebo datova zmena jde pres novou migraci
- cislovani musi navazovat na nejvyssi existujici migraci
- migrace maji byt idempotentni, kdyz je to technicky mozne
- MySQL/MariaDB DDL se v runneru neobaluje falesnou transakci, protoze DDL provadi implicitni commit; zaznam do `schema_migrations` vznikne az po uspesnem SQL

## Deploy

- `master` -> produkce
- `dev` -> staging
- GitHub Actions buildi Composer artifact bez dev zavislosti a nasazuje pres FTPS
- `.env` se nedeployuje
- `storage/media` se nasazuje jako soucast buildu

## Zdroje pravdy

Pri rozporu plati toto poradi:

1. aktualni kod
2. `.env` a runtime konfigurace
3. tato dokumentace a specializovane reference (`API.md`, `GTM_ADMIN.md`)
