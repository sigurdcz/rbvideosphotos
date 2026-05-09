# CLAUDE.md — instrukce pro AI asistenta

Tento soubor popisuje pravidla, která musíš dodržovat při práci na tomto projektu.
Čti ho vždy na začátku každé session. Pravidla zde mají přednost před tvým obecným chováním.

## Co je tento projekt

**SEO Editor CMS** — univerzální nástroj pro tvorbu webových stránek s důrazem na SEO.
Nejde o fotografický web ani o jinou konkrétní vertikálu. Seed a šablony jsou záměrně generické.

Stack: PHP 8+, MySQL/MariaDB, PDO, Vanilla JS, bez frameworku, Composer autoload.

---

## Základní pravidla

### Co nikdy nedělat

- Nepřidávej ORM, framework (Laravel, Symfony, …) ani npm build.
- Necommituj `.env`, credentials ani media soubory.
- Nepoužívej hardcoded doménu ze `sites.domain` pro URL médií — vždy root-relative (`/storage/media/…`).
- Nepřidávej `/blog/` ani `/nase-prace/` prefix do slugů blogových příspěvků a portfolia.
- Nespouštěj `git push --force` na `master` bez explicitního souhlasu uživatele.
- Neodstraňuj existující bezpečnostní ochrany (`realpath()` checks, CSRF, `requireAdmin()`).

### Co vždy dělat

- Udržuj seed (`database/seeds/seed.php`) generický — žádný klientský obsah.
- Klientský obsah patří do `seed_photographer.php` nebo do jiného pojmenovaného seed souboru.
- Při změně schématu aktualizuj `database/migrations/001_schema.sql` (jediný migration soubor).
- Po změně routingu v `public/index.php` aktualizuj sekci „Request flow" v `ARCHITECTURE.md`.
- Po přidání nového systémového konceptu aktualizuj `ARCHITECTURE.md`.

---

## Dva paralelní blogové systémy

Existují dva plně rovnocenné blogové systémy. Sdílejí layout, navigaci a styly.

### Blog (Články)

- DB tabulka: `blog_posts`
- URL výpisu: `/blog` (renderuje `tryServeBlogListingPage()` v `public/index.php`)
- URL detailu: `/{slug}` — **bez** prefixu `/blog/`
- Admin: Blog — články

### Naše práce (Portfolio)

- DB tabulky: `portfolio_entries`, `portfolio_media`
- URL výpisu: `/nase-prace` (renderuje `tryServePortfolioPage()` v `public/index.php`)
- URL detailu: `/{slug}` — **bez** prefixu `/nase-prace/`
- Admin: Naše práce

### Routing pořadí (public/index.php)

```
1. API (/api/v1/*)
2. Assets (/assets/*, /storage/media/*)
3. 301 redirect pro legacy /blog/* a /nase-prace/* cesty
4. /nase-prace  → tryServePortfolioPage() (výpis)
5. /blog        → tryServeBlogListingPage() (výpis)
6. CMS stránka  → tryServeDatabasePage()
7. Blog detail  → tryServeBlogPostPage()   [fallback po CMS]
8. Portfolio detail → tryServePortfolioPage() [fallback po CMS]
9. Statický soubor ze storage/published
10. 404
```

Toto pořadí je záměrné — neměň ho bez dobrého důvodu.

---

## Navigace — dvě varianty

`sites.layout_nav_home` (pro homepage, `url_path = ''`):
- Hash anchory: `#sluzby`, `#nase-prace`, `#blog`, `#cenik`, `#faq`, `#kontakt`
- Kliknutí scrolluje na sekci na homepage

`sites.layout_nav_sub` (pro všechny ostatní stránky):
- Přímé URL: `/sluzby`, `/nase-prace`, `/blog`, `/cenik`, `/faq`, `/kontakt`
- Kliknutí přejde na danou stránku

Homepage anchor sekce mají každá vlastní CTA tlačítko odkazující na příslušnou substránku.

---

## Substránky a šablony

Uživatel si při zakládání substránky vybere šablonu (layout). Šablony jsou seedované.

Šablona definuje strukturu HTML s `data-block="key"` atributy — ty jsou editovatelné v adminu i inline na frontendu.

Předpřipravené šablony (seedované v `seed.php`):
- `Homepage` — jedinou instancí je `/`
- `Služby`, `Ceník`, `FAQ`, `Kontakt`
- `Blog — přehled` (pro `/blog`)
- `Naše práce — přehled` (pro `/nase-prace`)

---

## Sdílený layout webu

Layout (header, nav, footer, globální CSS/JS) je uložen v tabulce `sites`:
- `layout_html` — HTML wrapper s `{nav}`, `{page_content}`, `{site_name}`
- `layout_css` — globální CSS (nav, footer, tlačítka, reset, …)
- `layout_js` — globální JS (smooth scroll, hamburger menu, …)
- `layout_nav_home` — nav HTML pro homepage
- `layout_nav_sub` — nav HTML pro ostatní stránky

`PageRenderer::applyLayout()` dosadí `{page_content}`, `{site_name}` a `{nav}`.

---

## Migrace

Jedno konsolidované schema: `database/migrations/001_schema.sql`.

- Používej `CREATE TABLE IF NOT EXISTS` — migrace musí být idempotentní.
- Nepiš separátní migrační soubory 002, 003, … — vše slij do `001_schema.sql`.
- Po změně schématu smaž `storage/.migration-lock` a spusť migrace znovu.

---

## Seed

Výchozí seed: `database/seeds/seed.php` (generický, žádný klientský obsah).

Příkaz v `SetupController::runSeed()` spouští právě tento soubor.

Seed je idempotentní: DELETE všechna data → znovu INSERT. Schema neruší.

Klientský seed (např. `seed_photographer.php`) se spouští ručně — není výchozí.

---

## Media URL

```php
// Správně:
'/storage/media/2026/05/foto.webp'

// Špatně:
'https://domena.cz/storage/media/2026/05/foto.webp'
```

Výjimka: sitemap, canonical URL a OG tagy vyžadují absolutní URL → použij `sites.domain`.

---

## Bezpečnost — co kontrolovat před každým PR

- `readfile()` — musí mít `realpath()` + prefix check
- POST formuláře — musí volat `$this->csrfCheck()`
- Admin routes — musí volat `requireAuth()` nebo `requireAdmin()`
- Žádné `shell_exec`, `exec`, `system` s user inputem
- Připravené PDO dotazy (`prepare()` + `execute()`) — nikdy string interpolace do SQL

---

## CI/CD

- `master` → produkce (webzdarma `/web/`)
- `dev` → staging (`/web/dev/`)
- Deploy přes FTPS (GitHub Actions + FTP-Deploy-Action)
- `.env` se generuje z GitHub Secrets — necommituj ho
- `storage/media` není součástí deploy artefaktu

Požadované GitHub Secrets pro produkci:
`FTP_HOST`, `FTP_USER`, `FTP_PASS`,
`PROD_APP_URL`, `PROD_DB_HOST`, `PROD_DB_PORT`, `PROD_DB_NAME`, `PROD_DB_USER`, `PROD_DB_PASS`,
`PROD_MIGRATE_TOKEN`, `PROD_API_KEY`

---

## Lokální vývoj

```bash
php -S localhost:8000 router.php
```

Migrace: `http://localhost:8000/migrate-util.php?token=<MIGRATE_TOKEN>`

Seed: admin → `/admin/setup` → „Spustit seed" nebo Armageddon (reset + seed)

---

## Souborová mapa (klíčové soubory)

| Soubor | Popis |
|--------|-------|
| `public/index.php` | Front controller; routing pro blog, portfolio, CMS stránky |
| `admin/index.php` | Admin router (45 routes) |
| `app/Controllers/SetupController.php` | Seed + Armageddon; spouští `seed.php` |
| `app/Services/PageRenderer.php` | Renderování šablon, layout injection, SEO injection |
| `app/Services/PublishService.php` | Publikování stránek do `storage/published` |
| `app/Repositories/BlogRepository.php` | CRUD pro `blog_posts` |
| `app/Repositories/PortfolioRepository.php` | CRUD pro `portfolio_entries` + media |
| `database/migrations/001_schema.sql` | Jediné schéma databáze |
| `database/seeds/seed.php` | Výchozí generický seed |
| `database/seeds/seed_photographer.php` | Příklad klientského seedu |
| `migrate-util.php` | HTTP endpoint pro spuštění migrací |
| `ARCHITECTURE.md` | Detailní architektura projektu |
