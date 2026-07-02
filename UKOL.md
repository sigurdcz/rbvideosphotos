# UKOL: Oprava kontaktniho formulare a sekci

Datum: 2026-07-02
Zadani: Opravit kontaktni stranku: neprekrivajici se inputy, citelne texty, stylovany checkbox, proces jako na HP, FAQ z prvnich 4 FAQ polozek a spravne rozlozeni panelu.

## Kroky

- [x] Najit zdroj HP procesu, FAQ dat a kontaktni runtime sablony.
- [x] Upravit kontaktni formular, CSS sloupce, checkbox a panelove vrstvy.
- [x] Nahradit rucne psane FAQ na kontaktu prvnimi 4 aktivnimi FAQ polozkami.
- [x] Overit syntaxi, diff a dopad na dokumentaci.
- [x] Commitnout a pushnout opravu.

## Poznamky

- Pracovni strom byl pred zacatkem cisty.
- Kontaktni FAQ nema zustat hardcoded; zdroj ma byt `FaqRepository::allActive()`.
- Proces ma vizualne odpovidat svetle HP sekci ze screenshotu.
- Formular ma izolovany grid, citelny intro text a stylovany checkbox.
- Kontaktni FAQ se generuje z prvnich 4 aktivnich polozek `faq_items`.
- Lokalni `/kontakt` vraci HTTP 200 a render obsahuje nove bloky `kt-form-grid`, `kt-consent`, `kt-faq-item` a procesovy nadpis.
- Relevantni dokumentace nevyzaduje zmenu; jde o runtime CSS/HTML kontaktni stranky.

## Vysledek

Kontaktni formular ma izolovane sloupce bez prekryvu, citelny intro text a vlastni checkbox. Sekce procesu je svetla ve stylu HP se 4 kroky a kontaktni FAQ se bere z prvnich 4 aktivnich FAQ polozek. Overeno `php -l public/index.php`, `git diff --check` a lokalnim HTTP 200 pro `/kontakt`.
