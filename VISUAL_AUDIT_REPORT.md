# Vizualni audit lokalniho webu

## VA-05: Rozmazany hero obraz detailu galerie

- Problem: `PortfolioDetailData` posilal do velkeho hero `thumb_path` o maximalni sirce 300 px. Na detailu Aneta a Martin byl obraz roztazen na priblizne 930 px a viditelne pixelizoval.
- Screenshot nalezu: uzivatelem dodany snimek `localhost:8000/aneta-a-martin-hrazdilovi`.
- Oprava: hero je skutecny prioritni obraz s profilem `portfolio-hero`; runtime srcset obsahuje 480px/23 140 B, 720px/38 530 B a 1280px/80 898 B WebP. Desktop zvoli 1280px variantu, mobil mensi odpovidajici variantu. Fade a `object-fit:cover` zustaly zachovane.
- Overeni: runtime HTML ma `loading=eager`, `fetchpriority=high`, `sizes="(max-width: 700px) calc(100vw - 32px), 62vw"` a vsechny tri fyzicke varianty. Funkcni sada prosla 139/139. Cerstvy Chrome screenshot nevznikl, protoze CDP endpoint na cistem portu 9531 nebyl dostupny; starsi obraz nebyl pouzit jako nahrada.

## VA-04: Jedna akce byla na homepage zobrazena vicekrat

- Problem: Golden Bash JavaScript klonoval kartu databazove galerie pro jeji dalsi fotografie, dokud mozaika nemela osm dlazdic. Lenka a Lukas proto pusobili jako tri samostatne galerie, ackoli slo o jednu akci.
- Screenshot nalezu: uzivatelem dodany homepage vyrez se tremi kartami `Lenka a Lukas`; lokalni baseline se 12. 8. 2026 nepodarilo znovu poridit, protoze Chrome CDP bootstrap na dvou cistych portech timeoutoval a in-app Browser vratil `No browser is available`.
- Oprava: klient uz karty neklonuje. Homepage renderuje pet serverovych galerii prave jednou a jejich dalsi fotografie zustavaji uvnitr lightboxu. Desktopova mrizka ma dve plne rady bez prazdne treti rady.
- Overeni kodu: `php tests/run.php` dokoncil 139/139 funkcnich testu; 26 drive znamych neprijatych golden-master rozdilu zustava mimo tento ukol. Cerstvy finalni screenshot je nadale blokovan nedostupnym browser runtime a neni nahrazen starsim obrazkem.

Datum capture: 2026-08-12 (Europe/Prague)
Runtime: `http://localhost:8000`
Browser: instalovany Google Chrome, headless CDP
Viewport: `1920 x 1080` CSS px, DPR 1
Rozsah: vsech 40 URL z aktualniho `sitemap.xml`

## Shrnuti

- 40/40 sitemap URL ma cerstvy full-page desktopovy screenshot.
- Potvrzeny jsou 2 opakovatelne skupiny vizualnich vad: prekryv na strance podminek a prazdne sede media sloty.
- U 5 stranek prvni pruchod zachytil zavazne nenacteni stylu nebo rozpad geometrie. Izolovany recapture vsech 5 stranek byl cisty, proto jde o nestabilitu nacitani assetu lokalniho PHP serveru, ne o trvaly CSS layout.
- Zadne dalsi opakovatelne prekryvy tlacitek, panelu nebo textu nebyly na desktopovych snimcich nalezeny.

## Nalezy

### VA-01: Navigacni panel prekryva nadpis a uvodni text podminek

- Zavaznost: vysoka
- URL: `/podminky-uziti`
- Problem: plovouci panel `Rychla orientace` lezi pres velky nadpis `Co plati pri pouzivani tohoto webu` a pres prvni radky uvodniho odstavce. Text je castecne necitelny a vizualni hierarchie se rozpada.
- Screenshot: [desktop-13-podminky-uziti.png](storage/qa/visual-audit-2026-08-11/desktop-13-podminky-uziti.png)
- Automaticky signal: skutecny geometricky prekryv `p.section-intro` s odkazem v panelu kolem Y 775.
- Opraveny screenshot: [desktop-01-podminky-uziti.png](storage/qa/finish-visual-audit/terms-accepted/desktop-01-podminky-uziti.png)
- Stav: opraveno. Panel je zarovnany s prvni pravni kartou v levem sloupci, nadpis ani intro neprekryva a nevytvari novou prazdnou radku. Mobilni panel zustava skryty.

### VA-02: Prazdne sede media bloky misto obrazku — překlasifikováno pod VA-03

- Zavaznost: stredni
- URL: `/blog`, `/o-nas` a vsechny zachycene blogove detaily `/reklamni-foto-video-pro-firmy` az `/svatba-arboretum-bila-lhota-helena-tomas`
- Problem: listing i detaily obsahuji svetle sede obdelniky bez obrazku, popisku nebo vysvetleni. Na detailu jsou typicky dva az tri prazdne sloty tesne pred odkazem zpet; na `/o-nas` jsou tri prazdne karty v casti pripadovych studii. Pusobi jako nedokonceny skeleton nebo chybejici media.
- Screenshoty: [desktop-06-blog.png](storage/qa/visual-audit-2026-08-11/desktop-06-blog.png), [desktop-12-o-nas.png](storage/qa/visual-audit-2026-08-11/desktop-12-o-nas.png), [desktop-15-reklamni-foto-video-pro-firmy.png](storage/qa/visual-audit-2026-08-11/desktop-15-reklamni-foto-video-pro-firmy.png), [desktop-20-svatba-ruzove-udoli-ivanka-lubomir.png](storage/qa/visual-audit-2026-08-11/desktop-20-svatba-ruzove-udoli-ivanka-lubomir.png), [desktop-30-svatba-penzion-fojtstvi-nikola-jirka.png](storage/qa/visual-audit-2026-08-11/desktop-30-svatba-penzion-fojtstvi-nikola-jirka.png)
- Cerstve screenshoty: [blog](storage/qa/finish-visual-audit/final-desktop-2/desktop-01-blog.png), [O nas](storage/qa/finish-visual-audit/final-desktop-2/desktop-02-o-nas.png), [detail clanku](storage/qa/finish-visual-audit/final-desktop-2/desktop-04-reklamni-foto-video-pro-firmy.png)
- Stav: nejde o chybejici obsah. Aktualni HTML ma 17/17 blogovych thumbnailu, 3/3 O nas media a 2/2 obrazky kontrolovane galerie. Fail-closed recapture zachytil konkretni nedokoncene WebP requesty a po uspesnem opakovani jsou vsechna media viditelna. Nalez je projev VA-03; zadna genericka media ani prazdne nahrady nebyly pridany.

### VA-03: Nestabilni nacteni CSS/assetu pri plosnem pruchodu

- Zavaznost: vysoka pro QA spolehlivost, stredni pro bezneho navstevnika
- Problem: prvni sekvencni pruchod zachytil pet stranek bez kompletniho theme CSS nebo s extremne rozpadlou vyskou. Projevy zahrnovaly obri cerne ikony, 28 878px vysokou stranku, 101 539px portfolio tvorene tenkymi pruhy a dva prakticky nestylovane detaily s obrovskou bilou plochou.
- Puvodni screenshoty: [desktop-03-reklamni-foto-video.png](storage/qa/visual-audit-2026-08-11/desktop-03-reklamni-foto-video.png), [desktop-11-nase-prace.png](storage/qa/visual-audit-2026-08-11/desktop-11-nase-prace.png), [desktop-23-svatba-u-robinsona-martina-jaroslav.png](storage/qa/visual-audit-2026-08-11/desktop-23-svatba-u-robinsona-martina-jaroslav.png), [desktop-31-svatba-arboretum-bila-lhota-helena-tomas.png](storage/qa/visual-audit-2026-08-11/desktop-31-svatba-arboretum-bila-lhota-helena-tomas.png), [desktop-32-kamila-a-matej-duriskovi.png](storage/qa/visual-audit-2026-08-11/desktop-32-kamila-a-matej-duriskovi.png)
- Cisty recapture: [reklamni-foto-video](storage/qa/visual-audit-2026-08-11/recapture/desktop-01-reklamni-foto-video.png), [nase-prace](storage/qa/visual-audit-2026-08-11/recapture/desktop-02-nase-prace.png), [Martina a Jaroslav](storage/qa/visual-audit-2026-08-11/recapture/desktop-03-svatba-u-robinsona-martina-jaroslav.png), [Arboretum Bila Lhota](storage/qa/visual-audit-2026-08-11/recapture/desktop-04-svatba-arboretum-bila-lhota-helena-tomas.png), [Kamila a Matej](storage/qa/visual-audit-2026-08-11/recapture/desktop-05-kamila-a-matej-duriskovi.png)
- Stav: opraveno v QA pipeline. Skript zachovava explicitni runtime origin, ceka na deklarovane obrazky, overuje Golden Bash CSS promennou, odmita abnormalni vysku a zkusi stranku nejvyse `MaxAttempts` krat. Pri finalnim behu `/blog` selhal na dvou konkretnich nedokoncenych WebP requestech a prosel az tretim pokusem; po vycerpani limitu skript skonci viditelnou chybou.

### VA-04: Prekryvajici se radky homepage hero nadpisu — opraveno

- Zavaznost pred opravou: vysoka
- URL: `/`
- Problem: dekorativni nadpis `Zachytíme / váš příběh / navždy.` mel pri sirce 1512 px font 95,256 px, ale line-height pouze 78,110 px. Glyph boxy byly vysoke 106 px, takze se sousedni radky geometricky prekryvaly asi o 28 px.
- Baseline: [desktop-01-home.png](storage/qa/hero-title-overlap/baseline-measured/desktop-01-home.png)
- Opraveny screenshot: [1512 × 872](storage/qa/hero-title-overlap/iteration-02-1512x872/desktop-01-home.png), [1920 × 1080](storage/qa/hero-title-overlap/acceptance-1920x1080/desktop-01-home.png), [390 × 844](storage/qa/hero-title-overlap/acceptance-390x844/desktop-01-home.png)
- Stav: opraveno. Font je omezen na 82 px a line-height je 1,23; vsechny tri viewporty maji mezi glyph boxy kladnou mezeru, CTA zustava viditelne a horizontalni overflow je nulovy.

### VA-05: Mobilni bile pasy a necitelny hero obsah — opraveno

- Zavaznost pred opravou: vysoka
- URL: `/`, mobil 390 × 844
- Problem: `padding-top:64px` na shellu vytvarel prazdny pas nad hlavickou. Konflikt 760px a 380px mobilnich hero pravidel nechaval dalsi bilou plochu pred obrazkem, tmave texty bez overlaye lezely pres fotografii a story titulek byl orezany hranou sekce.
- Baseline: [baseline-390x844.png](storage/qa/mobile-hero-whitespace/baseline-390x844.png)
- Opraveny screenshot: [iteration-01-390x844.png](storage/qa/mobile-hero-whitespace/iteration-01-390x844.png)
- Stav: opraveno. Header konci a hero zacina na Y 72; hero konci a dalsi sekce zacina na Y 672. Obe mezery jsou 0 px, textovy stack ma 0 kolizi a horizontalni overflow je nulovy.

## Inventar screenshotu

| # | URL | Screenshot | Vysledek |
|---:|---|---|---|
| 1 | `/` | `desktop-01-home.png` | bez potvrzene vady |
| 2 | `/rande-a-zasnuby` | `desktop-02-rande-a-zasnuby.png` | bez potvrzene vady |
| 3 | `/reklamni-foto-video` | `desktop-03-reklamni-foto-video.png` | VA-03; recapture cisty |
| 4 | `/rodinne-a-portretni-foceni` | `desktop-04-rodinne-a-portretni-foceni.png` | bez potvrzene vady |
| 5 | `/svatebni-foto-video` | `desktop-05-svatebni-foto-video.png` | bez potvrzene vady |
| 6 | `/blog` | `desktop-06-blog.png` | VA-02 |
| 7 | `/cenik` | `desktop-07-cenik.png` | bez potvrzene vady |
| 8 | `/faq` | `desktop-08-faq.png` | bez potvrzene vady |
| 9 | `/hodnoceni` | `desktop-09-hodnoceni.png` | bez potvrzene vady |
| 10 | `/kontakt` | `desktop-10-kontakt.png` | bez potvrzene vady |
| 11 | `/nase-prace` | `desktop-11-nase-prace.png` | VA-03; recapture cisty |
| 12 | `/o-nas` | `desktop-12-o-nas.png` | VA-02 |
| 13 | `/podminky-uziti` | `desktop-13-podminky-uziti.png` | VA-01 |
| 14 | `/sluzby` | `desktop-14-sluzby.png` | bez potvrzene vady |
| 15-22 | osm aktualnich blogovych detailu | `desktop-15-*` az `desktop-22-*` | VA-02 |
| 23 | `/svatba-u-robinsona-martina-jaroslav` | `desktop-23-svatba-u-robinsona-martina-jaroslav.png` | VA-02 a VA-03; recapture CSS cisty |
| 24-30 | sedm dalsich svatebnich clanku | `desktop-24-*` az `desktop-30-*` | VA-02 |
| 31 | `/svatba-arboretum-bila-lhota-helena-tomas` | `desktop-31-svatba-arboretum-bila-lhota-helena-tomas.png` | VA-02 a VA-03; recapture CSS cisty |
| 32 | `/kamila-a-matej-duriskovi` | `desktop-32-kamila-a-matej-duriskovi.png` | VA-03; recapture cisty |
| 33 | `/aneta-a-martin-hrazdilovi` | `desktop-33-aneta-a-martin-hrazdilovi.png` | bez potvrzene vady |
| 34 | `/lenka-a-lukas-tuikovi` | `desktop-34-lenka-a-lukas-tuikovi.png` | bez potvrzene vady |
| 35 | `/marie-a-miroslav-cervenkovi` | `desktop-35-marie-a-miroslav-cervenkovi.png` | bez potvrzene vady |
| 36 | `/martina-a-jaroslav-zackovi` | `desktop-36-martina-a-jaroslav-zackovi.png` | bez potvrzene vady |
| 37 | `/simona-a-michal-novakovi` | `desktop-37-simona-a-michal-novakovi.png` | bez potvrzene vady |
| 38 | `/lubos-a-iva-neverilovi` | `desktop-38-lubos-a-iva-neverilovi.png` | bez potvrzene vady |
| 39 | `/lucie-a-jarek-stipcakovi` | `desktop-39-lucie-a-jarek-stipcakovi.png` | bez potvrzene vady |
| 40 | `/lea-foceni-pro-ritualni-saty` | `desktop-40-lea-foceni-pro-ritualni-saty.png` | bez potvrzene vady |

Kompletni strojova mereni jsou v `storage/qa/visual-audit-2026-08-11/audit-data.json`; recapture mereni v `storage/qa/visual-audit-2026-08-11/recapture/audit-data.json`.

## Omezeni

Tento beh je kompletni desktopovy audit sitemap. Mobilni breakpoint nebyl soucasti pozadovaneho inventare kazde stranky a nebyl pouzit k rozsireni poctu 40 URL; pro opravu VA-01 a VA-02 je nasledne povinna kontrola `390 x 844` podle `qa-testing.md`.
