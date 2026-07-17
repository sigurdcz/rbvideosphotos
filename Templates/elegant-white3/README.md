# Elegant White 3

`Elegant White 3` (`elegant-white3`) je samostatna kopie `Elegant White`, upravena podle UI/UX a SEO auditu. Sablona zustava prezentacni vrstvou: nemeni CORE, databazove schema ani CMS repository a nevklada neoverena klientska tvrzeni.

## Struktura

- `layout.html` zachovava kompatibilni `theme-elegant-white` tridu a pridava izolacni `theme-elegant-white3`.
- `assets/theme.css` a `assets/theme.js` jsou vlastni kopie zdrojove sablony, aby zmeny Elegant White 3 neovlivnily puvodni tema.
- `assets/homepage-v3.css` a `assets/homepage-v3.js` jsou homepage override assety registrovane v `template.json`.

## Homepage override

Override pracuje pouze s existujicim DB-driven DOM. Z existujicich textu a odkazu sestavi SEO H1, sekundarni CTA, klientsky proof, CTA ceniku a pristupne obrazove alty. Existujici sekce presklada do konverzniho poradi a na mobilu zapne plnosirkovy cenik, vertikalni proces, swipe radky blogu a recenzi a sticky kontaktni CTA.

Javascript je guardovany tridou `theme-elegant-white3`, omezeny na `data-cms-page-key="homepage"` a idempotentni. Chybejici obsah se nenahrazuje fiktivnimi statistikami, jmeny, hodnocenimi ani dodacimi terminy.

## Overeni

Referencni QA viewporty jsou desktop `1440 x 1000` a mobil `390 x 844`. Timestampove progress screenshoty se ukladaji do rootu jako `visual-progress-ew3-*.png` a jsou lokalne ignorovane Gitem. Aktualni acceptance podminky a skore jsou v root `UKOL.md`.
