# UKOL: Konverzní tracking + oprava ukládání nastavení

Datum: 2026-05-18
Zadání: (1) Přesunout konverzní scripty na kliknutí místo /dekujeme stránky. (2) Opravit kritický bug — admin/settings nic neukládá.

## Kroky

- [x] Prozkoumat existující tracking kód (GA4, GTM, Meta Pixel, Clarity)
- [x] Prozkoumat kontaktní formulář — jak se odesílá, kde je success banner
- [x] Opravit tracking.js — 3 problémy:
  1. social_click nepálí fbq/generate_lead → přidáno fbq('track','Contact')
  2. setupThankYouPage nekontroluje ?sent=1 → přidána detekce search.indexOf('sent=1')
  3. chybí GA4 generate_lead → přidáno gtag('event','generate_lead')
- [x] Opravit SiteRepository — admin/settings nic neukládá:
  - supportsColumn() přepsáno na SHOW COLUMNS FROM sites (funguje na webzdarma)
  - writableColumns() doplněno o 6 tracking sloupců z migrace 015
- [ ] Commitnout výsledek

## Poznámky

Konverze bez /dekujeme — trackovat při:
1. Kliknutí na odeslat formulář (kontaktní formulář /kontakt)
2. Kliknutí na sociální sítě (Instagram, FB, YouTube atd.)

Bug v settings: webzdarma blokuje information_schema → supportsColumn() vrací vždy false
→ writableColumns() vrátí prázdné pole → update() se ihned vrátí bez zápisu

## Výsledek

- `SiteRepository::supportsColumn()` — přepsáno z `information_schema` na `SHOW COLUMNS FROM sites`; webzdarma blokoval information_schema, takže žádné nastavení se nikdy neuložilo
- `SiteRepository::writableColumns()` — doplněno 6 chybějících tracking sloupců (tracking_enabled, tracking_require_consent, tracking_debug, clarity_id, meta_pixel_id, tracking_events_json)
- `tracking.js` — 3 opravy: (1) `setupThankYouPage` nově detekuje `?sent=1`, (2) `generate_lead` GA4 key event při odeslání formuláře, (3) Meta Pixel `Contact` event při kliknutí na sociální sítě
