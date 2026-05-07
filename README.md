# SEO Mini CMS

Jednoduche SEO CMS pro male staticke weby v cistem PHP 8. Aplikace ma admin rozhrani pro spravu obsahu, SEO a medii, ale verejny web se servira bez frameworku a bez buildu.

## Aktualni stav

- Backend: custom PHP aplikace bez Laravelu a Symfony
- Databaze: pouze MySQL nebo MariaDB
- Admin: `/admin`
- Verejny web: `public/index.php`
- Media: `storage/media/*`
- Publikovane exporty: `storage/published/*`

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

Projekt cte nastaveni z `.env`:

```env
APP_URL=http://localhost:8000
APP_DEBUG=true

DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=seo_editor_cms
DB_USER=root
DB_PASS=
```

## Prvotni spusteni

1. Vytvor databazi.
2. Spust migrace.
3. Volitelne nacti seed.
4. Spust lokalni PHP server.

Priklady:

```bash
php -S localhost:8000 router.php
```

Migrace pres URL utility:

```text
http://localhost:8000/migrate-util.php?token=rbpv-migrate-2026-05-02-t7K9mQ4sX2
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
3. Cast vystupu se pri publishi generuje do `storage/published`.
4. Portfolio listing a detail se generuji jako staticke HTML do `storage/published/nase-prace`.
5. `sitemap.xml`, `robots.txt` a dalsi exporty se take ukladaji do `storage/published`.

## Dulezite URL pravidlo

Pro media a interni odkazy nepouzivej natvrdo ulozenou domenu tam, kde to neni nutne.

- Interni odkazy preferuj relativni, napr. `/blog`, `/cenik`, `/nase-prace`
- Media na webu pouzivej jako root-relative cesty, napr. `/storage/media/2026/05/obrazek.webp`
- Absolutni URL jsou potreba hlavne pro SEO vystupy typu `sitemap.xml`

## Struktura projektu

```text
seo-editor/
|- app/                aplikacni logika
|- admin/              admin panel
|- config/             konfigurace
|- database/           migrace, schema, seedy
|- public/             verejny front controller a assety
|- storage/
|  |- media/           nahrane soubory
|  |- logs/            logy
|  `- published/       generovane exporty a portfolio HTML
|- vendor/             autoload
|- router.php          router pro lokalni PHP server
|- index.php           vstup pro hosting
`- migrate-util.php    tokenova migracni utilita
```

## Nasazeni

- Produkce bezi z branche `master`
- Staging bezi z branche `dev`
- Deploy probiha pres GitHub Actions a FTP
- `.env` se nedeployuje
- `storage/media` se nedeployuje jako soucast buildu

## Poznamky k portfoliu

- Vybrane obrazky se ukladaji do vazebni tabulky `portfolio_media`
- Detail i listing portfolia se publikuji do `storage/published/nase-prace`
- Galerie a nahledy medii pouzivaji verejne root-relative URL z `storage_path`
