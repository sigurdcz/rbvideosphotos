# UKOL: GA4 tag – measurementIdOverride must not be empty

Datum: 2026-06-28
Zadání: GA4 event tag (gaawe) musí mít parametr measurementIdOverride s neprázdným GA4 ID.
Hotovo když: Apply vytvoří GA4 event tagy bez chyby; chybějící GA4 ID dá jasnou blokující hlášku; PHP lint čistý.

## Kroky

- [x] V GtmManifestCompiler přejmenovat parametr measurementId -> measurementIdOverride
- [x] Přidat guard: prázdné GA4 ID u enabled eventu -> RuntimeException s jasnou CZ hláškou
- [x] PHP lint
- [x] Commit

## Poznámky

- Chyba: vendorTemplate.parameter.measurementIdOverride: The value must not be empty.
- gaawe = GA4 Event tag; aktuální GTM schéma chce measurementIdOverride (G-XXXX), ne legacy measurementId.
- measurementId bere z sites.google_analytics_id. Pokud prázdné, i po přejmenování klíče bude hodnota
  prázdná -> guard to zachytí dřív s lidskou hláškou (no silent fallback dle zadání UKOL GTM).
- Změna parametru změní config_hash -> re-plan ukáže update/create správně.
- Bez zásahu do GA4 runtime / frontend eventů.

## Výsledek

GA4 event tag v GtmManifestCompiler nyní posílá parametr measurementIdOverride (místo legacy
measurementId), jak vyžaduje aktuální gaawe schéma GTM. Přidán guard: pokud chybí GA4
Measurement ID (sites.google_analytics_id), compile vyhodí jasnou CZ hlášku a apply/plan se
zastaví dřív, než narazí na kryptickou GTM chybu. Změna parametru mění config_hash, takže
re-plan ukáže korektní stav.
