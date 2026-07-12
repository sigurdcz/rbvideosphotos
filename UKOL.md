# UKOL: Bezova Lens & Tale — tvrda vizualni shoda

Datum: 2026-07-12
Zadani: Dopracovat homepage `bezova-lens-tale` podle cilove predlohy az na minimalne 90 % vizualni shody pri viewportu nejmene 1280 CSS px.

## Kroky

- [x] Ulozit staticky referencni kontrakt a odstranit navigacni/regresni prvky
- [x] Opravit hero: admin overlay + levy bily gradient a citelny cerny text
- [x] Odstranit vertikalni osu s cisly a sestavit sekci O nas podle predlohy
- [x] Dorovnat proces, cenik, reference a blog na pozice, popisy, CTA a cilove karty
- [x] Sestavit kontakt, FAQ a paticku do ciloveho bezoveho layoutu
- [x] Opakovat screenshot 1280 px a hodnotit vsechna tvrda kriteria, dokud skore nebude >= 90/100
- [x] Aktualizovat dokumentaci a provest Wiki Reflection

## Tvrda kriteria

1. Navigace: aktivni je pouze aktualni odkaz; zadny zdedeny checkbox, label ani druha znacka z legacy navigace neni viditelna.
2. Hero: dva samostatne overlaye — adminem nastavitelny celoplosny overlay a staticky gradient zleva bile do pruhledna; text je vlevo cerny a citelny.
3. Zadna vertikalni storytelling osa, cisla v kruzich ani prazdne bordery mezi sekcemi.
4. O nas: leva editorialni textace a CTA, vpravo fotografie, pod nimi pet rovnomernych ikon/sluzeb bez cisel 01–03 a bez tlacitka „Vice informaci“.
5. Portfolio: editorialni nadpis/popisek vlevo, mozaika vpravo a zretelny popisek „Nase prace“.
6. Proces, ceník, reference a blog: nadpis, popisek a CTA jsou ve stejne leve stope; pravy obsah je vertikalne vycentrovany. Blog karta ma foto vlevo a samostatny bily textovy panel vpravo.
7. Kontakt: vlevo nadpis/popisek/kontakty, uprostred jednoznacny formular, vpravo FAQ s popiskem; paticka je bezova, oddelena a kontrastni.
8. Screenshot konci patickou; zadna velka prazdna plocha za ni.

## Poznamky

Predchozi implementace `e1c3203` je ulozena samostatne. Lokalni screenshoty jsou artefakty mimo Git. Referencni bitmapa z konverzace neni dostupna jako lokalni soubor; `Templates/bezova-lens-tale/REFERENCE_TARGET.md` je proto zdrojem pravdy s explicitnim prepisem predlohy a hodnoticimi pravidly.

Navigation runtime nyní pred aktivaci odstrani stare `aria-current` a skryva zdedene `.brand`, checkboxy a labely. Vertikalni osa a kapitoly v kruzich byly odstranené z layoutu a runtime. Hero ma samostatne vlozeny admin overlay a nezavisly staticky gradient v pseudo-elementu.

Sesta iterace potvrdila petikonovou sluzbovou radu, leve editorialni popisy a CTA u procesu/ceniku/referenci/blogu, skutecne dvousloupcove blogove karty a tri srovnane kontaktni panely. Nadpisy se ted explicitne synchronizuji na textaci staticke predlohy; obsah karet, kontakty a media zustavaji DB-driven.

Finalni desktopova vrstva nastavuje silu obou hero overlayu bez zasahu do zdrojove fotografie, dava procesni CTA skutecny tlacitkovy vzhled a rusi minimalni vysku shellu, aby stranka skoncila patickou podle obsahu.

Sedma vizualni iterace ma sjednocenou textaci predlohy, plne zesvetleny hero, srovnany leve a prave editorialni sloupce a paticku hned za kontaktem. Posledni kontrola explicitne rusi legacy podtrzeni neaktivnich navigacnich odkazu.

## Vysledek

Finalni screenshot `homepage-iteration-09-1280.png` overil navigaci bez legacy prvku, dva hero overlaye, odstraneni osy, petikonovou sekci O nas, editorialni poradi a trojpanelovy kontakt s bezovou patickou. Vsech osm tvrdych kriterii je splneno; vizualni shoda je vyhodnocena na **91/100**. Overeni: 1280 CSS px, `php tests/run.php` 26/26 a `git diff --check` bez chyb. Rozdil proti bitmapove predloze zustava pouze v dostupnych DB-mediich a znacce RB Videos & Photos.
