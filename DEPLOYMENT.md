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

Serverové `.env` musí mít správné `APP_URL=https://vase-domena.cz` a SMTP konfiguraci popsanou níže. Obnova hesla i kontaktní formulář používají autentizované SMTP, bez návratu k PHP `mail()`. Při selhání se zobrazí chyba a reset token se odstraní. Účet `admin@example.com` nemůže přijmout skutečný reset e-mail: jeho adresu nejprve změňte v tabulce users na svou skutečnou adresu. Příjemce resetu je e-mail účtu, odesílatel je SMTP schránka.

Odkaz platí 30 minut, nové vyžádání ruší předchozí a použití je atomické a jednorázové. Nové heslo má 12–72 bajtů. Po změně jsou staré přihlášené relace odmítnuty při dalším požadavku; první nasazení této ochrany také odhlásí starší relace. Žádosti mají CSRF a limit 3/15 minut podle IP i e-mailu. Neexistující účet dostane stejnou obecnou odpověď; chybu transportu zobrazujeme explicitně. Tokeny se ukládají pouze jako SHA-256; hesla jako bcrypt. Odkaz v e-mailu obsahuje tajný token, proto nesdílejte e-mail ani syrové serverové access logy (aplikace token v URI maskuje).

Ověření vývoje: `php tests/PasswordResetIntegrationTest.php` vytváří a v finally odstraňuje pouze vlastní náhodnou testovací databázi, vyžaduje lokální DB účet s CREATE/DROP DATABASE. Produkční doručení ověřte vlastní e-mailovou schránkou; testy reálné e-maily neposílají.

## Archiv kontaktních zpráv a stav pošty

Po nasazení spusťte migrace včetně **104_add_mail_archive.sql**. V administraci přibyla stránka **Zprávy** (`/admin/messages`, pouze role admin). Obsah poptávky (jméno, kontakt, typ služby, místo, zpráva a balíček) se ukládá do `mail_archive.body` **před pokusem o odeslání**. Příjemce zůstává v nastavení Kontakt/Nastavení (`form_email`, případně kontaktní e-mail). Vytvoření schránky team@rbvideosphotos.cz samo toto nastavení nemění.

Stavy: `stored` = uloženo, výsledek pokusu nepotvrzen (např. přerušení procesu); `accepted` = SMTP server přijal zprávu k doručení, nikoli důkaz doručení do schránky; `failed` = transport odmítl, konfigurace je neplatná nebo nastala chyba. Časové údaje jsou UTC. Přehled zobrazuje příjemce a bezpečný chybový kód. Automatické opakování se neprovádí, aby při nejasném výsledku nevznikaly duplicitní zprávy. Obsah lze v administraci přečíst a požadavek vyřídit přímo.

Pokud odeslání selže, návštěvník dostane potvrzení uložení a informaci o nepotvrzeném e-mailu. Při selhání zápisu do databáze se nic neodesílá a formulář ukáže chybu. Mail log obsahuje ID záznamu a kód chyby, nikoli obsah zprávy. Archiv je provozní záloha proti výpadku pošty; pro ztrátu databáze je nutná běžná externí záloha DB. Armagedon/reset databáze může archiv odstranit. Historické zprávy před nasazením nelze zpětně obnovit.

Obnova hesla zapisuje pouze metadata pokusu do stejné tabulky, **nikdy resetovací URL, token ani tělo e-mailu**. Neúspěšné/nepotvrzené předání zneplatní reset token a zobrazí chybu.

## SMTP pro Active24 / Websupport

Na serveru do stávajícího `.env` doplňte:

```dotenv
SMTP_HOST=smtp.websupport.cz
SMTP_PORT=465
SMTP_ENCRYPTION=ssl
SMTP_USERNAME=team@rbvideosphotos.cz
SMTP_PASSWORD="VLOZTE_HESLO_K_EMAILOVE_SCHRANCE"
MAIL_FROM=team@rbvideosphotos.cz
```

`SMTP_PASSWORD` je heslo k e-mailové schránce, nikoli FTP/DB heslo. Ponechte stávající APP_URL a DB konfiguraci. Skutečné heslo necommitujte ani neposílejte do chatu. `.env` zůstává serverový a workflow ho nepřepisuje, GitHub SMTP Secrets se tedy samy do konfigurace nepřenesou.

Host ověřte v detailu konkrétní schránky. Websupport oficiálně uvádí `smtp.websupport.cz:465` (SSL/TLS), alternativně port `587` s `SMTP_ENCRYPTION=tls` (STARTTLS). Host je pouze jméno bez `ssl://` a bez portu. SMTP musí být u schránky povolené. `MAIL_FROM` musí odpovídat `SMTP_USERNAME`; při vynechání se použije SMTP_USERNAME. **Příjemce kontaktu zůstává podle administrace**, adresa team@ je odesílatel/přihlašovací účet.

Transport je PHPMailer **7.1.1**, zamčený v composer.lock (`phpmailer/phpmailer:^7.0`). Nasazení musí provést `composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist`; oba stávající workflow tento krok již mají. Nutný PHP OpenSSL pro TLS a důvěryhodné CA; ověřování certifikátu a jména serveru je zapnuté. Čas spojení 20 s, SMTP Timelimit 30 s, debug vypnutý. Žádný fallback na `mail()` ani automatický retry. Nová SQL migrace pro SMTP není potřeba, archiv stále vyžaduje migraci 104.

Chybějící/chybná konfigurace se v archivu zaznamená jako `smtp_configuration`, SMTP odmítnutí či spojení jako `smtp_transport`. Syrová odpověď SMTP ani přihlašovací údaje se nezapisují. Zpráva zůstává v DB i při chybě SMTP; accepted stále není potvrzení doručení do koncové schránky. Bez skutečných přihlašovacích údajů zatím produkční doručení nebylo ověřeno.

Zdroje: [Websupport - poštovní protokoly](https://www.websupport.cz/podpora/kb/postovni-protokoly/), [PHPMailer](https://github.com/PHPMailer/PHPMailer).


Ověření: `php tests/SmtpMailerTest.php`, `php tests/MailPersistenceIntegrationTest.php`, `php tests/PasswordResetIntegrationTest.php`, `php tests/MigrationUpgradeOrderIntegrationTest.php`. Izolované lokální databáze se po testech odstraní; reálná pošta se v testech neposílá.
