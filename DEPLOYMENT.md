# Active24 deployment

Kompletní první instalace testovacího prostředí: [STAGING.md](STAGING.md). Odložená práce: [BACKLOG.md](BACKLOG.md). Další vývoj patří na `dev`; produkční merge/push vyžaduje výslovný souhlas uživatele podle `INSTRUCTIONS.md`.

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

Video galerie: nová obsahová migrace `106_replace_approved_video_portfolio.sql` používá sdílený manifest `ApprovedVideoPortfolioService::MANIFEST` s přesnými 12 YouTube videi schválenými 17. 10. 2026. Nejprve `dev`/staging, potom migrace s `&publish=1` a kontrola `/nase-prace`. Obměňuje pouze video položky prvního veřejného webu; fotografie, jejich vzájemné pořadí, hero zdroje i fyzická média zachovává. Odstraní také staré exporty vyřazených video položek a stará vedlejší videa u zachovaných položek. Osm URL se překrývá s původní galerií; kontrola nepřítomnosti se vztahuje na čtyři vyřazená ID. Přesný seznam a výsledky ověření jsou v `VIDEO-GALLERY-2026-10-17.md`. Aktuální demo seed používá stejný manifest, takže reset nevrátí původní videa.

1. Over, ze serverovy `.env` odkazuje na databazi spravneho prostredi.
2. Pro novou verzi spust `/migrate-util.php?token=<MIGRATE_TOKEN>` z vlastniho prohlizece.
3. Utility aplikuji append-only migrace. Existujici migrace jsou beze zmen; aktualni verze obsahuje dalsi migrace az po 110.
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

## Health monitoring a cron

Po nasazeni aplikuj append-only migraci **110_create_health_checks.sql**. Do serveroveho `.env` lze explicitne pridat:

```dotenv
HEALTH_CHECK_URL=https://rbvideosphotos.cz/api/health
```

Na stagingu pouzij `https://staging.rbvideosphotos.cz/api/health`; prostredi se nesmi navzajem monitorovat do stejne DB. Kdyz promenna chybi, runner sklada cil jako `{APP_URL}/api/health`. Zadny novy GitHub Secret neni potreba.

V Active24 vytvor CLI cron s intervalem 10 minut. Priklad cron vyrazu a prikazu:

```cron
*/10 * * * * /usr/bin/php /ABSOLUTNI/CESTA/K/WEBU/bin/health-check.php
```

Presnou absolutni cestu a nazev PHP CLI vezmi z detailu hostingu; nepouzivej webovou URL skriptu, protoze `bin/health-check.php` mimo CLI vraci404. Po prvnim behu over: `/api/health` HTTP200 JSON, cron exit0, novy radek v admin **Analytika**, a aktualni cas posledni kontroly. Simulaci chyby provadej pouze na stagingu: docasne nastav nedostupny `HEALTH_CHECK_URL`, spust runner, ocekavej exit1 a `unhealthy` radek (je-li DB dostupna). Pri nedostupne DB se zaznam ulozi do `storage/logs/health-check-pending.jsonl`; po obnove dalsi beh vypise `replayed>0` a soubor vyprazdni.

`/api/health` neni sitemap URL, nevytvari session a nevstupuje do `site_visits`. Vraci jen agregovany stav bez DB hostu, jmen, hesel nebo vyjimky. V administraci je monitoring po 20 minutach bez zaznamu explicitne stale; sede dny nejsou vydavany za dostupnost.

## Kontrola PHP runtime pod MIGRATE_TOKEN

Po nasazeni over presnou serverovou verzi bez verejneho `phpinfo()`:

```text
https://rbvideosphotos.cz/api/runtime-info?token=TVUJ_MIGRATE_TOKEN
```

Token je GET parametr podle provozniho kontraktu. Aplikacni logger jeho hodnotu maskuje, ale provideruv access log muze uchovat plnou URL; odkaz proto nesdilej a po podezreni na unik token zmen. Platny JSON vraci jen verzi, SAPI, bezpecnostni minimum a stav povinnych extensions. Pro upozorneni z 21.10.2026 musi byt produkcni PHP 8.5 alespon 8.5.12 (`security_status=patched`). `unknown_branch` neni potvrzeni bezpecnosti a vyzaduje rucni kontrolu oficialniho PHP advisory. Stejnou kontrolu proved oddelene na stagingu.

Obsahove PHP handlery se vzdy provadeji az po dokonceni vsech SQL migraci, i kdyz web uz existuje. Aktualni handler starsi migrace muze pouzivat schema_name z 079; jednopruschodove poradi proto neni bezpecne. Regresni overeni: php tests/MigrationUpgradeOrderIntegrationTest.php vytvori vlastni nahodnou DB, spusti skutecne SQL a nahradi pouze obsahove efekty kontrolou schema zavislosti.

## PHP 8.5 a davkove JSON odpovedi

`ImageVariantService` uvolnuje reference na `GdImage` pres `unset`. `imagedestroy()` je od PHP 8.0 bez ucinku a od PHP 8.5 deprecated; pri debug vypisu by varovani porusilo JSON odpoved davky. Zastarala volani se odstranuji, chyby se nepotlacuji. Formular rozlisuje neplatny JSON, chybu aplikace a vyprsene prihlaseni; nedohledava JSON uvnitr poskozene odpovedi. Test klienta: `node tests/DemoSeedResponseTest.js`. Oficialni reference: https://www.php.net/manual/en/function.imagedestroy.php.

## Obnova hesla administrace

Po nasazení spusťte migrace (`103_add_password_resets.sql` přidává tabulku jednorázových tokenů). Na `/admin/login` je odkaz **Zapomenuté heslo**. Uživatel zadá přihlašovací jméno účtu (interně users.email); odkaz se posílá na uložené Nastavení > E-mail pro příjem formulářů (`contact_info.form_email`) u prvního webu. Login nemusí být doručitelná e-mailová adresa. Účet se tím nevytváří a jiné účty se nemění.

Serverové `.env` musí mít správné `APP_URL=https://vase-domena.cz` a SMTP konfiguraci popsanou níže. Obnova hesla i kontaktní formulář používají autentizované SMTP, bez návratu k PHP `mail()`. Při selhání se zobrazí chyba a reset token se odstraní. Například login `admin@example.com` zůstává beze změny a odkaz odejde na skutečnou schránku uloženou v Nastavení. V e-mailu je identifikován cílový účet. Odesílatel je SMTP schránka.

Odkaz platí 30 minut, nové vyžádání ruší předchozí a použití je atomické a jednorázové. Nové heslo má 12–72 bajtů. Po změně jsou staré přihlášené relace odmítnuty při dalším požadavku; první nasazení této ochrany také odhlásí starší relace. Žádosti mají CSRF a limit 3/15 minut podle IP i e-mailu. Neexistující účet dostane stejnou obecnou odpověď; chybu transportu zobrazujeme explicitně. Tokeny se ukládají pouze jako SHA-256; hesla jako bcrypt. Odkaz v e-mailu obsahuje tajný token, proto nesdílejte e-mail ani syrové serverové access logy (aplikace token v URI maskuje).

Ověření vývoje: `php tests/PasswordResetIntegrationTest.php` vytváří a v finally odstraňuje pouze vlastní náhodnou testovací databázi, vyžaduje lokální DB účet s CREATE/DROP DATABASE. Produkční doručení ověřte vlastní e-mailovou schránkou; testy reálné e-maily neposílají.

## Archiv kontaktních zpráv a stav pošty

Po nasazení spusťte migrace včetně **104_add_mail_archive.sql**. V administraci přibyla stránka **E-maily a zprávy** (`/admin/messages`, pouze role admin). Obsah poptávky (jméno, kontakt, typ služby, místo, zpráva a balíček) se ukládá do `mail_archive.body` **před pokusem o odeslání**. Příjemce zůstává v nastavení Kontakt/Nastavení (`form_email`, případně kontaktní e-mail). Vytvoření schránky team@rbvideosphotos.cz samo toto nastavení nemění.

Stavy: `stored` = uloženo, výsledek pokusu nepotvrzen (např. přerušení procesu); `accepted` = SMTP server přijal zprávu k doručení, nikoli důkaz doručení do schránky; `failed` = transport odmítl, konfigurace je neplatná nebo nastala chyba; `spam` = statický filtr zprávu uložil, ale záměrně nevolal SMTP. Časové údaje jsou UTC. Přehled zobrazuje příjemce a bezpečný chybový kód. Automatické opakování se neprovádí, aby při nejasném výsledku nevznikaly duplicitní zprávy. Obsah lze v administraci přečíst a požadavek vyřídit přímo.

Statický antispam je konzervativní: samotný odkaz zprávu nezablokuje. Blokuje až skóre 7 založené na silné botí frázi, URL v poli jména nebo kombinaci hazardní propagace, výzvy k akci a odkazu. Důvod je v archivu jako `static_spam:*`. Falešně zachycenou poptávku lze proto vždy dohledat v **E-maily a zprávy → Zachyceno statickým antispamem**. Filtr nemění obnovu hesla.

Pokud odeslání selže, návštěvník dostane potvrzení uložení a informaci o nepotvrzeném e-mailu. Při selhání zápisu do databáze se nic neodesílá a formulář ukáže chybu. Mail log obsahuje ID záznamu a kód chyby, nikoli obsah zprávy. Archiv je provozní záloha proti výpadku pošty; pro ztrátu databáze je nutná běžná externí záloha DB. Armagedon/reset databáze může archiv odstranit. Historické zprávy před nasazením nelze zpětně obnovit.

Obnova hesla zapisuje pouze metadata pokusu do stejné tabulky, **nikdy resetovací URL, token ani tělo e-mailu**. Neúspěšné/nepotvrzené předání zneplatní reset token a zobrazí chybu.

## SMTP pro Active24 / Websupport

### Nepovinná kopie kontaktních zpráv

Po nasazení spusťte migrace **107_add_contact_email_copy.sql** a
**108_expand_contact_email_copy_list.sql**. V **Nastavení → Kontaktní komunikace**
lze uložit více adres do pole **Kopie kontaktních zpráv**. Adresy oddělte čárkou,
mezerou nebo novým řádkem. Prázdné pole kopii vypne; hlavní příjemce zůstává zachovaný.
Používá se běžná CC kopie, takže její adresy vidí ostatní příjemci. Duplicity a adresa
shodná s hlavním příjemcem se neposílají opakovaně. Kopie se neposílá u obnovy hesla.
Nastavení smí měnit jen administrátor.

Normalizovaný seznam eviduje databázový archiv v `cc_recipient` ještě před SMTP
pokusem a přehled **E-maily a zprávy** jej zobrazuje. Chyba SMTP zachová tělo i
příjemce v databázi. Neplatná adresa se neignoruje: nastavení odmítne celý seznam,
transport zprávu označí jako
selhanou a zachová zálohu. `.env` ani GitHub Secrets pro tuto funkci nepřibývají.

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

## Přehled e-mailů a změna vlastního hesla

- `/admin/messages`: administrátor vidí kontaktní zprávy i metadata obnovy hesla. Filtry `status=accepted|failed|stored|spam` a `kind=contact|password_reset` lze kombinovat. Přehled zobrazuje příjemce, předmět, vznik a poslední pokus (UTC), obsah kontaktu a bezpečný chybový kód. Počty jsou za celý archiv, počet nalezených a seznam respektují filtr. Stránkování po 50 zachovává filtry. `accepted` je předání poštovní službě, nikoli potvrzené doručení. Neplatný filtr vrací HTTP 400; editor nemá k archivu přístup.
- `/admin/account/password`: každý přihlášený uživatel může změnit **pouze vlastní heslo**. Odkaz je v patičce administrace. Zadává současné heslo, nové heslo (12–72 bajtů, jiné než současné) a jeho potvrzení. POST vyžaduje CSRF a má limit 5 pokusů za 15 minut na účet. Po úspěchu se uživatel odhlásí a přihlásí novým heslem. Ostatní relace budou při dalším požadavku odmítnuty; existující reset odkazy účtu se zneplatní.

Změna hesla neodesílá e-mail a nezávisí na dostupnosti SMTP. Pokud současné heslo neznáte, použijte `/admin/forgot-password`; ten identifikuje účet podle loginu a posílá odkaz na uložené `contact_info.form_email`. Heslo ani reset token se do archivu/logu nepíše, audit změny obsahuje pouze user_id.

Bez nové SQL migrace; vyžaduje existující `password_resets` a `mail_archive` (103/104). `php tests/AccountAdminIntegrationTest.php` ověřuje vlastní účet, reset tokeny, rollback a filtrování na samostatné testovací DB, která se v finally odstraní.

## Centrální adresa pro obnovu přístupu

Obnova neposílá na login účtu. Používá **uložené** pole Nastavení > E-mail pro příjem formulářů (`contact_info.form_email`) u prvního webu. Pokud pole chybí nebo není platné, zobrazí chybu, nevytvoří token a nic nepošle na users.email, veřejný contact_info.email ani výchozí adresu. V Nastavení je nutné platnou adresu skutečně uložit. Při neexistujícím loginu zůstává obecné potvrzení bez e-mailu.

Odkaz je stále jednorázový, 30 minut a vázaný na konkrétní user_id. Tělo e-mailu uvádí login, archiv ukládá skutečného centrálního příjemce bez resetovacího těla/tokenu. Centrální schránka tedy spravuje obnovu všech CMS účtů, které zná podle loginu.

Proto je `form_email` chráněné pole: admin smí adresu měnit, editor může upravovat ostatní veřejné kontakty. Contact/Page kontrolují roli před zápisem a ContactRepository při běžném upsertu chráněný sloupec vůbec neaktualizuje. Settings (requireAdmin) a důvěryhodný demo seed změnu povolují výslovně. Změna veřejného contact_info.email nemění recovery adresu.

Bez nové migrace. Ověření: `php tests/PasswordResetIntegrationTest.php` a `php tests/RecoveryRecipientPolicyIntegrationTest.php`, izolované DB, žádné reálné e-maily.

## Sjednocení obrázků pro sdílení stránek

Migrace **105_clear_page_social_image_overrides.sql** vymaže `seo_settings.og_image_id` a `twitter_image_id` u všech stránek (včetně draftů a všech webů). Zůstávají globální `sites.default_og_image_id`, `default_twitter_image_id`, `default_instagram_image_id`, média, obrázky v obsahu, `schema_image_id` i textové SEO údaje. Po úspěšném běhu běžný migrátor zapíše provedení; samotný SQL UPDATE je opakovatelný. Starší migrace nejsou upraveny.

Po nasazení spusťte migrace a ověřte `/kontakt` i další stránky: `og:image` má používat obrázek z Nastavení; `twitter:image` globální Twitter obrázek, případně globální OG. Když globální obrázek není nastavený, migrace žádný nevytváří. U speciálních blogových/portfolio detailů má nově globální obrázek přednost před obsahovým hero obrázkem. Samostatný statický export portfolia používá stejnou prioritu i veřejné `/media/` URL. Pro již vytvořený statický export znovu spusťte publikování; sociální sítě mohou držet vlastní cache náhledů.

Aktuální base/demo PHP seedy už nevracejí původní testovací per-page fotografie. Ručně nastavený nový page override v SEO může i v budoucnu přepsat globální obrázek; migrace čistí současná data, nejde o zákaz této funkce. Globální Instagram obrázek zůstává uložený v Nastavení, stránkový Instagram override v modelu není.

Ověření: `php tests/SocialImageResetIntegrationTest.php` (izolovaná DB, všechny stránky, zachování ostatních dat, opakování migrace, live head, portfolio export, demo SEO seed). Test odstraní pouze vlastní náhodnou DB a náhodný exportní soubor. Neprovádí migraci nad produkčními daty.
