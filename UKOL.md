# UKOL: Bezova Lens & Tale - prisny komponentovy audit

Datum: 2026-07-13
Zadani: Opravit homepage podle uzivatelsky anotovanych screenshotu. Predchozi tvrzeni 100/100 je zruseno; geometricka shoda sama o sobe nebyla dukazem vizualni spravnosti komponent.

## Vychozi stav

- Platne vychozi skore: **72/100**
- Viewport: 1280x800 CSS px, DPR 1, Chrome
- Runtime: `http://127.0.0.1:8766/`
- Cil: dodana sablona Lens & Tale a pozdejsi explicitni anotace uzivatele

## Povinna akceptacni kriteria

- [x] Co nabizime: obrazek je v dostatecne vysokem ramecku, zarovnany k hornimu okraji a neusekava hlavy
- [x] Co nabizime: CTA `Vice o nas` pouziva jednotny zlaty CTA styl
- [x] Co nabizime: vsech pet polozek pochazi z DB; zadne dve staticke JS nahrazky
- [x] Co nabizime: vsech pet karet ma stejny DOM, rozmery, ikony a zarovnani
- [x] Nase prace: CTA je dole v levem obsahovem sloupci a neprekryva portfolio
- [x] Jak to probiha: levy obsah nezasahuje do procesu a CTA odpovida standardu webu
- [x] Cenik: levy obsah nezasahuje do karet; featured label je plne citelny
- [x] Cenik: vsechny tri karty maji citelny odrazkovy seznam vlastnosti
- [x] Reference: levy obsah nezasahuje do karet a obe sipky jsou uvnitr canvasu
- [x] Kontakt: tri panely maji shodnou vysku a vizualne oddelene plochy
- [x] Kontakt: inputy a textarea jsou jasne viditelne; submit CTA neni schovane
- [x] Kontakt: FAQ nema zdvojeny label, polozky splyvaji s panelem a CTA ma standardni styl
- [x] Paticka: logo a nazev maji viditelny odstup od filmoveho pasu
- [x] Interakce: aktivni navigace, FAQ, carousel a hover/focus zustavaji funkcni
- [x] Runtime: zadny horizontalni overflow, pageerror ani chybejici asset; PHP testy projdou
- [x] Finalni screenshot pri 1280 px projde rucni kontrolou vsech bodu, ne pouze DOM geometrii

## Rubrika 100 bodu

- Globalni canvas a geometrie: 15
- Header, hero a navigace: 10
- Co nabizime: 15
- Portfolio a proces: 15
- Cenik a reference: 20
- Kontakt a paticka: 15
- Typografie, media, CTA a ikony: 5
- Interakce a runtime integrita: 5

## Iterace

- Iterace 0: **72/100**. Duvody: dve staticke servisni polozky, chybne cropovani about fotografie, nekonzistentni CTA, prekryvy levych sloupcu, necitelny featured label, chybejici odrazky, reviews sipka mimo canvas, rozbita hierarchie/kontrast kontaktu a nestejne vysky panelu.
- Implementace iterace 1: seed rozsiruje `service_items` na pet DB zaznamu a homepage renderuje vsech pet; JS staticke doplnky byly odstraneny. Portfolio CTA se presouva do leveho obsahoveho sloupce, cenove popisy se meni na semanticke odrazky a finalni CSS oddeluje sloupce, sjednocuje CTA, kontaktni panely a footer offset. Skore zustava 72/100 do porizeni noveho screenshotu.
- Iterace 1: **84/100**, screenshot `homepage-component-audit-iteration-01-1280.png`. Proslo: crop fotografie nahoru, 3 cenove seznamy, kontakt 390/390/390, 0 overflow, runtime/interakce. Neproslo: Demo seeder znovu omezil homepage na 3 DB sluzby; review controls mely box x=784..1488 a prava sipka byla mimo canvas. Oprava iterace 2 meni vsechny runtime/autofill limity na 5 a rusi konflikt margin+left u carouselu.
- Kontrola iterace 2 odhalila jeste jeden limit `3` primo ve `fillHomepageFallbacks`; byl zmenen na 5. Carousel controls jsou nyni uvnitr canvasu (x=520..1224). Skore zustava 84/100, dokud novy screenshot nepotvrdi pet karet.
- Iterace 3: **92/100**, screenshot `homepage-component-audit-iteration-03-1280.png`. Proslo: 5 DB karet / 0 JS doplnku, reviews controls x=520..1224, vsechny CTA maji zlaty gradient a bily text, vsechny kontaktni panely jsou stejne vysoke. Zbyvala portfolio CTA siroka 264 px a prekryvajici zacatek gridu; finalni pravidlo ji omezuje na 200 px uvnitr 218px leveho sloupce.
- Prvni finalni capture zachytil about PNG pred dokoncenim dekodovani a ukazal alt text; HTTP zdroj je pritom 200 `image/png` a ma 178364 B. QA skript proto pred screenshotem explicitne ceka na `decode()` vsech obrazku a dva animation framy. Tato iterace se kvuli viditelne chybe media nehodnoti vys nez 89/100.
- Finalni cista iterace: **97/100**, screenshot `homepage-component-audit-final-clean-1280.png`. PHP lokalni server byl spusten se 4 workery, aby full-page capture neztracel paralelni media pozadavky. Audit: request failures 0, page errors 0, overflow 0, 5 DB sluzeb, 0 statickych doplnku, 3 pricing listy, kontakt 390/390/390 a vsechny interakce prosly. `php tests/run.php`: 26/26; PHP lint 4/4; migrace beze zmen.

## Vysledek

Hotovo. Finalni poctive skore: **97/100**. Screenshot: `homepage-component-audit-final-clean-1280.png`. Povinne body z anotaci prosly; 3 body zustavaji jako rezerva za obecnou pixelovou odchylku od cele staticke predlohy. Runtime audit: 5 DB karet, 0 statickych doplnku, 3 cenove seznamy, kontakt 390/390/390, reviews controls x=520..1224, portfolio CTA 200 px, overflow 0, request failures 0, page errors 0 a vsechny interakce prosly.
