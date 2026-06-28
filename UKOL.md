# UKOL: GTM apply – duplicate name (stale cache)

Datum: 2026-06-28
Zadání: Apply má před zápisem načíst aktuální inventář workspace, ne zastaralý cache.
Hotovo když: Apply na čerstvém inventáři vytvoří jen chybějící entity, existující aktualizuje – bez "duplicate name"; PHP lint čistý.

## Kroky

- [x] Najít příčinu — apply() i plan() staví plán z loadCached; cache pořízen na prázdném workspace, mezitím vznikly remote entity → findRemote nic nenajde → create → GTM duplicate name
- [x] Upravit GtmController::apply() — použít čerstvý inventory->load() místo loadCached()
- [x] Ošetřit, že fresh load při apply nezhavaruje (token refresh už řešen dříve — validAccessToken)
- [x] PHP lint
- [x] Commit

## Poznámky

- findRemote (GtmPlanService) matchuje remote entity podle management_key v notes nebo přesného názvu.
  Funguje jen pokud inventář obsahuje aktuální remote entity → proto apply potřebuje fresh load.
- Předchozí částečné applye vytvořily entity v GTM, ale neuložily je lokálně (chyba last_status padla
  AŽ po API create). Fresh load je teď uvidí a plán je adoptuje (update/noop) místo create.
- Pokud by stejné jméno existovalo víckrát (duplicitní remote z opakovaných pokusů), findRemote vrátí
  conflict a apply se bezpečně zastaví — uživatel musí duplicitu v GTM uklidit ručně.
- plan() (Sestavit plán) ponechávám na loadCached — je to read-only preview; uživatel má před ním
  Načíst inventář. Apply se ale nesmí spoléhat na stáří cache.
- Bez zásahu do GA4 / frontend trackingu.

## Výsledek

GtmController::apply() nyní volá inventory->load() (čerstvý stav workspace + uloží snapshot)
místo loadCached(). Plán se tak staví proti aktuálnímu GTM stavu: existující entity (i ty
vytvořené dřívějšími částečnými applyi) se matchnou přes management_key v notes / název a
aplikují jako update/noop místo create — konec chyby "Found entity with duplicate name".
plan() preview zůstává na cache (read-only). Fresh load řeší token refresh přes validAccessToken.
