# UKOL: Centrovat mobilni hero obsah

Datum: 2026-07-01
Zadani: Pri mobilnim rozliseni vratit textace a tlacitka v `jemna-bezova` homepage hero na stred.

## Kroky

- [x] Najit mobilni CSS pravidla pro homepage hero obsah.
- [x] Pridat finalni mobilni centrovani hero textu a CTA.
- [x] Overit diff bez sahani na lokalni DB.
- [x] Zhodnotit dokumentaci a zapsat vysledek.

## Poznamky

- Po poslednim hero media fixu je video spravne pres celou hero sekci, ale mobilni obsah je vizualne zarovnany doleva.
- Potreba je centrovat jen mobilni `.hp-hero` obsah v aktivni `jemna-bezova` sablone.
- `Templates/jemna-bezova/assets/theme.css` ma finalni `@media (max-width: 760px)` override, ktery centruje container, nadpis, podnadpis i CTA.
- Overeno pres `git diff --check`. DB se nepouzivala.
- Dokumentaci neni potreba menit: jde o vizualni CSS bugfix v existujici sablone.

## Vysledek

Mobilni homepage hero v `jemna-bezova` znovu centruje label, nadpis, podnadpis i CTA tlacitka v ramci hero containeru.
