# SEO Mini CMS

Jednoduche SEO CMS pro male staticke weby v cistem PHP 8. Aplikace ma admin rozhrani pro spravu obsahu, SEO a medii, ale verejny web se servira bez frameworku a bez buildu.

## Aktualni stav

- Backend: custom PHP aplikace bez Laravelu a Symfony
- Databaze: pouze MySQL nebo MariaDB
- Admin: `/admin`
- Verejny web: `public/index.php`
- Media: `storage/media/*`
- Publikovane exporty: `storage/published/*`
- Minifikovane assety: `public/assets/generated/*`

## Brand a hero media

V admin sekci `Nastaveni` lze vybrat logo, favicony, vychozi hero video a poster obrazek primo z medii. Vlastni hero video stranky ma prednost; pokud neni vyplnene, podporovane stranky pouziji vychozi video a poster z nastaveni webu.

## Temata (Templates)

Projekt obsahuje predpripravena vizualni temata v adresari `Templates/`. Kazde tema ma vlastni `layout.html`, `theme.css`, volitelne `theme.js` a popisny `template.json`. Aktivni tema se nastavuje v `Nastaveni → Tema`.

Dostupna temata:

| Tema | Klic |
|---|---|
| Jemna bezova | `jemna-bezova` |
| Soft Memories | `soft-memories` |
| Modern Atelier | `modern-atelier` |
| Minimal Dark | `minimal-dark` |
| Editorial Gold | `editorial-gold` |

Tema lze aplikovat jednim klikem — `ThemeTemplateRegistry` nacte layout, CSS a JS z adresare tematu a prepise sablony webu v databazi.

## Dokumentace pro vyvoj

- `INSTRUCTIONS.md` obsahuje obecna pracovni pravidla pro vsechny AI asistenty i vyvojare.
- `ARCHITECTURE.md` popisuje technickou architekturu, request flow, databazove oblasti a systemove koncepty.
- Projekt nepouziva specializovane instrukcni MD soubory pro konkretni AI nastroje.

## Lokalni databaze pro tento projekt

- Host: `127.0.0.1`
- Port: `3306`
- Databaze: `seo_editor_cms` nebo hodnota z `.env`
- Uzivatel: podle `.env`
- phpMyAdmin: `http://127.0.0.1:8081/`

## Pozadavky

- PHP 8.0+
- MySQL 8+ nebo MariaDB 10.4+
- Rozsireni: `PDO`, `pdo_mysql`, `DOM`, `GD` nebo `Imagick`

## Konfigurace

Projekt cte nastaveni z `.env`. Zkopiruj `.env.example` jako `.env` a vyplň hodnoty:

```env
APP_URL=http://localhost:8000
APP_DEBUG=true

# Vygeneruj: php -r "echo bin2hex(random_bytes(20));"
MIGRATE_TOKEN=

# Vygeneruj: php -r "echo bin2hex(random_bytes(32));"
API_KEY=

DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=seo_editor_cms
DB_USER=root
DB_PASS=
```

### Dulezite env promenne

| Promenna | Popis |
|---|---|
| `APP_URL` | Verejná URL aplikace. Pokud zacina `https://`, session cookie dostane `Secure` flag automaticky. |
| `APP_DEBUG` | `true` v lokalu, `false` na produkci. |
| `MIGRATE_TOKEN` | Token pro `migrate-util.php`. Nesmí byt hardcoded — generuj nahodne. |
| `API_KEY` | Klic pro `/api/v1/*` endpointy. |
| `GA4_ID` | Google Analytics 4 — ID měření (G-XXXXXXXXXX). |
| `GTM_ID` | Google Tag Manager — ID kontejneru (GTM-XXXXXXX). |
| `CLARITY_ID` | Microsoft Clarity — ID projektu. |
| `META_PIXEL_ID` | Meta Pixel — ID pixelu (volitelne). |
| `FTP_HOST` / `FTP_USER` / `FTP_PASS` | Pristupove udaje pro deploy pres FTP. |

Tracking kody (GA4, GTM, Clarity, Meta Pixel) se injektuji az po souhlasu s cookies pres `cookie-consent-runtime.js` — nikdy se nenacitaji blokovane.

## Prvotni spusteni

1. Vytvor databazi.
2. Nastav `.env`.
3. Spust migrace.
4. Volitelne nacti seed.
5. Spust lokalni PHP server.

```bash
php -S localhost:8000 router.php
```

Migrace pres URL utility:

```text
http://localhost:8000/migrate-util.php?token=<MIGRATE_TOKEN>
```

Seed z adminu:

```text
http://localhost:8000/admin/setup
```

Seed z CLI:

```bash
php database/seeds/seed_photographer.php
php database/seeds/seed_photographer.php --fresh
```

## Prihlaseni do administrace

- URL: `http://localhost:8000/admin`
- Vychozi e-mail: `admin@example.com`
- Vychozi heslo: `admin123`

## Jak projekt funguje

1. Do adminu se nahraji sablony a obsah.
2. Verejne stranky se bezne renderuji primo z databaze.
3. Pri publishovani webu se vycisti `public/assets/generated/` a regeneruji minifikovane CSS/JS soubory.
4. Portfolio listing a detail se generuji jako staticke HTML do `storage/published/nase-prace`.
5. `sitemap.xml`, `robots.txt` a `llms.txt` se generuji do `storage/published`.

### CSS a JS minifikace

`FrontendAssetManager` zapisuje layout CSS/JS do `public/assets/generated/` jako hahovane soubory (`prefix-HASH.css`). Pred zapisem prochazi pres `AssetMinifier` (regex minifikace bez zavislosti). Pri kazdem globalni publishovani se adresar vycisti a soubory se regeneruji — zadne stale assety nezustavaji.

### /llms.txt

Na kazdem publishovani se do `storage/published/llms.txt` zapise soubor dle specifikace [llmstxt.org](https://llmstxt.org/). Obsahuje nazev webu, popis a seznam vsech stranek s URL a meta popisky — pro AI indexery analogicky jako `sitemap.xml` pro crawlery.

## Dulezite URL pravidlo

Pro media a interni odkazy nepouzivej natvrdo ulozenou domenu tam, kde to neni nutne.

- Interni odkazy preferuj relativni, napr. `/blog`, `/cenik`, `/nase-prace`
- Media na webu pouzivej jako root-relative cesty, napr. `/storage/media/2026/05/obrazek.webp`
- Absolutni URL jsou potreba hlavne pro SEO vystupy typu `sitemap.xml`

## Struktura projektu

```text
seo-editor/
|- app/                aplikacni logika
|  |- Core/            jadro (Router, Auth, Controller, SlugTrait, ...)
|  |- Controllers/     controllery
|  |- Repositories/    DB pristup
|  |- Services/        business logika
|  `- Support/         pomocne tridy (AssetMinifier, FrontendAssetManager)
|- admin/              admin panel
|- config/             konfigurace
|- database/           migrace, schema, seedy
|- public/
|  |- index.php        front controller
|  |- assets/          assety editoru a tracking
|  `- assets/generated/  minifikovane CSS/JS (generovane, neni v gitu)
|- storage/
|  |- media/           nahrane soubory
|  |- logs/            logy
|  `- published/       sitemap.xml, robots.txt, llms.txt, portfolio HTML
|- Templates/          predpripravena vizualni temata
|- vendor/             autoload
|- router.php          router pro lokalni PHP server
|- index.php           vstup pro hosting
`- migrate-util.php    tokenova migracni utilita (token z .env)
```

## Role a autorizace

Aplikace ma dve role:

| Role | Popis |
|---|---|
| `admin` | Plny pristup vcetne Nastaveni a Setup/Armagedon |
| `editor` | Sprava obsahu (stranky, blog, portfolio, media) |

Endpointy `SetupController` a `SettingsController` vyzaduji roli `admin`.

## Bezpecnost

- **Path traversal**: vsechny `readfile()` bloky pouzivaji `realpath()` + kontrolu prefixu
- **CSRF**: tokeny na vsech POST formularich pres `hash_equals()`
- **Session cookie**: `Secure` flag se nastavuje automaticky dle `APP_URL`
- **Tajemstvi**: `MIGRATE_TOKEN` a `API_KEY` musi byt v `.env`, nikdy v kodu
- **SQL injection**: PDO prepared statements vsude

## API

REST API je dostupne na `/api/v1/*` s autentizaci pres `X-Api-Key` nebo `Authorization: Bearer` header.

```text
GET    /api/v1/articles
POST   /api/v1/articles
GET    /api/v1/articles/{slug}
PUT    /api/v1/articles/{slug}
DELETE /api/v1/articles/{slug}

GET    /api/v1/portfolio
POST   /api/v1/portfolio
GET    /api/v1/portfolio/{slug}
PUT    /api/v1/portfolio/{slug}
DELETE /api/v1/portfolio/{slug}

POST   /api/v1/media            multipart/form-data; pole: file, alt_text, title
DELETE /api/v1/media/{id}
```

Write operace (POST/PUT/DELETE) maji stejne vedlejsi efekty jako admin UI — synchronizace homepage bloku, publikovani statickeho HTML, generovani obrazkovych variant. PUT je parcialni: chybejici pole zustanou na aktualni hodnote z DB. Duplicitni slug vraci `409 Conflict`.

`API_KEY` se nastavuje v `.env`. Detailni dokumentaci endpointu vcetne vsech polí najdes v admin panelu pod `Nastaveni → API`.

## Konverzni tracking

Web trackuje konverze bez nutnosti stranky `/dekujeme`. `public/assets/tracking.js` detekuje:

- **Odeslani formulare** — `submit` event na kontaktnim formulari → `lead_form_submit`, GA4 `generate_lead`, Meta Pixel `Lead`
- **Parametr `?sent=1`** — kdyz backend po odeslani presmeruje s query stringem → `lead_form_submit`, GA4 `generate_lead`, Meta Pixel `Lead`
- **Kliknuti na socialni site** — Instagram, Facebook, YouTube atd. → `social_click`, Meta Pixel `Contact`
- **Kliknuti na telefon / email** — `phone_click`, `email_click`

Vsechny udalosti prochazi consent gate — nespousti se pred souhlasem s analytics cookies (pokud je consent povinny).

## Nasazeni

- Produkce bezi z branche `master`
- Staging bezi z branche `dev`
- Deploy probiha pres GitHub Actions a FTP
- `.env` se nedeployuje
- `storage/media` se nedeployuje jako soucast buildu
- `public/assets/generated/` se nedeployuje — generuje se pri publishovani

## Media a obrazky

- Nahrane obrazky se zpracovavaji pres `MediaIngestionService`: detekce MIME, validace velikosti, presun souboru, zapis do DB
- Automaticky se generuji varianty: `webp` (plna velikost), `thumb` (max 300 px), `medium` (max 900 px)
- Logo webu se servuje pres `<picture><source type="image/webp">` — WebP varianta se vyhledava automaticky z `media_variants`
- Vsechna media na frontendu pouzivaji root-relative cesty (`/storage/media/...`), nikdy natvrdo ulozenou domenu

## Poznamky k portfoliu

- Vybrane obrazky se ukladaji do vazebni tabulky `portfolio_media`
- Detail i listing portfolia se publikuji do `storage/published/nase-prace`
- Galerie a nahledy medii pouzivaji verejne root-relative URL z `storage_path`
