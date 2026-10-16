# AI_TODO - routing modelu a rozpocet tokenu

Tenhle soubor cte skill `model-check` pred zahajenim prace. Rika, ktery model ma delat jaky
druh ukolu v tomhle projektu a jak se pozna, ze prace zbytecne plytva tokeny.

## Routing podle druhu prace

| Druh prace | Model | Proc |
| --- | --- | --- |
| Architektura a kontrakty pred implementaci | **Fable 5.1** | Povinne. Navrh datoveho modelu, poradi kroku, dopad na goldeny. |
| Code review hotove zmeny | **Fable 5.1** | Povinne pred oznacenim za hotove. Nalezl realne chyby (chybejici transakce, prepis galerie, spatne razeni). |
| Sitovy a autoritativni kod (publish, API, migrace, auth) | **Opus 5**, review Fable 5.1 | Chyba se projevi az na produkci. Nikdy Haiku. |
| Rendering, sablony, CSS, admin UI | **Opus 5** | Hodne skrytych vazeb mezi tematy, runtime CSS a goldeny. |
| Pruzkum repozitare ("kde je co") | **subagent** (Explore / Sonnet) | Cte do vlastniho kontextu, zpet posila jen zaver. V hlavnim kontextu stoji nasobne vic. |
| Mechanicke git prikazy (`add`, `commit`, `push`, spousteni testu, aplikace hotoveho patche) | **Sonnet 5** | Nevyzaduje uvahu, jen presnost. |
| Zprava commitu, popis zmeny | model, ktery zmenu delal | Ma vysvetlit *proc*, ne jen *co* - to vyzaduje kontext rozhodnuti z prace, ktery cerstvy model nema. Nove nacteni kontextu stoji vic, nez se timto usetri. |
| Psani dokumentace/komentaru **podle hotove osnovy** nebo sablony | **Sonnet 5**, mechanicke prepisy **Haiku 4.5** | Format bez rizika regrese. |
| Psani dokumentace/komentaru **od nuly** (co je podstatne, jaka je architektura) | model, ktery kod psal | Vyber podstatneho je uvaha, ne prepis. |
| Oprava cestiny/gramatiky ve velkem objemu textu | **Sonnet 5** s kontrolou, ne Haiku bez dozoru | Male modely chybuji v ohybani; tenhle projekt navic zamerne mixuje text s/bez diakritiky. |

Tvrda pravidla: Fable 5 (bez .1) je zakazany. Po dvou selhanich eskaluj o uroven vys.
Nikdy Haiku na sitovy nebo autoritativni kod.

## Rozpocet a merici baseline

Meri se skillem `token-usage` (`quick` / `detail`, okno jednoho ukolu pres `turns`).

Namereno 20. 9. 2026 pred zavedenim uspornych pravidel:

| Metrika | Hodnota |
| --- | --- |
| Cely projekt | 1,478 mld. tokenu / 3 233 odpovedi |
| Prumerny kontext na jedno volani modelu | ~457 000 tokenu |
| Stredne velky ukol (razeni fotek, videi, blogu a praci) | 60,5 M tokenu / 159 odpovedi / 2 h 13 min |
| Rozpad toho ukolu | pruzkum kodu 27,5 %, uprava kodu 18,5 %, patch skripty 15,6 %, testy 12 %, git 6,5 % |
| Subagenti (architektura + review) | 3,3 M, tedy 5,5 % |

Cil pro srovnatelne velky ukol: **15-20 M tokenu** pri stejnem rozsahu kontroly (testy, goldeny,
review). Kdyz ukol tehle velikosti prekroci 30 M, neco je spatne - typicky prilis dlouha session
nebo hledani v kodu misto pouziti mapy v `ARCHITECTURE.md`.

## Jak se setri, aniz klesne kvalita

1. **Nova session na novy ukol.** Dve nejdelsi sessions spolykaly 86 % spotreby projektu, protoze
   ke kazdemu volani nastroje se prikladala cela dosavadni konverzace. Na strane uzivatele.
2. **Pruzkum delegovat.** Hledani v repu v hlavnim kontextu stalo 16,6 M; oba subagenti vcetne
   architektury a review stali dohromady 3,3 M.
3. **Min round-tripu.** Kazde volani nastroje plati cely kontext znovu. Slucovat prikazy
   (patch + kontrola syntaxe v jednom), plnou testovaci sadu pouzit na zaver, ne po kazdem kroku.
4. **Velke vystupy do souboru**, do kontextu jen shrnuti. Vypsany diff nebo full-page screenshot
   se plati znovu pri kazdem dalsim volani do konce session; screenshot delat jako vyrez.
5. **Necist znovu soubor, ktery jsem prave zapsal.**

Co se **neskrta**: testy, goldeny a review od Fable 5.1. Dohromady necelych 18 % objemu a
odhalily chyby, ktere by jinak sly na produkci.

## Routing na urovni faze, ne kroku

Model se preklopi jen na zacatku session, ne behem ni - delegovat jeden git commit nebo jednu
opravu preklepu na jineho subagenta je ztratove, protoze si musi znovu nacist kontext. Levnejsi
model tedy ma smysl az kdyz na nem bezi cela fazova session, typicky:

- **implementace** (navrh + kod + testy) na modelu podle radku vyse,
- **dodelavky** (uklid, dokumentace, commit, push) jako samostatna faze/session prepnuta na
  Sonnet 5 pres `/model sonnet`, kdyz uz je hlavni prace hotova.

Globalni vychozi model a effort (`~/.claude/settings.json`) je na uzivateli, tenhle soubor ho
nevynucuje - jen rika, na co eskalovat a na co ne, jakmile session bezi.
