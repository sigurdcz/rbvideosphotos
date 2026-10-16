# SEO Mini CMS

Jednoduche SEO CMS pro male staticke weby v cistem PHP 8. Aplikace ma admin rozhrani pro spravu obsahu, SEO, medii, temat a tracking konfigurace. Verejny web se servira bez frameworku a bez buildu.

## Dokumentacni mapa

- `README.md` - rychly onboarding, provozni minimum a rozcestnik
- `ARCHITECTURE.md` - technicka architektura, request flow, datovy model a systemove chovani
- `API.md` - REST API reference pro clanky, portfolio a media
- `GTM_ADMIN.md` - provozni navod k `/admin/gtm`
- `INSTRUCTIONS.md` - contribution pravidla pro vyvoj a AI asistenty
- `analytics.md` - staticky snapshot predane GA4 analytiky; urcuje prioritni testovaci viewporty podle realne navstevnosti
- `qa-testing.md` - povinny QA postup pro verejny runtime na telefonu `390 x 844` CSS px a PC `1920 x 1080` CSS px
- `SCREENSHOT-WORKFLOW.md` - primarni Chrome screenshot workflow; VS Code Integrated Browser je jen diagnosticka historie

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
- Media pipeline s variantami `webp`, `medium`, `thumb` a Open Graph cropem `og`
- Publish flow pro sitemap, robots, llms.txt, security.txt a portfolio detail/listing exporty
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
| Elegant White 2 | `elegant-white2` |
| Elegant White 3 | `elegant-white3` |
| ETHERNA | `etherna` |
| Béžová Lens & Tale | `bezova-lens-tale` |

Aktivni tema se nastavuje v `Nastaveni -> Tema`. Technicke chovani theme aplikace je v `ARCHITECTURE.md`.

## Security.txt

CMS umi generovat strojove citelny kontakt pro odpovedne hlaseni zranitelnosti podle [RFC 9116](https://www.rfc-editor.org/rfc/rfc9116.html).

- Nastaveni: `/admin/security-txt` (pouze role `admin`)
- Kanonicky endpoint: `/.well-known/security.txt`
- Kompatibilni endpoint: `/security.txt` -> trvaly redirect na kanonickou cestu
- Povinny obsah pri zapnuti: alespon jeden `Contact` a prave jeden budoucí `Expires`
- Vychozi expirace nove konfigurace: 180 dnu; ulozena hodnota se pri HTTP requestech automaticky neposouva
- Vypnuta, neplatna nebo expirovana konfigurace vraci na obou URL 404

Volitelne lze nastavit `Canonical`, `Encryption`, `Policy`, `Acknowledgments`, `Preferred-Languages`, `Hiring` a komentare. Webove odkazy musi byt HTTPS. Do `Encryption` patri pouze URL verejneho klice, nikdy soukromy klic ani vlozeny klicovy blok.

Zverejneni `security.txt` samo o sobe neudeluje opravneni k bezpecnostnimu testovani. Rozsah a pravidla patri do odkazovane bezpecnostni politiky.

Overeni HTTP odpovedi:

```bash
curl -i https://example.com/.well-known/security.txt
curl -I https://example.com/.well-known/security.txt
curl -I https://example.com/security.txt
```

Automaticke testy generatoru a endpointu:

```bash
php tests/run.php
```

## Pozadavky

- PHP 8.0+
- MySQL 8+ nebo MariaDB 10.4+
- Rozsireni pro bezny provoz: `PDO`, `pdo_mysql`, `DOM`, `mbstring`, `fileinfo` a `GD`
- Pro GTM administraci navic: `curl` a `OpenSSL`

`Imagick` muze pomoct s detekci podpory WebP, ale aktualni generator obrazovych variant pouziva funkce z rozsireni `GD`; samotny Imagick proto GD nenahrazuje.

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
| `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD` | Nasazeni pres FTP/FTPS |

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

Setup obrazovka nabizi dve navazujici operace pro referencni stav RB Photos & Videos:

1. `Armagedon` smaze databazi i renderovaci filesystem stav, spusti vsechny migrace a `database/seeds/seed.php`.
2. `Doplnit demo obsah` nahraje referencni obsah, explicitne aktivuje `Elegant White` (`elegant-white`, verze `1.0.0`) a web publikuje.

Hlavni seed nastavuje cookie consent listu jako vypnutou. Analyticka a marketingova kategorie zustavaji povolene, takze nakonfigurovane merici nastroje mohou bezet bez consent dialogu. Prepinac lze pozdeji zmenit v admin sekci Nastaveni.

Hlavni i Demo seed take nastavuji defaultni hero poster na nahranou svatebni fotografii. Homepage tak ma obrazove pozadi i bez nastaveneho hero videa.

Po dokonceni porovnej `Demo state fingerprint` na lokalnim a online `/admin/setup`. Shodny SHA-256 znamena shodny kanonicky datovy stav; domena, nahodne fyzicke nazvy medii a casy publikace jsou z porovnani zamerne vynechane.

Seed z CLI:

```bash
php database/seeds/seed.php
php database/seeds/seed_photographer.php
php database/seeds/seed_photographer.php --fresh
```

Kompletni lokalni `Armagedon -> Demo obsah` lze spustit stejnou sdilenou pipeline jako v administraci:

```bash
php bin/reset-demo-local.php --confirm-local
php bin/demo-state-fingerprint.php --hash
```

Prikaz reset tvrde odmita databazi, jejiz `DB_HOST` neni `127.0.0.1`, `localhost` nebo `::1`. Nikdy jej nepouzivej jako produkcni deploy prikaz.

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
- `storage/published/llms.txt` a aktivni `.well-known/security.txt` se generuji pri publishi spolu se sitemap a portfolio vystupy
- GitHub Actions nenasazuje obsah `storage/media`, `storage/published`, `storage/active-theme-key.txt` ani `public/assets/generated`; tyto cesty vlastni cilove prostredi a obnovuje je Armagedon/Publish pipeline
- Konverzni tracking a consent flow jsou popsane v `ARCHITECTURE.md`

## Dalsi dokumentace

- Chces API kontrakty: otevri `API.md`
- Chces request flow, tabulky a interni sluzby: otevri `ARCHITECTURE.md`
- Chces obsluhu GTM adminu: otevri `GTM_ADMIN.md`
- Chces contribution guardrails: otevri `INSTRUCTIONS.md`

## GitHub Actions deployment

Repozitar: `https://github.com/sigurdcz/rbvideosphotos`.
Push vetve `master` spousti produkcni FTPS nasazeni do `/rbvideosphotos.cz/web/` s GitHub Environment `production`.
Push vetve `dev` spousti staging FTPS nasazeni do `/web/dev/` s GitHub Environment `staging`.
V kazdem environment nastav Secrets `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD` (nikoli pouze Variables).
Workflow lze spustit i rucne. Pouziva FTPS na portu 21.
Deployment prenasi aplikacni kod a Composer zavislosti; databazi, media, publikovany obsah ani `.env` neprenasi.
Na novem hostingu je potreba samostatne pripravit `.env`, databazi a obsah.
