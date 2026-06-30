# UKOL: Prepsat texty stranek, blogu a nasi prace

Datum: 2026-06-30
Zadani: Pouzit dodane civilnejsi textace do odpovidajicich stranek obsahu, blogovych clanku a polozek `Nase prace` v projektu SEO Editor.

## Kroky

- [x] Namapovat dodane texty na existujici blogy, stranky a portfolio obsah.
- [x] Najit zdroj aktualnich textu v aktivnim obsahu a v demo seederu.
- [x] Upravit seed a demo content tak, aby nove texty byly zdrojem pro blogy a `Nase prace` bez zasahu do DB.
- [x] Overit syntax a rychle zkontrolovat, ze odpovidajici seed/demo zaznamy nesou nove texty.
- [x] Rozhodnout o dokumentaci a dopsat jen pokud se meni dlouhodobe chovani nebo workflow.

## Poznamky

- Jde primarne o obsahovou zmenu; priorita je zapsat texty do existujiciho content workflow, ne menit architekturu.
- Prvnich pet dodanych textu odpovida blogovym clankum podle existujicich nazvu v demo obsahu.
- Druhych pet textu odpovida polozkam `Nase prace` a jejich detailum.
- Uzivatel explicitne rekl nepracovat s DB; zmena musi zustat jen v `seed` a `demo content` souborech.
- Zdroj konkretnich blog/portfolio textu v tomto workflow je `app/Services/DemoContentSeeder.php`; `database/seeds/demo_content.php` je jen wrapper, ktery ten samy seeder spousti.
- Overeni probehlo pres `php -l app/Services/DemoContentSeeder.php` a `rg`, ze vsech 10 novych uvodnich vet je v seederu pritomnych.
- Dokumentaci jsem nemenil; nemeni se workflow editoru ani architektura, jen obsah demo naplne.

## Vysledek

Hotovo:
- Blogove demo clanky uz maji nove civilnejsi perexy i plny obsah se sekcemi `Kratky uvod`, `Text` a `Zaver`.
- Portfolio / `Nase prace` ma nove nazvy, meta popisy a detailni texty misto generickych placeholder odstavcu.
- Zmena zustala jen v demo content zdroji bez zasahu do DB; pro propsani do webu je potreba znovu spustit nacteni demo obsahu.
