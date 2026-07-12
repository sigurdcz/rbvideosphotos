# UKOL: Oprava neaplikovane kompozice bezova-lens-tale

Datum: 2026-07-12
Zadani: Podle noveho aktualniho screenshotu odstranit pricinu, proc se cilova DOM kompozice a finalni CSS neprojevuji, a dorovnat vysledek k druhemu ocekavanemu screenshotu.

## Kroky

- [x] Zjistit, zda theme JavaScript bezi, kolikrat se nacita a kde se zastavi
- [x] Opravit inicializacni/cascade chybu bez dalsiho slepeho vrstveni CSS
- [x] Overit cilove poradi sekci a nove DOM komponenty v runtime
- [x] Dorovnat geometrii hero, O nas, portfolio, proces, cenik, reference, blog a kontakt
- [x] Overit desktop/mobile assety, syntax a testy
- [x] Provest Wiki Reflection a uzavrit vysledek

## Poznamky

Prvni screenshot je aktualni stav, druhy je cil. Filmovy pas a zakladni barevny vibe funguje; selhava hlavne skladani sekci: sluzby zustaly jako vysoke karty, portfolio je tmave, blog je rozbity, FAQ je samostatny tmavy blok, poradi procesu/ceniku/referenci neodpovida cili a kontakt nema tri cilove panely.

To odpovida situaci, kdy se nacte cast CSS, ale theme DOM kompozitor se nespusti nebo skonci pred preusporadanim.

Root cause: `template.js_source` a theme JS se generuji do jednoho assetu, legacy skript byl pred theme kompozitorem. Runtime chyba legacy kodu proto zastavila zbytek souboru. Pro `bezova-lens-tale` je poradi symetricky v runtime i publish otoceno: nejdriv idempotentni theme kompozitor, potom legacy skript. Ostatni temata zachovavaji puvodni poradi.

Theme po uspesnem dokonceni nastavuje `body[data-blt-ready="true"]`, aby bylo mozne odlisit nacteny CSS asset od skutecne dokoncene DOM kompozice.

Overeni generovaneho assetu: theme IIFE zacina na pozici 0 a `'use strict'` na pozici 14, tedy pred legacy page kodem. PHP lint, Windows JScript syntax check, HTTP preview a 18/18 testu prosly. CSS selektory odpovidaji vsem tridam, ktere kompozitor vytvari.

Wiki Reflection: novy root cause nebyl v dossieru popsany, proto byl aktualizovan SEO Editor hub a architecture stranka a wiki zmena byla ihned commitnuta jako `297d7ee`.

## Vysledek

Opravena pricina neaplikovaneho designu: pro `bezova-lens-tale` se theme DOM kompozitor v runtime i publish generovanem assetu spousti pred legacy page JavaScriptem. Cilove tridy, poradi sekci a panely tak vzniknou i kdyz nasledny legacy kod vyhodi runtime chybu. Pridan dokoncovaci marker `data-blt-ready`.
