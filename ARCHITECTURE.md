# Architektura a technické postupy — RB Photos SEO Editor

> Referenční dokument pro vývojáře. Popisuje, jak projekt funguje, jaké jsou použité technologie, patterny a konvence.

---

## Obsah

1. [Co projekt dělá](#co-projekt-dělá)
2. [Technologie](#technologie)
3. [Adresářová struktura](#adresářová-struktura)
4. [Architektura backendu](#architektura-backendu)
5. [Publish pipeline](#publish-pipeline)
6. [Frontend patterny (publikované HTML)](#frontend-patterny-publikované-html)
7. [Databázové schéma](#databázové-schéma)
8. [Deploy workflow](#deploy-workflow)
9. [Lokální vývoj](#lokální-vývoj)
10. [Bezpečnostní patterny](#bezpečnostní-patterny)

---

## Co projekt dělá

**SEO Editor** je jednoduchý headless CMS pro statické weby malých lokálních podnikatelů (fotograf v Olomouci).

Princip:
1. Správce nahraje HTML šablonu s `data-block` atributy (sloty pro obsah)
2. V admin panelu vyplní texty, obrázky a SEO metadata
3. Klikne "Publikovat" → systém vygeneruje čisté statické HTML soubory do `/storage/published/`
4. Výstup jsou plain HTML soubory bez PHP závislosti — ideální pro sdílený hosting

---

## Technologie

| Vrstva | Technologie |
|--------|-------------|
| Backend | PHP 8.0+, custom micro-framework (bez Laravelu/Symfony) |
| Database (prod) | MySQL 8 / MariaDB 10.4 na webzdarma.cz |
| Database (dev) | SQLite (soubor `storage/database.sqlite`) |
| ORM / DB layer | PDO připravené příkazy, custom Repositories |
| Autoloader | Composer PSR-4 (`App\` → `app/`) |
| Admin frontend | Vanilla JS + custom CSS, žádný framework |
| Publikovaný frontend | Statické HTML + CSS + Vanilla JS (žádný build systém) |
| Deploy | GitHub Actions + FTP (SamKirkland/FTP-Deploy-Action) |
| Hosting | webzdarma.cz (shared Apache, FTP přístup) |
| Lokální server | PHP built-in server (`php -S localhost:8000 router.php`) |

---

## Adresářová struktura

```
seo-editor/
├── .github/workflows/         # CI/CD: deploy.yml (prod) + deploy-dev.yml (staging)
├── .claude/                   # Claude Code nastavení (lokální permise)
├── admin/                     # Admin panel aplikace
│   ├── index.php              # Entry point: router + auth check
│   ├── assets/
│   │   ├── admin.css          # Admin UI styly
│   │   └── admin.js           # Admin interaktivita (tabs, slug gen, media picker)
│   └── views/                 # PHP view templates
│       ├── layouts/main.php   # Hlavní layout (sidebar, topbar, content slot)
│       ├── auth/login.php
│       ├── pages/             # Stránky CRUD
│       ├── seo/               # SEO editor
│       ├── publish/           # Publikování
│       ├── templates/         # Správa šablon
│       ├── media/             # Upload médií
│       ├── redirects/         # 301/302 redirecty
│       └── settings/          # Nastavení webu (GA, GTM, skripty)
├── app/                       # Aplikační logika
│   ├── Core/                  # Framework komponenty
│   ├── Controllers/           # HTTP request handlery
│   ├── Repositories/          # Database abstrakce
│   └── Services/              # Business logika
├── config/                    # Konfigurace (app.php, database.php, seo.php)
├── database/                  # Schema + seed + init skripty
├── public/
│   ├── index.php              # Frontend router — servíruje /storage/published/
│   └── .htaccess              # Rewrite rules pro frontend
├── storage/
│   ├── database.sqlite        # SQLite DB pro lokální vývoj
│   ├── media/                 # Nahrané obrázky
│   ├── logs/                  # Aplikační logy
│   ├── cache/                 # Cache
│   └── published/             # GENEROVANÝ VÝSTUP (statické HTML)
│       ├── index.html
│       ├── cenik/index.html
│       ├── blog/index.html
│       ├── blog/*/index.html
│       ├── sitemap.xml        # Auto-generovaný při publish
│       ├── robots.txt         # Auto-generovaný při publish
│       └── .htaccess          # Auto-generovaný z redirect tabulky
├── vendor/                    # Composer autoloader
├── .env                       # Secrets (DB + FTP credentials) — NENÍ v gitu
├── .htaccess                  # Root Apache routing
├── router.php                 # PHP built-in server router (dev)
├── index.php                  # Shared hosting entry point
├── start.bat / stop.bat       # Windows dev server skripty
└── composer.json              # PSR-4 autoload config
```

---

## Architektura backendu

### Micro-framework pattern (app/Core/)

Projekt nepoužívá žádný PHP framework — má vlastní minimální vrstvu:

```
Request → admin/index.php → Core\Router → Controller → Repository → View
                                                      ↓
                                                  Service
                                                      ↓
                                               published/HTML
```

**Router** (`app/Core/Router.php`)
- Jednoduchý path-based routing bez regex parametrů
- Route definice jsou v `admin/index.php`
- Dispatch volá `ControllerClass::method()`

**Controller** (`app/Core/Controller.php`)
- Base class pro všechny controllery
- Metody: `view($template, $data)`, `json($data)`, `flash($msg)`, `redirectTo($url)`
- CSRF check povinný pro POST requesty

**Database** (`app/Core/Database.php`)
- PDO singleton — jediná instance per request
- Přepíná mezi SQLite a MySQL podle `config/database.php`
- Lokálně: `storage/database.sqlite`; produkčně: MySQL na webzdarma.cz

**Auth** (`app/Core/Auth.php`)
- Session-based autentizace
- CSRF tokeny generované per-session
- Role: `admin` / `editor`

---

### Repository pattern (app/Repositories/)

Každá databázová tabulka má vlastní repository:

```php
// Vzor pro každé repo:
class PageRepository {
    public function findById(int $id): ?array
    public function allBySite(int $siteId): array
    public function create(array $data): int   // vrací insert ID
    public function update(int $id, array $data): void
    public function delete(int $id): void
    public function findPublished(int $siteId): array
}
```

Žádný ORM — jen PDO prepared statements. Data jsou čisté PHP pole (array), ne objekty/modely.

---

### Service pattern (app/Services/)

Business logika oddělena od controllerů:

| Service | Účel |
|---------|------|
| `PublishService` | Orchestruje celý publish workflow (191 řádků) |
| `PageRenderer` | DOM injection dat do HTML šablony |
| `SeoRenderer` | Generuje HTML hlavičku: meta, OG, Twitter Card, JSON-LD |
| `TemplateParser` | Extrahuje `data-block` klíče z HTML šablony |
| `SitemapGenerator` | Generuje XML sitemap ze všech publishnutých stránek |
| `RobotsGenerator` | Generuje robots.txt |
| `ImageVariantService` | WebP konverze a resizing nahraných obrázků |
| `SeoAuditService` | Validace SEO completeness |

---

## Publish pipeline

Klik "Publikovat" → `PublishController` → `PublishService::publishPage()`:

```
1. Načti: page + template + content_blocks + seo_settings + site config

2. PageRenderer::render(templateHtml, blocks)
   └─ DOM parsing HTML šablony
   └─ Injection: data-block="key" → value z content_blocks tabulky
   └─ Injection: data-media="key" → URL nahraného obrázku
   └─ Injection: data-cms-field="key" → editovatelný obsah

3. SeoRenderer::renderHead(seoSettings, siteConfig)
   └─ <title>, <meta name="description">, <meta name="keywords">
   └─ <link rel="canonical">
   └─ Open Graph: og:title, og:description, og:url, og:image, og:type
   └─ Twitter Card: twitter:title, twitter:description, twitter:card
   └─ JSON-LD: LocalBusiness / BlogPosting / FAQPage schema
   └─ Google Analytics (gtag.js) + Google Tag Manager

4. Injektuj SEO head do <head> šablony

5. Zapiš výsledek do:
   storage/published/{url_path}/index.html

6. Regeneruj:
   └─ storage/published/sitemap.xml
   └─ storage/published/robots.txt
   └─ storage/published/.htaccess (z redirect tabulky)

7. Aktualizuj pages.status = 'published', pages.published_at = NOW()
8. Zapiš záznam do publish_history
```

---

## Frontend patterny (publikované HTML)

Publikované HTML soubory jsou čistý statický výstup. Žádný PHP, žádný build step.

### Dynamické URL (bez hardcoded domény)

**KRITICKÉ PRAVIDLO:** Nikdy nepoužívat `localhost` ani pevnou doménu v HTML atributech.

**Pattern pro `og:url` a `canonical`:**

```html
<!-- V HTML: placeholder hodnota -->
<link rel="canonical" href="/cesta-stranky">
<meta property="og:url" content="og-url-placeholder">

<!-- Na konci <body>: JS nahradí placeholdery reálnou doménou -->
<script>
(function(){
  var o = location.origin;
  var c = document.querySelector('link[rel="canonical"]');
  if(c) c.href = o + '/cesta-stranky';
  var og = document.querySelector('meta[property="og:url"]');
  if(og) og.content = o + '/cesta-stranky';
})();
</script>
```

**Pattern pro JSON-LD schema (s SITE_ORIGIN placeholder):**

```html
<!-- V <head>: JSON-LD s placeholderem -->
<script type="application/ld+json" id="ld-main">
{
  "@type": "LocalBusiness",
  "url": "SITE_ORIGIN/",
  "@id": "SITE_ORIGIN/#business"
}
</script>

<!-- V JS sekci: regex replace -->
<script>
(function(){
  var o = location.origin;
  var ld = document.getElementById('ld-main');
  if(ld) ld.textContent = ld.textContent.replace(/SITE_ORIGIN/g, o);
})();
</script>
```

**Proč JS a ne server-side?**
- Hosting je sdílený (webzdarma.cz) — stejné HTML soubory musí fungovat na dev i prod
- Doména se mění (dev: `localhost:8000`, prod: `rbvideosphotos.kvalitne.cz`)
- `window.location.origin` vrátí vždy správnou doménu bez nutnosti rebuild

**Výjimka:** `sitemap.xml` a `robots.txt` jsou statické soubory čtené Googlem bez JS — tam musí být absolutní URL. Jsou auto-generovány s doménou z `site_settings` při každém publishnutí.

---

### CMS data-atributy (editovatelný obsah)

Každý editovatelný prvek šablony má `data-cms-*` atribut:

```html
<!-- Jednoduchý text field -->
<h1 data-cms-field="hero-heading">Výchozí nadpis</h1>

<!-- Blok textu (obsah stránky z DB) -->
<p data-block="intro_text">Výchozí text...</p>

<!-- Obrázek z media knihovny -->
<img data-media="hero_image" src="/assets/placeholder.jpg" alt="">

<!-- Opakovatelná kolekce položek -->
<div data-cms-repeat="pricing-weddings">
  <div data-cms="pricing-item">
    <h3 data-cms-field="title"></h3>
    <span data-cms-field="price_from"></span>
    <ul data-cms-repeat="features">
      <li data-cms-field="feature"></li>
    </ul>
  </div>
</div>
```

`TemplateParser` extrahuje všechny `data-block` klíče při uložení šablony → vytvoří záznamy v `content_blocks` tabulce → admin panel zobrazí formulář pro každý klíč.

---

### Design system (CSS proměnné)

Všechny publikované stránky sdílejí identický design system definovaný CSS proměnnými:

```css
:root {
  --gold:   #c4963c;   /* Primární zlatá — CTA tlačítka, akcenty */
  --gold-d: #a67c2a;   /* Tmavší zlatá — hover stav */
  --dark:   #0c0c0e;   /* Hero sekce, navbar, footer background */
  --dark2:  #141416;   /* Sekundární tmavé sekce */
  --dark3:  #1e1e22;   /* Hover stav tmavých karet */
  --light:  #f9f8f6;   /* Světlé sekce pozadí */
  --text:   #1c1c1e;   /* Hlavní text */
  --muted:  #6b7280;   /* Sekundární text, popisky */
  --border: #e4e4e7;   /* Ohraničení karet a tabulek */
  --radius: 3px;       /* Border-radius všech prvků */
}
```

Styly jsou **inline** v každém HTML souboru (žádný sdílený CSS soubor) — to umožňuje nasazení jednotlivých stránek nezávisle na sobě.

---

### Komponenty

Opakující se UI bloky mají konzistentní CSS třídy:

| Třída | Komponenta |
|-------|------------|
| `.pricing-item` / `.pricing-item.featured` | Cenová karta (základní / zvýrazněná) |
| `.service-card` | Karta služby |
| `.faq-item` + `.faq-q` + `.faq-a` | FAQ accordion (JS toggle přes `.open` třídu) |
| `.blog-card` | Karta blog článku |
| `.cta-block` | CTA sekce (tmavé pozadí, dvě tlačítka) |
| `.tip-item` | Tip karta (světlé pozadí, číslo + nadpis + text) |
| `.review-card` | Recenze zákazníka |
| `.navbar-blog` | Sticky navbar pro podstránky |
| `.section-dark` / `.section-light` | Alternující sekce pozadí |
| `.sticky-cta` | Plávající CTA po scrollu mimo hero |

---

### Structured data (JSON-LD)

Každý typ stránky má odpovídající schema:

| Stránka | Schema typ |
|---------|-----------|
| Homepage | `LocalBusiness` + `FAQPage` |
| Blog listing | `WebPage` |
| Blog článek | `BlogPosting` (s `datePublished`, `dateModified`, `author`) |
| Ceník | `FAQPage` |

JSON-LD je vždy v `<script type="application/ld+json" id="ld-[name]">` tagu v `<head>`. Dynamické URL jsou řešeny JS replace patternm (viz výše).

---

### Navigace a interní linking

- Navbar na všech stránkách: Služby → Ceník → Videa → Reference → Blog → Kontakt
- Všechny interní linky jsou **relativní URL** (např. `/blog`, `/cenik`, `/#kontakt`)
- Blog → Homepage: `/` nebo `/#sluzby`
- Článek → Blog: `/blog`
- Sidebar v článcích: "Další články" s `<a href="/blog/...">` linky

---

## Databázové schéma

11 tabulek (MySQL prod / SQLite dev):

```
users               — admin účty (email, password_hash, role: admin|editor)
sites               — konfigurace webu (domain, GA ID, GTM ID, head/body skripty)
templates           — HTML šablony s data-block sloty
pages               — stránky (title, slug, url_path, status: draft|published)
content_blocks      — obsah bloků (page_id, block_key, block_type, content)
media               — metadata nahraných obrázků
media_variants      — WebP varianty a thumbnaily obrázků
seo_settings        — per-stránka SEO (meta_title, meta_desc, OG, Twitter, schema)
redirects           — 301/302 redirecty → generuje .htaccess
publish_history     — audit log publishnutí
site_settings       — (přes sites tabulku) GA, GTM, vlastní skripty
```

Vztahy:
```
sites 1──* templates
sites 1──* pages
pages *──1 templates
pages 1──* content_blocks
pages 1──1 seo_settings
pages 1──* publish_history
sites 1──* media
media 1──* media_variants
sites 1──* redirects
```

---

## Deploy workflow

### Prostředí

| Prostředí | Git branch | FTP cílová složka | URL |
|-----------|-----------|-------------------|-----|
| Production | `master` | `/web/` | `rbvideosphotos.kvalitne.cz` |
| Staging | `dev` | `/web/dev/` | `rbvideosphotos.kvalitne.cz/dev/` |

### GitHub Actions (`.github/workflows/deploy.yml`)

```
Push na master
    │
    ▼
1. Checkout kódu
2. Composer install --no-dev --optimize-autoloader
3. rsync build artefaktů do _deploy/
   └─ EXCLUDE: .git, .github, .env, .claude, *.bat, node_modules
   └─ EXCLUDE: storage/database.sqlite, storage/media/, storage/cache/
   └─ INCLUDE: prázdné storage/* adresáře (pro chmod)
4. FTP Deploy: _deploy/ → /web/
   └─ SamKirkland/FTP-Deploy-Action@v4
   └─ Credentials z GitHub Secrets (FTP_HOST, FTP_USER, FTP_PASS)
```

**Co se NENASAZUJE:**
- `.env` (DB + FTP credentials)
- `storage/database.sqlite` (produkce používá MySQL)
- `storage/media/` (media soubory se nemigrují přes deploy)
- Vývoj/debug soubory (`.claude/`, `*.bat`, `.gitignore`)

**Co se nasazuje:**
- PHP aplikace (`app/`, `admin/`, `config/`, `public/`, `database/`)
- `storage/published/` — vygenerované HTML stránky
- `vendor/` — Composer autoloader
- `.htaccess` soubory
- `index.php`, `router.php`, `composer.json`

---

## Lokální vývoj

### Spuštění

```bat
REM Windows:
start.bat
REM = php -S localhost:8000 router.php

REM Nebo manuálně:
php -S localhost:8000 router.php
```

### Bootstrap databáze (první spuštění)

```bash
php database/init-sqlite.php
# Vytvoří storage/database.sqlite se schématem + seed daty
# Výchozí admin: admin@example.com / password

# Volitelně: nahrát demo data fotografa
php database/bunny-fotos-init.php
```

### URL struktura lokálně

```
http://localhost:8000/          → public/index.php → storage/published/index.html
http://localhost:8000/blog/     → public/index.php → storage/published/blog/index.html
http://localhost:8000/admin/    → admin/index.php → dashboard
http://localhost:8000/admin/pages → admin/index.php → pages listing
```

### router.php — dev server routing

```php
// admin/* → admin/index.php
// storage/published/* → přímý file serve (obrázky, CSS)
// vše ostatní → public/index.php
```

### Přepínání DB driverů

`config/database.php`:
```php
return [
    'driver' => 'sqlite',  // 'sqlite' (dev) nebo 'mysql' (prod)
    // ...
];
```

Lokálně vždy SQLite — žádná konfigurace MySQL serveru potřeba.

---

## Bezpečnostní patterny

### CSRF ochrana

Každý POST formulář musí obsahovat CSRF token:

```php
// V šabloně:
<input type="hidden" name="csrf_token" value="<?= $csrf ?>">

// V controlleru (Base Controller automaticky ověřuje):
$this->checkCsrf();  // vyhazuje 403 při neshodě
```

Tokeny jsou generovány per-session v `Auth::generateCsrfToken()`.

### Autentizace

- Session-based (PHP sessions)
- Password hash: `password_hash()` / `password_verify()` (bcrypt)
- Všechny admin routy ověřují `Auth::check()` na začátku `admin/index.php`
- Redirect na `/admin/login` pokud není přihlášen

### Input sanitizace

- Všechny DB operace: PDO prepared statements (parametrizované dotazy)
- HTML output: `htmlspecialchars()` v PHP view templates
- Upload validace: MIME type + extension whitelist v `MediaController`

### Secrets management

- `.env` je v `.gitignore` — nikdy není commitován
- GitHub Secrets pro CI/CD (FTP_HOST, FTP_USER, FTP_PASS, DB_*)
- Produkční DB credentials nejsou nikdy v kódu ani v `storage/`

---

## Poznámky pro vývojáře

### Přidání nové stránky (manuálně)

1. Vytvoř soubor `storage/published/{slug}/index.html`
2. Přidej URL do `storage/published/sitemap.xml`
3. Přidej link do navigace na ostatních stránkách (navbar)
4. Commitni a pushni → GitHub Actions automaticky nasadí

### Přidání nové šablony

1. Vytvoř HTML s `data-block="klíč"` atributy pro editovatelný obsah
2. Nahraj přes admin panel (`/admin/templates/create`)
3. Systém automaticky extrahuje bloky → vytvoří formulář v editoru stránek

### Úprava publikovaného HTML přímo

Soubory v `storage/published/` jsou generovaný výstup — změny jsou přepsány při dalším publishnutí přes admin panel. Pro trvalé úpravy edituj šablonu nebo obsah stránky v adminu.

Výjimka: pokud stránka ještě nemá záznam v DB (ručně vytvořená), změny v souboru přežijí.

### Přidání odkazu do sitemapy

`storage/published/sitemap.xml` je auto-generovaný. Pro trvalou změnu:
- Přes admin → Publikovat celý web (regeneruje sitemap)
- Nebo edituj přímo XML soubor (přepíše se při příštím publish)

### Konvence pro nové PHP soubory

```
app/Controllers/NazevController.php  — HTTP logika (žádná DB, žádný HTML)
app/Repositories/NazevRepository.php — pouze PDO dotazy
app/Services/NazevService.php        — business logika
admin/views/nazev/index.php          — HTML view (žádná logika)
```

Namespace: `App\Controllers\`, `App\Repositories\`, `App\Services\`
