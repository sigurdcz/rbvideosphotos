# UKOL: Fix analytics/tracking issues — inline JS, duplicate runtime, encoding

Datum: 2026-05-18
Zadání: Opravit tři problémy s cookie consent a tracking skriptem: velký inline JS blok ve zdrojáku, duplicitní injektování runtime skriptu a garbled znaky v tracking.js.

## Kroky

- [ ] Opravit check duplikátního runtime v injectCookieConsent() — cmsCookieConsentSettings → cookie-consent-runtime.js
- [ ] Odstranit inline `script[data-cookie-consent-script]` blok z index.php (330+ řádků)
- [ ] Opravit box-drawing Unicode znaky v tracking.js na plain ASCII
- [ ] Commitnout výsledek

## Poznámky

- Inline JS blok v $consent heredoc (řádky 287-618 index.php) duplikuje /assets/cookie-consent.js
- injectSharedFrontendAssets() už načítá cookie-consent.js přes data-cookie-consent-js marker
- Inline blok má jiný marker (data-cookie-consent-script) — deduplikace nefunguje → JS běží dvakrát
- str_contains($html, 'cmsCookieConsentSettings') nikdy nesedí — správná kontrola je 'cookie-consent-runtime.js'
- tracking.js má Unicode ── znaky v komentářích, webzdarma je servuje bez charset=UTF-8 → garbled výstup

## Výsledek
