# UKOL: Opravit mobilni centrovani hero obsahu

Datum: 2026-07-01
Zadani: Mobilni texty a tlacitka v homepage hero `jemna-bezova` musi byt skutecne na stred i po vygenerovani page CSS.

## Kroky

- [x] Overit stav git pracovni kopie a aktualni CSS zdroje.
- [x] Doplnit finalni CSS override do zdroje, ktery se sklada az za ulozene layout CSS.
- [x] Overit PHP/CSS bez zasahu do lokalni DB.
- [x] Zhodnotit dokumentaci a zapsat vysledek.

## Poznamky

- Pracovni kopie byla pred opravou cista.
- Stavajici `Templates/jemna-bezova/assets/theme.css` uz obsahuje mobilni centrovani, ale screenshot ukazuje, ze runtime `page-css` stale pouziva starsi sirku/zarovnani.
- Oprava musi zustat ve zdrojich sablony/asset pipeline; lokalni DB se nepouziva.
- `app/Services/ThemeTemplateRegistry.php` ted pro `jemna-bezova` homepage pripojuje runtime-safe hero override az za ulozene layout CSS.
- `Templates/jemna-bezova/assets/theme.css` ma stejnou mobilni sirku kontejneru zpresnenou na centrovany limit 360 px.
- Overeno pres `php -l app/Services/ThemeTemplateRegistry.php` a `git diff --check`; DB se nepouzivala.
- Dokumentaci neni potreba menit: jde o cileny runtime/CSS bugfix existujici sablony.

## Vysledek

Mobilni homepage hero v `jemna-bezova` dostava finalni centrovaci CSS az pri skladani CSS pro stranku, takze texty a CTA zustanou na stredu i kdyz je v ulozenem layout CSS starsi pravidlo.
