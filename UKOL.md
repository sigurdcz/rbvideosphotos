# UKOL: Zmensit obrazky a nahledy

Datum: 2026-07-02
Zadani: Zmensit velke PNG obrazky v demo assets i storage a zestrucnit generovani thumbnailu, aby blog, nase prace a podobne nahledy mely nizsi velikost souboru.

## Kroky

- [x] Zapsat aktualni zadani do UKOL.md
- [x] Najit a prepsat velke PNG soubory v demo assets a storage
- [x] Zmensit kvalitu a rozmery generovanych nahledu pro nove uploady
- [x] Overit velikosti a render po uprave

## Poznamky

- `weare2.png` a dalsi demo PNG byly zmensene pres resize a paletizaci bez zmeny cest.
- WebP a JPG varianty v media storage byly znovu prekreslene na nizsi kvalitu, aby se snizila velikost souboru.
- U variant pro novy upload jsou thumb/medium mensi a s nizsi kvalitou vystupu.

## Vysledek

Velke PNG i WebP media byla zmensena na disku a novy upload pipeline ma nizsi thumb/medium kvalitu i rozmery. Klicove soubory jako `weare2.png` a velke nahledy na blogu / nasi praci jsou citelne mensi.
