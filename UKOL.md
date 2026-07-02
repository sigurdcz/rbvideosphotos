# UKOL: Posunout druhe cenikove texty niz

Datum: 2026-07-02
Zadani: Posunout sekci s textem Rodinne, portretni a firemni foceni niz pod balicky na /cenik, aby ji neprekryval obsah nad ni.

## Kroky

- [x] Zapsat aktualni zadani do UKOL.md
- [x] Najit misto, kde se vykresluje druhy cenikovy nadpis a popis
- [x] Posunout spacing za balicky tak, aby text nebyl prekryty
- [x] Overit render /cenik po uprave

## Poznamky

- Oprava musi byt v runtime i v demo seederu, aby se po reseedu vracelo stejné rozlozeni.
- Jde jen o vertikalni mezery, ne o zmenu obsahu.

## Vysledek

Sekce `Rodinne, portretni a firemni foceni` ma nyni vetsi spodní rezervu po balickach, takze uz neskace pod vizualni prekryv predchoziho gridu. Uprava je v runtime CSS i v demo seederu, aby se po reseedu nevracela stara mezera.
