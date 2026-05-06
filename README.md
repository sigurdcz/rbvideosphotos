# SEO Mini CMS

Jednoduch� SEO CMS pro statick� weby napsan� v �ist�m PHP 8+. Um� spravovat HTML �ablony, editovat obsah p�es data-block atributy a publikovat hotov� statick� soubory v�etn� sitemap.xml a robots.txt.


## Lok�ln� datab�ze pro tento projekt

- Typ: `MariaDB / MySQL`
- Host: `127.0.0.1`
- Port: `3306`
- Datab�ze projektu: `seo_editor_cms`
- Aplika�n� u�ivatel: `seo_editor_app`
- K�dov�n�: `utf8mb4`
- phpMyAdmin: `http://127.0.0.1:8081/`

Projekt je te� nastaven� jako `MySQL/MariaDB only`. SQLite fallback se nem� pou��vat.
---

## Požadavky

- PHP 8.0+
- MySQL 8+ nebo MariaDB 10.4+
- Rozšíření: PDO, PDO_MySQL, GD nebo Imagick (pro WebP), DOM
- Composer (volitelné — autoloader je přiložen)

---

## Instalace

### 1. Vytvoření databáze

```sql
CREATE DATABASE seo_cms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Import schématu

```bash
mysql -u root -p seo_cms < database/schema.sql
```

### 3. Import seed dat (výchozí admin + ukázková šablona)

```bash
mysql -u root -p seo_cms < database/seed.sql
```

### 4. Konfigurace databáze

Zkopírujte nebo upravte `config/database.php`:

```php
return [
    'host'     => '127.0.0.1',
    'port'     => '3306',
    'dbname'   => 'seo_cms',
    'username' => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',
];
```

Nebo použijte proměnné prostředí:
```
DB_HOST=127.0.0.1
DB_NAME=seo_cms
DB_USER=root
DB_PASS=heslo
```

### 5. Spuštění přes lokální PHP server

```bash
# Ze složky projektu:
php -S localhost:8000 -t . router.php
```

### 6. Přihlášení do administrace

Otevřete: **http://localhost:8000/admin**

Výchozí přihlašovací údaje:
- **E-mail:** `admin@example.com`
- **Heslo:** `admin123`

> Po přihlášení heslo změňte!

---

## Struktura projektu

```
seo-editor/
├── app/
│   ├── Core/           # Router, Controller, View, Database, Auth, Config
│   ├── Controllers/    # HTTP controllery
│   ├── Repositories/   # Databázové operace (PDO)
│   └── Services/       # Business logika (parser, renderer, publish...)
├── admin/
│   ├── index.php       # Entry point administrace
│   ├── assets/         # admin.css, admin.js
│   └── views/          # PHP views (dashboard, pages, templates, seo...)
├── config/             # app.php, database.php, seo.php
├── database/           # schema.sql, seed.sql
├── public/             # Veřejný front-end
├── storage/
│   ├── media/          # Nahrané obrázky
│   ├── published/      # Vygenerované statické HTML, sitemap, robots
│   └── logs/
├── vendor/             # Autoloader (nebo Composer)
└── .htaccess           # Apache routing
```

---

## Jak to funguje

### 1. Šablona

Nahrajte HTML šablonu s `data-block` atributy:

```html
<h1 data-block="hero_title">Původní nadpis</h1>
<p data-block="hero_text">Původní text</p>
<img data-media="hero_image" src="/assets/hero.jpg" alt="">
```

### 2. Stránka

Vytvořte stránku, vyberte šablonu. Systém automaticky parsuje bloky z HTML.

### 3. Editace

V admin panelu upravte obsah každého bloku.

### 4. SEO

Nastavte meta title, description, OG tagy, Twitter Card, schema.org JSON-LD.

### 5. Publikování

Klikněte **Publikovat** — systém vygeneruje hotový statický HTML soubor do `/storage/published/`.

---

## Výstup publikování

```
storage/published/
├── index.html
├── sluzby/
│   └── index.html
├── kontakt/
│   └── index.html
├── sitemap.xml
├── robots.txt
└── .htaccess          # Pro redirecty
```

---

## Bezpečnost

- PDO prepared statements
- CSRF tokeny pro všechny POST formuláře
- Validace uploadů (typ, velikost, MIME)
- `password_hash` / `password_verify`
- `session_regenerate_id` po přihlášení
- HTTPOnly session cookies
- XSS escapování výstupu

---

## PHP server — router.php

Pro lokální vývoj pomocí vestavěného PHP serveru:

```bash
php -S localhost:8000 router.php
```

---

## Roadmap (budoucí rozšíření)

- Vícejazyčnost stránek
- Verzování stránek
- Real-time preview
- FTP/Git deploy
- AI generování SEO textů
- Správa více webů
- WYSIWYG editor (Quill/TipTap)

---

## Licence

MIT


