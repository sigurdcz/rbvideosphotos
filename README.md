# SEO Mini CMS

Jednoduche SEO CMS pro male staticke weby v cistem PHP 8. Aplikace ma admin rozhrani pro spravu obsahu, SEO, medii, temat a tracking konfigurace. Verejny web se servira bez frameworku a bez buildu.

## Dokumentacni mapa

- `README.md` - rychly onboarding, provozni minimum a rozcestnik
- `ARCHITECTURE.md` - technicka architektura, request flow, datovy model a systemove chovani
- `API.md` - REST API reference pro clanky, portfolio a media
- `GTM_ADMIN.md` - provozni navod k `/admin/gtm`
- `INSTRUCTIONS.md` - contribution pravidla pro vyvoj a AI asistenty

Pri rozporu mezi dokumentaci a kodem je zdrojem pravdy aktualni implementace.

## Aktualni stav

- Backend: custom PHP aplikace bez Laravelu a Symfony
- Databaze: MySQL nebo MariaDB
- Admin: `/admin`
- Verejny web: `public/index.php`
- Media: `storage/media/*`
- Publikovane exporty: `storage/published/*`
- Generovane assety: `public/assets/generated/*`

## Hlavni schopnosti

- CMS stranky s editovatelnymi bloky a vlastnim layoutem
- Blog a portfolio s primymi slugy bez `/blog/` a `/nase-prace/` prefixu v detailu
- Inline editor na frontendu pro prihlasene uzivatele
- Media pipeline s variantami `webp`, `medium` a `thumb`
- Publish flow pro sitemap, robots, llms.txt a portfolio detail/listing exporty
- Theme system nad adresarem `Templates/`
- Consent-aware tracking a GTM/GA4 konfigurace v adminu

Zakladni pravidlo systemu: sablony nenesou obsahova data. Obsah patri do DB a tema ma resit jen layout, CSS/JS a napojeni na placeholdery.

## Temata

Kazde tema zije v `Templates/<key>/` a pouziva `layout.html`, `assets/theme.css`, volitelne `assets/theme.js` a `template.json`.

Aktualne dostupna temata:

| Nazev | Klic |
| --- | --- |
| Jemna bezova | `jemna-bezova` |
| Soft Memories | `soft-memories` |
| Modern Atelier | `modern-atelier` |
| Minimal Dark | `minimal-dark` |
| Editorial Gold | `editorial-gold` |
| Elegant White | `elegant-white` |

Aktivni tema se nastavuje v `Nastaveni -> Tema`. Technicke chovani theme aplikace je v `ARCHITECTURE.md`.

## Pozadavky

- PHP 8.0+
- MySQL 8+ nebo MariaDB 10.4+
- Rozsireni: `PDO`, `pdo_mysql`, `DOM`, `GD` nebo `Imagick`

## Konfigurace

Projekt cte nastaveni z `.env`. Zkopiruj `.env.example` jako `.env` a dopln hodnoty:

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

Nejdulesitejsi promenne:

| Promenna | Popis |
| --- | --- |
| `APP_URL` | Verejna URL aplikace; pri `https://` dostane session cookie `Secure` flag |
| `APP_DEBUG` | `true` v lokalu, `false` na produkci |
| `MIGRATE_TOKEN` | Token pro `migrate-util.php` |
| `API_KEY` | Klic pro `/api/v1/*` endpointy |
| `GTM_CLIENT_ID`, `GTM_CLIENT_SECRET`, `GTM_ENCRYPTION_KEY` | OAuth a sifrovani GTM adminu |
| `GTM_REDIRECT_URI`, `GTM_AUTH_URI`, `GTM_TOKEN_URI`, `GTM_API_BASE_URI` | Volitelne GTM/OAuth override |
| `FTP_HOST`, `FTP_USER`, `FTP_PASS` | Nasazeni pres FTP/FTPS |

GA4, Clarity a Meta Pixel ID se bezne nastavuji v admin sekci `Nastaveni` a ukladaji se do tabulky `sites`, ne do `.env`.

Detail GTM setupu je v `GTM_ADMIN.md`.

## Prvotni spusteni

1. Vytvor databazi.
2. Nastav `.env`.
3. Spust migrace.
4. Volitelne nacti seed.
5. Spust lokalni PHP server.

```bash
php -S localhost:8000 router.php
```

Migrace pres URL utilitu:

```text
http://localhost:8000/migrate-util.php?token=<MIGRATE_TOKEN>
```

Seed z adminu:

```text
http://localhost:8000/admin/setup
```

Poznamka: setup obrazovka vedle zakladniho seedu nabizi i klientsky/demo obsah pro RB Photos & Videos. Pred spustenim na existujicich datech zkontroluj, co presne chces nahrat.

Seed z CLI:

```bash
php database/seeds/seed.php
php database/seeds/seed_photographer.php
php database/seeds/seed_photographer.php --fresh
```

## Prihlaseni do administrace

- URL: `http://localhost:8000/admin`
- Vychozi e-mail: `admin@example.com`
- Vychozi heslo: `admin123`

## Lokalni databaze

- Host: `127.0.0.1`
- Port: `3306`
- Databaze: `seo_editor_cms` nebo hodnota z `.env`
- Uzivatel: podle `.env`
- phpMyAdmin: `http://127.0.0.1:8081/`

## Provozni poznamky

- Media a interni odkazy preferuji root-relative cesty jako `/media/...`, `/blog`, `/cenik`
- Absolutni URL jsou potreba hlavne pro sitemap, canonical a Open Graph vystupy
- `public/assets/generated/` se negituje a pri publishi se regeneruje
- `storage/published/llms.txt` se generuje pri publishi spolu se sitemap a portfolio vystupy
- Konverzni tracking a consent flow jsou popsane v `ARCHITECTURE.md`

## Dalsi dokumentace

- Chces API kontrakty: otevri `API.md`
- Chces request flow, tabulky a interni sluzby: otevri `ARCHITECTURE.md`
- Chces obsluhu GTM adminu: otevri `GTM_ADMIN.md`
- Chces contribution guardrails: otevri `INSTRUCTIONS.md`
