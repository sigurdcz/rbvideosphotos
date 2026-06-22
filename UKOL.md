# UKOL: GA4 event console logy

Datum: 2026-06-22
Zadání: Doplnit do frontend trackingu viditelný console log, který u GA4 eventů jasně ukáže, jestli byl event skutečně odeslán nebo proč se neodeslal.

## Kroky

- [x] Najít skutečné místo, odkud se posílají GA4 eventy
- [x] Upravit tracking runtime tak, aby logoval odeslání i blokaci eventů
- [x] Zkontrolovat dopad do dokumentace a ověřit diff

## Poznámky

- Trackovací runtime je centrálně injektovaný přes `App\Services\SeoRenderer::renderTrackingRuntime()` a běží z `public/assets/tracking.js`.
- Požadované eventy jako `phone_click`, `email_click`, `cta_click`, `lead_form_submit` a `scroll_90` už existují; chybí viditelný konzolový audit mimo debug-only log.
- V repu byl před začátkem rozpracovaný `UKOL.md` z minulého úkolu; přepsán na aktuální zadání podle workflow skillu.
- `public/assets/tracking.js` nově loguje i blokované eventy a chybějící `gtag`, takže při testu není ticho, když se event neposlal.
- `README.md` a `ARCHITECTURE.md` doplněny o popis konzolového auditu pro GA4 tracking.
- Pokus o syntax check přes `node --check public/assets/tracking.js` nešel ověřit, protože `node` v tomto prostředí není nainstalovaný.

## Výsledek

- `public/assets/tracking.js` teď pro GA4 eventy vypisuje do konzole stav `Event odeslan`, `Event neodeslan` nebo `Event selhal` včetně názvu eventu, parametrů a důvodu blokace.
- Audit pokrývá i případy bez consentu, s vypnutým eventem nebo bez dostupného `window.gtag`, takže je při testování vidět, proč se nic neposlalo.
- Dokumentace v `README.md` a `ARCHITECTURE.md` odpovídá novému chování runtime.
