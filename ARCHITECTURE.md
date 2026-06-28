# Architektura — SEO Editor CMS

Tento dokument popisuje aktuální technický stav a záměr projektu.

## Záměr projektu

SEO Editor je **univerzální CMS pro tvorbu webových stránek** s důrazem na SEO. Umožňuje:

- Vytvářet a spravovat vlastní substránky s volbou layoutu
- Provozovat dva paralelní blogové systémy (Články a Naše práce)
- Sdílet jednotný layout (navigace, patička, styly) napříč celým webem
- Editovat obsah inline přímo na frontendu
- Exportovat SEO metadata, sitemap, robots.txt a llms.txt
- Aplikovat předpřipravená vizuální témata z adresáře `Templates/`

---

## Stack

- PHP 8+
- MySQL / MariaDB
- PDO (bez ORM)
- Vanilla JS (bez frameworku)
- Composer autoload pro namespace `App\`

## GTM administrace

Administrace obsahuje samostatnou sekci `/admin/gtm` pro spravu Google Tag Manageru pres API. Cilem je pripravit a spravovat GTM entity z CMS bez rucni prace primo v rozhrani GTM.

### Co tato vrstva dela

- OAuth pripojeni Google uctu a ulozeni sifrovaneho refresh tokenu
- vyber GTM account, container a workspace
- nacitani inventare tagu, triggeru, promennych a verzi
- katalog predpripravenych eventu pro SEO editor
- plan/apply workflow pro vytvareni a update GTM entit
- vytvareni verzi a jejich samostatna publikace

### Dulezite sluzby

- `GtmApiClient` - wrapper nad GTM REST API v2
- `GtmConnectionService` - sprava OAuth spojeni, sifrovani tokenu a vyberu workspace
- `GtmInventoryService` - nacitani inventare z GTM
- `GtmEventCatalog` - katalog podporovanych eventu
- `GtmManifestCompiler` - preklad lokalni definice na GTM tag/trigger payload
- `GtmPlanService` / `GtmApplyService` - diff, plan a zapis do workspace
- `GtmVersionService` - vytvareni a publikace verzi

### GTM data tabulky

- `gtm_connections`
- `gtm_event_definitions`
- `gtm_managed_entities`
- `gtm_sync_runs`
- `gtm_sync_operations`
- `gtm_container_versions`

### Bezpecnostni pravidla

- refresh tokeny se ukladaji sifrovane
- chyby GTM API jsou explicitni a zapisuje se auditni stopa
- externi entity se nemeni bez potvrzeni
- publikace verze je oddelena od vytvoreni workspace verze

---

## Adresáře

```text
app/
  Core/           jádro aplikace (Router, Auth, Database, …)
  Controllers/    řadiče
  Repositories/   přístup k DB (PDO, připravené dotazy)
  Services/       business logika (PageRenderer, SeoRenderer, PublishService, …)
  Support/        pomocné třídy (AssetMinifier, FrontendAssetManager)
admin/
  index.php       admin router
  views/          PHP šablony adminu
  assets/         CSS a JS pro admin
public/
  index.php       front controller veřejného webu
  assets/         assety inline editoru a tracking.js
  assets/generated/  minifikované CSS/JS (generované za běhu, není v gitu)
Templates/
  <tema>/
    layout.html     HTML layout tématu
    assets/theme.css  styly tématu
    assets/theme.js   JS tématu (volitelné)
    template.json   metadata tématu (name, key, description)
database/
  migrations/     SQL migrace aplikované postupně přes schema_migrations
  seeds/          PHP seedy
storage/
  media/          nahraná média
  published/      staticky exportované soubory (sitemap, robots, llms.txt, portfolio HTML)
  logs/           logy
```

---

## Dva paralelní blogové systémy

Oba systémy jsou rovnocenné. Sdílejí layout, navigaci a styly — liší se pouze strukturou záznamu a layoutem detailu.

| Vlastnost | Blog (Články) | Naše práce (Portfolio) |
| --- | --- | --- |
| DB tabulka | `blog_posts` | `portfolio_entries` + `portfolio_media` |
| URL výpisu | `/blog` | `/nase-prace` |
| URL detailu | `/{slug}` (bez prefixu) | `/{slug}` (bez prefixu) |
| Pole záznamu | title, slug, excerpt, content, featured_image | title, slug, description, video_url, media[] |
| Admin sekce | Blog — články | Naše práce |
| Rendering výpisu | `tryServeBlogListingPage()` | `tryServePortfolioPage()` |
| Rendering detailu | `tryServeBlogPostPage()` | `tryServePortfolioPage()` |

### Pravidlo URL: žádný prefix

Slugy blogů i portfolia jsou přímé — **neobsahují** `/blog/` ani `/nase-prace/` prefix:

```text
✓ /priprava-na-svatebni-foceni     (blog post)
✓ /svatba-martin-helena            (portfolio entry)
✗ /blog/priprava-na-svatebni-foceni
✗ /nase-prace/svatba-martin-helena
```

Při přístupu na `/blog/something` nebo `/nase-prace/something` provede
`tryRedirectLegacyContentPath()` 301 redirect na `/{something}`.

---

## Substránky (CMS stránky)

Uživatel si může vytvářet vlastní substránky (ceník, FAQ, o nás, …). Při zakládání si vybere layout (**šablonu**). Šablony jsou předpřipraveny seedem.

### Předpřipravené šablony (seedované automaticky)

| Název šablony | URL | Poznámka |
| --- | --- | --- |
| Homepage | `/` | Neměnná, vždy existuje; obsahuje anchor sekce |
| Služby | `/sluzby` | Výčet služeb |
| Ceník | `/cenik` | Přehled cen |
| FAQ | `/faq` | Otázky a odpovědi |
| Kontakt | `/kontakt` | Kontaktní informace |
| Blog — přehled | `/blog` | Hero text; seznam článků renderuje PHP |
| Naše práce — přehled | `/nase-prace` | Hero text; grid portfolia renderuje PHP |
| Hodnocení | `/hodnoceni` | Recenze; seed: `database/seeds/seed_reviews_page.php` |

Nové substránky mohou používat libovolnou z dostupných šablon.

### Speciální layouty v administraci

Některé layouty mají v editoru stránky vlastní specializované UI místo generického seznamu bloků:

- `FAQ` spravuje hero bloky stránky a zároveň položky z tabulky `faq_items`
- `Kontakt` spravuje hero bloky stránky a zároveň strukturovaná pole z tabulky `contact_info`
- `Ceník` zobrazuje pevné karty jako samostatná pole
- `Služby` seskupuje obsah podle layoutu stránky

FAQ ani Kontakt nejsou v administraci samostatné sekce v sidebaru. Spravují se přes `Stránky` podle vybraného layoutu.

---

## Témata (Templates)

Vizuální témata jsou v adresáři `Templates/<klic>/`. Každé téma má vlastní layout, styly a volitelně JS. Registr témat zajišťuje `ThemeTemplateRegistry` — načte soubory tématu, přepíše layout a sablony webu v DB a nastaví `sites.active_theme_key`.

Dostupná témata:

| Klíč | Název |
| --- | --- |
| `jemna-bezova` | Jemná béžová |
| `soft-memories` | Soft Memories |
| `modern-atelier` | Modern Atelier |
| `minimal-dark` | Minimal Dark |
| `editorial-gold` | Editorial Gold |

Aplikace tématu je nevratná operace (přepíše layout v DB) — před aplikací se doporučuje záloha.

---

## Navigace — dvě varianty

| Pole v DB | Kde se použije | Chování odkazů |
| --- | --- | --- |
| `sites.layout_nav_home` | Homepage (`url_path = ''`) | Hash anchory (`#sluzby`, `#cenik`, …) |
| `sites.layout_nav_sub` | Ostatní stránky, blog, portfolio | Přímé URL (`/sluzby`, `/cenik`, …) |

Každá anchor sekce na homepage má vlastní CTA tlačítko odkazující na příslušnou substránku (`#sluzby` → tlačítko „Všechny služby" → `/sluzby`).

Pokud `layout_nav_home`/`layout_nav_sub` nejsou vyplněny, placeholder `{nav}` se tiše odstraní.

---

## Sdílený layout webu

Layout je uložen v `sites.layout_html` a obsahuje placeholder `{page_content}`, `{site_name}` a `{nav}`.

```text
{nav}             →  sites.layout_nav_home  (homepage)
                  →  sites.layout_nav_sub   (ostatní stránky)
{page_content}    →  vyrendrovaný obsah šablony (PageRenderer::render())
{site_name}       →  sites.name
```

Globální CSS je v `sites.layout_css`, JS v `sites.layout_js`.

### Globální brand a hero média

Tabulka `sites` zároveň drží globální brand assety a fallback média pro hero sekce:

- `logo_url` pro logo v hlavičce a patičce
- `favicon_ico_url`, `favicon_png_url`, `apple_touch_icon_url` pro ikony webu
- `default_hero_video_url` jako fallback video pro stránky bez vlastního hero videa; hodnota je URL z Vimeo nebo YouTube
- `default_hero_poster_url` jako poster/placeholder obrázek, který se zobrazí před načtením videa

Logo se servuje přes `<picture><source type="image/webp">` — `injectSiteLogo()` v `public/index.php` vyhledá WebP variantu z `media_variants` automaticky.

Každá stránka může mít vlastní blok `hero_video`. Do tohoto bloku se ukládá pouze URL z Vimeo nebo YouTube, ne uploadovaný video soubor. Pokud je blok prázdný, frontend použije globální `default_hero_video_url` ze Settings. Renderer při výstupu převede Vimeo/YouTube odkaz na embed iframe pozadí, aby s tím počítaly všechny šablony stejně.

### Cookie consent a analytika

Verejny frontend injektuje cookie consent pres `public/index.php` pri sanitizaci vystupniho HTML. Globalni prepinac je `sites.cookie_consent_enabled`.

Rozhodnuti uzivatele se uklada do `localStorage` pod klicem `cookie_consent_preferences` ve strukturach `necessary`, `analytics`, `marketing`, `timestamp` a `version`. Kategorie `necessary` je vzdy aktivni. Kategorie `analytics` a `marketing` jsou pri zapnute consent liste vypnute, dokud je uzivatel nepovoli.

`SeoRenderer` nesmi nacitat Google Analytics, Google Tag Manager ani kategorizovane analyticke/marketingove skripty pred souhlasem uzivatele. GA se spousti pouze po povoleni `analytics`. GTM kontejner (`sites.google_tag_manager_id`, snippet `gtm.js`) a skripty v polich `marketing_head_scripts` / `marketing_body_scripts` se spousti po povoleni `marketing`. Puvodni `head_scripts` a `body_scripts` jsou urcene jen pro technicky nezbytne skripty.

GTM container snippet injektuje `cookie-consent-runtime.js` funkci `loadGoogleTagManager()` az po marketing souhlasu (analogicky jako Meta Pixel). ID kontejneru se zadava v `Nastaveni → Google Tag Manager ID`. Pole je oddelene od admin sekce `/admin/gtm`, ktera jen spravuje konfiguraci uvnitr kontejneru.

Pokud je `cookie_consent_enabled = 0`, consent modal se nezobrazi a analyticke/marketingove loadery se chovaji jako povolene podle globalniho nastaveni webu.

---

## CSS a JS minifikace

`FrontendAssetManager` (`app/Support/FrontendAssetManager.php`) zapisuje layout CSS/JS jako hashované soubory do `public/assets/generated/`. Před zápisem obsah prochází přes `AssetMinifier` (`app/Support/AssetMinifier.php`) — regex minifikace bez externích závislostí.

```text
layout_css / layout_js
  → AssetMinifier::minify()
  → sha1 hash → prefix-HASH.css / prefix-HASH.js
  → public/assets/generated/prefix-HASH.css
  → <link href="/assets/generated/prefix-HASH.css" data-generated-page-css>
```

Při každém globálním publishování volá `PublishService` metodu `FrontendAssetManager::clearGeneratedAssets()` — adresář se před regenerací vyčistí, stale soubory nezůstávají.

Soubory v `public/assets/generated/` nejsou verzovány v gitu.

---

## Konverzní tracking

`public/assets/tracking.js` trackuje konverze bez nutnosti stránky `/dekujeme`:

| Událost | Spouštěč | GA4 event | Meta Pixel |
| --- | --- | --- | --- |
| `lead_form_submit` | submit na kontaktním formuláři | `lead_form_submit` + `generate_lead` | `Lead` |
| `lead_form_submit` | `?sent=1` v URL po přesměrování | `lead_form_submit` + `generate_lead` | `Lead` |
| `lead_thank_you_page` | pathname `/dekujeme` | `lead_thank_you_page` + `generate_lead` | `Lead` |
| `social_click` | klik na Instagram, FB, YouTube, … | `social_click` | `Contact` |
| `phone_click` | klik na `tel:` odkaz | `phone_click` | — |
| `email_click` | klik na `mailto:` odkaz | `email_click` | — |

Všechny události procházejí consent gate (`canTrack()`). `generate_lead` je standardní GA4 key event — GA4 ho počítá jako konverzi automaticky. Zapnutí/vypnutí jednotlivých událostí se řídí `sites.tracking_events_json`.
Při pokusu o odeslání GA4 eventu zapisuje runtime do konzole zprávy `[GA4 Tracking] Event odeslan`, `Event neodeslan` nebo `Event selhal`, aby bylo při testování vidět, zda event prošel a případně proč byl zablokován.

---

## Request flow

### Admin

```text
request → admin/index.php → Core\Router → Controller → Repository/Service → View
```

### Veřejný web (public/index.php)

```text
request → public/index.php
        1. /api/v1/*              → ApiController::dispatch()
        2. /assets/*              → readfile (ochrana realpath)
        3. /storage/media/*       → readfile (ochrana realpath)
        4. 301 redirect pro legacy /blog/* a /nase-prace/* cesty
        5. /nase-prace            → tryServePortfolioPage()     (výpis)
        6. /blog                  → tryServeBlogListingPage()    (výpis)
        7. CMS stránka z DB       → tryServeDatabasePage()
        8. Blog detail            → tryServeBlogPostPage()       (fallback po CMS)
        9. Portfolio detail       → tryServePortfolioPage()      (fallback po CMS)
       10. Statický soubor        → storage/published/ (realpath ochrana; zahrnuje sitemap.xml, robots.txt, llms.txt)
       11. 404
```

---

## Databázové schéma

Databáze je řízena postupnými SQL migracemi v `database/migrations/`. Stav aplikovaných migrací drží tabulka `schema_migrations`, takže migrační utilitu lze spouštět opakovaně a aplikují se jen nové soubory.

### Tabulky

| Tabulka | Popis |
| --- | --- |
| `users` | Uživatelé (admin / editor) |
| `sites` | Konfigurace webu vč. layout, nav, tracking, brand |
| `templates` | Šablony stránek (layout pro CMS stránky) |
| `pages` | CMS stránky napojené na šablonu |
| `content_blocks` | Editovatelné bloky (`data-block="key"`) |
| `seo_settings` | SEO metadata per stránka |
| `media` | Nahraná média |
| `media_variants` | Varianty médií (webp, thumb, medium) |
| `blog_posts` | Blogové příspěvky |
| `blog_media` | Média přiřazená k blogovému příspěvku |
| `portfolio_entries` | Záznamy portfolia (Naše práce) |
| `portfolio_media` | Média přiřazená k záznamu portfolia |
| `faq_items` | Položky FAQ; spravují se přes stránku s layoutem FAQ |
| `service_items` | Položky služeb |
| `contact_info` | Kontaktní údaje (tel, email, adresy, soc. sítě) |
| `reviews` | Recenze / hodnocení zákazníků |
| `redirects` | URL přesměrování |
| `publish_history` | Historie publishování |

### Klíčové sloupce tabulky `sites`

Tabulka `sites` roste přes migrace. `SiteRepository::writableColumns()` udržuje explicitní seznam zapisovatelných sloupců a filtruje je přes `supportsColumn()` (SHOW COLUMNS FROM sites) — díky tomu funguje správně i na hostingu bez přístupu k `information_schema`.

| Skupina | Sloupce |
| --- | --- |
| Základní | `name`, `domain`, `default_language` |
| Analytics | `google_analytics_id`, `google_tag_manager_id` |
| Skripty | `head_scripts`, `body_scripts`, `analytics_*_scripts`, `marketing_*_scripts` |
| Layout | `layout_html`, `layout_css`, `layout_js`, `layout_nav_home`, `layout_nav_sub`, `active_theme_key` |
| Brand | `logo_url`, `favicon_ico_url`, `favicon_png_url`, `apple_touch_icon_url` |
| Hero média | `default_hero_video_url`, `default_hero_poster_url`, `video_overlay_opacity` |
| Cookie consent | `cookie_consent_enabled`, `cookie_consent_analytics_enabled`, `cookie_consent_marketing_enabled` |
| Tracking | `tracking_enabled`, `tracking_require_consent`, `tracking_debug`, `clarity_id`, `meta_pixel_id`, `tracking_events_json` |

---

## Media URL

- Vždy root-relative: `/storage/media/YYYY/MM/soubor.webp`
- Nikdy hardcoded doména ze `sites.domain` (kvůli staging/prod kompatibilitě)
- Výjimka: sitemap.xml a absolutní OG tagy musí obsahovat absolutní URL

---

## Inline editor

Přihlášený uživatel vidí na frontendu admin bar a může editovat bloky přímo na stránce:

- `data-block="key"` → editor ví, který blok edituje
- AJAX PATCH na `/admin/inline/update`
- Nahrávání médií přes `/admin/inline/upload`

Speciální veřejné stránky (`/blog`, `/nase-prace`, `/faq`, `/kontakt`) musí při přihlášeném uživateli dostat stejný editorový obal jako běžné CMS stránky. Inline editor podporuje i procedurální data přes prefixované klíče: `faq:ID:question`, `blog:ID:title`, `portfolio:ID:title`, `contact:phone_1`. Tyto klíče se ukládají přes `/admin/inline/update` do odpovídajících tabulek, ne do `content_blocks`.

CMS admin bar se vkládá do normálního toku dokumentu za otevírací `<body>`, nikdy jako fixed overlay nad webem.

---

## Bezpečnost

### Path traversal

Všechny `readfile()` bloky v `public/index.php` používají `realpath()` + kontrolu prefixu.

### Autorizace

- `requireAuth()` — jakýkoliv přihlášený uživatel
- `requireAdmin()` — pouze role `admin`

### Session cookie

`secure` flag se nastavuje automaticky: pokud `APP_URL` začíná `https://`, je `secure = true`.

### Tajemství v .env

- `MIGRATE_TOKEN` — token pro `migrate-util.php`
- `API_KEY` — klíč pro `/api/v1/*` endpointy

---

## Seed

Seed (`database/seeds/seed.php`) je generický a idempotentní:

1. Vyčistí data (DELETE, ne DROP — schema zůstane)
2. Vytvoří admin uživatele (`admin@example.com` / `admin123`)
3. Vytvoří výchozí web s layoutem a navigačními variantami
4. Vytvoří 7 šablon + 7 stránek s placeholder bloky
5. Publikuje web

Spuštění:

- Admin: `/admin/setup` → „Spustit seed"
- Admin: `/admin/setup` → „Armagedon" (reset DB + seed)
- CLI: `php database/seeds/seed.php`

Seed pro konkrétního klienta (`seed_photographer.php`) zůstává jako příklad. Výchozí seed je `seed.php`.

---

## Migrace

Migrace jsou verzované SQL soubory v `database/migrations/`.

Spuštění:

```text
/migrate-util.php?token=<MIGRATE_TOKEN>
```

Migrátor drží stav v tabulce `schema_migrations`. Lze ho spouštět opakovaně; nově aplikuje jen soubory, které v tabulce ještě nejsou.

Pravidla pro změny databáze:

- Nikdy neupravovat ani nemazat staré migrace.
- Každá změna databázové struktury nebo dat vzniká jako nová migrace.
- Nová migrace dostane další pořadové číslo podle nejvyšší existující migrace.
- Číslování musí jít vzestupně po sobě a nesmí zbytečně skákat dopředu.
- Migrace mají být idempotentní, pokud je to technicky možné.

---

## Lokální vývoj

```bash
php -S localhost:8000 router.php
```

Dev router:

- `/admin/*` → `admin/index.php`
- existující soubory → přímé servírování
- vše ostatní → `public/index.php`

---

## Deploy (CI/CD)

- `master` branch → produkce (webzdarma `/web/`)
- `dev` branch → staging (webzdarma `/web/dev/`)
- GitHub Actions buildí Composer no-dev artifact a deployuje přes FTPS

### Požadované GitHub Secrets

| Secret | Popis |
| --- | --- |
| `FTP_HOST` | Hostitel FTP serveru |
| `FTP_USER` | FTP uživatel |
| `FTP_PASS` | FTP heslo |
| `PROD_APP_URL` | Produkční URL (`https://…`) |
| `PROD_DB_HOST` | MySQL host |
| `PROD_DB_PORT` | MySQL port (default 3306) |
| `PROD_DB_NAME` | Název databáze |
| `PROD_DB_USER` | DB uživatel |
| `PROD_DB_PASS` | DB heslo |
| `PROD_MIGRATE_TOKEN` | Token pro migrate-util.php |
| `PROD_API_KEY` | Klíč pro API endpointy |

---

## Zdroje pravdy (při rozporu)

1. Aktuální kód
2. `.env` a runtime konfigurace
3. Novější dokumentace
