# UKOL: Weare media pro homepage

Datum: 2026-07-02
Zadani: Presunout fotku weare na spravu pres media, pouzit zmenseny soubor z media storage a pridat moznost menit tento obrazek na homepage pres media picker.

## Kroky

- [x] Zapsat aktualni zadani do UKOL.md
- [x] Najit, kde se homepage weare obrazek nacita a jak se dnes vybere
- [x] Napojit homepage na media vyber misto hardcoded souboru
- [x] Zajistit, aby se weare soubor po uploadu automaticky zmensoval a pouzival jeho zmensena verze
- [ ] Overit upload, render homepage a velikost vystupu

## Poznamky

- Cilem je stejne chovani jako u ostatnich medii: upload do media knihovny, zpracovani variant, a pak vyber z adminu.
- Pokud je weare dnes jen demo asset, treba jej prevest na normalni mediovy zaznam a nenechat staticky PNG v layoutu.
- Homepage ted edituje sdilenou sadu `service_items` primo z homepage adminu a render bere preferovane media varianty (`medium`, `webp`, `thumb`) misto puvodniho originalu.

## Vysledek

Hotovo v kodu, zbyva jen pripadne manualni browser overeni.
