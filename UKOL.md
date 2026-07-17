# UKOL: ETHERNA z dodaneho UI/UX prototypu

Datum: 2026-07-17
Zadani: Vytvorit novou prazdnou DB-first sablonu ETHERNA podle celeho dodaneho multipage HTML/CSS/JS prototypu, bez zmen jadra CMS.

## Kroky

- [x] Ulozit hotovou rozpracovanou sablonu Elegant White 3 do samostatneho commitu.
- [x] Zmapovat strukturu, CSS, JavaScript a vsechny stranky dodaneho prototypu.
- [x] Vytvorit izolovanou sablonu `Templates/etherna` s manifestem, layoutem a assety.
- [x] Prenest vzhled a page-specific rozlozeni prototypu na existujici DB-driven vystupy CMS.
- [x] Overit manifest, PHP testy, syntaxi assetu, diff a runtime preview podporovanych rout.
- [x] Aktualizovat dokumentaci a uzavrit vysledek.

## Poznamky

- Nazev tematu je presne `ETHERNA`; podtitul je `Eterna — Luxury Wedding Photography & Film Template`.
- Zdrojovy multipage prototyp je pouze vizualni a strukturou predloha. Klientske texty, kontakty, ceny, clanky, portfolio a media se nesmi kopirovat jako hardcoded obsah do CMS sablony.
- Cilem je plne vyuzit designovy jazyk, CSS a rozlozeni stranek prototypu v mezich existujiciho kontraktu layoutu a DB-driven renderovanych bloků.
- Vytvoren `Templates/etherna`: manifest registruje globalni layout a samostatne homepage/inner-page assety; assety prenaseji filmovy hero, editorialni typografii, karty, cenik, FAQ, formulare, listingy, detail a mobilni navigaci bez prototypovych dat.
- Runtime preview na `http://127.0.0.1:8766/?theme_preview=etherna` vratil HTTP 200, tridu `theme-etherna`, ETHERNA layout a generovane CSS/JS assety. Stejny theme contract prosly `/sluzby`, `/nase-prace`, `/blog`, `/cenik`, `/faq` a `/kontakt`.
- Zdrojovy prototyp ma take samostatne staticke legalni, cookie a 404 stranky. CMS aktualne nema samostatne specialni renderery pro tyto archetypy; bez DB obsahu je ETHERNA nemaskuje prototypovym textem a generic CMS page nadale pouzije globalni layout.

## Vysledek

Hotovo. ETHERNA je nova izolovana prazdna DB-first sablona. Dokumentace: `README.md` a `Templates/etherna/README.md`. Overeni: 27/27 PHP testu, validni JSON manifest, registry/runtime preview vsech podporovanych verejnych rout a HTTP 200 pro generovane ETHERNA CSS/JS assety; `git diff --check` prosel.
