# UKOL: Opravit opacity hero textu

Datum: 2026-07-01
Zadani: Odstranit opacity z hero video wrapperu v `jemna-bezova`, aby text a tlacitka v hero zustaly na opacity 1.

## Kroky

- [x] Najit CSS pravidlo, ktere nastavuje opacity na hero video wrapper.
- [x] Presunout ztmaveni na video/overlay bez opacity na rodicovskem wrapperu.
- [x] Overit diff bez sahani na lokalni DB.
- [x] Zhodnotit dokumentaci a zapsat vysledek.

## Poznamky

- Problemove pravidlo je `.theme-jemna-bezova .theme-hero-video { opacity: .86; }`.
- Text a tlacitka v hero musi zustat plne nepruhledne; pruhlednost se nesmi aplikovat na rodicovsky wrapper v hero kompozici.
- `Templates/jemna-bezova/assets/theme.css` ted ma na `.theme-hero-video` opacity 1 a ztmaveni resi pres `filter`; texty a tlacitka v `.hp-hero` maji explicitne opacity 1.
- Overeno pres `rg` kontrolu opacity pravidel a `git diff --check`. DB se nepouzivala.
- Dokumentaci neni potreba menit: jde o vizualni CSS bugfix v existujici sablone.

## Vysledek

Hero video wrapper v `jemna-bezova` uz nema snizenou opacity. Ztmaveni zustava na videu pres filter/overlay a hero texty i tlacitka jsou explicitne na opacity 1.
