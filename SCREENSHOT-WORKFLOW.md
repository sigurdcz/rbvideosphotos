# Screenshot workflow

Aktualni vizualni QA se provadi nad skutecnym lokalnim runtime v explicitne instalovanem Google Chrome. Stare screenshoty, snimek cele plochy ani obraz z jineho browseru se nesmi vydavat za cerstvy Chrome dukaz.

## Standardni capture

1. Spustit web na kanonickem `http://127.0.0.1:8766` prikazem z `qa-testing.md`.
2. Pro audit celeho verejneho webu spustit:

   ```powershell
   powershell -ExecutionPolicy Bypass -File .\scripts\qa_full_site_visual_audit.ps1
   ```

3. Skript vezme URL z aktualniho `sitemap.xml`, pouzije instalovany `chrome.exe`, proscrolluje stranku kvuli lazy-load obsahu a ulozi full-page PNG i `audit-data.json`.
4. Podezrelou route znovu zachytit izolovane pomoci `-RoutePattern`, protoze lokalni PHP development server muze pri soubeznych asset requestech jednorazove resetovat spojeni:

   ```powershell
   powershell -ExecutionPolicy Bypass -File .\scripts\qa_full_site_visual_audit.ps1 `
     -OutputDir storage/qa/visual-audit-recheck `
     -RoutePattern '^/nase-prace$' `
     -WaitMilliseconds 3000
   ```

5. `-MaxAttempts 5` povoli omezeny recapture pri nedodanem stylesheetu, nedokoncenem obrazku nebo abnormalni geometrii. Po vycerpani limitu skript viditelne selze.
6. URL ze sitemap poskytuje pouze cestu a query. Origin zustava vzdy z explicitniho `-BaseUrl`, aby audit omylem nepresel na jiny lokalni server.

### Izolovany homepage capture

Pro iteracni homepage QA pouzit `scripts/qa_chrome_cdp_capture.ps1`. Skript uzamkne DPR 1 a zadany viewport, proscrolluje lazy media, ceka na fonty a viditelne obrazky, opakovane meri full-page vysku a pri nestabilni vysce explicitne selze.

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\qa_chrome_cdp_capture.ps1 `
  -Url http://127.0.0.1:8766/ `
  -Output storage/qa/homepage-1920.png `
  -Width 1920 `
  -Height 1080 `
  -Port 9333
```

`Runtime.visualQa` obsahuje prvky mimo viewport, primy i zdedeny clipping, kontrolni plochy, podezrele prekryvy, fixed/sticky zakryti a geometrii klicovych homepage sekci. `LazyLoad.failedVisibleImages` musi byt prazdne a `HeightStable` musi byt `true`.

Full-page PNG je pouze celkovy dukaz poradi a kompozice. Kazda uzivatelem oznacena nebo rizikova oblast musi mit take samostatny 100% viewportovy detail pres `-ScrollSelector` a `-ScrolledOutput`, pripadne po realne interakci pres `-ClickedOutput`. Pozadovany detailni soubor se po behu explicitne overi; kombinace `-CaptureViewportOnly` s pozdejsim scroll capture se nepouziva, protoze viewport-only vetev konci driv.

`-OpenNavigation` podporuje obecny `.nav-toggle` i Golden Bash `.gb-menu`. Pokud ovladac nenajde nebo menu po kliku neni otevrene, capture skonci chybou; nevytvori tichy screenshot zavreneho stavu. Dalsi stav lze overit pres `-ScrollSelector`, `-ClickSelector` a `-ClickedOutput`.

Cookie summary nejprve zachytit jako skutecny fixed stav. Pro nasledny neruseny layout screenshot lze v cistem profilu pouzit `-ClickSelector '[data-cookie-reject-all]'`; v reportu musi byt tento stav vyznamove oznacen, nesmi se vydavat za vypnutou konfiguraci.

## Povinne kontroly

- Screenshot je novejsi nez posledni zmena kodu a obsahuje pouze web.
- Desktop ma viewport `1920 x 1080`, mobil `390 x 844`, DPR 1 a zoom 100 %.
- `scrollWidth` se rovna `clientWidth`; kazdy rozdil je horizontalni overflow.
- Neni prekryty text, tlacitko, formular ani panel; obsah neni orezany.
- Nejsou pritomne nevysvetlene velke prazdne plochy, cerne bloky, bile bloky ani sede media placeholdery.
- CSS/JS/media requesty a konzole jsou bez chyb.
- Izolovany recapture musi potvrdit kazdou vadu, ktera muze byt zpusobena chybejicim assetem. Pokud recapture projde, prvni selhani zustane v reportu jako problem spolehlivosti lokalniho runtime, nikoli jako potvrzena layoutova vada.
- `HeightStable` je `true`, vsechna viditelna deklarovana media jsou nactena a zadny zkraceny startovni timeout se nepouziva jako finalni dukaz.
- Obe strany kazde zmenene media-query hranice maji vlastni capture; k nim je reprezentativni desktop a mobil.
- Pokud novy uzivatelsky screenshot nebo anotace odporuje PASS, PASS i posledni skore se okamzite odvolaji a `UKOL.md` se znovu otevre.

## Vystup auditu

Screenshoty patri do `storage/qa/<audit>/`. Report musi u kazdeho nalezu uvest URL, zavaznost, full-page i detailni screenshot a zda byl nalez potvrzen opakovanym capture. Samotne HTTP 200, nulovy overflow, prazdny clipping seznam ani uspesne nacteni medii nenahrazuji vizualni kontrolu PNG v puvodnim meritku.
