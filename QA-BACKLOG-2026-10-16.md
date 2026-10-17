# Ověření oprav backlogu — 16. 10. 2026

Runtime `http://127.0.0.1:8767/`, aktivní Golden Bash Diagonal, skutečná lokální DB. Google Chrome přes CDP, DPR 1, cache vypnutá; konečný profil port 9336 s `--hide-scrollbars`. Kontrolované šířky 390, 700, 701, 1025, 1050, 1051 a 1920 CSS px. Výška kontrol 844, screenshotový detail 1025×724. Cookie dialog v konečných kontrolách odmítnut skutečným ovládacím prvkem.

## Rubrika a omezení

Rubrika pro nahlášené vady: navigace a stavy 35 bodů, skutečně použitý český font 30, pokrytí videa/posteru 35. **Celkové vizuální skóre nepřiděleno**: lokální média a obsah se liší od uživatelského screenshotu, logo chybí a Vimeo v automatickém Chrome nehraje. Baseline `.codex-qa/backlog-baseline-1025.png` je diagnostika, nikoli platné úplné srovnání. Produkční prohlížečový preview s lokálními styly byl jen dočasný diagnostický pokus, žádný produkční deploy.

## Zjištění a změny

- Burger byl viditelný do 1050 px, ale animace existovala jen v užším mobilním bloku. Nyní má společné centrování/transformace, přechod a transparentní hover/active až do 1050 px. Escape a `aria-expanded` zůstávají svázané s otevřením menu; focus-visible je zachovaný.
- Původní kurzíva obsahovala Light outlines deklarované jako 400. Nový soubor je skutečný Regular 400 v4.001, 979 Unicode znaků včetně kompletní české sady. Preload i font CSS ukazují na nový název, staré cachované soubory se nepřepisují. Swap umožňuje opravit opožděný font; při pomalé síti může vzniknout layout shift, který je potřeba zkontrolovat na stagingu.
- Oficiální Vimeo oEmbed vrátil pro desktop 426×206 a mobil 240×240. Dřívější pevný poměr 16:9 nemohl spolehlivě vyplnit hero. Runtime žádá rozměry přímo od iframe, ověřuje přesný origin i `event.source` a volí rozměry `max(panelWidth, panelHeight*ratio)` / `max(panelHeight, panelWidth/ratio)`. Změny rozměru panelu sleduje ResizeObserver. Při neznámých metadatech zůstává výslovně provizorní 16:9 s console warning a `data-video-cover-error` po 15 s.
- Minifikátor poškodil regulární výraz končící escapovaným lomítkem. Kontrola URL proto používá přímý prefix; následně byla zkontrolována syntaxe skutečně vygenerovaného runtime, ne pouze zdroje.

## Důkazy

- `node tests/HeroVideoCoverTest.js [cesta-ke-generovanemu-bundle]`: reálné poměry, resize, odmítnutí cizího origin/iframe, neplatných rozměrů a výslovný timeout; také kompilace zdroje a volitelného skutečného bundle.
- `php tests/run.php`: 219/219 funkčních testů, 27/28 HTML golden kontrol. Zbývající `o-nas publish` je známý starší rozdíl posteru; nezměněn. Golden změny se týkají pouze nového font preloadu a vysvětlujícího komentáře.
- `.codex-qa/backlog-verified-*.log`: skutečné Chrome fonty, geometrie, `aria-expanded`, transformace ±45°, transparentní hover, Escape/focus, overflow a JS výjimky. Video geometrie používá **výslovně syntetické Vimeo metadata fixtures** ověřených rozměrů; není důkazem skutečného přehrávání ani živé komunikace Vimeo API.
- Detail fontu `.codex-qa/backlog-font-final.png`; navigace `.codex-qa/backlog-final-390-open.png`, `.codex-qa/backlog-final-1050-open.png`. Lokální QA soubory jsou ignorované a nejsou součástí deploymentu.

## Otevřené brány

Staging po uživatelském potvrzení DB/.env: homepage HTTP 404, login HTTP 200. Dokončit setup/demo/publish, ověřit skutečně hrající video bez pruhů, poster před načtením/při výpadku, pozdní font load, dotykové ovládání a SMTP. Master branch protection ani produkční merge nejsou součástí dokončených kontrol. Změny připravené na dev; tento úkol neobsahuje nový push ani produkční nasazení.
# Homepage O nás: větší portrét (2026-10-17)

Golden Bash i Diagonal od šířky 1200 CSS px používají pro fotografii 40 %
obsahové mřížky a mezeru 48 px. Portrét se natahuje na výšku sousedního
sloupce; absolutní obrázek má 100% výšku a `object-fit: cover`, bez deformace.
Původní poměr 10:11 pod 1200 px zůstává zachován. Natažení od 951 px bylo
zamítnuto po vizuálním ověření: ořezávalo obličeje.

Chrome CDP náhled nad staging daty, DPR 1, šířky 1920/1199/1200/390:
fotografie načtena, nulové horizontální přetékání. Na 1920 px fotografie
552 × 698 px místo 430 × 473 px, výška shodná se sloupcem obsahu.
Rubrika cílové sekce: velikost a vyrovnání 45, ořez 25, responzivita 20,
runtime 10; finální ověření nasazeného CSS zaznamená UKOL.md.
