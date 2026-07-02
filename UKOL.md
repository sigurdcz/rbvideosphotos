# UKOL: Redesign kontaktni stranky

Datum: 2026-07-02
Zadani: Kontaktni stranka ma vizualne odpovidat dodanemu tmavemu fotografickemu navrhu s hero sekci, kontaktnimi kartami, formularem, procesem, FAQ a footerem se socialnimi sitemi.

## Kroky

- [x] Najit zdroj kontaktni sablony, demo obsahu a frontend renderovani formulare/socialnich siti.
- [x] Upravit kontaktni HTML/CSS/seed tak, aby odpovidal dodanemu designu.
- [x] Zachovat funkcni kontaktni data, formular a socialni site.
- [x] Overit PHP syntaxi a dopady na dokumentaci.
- [x] Commitnout a pushnout hotovou upravu.

## Poznamky

- Pracovni kopie byla pred zacatkem cista.
- Cil: `/kontakt` ma mit tmavy premium layout jako screenshot: hero s CTA tlacitky, vlevo kontaktni informace, vpravo poptavkovy formular, dole proces, FAQ a footer.
- Kontaktni stranka je specialni runtime handler v `public/index.php`; upraveny je obsah, CSS, formular a odesilane telo zpravy.
- Socialni odkazy zustavaji napojene pres kontaktni data a `injectFooterSocials`; pokud nejsou vyplnene v datech, kod negeneruje prazdne socialni odkazy.
- Relevantni dokumentace byla posouzena (`README.md`, `ARCHITECTURE.md`, `INSTRUCTIONS.md`, `GTM_ADMIN.md`); zmena je vizualni runtime uprava, bez nutne aktualizace techto dokumentu.

## Vysledek

Kontaktni stranka ma novy tmavy premium layout s hero CTA, kontaktnimi kartami, poptavkovym formularem, postupem a FAQ. Overeno `php -l public/index.php` a `git diff --check`.
