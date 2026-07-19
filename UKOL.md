# UKOL: Email manager — implementační plán pro LLM agenty

Datum: 2026-07-19
Zadání: Připravit bodový implementační plán featury „Email manager" (sekce Emailing v adminu, záloha+centralizace mailů, odpověď z administrace, tracking otevření/zodpovězení) — každý úkol s obtížností Haiku/Sonnet/Opus pro dalšího LLM agenta.
Hotovo když: Kompletní plán pokrývající všechny 4 požadavky je sepsán v chatu i v `EMAIL_MANAGER_PLAN.md`, každý úkol má přiřazený model a plán sedí na reálnou architekturu projektu.

## Kroky

- [x] Git commit rozdělaného stavu (`118ca71`)
- [x] Sumarizace a ověření zadání (cíl i kritérium jasné)
- [x] Průzkum architektury: mail() v kontakt POST handleru, admin CRUD vzor, migrace (poslední 021), `GtmTokenCipher`, `SecurityTxtHttpResponder`, ApiController X-Api-Key, composer bez závislostí, webzdarma hosting
- [x] Návrh architektury a rozdělení na fáze/úkoly s modely
- [x] Zápis plánu do `EMAIL_MANAGER_PLAN.md`
- [x] Sepsání plánu do chatu (deliverable)
- [x] Commit UKOL.md + EMAIL_MANAGER_PLAN.md
- [x] Revize v2 dle zpětné vazby (zjednodušení): vypuštěn tracking pixel/„otevřeno", IMAP→POP3, SMTP klient→`mail()`, 5 tabulek→2, bez API cron endpointu; přepsán `EMAIL_MANAGER_PLAN.md`, sepsáno do chatu, commit
- [x] Revize v3 — ověření proti Gmailu na internetu (zadání uživatele): basic auth mrtvý → app password (2SV), POP3 „download once" → návrat k IMAP subsetu (read-only, jen INBOX), `mail()` s gmail From → DMARC quarantine → návrat SmtpClienta, Gmail API/OAuth zamítnuto (7denní refresh tokeny v Testing, CASA audit); přepsán `EMAIL_MANAGER_PLAN.md` na v3 se zdroji, commit

## Poznámky

- Předchozí úkol (PHTML refactoring šablon) má otevřenou Fázi 6 (theme overridy a úklid + `PortfolioPublishService` blocker) — kompletní stav je v git historii `UKOL.md` (commit `118ca71` a starší) a v `TEMPLATE_REFACTOR_PLAN.md`. Tento úkol ho nenahrazuje, jen ho odsouvá v čase.
- Klíčová zjištění průzkumu: jediný odchozí mail dnes je `@mail()` v `public/index.php:2317` (kontaktní formulář, nic se neukládá); žádný SMTP/IMAP kód; composer bez externích závislostí (ručně psaný autoload shim); migrace končí `021` → nové od `022`; admin sekce = router záznamy + Controller + views + nav položka v `admin/views/layouts/main.php`.

## Výsledek

Plán featury Email manager ve verzi v3 (zjednodušený scope z v2, protokoly ověřené proti Gmailu): 7 fází (0–6), 19 úkolů (Haiku 5×, Sonnet 9×, Opus 5×) + 2 povinné Fable 5 review kroky. Scope: záloha mailů a zpráv z formuláře v DB (raw_source MEDIUMBLOB), IMAP sync INBOXu (read-only, app password), odesílání přes SMTP submission účtu schránky (mail() jen fallback pro notifikace), stav „zodpovězeno" odesláním odpovědi z adminu. Klíčová ověřená fakta: Gmail basic auth mrtvý (jen app password s 2SV), POP3 „download once" nevhodný pro zálohu, From gmail.com přes cizí server = DMARC quarantine, Gmail API/OAuth nereálné pro osobní projekt (7denní tokeny v Testing / CASA audit). Autoritativní verze se zdroji v `EMAIL_MANAGER_PLAN.md`.
