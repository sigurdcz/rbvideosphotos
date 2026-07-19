# Email manager — implementační plán (v2, zjednodušený)

Datum: 2026-07-19 · Stav: PŘIPRAVENO K IMPLEMENTACI · Určeno pro: LLM agenty (u každého úkolu přiřazený model)

## Cíl (4 body, nic víc)

1. **Záloha v DB** — všechny e-maily ze schránky vlastníka a všechny zprávy z kontaktního formuláře webu se ukládají do vlastní databáze (včetně surového zdroje zprávy).
2. **Jednoduchý sync schránky** — pravidelné stažení nové pošty ze schránky (POP3; SMTP je jen odesílací protokol, stahování řeší POP3 — nejjednodušší varianta, žádné složky ani IMAP stav).
3. **Odesílání e-mailů uživatelům** — z adminu, přes existující `mail()` (na webzdarma už funguje pro kontaktní formulář); žádný vlastní SMTP klient.
4. **Označování vyřízených zpráv** — zpráva dostane stav „zodpovězeno" automaticky tím, že na ni někdo z adminu odešle odpověď (`answered_at` + vazba na odeslanou odpověď).

**Vědomě vypuštěno oproti v1:** tracking pixel / stav „otevřeno", IMAP klient + složky + Sent APPEND, vlastní SMTP klient, párování externích odpovědí, tabulky `email_events`/`email_attachments`/`email_folders`, API cron endpoint. (Kdykoliv jde doplnit později; přílohy zůstávají dostupné v uloženém surovém EML.)

## Výchozí stav (ověřeno v kódu 2026-07-19)

- Jediný e-mail dnes: `POST /kontakt/odeslat` → `@mail()` v `public/index.php:2317`; **nic se neukládá do DB**. Handler má CSRF + honeypot + `RequestThrottle` — nesahat.
- Žádné composer závislosti (`vendor/autoload.php` je ručně psaný PSR-4 shim) — **nezavádět je**.
- Precedenty: `GtmTokenCipher` (AES-256-GCM, klíč v `.env`) pro heslo schránky · admin CRUD vzor (`ReviewController` + routy v `admin/index.php` + nav v `admin/views/layouts/main.php`) · migrace append-only, poslední `021` · testy `php tests/run.php` (45 + 26 golden; GET render `/kontakt` se nesmí změnit ani o byte).

## Architektonická rozhodnutí (závazná)

1. **POP3 přes `stream_socket_client` s TLS** (port 995): USER/PASS, STAT, UIDL, RETR, QUIT. **Nikdy DELE** — pošta zůstává na serveru, my jen zálohujeme. Nová zpráva = UIDL, které ještě není v DB.
2. **Odesílání přes `mail()`** s korektními hlavičkami (UTF-8 subject dle RFC 2047, `From` = adresa schránky, `Message-ID`, u odpovědi `In-Reply-To`/`References`, ochrana proti header injection). Odeslaná zpráva se vždy uloží do DB → „Odeslané".
3. **Záloha = surový zdroj zprávy v DB** (`raw_source` MEDIUMBLOB, cap 2 MB — větší zprávy jen parsovaný text + poznámka). Žádné soubory ve storage, žádné změny deploy.
4. **Jedna schránka na web** — formulář nastavení (ne CRUD seznam), heslo šifrované `MailCredentialCipher`.
5. **Sync trigger:** tlačítko „Stáhnout poštu" + automaticky při návštěvě `/admin/emailing` (throttle např. 5 min + zámek + časový budget ~20 s). Žádný cron.
6. **HTML těla příchozích mailů = nedůvěryhodný vstup (XSS):** render jen server-side strip + `<iframe sandbox="" srcdoc>`; primárně zobrazovat textovou část.
7. **„Zodpovězeno"** = odesláním odpovědi z adminu: originál dostane `answered_at` + `answered_by_id` (FK na odeslanou zprávu). Žádné ruční přepínání stavu.

## DB kontrakt (migrace `022_create_emailing_tables.sql`)

- **`email_accounts`**: `id`, `site_id` FK, `email_address`, `pop3_host`, `pop3_port` (default 995), `username`, `password_encrypted` TEXT, `is_active` TINYINT(1) default 1, `last_sync_at` DATETIME NULL, `last_sync_error` TEXT NULL, `created_at`, `updated_at`. (Jeden řádek na site.)
- **`email_messages`**: `id`, `site_id`, `account_id` FK NULL, `direction` enum(`inbound`,`outbound`), `source` enum(`pop3`,`contact_form`,`admin`), `pop3_uidl` VARCHAR(190) NULL, `message_id` VARCHAR(255) NULL, `in_reply_to` VARCHAR(255) NULL, `from_name` VARCHAR(255) NULL, `from_email` VARCHAR(255), `to_email` VARCHAR(255), `subject` VARCHAR(998), `sent_date` DATETIME NULL, `body_text` LONGTEXT NULL, `body_html` LONGTEXT NULL, `attachment_names` TEXT NULL (JSON pole jmen), `raw_source` MEDIUMBLOB NULL, `size_bytes` INT UNSIGNED default 0, `read_at` DATETIME NULL, `answered_at` DATETIME NULL, `answered_by_id` FK→`email_messages.id` NULL, `created_at`, `updated_at`; UNIQUE(`account_id`,`pop3_uidl`) (idempotentní sync), index (`site_id`,`direction`,`created_at`).

## Úrovně obtížnosti

**[Haiku]** mechanická práce dle kontraktu · **[Sonnet]** standardní implementace dle vzoru · **[Opus]** složitá logika / bezpečnostně citlivé.
Tvrdá pravidla (model-check): nikdy Haiku na síťový kód; po 2 selháních eskalovat výš; síťový kód po dokončení reviduje **Fable 5**.

---

## Fáze 0 — Ověření prostředí

- [ ] **0.1 [Sonnet]** Ověřit: POP3 parametry schránky vlastníka (host/port — u webzdarma typicky pop3.webzdarma.cz:995), průchodnost odchozího TLS spojení `stream_socket_client('ssl://…:995')` z produkce, a doručitelnost `mail()` s `From` = adresa schránky (testovací mail na Gmail → projde SPF?). Výsledky zapsat do tohoto souboru (sekce Poznámky z provozu). Blokovaný port 995 = STOP, eskalovat uživateli.

## Fáze 1 — DB základ

- [ ] **1.1 [Haiku]** Migrace `database/migrations/022_create_emailing_tables.sql` přesně dle kontraktu výše (idempotentní, utf8mb4).
- [ ] **1.2 [Haiku]** `App\Services\Mail\MailCredentialCipher` — kopie vzoru `GtmTokenCipher` s klíčem `MAIL_ENCRYPTION_KEY`; přidat do `.env.example` s generovacím příkazem.
- [ ] **1.3 [Sonnet]** `EmailAccountRepository` (findForSite, save — upsert jednoho řádku) a `EmailMessageRepository` (stránkovaný list s filtrem direction + LIKE hledáním, findById, existsUidl, insertInbound, insertOutbound, markRead, markAnswered). Prepared statements; unit testy dle vzoru v `tests/`, zapojené do `tests/run.php`.

## Fáze 2 — Parsování a odesílání (bez sítě)

- [ ] **2.1 [Opus]** `App\Services\Mail\MailParser` — z raw zprávy: unfold + RFC 2047 dekódování hlaviček (From/To/Subject/Date/Message-ID/In-Reply-To), multipart walk → `body_text` + `body_html`, konverze charsetů do UTF-8 (vč. windows-1250 a iso-8859-2 — česká pošta), jména příloh do pole, tolerantní k vadným zprávám (nikdy exception ven, degradace na raw text). Součástí úkolu: `.eml` fixtures (`tests/fixtures/mail/`: plain, multipart, diakritika v subjectu, windows-1250 tělo, příloha, vadná zpráva) + unit testy.
- [ ] **2.2 [Sonnet]** `App\Services\Mail\MailerService` — odeslání plain-text mailu přes `mail()`: RFC 2047 encode subjectu, hlavičky `From` (adresa účtu, jinak `noreply@{host}` jako dnes), `Reply-To`, vygenerovaný `Message-ID`, volitelné `In-Reply-To`/`References`; CR/LF strip všech vstupů do hlaviček (vzor `cleanHeaderValue`); po odeslání uložit zprávu přes `insertOutbound` (včetně raw rekonstrukce). Unit test skládání hlaviček.

## Fáze 3 — POP3 sync (síťový kód — nikdy Haiku)

- [ ] **3.1 [Opus]** `App\Services\Mail\Pop3Client` — `stream_socket_client` TLS: connect, USER/PASS, STAT, UIDL (list), RETR n, QUIT; timeouty (connect 10 s, read 15 s), čitelné chyby (špatné heslo vs. síť), heslo nikdy do logu/výjimky, **žádné DELE**. Součástí úkolu transcript test přes fake stream (happy path, auth fail, timeout).
- [ ] **3.2 [Opus]** `App\Services\Mail\EmailSyncService` — běh: načíst účet + dešifrovat heslo → UIDL list → diff proti `existsUidl` → RETR jen nové, novější napřed, batch cap 25/běh + časový budget; per-site zámek (vzor `RequestThrottle` storage) a min. interval 5 min pro auto-trigger; každou zprávu: `MailParser` → `insertInbound` (raw_source s cap 2 MB); chyby do `last_sync_error`, úspěch do `last_sync_at`. První sync velké schránky: stáhne jen nejnovějších 25 a pokračuje dalšími běhy. Integrační test s fake klientem.
- [ ] **3.R [review]** Review Fáze 3 modelem **Fable 5** (tvrdé pravidlo). Bez schválení nepokračovat.

## Fáze 4 — Kontaktní formulář do DB

- [ ] **4.1 [Sonnet]** V POST `/kontakt/odeslat` (`public/index.php`) po stávajících validacích uložit zprávu: `direction=inbound`, `source=contact_form`, from = kontakt návštěvníka, to = `form_email`, subject/body jako v dnešním mailu; ukládá se i když `mail()` selže. **PŘÍSNÝ SCOPE: CSRF/honeypot/throttle a celý GET render `/kontakt` beze změny; DB chyba nesmí rozbít odeslání ani redirect (try/catch + `Logger`).** Golden 26/26 musí projít beze změny.

## Fáze 5 — Admin sekce Emailing

- [ ] **5.1 [Haiku]** Skeleton: routy v `admin/index.php` (`/admin/emailing` GET, `/admin/emailing/detail` GET, `/admin/emailing/reply` GET+POST, `/admin/emailing/sync` POST, `/admin/emailing/settings` GET+POST), `EmailingController` kostra s `requireAuth()`, nav položka „Emailing" v `main.php`, prázdné views `admin/views/emailing/`.
- [ ] **5.2 [Sonnet]** Nastavení schránky (`/admin/emailing/settings`): formulář jednoho účtu (adresa, POP3 host/port, login, heslo — při editaci prázdné = beze změny, šifrovat), tlačítko „Otestovat připojení" (POP3 login dry-run, výsledek do flash), CSRF.
- [ ] **5.3 [Sonnet]** Seznam (`/admin/emailing`): záložky Přijaté / Odeslané, sloupce Od/Komu · Předmět · Datum · Stav (tučně nepřečtené dle `read_at`; badge „↩ Zodpovězeno" dle `answered_at`; ikona zdroje schránka/formulář), stránkování, LIKE hledání; stav synchronizace (`last_sync_at`/`last_sync_error`) + tlačítko „Stáhnout poštu"; auto-sync trigger při načtení (přes throttle/zámek z 3.2, nikdy neblokovat render déle než budget).
- [ ] **5.4 [Opus]** Detail (`/admin/emailing/detail?id=`): hlavičky, tělo — primárně `body_text`; `body_html` jen přes server-side strip skriptů/handlerů + `<iframe sandbox="" srcdoc>`; výpis jmen příloh (obsah je v raw_source — bez download UI, mimo scope); při zobrazení `markRead`; tlačítko **Odpovědět**; odkaz na spárovanou odpověď/originál (`answered_by_id`/`in_reply_to`). XSS-kritický úkol.
- [ ] **5.5 [Sonnet]** Odpověď (`/admin/emailing/reply?id=`): GET — prefill To = Reply-To||From, Subject `Re: …` (bez zdvojení), citace původního textu prefixem `>`; POST — CSRF + `RequestThrottle`, validace příjemce `FILTER_VALIDATE_EMAIL`, odeslání přes `MailerService` s `In-Reply-To` → originálu nastavit `markAnswered(answered_by_id)`, flash + redirect na detail originálu. Z detailu i „nový e-mail" bez originálu (prázdný compose) — pokrývá „odesílání e-mailů uživatelům".
- [ ] **5.6 [Haiku]** CSS/UX doladění dle stylu adminu + badge počtu nepřečtených u položky Emailing v nav.

## Fáze 6 — Kvalita a předání

- [ ] **6.1 [Sonnet]** Kompletní `php tests/run.php`: stávajících 45 testů + 26 golden 0 failures (kontakt GET nezměněn) + nové testy z fází 1–3; ruční E2E: poslat mail do schránky → Stáhnout poštu → objeví se → otevřít → Odpovědět → odeslat → originál „Zodpovězeno"; formulář z webu → objeví se v Přijatých. Visual QA sekce na 390×844 + 1920×1080 (dle projektového pravidla bodovat jen layout/CSS, ne obsah).
- [ ] **6.2 [Haiku]** Dokumentace: `ARCHITECTURE.md` (modul Emailing: 2 tabulky, POP3 sync, mail() odesílání), `README.md` (nastavení schránky), `.env.example` (`MAIL_ENCRYPTION_KEY`).
- [ ] **6.R [review]** Závěrečné review featury modelem **Fable 5** (POP3 klient, zásah do kontakt handleru, XSS plocha detailu), verdikt do `UKOL.md`.

## Rizika (vědomá)

- Blokovaný odchozí port 995 na hostingu → STOP ve Fázi 0 (mění architekturu příjmu).
- `mail()` s `From` = adresa schránky: doručitelnost ověřuje Fáze 0; kdyby SPF neprošel, `From` zůstane `noreply@{host}` a adresa schránky půjde do `Reply-To`.
- POP3 vidí jen INBOX — odeslaná pošta z mail klienta vlastníka se nezálohuje (zálohují se odpovědi odeslané z adminu; plná centralizace byla vypuštěna se scope v1).
- Disková/DB kvóta: raw_source cap 2 MB; retention (mazání starých) je budoucí rozšíření.
- Mimo scope: stav „otevřeno" (pixel), přílohy download UI, více schránek, IMAP/složky, ruční přepínání „vyřízeno", kampaně.
