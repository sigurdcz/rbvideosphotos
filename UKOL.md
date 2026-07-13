# UKOL: Bezova Lens & Tale - detailni opravy a vizualni QA skill

Datum: 2026-07-13
Zadani: Nejprve ulozit do wiki a Codex skillu metodiku bodovane screenshot iterace, potom opravit vsechny oznacene vady homepage a overovat je opakovanymi screenshoty pri viewportu alespon 1280 px.

## Kroky

- [x] Zapsat reprodukovatelnou metodiku screenshot score loopu do wiki
- [x] Vytvorit a validovat Codex skill pro iterativni vizualni QA
- [x] Odstranit zdedeny Brand Page prvek a opravit anchor aktivaci navigace
- [x] Opravit hero texty, gramatiku CTA, overlay, pozici fotografie a kapitolu 1
- [x] Opravit sekci Co nabizime, ikony a prekryv panelu
- [x] Opravit cenik, featured label, karty a prechody mezi sekcemi
- [x] Opravit hover lesk, reference, kontakt a paticku
- [x] Opakovat screenshot loop pri 1440 px do splneni vsech kriterii
- [x] Spustit testy, diff check a uzavrit dokumentaci

## Poznamky

Vychozi skore po uzivatelskem detailnim auditu: 72/100. Zdroj pravdy tvori dodane anotovane screenshoty a posledni cilovy preview. Screenshoty zustavaji lokalni QA artefakty mimo Git.

Wiki nyni obsahuje samostatny pattern `patterns/visual-screenshot-score-loop.html`, kanonicky glossary tag a katalogovy zaznam. Globalni skill `visual-screenshot-score-loop` je inicializovan se samostatnou bodovaci rubrikou; pred opravou homepage jeste projde validator.

Skill prosel oficialnim `quick_validate.py` po doplneni chybejici validační zavislosti PyYAML 6.0.3. Wiki zmena je samostatne commitnuta jako `8a68334`; dva nesouvisejici rozpracovane wiki soubory zustaly nedotcene.

Homepage iterace 1 odstranuje legacy `.brand`, checkbox a label primo z DOM. Aktivni navigace uz neporovnava pouze pathname `/`, ale sleduje skutecne anchor sekce. Hero dostava spravnou cestinu, horni crop fotografie, kontrastni metadata a marker kapitoly vycentrovany na rail.

Iterace 1: 79/100. Iterace 2: 87/100. Druha iterace vratila cele nabidkove panely se semantickymi SVG ikonami, odkryla featured stitek a cele cenove karty, oddelila reference a zavedla zlaty shimmer hover. Treti iterace zkracuje a zpevnuje proces, kontakt a paticku a zvysuje kontrast jejich ovladacich a textovych prvku.

Finalni iterace: 95/100. Playwright capture pouziva skutecny viewport 1440x900 a 1280x800, pred full-page screenshotem postupne aktivuje scroll-reveal a vraci se do hero. Pri 1280 px je horizontalni overflow 0, navigace v hero nema aktivni polozku a cenove karty konci 17 px pred hranou sekce. Hover ma overeny zlaty gradient a stin. `php tests/run.php`: 26 testu, 0 chyb; `git diff --check`: bez chyb, pouze upozorneni na budouci CRLF normalizaci.

Kanonicky designovy zdroj je nyni explicitne zaznamenan v `Templates/bezova-lens-tale/REFERENCE_TARGET.md`: staticky `lens-tale-multipage` s canvasem 864x2093, filmem 108 px, originalni typografii, ikonami a komponentami. Pozdejsi uzivatelske korekce maji prednost, zejmena odstraneni chapter railu a cisel.

## Vysledek

Hotovo. Dosazene a overene skore: 95/100.
