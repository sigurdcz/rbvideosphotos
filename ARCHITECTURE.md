# Architektura — SEO Editor CMS

Tento dokument popisuje aktuální technický stav a záměr projektu.

## Záměr projektu

SEO Editor je **univerzální CMS pro tvorbu webových stránek** s důrazem na SEO. Umožňuje:

- Vytvářet a spravovat vlastní substránky s volbou layoutu
- Provozovat dva paralelní blogové systémy (Články a Naše práce)
- Sdílet jednotný layout (navigace, patička, styly) napříč celým webem
- Editovat obsah inline přímo na frontendu
- Exportovat SEO metadata, sitemap a robots.txt

---

## Stack

- PHP 8+
- MySQL / MariaDB
- PDO (bez ORM)
- Vanilla JS (bez frameworku)
- Composer autoload pro namespace `App\`

---

## Adresáře

```text
app/
  Core/           jádro aplikace (Router, Auth, Database, …)
  Controllers/    řadiče
  Repositories/   přístup k DB (PDO, připravené dotazy)
  Services/       business logika (PageRenderer, SeoRenderer, PublishService, …)
admin/
  index.php       admin router
  views/          PHP šablony adminu
  assets/         CSS a JS pro admin
public/
  index.php       front controller veřejného webu
  assets/         assety inline editoru
database/
  migrations/     SQL migrace aplikovane postupne pres schema_migrations
  seeds/          PHP seedy
storage/
  media/          nahraná média
  published/      staticky exportované soubory (sitemap, robots, …)
  logs/           logy
```

---

## Dva paralelní blogové systémy

Oba systémy jsou rovnocenné. Sdílejí layout, navigaci a styly — liší se pouze strukturou záznamu a layoutem detailu.

| Vlastnost          | Blog (Články)                      | Naše práce (Portfolio)                          |
|--------------------|------------------------------------|-------------------------------------------------|
| DB tabulka         | `blog_posts`                       | `portfolio_entries` + `portfolio_media`         |
| URL výpisu         | `/blog`                            | `/nase-prace`                                   |
| URL detailu        | `/{slug}` (bez prefixu)            | `/{slug}` (bez prefixu)                         |
| Pole záznamu       | title, slug, excerpt, content, featured_image | title, slug, description, video_url, media[] |
| Admin sekce        | Blog — články                      | Naše práce                                      |
| Rendering výpisu   | `tryServeBlogListingPage()` v public/index.php | `tryServePortfolioPage()` v public/index.php |
| Rendering detailu  | `tryServeBlogPostPage()` v public/index.php    | `tryServePortfolioPage()` v public/index.php |

### Pravidlo URL: žádný prefix

Slugy blogů i portfolia jsou přímé — **neobsahují** `/blog/` ani `/nase-prace/` prefix:

```
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

| Název šablony        | URL               | Poznámka                                      |
|----------------------|-------------------|-----------------------------------------------|
| Homepage             | `/`               | Neměnná, vždy existuje; obsahuje anchor sekce |
| Služby               | `/sluzby`         | Výčet služeb                                  |
| Ceník                | `/cenik`          | Přehled cen                                   |
| FAQ                  | `/faq`            | Otázky a odpovědi                             |
| Kontakt              | `/kontakt`        | Kontaktní informace                           |
| Blog — přehled       | `/blog`           | Hero text; seznam článků renderuje PHP        |
| Naše práce — přehled | `/nase-prace`     | Hero text; grid portfolia renderuje PHP       |

Nové substránky mohou používat libovolnou z dostupných šablon.

### Speciální layouty v administraci

Některé layouty mají v editoru stránky vlastní specializované UI místo generického seznamu bloků:

- `FAQ` spravuje hero bloky stránky a zároveň položky z tabulky `faq_items`
- `Kontakt` spravuje hero bloky stránky a zároveň strukturovaná pole z tabulky `contact_info`
- `Ceník` zobrazuje pevné karty jako samostatná pole
- `Služby` seskupuje obsah podle layoutu stránky

FAQ ani Kontakt nejsou v administraci samostatné sekce v sidebaru. Spravují se přes `Stránky` podle vybraného layoutu.

---

## Navigace — dvě varianty

| Pole v DB             | Kde se použije       | Chování odkazů                              |
|-----------------------|----------------------|---------------------------------------------|
| `sites.layout_nav_home` | Homepage (`url_path = ''`) | Hash anchory (`#sluzby`, `#cenik`, …) pro smooth scroll |
| `sites.layout_nav_sub`  | Všechny ostatní stránky, blog, portfolio | Přímé URL (`/sluzby`, `/cenik`, …) |

Každá anchor sekce na homepage má vlastní CTA tlačítko odkazující na příslušnou substránku (`#sluzby` → tlačítko „Všechny služby" → `/sluzby`).

Pokud `layout_nav_home`/`layout_nav_sub` nejsou vyplněny, placeholder `{nav}` se tiše odstraní.

---

## Sdílený layout webu

Layout je uložen v `sites.layout_html` a obsahuje placeholder `{page_content}`, `{site_name}` a `{nav}`.

```
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
- `default_hero_video_url` jako fallback video pro stránky bez vlastního hero videa
- `default_hero_poster_url` jako poster/placeholder obrázek, který se zobrazí před načtením videa

Stránka může mít vlastní blok `hero_video`. Pokud je prázdný, frontend pro podporované stránky použije globální `default_hero_video_url`.

---

## Request flow

### Admin

```
request → admin/index.php → Core\Router → Controller → Repository/Service → View
```

### Veřejný web (public/index.php)

```
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
       10. Statický soubor        → storage/published (realpath ochrana)
       11. 404
```

---

## Databázové schéma

Databáze je řízena postupnými SQL migracemi v `database/migrations/`. Stav aplikovaných migrací drží tabulka `schema_migrations`, takže migrační utilitu lze spouštět opakovaně a aplikují se jen nové soubory.

### Tabulky

| Tabulka             | Popis                                           |
|---------------------|-------------------------------------------------|
| `users`             | Uživatelé (admin / editor)                      |
| `sites`             | Konfigurace webu vč. layout HTML/CSS/JS a nav   |
| `templates`         | Šablony stránek (layout pro CMS stránky)        |
| `pages`             | CMS stránky napojené na šablonu                 |
| `content_blocks`    | Editovatelné bloky (`data-block="key"`)         |
| `seo_settings`      | SEO metadata per stránka                        |
| `media`             | Nahraná média                                   |
| `media_variants`    | Varianty médií (rozlišení, formát)              |
| `blog_posts`        | Blogové příspěvky                               |
| `portfolio_entries` | Záznamy portfolia (Naše práce)                  |
| `portfolio_media`   | Média přiřazená k záznamu portfolia             |
| `redirects`         | URL přesměrování                                |
| `publish_history`   | Historie publishování                           |

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
```
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

| Secret          | Popis                              |
|-----------------|------------------------------------|
| `FTP_HOST`      | Hostitel FTP serveru               |
| `FTP_USER`      | FTP uživatel                       |
| `FTP_PASS`      | FTP heslo                          |
| `PROD_APP_URL`  | Produkční URL (`https://…`)        |
| `PROD_DB_HOST`  | MySQL host                         |
| `PROD_DB_PORT`  | MySQL port (default 3306)          |
| `PROD_DB_NAME`  | Název databáze                     |
| `PROD_DB_USER`  | DB uživatel                        |
| `PROD_DB_PASS`  | DB heslo                           |
| `PROD_MIGRATE_TOKEN` | Token pro migrate-util.php    |
| `PROD_API_KEY`  | Klíč pro API endpointy             |

---

## Zdroje pravdy (při rozporu)

1. Aktuální kód
2. `.env` a runtime konfigurace
3. Novější dokumentace

## Inline editor - special pages

Specialni verejne stranky (`/blog`, `/nase-prace`, `/faq`, `/kontakt`) musi pri prihlasenem uzivateli dostat stejny editorovy obal jako bezne CMS stranky. Pokud specialni renderer sklada obsah proceduralne, musi napojit alespon strankove `content_blocks` (napr. `hero_h1`, `hero_sub`) pres `data-inline-key`.

CMS admin bar se vklada do normalniho toku dokumentu za oteviraci `<body>`, nikdy jako fixed overlay nad webem.

Inline editor podporuje i znama proceduralni data pres prefixovane klice: `faq:ID:question`, `blog:ID:title`, `portfolio:ID:title`, `contact:phone_1`. Tyto klice se ukladaji pres `/admin/inline/update` do odpovidajicich tabulek, ne do `content_blocks`.
