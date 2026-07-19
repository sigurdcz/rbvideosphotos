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

## Poznámky

- Předchozí úkol (PHTML refactoring šablon) má otevřenou Fázi 6 (theme overridy a úklid + `PortfolioPublishService` blocker) — kompletní stav je v git historii `UKOL.md` (commit `118ca71` a starší) a v `TEMPLATE_REFACTOR_PLAN.md`. Tento úkol ho nenahrazuje, jen ho odsouvá v čase.
- Klíčová zjištění průzkumu: jediný odchozí mail dnes je `@mail()` v `public/index.php:2317` (kontaktní formulář, nic se neukládá); žádný SMTP/IMAP kód; composer bez externích závislostí (ručně psaný autoload shim); migrace končí `021` → nové od `022`; admin sekce = router záznamy + Controller + views + nav položka v `admin/views/layouts/main.php`.

## Výsledek

Plán featury Email manager je hotový: 7 fází, 24 úkolů + 2 povinné Fable 5 review kroky, každý úkol s přiřazeným modelem (Haiku 7×, Sonnet 9×, Opus 8×). Autoritativní verze v `EMAIL_MANAGER_PLAN.md`, kompletní znění sepsáno i do chatu. Obsahuje závazná architektonická rozhodnutí (socket SMTP/IMAP klienti bez composer závislostí, EML záloha ve storage, DB kontrakt pro migraci 022, pixel endpoint před Auth::start(), duální sync trigger bez cronu) a STOP podmínku ve Fázi 0 (blokované TLS porty na hostingu).
