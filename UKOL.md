# UKOL: Vypnout cookie consent v hlavnim seedu

Datum: 2026-07-12
Zadani: Nastavit v hlavnim seedu cookie consent listu defaultne jako vypnutou, aniz by se odstranily nebo zakazaly analyticke a tracking nastroje.

## Kroky

- [x] Auditovat seed a runtime podminku vykresleni cookie consent listy
- [x] Nastavit `cookie_consent_enabled = 0` v hlavnim seedu pro novy i existujici seedovany web
- [x] Pridat regresni test seed defaultu
- [x] Spustit lokalni Armageddon a Demo obsah a overit DB i homepage bez listy
- [x] Aktualizovat relevantni dokumentaci
- [x] Provest Wiki Reflection a uzavrit vysledek

## Poznamky

Acceptance signal: po `Armageddon -> Demo obsah` musi mit hlavni site v DB `cookie_consent_enabled = 0`, homepage nesmi obsahovat viditelnou consent listu/dialog a fingerprint musi zustat dostupny. Analyticke identifikatory, tracking konfigurace a runtime soubory zustavaji zachovane.

Historicke migrace se nemeni; jde o vychozi seed/bootstrap hodnotu, nikoli schema zmenu.

Audit: `seedUpsertSite()` dosud pri INSERTu spolehal na DB default `1` a pri UPDATE consent nemenil. Runtime `cookie-consent.js` pri hodnote false skryje host, modal i backdrop; `cookie-consent-runtime.js` v tomto rezimu povoli analytiku bez consent kroku.

Hlavni seed ted v INSERT i UPDATE vetvi explicitne zapisuje `cookie_consent_enabled = 0`. Kategorie analytics/marketing se nemeni a zustavaji povolene podle stavajicich defaultu/nastaveni.

Demo fingerprint nove zahrnuje hlavni consent prepinac i analytickou a marketingovou kategorii, aby rozdil tohoto runtime chovani mezi lokalem a online serverem nezustal skryty.

Regresni sada hlida INSERT i UPDATE v hlavnim seedu a samostatne overuje, ze consent runtime serializuje hlavni prepinac jako false, zatimco analyticka a marketingova kategorie zustavaji true.

Kompletni lokalni reset aplikoval 21 migraci a vytvoril fingerprint `fa2daef108a8c1b13c6abd6d3d909b204324f1c9b207a42ab51351e4fc470602`. JSON fingerprint report potvrdil consent `0`, analytics kategorii `1` a marketing kategorii `1`; homepage vraci HTTP 200 a runtime meta obsahuje `cookieConsentEnabled:false` i `analyticsCategoryEnabled:true`.

Jednorazovy inline PHP DB dotaz selhal na PowerShell quoting az po uspesnem resetu. Hodnoty byly nasledne nacteny pres standardni JSON fingerprint report; selhani nebylo povazovano za uspesny dukaz ani skryto fallbackem.

README popisuje consent-off seed default a zachovani analytiky, ARCHITECTURE vysvetluje runtime model a INSTRUCTIONS z nej delaji trvaly seed invariant. API se nemeni.

## Vysledek

Hlavni seed nastavuje cookie consent listu na vypnuto v INSERT i UPDATE vetvi. Kompletni lokalni reset potvrdil DB hodnoty consent `0`, analytics `1`, marketing `1`; homepage vraci HTTP 200 a runtime konfiguraci `cookieConsentEnabled:false`. Novy fingerprint je `fa2daef108a8c1b13c6abd6d3d909b204324f1c9b207a42ab51351e4fc470602`.

Aktualizovany relevantni dokumenty: `README.md`, `ARCHITECTURE.md` a `INSTRUCTIONS.md`. API kontrakt se nemenil. PHP lint prosel, regresni sada prosla 24/24, `git diff --check` je cisty a historicke migrace zustaly beze zmeny.

Wiki Reflection: domena `pipelines` zustava `ok`; hub a dossier byly aktualizovany a samostatne commitnuty v `C:\Wiki` jako `094a2d4 docs(seo editor): record consent-off seed default`.
