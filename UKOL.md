# UKOL: Kvalita komprese medii

Datum: 2026-07-02
Zadani: Pridat do administrace nastaveni kvality komprese mediovych fotek a zvysit vychozi kvalitu oproti soucasnemu stavu.

## Kroky

- [x] Zapsat aktualni zadani do UKOL.md
- [x] Najit vsechny upload cesty a misto, kde se kvalita nastavuje natvrdo
- [x] Pridat do administrace nastaveni kvality komprese medii
- [x] Napojit upload a generovani variant na nastavitelne kvality
- [x] Zvysit vychozi kvalitu komprese
- [x] Overit syntax a chovani zmen

## Poznamky

- Kvalita se dnes ridi natvrdo v `ImageVariantService` a upload pipeline generuje varianty pri uploadu.
- Nastaveni ma byt per web, stejne jako ostatni site konfigurace v tabulce `sites`.
- Pridan `sites.media_image_quality` s defaultem 90, napojene settings UI a synchronizace kvality pro `MediaController` i `MediaIngestionService`.

## Vysledek

Hotovo. Kvalita komprese medii je nastavitelna v administraci a vychozi kvalita byla zvednuta na 90.
