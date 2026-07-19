# Email manager — implementační plán (v3, ověřeno proti Gmailu)

Datum: 2026-07-19 · Stav: PŘIPRAVENO K IMPLEMENTACI · Určeno pro: LLM agenty (u každého úkolu přiřazený model)

## Cíl (4 body)

1. **Záloha v DB** — všechny e-maily ze schránky vlastníka a všechny zprávy z kontaktního formuláře webu se ukládají do vlastní databáze (včetně surového zdroje zprávy).
2. **Jednoduchý sync schránky** — stahování nové pošty přes **IMAP** (jen INBOX, read-only).
3. **Odesílání e-mailů uživatelům** — z adminu přes **SMTP submission** účtu schránky; `mail()` zůstává jen jako fallback pro notifikace bez nakonfigurovaného účtu.
4. **Označování vyřízených zpráv** — zpráva dostane stav „zodpovězeno" automaticky odesláním odpovědi z adminu (`answered_at` + vazba na odeslanou odpověď).

## Proč IMAP a SMTP, ne POP3 a mail() — ověřeno 2026-07-19

Schránka vlastníka je/může být Gmail. Ověřené skutečnosti (zdroje na konci dokumentu):

1. **Obyčejné heslo u Gmailu nefunguje.** Google vypnul „less secure apps" / basic auth (Workspace definitivně 2025). Jediná podporovaná password cesta pro IMAP/SMTP klienty je **app password**: 16znakový kód, vyžaduje zapnuté dvoufázové ověření (2SV). App passwords v 2026 dál fungují (oficiálně „nedoporučené", ale podporované). Login = celá adresa, heslo = app password.
2. **Gmail POP3 je pro zálohu nevhodný**: jednou stažená (RETR) zpráva se přes POP už nikdy znovu nenabídne („download once"; recent mode řeší jen posledních 30 dní). Pád mezi RETR a uložením do DB = zpráva pro zálohu nenávratně neviditelná. **IMAP tímto netrpí** (server nic neskrývá, stav syncu držíme my přes UID) a od ledna 2025 je v Gmailu **vždy zapnutý** (žádné nastavení v Gmailu není potřeba).
3. **`mail()` s `From: …@gmail.com` z cizího serveru nedoručí.** Gmail vynucuje DMARC pro gmail.com (spoofing → quarantine) a od 2/2024 sender guidelines vyžadují SPF/DKIM alignment s doménou ve `From`. Odpovědi z Gmail identity musí odejít přes **smtp.gmail.com** s app password. Bonus: pošta odeslaná přes smtp.gmail.com se Gmailu automaticky ukládá do složky Odeslané.
4. **Gmail API + OAuth zamítnuto** (ač projekt má GTM OAuth precedent): Gmail scopes jsou „restricted" — externí OAuth app v Testing módu má refresh tokeny s expirací **7 dní** (= týdenní ruční re-connect), produkční publikace s restricted scopes vyžaduje CASA Tier 2 bezpečnostní audit — pro osobní CMS nereálné. GTM integrace funguje, protože GTM scopes nejsou restricted.

Stejný design funguje i pro ne-Gmail schránku (např. webzdarma: imap./smtp.webzdarma.cz s běžným heslem) — univerzální pravidlo: **posílat vždy přes SMTP server poskytovatele schránky, jejíž adresa je ve `From`**.

**Standardní Gmail parametry:** IMAP `imap.gmail.com:993` (SSL) · SMTP `smtp.gmail.com:465` (SSL) nebo `:587` (STARTTLS).

**Vědomě vypuštěno (z v1/v2):** tracking pixel / stav „otevřeno", POP3, složky mimo INBOX, IMAP APPEND (Sent řeší smtp.gmail.com sám), párování externích odpovědí, tabulky events/attachments/folders, API cron endpoint. Přílohy zůstávají dostupné v uloženém surovém EML.

## Výchozí stav (ověřeno v kódu 2026-07-19)

- Jediný e-mail dnes: `POST /kontakt/odeslat` → `@mail()` v `public/index.php:2317`; **nic se neukládá do DB**. Handler má CSRF + honeypot + `RequestThrottle` — nesahat.
- Žádné composer závislosti (`vendor/autoload.php` je ručně psaný PSR-4 shim) — **nezavádět je**.
- Precedenty: `GtmTokenCipher` (AES-256-GCM, klíč v `.env`) pro heslo schránky · admin CRUD vzor (`ReviewController` + routy v `admin/index.php` + nav v `admin/views/layouts/main.php`) · migrace append-only, poslední `021` · testy `php tests/run.php` (45 + 26 golden; GET render `/kontakt` se nesmí změnit ani o byte).

## Architektonická rozhodnutí (závazná)

1. **IMAP subset, read-only, jen INBOX**: `stream_socket_client` TLS 993 → CAPABILITY, LOGIN, **EXAMINE INBOX** (read-only režim, čte UIDVALIDITY+UIDNEXT), `UID SEARCH UID N:*`, `UID FETCH (BODY.PEEK[] RFC822.SIZE)` (PEEK — nenastavuje \Seen), LOGOUT. Ve schránce se nikdy nic nemění ani nemaže.
2. **SMTP submission**: implicit TLS 465 (primárně) / STARTTLS 587, EHLO, AUTH PLAIN + LOGIN, MAIL FROM/RCPT TO/DATA (dot-stuffing), QUIT. `From` = adresa účtu. Bez nakonfigurovaného účtu se odesílá jako dnes přes `mail()` (`From: noreply@{host}`) — jen notifikace, nikdy Gmail identita.
3. **Záloha = surový zdroj zprávy v DB** (`raw_source` MEDIUMBLOB, cap 2 MB — větší zprávy jen parsovaný text + poznámka). Žádné soubory ve storage, žádné změny deploye.
4. **Jedna schránka na web** — formulář nastavení (ne CRUD seznam), heslo/app password šifrované `MailCredentialCipher` (vzor `GtmTokenCipher`, klíč `MAIL_ENCRYPTION_KEY`).
5. **Sync trigger:** tlačítko „Stáhnout poštu" + automaticky při návštěvě `/admin/emailing` (throttle 5 min + zámek + časový budget ~20 s). Žádný cron.
6. **Sync stav:** `imap_uidvalidity` + `imap_last_uid` na účtu. Změna UIDVALIDITY → reset `last_uid=0` + resync; duplicitám brání UNIQUE(účet, uidvalidity, uid) + sekundární dedupe přes `Message-ID`.
7. **HTML těla příchozích mailů = nedůvěryhodný vstup (XSS):** render jen server-side strip + `<iframe sandbox="" srcdoc>`; primárně zobrazovat textovou část.
8. **„Zodpovězeno"** = odesláním odpovědi z adminu: originál dostane `answered_at` + `answered_by_id` (FK na odeslanou zprávu). Žádné ruční přepínání stavu.

## DB kontrakt (migrace `022_create_emailing_tables.sql`)

- **`email_accounts`**: `id`, `site_id` FK, `email_address`, `imap_host` (default `imap.gmail.com`), `imap_port` (default 993), `smtp_host` (default `smtp.gmail.com`), `smtp_port` (default 465), `username`, `password_encrypted` TEXT, `imap_uidvalidity` BIGINT UNSIGNED NULL, `imap_last_uid` BIGINT UNSIGNED default 0, `is_active` TINYINT(1) default 1, `last_sync_at` DATETIME NULL, `last_sync_error` TEXT NULL, `created_at`, `updated_at`. (Jeden řádek na site.)
- **`email_messages`**: `id`, `site_id`, `account_id` FK NULL, `direction` enum(`inbound`,`outbound`), `source` enum(`imap`,`contact_form`,`admin`), `imap_uidvalidity` BIGINT UNSIGNED NULL, `imap_uid` BIGINT UNSIGNED NULL, `message_id` VARCHAR(255) NULL (index), `in_reply_to` VARCHAR(255) NULL, `from_name` VARCHAR(255) NULL, `from_email` VARCHAR(255), `to_email` VARCHAR(255), `subject` VARCHAR(998), `sent_date` DATETIME NULL, `body_text` LONGTEXT NULL, `body_html` LONGTEXT NULL, `attachment_names` TEXT NULL (JSON pole jmen), `raw_source` MEDIUMBLOB NULL, `size_bytes` INT UNSIGNED default 0, `read_at` DATETIME NULL, `answered_at` DATETIME NULL, `answered_by_id` FK→`email_messages.id` NULL, `created_at`, `updated_at`; UNIQUE(`account_id`,`imap_uidvalidity`,`imap_uid`), index (`site_id`,`direction`,`created_at`).

## Úrovně obtížnosti

**[Haiku]** mechanická práce dle kontraktu · **[Sonnet]** standardní implementace dle vzoru · **[Opus]** složitá logika / bezpečnostně citlivé.
Tvrdá pravidla (model-check): nikdy Haiku na síťový kód; po 2 selháních eskalovat výš; síťový kód po dokončení reviduje **Fable 5**.

---

## Fáze 0 — Ověření prostředí a přístupů

- [ ] **0.1 [Sonnet]** (a) Uživatelský krok — vyžádat od vlastníka: zapnuté 2SV na Google účtu + vygenerované **app password** (myaccount.google.com → Security → 2-Step Verification → App passwords); (b) z produkce (webzdarma) ověřit průchodnost odchozích TLS spojení `stream_socket_client` na `imap.gmail.com:993` a `smtp.gmail.com:465`/`587` (hostingy někdy blokují odchozí SMTP kvůli spamu!); (c) testovací IMAP LOGIN a SMTP AUTH s app password; (d) poslat testovací mail přes smtp.gmail.com a ověřit doručení + kopii v Odeslaných. Výsledky zapsat do tohoto souboru. **Blokovaný port 993/465/587 z hostingu = STOP, eskalovat uživateli** (řešením by pak byl proxy/relay, mimo scope).

## Fáze 1 — DB základ

- [ ] **1.1 [Haiku]** Migrace `database/migrations/022_create_emailing_tables.sql` přesně dle kontraktu výše (idempotentní, utf8mb4).
- [ ] **1.2 [Haiku]** `App\Services\Mail\MailCredentialCipher` — kopie vzoru `GtmTokenCipher` s klíčem `MAIL_ENCRYPTION_KEY`; přidat do `.env.example` s generovacím příkazem.
- [ ] **1.3 [Sonnet]** `EmailAccountRepository` (findForSite, save — upsert jednoho řádku, updateSyncState) a `EmailMessageRepository` (stránkovaný list s filtrem direction + LIKE hledáním, findById, existsUid, existsMessageId, insertInbound, insertOutbound, markRead, markAnswered). Prepared statements; unit testy dle vzoru v `tests/`, zapojené do `tests/run.php`.

## Fáze 2 — Parsování a skládání zpráv (bez sítě)

- [ ] **2.1 [Opus]** `App\Services\Mail\MailParser` — z raw zprávy: unfold + RFC 2047 dekódování hlaviček (From/To/Subject/Date/Message-ID/In-Reply-To), multipart walk → `body_text` + `body_html`, konverze charsetů do UTF-8 (vč. windows-1250 a iso-8859-2 — česká pošta), jména příloh do pole, tolerantní k vadným zprávám (nikdy exception ven, degradace na raw text). Součástí úkolu: `.eml` fixtures (`tests/fixtures/mail/`: plain, multipart, diakritika v subjectu, windows-1250 tělo, příloha, vadná zpráva) + unit testy.
- [ ] **2.2 [Sonnet]** `App\Services\Mail\MailComposer` — sestavení odchozí plain-text zprávy: RFC 2047 encode subjectu, `From`/`Reply-To`/`To`, vygenerovaný `Message-ID` (`<random@doména-účtu>`), volitelné `In-Reply-To`/`References`, `Date`, UTF-8 quoted-printable tělo; CR/LF strip všech vstupů do hlaviček (vzor `cleanHeaderValue`). Výstup: kompletní raw message (pro SMTP DATA i pro uložení do DB) + varianta headers/body pro `mail()` fallback. Unit testy.

## Fáze 3 — Síťoví klienti a sync (síťový kód — nikdy Haiku)

- [ ] **3.1 [Opus]** `App\Services\Mail\ImapClient` — `stream_socket_client` TLS: CAPABILITY, LOGIN, EXAMINE INBOX (parsovat UIDVALIDITY/UIDNEXT), `UID SEARCH UID N:*`, `UID FETCH (BODY.PEEK[] RFC822.SIZE)` s literal `{n}` handlingem, LOGOUT; timeouty (connect 10 s, read 15 s), čitelné chyby (špatné heslo vs. síť), heslo nikdy do logu/výjimky, **žádné STORE/EXPUNGE — striktně read-only**. Součástí úkolu transcript test přes fake stream (happy path, auth fail, timeout, literal parsing).
- [ ] **3.2 [Opus]** `App\Services\Mail\SmtpClient` — implicit TLS 465 + STARTTLS 587: EHLO, AUTH PLAIN i LOGIN, MAIL FROM/RCPT TO/DATA s dot-stuffingem, QUIT; timeouty, rozlišení chyb (auth vs. odmítnutý příjemce vs. síť), heslo nikdy do logu. Transcript test.
- [ ] **3.3 [Sonnet]** `App\Services\Mail\MailerService` — fasáda odesílání: je-li aktivní účet → `MailComposer` + `SmtpClient` přes účet (`From` = adresa účtu); jinak `mail()` fallback (`From: noreply@{host}` jako dnes — nikdy ne Gmail adresa, viz DMARC). Po odeslání vždy `insertOutbound` (včetně raw). Test s fake SMTP klientem.
- [ ] **3.4 [Opus]** `App\Services\Mail\EmailSyncService` — běh: účet + dešifrované heslo → EXAMINE INBOX → porovnat UIDVALIDITY (změna → reset `imap_last_uid=0`, warning log, žádné mazání dat) → `UID SEARCH` od `last_uid+1` → FETCH jen nové, batch cap 25/běh + časový budget; per-site zámek (vzor `RequestThrottle` storage) a min. interval 5 min pro auto-trigger; každou zprávu: `MailParser` → dedupe (`existsUid`, `existsMessageId`) → `insertInbound` (raw_source cap 2 MB) → posunout `imap_last_uid`; chyby do `last_sync_error`, úspěch do `last_sync_at`. První sync velké schránky: začít od `UIDNEXT − batch` (jen nejnovější zprávy), starší se dotahují dalšími běhy. Integrační test s fake klientem.
- [ ] **3.R [review]** Review Fáze 3 modelem **Fable 5** (tvrdé pravidlo). Bez schválení nepokračovat.

## Fáze 4 — Kontaktní formulář do DB

- [ ] **4.1 [Sonnet]** V POST `/kontakt/odeslat` (`public/index.php`) po stávajících validacích uložit zprávu: `direction=inbound`, `source=contact_form`, from = kontakt návštěvníka, to = `form_email`, subject/body jako v dnešním mailu; ukládá se i když `mail()` selže. **PŘÍSNÝ SCOPE: CSRF/honeypot/throttle a celý GET render `/kontakt` beze změny; DB chyba nesmí rozbít odeslání ani redirect (try/catch + `Logger`).** Golden 26/26 musí projít beze změny.

## Fáze 5 — Admin sekce Emailing

- [ ] **5.1 [Haiku]** Skeleton: routy v `admin/index.php` (`/admin/emailing` GET, `/admin/emailing/detail` GET, `/admin/emailing/reply` GET+POST, `/admin/emailing/sync` POST, `/admin/emailing/settings` GET+POST), `EmailingController` kostra s `requireAuth()`, nav položka „Emailing" v `main.php`, prázdné views `admin/views/emailing/`.
- [ ] **5.2 [Sonnet]** Nastavení schránky (`/admin/emailing/settings`): formulář jednoho účtu (adresa, IMAP host/port, SMTP host/port — předvyplněné Gmail defaulty, login, heslo/app password — při editaci prázdné = beze změny, šifrovat), nápověda „Gmail: zapněte 2FA a vygenerujte App password", tlačítko „Otestovat připojení" (IMAP LOGIN + SMTP AUTH dry-run, výsledek do flash), CSRF.
- [ ] **5.3 [Sonnet]** Seznam (`/admin/emailing`): záložky Přijaté / Odeslané, sloupce Od/Komu · Předmět · Datum · Stav (tučně nepřečtené dle `read_at`; badge „↩ Zodpovězeno" dle `answered_at`; ikona zdroje schránka/formulář), stránkování, LIKE hledání; stav synchronizace (`last_sync_at`/`last_sync_error`) + tlačítko „Stáhnout poštu"; auto-sync trigger při načtení (přes throttle/zámek z 3.4, nikdy neblokovat render déle než budget).
- [ ] **5.4 [Opus]** Detail (`/admin/emailing/detail?id=`): hlavičky, tělo — primárně `body_text`; `body_html` jen přes server-side strip skriptů/handlerů + `<iframe sandbox="" srcdoc>`; výpis jmen příloh (obsah je v raw_source — bez download UI, mimo scope); při zobrazení `markRead`; tlačítko **Odpovědět**; odkaz na spárovanou odpověď/originál (`answered_by_id`/`in_reply_to`). XSS-kritický úkol.
- [ ] **5.5 [Sonnet]** Odpověď (`/admin/emailing/reply?id=`): GET — prefill To = Reply-To||From, Subject `Re: …` (bez zdvojení), citace původního textu prefixem `>`; POST — CSRF + `RequestThrottle`, validace příjemce `FILTER_VALIDATE_EMAIL`, odeslání přes `MailerService` s `In-Reply-To` → originálu nastavit `markAnswered(answered_by_id)`, flash + redirect na detail originálu. Bez `id` funguje jako „nový e-mail" (prázdný compose) — pokrývá „odesílání e-mailů uživatelům".
- [ ] **5.6 [Haiku]** CSS/UX doladění dle stylu adminu + badge počtu nepřečtených u položky Emailing v nav.

## Fáze 6 — Kvalita a předání

- [ ] **6.1 [Sonnet]** Kompletní `php tests/run.php`: stávajících 45 testů + 26 golden 0 failures (kontakt GET nezměněn) + nové testy z fází 1–3; ruční E2E: poslat mail do schránky → Stáhnout poštu → objeví se → otevřít → Odpovědět → odeslat → originál „Zodpovězeno" + odpověď dorazila a je v Gmail Odeslaných; formulář z webu → objeví se v Přijatých. Visual QA sekce na 390×844 + 1920×1080 (dle projektového pravidla bodovat jen layout/CSS, ne obsah).
- [ ] **6.2 [Haiku]** Dokumentace: `ARCHITECTURE.md` (modul Emailing: 2 tabulky, IMAP sync, SMTP odesílání), `README.md` (nastavení schránky vč. Gmail app password postupu), `.env.example` (`MAIL_ENCRYPTION_KEY`).
- [ ] **6.R [review]** Závěrečné review featury modelem **Fable 5** (síťoví klienti, zásah do kontakt handleru, XSS plocha detailu), verdikt do `UKOL.md`.

## Rizika (vědomá)

- Blokované odchozí porty 993/465/587 z webzdarma → STOP ve Fázi 0 (bez nich featura nejde postavit v tomto scope).
- App password: přestane platit při změně hesla Google účtu → `last_sync_error` to zviditelní v adminu; do README krok „vygenerovat nové".
- IMAP vidí jen INBOX — pošta odeslaná z vlastního mail klienta vlastníka se nezálohuje (odpovědi z adminu ano; plná centralizace vypuštěna se scope v1). Spam složka se nesynchronizuje.
- Gmail limit 15 současně připojených klientů — náš krátký sync (connect→fetch→logout) je neškodný.
- Disková/DB kvóta: raw_source cap 2 MB; retention (mazání starých) je budoucí rozšíření.
- Mimo scope: stav „otevřeno" (pixel), přílohy download UI, více schránek, složky, ruční přepínání „vyřízeno", kampaně, OAuth/Gmail API.

## Zdroje (ověření 2026-07-19)

- [Sign in with app passwords — Gmail Help](https://support.google.com/mail/answer/185833) (2SV povinné, stav 2026)
- [Transition from less secure apps to OAuth — Workspace Admin Help](https://support.google.com/a/answer/14114704) (konec basic auth; OAuth nebo app password pro IMAP/SMTP/POP)
- [Add Gmail to another email client — Gmail Help](https://support.google.com/mail/answer/7126229) (IMAP od ledna 2025 vždy zapnutý; limit 15 klientů)
- [Read Gmail messages on other email clients using POP — Gmail Help](https://support.google.com/mail/answer/7104828) (POP „download once" / recent mode)
- [Email sender guidelines](https://support.google.com/a/answer/81126) a [FAQ](https://support.google.com/a/answer/14229414) (SPF/DKIM/DMARC alignment od 2/2024; gmail.com spoofing → quarantine)
- [Using OAuth 2.0 to Access Google APIs](https://developers.google.com/identity/protocols/oauth2) a [Manage App Audience — Cloud Console Help](https://support.google.com/cloud/answer/15549945) (Testing mód = refresh token 7 dní; restricted scopes vyžadují verifikaci/CASA)
