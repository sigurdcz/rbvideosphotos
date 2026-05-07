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
request -> admin/index.php -> Core\\Router -> Controller -> Repository/Service -> View
```

### Verejny web

```text
request -> public/index.php
        -> API routing
        -> asset/media passthrough
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

## Core vrstva

### `app/Core/Router.php`

- jednoduche path routing pro admin

### `app/Core/Controller.php`

- render view
- flash messages
- redirect
- JSON odpovedi
- CSRF kontrola pro POST

### `app/Core/Database.php`

- PDO singleton
- cte konfiguraci z `.env` a `config/database.php`
- ocekava MySQL nebo MariaDB

### `app/Core/Auth.php`

- session autentizace
- role `admin` a `editor`
- CSRF token per session

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
2. obslouzi `/assets/*`
3. obslouzi `/storage/media/*`
4. obslouzi portfolio z `storage/published/nase-prace`
5. pokusi se vyrenderovat beznou stranku z databaze
6. pripadne spadne na export ve `storage/published`

To znamena, ze neplati stare tvrzeni, ze cely web je servirovan jen z predgenerovanych HTML souboru.

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
/migrate-util.php?token=...
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
