# UKOL: Nahrat header obrazky z `data dalsi`

Datum: 2026-06-30
Zadani: Nahrat nove obrazky pres existujici upload flow, priradit je jako header fotky pro stranky a pouzit `weare2.png` v homepage sekci `Nase sluzby`.

## Kroky

- [x] Najit slozku s obrazky a mista v kodu, ktera ridi header vizualy a homepage sluzbu.
- [x] Nahrat vsechny obrazky z `data dalsi` pres media ingest flow a doplnit k nim varianty.
- [x] Priradit obrazky k odpovidajicim strankam a homepage sluzbe `weare2.png`.
- [x] Overit, ze se pouzivaji spravne public cesty a ze upload vytvoril varianty.
- [ ] Rozhodnout o dokumentaci a dopsat jen pokud se meni dlouhodobe chovani nebo workflow.

## Poznamky

- Soubor `weare2.png` ma jit na homepage sekci `Nase sluzby`.
- Ostatni soubory z `data dalsi` vypadaji jako header obrazky pro stranky `blog`, `cenik`, `faq`, `kontakt`, `nase-prace`, `podminky-uziti` a `sluzby`.
- Zmena ma probihat pres stejnou ingest/variant vrstvu jako admin upload, ne pres rucni kopirovani souboru.

## Vysledek

Hotovo.
