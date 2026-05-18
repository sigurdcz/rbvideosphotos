# UKOL: Tracking systém — consent + konverze + admin toggles

Datum: 2026-05-18
Zadání: Implementovat kompletní GDPR-safe tracking s centrální konfigurací, consent systémem, JS modulem a admin UI.

## Kroky

- [x] Průzkum kódu — admin, DB, consent lišta, index.php struktura
- [x] Migrace — tracking_config tabulka v DB
- [x] config/tracking.php — centrální PHP konfigurace (SeoRenderer.renderTrackingRuntime)
- [x] Integrace s .env.php pro API klíče (přes sites tabulku + admin)
- [x] public/assets/js/tracking.js — window.AppTracking modul
- [x] Consent logika — propojení s existující lištou (canLoadAnalytics hook)
- [x] Injektáž trackingové konfigurace z PHP do JS (meta cms-tracking-config)
- [x] GTM/GA4/Clarity/Meta Pixel — podmíněné načítání dle consent + config
- [x] Event tracking — form submit, thank-you page, phone/email click
- [x] Event tracking — CTA, social, gallery, video, pricing, scroll 90%
- [x] Admin UI — sekce Tracking/Konverze se všemi toggles
- [x] Uložení/načítání toggle config z DB
- [x] Refactoring index.php — extrahovat tracking logiku ven (SeoRenderer)
- [x] Commitnout vše

## Poznámky

## Výsledek

Implementován kompletní GDPR-safe tracking systém:
- **DB migrace 015**: 6 nových sloupců v tabulce sites (tracking_enabled, tracking_require_consent, tracking_debug, clarity_id, meta_pixel_id, tracking_events_json)
- **SeoRenderer**: nová metoda renderTrackingRuntime() injektuje cms-tracking-config meta tag + tracking.js defer script; clarityId/metaPixelId přidány do consent settings JSON
- **cookie-consent-runtime.js**: loadClarity() (analytics consent) + loadMetaPixel() (marketing consent) — podmíněné načítání bez chyb při prázdném ID
- **tracking.js** (nový): window.AppTracking s init/canTrack/trackEvent API, 10 event handlerů, ES5 kompatibilita, fail-safe, debug mód
- **SettingsController**: buildTrackingEventsJson() helper + uložení všech tracking polí
- **Admin UI**: karta Tracking/Konverze s přehledným checkboxem pro každý event + ID pole pro Clarity a Meta Pixel
