# UKOL: Elegant White final homepage tuning

Datum: 2026-07-04
Zadani: Doladit sablonu `Elegant White` podle noveho ciloveho screenshotu, protoze aktualni stav je stale prilis uzky, vybledly a ma nepresne texty/sekce.

## Cil

Homepage Elegant White ma byt vyrazne bliz cilovemu screenshotu: vetsi kontrast a meritko, sirsi hero s citelnym textem, vetsi about/portfolio/sluzby bloky, recenze a FAQ ve spravnem stylu, 5 kroku procesu a kontakt s fotkou kamery.

## Kroky

- [x] 1. Zkontrolovat aktualni JS/CSS pro Elegant White
  Popis: Najit mista, ktera zpusobuji male meritko, vybledly hero, useknute FAQ a chybejici 5. procesni krok.
  Hotovo kdyz: Je jasne, ktere selektory a DOM transformace upravit.

- [x] 2. Upravit JS obsah a zdroje sekci
  Popis: Doladit textace hero/about/sluzby/FAQ/proces a vybrat kontaktni vizual tak, aby primarne pouzil kamerovou fotku misto svatebni.
  Hotovo kdyz: DOM transformace odpovida cilovemu poradi a obsahu.

- [x] 3. Upravit CSS meritko, kontrast a layout
  Popis: Zvetsit hlavni bloky, omezit vybledlost hero, zlepsit kontrast, portfolio a sluzby roztahnout jako v cili a formular/karty zaoblit.
  Hotovo kdyz: Vzhled je citelne blize referenci a nepusobi zmensene.

- [x] 4. Otestovat diff a syntaxi dostupnymi nastroji
  Popis: Spustit dostupne kontroly a zkontrolovat pracovni strom.
  Hotovo kdyz: `git diff --check` projde a jsou vypsana pripadna omezeni.

## Poznamky

- Aktualni cilovy screenshot ma silnejsi text, vetsi obsahovy kontejner a mene vybledly hero nez soucasny stav.
- `theme.js` upravuje obsah Elegant White homepage: silnejsi hero text, nove CTA, kontaktni vizual pevne na `/media/2026/07/kontakt.png`, 4 prehledove sluzby vcetne fallbacku, FAQ zavrene ve vychozim stavu a proces na 5 kroku.
- `theme.css` dostal finalni override pro homepage: sirsi kontejnery, vetsi hero/about/portfolio/sluzby, vyssi kontrast textu, vetsi ikony, sirsi FAQ/proces a vetsi kontaktni panel.
- Dokumentace mimo `UKOL.md` neni menena, protoze jde o konkretni sablonovy styling bez zmeny verejne API nebo obsluhy administrace.
- Overeni: `git diff --check` proslo. `node --check Templates\elegant-white\assets\theme.js` neslo spustit, protoze `node` neni v aktualnim shellu dostupny.

## Vysledek

Elegant White homepage je doladena dalsim pass-em: texty a CTA jsou bliz cilovemu screenshotu, kontakt pouziva explicitni kamerovy obrazek, sluzby maji stabilni 4 polozky, FAQ je zavrene ve vychozim stavu, proces ma 5 kroku a CSS zvetsuje meritko/kontrast hlavni homepage kompozice.
