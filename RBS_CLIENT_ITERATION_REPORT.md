# Report: issues po konzultaci s RBS Videos

Datum: 2026-08-23

## Opravene issues

- Hero video: renderer nyni rozlisuje YouTube/Vimeo embed a prime MP4/WebM/MOV. Nativni video ma `autoplay`, `muted`, `playsinline`, `loop`; poster je fallback, ne trvaly prekryv.
- Homepage O nas: existujici `service_items` pipeline zustava jedinym zdrojem a Chrome overil 6 aktivnich karet.
- Homepage kontakt: `contact_visual` se predava do core partialu a vykresluje v levem panelu. Chybejici blok doplni migrace z existujiciho posteru.
- Navigace a footer: pricina byla sdilena Golden Bash sablona/footer s natvrdo vlozenym `Wedding stories`. Text byl odstranen u zdroje; vsechny routy pouzivaji `sites.name`.
- Nase prace: listing je rozdelen na Fotogalerii a Videogalerii; prazdna skupina ma kompaktni citelny stav.
- Razeni: zachovany `sort_order`, pridan explicitni `content_type`; live/publish radi deterministicky `content_type`, `sort_order`, `id`.
- Jak to probiha: staticka PHTML data byla presunuta do `shared_sections` a jednoho admin editoru.
- Hero texty: audit potvrdil `content_blocks` a inline editor pro hlavni headline/subtitle uvedenych route.

## Zmenene oblasti

Migrace 065; portfolio/shared-section repository a controllery; admin formular a rozcestnik; live/publish view modely a partialy; Golden Bash layout/footer; hero renderer/runtime; testy, golden snapshots, QA skript a dokumentace.

## Databaze a migrace

Nova append-only migrace 065 pridava `portfolio_entries.content_type`, listing index, tabulku `shared_sections`, centralni `process_cooperation` a chybejici homepage `contact_visual`. Existujici typy odvozuje z `video_url`. Historicke migrace 001-064 nebyly zmeneny.

## Nove moznosti administrace

- `Sdilene sekce -> Jak to probiha`: stitek, nadpis, uvod, 4 kroky, 4 vyhody, ikony a aktivita.
- `Nase prace`: typ Fotogalerie/Videogalerie a ciselne poradi.
- Homepage kontaktni obraz jako standardni image blok.
- Rozcestnik na existujici centralni FAQ, reference, branding a strankove bloky.

## Hardcoded text audit

Do DB byly prevedeny obsah procesu a kontaktni vizual. Hero texty, hlavni section texty, FAQ, reference, sluzby, portfolio a blog uz pouzivaji DB/inline editor. Zamerne zustaly technicke nebo taxonomicke popisky (`Fotogalerie`, `Videogalerie`, prazdne stavy, formularove labely, accessibility texty a admin napoveda) a theme dekoracni kickery; nejde o druhou kopii klientsky spravovaneho obsahu.

## Testovani

- Migrace 065: OK; historie SKIP bez prepisu.
- `php tests/run.php`: 146/146; golden 28/28.
- Chrome 1440x900, 768x1024 a 390x844: homepage, Sluzby, Nase prace, Blog, Cenik, O nas, Hodnoceni; bez `Wedding stories`, mobilni menu 7 odkazu.
- Kontrolovany MP4 runtime: `paused=false`, `readyState=4`, `muted/autoplay/loop/playsInline=true`, bez chyby; puvodni DB hodnota obnovena.
- Kontaktni obraz: HTTP 200 `image/webp`, viditelny v desktop screenshotu.
- QA artefakty: `storage/qa/rbs-client-iteration/`.
