# UKOL: Mobilni oprava kontaktni stranky

Datum: 2026-07-02
Zadani: Opravit mobilni kontakt: odstranit pravou mezeru, vycentrovat tlacitko, zmensit a odsadit consent text a skryt sekci `kt-process` na mobilu.

## Kroky

- [x] Zkontrolovat stav pracovniku a rozsah mobilni opravy.
- [x] Upravit mobilni CSS kontaktni stranky.
- [x] Overit syntaxi, diff a lokalni dopady.
- [x] Commitnout a pushnout opravu.

## Poznamky

- Pracovni strom byl pred zacatkem cisty.
- Oprava se tyka jen mobilniho breakpointu kontaktni stranky v `public/index.php`.
- Mobilni override skryva `.kt-process`, omezuje sirky kontejneru, zmensuje consent text a centruje submit tlacitko.
- Lokalni `/kontakt` vratil HTTP 200 a generovany CSS obsahoval nove mobilni pravidlo.

## Vysledek

Mobilni kontakt ma omezeny horizontalni overflow, submit tlacitko je centrovane, consent text je mensi/lehci s vetsim odstupem a sekce `.kt-process` je na mobilu skryta. Overeno `php -l public/index.php`, `git diff --check` a lokalnim HTTP 200 pro `/kontakt`.
