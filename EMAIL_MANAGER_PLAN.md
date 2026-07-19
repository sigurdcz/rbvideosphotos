# Email manager — implementační plán

Datum: 2026-07-19 · Stav: PŘIPRAVENO K IMPLEMENTACI · Určeno pro: LLM agenty (u každého úkolu je přiřazený model)

## Cíl featury

1. V adminu nová sekce **Emailing**.
2. Veškeré e-maily **zálohované a centralizované** — odchozí (kontaktní formulář, odpovědi z adminu) i příchozí (IMAP sync schránky vlastníka) na jednom místě: parsovaná data v DB + surové EML ve storage.
3. **Odpověď kliknutím z administrace** — bez otevírání mail provideru: tlačítko Odpovědět → předvyplněný formulář → odeslání přes SMTP účtu vlastníka (fallback `mail()`).
4. U e-mailu viditelný stav **otevřen** (tracking pixel u odchozích HTML mailů; `read_at` u příchozích otevřených v adminu) a **zodpovězen** (někdo v adminu kliknul Odpovědět, napsal a odeslal → `answered_at` + vazba na odchozí zprávu; navíc párování externích odpovědí přes `In-Reply-To`/`References`).

## Výchozí stav (ověřeno v kódu 2026-07-19)

- Jediný odchozí e-mail: `POST /kontakt/odeslat` → `@mail()` v `public/index.php:2317` (plain text, `From: noreply@{host}`); **nic se neukládá do DB** (jen `Logger::warning` při selhání). CSRF + honeypot + `RequestThrottle` v handleru.
- Žádný SMTP/IMAP kód. `composer.json` bez externích závislostí; `vendor/autoload.php` je ručně psaný PSR-4 shim `App\` → `app/` — **nezavádět composer závislosti**.
- Hosting webzdarma (`kvalitne.cz`), MySQL, deploy FTPS přes GitHub Actions, konfigurace v `.env`.
- Precedenty k zrcadlení:
  - `App\Services\GtmTokenCipher` — AES-256-GCM šifrování citlivých hodnot, klíč v `.env` → vzor pro hesla schránek.
  - `SecurityTxtHttpResponder` + skill `cms-technical-file-endpoint` — veřejný endpoint **před** `Auth::start()`, bez session cookie → vzor pro tracking pixel.
  - `ApiController` — autentizace `X-Api-Key` (`API_KEY` v `.env`) → vzor pro externí sync endpoint.
  - Admin CRUD vzor: routy v `admin/index.php`, Controller s `requireAuth()`/`csrfCheck()`, views v `admin/views/<sekce>/`, nav položka v `admin/views/layouts/main.php` (viz `ReviewController`).
  - Migrace append-only v `database/migrations/`, poslední je `021` → nové začínají `022`.
  - Testy: `php tests/run.php` (45 testů + 26 golden checků) — golden pokrývá veřejné GET renderování; **GET render `/kontakt` se nesmí změnit ani o byte**.

## Závazná architektonická rozhodnutí

1. **Vlastní socket klienti** (SMTP submission + IMAP4rev1 subset) v `App\Services\Mail\` přes `stream_socket_client` s TLS. Žádné composer závislosti, `ext-imap` se nepředpokládá (deprecated, na sdíleném hostingu nejisté). Pokud Fáze 0 zjistí dostupné `ext-imap`, smí vzniknout alternativní driver za stejným rozhraním — výchozí zůstává socket implementace.
2. **Záloha = surové EML** v `storage/mail/YYYY/MM/{id}.eml` + parsovaná data v DB. `storage/mail/` je runtime state cílového prostředí (přidat do `.gitignore` a do deploy exclude — stejně jako `storage/media`).
3. **Stavy zprávy:** `opened_at`+`open_count` (pixel, odchozí HTML maily), `read_at` (otevřeno v admin UI), `answered_at`+`answered_by_id` (odpovězeno z adminu; nebo spárovaná externí odpověď).
4. **Sync bez spoléhání na cron** (webzdarma free nemusí mít): (a) oportunistický při návštěvě `/admin/emailing` — mutex + minimální interval (např. 5 min) + časový budget (např. 20 s), (b) `POST /api/v1/emailing/sync` s `X-Api-Key` pro externí cron/uptime pinger, (c) tlačítko „Synchronizovat teď".
5. **Odesílání:** primárně SMTP submission přes nakonfigurovaný účet (autentizované, správný `From`, kopie do Sent přes IMAP APPEND); fallback `mail()` když účet není nakonfigurován. Kontaktní formulář musí fungovat i bez konfigurace účtů.
6. **Hesla schránek** šifrovaná AES-256-GCM (`MailCredentialCipher` dle `GtmTokenCipher`), klíč `MAIL_ENCRYPTION_KEY` v `.env`. Hesla nikdy do logů ani do HTML.
7. **HTML těla příchozích mailů = nedůvěryhodný vstup (XSS).** Render výhradně: server-side strip `<script>`/event handlerů/external form actions → `<iframe sandbox="" srcdoc="...">`. Nikdy přímo do admin DOM.
8. **Tracking pixel** = veřejný `GET /e/{token}.gif` routovaný před `Auth::start()` v `public/index.php` (vzor security.txt; použít skill `cms-technical-file-endpoint`). Bez session, `Cache-Control: no-store`, vždy HTTP 200 + 1×1 GIF (i pro neplatný token — žádná enumerace).
9. **Threading:** každá odchozí zpráva dostává vlastní `Message-ID` (`<random@{host}>`); odpověď nastavuje `In-Reply-To` + `References`. Sync páruje příchozí zprávy proti uloženým `Message-ID` → detekce externích odpovědí.

## DB schéma (kontrakt pro migraci `022_create_emailing_tables.sql`)

- **`email_accounts`**: `id`, `site_id` FK, `label`, `email_address`, `imap_host`, `imap_port` (default 993), `imap_encryption` enum(`ssl`,`tls`,`none`), `smtp_host`, `smtp_port` (default 465), `smtp_encryption` enum(`ssl`,`tls`,`none`), `username`, `password_encrypted` TEXT, `sent_folder` VARCHAR(190) default `'Sent'`, `is_active` TINYINT(1) default 1, `last_sync_at` DATETIME NULL, `last_sync_error` TEXT NULL, `created_at`, `updated_at`.
- **`email_folders`**: `id`, `account_id` FK, `name` VARCHAR(190), `uidvalidity` BIGINT UNSIGNED NULL, `last_uid` BIGINT UNSIGNED default 0, `created_at`, `updated_at`; UNIQUE(`account_id`,`name`). Sync stav per složka (INBOX, Sent).
- **`email_messages`**: `id`, `site_id`, `account_id` FK NULL, `folder_id` FK NULL, `uid` BIGINT UNSIGNED NULL, `direction` enum(`inbound`,`outbound`), `source` enum(`imap`,`contact_form`,`admin_reply`,`system`), `message_id` VARCHAR(255) NULL (index), `in_reply_to` VARCHAR(255) NULL (index), `references_header` TEXT NULL, `from_name` VARCHAR(255), `from_email` VARCHAR(255), `to_json` TEXT, `cc_json` TEXT NULL, `reply_to_email` VARCHAR(255) NULL, `subject` VARCHAR(998), `sent_date` DATETIME NULL, `body_text` LONGTEXT NULL, `body_html` LONGTEXT NULL, `raw_headers` TEXT NULL, `eml_path` VARCHAR(255) NULL, `size_bytes` INT UNSIGNED default 0, `has_attachments` TINYINT(1) default 0, `tracking_token` CHAR(32) NULL UNIQUE, `opened_at` DATETIME NULL, `open_count` INT UNSIGNED default 0, `read_at` DATETIME NULL, `answered_at` DATETIME NULL, `answered_by_id` FK→`email_messages.id` NULL, `created_at`, `updated_at`; UNIQUE(`folder_id`,`uid`) (idempotentní sync), index (`site_id`,`direction`,`created_at`).
- **`email_attachments`**: `id`, `message_id` FK, `filename` VARCHAR(255), `mime_type` VARCHAR(190), `size_bytes` INT UNSIGNED, `storage_path` VARCHAR(255) NULL (NULL = neextrahované, jen v EML), `created_at`.
- **`email_events`**: `id`, `message_id` FK, `event` enum(`open`,`reply_click`,`reply_sent`,`sync_in`,`send`), `meta_json` TEXT NULL, `created_at`. (Do `meta_json` neukládat celé IP — GDPR; stačí zkrácený user-agent.)

## Úrovně obtížnosti

Tři úrovně dle mapování na modely: **[Haiku]** mechanická práce podle šablony/kontraktu · **[Sonnet]** standardní implementace podle existujícího vzoru · **[Opus]** složitá logika, edge cases, bezpečnostně citlivá místa.

Tvrdá pravidla (z projektového model-check): nikdy Haiku na síťový/autoritativní kód; po 2 selháních eskalace o úroveň výš; síťové klienty a zásahy do kontakt POST handleru po dokončení zreviduje **Fable 5** (review krok, ne čtvrtá úroveň implementace).

---

## Fáze 0 — Průzkum prostředí (blokuje Fázi 3+)

- [ ] **0.1 [Sonnet]** Ověřit na produkci (webzdarma) a lokálně: PHP verze, `ext-openssl`, funkčnost `stream_socket_client` s `ssl://` (odchozí porty 993/465/587 nejsou blokované), dostupnost `ext-imap` (jen informativně), funkčnost `mail()`, `max_execution_time`, dostupnost cronu v administraci webzdarma, a IMAP/SMTP parametry schránky vlastníka (host, porty, šifrování). Výsledky zapsat do `EMAILING.md` (nový provozní dokument featury). Pokud jsou odchozí TLS porty blokované, STOP — eskalovat uživateli (mění to architekturu příjmu pošty).

## Fáze 1 — Základy: migrace, šifra, repozitáře

- [ ] **1.1 [Haiku]** Migrace `database/migrations/022_create_emailing_tables.sql` přesně dle kontraktu výše (idempotentní `CREATE TABLE IF NOT EXISTS`, utf8mb4). Nepřidávat nic navíc.
- [ ] **1.2 [Haiku]** `App\Services\Mail\MailCredentialCipher` — adaptace `GtmTokenCipher` (AES-256-GCM), klíč `MAIL_ENCRYPTION_KEY`; přidat klíč do `.env.example` s generovacím příkazem. Přidat `storage/mail/` do `.gitignore` a deploy exclude v GitHub Actions workflow.
- [ ] **1.3 [Sonnet]** Repozitáře dle vzoru existujících: `EmailAccountRepository` (CRUD, `findActive`), `EmailMessageRepository` (stránkovaný list s filtry direction/source/fulltext, `findById`, `findByMessageId`, `upsertByFolderUid`, `markRead`, `markAnswered`, `markOpened` — first-open + increment, `insertOutbound`, `insertInbound`), `EmailFolderRepository`, `EmailEventRepository` (`log`, `allForMessage`). Prepared statements všude.
- [ ] **1.4 [Haiku]** Unit testy repozitářů podle vzoru stávajících testů v `tests/` (zapojit do `tests/run.php`).

## Fáze 2 — MIME vrstva (bez sítě)

- [ ] **2.1 [Opus]** `App\Services\Mail\MimeMessageBuilder` — sestavení RFC 5322 zprávy: `multipart/alternative` (text + HTML), RFC 2047 kódování hlaviček (UTF-8), quoted-printable/base64 těla, generování `Message-ID`, volitelné `In-Reply-To`/`References`, volitelná injektáž tracking pixelu do HTML části, validace hlaviček proti header injection (CR/LF strip — vzor `cleanHeaderValue` v kontakt handleru). Výstup: kompletní raw message string + samostatně headers/body pro `mail()` fallback.
- [ ] **2.2 [Opus]** `App\Services\Mail\MimeMessageParser` — parsování raw EML: unfold + RFC 2047 dekódování hlaviček, rekurzivní multipart walk, priorita `text/plain`+`text/html`, konverze charsetů do UTF-8 (`mb_convert_encoding`, fallback windows-1250/iso-8859-2 — česká pošta!), extrakce příloh (metadata vždy; obsah na disk jen do limitu např. 5 MB/soubor, jinak `storage_path=NULL`), tolerantní k vadným zprávám (nikdy fatal, degradace na raw text).
- [ ] **2.3 [Sonnet]** Sada `.eml` fixtures (`tests/fixtures/mail/`): čistý text, multipart alternative, příloha, česká diakritika v předmětu (RFC 2047), windows-1250 tělo, vnořený multipart, vadná zpráva. Unit testy parseru a builderu (round-trip: builder → parser).

## Fáze 3 — Síťoví klienti (síťová autorita — nikdy Haiku)

- [ ] **3.1 [Opus]** `App\Services\Mail\SmtpClient` — socket SMTP submission: connect (implicit TLS 465 / STARTTLS 587), EHLO, AUTH LOGIN+PLAIN, MAIL FROM/RCPT TO/DATA (dot-stuffing!), QUIT; timeouty, čitelné chybové stavy (špatné heslo vs. síť vs. odmítnutý příjemce), žádná hesla v log výstupu.
- [ ] **3.2 [Opus]** `App\Services\Mail\ImapClient` — socket IMAP4rev1 subset: CAPABILITY, LOGIN, LIST, SELECT/EXAMINE (číst UIDVALIDITY+UIDNEXT), `UID SEARCH UID N:*`, `UID FETCH (BODY.PEEK[] RFC822.SIZE)` (PEEK — nenastavovat \Seen ve schránce!), APPEND (kopie odeslané zprávy do Sent), LOGOUT; literals `{n}` handling, timeouty, tolerantní parser odpovědí.
- [ ] **3.3 [Opus]** `App\Services\Mail\MailerService` — fasáda odesílání: vstup (from account|null, to, subject, text, html?, in_reply_to message?) → builder → SMTP přes účet, fallback `mail()`; po odeslání uložit outbound zprávu do DB + EML do storage, event `send`, APPEND do Sent složky účtu (best-effort, chyba nesmí shodit odeslání).
- [ ] **3.4 [Sonnet]** Transcript testy obou klientů: fake server přes stream wrapper / dvojici in-memory streamů, scénáře happy path + auth fail + timeout + vadná odpověď.
- [ ] **3.R [— review]** Nezávislé review Fáze 3 modelem **Fable 5** (tvrdé pravidlo model-check: síťová autorita). Bez schválení nepokračovat na Fázi 4.

## Fáze 4 — Sync a integrace kontaktního formuláře

- [ ] **4.1 [Opus]** `App\Services\Mail\EmailSyncService` — inkrementální sync per účet+složka (INBOX + Sent): porovnat UIDVALIDITY (změna → reset `last_uid=0` + warning log, žádné mazání dat), `UID SEARCH` od `last_uid+1`, batch limit (např. 25 zpráv/běh) + časový budget, per-účet mutex (DB/soubor — vzor `RequestThrottle` storage), fetch → uložit EML → parse → `upsertByFolderUid` (dedupe), event `sync_in`; párování `In-Reply-To`/`References` proti `message_id` existujících zpráv → u originálu nastavit `answered_at` (externí odpověď) a u odchozích z adminu spárovat doručenou kopii. Chyba jednoho účtu nesmí zastavit ostatní (`last_sync_error`).
- [ ] **4.2 [Sonnet]** Sync triggery: (a) oportunistický v `EmailingController::index` (interval + budget + mutex, nikdy neblokovat render déle než budget), (b) `POST /api/v1/emailing/sync` v `ApiController` dispatch s `X-Api-Key` (vzor existujících API rout) — pro webzdarma cron / externí pinger, (c) POST route + tlačítko „Synchronizovat teď" v adminu (CSRF). Zapsat do `API.md`.
- [ ] **4.3 [Opus]** Integrace kontaktního formuláře (`public/index.php`, POST `/kontakt/odeslat`): po stávajících validacích (1) uložit zprávu jako `inbound`/`contact_form` do DB (od: návštěvník, komu: `form_email`) — ukládá se i když `mail()` selže (stav v `meta_json`), (2) notifikační mail majiteli skládat přes `MimeMessageBuilder` (multipart text+HTML s pixelem) místo ručních hlaviček. **PŘÍSNÝ SCOPE:** CSRF/honeypot/throttle logika a celý GET render `/kontakt` beze změny (golden 26/26 musí projít); DB chyba nesmí rozbít odeslání ani redirect (try/catch + log). Po dokončení review **Fable 5** (zásah do request/validate flow).

## Fáze 5 — Tracking otevření (pixel)

- [ ] **5.1 [Sonnet]** Veřejný endpoint `GET /e/{token}.gif` v `public/index.php` **před** `Auth::start()` — použít skill `cms-technical-file-endpoint` a vzor `SecurityTxtHttpResponder`: validace tokenu (`^[a-f0-9]{32}$`), lookup `tracking_token` → `markOpened` (první otevření `opened_at`, vždy `open_count++`, event `open` bez plné IP), vždy HTTP 200 + 1×1 transparent GIF (hardcoded bytes), `Cache-Control: no-store, no-cache`, bez session cookie. Aktualizovat request flow v `ARCHITECTURE.md`.
- [ ] **5.2 [Haiku]** Zapojení: generování `tracking_token` při každém odchozím mailu, předání pixel URL builderu; site nastavení `emailing_tracking_enabled` (default on, vypínatelné — GDPR poznámka do `EMAILING.md`: pixel je indikativní, Gmail proxy/Apple MPP zkreslují).

## Fáze 6 — Admin sekce Emailing

- [ ] **6.1 [Haiku]** Skeleton: routy v `admin/index.php` (`/admin/emailing`, `/admin/emailing/detail`, `/admin/emailing/reply` GET+POST, `/admin/emailing/sync` POST, `/admin/emailing/accounts` CRUD sada), `EmailingController` + `EmailAccountController` kostry s `requireAuth()`, nav položka „Emailing" v `admin/views/layouts/main.php`, prázdný `admin/views/emailing/index.php`.
- [ ] **6.2 [Sonnet]** Účty (`/admin/emailing/accounts`): CRUD formulář dle vzoru reviews (heslo se při editaci nezobrazuje, prázdné = beze změny; šifrovat přes `MailCredentialCipher`), tlačítko „Otestovat připojení" (IMAP LOGIN + SMTP EHLO/AUTH dry-run, výsledek do flash), validace hostů/portů.
- [ ] **6.3 [Sonnet]** Inbox (`/admin/emailing`): záložky Přijaté/Odeslané/Vše, sloupce Od/Komu · Předmět · Datum · Stav (● nepřečteno tučně dle `read_at`, 👁 otevřeno s `open_count` dle `opened_at`, ↩ zodpovězeno dle `answered_at`, ikona zdroje formulář/schránka/admin), stránkování, LIKE hledání (od/předmět/tělo), filtr účtu. Info stav `last_sync_at`/`last_sync_error` + tlačítko sync.
- [ ] **6.4 [Opus]** Detail (`/admin/emailing/detail?id=`): hlavičky, tělo — HTML přes server-side sanitizaci + `<iframe sandbox="" srcdoc>` (rozhodnutí 7), fallback plain text; přílohy (extrahované = download přes route s realpath ochranou proti `storage/mail/`; neextrahované = on-demand výtah z EML parserem, nad limit = hláška „stáhněte ve schránce"); vlákno (řetěz přes `in_reply_to`/`answered_by_id`); při zobrazení nastavit `read_at`; tlačítko **Odpovědět** (→ zaloguje event `reply_click` a vede na compose). XSS-kritický úkol.
- [ ] **6.5 [Opus]** Odpověď (`/admin/emailing/reply`): GET compose — prefill To = `Reply-To` || `From`, Subject `Re: …` (bez zdvojení Re:), citovaný původní text (`> `), volitelný podpis ze site nastavení; POST — CSRF, `RequestThrottle` (ochrana proti omylem-spamu), odeslání přes `MailerService` s `in_reply_to` → originál dostane `answered_at`+`answered_by_id`, event `reply_sent`, flash + redirect na detail originálu. Validace příjemce (`FILTER_VALIDATE_EMAIL`), header injection nemožná (builder validuje).
- [ ] **6.6 [Haiku]** CSS/UX doladění sekce dle stylu zbytku adminu (stavové ikony, badge počtu nepřečtených v nav).

## Fáze 7 — Kvalita, dokumentace, předání

- [ ] **7.1 [Sonnet]** Kompletní `php tests/run.php`: stávající 45 testů + 26 golden checků 0 failures (kontakt GET nezměněn!) + nové testy (repozitáře, MIME, transcript, sync s fake klientem). Smoke test pixel endpointu (curl: 200, GIF bytes, žádný `Set-Cookie`).
- [ ] **7.2 [Haiku]** Dokumentace: `ARCHITECTURE.md` (modul Emailing — tabulky, request flow `/e/{token}.gif`, sync triggery), `API.md` (sync endpoint), `README.md` (setup schránky), `.env.example` (`MAIL_ENCRYPTION_KEY`), `EMAILING.md` (provoz: přidání účtu, cron doporučení, GDPR/retention poznámky).
- [ ] **7.3 [Sonnet]** E2E checklist + visual QA: poslat testovací mail do schránky → sync → objeví se v Přijatých → otevřít (read) → Odpovědět → odeslat → ověřit doručení odpovědi, `answered` stav, pixel open po otevření odpovědi příjemcem; formulář na webu → objeví se v Emailing; visual QA admin sekce 390×844 + 1920×1080 (dle projektového pravidla: nebodovat obsah z DB, jen layout/CSS).
- [ ] **7.R [— review]** Závěrečné review celé featury modelem **Fable 5** (síťová autorita + nové veřejné endpointy + XSS plocha), verdikt do `UKOL.md`.

## Rizika a omezení (vědomá)

- **Hosting:** blokované odchozí porty by zabily IMAP/SMTP (řeší Fáze 0 STOP podmínkou). `mail()` fallback drží kontaktní formulář funkční vždy.
- **Pixel je indikativní:** Gmail image proxy (cache → podpočítání), Apple Mail Privacy Protection (prefetch → nadpočítání), plain-text klienti (nic). Stav „otevřeno" prezentovat jako orientační.
- **Disk kvóta webzdarma:** EML + přílohy rostou → limit extrakce příloh, retention nastavení (budoucí rozšíření: mazání EML starších N měsíců).
- **GDPR:** centralizace mailů = osobní údaje (přístup jen za `requireAuth`, retention zmíněna v `EMAILING.md`); open-tracking zmínit v privacy policy webu.
- **Mimo scope této featury:** více schránek s per-user přístupem, štítky/složky vlastní, fulltextový index, push (IDLE), odesílání kampaní/newsletterů, DKIM podepisování (na sdíleném hostingu řeší poskytovatel).
