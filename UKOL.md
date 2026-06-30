# UKOL: Opravit viditelnost homepage hero

Datum: 2026-07-01
Zadani: Opravit `jemna-bezova` homepage hero tak, aby video/obraz vyplnil celou sirku a vysku hero a texty s tlacitky byly vzdy viditelne nad videem.

## Kroky

- [x] Najit CSS pravidla, kvuli kterym je video v ramecku a prekryva obsah.
- [x] Upravit positioning videa a z-index hero obsahu v aktualni sablone.
- [x] Overit diff bez sahani na lokalni DB.
- [x] Zhodnotit dokumentaci a zapsat vysledek.

## Poznamky

- `.theme-hero-video` je v DOM uvnitr `.hp-hero > .container`; container ma `position: relative` a `max-width`, proto video vyplnuje jen ramecek.
- Texty/tlacitka jsou sourozenci absolutne pozicovaneho videa a musi dostat vlastni `position: relative` + vyssi `z-index`.
- `Templates/jemna-bezova/assets/theme.css` ma finalni override na konci souboru: container neni positioning context, video/iframe cover jde pres celou `.hp-hero` a text/CTA maji z-index nad overlayem.
- Overeno pres `git diff --check`. DB se nepouzivala.
- Dokumentaci neni potreba menit: jde o vizualni CSS bugfix v existujici sablone.

## Vysledek

Homepage hero v `jemna-bezova` uz nenechava video/obraz v ramecku containeru; media cover jde pres celou `.hp-hero` a texty s tlacitky jsou explicitne ve vrstve nad videem i overlayem.
