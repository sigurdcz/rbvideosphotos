# Staging na Active24

Staging je samostatný web [staging.rbvideosphotos.cz](https://staging.rbvideosphotos.cz). Push větve `dev` spouští `Deploy to Active24 (staging)`. Produkci nasazuje pouze `master`. Nový merge/push do `master` vyžaduje výslovný souhlas uživatele s konkrétní změnou. Nepoužíváme druhou větev pojmenovanou `staging`.

## 1. Povinné nastavení hostingu

1. V Active24 vytvoř subdoménu `staging.rbvideosphotos.cz` a ověř její DNS a platný HTTPS certifikát. Musí obsluhovat vlastní adresář `/rbvideosphotos.cz/sub/staging/`, nikoli produkční `/rbvideosphotos.cz/web/`.
2. Aktivuj PHP s PDO MySQL, GD (včetně WebP), mbstring, OpenSSL a zapisováním do `storage/` a `public/assets/generated/`. Produkce běží na PHP 8.5; workflow sestavuje kompatibilní PHP 8.1 zdroje.
3. Použij samostatný FTP účet `staging.rbvideosphotos.cz` omezený přímo na staging adresář. Připojení musí po přihlášení ukázat jeho obsah jako `/`. Hlavní FTP účet s přístupem k celému hostingu pro Actions nepoužívej.
4. U tohoto FTP účtu nesmí GEO/IP ochrana blokovat GitHub runner. Funkční přihlášení z Total Commanderu v ČR samo přístup runneru nepotvrzuje.
5. Před zveřejněním testovacího obsahu zapni ochranu stagingu heslem na úrovni hostingu a ověř, že nepřihlášený návštěvník dostane HTTP 401. Staging nemá automatickou ochranu před indexací; samotný název subdomény ani robots.txt ji nezaručí. Pokud hostingová ochrana není dostupná, je to nedokončený krok a je potřeba ochranu výslovně dořešit.

## 2. Povinné GitHub Environment Secrets

Otevři repozitář → Settings → Environments → **staging** → Add environment secret:

| Název | Hodnota pro staging |
| --- | --- |
| `FTP_SERVER` | `37.9.175.211` nebo aktuální FTP hostname z Active24; bez `ftp://`, cesty a bez `:21` |
| `FTP_USERNAME` | `staging.rbvideosphotos.cz` (ověř přesný login v Active24) |
| `FTP_PASSWORD` | Heslo tohoto staging FTP účtu |

Environment `staging` je založený s povolenou deployment větví `dev`. Production má své vlastní secrets. **Nekopíruj produkční FTP login do stagingu.** Port 21, FTPS a `server-dir: /` už jsou ve workflow. Absolutní cestu `/rbvideosphotos.cz/sub/staging/` do workflow nepřidávej: vytvořila by vnořenou složku v kořeni účtu.

GitHub Variables ani DB/SMTP secrets workflow nyní nepotřebuje. Aplikační `.env` se na server nahrává samostatně a deployment ho nepřepisuje. Hodnoty z GitHub secrets se do něj automaticky nepropíší.

## 3. Povinná samostatná databáze

V Active24 vytvoř a aktivuj **novou MariaDB databázi** např. `rbvideosphotos_staging` a samostatného uživatele s právy na tuto databázi včetně CREATE/ALTER pro migrace. Nestačí zadat název do `.env`. Nepoužívej produkční DB ani prefixy tabulek (CMS prefixy nepodporuje).

Z detailu nové DB opiš skutečný host, jméno a uživatele. Pro tento hosting byl uveden `db.r4.active24.cz`, port `3306`. Povolení spojení/IP musí zahrnovat webový server; GitHub runner DB nepoužívá. Databázové heslo není FTP ani poštovní heslo.

## 4. Povinný serverový `.env`

Přes staging FTP nahraj soubor **přesně `.env`** do kořene účtu vedle `index.php`, `composer.json`, `.htaccess` a složky `public`. Ne `env`, ne `.env.txt`, ne dovnitř `public`.

```dotenv
APP_URL=https://staging.rbvideosphotos.cz
APP_DEBUG=false
MIGRATE_TOKEN=VLOZ_NOVY_NAHODNY_TOKEN_PRO_STAGING
API_KEY=VLOZ_JINY_NOVY_NAHODNY_KLIC_PRO_STAGING

DB_DRIVER=mysql
DB_HOST=db.r4.active24.cz
DB_PORT=3306
DB_NAME=rbvideosphotos_staging
DB_USER=SKUTECNY_UZIVATEL_STAGING_DB
DB_PASS="SKUTECNE_HESLO_STAGING_DB"

SMTP_HOST=smtp.websupport.cz
SMTP_PORT=465
SMTP_ENCRYPTION=ssl
SMTP_USERNAME=team@rbvideosphotos.cz
SMTP_PASSWORD="HESLO_K_POSTOVNI_SCHRANCE"
MAIL_FROM=team@rbvideosphotos.cz

# Testovaci prostredi neposila produkcni analytics.
GA4_ID=
GTM_ID=
CLARITY_ID=
META_PIXEL_ID=
```

Všechny zástupné hodnoty nahraď. Tokeny generuj jednotlivě např. `php -r "echo bin2hex(random_bytes(32));"`. Nepřebírej produkční tokeny, API klíč ani DB účet. SMTP host potvrď v detailu konkrétní schránky; jde o autentizované odesílání, ne PHP mail(). Heslo uvozovkuj; při speciálních znacích ověř pravidla načítání `.env`. Tajemství nepatří do Gitu ani screenshotů.

## 5. První přenos a inicializace

1. Po uložení secrets pushni `dev`, nebo v Actions vyber staging workflow → Run workflow → větev **dev**. Při chybějícím secretu skončí kontrola s jeho názvem, bez výpisu hodnot.
2. Počkej na zelené FTPS nasazení. V kořeni staging FTP musí být přímo `index.php`, `.htaccess`, `public`, `app`, `vendor` — žádná další vnořená kopie `rbvideosphotos.cz/sub/staging`. `.htaccess` směruje na `public/index.php`; do APP_URL nepřidávej `/public`.
3. Z vlastního prohlížeče otevři `https://staging.rbvideosphotos.cz/migrate-util.php?token=TVUJ_STAGING_MIGRATE_TOKEN`. Nejprve vznikne SQL schéma (včetně 103–105). Obsahové migrace bez webu mohou být výslovně odložené.
4. Na prázdné tabulce users vytvoř prvního admina přes `/bootstrap-admin.php` (staging token, vlastní login a heslo 12–72 bajtů). Existující účty bootstrap nepřepisuje.
5. Přihlas se na `/admin/login` → `/admin/setup`, spusť základní seed a podle potřeby dávkový demo obsah. **Armagedon není součást běžného nasazování**, maže tabulky a runtime. Seed může obnovit výchozí účet `admin@example.com` / `admin123`; ihned nastav vlastní heslo přes `/admin/account/password`.
6. Po dokončení obsahu znovu spusť migrace: obsahové opravy už najdou web i galerie. Chyba 055 s chybějící galerií znamená nedokončený předpokládaný dataset; nepřeskakuj migraci a neoznačuj ji za hotovou.
7. V administraci nastav správnou staging doménu, vyber Golden Bash Diagonal, ulož skutečný **E-mail pro příjem formulářů** a globální obrázky pro sdílení. Příjemce mailů i obnovy hesla je nastavení administrace, nikoli SMTP login. Pro staging zvol svou testovací schránku. Zkontroluj i uložená analytics/tracking nastavení; kopie produkční DB může stále obsahovat produkční ID.
8. Publikuj obsah. Pro existující inicializovaný web lze po migracích použít `&publish=1`. Nový staging nemá produkční uploady: deployment záměrně nepřenáší `storage/media`, cache ani publikované stránky. Demo musí doběhnout; vlastní média nahraj samostatně.

## 6. Ověření a další aktualizace

- Ověř správnou doménu v adresním řádku, homepage a `/kontakt`, admin login, motiv a načtení médií. HTTP 404 před seedem není důkaz chyby FTP.
- Zkontroluj sdílecí metadata: stránky bez vlastního override mají globální OG/Twitter obrázek. Migrace 105 odstraňuje stará jednotlivá nastavení; po ní obnov statický publish.
- Odešli vlastní testovací kontakt, ověř `/admin/messages` a skutečné doručení. `accepted` potvrzuje pouze přijetí SMTP serverem. Při selhání je kontakt uložen v DB; neúspěšná obnova hesla vyžaduje nový pokus po opravě SMTP.
- Ověř obnovu na `/admin/forgot-password` a odkaz mířící na staging. Login účtu pouze identifikuje účet; cílem je uložený e-mail z Nastavení.
- Při problému čti čerstvý `storage/logs/app-YYYY-MM-DD.log` / exception log. Sdílej pouze očištěné řádky bez tokenů. Prázdný log může znamenat nezapisovatelný adresář nebo jinou instalaci.
- Před každou další DB aktualizací udělej zálohu. Push `dev` přenese pouze kód, pak spusť případné migrace a publish. Obnova staršího kódu sama nevrací DB; potřebuje kompatibilní schéma nebo odpovídající zálohu.
- Teprve po ověření stagingu si vyžádej souhlas uživatele s konkrétním merge do `master`. GitHub automatické slučování nepoužívej. Otevřené navazující kroky jsou v `BACKLOG.md`.

## Stav při aktivaci 16. 10. 2026

Produkční push OG opravy `fe561ed` má úspěšné Actions [38959519402](https://github.com/sigurdcz/rbvideosphotos/actions/runs/38959519402). Produkční migrace 105 se deploymentem automaticky nespouští. Environment staging byl vytvořen; při první kontrole neměl žádné vlastní FTP secrets. Přenos, inicializace DB a ověření funkčního stagingu jsou samostatné kroky — zelená kontrola přenosu je nenahrazuje.

První dev push `bd097d3` spustil [38959853911](https://github.com/sigurdcz/rbvideosphotos/actions/runs/38959853911): kontrola secrets selhala před checkoutem, žádné soubory se nepřenesly. Po doplnění všech tří secrets použij Re-run all jobs u tohoto běhu. Staging adresa zatím vrací HTTP 200 s titulkem „Brzy jsme zpět“; nový CMS tím není ověřený.

Aktualizace: po doplnění/opravě FTP secrets uspěl [pokus 5 běhu 38960080119](https://github.com/sigurdcz/rbvideosphotos/actions/runs/38960080119/attempts/5). První úplný přenos dokončen 16. 10. 2026. Předchozí popis chybějících secrets je historie; další bránou je serverový `.env`, vlastní DB a inicializace podle kroků výše.
