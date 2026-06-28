# UKOL: GTM apply selže s expirovaným OAuth tokenem

Datum: 2026-06-28
Zadání: Apply a Version create/publish musí obnovit expirovaný access token, ne jen prázdný.
Hotovo když: Apply i Version si před voláním GTM API obnoví expirovaný token a operace projde; PHP lint čistý.

## Kroky

- [x] Najít příčinu — Apply/Version requireAccessToken refreshuje jen při prázdném tokenu, ne při expiraci
- [x] Přidat centrální metodu GtmConnectionService::validAccessToken() (kontrola token_error, prázdnosti i expirace)
- [x] Použít validAccessToken() v GtmApplyService (nahradit requireAccessToken)
- [x] Použít validAccessToken() v GtmVersionService (nahradit requireAccessToken)
- [x] Použít validAccessToken() v GtmInventoryService (nahradit ensureAccessToken, sjednotit)
- [x] PHP lint všech změněných souborů
- [x] Commit

## Poznámky

- GTM access token platí ~1 h. requireForSite vrací uložený (decryptovaný) token, který je přítomný, ale po hodinách expirovaný → Google vrací "invalid authentication credentials".
- Inventory load to řeší správně (ensureAccessToken kontroluje token_expires_at). Apply (GtmApplyService:164) a Version (GtmVersionService:115) kontrolují jen prázdnost — bug.
- Centralizace odstraní duplicitu 3 skoro stejných metod.
- Bez zásahu do GA4 / frontend trackingu.

## Výsledek

Přidána centrální metoda `GtmConnectionService::validAccessToken()`, která token obnoví při
prázdnosti i expiraci (60s rezerva) a hlásí token_error i chybějící refresh token. Apply
(`GtmApplyService`), Version create/publish (`GtmVersionService`) i Inventory load
(`GtmInventoryService`) ji nyní používají; odstraněny tři duplicitní privátní metody
(`requireAccessToken` × 2, `ensureAccessToken`). Apply po hodinách už neselže s
"invalid authentication credentials" — token se před voláním GTM API obnoví.
