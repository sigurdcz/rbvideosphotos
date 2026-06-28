# UKOL: Render GTM snippet na webu (consent-aware)

Datum: 2026-06-28
Zadání: Renderovat GTM kontejner na veřejném webu, pokud je ID vyplněné, tracking zapnutý a je marketing souhlas.
Hotovo když: Po vyplnění GTM ID + marketing souhlasu se načte googletagmanager.com/gtm.js?id=GTM-...; bez ID nebo souhlasu se nenačte; GA4 runtime beze změny.

## Kroky

- [x] Přidat input "Google Tag Manager ID" do Nastavení (admin/views/settings/index.php)
- [x] Předat googleTagManagerId do consent settings v SeoRenderer::renderConsentRuntime()
- [x] Přidat loadGoogleTagManager() do cookie-consent-runtime.js (gate marketing), zavolat z loadMarketingScripts()
- [x] Doplnit reload-on-disable pro GTM v reloadIfTrackingWasDisabled()
- [x] PHP lint (SeoRenderer + settings) OK; JS edity dle existujícího vzoru (node nedostupný)
- [x] Aktualizovat dokumentaci (ARCHITECTURE pozn. o GTM snippetu)
- [x] Commit

## Poznámky

- Pole google_tag_manager_id: sloupec existuje (schema 001), writable v SiteRepository:115, ukládá se v
  SettingsController:45 — ale v UI chybí input. Doplnit ho.
- GA/Clarity/Pixel injektuje klient cookie-consent-runtime.js po souhlasu. GTM = marketing kategorie
  (ARCHITECTURE.md). Mirror vzoru loadMetaPixel().
- Standardní GTM head snippet: (function(w,d,s,l,i){...gtm.js?id=...})(window,document,'script','dataLayer','GTM-XXXX').
- GA4 direct gtag zůstává beze změny (fáze nezasahuje do GA4 runtime). Pozn.: pokud by GTM kontejner měl
  vlastní GA4 config tag, mohlo by dojít k dvojímu měření — to je konfigurace v GTM, ne náš kód.
- Bez ID / window.__gtmLoaded / bez marketing consentu => nenačítat.
- Bez zásahu do GA4 runtime a frontend eventů.

## Výsledek

GTM container snippet se nyní renderuje na webu v rámci consent systému:
- Nastavení má nové pole "Google Tag Manager ID" (vedle GA ID), ukládá se do sites.google_tag_manager_id.
- SeoRenderer předává googleTagManagerId do cms-cookie-consent-settings.
- cookie-consent-runtime.js: nová loadGoogleTagManager() injektuje gtm.js?id=GTM-... jen když je ID
  vyplněné, ještě nenačtené a je marketing souhlas; volá se z loadMarketingScripts(); odebrání
  marketing souhlasu vyvolá reload (reloadIfTrackingWasDisabled).
- Prázdné ID nebo bez souhlasu = nic se nenačte. Při cookie_consent_enabled=0 se chová dle globálu.
- GA4 direct gtag a frontend eventy beze změny. ARCHITECTURE.md doplněna.
