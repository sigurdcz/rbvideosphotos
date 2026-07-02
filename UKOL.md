# UKOL: Vymena kontaktniho e-mailu

Datum: 2026-07-02
Zadani: V celem repozitari odstranit puvodni Gmail kontakt a nahradit ho kontaktnim e-mailem `rb_vp@volny.cz`.

## Kroky

- [x] Najit vsechny vyskyty stareho e-mailu.
- [x] Nahradit stare hodnoty za `rb_vp@volny.cz`.
- [x] Overit, ze stary e-mail nikde nezustal.
- [x] Overit syntaxi / relevantni dopady a zapsat vysledek.
- [x] Commitnout a pushnout zmenu.

## Poznamky

- Pracovni strom byl pred zacatkem cisty.
- Predchozi `UKOL.md` obsahoval zastaraly rozpracovany checkbox, ale zmeny byly uz commitnute a pushnute.
- Stara adresa byla nalezena pouze v demo seed datech kontaktu a v pracovnim `UKOL.md`.
- Relevantni dokumentace nevyzaduje zmenu; jde o seed kontaktni hodnoty.

## Vysledek

Kontaktni seed pouziva `rb_vp@volny.cz` pro verejny e-mail i cil formularu. Overeno, ze puvodni Gmail adresa mimo `.git` nezustala, a `php -l database/seeds/seed.php` prosel.
