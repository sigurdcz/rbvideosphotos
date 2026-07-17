# QA testing: viewporty a postup

Tento postup overuje skutecny lokalni runtime SEO Editoru. Zdroj volby viewportu je `analytics.md`; nejde o obecne designove breakpointy.

## Pevne viewporty

| Scenar | Viewport | Pouziti |
| --- | --- | --- |
| Telefon (iphone 12 Pro) | **390 x 844 CSS px** | Povinna mobilni kontrola kazde zmeny verejneho renderingu nebo aktivni sablony. |
| PC | **1920 x 1080 CSS px** | Povinna desktopova kontrola kazde zmeny verejneho renderingu nebo aktivni sablony. |

Nemenit tyto rozmery mezi iteracemi, jinak nelze porovnavat screenshoty ani overflow. Browser zoom musi byt 100 %.

## Predpoklady

1. Overit aktivni tema v databazi a ve zdrojovem HTML tridu `theme-<key>`.
2. Spustit lokalni server standardnim prikazem projektu:

   ```powershell
   php -S 127.0.0.1:8766 router.php
   ```

3. Otevrit `http://127.0.0.1:8766/` bez `theme_preview`, pokud se testuje skutecne aktivni tema.
4. Pro izolovanou kontrolu neaktivni sablony pouzit `?theme_preview=<key>` a do zaznamu napsat tento parametr.
5. Zaznamenat datum, route, aktivni theme key, viewport, zoom, stav dat/seedu a zda byl zobrazen cookie dialog.

## Povinne routy

Pri zmene homepage poradi, theme CSS nebo theme JavaScriptu overit:

- `/`
- `/sluzby`
- `/nase-prace` a jeden existujici detail portfolia
- `/blog` a jeden existujici clanek
- `/cenik`
- `/faq`
- `/kontakt`

## Postup pro kazdy viewport

1. Nastavit presne dany viewport a obnovit stranku bez cache.
2. Zkontrolovat, ze odpoved obsahuje spravnou tridu tematu a ze se nacetly aktualni generovane CSS a JS assety.
3. Zachytit novy screenshot cele stranky; nepouzivat stary obraz ani snimek plochy jako dukaz runtime stavu.
4. Overit horizontalni overflow:

   ```js
   document.documentElement.scrollWidth === document.documentElement.clientWidth
   ```

   Ocekavany vysledek je `true`.

5. Overit viditelnost a poradi sekci, nadpisy, obrazky, kontrast, cteni textu a CTA.
6. Otestovat interakce: otevreni/zavreni mobilniho menu, FAQ, formular, odkazy na interní routy a klavesu Escape pro zavreni menu/dialogu.
7. Zkontrolovat konzoli a sit: zadne chyby JavaScriptu, selhane CSS/JS assety ani neocekavane 404.

## Homepage: Elegant White 3

Pro aktualni fix-up kontrolovat na obou viewportech toto poradi:

```text
Hero
-> Co nabizime / Sluzby (O nas)
-> Portfolio
-> Recenze
-> Cenik
-> Jak to probiha
-> Blog
-> Kontakt
-> FAQ
-> Paticka
```

`Co nabizime / Sluzby` je v aktualnich datech DB-renderovany blok `#sluzby`; nevytvaret druhou hardcoded sekci `O nas` pouze kvuli poradi.

## Zaznam iterace

Pro kazdou vizualni zmenu zapis do `UKOL.md`:

```text
Iterace NN
- route: /
- tema: elegant-white3
- viewport: 390 x 844 nebo 1920 x 1080
- screenshot: nazev cerstveho souboru
- overflow: true/false (scrollWidth/clientWidth)
- menu, FAQ, formular: pass/fail
- konzole a sit: pass/fail + konkretni chyba
- rozdily a dalsi krok: konkretne
```

## Prijeti zmeny

Zmena je pripravena k odevzdani pouze kdyz na obou pevných viewportech plati:

- screenshot je cerstvy a odpovida skutecnemu runtime;
- stranka nema horizontalni overflow;
- menu, FAQ a formular funguji;
- aktualni CSS/JS assety se nacetly bez chyb;
- povinne routy pro rozsah zmeny prochazeji;
- `php tests/run.php` a `git diff --check` prochazeji.

Tablet neni soucasti zakladni dvojice, protoze podle predane analytiky tvori jen 0,8 %. Otestovat jej navic pri chybe specificke pro tablet nebo pri zasahu do breakpointu mezi mobilem a desktopem.
