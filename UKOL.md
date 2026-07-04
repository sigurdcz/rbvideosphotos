# UKOL: Spravne vrstveni hero overlaye

Datum: 2026-07-04
Zadani: Opravit homepage hero stacking order tak, aby video/foto bylo dole, `::after` overlay nad nim a text s CTA tlacitky nad overlayem.

## Kroky

- [x] 1. Upravit CSS vrstveni `video/foto -> overlay -> text/CTA`
- [x] 2. Overit diff a commit/push

## Poznamky

- Success signal: `::after` muze zustat jako overlay pres video/foto, ale nesmi sedet nad textaci, podnadpisem ani CTA tlacitky.
- Upraveno v `Templates/elegant-white/assets/theme.css`: hero media vrstva ma `z-index: 0`, pseudo-elementy a media overlay `z-index: 1`, container/text/CTA `z-index: 2`.

## Vysledek

Hero vrstvy jsou explicitne nastavene jako `video/foto -> overlay -> text/CTA`, aby `::after` zustal pod textem a tlacitky.
