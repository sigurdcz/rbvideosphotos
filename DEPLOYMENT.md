# Active24 deployment

Aktualni aplikacni zdroje pochazeji ze `sigurdcz/seo-cms`, commit `b8da8da02878735ef10c506fb75c43cf41984542`. Hosting tohoto repozitare je Active24. Postupy a adresy puvodniho Webzdarma workflow se zde nepouzivaji.

## GitHub Actions

- `master`: environment `production`, FTPS do `/`.
- `dev`: environment `staging`, FTPS do `/`.
- Kazdy FTP ucet musi byt omezen na vlastni cilovou slozku; absolutni cestu hostingu workflow nepridava.
- Environment Secrets: `FTP_SERVER` (pouze hostname/IP), `FTP_USERNAME`, `FTP_PASSWORD`.
- FTPS port 21, Composer produkcni zavislosti a samostatny `_deploy` artefakt.
- `.env`, media, cache, logy, publikovany obsah a generovane assety patri serveru a neprenaseji se.
- Lokalni `.agents`, `.codex`, `.codex-qa`, `.tmp*`, zaloha `.htaccess` a testy se do artefaktu nedostanou. Prenesene docasne PHP helpery navic odmitaji HTTP a bezi jen pres CLI.
- GitHub Actions zde neprovadi automaticky migrace ani reset databaze. `deploy-maintenance.php` zustava neaktivni placeholder HTTP 404.

## Aktualizace databaze

1. Over, ze serverovy `.env` odkazuje na databazi spravneho prostredi.
2. Pro novou verzi spust `/migrate-util.php?token=<MIGRATE_TOKEN>` z vlastniho prohlizece.
3. Utility aplikuji append-only migrace. Existujici 001–023 jsou beze zmen; pribyvaji 024–102.
4. Sdileny `database/migration-schema-state.php` rozpozna zname historicke prekryvy a viditelne je zapise do evidence. Jine SQL chyby se neignoruji.
5. Obsahove application-compatible migrace bez existujiciho webu zustanou nezapsane a utility je explicitne uvede jako odlozene. Po zakladnim seedu spust migrace znovu.
6. Pro existujici web lze po uspesnych migracich pouzit `&publish=1` k uplnemu publishi. Nespoustej Armagedon pro beznou aktualizaci.

## Prazdna instalace

Po vytvoreni tabulek otevri `/bootstrap-admin.php`. POST formular vyzaduje serverovy MIGRATE_TOKEN, session CSRF, vlastni email a heslo 12–72 bajtu. DB zamek serializuje vytvareni; neprázdná tabulka users vytvoreni znemozni bez zmeny existujicich uctu.

Po prihlaseni na `/admin/login` pouzij `/admin/setup`. Na opravdu prazdne instalaci lze inicializovat zakladni a pak davkovy demo obsah. Armagedon smaze DB tabulky i runtime obsah a seed obnovi vychozi prihlaseni `admin@example.com` / `admin123`; heslo potom zmen. Golden Bash Diagonal je vychozi motiv noveho seedu, existujici explicitni volba jineho motivu se automaticky neprepisuje.

## Overeni

- `php tests/run.php`: regrese a HTML goldeny nad lokalni fixture DB.
- `php tests/InitialAdminTest.php`: autorizace, jednorazovost, heslo a zamek bootstrapu s PDO double.
- `php bin/seo-smoke.php https://rbvideosphotos.cz`: produkcni kontrola po migraci a publishi; dokud web nema inicializovany obsah, HTTP 404/500 je nezavrena brana.
- `.gitattributes` sjednocuje LF zdroju, aby textove kontrakty fungovaly i na Windows.

Obsahove PHP handlery se vzdy provadeji az po dokonceni vsech SQL migraci, i kdyz web uz existuje. Aktualni handler starsi migrace muze pouzivat schema_name z 079; jednopruschodove poradi proto neni bezpecne. Regresni overeni: php tests/MigrationUpgradeOrderIntegrationTest.php vytvori vlastni nahodnou DB, spusti skutecne SQL a nahradi pouze obsahove efekty kontrolou schema zavislosti.

## PHP 8.5 a davkove JSON odpovedi

`ImageVariantService` uvolnuje reference na `GdImage` pres `unset`. `imagedestroy()` je od PHP 8.0 bez ucinku a od PHP 8.5 deprecated; pri debug vypisu by varovani porusilo JSON odpoved davky. Zastarala volani se odstranuji, chyby se nepotlacuji. Formular rozlisuje neplatny JSON, chybu aplikace a vyprsene prihlaseni; nedohledava JSON uvnitr poskozene odpovedi. Test klienta: `node tests/DemoSeedResponseTest.js`. Oficialni reference: https://www.php.net/manual/en/function.imagedestroy.php.

## Obnova hesla administrace

Po nasazení spusťte migrace (`103_add_password_resets.sql` přidává tabulku jednorázových tokenů). Na `/admin/login` je odkaz **Zapomenuté heslo**. Uživatel zadá skutečný e-mail svého CMS účtu; odkaz se posílá právě na tento e-mail, nikoli na kontaktní adresu webu. Účet se tím nevytváří a jiné účty se nemění.

Serverové `.env` musí mít správné `APP_URL=https://vase-domena.cz`. Volitelné `MAIL_FROM=noreply@vase-domena.cz` určuje odesílatele; výchozí je `noreply@` + doména APP_URL. PHP `mail()` používá stejnou službu hostingu jako kontaktní formulář. Návrat true potvrzuje předání poště, nikoli doručení do schránky. Při false/selhání se zobrazí chyba a token se odstraní; ověřte poštu na hostingu a spam. Účet `admin@example.com` nemůže přijmout skutečný reset e-mail: jeho adresu nejprve změňte v tabulce users na svou skutečnou adresu.

Odkaz platí 30 minut, nové vyžádání ruší předchozí a použití je atomické a jednorázové. Nové heslo má 12–72 bajtů. Po změně jsou staré přihlášené relace odmítnuty při dalším požadavku; první nasazení této ochrany také odhlásí starší relace. Žádosti mají CSRF a limit 3/15 minut podle IP i e-mailu. Neexistující účet dostane stejnou obecnou odpověď; chybu transportu zobrazujeme explicitně. Tokeny se ukládají pouze jako SHA-256; hesla jako bcrypt. Odkaz v e-mailu obsahuje tajný token, proto nesdílejte e-mail ani syrové serverové access logy (aplikace token v URI maskuje).

Ověření vývoje: `php tests/PasswordResetIntegrationTest.php` vytváří a v finally odstraňuje pouze vlastní náhodnou testovací databázi, vyžaduje lokální DB účet s CREATE/DROP DATABASE. Produkční doručení ověřte vlastní e-mailovou schránkou; testy reálné e-maily neposílají.
