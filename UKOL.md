# UKOL: Odebrat GTM, ponechat pouze GA4

Datum: 2026-05-18
Zadání: Celý tracking přesunout výhradně pod Google Analytics 4 (gtag.js). GTM kompletně odebrat z kódu, nastavení i výstupu.

## Kroky

- [x] SeoRenderer.php — odebrat renderGtmHead(), renderGtmNoscript(), volání v renderHead() a renderBodyScripts(); odebrat googleTagManagerId z renderConsentRuntime()
- [x] SeoRenderer.php — renderBodyScripts() vrátit zpět k body_scripts only
- [x] tracking.js — odebrat dataLayer.push() blok (GTM gone, zbytečné)
- [x] settings/index.php — odebrat pole Google Tag Manager ID z UI
- [x] Commitnout výsledek

## Poznámky

- tracking.js volá gtag() přímo — GA4 eventy fungují bez GTM
- cookie-consent-runtime.js loadGoogleTagManager() byl odstraněn v předchozí session
- DB sloupec google_tag_manager_id zůstane (neškodí), jen se nezobrazuje v UI

## Výsledek
