# UKOL: Dorovnani sablony bezova-lens-tale podle ciloveho designu

Datum: 2026-07-12
Zadani: Porovnat aktualni runtime sablony `bezova-lens-tale` s cilovym Lens & Tale designem, zapsat vsechny vizualni nesrovnalosti a postupne je opravit bez presunu obsahovych dat z DB do sablony.

Primarni cil: vsech 20 bodu auditu je zavazne akceptacni kriterium. Ukol neni hotovy, dokud nebude kazdy bod implementovan a overen, nebo nebude uveden konkretni viditelny blocker.

## Kroky

- [x] Porovnat aktualni a cilovy screenshot po jednotlivych sekcich
- [x] Zapsat uplny seznam vizualnich nesrovnalosti a jejich prioritu
- [x] Opravit globalni geometrii, filmovy pas, hlavicku a hero
- [x] Prestavet sekce sluzeb, portfolia, procesu, ceniku, referenci a blogu
- [x] Prestavet FAQ, kontakt a footer do svetleho editorialniho systemu
- [x] Projit vsech 20 auditu bod po bodu a zaznamenat dukaz splneni
- [x] Overit runtime, desktop/mobile pravidla, assety a regresni testy
- [x] Aktualizovat dokumentaci a provest Wiki Reflection

## Poznamky

### Audit nesrovnalosti

1. **Kompozice a meritko (kriticke):** aktualni web je priblizne polovicni sirky cilove kompozice, obsah je stlacen do uzkeho sloupce a vysledkem je nekolikanasobne delsi stranka. Cil ma siroky editorialni canvas, kompaktni sekce a stabilni levy obsahovy rail.
2. **Filmovy pas (kriticke):** aktualni pas je uzky, kresleny abstraktnimi gradienty a konci s obsahem. Cil ma vyrazny siroky pas s realistickymi obrazovymi policky, perforaci, zlatym vnitrnim okrajem a souvislou vyskou cele stránky.
3. **Vertikalni osa (kriticke):** v cili vede vedle pasu zlata linka s ocislovanymi uzly pro sekce. Aktualni sablona nema linku, uzly, cisla ani jednotny levy zacatek nadpisu.
4. **Header (vysoka):** aktualni logo je male a jine, navigace je drobna/preplnena a CTA nema cilove meritko. Cil pouziva samostatnou monogramovou znacku, slovni logo, vzduch, jednoduchou navigaci a velke zlate tlacitko.
5. **Hero (kriticke):** aktualni hero je tmave video pres celou sirku, text je centrovany a bily. Cil je svetly/high-key obraz, text vlevo, obri dvouradkovy serifovy nadpis, zlaty rukopisny akcent a dve CTA; vyska i kontrast jsou zcela odlisne.
6. **Typografie (kriticke):** aktualni texty jsou male a casto bile na tmavem podkladu. Cil ma velke cerne Cormorant nadpisy, uppercase mikrostitky, kratke odstavce a zlaty script akcent. Hierarchie chybi napric strankou.
7. **Barevny system (kriticke):** aktualni stred webu opakovane pada do temne hnede/cerne. Cil je temer cely ivory/bily s velmi jemnymi bezovymi plochami; tmava barva patri jen filmu a textu.
8. **Sluzby / O nas (vysoka):** aktualni tri vysoke karty neodpovidaji cili. Cil ma dvousloupcovy editorialni blok s textem vlevo, sirokou tymovou fotografii vpravo a pod nim vodorovny rad peti ikon kategorii.
9. **Portfolio (kriticke):** aktualni tmava galerie malych karet s play ikonami ma byt svetla asymetricka mozaika: velky hlavni obraz a pet mensich vyrezu, vse s oblými rohy.
10. **Proces (vysoka):** aktualni proces je dlouhy vertikalni textovy seznam. Cil ma pet kroku na jedne horizontalni ose s kruhovymi zlatymi ikonami a kratkymi popisky.
11. **Cenik (vysoka):** aktualni karty jsou prazdne/vysoke a oddelene od kontextu. Cil ma tri kompaktni rameckove balicky vedle uvodniho textu, prostredni zvyrazneny zlatym stitkem a vsechny viditelne ceny i vyhody.
12. **Reference (stredni):** aktualni reference vizualne chybi jako samostatna svetla sekce. Cil ma tri nizke citacni karty vedle editorialniho nadpisu.
13. **Blog (vysoka):** aktualni tri genericke vysoke karty maji malo obsahu. Cil ma dve siroke horizontalni karty s fotografii a textem, vedle leveho uvodniho bloku.
14. **FAQ (kriticke):** aktualni FAQ je samostatny tmavy pruh s tmavymi polozkami. Cil integruje FAQ do svetle kontaktni sekce v pravem sloupci s jemnymi zlatymi linkami.
15. **Kontakt (vysoka):** aktualni kontakt je roztazeny a pred nim je dlouha temna plocha. Cil ma kompaktní tri sloupce: uvod/kontakty, formular, FAQ; tlacitko formulare prekryva spodni hranu jako zlaty pill.
16. **Footer (stredni):** aktualni footer je temny a obsahove robustni. Cilovy screenshot konci svetlou kontaktni sekci bez dominantniho tmaveho bloku; footer ma byt subtilni.
17. **Karty a stiny (stredni):** aktualni karty maji tezke sede stiny, velkou vysku a nekonzistentni zaobleni. Cil pouziva tenkou zlatobezovou linku, velmi lehky stin a radius cca 18–28 px.
18. **Rytmus a whitespace (kriticke):** aktualni sekce maji nekonzistentni mezery a casto obrovske prazdne vysky. Cil ma kazdou sekci jako kompaktní horizontalni kapitolu s jasnym cislem a konzistentnim odsazenim.
19. **Obrazovy styling (vysoka):** aktualni media jsou tmava, casto orezana jako bezne karty. Cil pouziva svetle teple fotografie, mozaikove orezy a velke oblouky/rohy. Fotografie zustanou DB-driven, ale jejich crop, overlay a radius upravi theme CSS.
20. **Responzivita (vysoka):** oprava musi zachovat cilovou desktopovou kompozici bez horizontalniho overflow a na mobilu prevest horizontalni bloky do citelneho poradi; filmovy pas se ma zuzit, ne prekryt obsah.

Obsah zustava DB-first. Opravy budou v `Templates/bezova-lens-tale/`; chybejici texty, fotografie nebo ceny se nebudou maskovat hardcoded obsahem v layoutu.

Prvni opravna iterace: canvas rozsiren na 1530 px, film na 150 px a napojen na DB obrazky z aktualni stranky; pridana zlata kapitola/osa, svetle hero s levym textem, svetle sekce, mozaikove portfolio, horizontalni proces, kompaktní cenik/reference/blog a svetly kontakt/footer. Vse je pouze prezentacni transformace existujiciho rendereru.

Druha iterace: `#proc-my` preveden na svetlou O nas/capability kapitolu; cenik, blog, proces a reference maji pevny levy editorialni rail a obsah vpravo; duplicitni reference se skryji; existujici DB FAQ se pri pritomnosti presune do tretiho sloupce kontaktu; legacy footer a cerna tlacitka jsou neutralizovany. Pokud DB FAQ na konkretni homepage chybi, theme jej nevymysli ani nemaskuje hardcoded obsahem.

### Dukaz splneni auditu

- 1, 18: canvas 1530 px, obsah 1180 px, jednotne sekce 78 px a kompaktni horizontalni gridy.
- 2, 19: film 150 px, perforace, zlata obruba a policka napojena na DB obrazky s konzistentnim cropem.
- 3: `.blt-storyline`, ocislovane chapter nodes a jednotny levy rail.
- 4, 5, 6: zvetsena hlavicka/logo/CTA; hero 710 px, svetly gradient, levy text, 72–112px serifovy H1.
- 7, 16: vsechny legacy dark sekce prepsany na ivory; legacy footer skryt, theme footer zesvetlen.
- 8: O nas/capability sekce z `#proc-my`, petidilny vodorovny rail a svetla servisni prezentace.
- 9: portfolio 1.45/1/1 mozaika, hlavni polozka pres dva radky, obrazovy crop a radius.
- 10: petisloupcovy proces s kruhovymi ikonami a spojovaci osou.
- 11: tri kompaktni cenikove karty, zlaty ramecek, vyrazna cena a zvyrazneny prostredni balicek.
- 12: jedna svetla reference sekce se tremi kartami; duplicitni legacy reference se odstrani.
- 13: dve siroke blogove karty s obrazkem a textem vedle leveho editorialniho uvodu.
- 14, 15: svetly kontakt; existujici DB FAQ se sklada jako treti sloupec, bez hardcoded otazek.
- 17: sjednocene tenke linky, lehke stiny a radius 22–28 px.
- 20: breakpointy 980/640 px, zuzeny film, skryta osa na mobilu, vsechny horizontalni bloky se skladaji bez pevne desktopove sirky.

HTTP runtime: 200; generovany CSS 52 935 B a JS 4 624 B obsahuji vsechny opravne selektory/transformace. Testy 18/18 a `git diff --check` prosly. Skutecny browser screenshot zustava neovereny: vestaveny browser opakovane selhal pred otevrenim stranky na chybejicim `sandboxPolicy`; podle pravidla No Silent Fallback nebyl nahrazen jinym skrytym rendererem.

Treti iterace zahajena: theme JavaScript dostal `data-blt-initialized` guard. Preview/runtime muze stejny asset vlozit vicekrat; bez guardu by se zdvojily listenery, FAQ panel a DOM transformace.

Layout byl ocisten od hardcoded znacky `L` a reklamni vety ve footeru. Monogram se nyni odvozuje z DB `{site_name}` a footer zobrazuje pouze DB nazev webu; theme tak neprepisuje identitu konkretniho klienta obsahem Lens & Tale.

Wiki Reflection: dotcene domeny `architecture`, `pipelines` a `tools` maji v dossieru SEO Editor coverage `ok`; DB-first theme kontrakt, preview workflow, runtime priorita a idempotentni JavaScript jsou jiz pokryty. Verdikt SKIP.

## Vysledek

Implementovano vsech 20 bodu auditniho seznamu v DB-neutralni theme vrstve. Lokalni HTTP preview a generovane assety jsou funkcni, responzivni pravidla jsou pritomna, testy prosly 18/18. Skutecny screenshot nebyl kvuli uvedenemu browser blockeru porizen; dalsi pixelova iterace proto vyzaduje novy screenshot vyrenderovane verze.
