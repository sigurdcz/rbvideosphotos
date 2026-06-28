# UKOL: GTM custom event filter {{Event}} -> {{_event}}

Datum: 2026-06-28
Zadání: Opravit arg0 v custom event triggeru z {{Event}} na {{_event}}, jak vyžaduje GTM API.
Hotovo když: Plán se aplikuje bez chyby "custom event filter first argument must be '{{_event}}'"; PHP lint čistý.

## Kroky

- [x] Opravit GtmManifestCompiler — arg0 value '{{Event}}' -> '{{_event}}'
- [x] PHP lint
- [x] Commit

## Poznámky

- GTM customEvent trigger interně používá built-in proměnnou {{_event}}, ne {{Event}}.
- Jediný výskyt: app/Services/GtmManifestCompiler.php:42.
- Pozn.: triggery vytvořené předchozím (chybným) apply mohly částečně vzniknout v workspace — viz dopad níže.

## Výsledek

V GtmManifestCompiler změněn arg0 custom event filtru z '{{Event}}' na '{{_event}}', jak
vyžaduje GTM API. Apply trigger create už nebude odmítnut validací. Předchozí chybný apply
selhal hned na prvním triggeru, takže workspace zůstal prázdný — po nasazení stačí znovu
Načíst inventář -> Sestavit plán -> Aplikovat plán. Změna config_hash způsobí, že plán
správně detekuje novou (opravenou) konfiguraci.
