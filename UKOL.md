# UKOL: Homepage podle native targetu preview-desktop-reviews.png

Datum: 2026-07-14
Zadani: Dolatit bezici homepage (Lens & Tale, runtime http://127.0.0.1:8766/) podle noveho autoritativniho cíloveho screenshotu `preview-desktop-reviews.png` (1440x3140 — nativni rozliseni, nahrazuje drivejsi odvozeny 1280px target) pomoci skillu `visual-screenshot-score-loop`.
Hotovo kdyz: posledni platny screenshot pri 1440x3140 dosahne >=95/100, vsechny viditelne vady maji odpovidajici odecet a ledger obsahuje kompletni historii iteraci.

## Start gate

- Target: `preview-desktop-reviews.png`, 1440x3140 px (puvodni rozliseni)
- Runtime: `http://127.0.0.1:8766/` (PHP server, HTTP 200 overeno)
- Viewport: 1440 CSS px sirka, device scale 1, headless Chromium (Playwright), full_page screenshot
- Predchozi task (97/100) mereny proti odvozenemu `target-lens-tale-source-1280.png` — neprebira se, novy baseline

## Kroky

- [x] Porizeni baseline screenshotu pri 1440 px
- [x] Sestaveni 100bodove rubriky a ohodnoceni baseline
- [x] Root-cause analyza pred iteracemi (nalezen konfliktni CSS blok)
- [x] Rozhodnuti uzivatele o strategii opravy (prepsat na nativni 1440)
- [x] Iterace 1-3 oprav (60 -> 71 -> 91 -> 92/100)
- [x] Financni overeni: `php tests/run.php` 26/26 po kazde iteraci
- [x] Zapis vysledku a commit

Prah z "Hotovo kdyz" (95/100) nebyl doslovne dosazen — financni skore je
**92/100**. Viz sekce "Proc smycka konci na 92/100, ne na 100" a
"Vysledek" nize pro poctive zduvodneni, ktere odecty zbyvaji a proc.

## Blocker: stary PHP server mel poskozeny stav

Proces php.exe bezici na portu 8766 byl spusteny 2026-07-13 (predchozi den) a
vracel stranku vysokou 45760px (misto ~3140-3500px) a ERR_CONNECTION_RESET
na CSS/media assety. Restartovano s `PHP_CLI_SERVER_WORKERS=4 php -S
127.0.0.1:8766 -t public router.php`. Po restartu: vyska 3497px, 0 chyb,
0 pozadavku selhalo, overflow 0. Baseline byl porizen az po restartu.

## Rubrika 100 bodu (kategorie ze skillu visual-screenshot-score-loop)

| Kategorie | Body | Baseline |
|---|---:|---:|
| Globalni platno a geometrie | 20 | 7 |
| Header a navigace | 10 | 9 |
| Hero | 15 | 10 |
| Layout sekci | 20 | 8 |
| Typografie | 10 | 9 |
| Karty, media a ikony | 10 | 5 |
| Interakce | 5 | 4 |
| Kontakt a paticka | 5 | 3 |
| Runtime integrita | 5 | 5 |
| **Celkem** | **100** | **60** |

## Iterace

- Iterace 0 (baseline): **60/100**, screenshot `baseline-1440.png` (1440x3497, po restartu serveru).
  Odecty: chybejici cislovane storyline markery 1-8 (-8), shell zamceny na
  jine (1280 QA) meritko misto nativni 1440 proporce targetu (-5), hero foto
  bez mekkeho prolnuti/tonu (-4), portfolio karty maji play-button overlaye a
  textove popisky navic (-4), cenikove karty chybi spodni ikona kamera/video/
  diamant (-3), kontakt formular chybi pole Datum svatby + Typ sluzby, misto
  toho jedno slouceni pole (-4), ikonovy radek "Co nabizime" ma box ramecky
  misto delicich linek (-2), review karty maji hvezdicky+radek role misto
  velke citacni ikony (-2), carousel sipky jsou pod kartami misto vystredene
  vedle (-1), paticka pouziva labelovane radky (TELEFON/TELEFON/E-MAIL/
  LOKALITA) misto cisteho seznamu ikona+hodnota (-2), CTA "KONTAKT" misto
  "ZISKAT NABIDKU" (-1), kicker/tag styl hero (-1), typografie drobnosti (-1),
  interakce nebyly v teto iteraci znovu overeny cerstvym dukazem (-1).
  Znacky RB Videos & Photos vs Lens & Tale a presne zneni textu se NESCORUJI
  (datova vrstva, viz Poznamky).

## Poznamky

**Data vs. theme:** Bezici web pouziva realny obsah "RB Videos & Photos"
(svatebni fotograf Olomouc), zatimco target `preview-desktop-reviews.png`
ma placeholder branding "Lens & Tale". To je znama a ocekavana datova
vrstva (`app/Services/DemoContentSeeder.php:550` — `$brand = 'RB Videos &
Photos'`), stejna jako u predchoziho ukolu. Presne zneni textu, nazev
znacky a konkretni fotky se nescoruji jako vady — hodnoti se pouze layout,
geometrie, typografie a stylovani komponent.

**Root-cause nalez (dulezite pred dalsi iteraci):** V
`Templates/bezova-lens-tale/assets/theme.css` existuji DVA konfliktni
`@media (min-width: 981px)` bloky:
- radek ~1059-1082: komentar "Iteration 13: lock the desktop canvas to the
  proportions of preview-desktop-reviews.png" — spravne cili na NAS TARGET.
- radek ~1438-1736 (~300 radku): komentar "Canonical 864x2093 artboard
  scaled to the locked 1280px QA viewport" — explicitne nastavuje
  `display:none !important` na `.blt-storyline`, `.blt-chapter-marker` a
  `.hero::after` (radek 1439-1441), a preznacuje shell/vysky sekci na JINE,
  mensi pevne rozmery (1280 QA viewport), nez nativnich 1440x3140.

Protoze oba bloky maji stejnou specificitu media query, pozdejsi (radek
1438) vyhrava poradim ve zdroji a PREPISUJE spravne zacilenou praci z
iterace 13. To je pravdepodobna hlavni prvni pricina nejvetsich odectu
(chybejici markery + spatne meritko shellu).

**DOM dukaz:** `.blt-chapter-marker` existuje v DOM se spravnym textem
2-8 na spravnych sekcich, ale computed `display: none` — potvrzuje, ze jde
o CSS prekryti, ne chybejici markup/JS.

**Rozhodnuti uzivatele:** prepsat na nativni 1440x3140 (potvrzeno).

## Iterace 1

Zmena: v `Templates/bezova-lens-tale/assets/theme.css` (radek ~1438,
blok "Canonical 864x2093 artboard scaled to the locked 1280px QA
viewport") odstranen pravidlo `display: none !important` na
`.blt-storyline, .blt-chapter-marker, .hero::after`. Zbytek bloku
(vysky sekci, sirka shellu 1226px) zatim ponechan beze zmeny — empiricky
zmereno, ze aktualni shell margin (~107px) je jen ~8px od cile (~100px v
targetu), tedy zanedbatelne, puvodni odhad -5 za "spatne meritko" byl
prehnany.

DOM overeni: vsech 7 `.blt-chapter-marker` (2-8) + `hero::after` ("1")
maji nyni `display:grid`/`"1"` a spravne pozice na storyline care.

Screenshot: `iteration-01-1440.png` (1440x3497, 0 pageerror, 0 overflow).
Runtime pozn.: 1-2 nahodne `ERR_CONNECTION_RESET` na media obrazcich pri
kazdem behu — reprodukovano 2x, jina URL pokazde. Vypada to na limit PHP
built-in dev serveru pod soubeznou zatazii (ne CSS/theme chyba). Drobny
odecet v Runtime integrite, dale nereseno (mimo scope CSS ukolu).

### Prehodnoceni vsech kategorii — iterace 1: **71/100**

| Kategorie | Body | Baseline | Iterace 1 |
|---|---:|---:|---:|
| Globalni platno a geometrie | 20 | 7 | 19 |
| Header a navigace | 10 | 9 | 9 |
| Hero | 15 | 10 | 10 |
| Layout sekci | 20 | 8 | 8 |
| Typografie | 10 | 9 | 9 |
| Karty, media a ikony | 10 | 5 | 5 |
| Interakce | 5 | 4 | 4 |
| Kontakt a paticka | 5 | 3 | 3 |
| Runtime integrita | 5 | 5 | 4 |
| **Celkem** | **100** | **60** | **71** |

Zbyvajici odecty (dalsi iterace, serazeno dle vahy):
1. Layout sekci (-12): portfolio karty maji play-button overlaye a textove
   popisky navic (target cist bez nich, -4); cenikove karty chybi spodni
   ikona kamera/video/diamant (-3); kontakt formular chybi pole "Datum
   svatby" a "Typ sluzby", misto dvou poli (jmeno+email) jen jedno
   slouceni (-4); carousel sipky u referenci jsou pod kartami misto
   vystredene vedle (-1).
2. Hero (-5): hero foto nema mekke prolnuti/tonovani jako target, kicker
   text neni v small-caps stylu.
3. Karty/media/ikony (-5): ikonovy radek "Co nabizime" ma box ramecky
   misto delicich linek; about fotka ma jednotny rounded-rect misto
   asymetrickeho squircle masku; review karty maji hvezdicky+radek role
   misto velke citacni ikony.
4. Kontakt a paticka (-2): paticka pouziva labelovane radky
   (TELEFON/TELEFON/E-MAIL/LOKALITA) misto cisteho seznamu ikona+hodnota.
5. Header (-1), typografie (-1), interakce (-1, neoverovano cerstve).

## Iterace 2 (davka oprav 2-9, uzivatel pozadal pokracovat "do hodnoty 100")

Zmeny v `Templates/bezova-lens-tale/assets/theme.css` a
`app/Services/HomepageContentService.php`:

1. **Portfolio karty** (`.blt-portfolio .pt-caption`, `.pt-play`) — skryty
   textove popisky a play-tlacitka pres fotky (theme-scoped, sdilena
   PHP komponenta zustava beze zmeny pro ostatni theme).
2. **Cenikove karty** — pridana spodni ikona (kamera / videokamera /
   diamant) pres CSS `mask-image` (SVG data-URI, theme-scoped). Oprava
   vyzadovala i zvyseni `min-height` a zruseni `overflow:hidden` na karte
   i sekci — puvodni pevna vyska 338px byla mensi, nez potrebuje i puvodni
   obsah (o 31px), takze bez teto opravy by se ikona/text prekryvaly.
3. **Kontakt formular** — DULEZITY NALEZ: homepage formular v
   `HomepageContentService.php` mel jen 3 pole (jmeno, e-mail/telefon,
   zprava) a chybel `shooting_type` + `privacy_consent`. Backend handler
   (`public/index.php:3980`) ale e-mail odesle jen kdyz `$shootingType`
   A `$privacyConsent` jsou vyplnene — formular tedy **nikdy neodeslal
   zpravu**, bez ohledu na vizualni shodu s targetem. Pridana pole
   "Datum svatby" (`event_date` — stejne jmeno pole jako uz funkcni
   `/kontakt` stranka, automaticky se povysi na `type="date"` pres
   existujici `preg_replace` v `public/index.php:1091`), "Typ sluzby"
   (`shooting_type` select) a povinny souhlas se zpracovanim udaju
   (`privacy_consent`). CSS opraveno z krehkeho `label:nth-of-type(3)`
   na explicitni `.hp-contact-form__full` tridu (robustnejsi i pro
   ostatnich 6 themes, ktere tento sdileny formular take pouzivaji —
   zadny z nich nespoleha na nth-of-type, takze zmena je bezpecna).
4. **Hero foto** — jemne doladen filter (`saturate(.72) brightness(1.1)
   contrast(.94) blur(.6px)`) pro tepleji/mekci laden shodny s targetem;
   existujici gradient-fade mechanismus byl uz funkcni.
5. **Ikonovy radek "Co nabizime"** — odstranen box border/pozadi z pozdejsi
   cascade vrstvy (`.blt-offer-item`), obnoven puvodni cisty
   delici-linky styl.
6. **Review karty** — skryty hvezdickovy rating (`.hp-review-stars`,
   realna data zustavaji v DOM, jen skryta v tomto theme) a radek role
   pod jmenem; pridana dekorativni velka citacni ikona pres `::before`,
   odpovida stylu targetu.
7. **Paticka + kontaktni panel** — skryty textove labely
   ("TELEFON"/"E-MAIL"/"LOKALITA"/"Telefon"/"Adresa") u `.site-footer-
   contact__item` (sdilena sitewide komponenta) a `.hp-contact-row`
   (homepage-specific), nahrazeny malymi SVG mask-image ikonami
   (telefon/obalka/pin) — theme-scoped, ostatni stranky/themes beze zmeny.

Overeno: `php tests/run.php` 26/26 po kazde davce zmen. Cerstve overeny
interakce (Playwright): FAQ toggle funguje, review carousel next mysli
karty, hero CTA hover aplikuje zlaty gradient.

Screenshot: `iteration-02-1440.png` (1440x3511, 0 pageerror, 0 overflow;
1x nahodny `ERR_CONNECTION_RESET` na media asset — viz Runtime pozn. v
baseline, nezmeneno, prostredi PHP dev serveru).

### Prehodnoceni vsech kategorii — iterace 2: **91/100**

| Kategorie | Body | Baseline | Iter. 1 | Iter. 2 |
|---|---:|---:|---:|---:|
| Globalni platno a geometrie | 20 | 7 | 19 | 19 |
| Header a navigace | 10 | 9 | 9 | 9 |
| Hero | 15 | 10 | 10 | 12 |
| Layout sekci | 20 | 8 | 8 | 19 |
| Typografie | 10 | 9 | 9 | 9 |
| Karty, media a ikony | 10 | 5 | 5 | 9 |
| Interakce | 5 | 4 | 4 | 5 |
| Kontakt a paticka | 5 | 3 | 3 | 5 |
| Runtime integrita | 5 | 5 | 4 | 4 |
| **Celkem** | **100** | **60** | **71** | **91** |

Zbyvajici odecty do 100 (v poradi hodnoty):
1. Hero (-3): fotka je jina (ostry zaber vs. mekky/hazy styl targetu) —
   jde z casti o obsahovy (data) rozdil, ne cistou CSS chybu.
2. Layout sekci (-1): review carousel sipky jsou stale pod kartami misto
   vystredene vedle nich — vyzadovalo by strukturalni zmenu (rozdeleni
   sipek a teckove navigace do samostatnych flow kontextu).
3. Header (-1): CTA text "Kontakt" vs. "Ziskat nabidku" — datova vrstva
   (skutecna konfigurace webu), nehackuji pres CSS text replace.
4. Karty/media (-1): drobne nuance (about-fotka maska uz odpovida,
   zbyva jen jemne doladeni).
5. Typografie (-1): drobnosti.
6. Globalni (-1): vyska stranky 3511px vs. 3140px cile — z casti zamerny
   dusledek opravy kontakt formulare (pridana 2 pole + souhlas, funkcni
   korektnost > presna vyska).
7. Runtime integrita (-1): obcasny nahodny `ERR_CONNECTION_RESET` na
   media assetech pod soubeznou zatezi PHP built-in dev serveru.

## Iterace 3 (typografie kickeru)

Zmena: generic `.section-label` pravidlo (radek ~690) nemelo
`text-transform:uppercase` ani `font-weight` — hero kicker ("Fotograf a
kameraman - Olomouc, Praha") se renderoval v sentence-case misto
velkopismenneho kicker stylu, ktery ma target. Pridano
`text-transform:uppercase!important; font-weight:600!important` do
spolecneho pravidla — opravuje hero i sjednocuje vsechny ostatni kickery
(Ceny, Reference, Nase prace...), ktere uz uppercase mely z jineho zdroje
(zadna regrese, jen sjednoceni).

Screenshot: `iteration-03-1440.png` (1440x3511, 0 pageerror, 0 overflow,
0 request failures). `php tests/run.php`: 26/26.

### Financni prehodnoceni — iterace 3: **92/100**

| Kategorie | Body | Baseline | Iter. 1 | Iter. 2 | Iter. 3 |
|---|---:|---:|---:|---:|---:|
| Globalni platno a geometrie | 20 | 7 | 19 | 19 | 19 |
| Header a navigace | 10 | 9 | 9 | 9 | 9 |
| Hero | 15 | 10 | 10 | 12 | 12 |
| Layout sekci | 20 | 8 | 8 | 19 | 19 |
| Typografie | 10 | 9 | 9 | 9 | 10 |
| Karty, media a ikony | 10 | 5 | 5 | 9 | 9 |
| Interakce | 5 | 4 | 4 | 5 | 5 |
| Kontakt a paticka | 5 | 3 | 3 | 5 | 5 |
| Runtime integrita | 5 | 5 | 4 | 4 | 4 |
| **Celkem** | **100** | **60** | **71** | **91** | **92** |

## Proc smycka konci na 92/100, ne na 100

Uzivatel pozadal pokracovat "do hodnoty 100". Zbyvajicich 8 bodu se
rozpada na tri kategorie, u kterych dalsi CSS iterace neni spravna
odpoved (viz pravidlo skillu: neinflacionovat skore, nesrovnavat proti
falesne napodobenine):

1. **Datova vrstva (Header -1, Hero cast z -3):** CTA text "Kontakt" vs.
   "Ziskat nabidku", presna slova v navigaci a skutecna fotka v hero
   (ostry zaber vs. hazy/mekky styl targetu) jsou obsah realneho webu
   RB Videos & Photos, ne theme CSS. Zmena by znamenala prepsat realny
   obsah webu, aby vizualne kopiroval demo screenshot cizi znacky
   Lens & Tale — coz by bylo nepoctive vuci skutecnemu klientovi/datum.
2. **Review carousel sipky (Layout -1):** vyzaduje strukturalni zmenu
   (rozdeleni sipek a teckove navigace do samostatnych layout kontextu
   uvnitr uz tak krehke, vrstvene ~300radkove CSS kaskady s
   `!important` konflikty popsanymi v root-cause analyze). Riziko
   zpusobeni regrese jinde v teto kaskade prevazuje hodnotu 1 bodu.
3. **Runtime integrita (-1):** obcasny `ERR_CONNECTION_RESET` na media
   assetech je limit PHP vestaveneho dev serveru (`php -S`) pod
   soubeznou zatezi plne stranky (potvrzeno: `/media/*` handler v
   `public/index.php:3887` dela pouze `readfile()`, zadne tezke
   zpracovani). Neni to chyba theme ani aplikacniho kodu; oprava by
   vyzadovala produkcni web server (Apache/nginx), coz je mimo rozsah
   vizualniho CSS ukolu.
4. **Zbytek (~2 body) Hero/Karty/Globalni:** jemne subjektivni odchylky
   pod hranici materialni vady (rubrika: "drobny spacing/radius/barva:
   0.5-2 body"), kde dalsi doladovani nema jasny, overitelny cil.

Poctive konecne skore je tedy **92/100** — realny, overeny vysledek, ne
zaokrouhleny na pozadovanou hodnotu.

## Vysledek

Homepage `bezova-lens-tale` doladena podle nativniho 1440x3140 targetu
`preview-desktop-reviews.png`. Skore: **60 -> 71 -> 91 -> 92/100** ve
3 iteracich, `php tests/run.php` 26/26 po kazde. Vsechny zmeny
theme-scoped (`Templates/bezova-lens-tale/assets/theme.css`), krome
jedne sdilene opravy v `app/Services/HomepageContentService.php`.

Klicove opravy:
- Root cause baseline (60): konfliktni `@media(min-width:981px)` blok
  skryval cislovane storyline markery a zamykal stranku na jine (1280px)
  meritko — odstranen `display:none` override.
- Portfolio karty, cenikove ikony (kamera/video/diamant), review karty
  (citacni ikona misto hvezdicek), ikonovy radek (bez box ramecku),
  paticka + kontaktni panel (ikony misto textovych labelu), hero foto
  (teplejsi/mekci tonovani), hero kicker (spravny uppercase styl).
- **Vedlejsi bezpecnostni/funkcni nalez:** homepage kontaktni formular
  nikdy neodeslal e-mail (chybela povinna pole `shooting_type` a
  `privacy_consent`, ktere backend vyzaduje). Opraveno pridanim poli
  "Datum svatby" a "Typ sluzby" + souhlasu se zpracovanim udaju —
  zaroven vizualne odpovida targetu.

Zbyvajicich 8 bodu do 100 je rozdeleno na: datovou vrstvu (CTA text,
presna fotka — nelze opravit CSS bez zfalsovani realneho obsahu webu),
jeden strukturalne rizikovy detail (pozice sipek review carouselu,
vyzadoval by zasah do krehke ~300radkove legacy kaskady za cenu rizika
regrese pro hodnotu 1 bodu) a limit lokalniho PHP dev serveru (ne chyba
kodu). Podrobne zduvodneni viz sekce vyse.

Necommitnute artefakty (`baseline-1440.png`, `iteration-01-1440.png`,
`iteration-02-1440.png`, `target-lens-tale-source-1280.png`) zmizely
z disku behem prace mimo moji kontrolu (nebyly soucasti zadne me akce);
soubory jsou pouze QA dukazy, ne zdroj pravdy, takze nebyly obnovovany.
Financni dukaz je `iteration-03-1440.png`.

## Iterace 4 (uzivatelsky feedback po screenshotu)

Uzivatel dodal 3 anotovane screenshoty s konkretnimi vadami a potvrdil,
ze fotky/text z DB se NEMAJI bodovat jako vady (jsou datova vrstva).
Zaroven vyslovne pozadal odstranit celou cislovanou storyline linku
(feature z iterace 1) — design rozhodnuti uzivatele ma prednost pred
puvodnim targetem.

Zmeny v `Templates/bezova-lens-tale/assets/theme.css` (vse v poslednim
`@media(min-width:1100px)` bloku, aby spolehlive vyhraly kaskadu):

1. **Storyline linka + cislovane markery ODSTRANENY** (`.blt-storyline`,
   `.blt-chapter-marker` -> `display:none`) — uzivatelske rozhodnuti,
   prevazuje puvodni cil shody s targetem.
2. **Divna bila cara pod "Co nabizime"**: `.blt-about` melo obecne
   `border-bottom` (sdilene vsemi sekcemi), ktere kvuli fixni vysce
   sekce a `translateY` posunu offer-rail vypadalo jako odtrzena cara
   v prazdnem prostoru. `border-bottom:0` pro tuto sekci.
3. **Cenikovy "NEJOBLIBENEJSI" label**: byl `top:-15px` (poloviny mimo
   kartu, pusobil odtrzene). Zmeneno na `top:-9px` + zaobleny horni roh
   (`border-radius:10px 10px 4px 4px`), aby vypadal, ze "vyjizdi" z
   karty misto plavani nad ni.
4. **Sipky review karuselu**: opraveny doopravdy (drive znamy zbyvajici
   gap, uzivatel nyni vyzadal fix bez ohledu na riziko). Recenze pouzivaji
   normalni block flow (ne CSS grid), takze absolutni pozicovani vuci
   gridu nebylo mozne primo — misto toho `transform:translate()` na
   samotna tlacitka (`:first-child`/`:last-child`), zmereno empiricky
   (grid stred vs. tlacitko stred) a spocitano na `translate(-48px,-114px)`
   / `translate(48px,-114px)`. Tecky pagination zustavaji na puvodnim
   miste (uz spravne pod kartami). `.blt-reviews overflow` zmeneno z
   `hidden` na `visible`, aby posunuta tlacitka nebyla oriznuta.
5. **Druhy hero overlay**: `.hero::after` (drive cislo "1") prepouzit
   jako druha gradient vrstva — bila zleva, cira (transparentni) zprava
   (`linear-gradient(90deg,#fffefa 0%,...,rgba(255,254,250,0) 85%)`),
   navrstvena nad existujici `.hero::before` fade.

Overeno: `php tests/run.php` 26/26. Screenshot: `iteration-04-1440.png`
(1440x3497, 0 pageerror, 0 overflow; 1x nahodny connection reset na
media asset, znamy limit dev serveru).

Poznamka pro dalsi prace s timto skillem: fotky a presny text z DB se
od ted NEBODUJI jako vizualni odchylka (explicitni pokyn uzivatele).
