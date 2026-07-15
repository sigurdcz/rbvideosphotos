# Screenshotovani webu ve VS Code Integrated Browseru

## Co bylo rozbite

VS Code Integrated Browser dokazal otevrit `http://127.0.0.1:8766/`, ale pri
predani obrazu agentovi padal nativni Electron `capturePage()` na
`UnknownVizError`. HTTP 200 proto nebyl dukaz funkcniho screenshotovani.
Predchozi nouzove snimky cele plochy nebo celeho VS Code se nesmeji pouzivat
jako vizualni dukaz webu.

## Provedena oprava hostitele

Opraven je hostitelsky soubor VS Code:

`C:\Program Files\Microsoft VS Code\4fe60c8b1c\resources\app\out\main.js`

Aktualni overeny SHA-256 upraveneho souboru:

`78BEF3D6D78022E7702822D4739C1A7C56B4EF93A2496E92EAB22ABA0E4A8C71`

Capture cesta nejprve ponecha pet nativnich pokusu `capturePage()`. Kdyz
vsechny skonci na `UnknownVizError`, zapise viditelnou chybu do logu a pouzije
CDP `Page.captureScreenshot` na stejnem BrowserView. Nevznika snimek plochy;
zdrojem je stale vykreslovaci proces otevrene webove stranky. Posledni
page-only JPEG se uklada do:

`%TEMP%\codex-integrated-browser-latest.jpg`

Oprava byla overena novym obrazem pouze stranky a logem
`Saved Integrated Browser screenshot for Codex`.

## Postup pro vizualni iterace v tomto projektu

1. Spustit projekt na `http://127.0.0.1:8766/` a overit, ze je otevreny ve
   VS Code Integrated Browseru.
2. Zapnout `Share with Agent`.
3. Pres Command Palette spustit `Browser: Toggle Device Emulation`.
4. Zadat presnou sirku, vysku a DPR z ciloveho screenshotu. Pro mobilni target
   je to `941 x 1672`, DPR `1`.
5. Pro presnou nativni sirku maximalizovat editorovou skupinu Browseru a
   docasne skryt bocni chat. Auto-scale maly editor fyzicky zmensuje capture a
   takovy obraz je pouze diagnosticky.
6. Pro cely dokument pouzit `Add to Chat` ->
   `Add Full Page Screenshot to Chat (Experimental)`. Tato polozka vyzaduje
   nastaveni `workbench.browser.experimentalUserTools.enabled: true`.
7. DevTools muze ulozit PNG nejprve jako soubor `*.tmp` v `Downloads`. Platny
   soubor ma PNG hlavicku `89 50 4E 47 0D 0A 1A 0A`; po overeni rozmeru se
   zkopiruje do rootu projektu s priponou `.png`.
8. Po kazde CSS iteraci se ulozi novy root artefakt:
   `visual-progress-mobile-00-YYYYMMDD-HHMMSS.png`,
   `visual-progress-mobile-01-YYYYMMDD-HHMMSS.png`, atd. Cilovy `preview-mobile-reviews.png`
   se nikdy neprepisuje.
9. Do `UKOL.md` se u kazde iterace zapise viewport, nazev screenshotu, skore,
   odecty a dalsi zmena.

Opakovatelny root skript pro kroky 3 az 8:

`powershell -ExecutionPolicy Bypass -File .\qa_integrated_browser_capture.ps1`

Bez parametru skript automaticky zvoli dalsi volne poradove cislo a prida
timestamp `YYYYMMDD-HHMMSS`. Po kazdem behu se uzivateli potvrdi presna cesta,
rozmery a cas ulozeni.

Skript po reloadu ceka pet sekund. Kratsi cekani muze zachytit nedokoncene
rendererove surfaces jako cerne bloky; takovy soubor se ulozi jako diagnostika,
ale neboduje se a stejny stav se zachyti znovu.

Skript umi znovu pouzit uz otevrene samostatne DevTools okno. V uzkem toolbaru
akceptuje `Hard Reload` stejne jako `Reload`; pokud je tlacitko DevTools schovane,
pouzije jeho inzerovany ekvivalent F12. DevTools prikazovou paletu otevira pres
`Customize and control DevTools` -> `Run command`, protoze aktivni Console prompt
muze zachytit klavesovou zkratku. Screenshot command je zavisly na hostitelskem
nastaveni `workbench.browser.experimentalUserTools.enabled`; pokud se po cistem
otevreni DevTools nezobrazi, capture musi viditelne selhat a vyzadat reload hostitele,
nikoli ulozit nahradni obraz.

## Kontroly platnosti

- Cas souboru musi byt novejsi nez reload nebo posledni uprava CSS.
- Rozmery a DPR musi odpovidat zamknutemu testu.
- Obraz musi obsahovat jen web, ne chrome VS Code ani celou plochu.
- Screenshot pri auto-scale `377x669`, `377x752` nebo podobne maly snimek se
  nesmi vydavat za nativni baseline 941x1672.
- Screenshot z jineho prohlizece nesmi potichu nahradit Integrated Browser.
- Pokud `agent.browsers.list()` vraci prazdny seznam, jde o nezavisly problem
  registrace `iab`; nema se zamaskovat tvrzenim, ze HTTP test je screenshot.

## Aktualni stav mobilniho QA

Autoritativni predlohy jsou verzovane jako `preview-mobile-reviews.png`
(`941 x 1672`) a `preview-tablet-reviews.png` (`1086 x 1448`). Lokalni
diagnosticke JPEGy a `visual-progress-mobile-*.png` jsou zamerne ignorovane
Gitem: slouzi k prubezne kontrole, ale nejsou zdrojem pravdy ani dukazem, ze
produkci odpovida prave commitnuty CSS stav.

Novy progress screenshot se uklada s timestampem podle kroku 8 a zustava v
rootu projektu pro okamzitou vizualni kontrolu uzivatelem. Commitne se jen
tehdy, kdyz je vyslovne vybran jako stabilni referencni artefakt a soucasne
odpovida commitnutemu kodu.
