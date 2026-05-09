# Architecture

Tento dokument popisuje aktualni technicky stav projektu podle skutecne implementace v repu.

## Prehled

SEO Editor je male CMS pro spravu obsahu a SEO nad statickym nebo skoro statickym webem.

Aktualni model:

1. Admin cast zapisuje data do MySQL nebo MariaDB.
2. Bezne verejne stranky se renderuji z databaze pres `public/index.php`.
3. Vybrane casti vystupu se pri publishi generuji do `storage/published`.
4. Portfolio listing a detail se serviruji jako predgenerovane HTML soubory.

## Stack

- PHP 8+
- MySQL nebo MariaDB
- PDO
- Vanilla JS
- Bez frontend buildu
- Composer autoload pro namespace `App\\`

## Adresare

```text
app/
  Core/           jadro aplikace
  Controllers/    controllery
  Repositories/   DB pristup
  Services/       business logika
admin/
  index.php       admin router
  views/          PHP sablony adminu
  assets/         CSS a JS pro admin
public/
  index.php       front controller pro verejny web
  assets/         assety inline editoru a verejne assety
database/
  migrations/     SQL migrace
  seeds/          PHP seedy
storage/
  media/          nahrana media
  published/      portfolio HTML, sitemap, robots a dalsi exporty
  logs/           logy
```

## Request flow

### Admin

```text
request -> admin/index.php -> Core\Router -> Controller -> Repository/Service -> View
```

### Verejny web

```text
request -> public/index.php
        -> API routing
        -> asset/media passthrough (path traversal ochrana pres realpath)
        -> portfolio static HTML
        -> DB render beznych stranek
        -> fallback na storage/published
```

## Databaze

Projekt je aktualne `MySQL/MariaDB only`.

Pouzite tabulky zahrnuji zejmena:

- `users`
- `sites`
- `templates`
- `pages`
- `content_blocks`
- `seo_settings`
- `media`
- `media_variants`
- `portfolio_entries`
- `portfolio_media`
- `redirects`
- `publish_history`

## Site layout a navigace

Layout je sdílen přes `sites.layout_html`. Podporované placeholdery:

- `{page_content}` — obsah stránky ze šablony
- `{site_name}` — název webu
- `{nav}` — navigační panel (volitelný, viz níže)

### Dvě varianty navigace

| Pole v DB | Kdy se použije | Chování odkazů |
|---|---|---|
| `sites.layout_nav_home` | Homepage (`url_path = /`) | Hash anchory (`#sluzby`, `#cenik`…) pro One Page smooth scroll |
| `sites.layout_nav_sub` | Všechny ostatní stránky + portfolio | Přímé URL (`/sluzby`, `/cenik`…) |

Oba nav bloky mohou obsahovat `{site_name}`, který se nahradí názvem webu.

Pokud `layout_nav_home`/`layout_nav_sub` nejsou vyplněny, `{nav}` se tiše odstraní a nav musí být součástí `layout_html` ručně.

### Povinně seedované stránky

Těchto 7 stránek je vždy naseedovaných:

| URL | Šablona |
|---|---|
| `/` | Homepage |
| `/sluzby` | Služby |
| `/nase-prace` | Portfolio (dynamické, mimo DB pages) |
| `/cenik` | Ceník |
| `/faq` | FAQ |
| `/blog` | Blog listing |
| `/kontakt` | Kontakt |

## Core vrstva

### `app/Core/Router.php`

- jednoduche path routing pro admin

### `app/Core/Controller.php`

- render view
- flash messages
- redirect
- JSON odpovedi
- CSRF kontrola pro POST
- `requireAuth()` — overeni prihlaseni
- `requireAdmin()` — overeni prihlaseni + role `admin`

### `app/Core/Database.php`

- PDO singleton
- cte konfiguraci z `.env` a `config/database.php`
- ocekava MySQL nebo MariaDB

### `app/Core/Auth.php`

- session autentizace
- role `admin` a `editor`
- CSRF token per session
- `secure` flag na session cookie se nastavuje automaticky podle `APP_URL` (https = true)

### `app/Core/SlugTrait.php`

- sdileny trait pro generovani slugu
- pouzivan v `PageController`, `BlogController`, `PortfolioController`
- parametr `allowSlash: true` povoluje lomitko (vnorene stranky)
- transliterace ceskych diakritik (vcetne velkych pismen)

## Repository vrstva

Repozitare vraceji pole a pouzivaji pripravene PDO dotazy. Neni zde ORM.

Klicove repozitare:

- `PageRepository`
- `TemplateRepository`
- `SeoRepository`
- `MediaRepository`
- `PortfolioRepository`

## Service vrstva

### `PageRenderer`

- vklada `data-block` obsah do HTML sablon
- umi media mapu a inline edit rezim

### `SeoRenderer`

- generuje title, meta description, canonical, OG a Twitter tagy

### `PublishService`

- publikuje klasicke stranky do `storage/published`
- regeneruje sitemap, robots a dalsi doprovodne soubory

### `ImageVariantService`

- vytvari varianty obrazku, pokud je k dispozici GD

## Portfolio

Portfolio ma vlastni workflow mimo bezny page render:

- admin formular uklada zaznam do `portfolio_entries`
- vybrane obrazky se ukladaji do `portfolio_media`
- detail zakazky se publikuje do `storage/published/nase-prace/{slug}/index.html`
- listing se publikuje do `storage/published/nase-prace/index.html`
- homepage blok `portfolio-tiles` se synchronizuje do `content_blocks`

### Aktualni pravidlo pro media URL v portfoliu

- nepouzivat natvrdo ulozenou domenu ze `sites.domain`
- pouzivat root-relative cestu odvozenou ze `storage_path`
- priklad: `/storage/media/2026/05/fotka.webp`

To je dulezite hlavne proto, aby media fungovala stejne na produkci, stagingu i lokalne.

## Verejny rendering

`public/index.php` dnes dela tyto veci:

1. obslouzi `/api/v1/*`
2. obslouzi `/assets/*` — s ochranou pred path traversalem (realpath + prefix check)
3. obslouzi `/storage/media/*` — s ochranou pred path traversalem
4. obslouzi portfolio z `storage/published/nase-prace`
5. pokusi se vyrenderovat beznou stranku z databaze
6. pripadne spadne na export ve `storage/published` — s ochranou pred path traversalem

To znamena, ze neplati stare tvrzeni, ze cely web je servirovan jen z predgenerovanych HTML souboru.

## Bezpecnost

### Path traversal

Vsechny tri `readfile()` bloky v `public/index.php` pouzivaji `realpath()` + kontrolu prefixu:

```php
$base = realpath($baseDir);
$file = realpath($baseDir . $uri);
if ($base !== false && $file !== false && str_starts_with($file, $base . DIRECTORY_SEPARATOR)) {
    readfile($file);
}
```

### Tajemstvi v .env

- `MIGRATE_TOKEN` — token pro `migrate-util.php` nesmí byt hardcoded v kodu
- `API_KEY` — klic pro `/api/v1/*` endpointy

Oba musi byt nastaveny v `.env` (viz `.env.example`).

### Role-based autorizace

- `requireAuth()` — jakykoli prihlaseny uzivatel
- `requireAdmin()` — pouze role `admin`
- `SetupController` a `SettingsController` vyzaduji `admin`

### Session cookie

- `secure` flag se nastavuje automaticky: pokud `APP_URL` zacina `https://`, je `secure = true`

## Lokalni vyvoj

Spusteni:

```bash
php -S localhost:8000 router.php
```

Dev router:

- `/admin/*` -> `admin/index.php`
- existujici soubory -> prime servirovani
- vse ostatni -> `public/index.php`

Migrace:

```text
/migrate-util.php?token=<MIGRATE_TOKEN z .env>
```

Seed:

- `/admin/setup`
- nebo `php database/seeds/seed_photographer.php`

## SEO a URL konvence

- interni odkazy drzet relativni
- media URL drzet root-relative, pokud neni nutna absolutni domena
- absolutni domena je vhodna pro `sitemap.xml` a podobne SEO exporty

## Deploy

- `master` -> production
- `dev` -> staging
- GitHub Actions pripravi deploy balicek
- `.env` se nedeployuje
- `storage/media` neni soucast standardniho deploy artefaktu

## Zname zdroje pravdy

Kdyz je rozpor mezi dokumentaci a kodem, prednost ma:

1. aktualni kod
2. `.env` a runtime konfigurace
3. novejsi dokumentace
