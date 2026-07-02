# UKOL: Dorovnat vzhled stranky Cenik

Datum: 2026-07-02
Zadani: Opravit vzhled samostatne stranky /cenik, kde jsou texty a balicky rozbite po demo seederu a rezrvacni CTA panel je nevycentrovany a nevzhledny.

## Kroky

- [x] Zapsat aktualni zadani do UKOL.md
- [x] Zjistit, ktera sablona a ktere bloky realne rozbijeji /cenik
- [x] Sjednotit demo cenik s fotografickym frontend vzorem
- [x] Overit lokalni render /cenik a mobilni rozlozeni
- [-] Commitnout a pushnout souvisejici zmeny

## Poznamky

- Problem neni jen hero; demo seeder ma jinou strukturu ceníku nez `seed_photographer.php`.
- Demo sablona stale renderuje `pricing_body`, zatimco fotograficky ceník pouziva `pricing_packages` a vlastni `ckp-*` karty.
- CTA cast v demo sablone nema centrovaci wrapper ani sladene CTA styly, proto vypada vlevo a rozpada se.
- Pridano `data-cms-page-key="cenik"` do renderu i publishe, aby slo zacit opravovat stavajici legacy markup bez vedlejsich zasahu do ostatnich stranek.
- Frontend runtime ted obsahuje page-specific rescue styly pro `/cenik`: hero, legacy karty i tmavy CTA panel.
- DemoContentSeeder ted zapisuje `pricing_packages` i `pricing_body` se stejnym obsahem kvuli kompatibilite se starym i novym template markupem.

## Vysledek

Stranka `/cenik` ma opravene page-specific styly pro cteni textu a centrovany CTA panel i pro starsi obsah v DB. Demo ceník byl sjednocen s fotografickym frontend vzorem, takze dalsi nacteni demo obsahu uz znovu nevytvori rozbity levy CTA blok ani bledy cenik.
