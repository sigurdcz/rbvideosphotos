# UKOL: Nova sablona Elegant White 2

Datum: 2026-07-10
Zadani: Vytvorit zcela novou samostatnou sablonu `elegant-white2`, ktera zachova DB obsah a strukturu sekci, ale prida premiovy bilo-zlaty editorial vzhled a vanilla CSS/JS animacni vrstvu podle prilozeného briefu.

Hotovo kdyz: sablona je samostatne registrovana a volitelna; nemeni data ani poradi sekci; pouziva zadane tokeny, Fraunces/Jost, ambient, zlatou nit, hero load sekvenci, scroll reveal, sticky navigaci, gradientni mikrointerakce a reduced-motion; funguje na hlavni i specialnich strankach od 360 px bez horizontalniho preteceni a bez JS chyb.

## Kroky

- [x] Zmapovat registraci sablon, layouty a realny DOM vsech typu stranek
- [x] Vytvorit samostatnou slozku a manifest/layouty `elegant-white2`
- [x] Implementovat kompletni tokenizovany editorial CSS vzhled
- [x] Implementovat vanilla JS animace, nit, sticky nav a pristupnost
- [x] Zaregistrovat sablonu v editoru bez zasahu do DB obsahu
- [x] Overit homepage a specialni stranky na desktopu a mobilu
- [x] Aktualizovat relevantni dokumentaci a projektovou wiki

## Poznamky

- Predchozi homepage opravy jsou oddeleny v commitu `0940dcb`; novy ukol zacina z cisteho stromu.
- `elegant-white` se nebude prepisovat ani pouzivat jako runtime zavislost nove sablony.
- Obsah, odkazy, media a poradi sekci zustavaji DB-driven. Theme muze pridavat pouze tridy a nezbytne prezentacni obaly/vrstvy.
- Bez knihoven a bez build kroku; vsechny animace pouze pres `transform` a `opacity`.
- `template.json` registruje samostatny klic `elegant-white2` pro vsechny podporovane page typy; discovery probiha automaticky pres `ThemeTemplateRegistry`.
- `layout.html` zachovava `{page_content}` a pridava pouze theme shell, dve ambientni vrstvy, SVG nit a filmove zrno.
- `assets/theme.css` definuje zadane tokeny, Fraunces/Jost hierarchii, svetly editorialni layout, gradientni akcenty, curtain/reveal stavy, karty, formulare, vsechny specialni page typy a responzivni/reduced-motion vetve.
- `assets/theme.js` pracuje pouze s prezentacnimi tridami/obaly: deli hero titulek do masek, akcentuje jedno slovo, spousti IO reveals, lerpuje SVG nit, ridi sticky header, mobilni menu a pristupne FAQ stavy.
- Sablona je volitelna automaticky po nacteni manifestu; zadna DB data, texty ani media nebyly vlozeny do theme souboru.
- Existujici DB FAQ bridge v runtime/publish vrstve byl rozsiren i na `theme-elegant-white2`; nova theme JS slouci ctvrty blok bez duplikace a nic nehardcoduje.
- Uzivatel doplnil pozadavek na commit a push; provedou se az po uspesnem browser a syntax overeni.
- Prvni DOM audit odhalil, ze DB layout CSS drzi vnoreny `.nav` jako `position:fixed`; nova sablona jej explicitne vraci do statickeho header flow, aby existoval jen jeden sticky navigacni kontext.
- Zdrojovy audit odhalil prazdna DB pole `layout_nav_home` a `layout_nav_sub`. Layout nove sablony proto obsahuje vlastni oznaceny default se standardnimi trasami; jakmile editor doda DB navigaci, CSS/JS default odstrani a pouzije pouze dodana data.
- Svetly preliv specialnich hero sekci byl zesilen, aby `--ink` nadpisy zustaly citelne i nad tmavymi DB fotografiemi.
- Globalni CMS video overlay se nacita az po theme assets a pouziva `!important`; nova sablona proto ma vlastni vysoce specificky runtime override, ktery odstrani tmavy filtr a zachova svetly editorialni kontrast na vsech hero variantach.
- Specialni routy nepridavaji `data-cms-page-key` na `<body>` stejne jako genericka DB cesta; runtime override je proto vazany na jednoznacnou body theme tridu, ne na volitelny atribut. Mobilni menu navic zastavuje kolidujici globalni click bubbling.
- Runtime muze vlozit theme JS dvakrat v kombinovane preview ceste. Inicializace je proto idempotentni na `body.dataset.ew2Initialized`; tim se eliminuji dvojite listenery, dve rAF smycky a dvojite prepnuti mobilniho menu.
- Homepage DB sablona obsahuje vlastni fixni `.nav` uvnitr `{page_content}`. Elegant White 2 ji skryva pouze jako primeho potomka `.ew2-page-content`, aby se neprekryvala s jedinou layoutovou sticky navigaci.
- Desktop audit pokryl homepage, sluzby, portfolio, blog, cenik, FAQ a kontakt: Fraunces se nacita, nav ma aktivni stav, homepage ma 4 FAQ, konzole nema zavazne chyby a sirka dokumentu se rovna viewportu.
- Pri 360 px je zlatá nit skryta, gridy se skladaji do jednoho sloupce, mobilni menu se otevre a nevznika horizontalni preteceni. Reduced-motion zobrazi `.rv`, nastavi nit na nulu a zkrati animace na prakticky nulove trvani.
- README registr dostupnych temat obsahuje `elegant-white2`. Projektova wiki byla doplnena v samostatnych commitech `9349eaf` a `2f7ddef`; wiki repozitar nema lintovaci skript, proto probehla HTML diff a whitespace kontrola.
- Finalni mobilni screenshot odhalil, ze runtime nechaval `.nav-inner` ve vysce 60 px a sloupcove odkazy zarovnal mimo viewport. Mobilni theme scope nyni vynucuje auto vysku, nulovy min-height, start alignment, plnou sirku odkazu a skrolovatelne menu do vysky viewportu.

## Vysledek

Vznikla zcela nova samostatna sablona `Elegant White 2` s vlastnim manifestem, layoutem, CSS a idempotentnim JS. Zachovava DB obsah a poradi sekci, pridava Fraunces/Jost typografii, svetly paper/cream system, jemne zlate akcenty, ambient, filmove zrno, scrollovou zlatou nit, hero curtain, IO reveals, sticky responzivni navigaci a reduced-motion rezim. Runtime i publish cesta predavaji ctvrty DB FAQ blok take nove theme. Vsechny hlavni verejne routy vraceji 200 a pouzivaji `theme-elegant-white2`; desktop i 360 px nemaji horizontalni preteceni ani zavazne konzolove chyby.
