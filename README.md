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
- `SCREENSHOT-WORKFLOW.md` - aktualni Chrome/CDP capture postup pro jednotlive routy i cely sitemap inventar
- `VISUAL_AUDIT_REPORT.md` - posledni uplny vizualni audit vsech verejnych URL a odkazy na screenshoty

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
| Golden Bash | `golden-bash` |
| Golden Bash Diagonal (vychozi) | `golden-bash-diagonal` |
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

Kanonicky vychozi motiv pro nove instalace, Demo seed i resolver bez explicitni volby je `golden-bash-diagonal`. Append-only migrace `066_set_golden_bash_diagonal_default_theme.sql` na existujicich webech prevede prazdny stav a drivejsi default `golden-bash`; jine rucne zvolene motivy zachova.

`Golden Bash Diagonal` je plna izolovana kopie `Golden Bash`. Obsahovy a funkcni kontrakt zustava shodny; jedinym vizualnim rozdilem je homepage hero, kde desktop a tablet nahrazuji puvodni V/krivkovy vyrez primou diagonalou od 27 % sirky nahore k 55 % dole. Mobil zachovava puvodni full-bleed video bez orezu.

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

Produkční workflow po FTPS deployi vytvoří jednorázový náhodně pojmenovaný vstupní bod, který načte `MIGRATE_TOKEN` přímo z produkčního `.env`, zavolá stejnou utilitu s `publish=1` a po požadavku se sám odstraní. Díky tomu se nedestruktivní migrace, úplný publish a SEO smoke provedou při každém produkčním nasazení, aniž by se produkční token kopíroval do GitHub Secrets nebo vypisoval do logu. Chybějící produkční `MIGRATE_TOKEN`, chyba migrace, publish nebo následného SEO smoke ukončí deployment jako neúspěšný.

Příčina původního pádu GitHub Actions, odstranění chybného požadavku na duplicitní GitHub secret a celý bezpečnostní kontrakt jsou popsány v [DEPLOYMENT.md](DEPLOYMENT.md).

Seed z adminu:

```text
http://localhost:8000/admin/setup
```

Setup obrazovka nabizi dve navazujici operace pro referencni stav RB Videos & Photos:

1. `Armagedon` smaze tabulky aktualni databaze i renderovaci filesystem stav, vytvori schema z migraci, spusti `database/seeds/seed.php` a nakonec aplikuje odlozene application-compatible migrace. Obsahova migrace 055, ktera vyzaduje kompletni Demo dataset, se pri cistem resetu pouze oznaci bezvysledkovym SQL markerem; aktualni obsah vytvori navazujici Demo seed. Samotnou databazi Armagedon nevytvari ani nemaze, takze funguje i pod omezenym Webzdarma uctem bez `CREATE TEMPORARY TABLES`.
2. `Doplnit demo obsah` spusti persistentni davkovou ulohu vhodnou i pro verejny shared hosting bez CLI. Modal zobrazuje pocet hotovych davek a procenta; jeden request importuje nejvyse jedno zakladni medium, jeden svatebni clanek nebo jednu portfolio galerii. Stav zustava v `demo_seed_jobs`, po reloadu se automaticky obnovi a selhala davka se opakuje tlacitkem bez preskoceni chyby. Po vsech 73 jednotkach se aktivuje `Golden Bash Diagonal` (`golden-bash-diagonal`), web publikuje a ulozi se finalni fingerprint.

Pred pouzitim produkcniho tlacitka musi byt aplikovana append-only migrace `063_create_demo_seed_jobs.sql`. Start, step i retry jsou POST operace chranene prihlasenim administratora a CSRF; status je dostupny pouze prihlasenemu administratorovi. Pro jeden web muze bezet jen jedna uloha a petiminutovy lease zabranuje soubehu dvou requestu.

Produkční deploy nikdy automaticky nespouští Armagedon ani Demo reset. Jednorázový autentizovaný maintenance wrapper aplikuje pouze čekající migrace a úplný publish; prázdný nebo neznámý databázový stav skončí viditelnou chybou. Havarijní fázovaná obnova z 10. 8. 2026 byla po zeleném běhu odstraněna. Podrobnosti jsou v [DEPLOYMENT.md](DEPLOYMENT.md).

Hlavni seed i append-only migrace `064_enable_cookie_consent_summary.sql` nastavuji cookie consent jako zapnuty. Pri chybejici volbe se zobrazuje jen kompaktni neblokujici lista; stranka zustava pouzitelna bez blur a detailni modal kategorii se otevre az po kliknuti na `Nastaveni`. Administrace muze consent vedome vypnout, ale seed ani produkcni upgrade jej uz nenechaji nechtene skryty.

Hlavni i Demo seed take nastavuji defaultni hero poster na nahranou svatebni fotografii. Homepage tak ma obrazove pozadi i bez nastaveneho hero videa. Vnitrni cream hero sada pro FAQ, Cenik, Kontakt, Blog, Sluzby, O nas a Hodnoceni, podporovane PNG favicony a tri PNG loga se ukladaji do `database/seeds/demo_assets` a importuji stejnou media pipeline jako bezny upload; samotne soubory v `public/assets/demo` nejsou runtime zdrojem. `favicon.ico` a `site.webmanifest` zustavaji staticke assety, nikoli polozky Media Library.

Referencni portfolio se pri Demo seedu kompletne nahrazuje deviti DB galeriemi a 33 fotografiemi ze `public/assets/demo/dalsifotkydoportfolia`. `PortfolioDemoContentImporter` pred smazanim stareho portfolia overi presne mapovani vsech souboru, kazdou fotografii posle pres `MediaIngestionService` a vyzaduje thumbnail variantu. V davkove ceste se importuje jedna galerie na request; opakovani ztraceneho checkpointu nahradi pouze stejny kanonicky slug. Chybejici, duplicitni nebo neprirazena fotografie seed viditelne zastavi. Skutecny stav po resetu overi `php bin/verify-demo-portfolio.php`; ocekavany vysledek je 9 galerii, 33 fotografii a 0 starych zaznamu.

Obsahovy SEO upgrade existujiciho webu vlastni append-only migrace `055_apply_content_seo_remediation.sql`. Produkcni maintenance ji provede pres `ApplicationCompatibleMigrationService` a `DemoContentSeeder::restoreContentSeoRemediation()`: pred zapisem overi realna media, atomicky obnovi servisni a blogovy obsah, upravi metadata a portfolio alt texty a vytvori pet 301 redirectu ze zkracenych svatebnich URL. Chybejici primarni data nejsou nahrazena placeholderem. `SeoRenderer` nevykresluje `meta keywords`, detail blogu ani portfolia neopakuje globalni procesni blok a homepage zustava bez cen.

Verejne HTML nikdy neodkazuje primo na chranenou fyzickou cestu `/storage/media/`. OG, Twitter a oba LocalBusiness JSON-LD zaznamy pouzivaji absolutni URL pres `/media/`, kterou obsluhuje verejny media controller. `LocalBusiness.image` je vzdy totozny s vyslednym `og:image`; bez vybraneho OG obrazku se zadny hero ani nahodny default nedosazuje. Stejny soubor pod `/storage/media/` muze byt hostingem spravne zakazan stavem 403.

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
- Po nasazeni spust `php bin/seo-smoke.php https://kanonicka-domena.example`; nastroj konci kodem 1 pri 4xx, noindexu, chybejicich/duplicitnich SEO tazich, neplatnem JSON-LD, chybe sitemap nebo rozbitem internim odkazu. Volitelny CA bundle se predava druhym argumentem nebo pres `SEO_SMOKE_CA_BUNDLE`; SSL verifikace se nevypina.
- GitHub Actions nenasazuje obsah `storage/media`, `storage/published`, `storage/active-theme-key.txt` ani `public/assets/generated`; tyto cesty vlastni cilove prostredi a obnovuje je Armagedon/Publish pipeline
- Konverzni tracking a consent flow jsou popsane v `ARCHITECTURE.md`

## Dalsi dokumentace

- Chces API kontrakty: otevri `API.md`
- Chces request flow, tabulky a interni sluzby: otevri `ARCHITECTURE.md`
- Chces obsluhu GTM adminu: otevri `GTM_ADMIN.md`
- Chces evidenci znamych blockeru a odlozenych zmen: otevri `TECH_DEBT.md`
- Chces contribution guardrails: otevri `INSTRUCTIONS.md`

## Editace sdileneho obsahu a portfolia

- `/admin/shared-sections` spravuje obsah pouzity na vice routach. Proces `Jak to probiha` ma jeden JSON datovy zdroj v DB.
- `/admin/portfolio` u kazde polozky nastavuje typ `Fotogalerie` nebo `Videogalerie` a ciselne `Poradi`; nizsi hodnota se zobrazi drive, shodu rozhodne stabilne ID.
- Hero headline/subtitle zustavaji page bloky. Homepage kontaktni obraz je editovatelny blok `contact_visual`.
- Homepage hero podporuje YouTube/Vimeo i primy MP4/WebM/MOV. Prime video pouziva `autoplay muted playsinline loop`; poster je fallback.

## Active24 deployment a prvni instalace

Tento repozitar prebral aktualni CMS ze `sigurdcz/seo-cms`, commit `b8da8da02878735ef10c506fb75c43cf41984542`. Produkcni a staging runtime data zustavaji oddelena.

- GitHub `master` -> environment `production`; `dev` -> environment `staging`.
- V kazdem environment nastav Secrets `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`. Server je pouze hostname/IP, bez protokolu a portu.
- Obe workflow pouzivaji FTPS port 21 a `server-dir: /`. FTP ucet musi mit koren primo v cilovem adresari daneho prostredi.
- `.env` vytvor na serveru vedle hlavniho `index.php`. Workflow jej ani databazi, media, publikovany obsah a generovane assety neprepisuje. Root `.htaccess` blokuje HTTP pristup k `.env`.
- Po aktualizaci spust `/migrate-util.php?token=<MIGRATE_TOKEN>`; nove migrace jsou append-only 024–102. Schema-state kontroly jsou sdilene s CLI pres `database/migration-schema-state.php` a uz splnene migrace se oznaci viditelne. Obsahove migrace bez existujiciho webu zustanou explicitne cekajici, dokud neprobehne seed.
- Na prazdne instalaci otevri `/bootstrap-admin.php`, zadej `MIGRATE_TOKEN` do POST formulare, e-mail a vlastni heslo (12–72 bajtu). CSRF a DB advisory lock chrani vytvoreni; pri neprázdné tabulce users bootstrap odmítne zmenu. Pak se prihlas na `/admin/login` a pouzij `/admin/setup`.
- Armagedon maze runtime data i DB a obnovi zakladni seed. Nasledne pouzij `admin@example.com` / `admin123` a zmen vychozi heslo. Demo obsah se v nove verzi zpracovava po davkach. Nespoustej reset pro beznou aktualizaci existujiciho obsahu.
- Golden Bash Diagonal je kanonicky vychozi motiv noveho CMS. Existujici rucni volbu jineho motivu migrace zachova; motiv vyber v administraci.
- Chyba vykresleni se loguje jako `frontend-render` a vraci HTTP 500 pres Runtime, nikoli skrytou 404.

Doplnkove overeni bootstrapu: `php tests/InitialAdminTest.php`. Testy vykresleni porovnavaji snapshoty s aktualni lokalni DB; rozdily dat nejsou duvodem automaticky prepsat goldeny. `.gitattributes` drzi zdrojove soubory v LF i na Windows.
