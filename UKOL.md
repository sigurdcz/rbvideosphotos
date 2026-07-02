# UKOL: Mobilni formular a FAQ na kontaktu

Datum: 2026-07-02
Zadani: Opravit mobilni sirku kontaktniho formulare a udelat kontaktni FAQ jako rozbalovaci dotazy s odpovedmi a tlacitkem na vsechny FAQ.

## Kroky

- [x] Zkontrolovat stav pracovniku a cil opravy.
- [x] Upravit mobilni CSS formulare, aby nepretekal pres sirku telefonu.
- [x] Prestavet kontaktni FAQ na accordion s odpovedmi a CTA odkazem na `/faq`.
- [x] Overit syntaxi, diff a lokalni render.
- [x] Commitnout a pushnout opravu.

## Poznamky

- Pracovni strom byl pred zacatkem cisty.
- Oprava se tyka specialni kontaktni runtime sablony v `public/index.php`.
- Mobilni formular ma `box-sizing` a `max-width` prepis i pro vnitrni prvky formulare.
- Kontaktni FAQ generuje otazku, odpoved, accordion toggle a CTA `Vsechny FAQ`.
- Lokalni `/kontakt` vratil HTTP 200; HTML obsahuje FAQ toggle, odpovedi a `/faq` odkaz, generovany CSS obsahuje mobilni sirku formulare.

## Vysledek

Mobilni formular ma prisnejsi sirku a `box-sizing` i pro vnitrni prvky, aby se nezvetsoval mimo viewport. Kontaktni FAQ je accordion s odpovedmi a tlacitkem `Vsechny FAQ` na `/faq`. Overeno `php -l public/index.php`, `git diff --check` a lokalnim HTTP 200 pro `/kontakt`.
