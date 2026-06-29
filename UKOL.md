# UKOL: Opravy šablony + tracking logs toggle

Datum: 2026-06-29
Zadání: Přidat checkbox pro tracking logy v admin Nastavení + opravit 3 problémy v šabloně jemná béžová.
Hotovo když: Checkbox v Nastavení funguje, hero bez gradientu, texty na Hodnocení viditelné, sociální sítě v HP formuláři pryč.

## Kroky

- [x] Přidat checkbox pro tracking logs do admin/views/settings/index.php
- [x] Přidat setting do config (enable_gtm_tracking_logs do SeoRenderer)
- [x] Přidat logiku do cookie-consent-runtime.js pro podmíněné logy
- [x] Oprava šablony: odstranit gradient z hero sekce (.hp-hero::after)
- [x] Oprava šablony: fixovat viditelnost textů na Hodnocení (review card name/desc barvy)
- [x] Oprava šablony: smazat div.jb-contact-socials z HP formuláře
- [x] PHP lint
- [x] Commit

## Poznámky

- Tracking logs: když toggle = on, JS console zobrazuje [GA4 Tracking] zprávy
- Hero gradient: je na řádce ~374 (linear-gradient to bottom), blokuje viditelnost scrollu
- Hodnocení: .jb-review-person strong/span jsou bílé (#fff) na bílém pozadí — potřebuje barvu
- Sociální sítě: na HP jsou v kontaktu pod formulárem, v Hodnocení nejsou (jen tam smazat)

## Výsledek

✅ Checkbox pro tracking logy: admin Nastavení → nová checkbox "Zobrazovat GTM tracking logy"

- Při ON: window.cmsCookieConsent.logTrackingEvent() vypíše [GA4 Tracking] do DevTools Console
- Flág: enableGtmTrackingLogs v runtime settings (cms-cookie-consent-settings meta)

✅ Opravy šablony jemná béžová:

- Hero sekce: odstraněn gradient (hp-hero::after) — tlačítko SCROLL teď vidět
- Hodnocení: jména/popis nyní tmavé (#1a1610/#5f5143) — viditelné na všech pozadích
- HP formulář: sociální sítě skrytá (jb-contact-socials display:none)

Commit: e52cb8a
