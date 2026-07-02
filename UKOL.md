# UKOL: Dolaďení kontaktni stranky podle reference

Datum: 2026-07-02
Zadani: Kontaktni stranka ma byt jeste blizsi dodanemu referencnimu screenshotu: kompaktnejsi tmavy hero, uzsi obsah, kontaktni karty, formular, proces a FAQ v jednom vizualnim rytmu.

## Kroky

- [x] Zkontrolovat aktualni kontaktni runtime sablonu.
- [x] Doladit proporce, mezery, velikosti a CTA podle reference.
- [x] Overit PHP syntaxi a diff.
- [-] Commitnout a pushnout doladeni.

## Poznamky

- Pred zacatkem doladeni byl strom po commitu cisty.
- Kontaktni stranka je specialni runtime handler v `public/index.php`.
- Cil doladeni: mene roztazeny layout, mensi hero vyska, karty a formular bliz referenci, tmave napojene sekce bez svetlych mezer.
- CSS kontaktu bylo zkompaktneno: nizsi hero, uzsi kontejnery, mensi karty/formular, proces a FAQ zarovnane na stejny rytmus.

## Vysledek

Kontaktni stranka byla zkompaktnena bliz referenci: nizsi hero, uzsi obsah, mensi karty, formular, proces a FAQ. Overeno `php -l public/index.php` a `git diff --check`.
