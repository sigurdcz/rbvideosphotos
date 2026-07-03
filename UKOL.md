# UKOL: About visual source a admin nahled

Datum: 2026-07-03
Zadani: Odstranit zbytecne vykreslenou source fotku z `theme-main` a zmensit admin nahled obrazku pro homepage sekci Nase sluzby.

## Kroky

- [x] Zapsat aktualni zadani do UKOL.md
- [x] Upravit hidden source tak, aby nebyl viditelny obrazek v hlavnim obsahu
- [x] Zmensit admin nahled pro homepage about visual picker
- [x] Overit syntax a hledane HTML/CSS/JS vystupy
- [-] Commit a push

## Poznamky

- Pricina viditelne fotky: source byl vlozen jako `<img class="jb-about-visual__source">`; theme CSS pro obrazky ho dokazalo zobrazit v `theme-main`.
- Cilem je ponechat hodnotu pro theme JS, ale bez realne vykresleneho obrazku mimo zamysleny panel.

## Vysledek

Oprava je pripravena: source hodnota pro `jb-about-visual__media` se vklada jako skryty `span` s `data-src`, ne jako realny obrazek v `theme-main`. Theme JS cte `data-src` a admin nahled ma omezene rozmery. Overeno pres `php -l` pro upravene PHP soubory a `git diff --check`.
