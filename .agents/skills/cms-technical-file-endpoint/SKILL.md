---
name: cms-technical-file-endpoint
description: Přidání nového veřejného strojově čitelného souboru/endpointu (typu security.txt, llms.txt, robots.txt, humans.txt, .well-known/*) do tohoto SEO CMS — DB-driven konfigurace, vrstvení služeb, routing před Auth::start(), publish integrace, append-only migrace a testy. Aktivuj, když se má přidat nebo upravit technický soubor, well-known endpoint nebo jiný machine-readable výstup webu.
---

# Nový technický soubor / well-known endpoint v SEO CMS

Postup pro přidání DB-driven strojově čitelného souboru do jádra CMS.
Referenční implementace: RFC 9116 security.txt, commit `54e0068` — při nejasnosti
kopíruj vzory z `app/Services/SecurityTxt*.php` a souvisejících souborů.

## Postup

1. **Nastuduj oficiální specifikaci** (RFC, IANA, spec vendora) PŘED návrhem
   validátoru. Pravidla formátu, povinná pole a expirace ber ze specifikace,
   ne z paměti.

2. **DB model přes novou append-only migraci.** Zkontroluj
   `database/migrations/` a `database/schema.sql`, číslo migrace navaž na
   nejvyšší existující. Starší migrace nikdy neměň. Stejnou změnu propiš i do
   `database/schema.sql` (fresh install). Migrace má být idempotentní
   (`CREATE TABLE IF NOT EXISTS`, …).

3. **Vrstvení služeb** (jedna třída = jedna odpovědnost, vzor `SecurityTxt*`):
   - `app/Repositories/<X>Repository.php` — čtení/zápis configu; když tabulky
     chybí, vrací `schema_available = false` (viz Pitfally).
   - `app/Services/<X>Validator.php` — serverová validace vstupů (URI schémata,
     expirace, jazyky, injection do řádkově orientovaného formátu).
   - `app/Services/<X>Generator.php` — deterministický výstup: UTF-8, LF,
     stabilní pořadí polí.
   - `app/Services/<X>Service.php` — koordinace + souborová cache ve
     `storage/cache/<x>/`, invalidace po uložení v adminu.
   - `app/Services/<X>HttpResponder.php` — GET i HEAD, ETag + Last-Modified,
     stavy 200/304/404, `Content-Type: text/plain; charset=utf-8`. Výstup
     nikdy neprochází HTML layoutem.

4. **Veřejný routing v `public/index.php` PŘED `Auth::start()`.** Vzor:

   ```php
   $earlyUri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
   if (tryServeSecurityTxt($earlyUri)) {
       exit;
   }
   Auth::start();
   ```

   Kompatibilní alias (např. `/security.txt`) přesměruj 301 na kanonickou
   `.well-known` cestu **jen když je konfigurace zapnutá, platná a
   neexpirovaná** — jinak obě URL vracejí 404. Aktualizuj pořadí rout
   v `ARCHITECTURE.md` (číslovaný seznam request flow).

5. **Admin:** routy v `admin/index.php` (GET index + POST update), controller
   s `requireAdmin()` a CSRF tokenem, view v `admin/views/<x>/index.php`,
   odkaz v `admin/views/layouts/main.php`, styly do `admin/assets/admin.css`.
   Po uložení: invalidovat cache, přegenerovat statický publish výstup,
   obnovit `.htaccess`.

6. **Publish integrace v `app/Services/PublishService.php`:** generuj statický
   soubor do `storage/published/` (včetně `.well-known/` podadresáře); když je
   konfigurace vypnutá/neplatná, staré soubory **smaž** — nesmí zůstat zastaralý
   výstup. Podmíněné řádky v `.htaccess` (redirecty) přidávej jen při
   enabled+valid stavu.

7. **Testy do `tests/run.php`** (procedurální, bez frameworku): unit testy
   validátoru/generátoru, integrační testy responderu (GET, HEAD, 304,
   redirect, disabled → 404) a regresní kontrola stávajících technických rout.

8. **Dokumentace:** README (uživatelská sekce s curl ověřením),
   ARCHITECTURE.md (routing pořadí + nová sekce), UKOL.md přes /zadani,
   projektová wiki (skill work-wiki-knowledge-base).

## Pitfally (draze získané)

- **MySQL/MariaDB DDL dělá implicitní commit** — migrační runner nesmí balit
  DDL do transakce, jinak vznikne falešný pocit atomicity a při chybě zůstane
  poloviční schéma zapsané jako aplikované. `database/migrate-mysql.php` proto
  používá guard a zapisuje do `schema_migrations` až PO úspěšném SQL:

  ```php
  $transactionalDdl = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql';
  if ($transactionalDdl) {
      $pdo->beginTransaction();
  }
  ```

- **Endpoint před `Auth::start()`** — veřejný strojový soubor nesmí zakládat
  PHP session ani posílat session cookie. Handler musí běžet a `exit`nout dřív,
  než se session nastartuje.

- **Chybějící migraci nezakrývej defaultem** — repository vrátí
  `schema_available = false` a veřejný endpoint zůstane vypnutý (404). Tichý
  default by maskoval nasazení bez migrace.

- **Alias redirect jen při platné konfiguraci** — trvalé 301 z aliasu na
  kanonickou cestu se smí posílat jen když endpoint reálně funguje; jinak 404
  na obou cestách (jinak vznikne redirect do 404).

- **Sync statického výstupu při vypnutí** — publish musí umět soubor nejen
  vytvořit, ale i odstranit (`removeSecurityTxtFile` vzor), včetně legacy cest.

## Ověření

```bash
php database/migrate-mysql.php   # spustit 2x — druhý běh musí být čistý SKIP
php tests/run.php
php -l <každý nový/změněný PHP soubor>
curl -i  https://example.com/.well-known/<soubor>
curl -I  https://example.com/.well-known/<soubor>   # HEAD
curl -I  https://example.com/<alias>                # redirect / 404
```

Commit dle INSTRUCTIONS.md: `feature(core): …`, bez AI atribucí.
