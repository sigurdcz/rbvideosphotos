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
| --- | --- |
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
| --- | --- |
| `APP_URL` | Verejná URL aplikace. Pokud zacina `https://`, session cookie dostane `Secure` flag automaticky. |
| `APP_DEBUG` | `true` v lokalu, `false` na produkci. |
| `MIGRATE_TOKEN` | Token pro `migrate-util.php`. Nesmí byt hardcoded — generuj nahodne. |
| `API_KEY` | Klic pro `/api/v1/*` endpointy. |
| `GA4_ID` | Google Analytics 4 — ID měření (G-XXXXXXXXXX). |
| `CLARITY_ID` | Microsoft Clarity — ID projektu. |
| `META_PIXEL_ID` | Meta Pixel — ID pixelu (volitelne). |
| `GTM_CLIENT_ID` | OAuth client ID pro Google Tag Manager admin sekci. |
| `GTM_CLIENT_SECRET` | OAuth client secret pro GTM admin sekci. |
| `GTM_REDIRECT_URI` | Volitelne prepsani OAuth callback URL. |
| `GTM_ENCRYPTION_KEY` | 32-byte klic pro sifrovani refresh tokenu GTM spojeni. |
| `GTM_AUTH_URI` / `GTM_TOKEN_URI` / `GTM_API_BASE_URI` | Volitelne prepsani Google OAuth a GTM endpointu. |
| `GTM_REQUEST_TIMEOUT` / `GTM_CONNECT_TIMEOUT` / `GTM_RETRY_DELAY_MS` | Ladeni timeoutu a retry chovani GTM API klienta. |
| `FTP_HOST` / `FTP_USER` / `FTP_PASS` | Pristupove udaje pro deploy pres FTP. |

Tracking kody (GA4, Clarity, Meta Pixel) se injektuji az po souhlasu s cookies pres `cookie-consent-runtime.js` — nikdy se nenacitaji blokovane.

## Google Tag Manager v administraci

Administrace obsahuje samostatnou sekci `Google Tag Manager` dostupnou na `/admin/gtm`. Tato sekce slouzi k tomu, aby bylo mozno spravovat GTM pres API bez rucni prace v rozhrani Google Tag Manageru.

Co umoznuje:

- pripojeni Google uctu pres OAuth
- vyber GTM uctu, kontejneru a workspace
- nacitani inventare tagu, triggeru, promennych a verzi
- spravu predpripravenych eventovych definic
- vytvoreni planu z lokalniho katalogu a jeho aplikaci do GTM workspace
- vytvoreni a publikaci GTM verzi

Databazova struktura pro tuto sekci je v nove append-only migraci `database/migrations/016_create_gtm_admin_tables.sql`.

Tato faze nezasahuje do runtime trackingu na webu. `public/assets/tracking.js`, `SeoRenderer` ani soucasne GA4 eventy se v tomto kroku nemení.

Poznamky k provozu:

- OAuth callback je `https://tvoje-domena/admin/gtm/oauth/callback` nebo hodnota z `GTM_REDIRECT_URI`
- refresh token se uklada sifrovane pomoci `GTM_ENCRYPTION_KEY`
- GTM API musi byt povolene v Google Cloud projektu
- pro OAuth scope se pouziva `tagmanager.readonly`, `tagmanager.edit.containers`, `tagmanager.edit.containerversions` a `tagmanager.publish`
- pokud Google vrati chybu nebo chybi opravneni, administrace to zobrazi explicitne a zapise do auditniho logu

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
| --- | --- |
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

REST API je dostupne na `/api/v1/*`. Vsechny endpointy vyzaduji autentizaci klicem z `.env`.

### Autentizace

```env
API_KEY=<vygeneruj: php -r "echo bin2hex(random_bytes(32));">
```

| Zpusob | Format |
| --- | --- |
| Header (doporuceno) | `X-Api-Key: {klic}` |
| Bearer token | `Authorization: Bearer {klic}` |
| Query param (mene bezpecne) | `?api_key={klic}` |

Chybejici nebo nespravny klic: `401 Unauthorized`.

### Spolecne konvence

- Telo POST/PUT pozadavku: `Content-Type: application/json`
- Odpovedi: `Content-Type: application/json; charset=UTF-8`
- `PUT` ma PATCH semantiku — pole chybejici v tele se zachovaji z aktualni hodnoty v DB
- Duplicitni slug: `409 Conflict { "error": "Slug already exists." }`
- Chybejici povinne pole: `400 Bad Request { "error": "..." }`
- Nenalezeny zdroj: `404 Not Found { "error": "..." }`

---

### Clanky — `/api/v1/articles`

#### `GET /api/v1/articles`

Vraci seznam vsech publikovanych clanku.

**Odpoved `200`:**

```json
{
  "posts": [ /* pole Article */ ],
  "total": 12
}
```

---

#### `POST /api/v1/articles`

Vytvori novy clanek.

**Telo (JSON):**

| Pole | Typ | Povinne | Popis |
| --- | --- | --- | --- |
| `title` | `string` | ano | Nadpis clanku |
| `slug` | `string` | ne | URL slug; pokud chybi, generuje se z `title` |
| `excerpt` | `string` | ne | Kratky popis, plain text |
| `content` | `string` | ne | Telo clanku, muze obsahovat HTML |
| `status` | `"draft"` \| `"published"` | ne | Vychozi: `"draft"` |
| `featured_image_id` | `integer` \| `null` | ne | ID media z `/api/v1/media` |
| `gallery_ids` | `integer[]` | ne | Pole ID medii pro galerii clanku |
| `meta_title` | `string` \| `null` | ne | SEO title; pokud `null`, pouzije se `title` |
| `meta_description` | `string` \| `null` | ne | SEO popis |

**Odpoved `201`:** objekt `Article` (viz nize).

---

#### `GET /api/v1/articles/{slug}`

Vraci detail jednoho clanku dle slugu.

**Parametr cesty:** `slug` — URL slug clanku.

**Odpoved `200`:** objekt `Article`.

---

#### `PUT /api/v1/articles/{slug}`

Castecna aktualizace clanku (vsechna pole jsou volitelna).

**Parametr cesty:** `slug` — URL slug clanku.

**Telo (JSON) — vsechna pole volitelna:**

| Pole | Typ | Popis |
| --- | --- | --- |
| `title` | `string` | Novy nadpis |
| `slug` | `string` | Novy slug; generuje se z `title` pokud chybi |
| `excerpt` | `string` | Kratky popis |
| `content` | `string` | Telo, muze obsahovat HTML |
| `status` | `"draft"` \| `"published"` | Zmena stavu |
| `featured_image_id` | `integer` \| `null` | Uvodni obrazek |
| `gallery_ids` | `integer[]` | Nahradí celou galerii; prazdne pole galerii odstrani |
| `meta_title` | `string` \| `null` | SEO title |
| `meta_description` | `string` \| `null` | SEO popis |

**Odpoved `200`:** aktualizovany objekt `Article`.

---

#### `DELETE /api/v1/articles/{slug}`

Smaze clanek vcetne vazebnich zaznamu galerie.

**Odpoved `200`:**

```json
{ "success": true, "deleted_slug": "muj-clanek" }
```

---

#### Objekt `Article`

```json
{
  "id":                  1,
  "title":               "Nazev clanku",
  "slug":                "nazev-clanku",
  "excerpt":             "Kratky popis...",
  "content":             "<p>HTML obsah...</p>",
  "featured_image_url":  "https://example.cz/storage/media/2026/05/foto.webp",
  "meta_title":          "SEO title",
  "meta_description":    "SEO popis",
  "status":              "published",
  "published_at":        "2026-05-18 12:00:00",
  "created_at":          "2026-05-18 10:00:00",
  "updated_at":          "2026-05-18 12:00:00"
}
```

| Pole | Typ | Popis |
| --- | --- | --- |
| `id` | `integer` | Interni ID |
| `title` | `string` | Nadpis |
| `slug` | `string` | URL slug |
| `excerpt` | `string` | Kratky popis |
| `content` | `string` | HTML obsah |
| `featured_image_url` | `string` \| `null` | Absolutni URL uvodni fotky (z domeny webu) |
| `meta_title` | `string` \| `null` | SEO title |
| `meta_description` | `string` \| `null` | SEO popis |
| `status` | `"draft"` \| `"published"` | Stav |
| `published_at` | `string` \| `null` | Datum publikovani (`Y-m-d H:i:s`) |
| `created_at` | `string` | Datum vytvoreni |
| `updated_at` | `string` | Datum posledni zmeny |

---

### Portfolio — `/api/v1/portfolio`

#### `GET /api/v1/portfolio`

Vraci seznam vsech publikovanych polozek portfolia.

**Odpoved `200`:**

```json
{
  "entries": [ /* pole PortfolioEntry */ ],
  "total": 8
}
```

---

#### `POST /api/v1/portfolio`

Vytvori novou polozku portfolia.

**Telo (JSON):**

| Pole | Typ | Povinne | Popis |
| --- | --- | --- | --- |
| `title` | `string` | ano | Nazev polozky |
| `slug` | `string` | ne | URL slug; generuje se z `title` pokud chybi |
| `description` | `string` | ne | Popis, muze obsahovat HTML |
| `video_url` | `string` \| `null` | ne | Vimeo nebo YouTube URL |
| `status` | `"draft"` \| `"published"` | ne | Vychozi: `"draft"` |
| `sort_order` | `integer` | ne | Poradi v listingu; vychozi: `0` |
| `media_ids` | `integer[]` | ne | Pole ID medii pro galerii polozky |
| `meta_title` | `string` \| `null` | ne | SEO title |
| `meta_description` | `string` \| `null` | ne | SEO popis |

**Odpoved `201`:** objekt `PortfolioEntry` (viz nize).

---

#### `GET /api/v1/portfolio/{slug}`

Vraci detail polozky portfolia.

**Parametr cesty:** `slug` — URL slug polozky.

**Odpoved `200`:** objekt `PortfolioEntry`.

---

#### `PUT /api/v1/portfolio/{slug}`

Castecna aktualizace polozky portfolia (vsechna pole volitelna).

**Parametr cesty:** `slug` — URL slug polozky.

**Telo (JSON) — vsechna pole volitelna:**

| Pole | Typ | Popis |
| --- | --- | --- |
| `title` | `string` | Novy nazev |
| `slug` | `string` | Novy slug |
| `description` | `string` | Popis, HTML |
| `video_url` | `string` \| `null` | Vimeo / YouTube; `null` video odstrani |
| `status` | `"draft"` \| `"published"` | Zmena stavu |
| `sort_order` | `integer` | Poradi |
| `media_ids` | `integer[]` | Nahradí celou galerii |
| `meta_title` | `string` \| `null` | SEO title |
| `meta_description` | `string` \| `null` | SEO popis |

**Odpoved `200`:** aktualizovany objekt `PortfolioEntry`.

---

#### `DELETE /api/v1/portfolio/{slug}`

Smaze polozku a odstrani publikovane staticke HTML soubory.

**Odpoved `200`:**

```json
{ "success": true, "deleted_slug": "moje-polozka" }
```

---

#### Objekt `PortfolioEntry`

```json
{
  "id":               1,
  "title":            "Svatba Jana a Petra",
  "slug":             "svatba-jana-a-petra",
  "description":      "<p>Popis...</p>",
  "video_url":        "https://vimeo.com/123456789",
  "video_embed_url":  "https://player.vimeo.com/video/123456789",
  "video_thumbnail":  "https://img.youtube.com/vi/xxx/hqdefault.jpg",
  "photos": [
    {
      "url":    "https://example.cz/storage/media/2026/05/foto.webp",
      "alt":    "Popis fotky",
      "width":  1920,
      "height": 1080
    }
  ],
  "meta_title":       "SEO title",
  "meta_description": "SEO popis",
  "status":           "published",
  "sort_order":       0,
  "published_at":     "2026-05-18 12:00:00",
  "created_at":       "2026-05-18 10:00:00",
  "updated_at":       "2026-05-18 12:00:00"
}
```

| Pole | Typ | Popis |
| --- | --- | --- |
| `id` | `integer` | Interni ID |
| `title` | `string` | Nazev polozky |
| `slug` | `string` | URL slug |
| `description` | `string` | HTML popis |
| `video_url` | `string` \| `null` | Puvodni URL videa (Vimeo / YouTube) |
| `video_embed_url` | `string` \| `null` | Embed URL pro `<iframe>` |
| `video_thumbnail` | `string` \| `null` | URL nahladu videa (YouTube thumb) |
| `photos` | `Photo[]` | Fotky galerie |
| `photos[].url` | `string` | Absolutni URL fotky |
| `photos[].alt` | `string` | Alt text |
| `photos[].width` | `integer` \| `null` | Sirka v px |
| `photos[].height` | `integer` \| `null` | Vyska v px |
| `meta_title` | `string` \| `null` | SEO title |
| `meta_description` | `string` \| `null` | SEO popis |
| `status` | `"draft"` \| `"published"` | Stav |
| `sort_order` | `integer` | Poradi v listingu |
| `published_at` | `string` \| `null` | Datum publikovani |
| `created_at` | `string` | Datum vytvoreni |
| `updated_at` | `string` | Datum posledni zmeny |

---

### Media — `/api/v1/media`

#### `POST /api/v1/media`

Nahraje jeden nebo vice souboru. Content-Type musi byt `multipart/form-data`.

**Telo (form-data):**

| Pole | Typ | Povinne | Popis |
| --- | --- | --- | --- |
| `file` nebo `files[]` | `file` \| `file[]` | ano | Jeden soubor (`file`) nebo vice (`files[]`) |
| `alt_text` | `string` | ano | Alt text pro vsechny nahrávane soubory |
| `title` | `string` | ne | Nazev; pokud chybi, pouzije se `alt_text` |

Povolene MIME typy: obrazky (`image/jpeg`, `image/png`, `image/webp`, `image/gif`, `image/svg+xml`). Ostatni typy zavisi na konfiguraci `MediaIngestionService`.

Po nahrání se automaticky generuji tri varianty: `webp` (plna velikost), `medium` (max 900 px), `thumb` (max 300 px).

**Odpoved `200`:**

```json
{
  "results": [
    {
      "success":       true,
      "media_id":      42,
      "storage_path":  "storage/media/2026/05/foto.webp",
      "url":           "/storage/media/2026/05/foto.webp",
      "variant_count": 3,
      "name":          "foto.jpg",
      "width":         1920,
      "height":        1080,
      "mime_type":     "image/webp"
    }
  ]
}
```

Pri chybe jednoho souboru obsahuje polozka `error` misto `success`:

```json
{ "error": "File too large (PHP limit): foto.jpg" }
```

| Pole | Typ | Popis |
| --- | --- | --- |
| `media_id` | `integer` | ID pro pouziti v `featured_image_id` / `media_ids` / `gallery_ids` |
| `storage_path` | `string` | Relativni cesta od rootu projektu |
| `url` | `string` | Root-relative URL pro pouziti v HTML |
| `variant_count` | `integer` | Pocet vygenerovanych variant (webp, medium, thumb) |
| `name` | `string` | Puvodni nazev souboru |
| `width` | `integer` \| `null` | Sirka v px |
| `height` | `integer` \| `null` | Vyska v px |
| `mime_type` | `string` \| `null` | MIME typ ulozeneho souboru |

---

#### `DELETE /api/v1/media/{id}`

Smaze medium vcetne vsech variant ze souboroveho systemu a z DB.

**Parametr cesty:** `id` — interni ID media (integer).

**Odpoved `200`:**

```json
{ "success": true, "deleted_id": 42 }
```

---

### Pomocny endpoint

#### `GET /api/v1/key`

Vraci aktualni API klic (vyzaduje autentizaci — pouzij pro overeni, ze klic funguje).

```json
{ "api_key": "abc123..." }
```

---

### Vedlejsi efekty write operaci

| Operace | Vedlejsi efekt |
| --- | --- |
| `POST /articles`, `PUT /articles/{slug}` | Synchronizace homepage bloku pres `BlogSyncService` |
| `DELETE /articles/{slug}` | Odebrani clanku z homepage bloku |
| `POST /portfolio` (`status: published`) | Publikovani statickeho HTML do `storage/published/nase-prace/` |
| `PUT /portfolio/{slug}` (`status: published`) | Prepsani statickych souboru + sync homepage dlazdic |
| `DELETE /portfolio/{slug}` | Smazani statickych souboru + sync homepage dlazdic |
| `POST /media` | Generovani obrazkovych variant (webp, medium, thumb) |
| `DELETE /media/{id}` | Smazani vsech variant ze FS a DB |

## Konverzni tracking

Web trackuje konverze bez nutnosti stranky `/dekujeme`. `public/assets/tracking.js` detekuje:

- **Odeslani formulare** — `submit` event na kontaktnim formulari → `lead_form_submit`, GA4 `generate_lead`, Meta Pixel `Lead`
- **Parametr `?sent=1`** — kdyz backend po odeslani presmeruje s query stringem → `lead_form_submit`, GA4 `generate_lead`, Meta Pixel `Lead`
- **Kliknuti na socialni site** — Instagram, Facebook, YouTube atd. → `social_click`, Meta Pixel `Contact`
- **Kliknuti na telefon / email** — `phone_click`, `email_click`
- **Kliknuti na CTA prvky** — prvky s `data-track-event`, typicky `cta_click`
- **Engagement eventy** — `gallery_open`, `video_play`, `pricing_click`, `scroll_90`

Vsechny udalosti prochazi consent gate — nespousti se pred souhlasem s analytics cookies (pokud je consent povinny).
Pri pokusu o odeslani GA4 eventu runtime zapisuje do konzole `[GA4 Tracking]` stav `Event odeslan`, `Event neodeslan` nebo `Event selhal`, vcetne nazvu eventu a duvodu blokace.

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
