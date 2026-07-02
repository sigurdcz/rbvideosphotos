# UKOL: Dolaďení kontaktniho panelu podle reference

Datum: 2026-07-02
Zadani: Kontaktni panel musi byt blize referenci: kompaktni leve karty, zadne inline socialni site v panelu, male nadpisy, stabilni dva sloupce a formular jako karta vedle kontaktu.

## Kroky

- [x] Zkontrolovat aktualni kontaktni sablonu a CSS.
- [x] Odstranit prvky, ktere rozbiji panelove rozlozeni, a doplnit finalni CSS prepis.
- [x] Overit syntaxi, diff a lokalni render.
- [x] Commitnout a pushnout opravu.

## Poznamky

- Pracovni strom byl pred zacatkem cisty.
- Hlavni problem: sablonove/starsi CSS stale zvetsuje `.ci-title` a desktop media query meni `.kt-grid`; inline socialni site prodluzuji levy sloupec oproti referenci.
- Inline socialni odkazy jsou odstraneny z kontaktniho panelu; socialni site zustavaji ve footeru pres `injectFooterSocials`.
- Na konec kontaktniho CSS pribyl finalni prepis pro rozmery, fonty, grid a formular.
- Lokalni `/kontakt` vratil HTTP 200 a generovany CSS obsahoval finalni kontaktni prepis.

## Vysledek

Kontaktni panel ma finalni prepis pro kompaktni rozlozeni podle reference: leve karty bez inline socialnich odkazu, male nadpisy, formular jako samostatna karta a pevny dvousloupcovy grid. Overeno `php -l public/index.php`, `git diff --check` a lokalni HTTP 200 pro `/kontakt`.
