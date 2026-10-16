# Visual CSS QA - Golden Bash homepage

Datum: 2026-09-06
Runtime: `http://127.0.0.1:8766/`
Tema: `golden-bash` (aktivni DB runtime, bez `theme_preview`)
Browser: Google Chrome, headless CDP, DPR 1, cisty docasny profil
Verdikt: **PASS po druhe uzivatelske acceptance iteraci, 98/100, zadna otevrena CRITICAL ani MAJOR vada**

Poznamka k integrite auditu: prvni deklarovany PASS 98/100 byl odvolan pote, co uzivatelske 768x1024 vyrezy prokazaly dalsi ctyri vady. Tyto vyrezy vytvorily novou baseline 78/100; nize uvedeny PASS patri az navazujici oprave a novym detailnim dukazum.

Referencni designovy screenshot nebyl dodan. Skore proto meri vizualni kvalitu, responzivitu a technickou korektnost podle zadane rubriky; nejde o pixelovou shodu s neznamym podkladem.

## Finalni skore

| Oblast | Body | Stav |
| --- | ---: | --- |
| Overlap a z-index | 15/15 | Bez prekryvu obsahu; zamerne celokartove overlay odkazy nejsou kolize. |
| Overflow a clipping | 15/15 | Finalni matice ma nulovy horizontalni, primy i ancestor clipping. |
| Tlacitka, formulare a ovladaci prvky | 10/10 | Portfolio ovladac je mimo karty, review CTA jsou vedle sebe, pole jsou citelna a interakce funguji. |
| Layout, spacing a alignment | 14/15 | Sekce jsou v toku; tabletovy footer je radkovy. Portfolio zachovava dve odlisne, ale citelne akce. |
| Text a ikony | 10/10 | Nadpisy, dlouhe recenze, blogove odkazy i lokalni ikony jsou cele. |
| Navigace a fixed UI | 10/10 | Menu je ciste; layoutove acceptance snimky pouzivaji skutecny stav po kliknuti na odmitnuti cookies. |
| Responzivita | 14/15 | Overeny desktop, tablet, mobil a obe strany dotcenych hranic; ctyri procesni karty jsou na 701 px zamerne hustejsi. |
| Media, interakce a runtime | 10/10 | 12/12 viditelnych medii; FAQ, menu a portfolio load-more potvrzeny. |

## Opravene nalezy

### [MAJOR] Ceny presahovaly viewport na 1051 px

- **Misto:** homepage, `.gb-pricing`, constrained desktop tesne nad 1050 px.
- **Problem:** treti cenova karta a jeji nadpis/tlacitko presahovaly pravou hranu; recenze a blog soucasne drzely prilis uzky desktopovy obsahovy sloupec.
- **Ocekavany stav:** vsechny tri karty musi zustat uvnitr viewportu a text nesmi byt oriznuty.
- **Pricina:** desktopovy dvousloupcovy section layout byl aktivni uz od 1051 px, i kdyz pro minimalni sirky karet nebyl dost siroky.
- **Oprava:** obsahovy guard byl rozsiren na 701-1199 px; od 1200 px je puvodni dvousloupcovy desktop prokazatelne cisty.

### [MAJOR] Pevne vysky orezavaly recenze a blog na tabletu

- **Misto:** `.gb-reviews`, `.gb-blog`, `.hp-blog-card`, zejmena 768 px.
- **Problem:** blog mel pevnych 450 px, jeho 360px karty orezavaly odkazy az o 16 px a kontakt zacinal, nez blogovy obsah skoncil. Dlouhe recenze byly stisnene do pevne sekce.
- **Ocekavany stav:** vyska sekce i karty se prizpusobi realnemu obsahu a dalsi sekce zacne az po nem.
- **Pricina:** desktopove `height`/`overflow:hidden` zustaly aktivni v jednosloupcovem tabletovem layoutu.
- **Oprava:** sekce maji v guardu `height:auto` a viditelny overflow; blogovy grid pouziva obsahovy radek s minimem 380 px, ktery se u aktualniho textu zvysil na 405 px.

### [MAJOR] Portfolio, kontakt a FAQ byly v nespravnych grid sloupcich

- **Misto:** `.gb-portfolio-mosaic`, `.gb-contact`, 701-1199 px.
- **Problem:** portfolio galerie mohla zustat v implicitnim uzkem `grid-column:2`; kontakt drzel tri prilis uzke sloupce a FAQ melo malo mista.
- **Ocekavany stav:** portfolio ma plnou sirku; kontakt ma citelny formular a samostatny plnosiroky FAQ radek.
- **Pricina:** starsi vysoce specificke `grid-column`/`grid-row` hodnoty prezily obecny media-query prepis.
- **Oprava:** explicitni umisteni portfolio prvku do prvniho sloupce, dvousloupcovy horni kontakt a FAQ pres oba sloupce. Otevrene FAQ pouziva obsahovou vysku bez clippingu.

### [MAJOR] Tabletove portfolio zdedilo desktopovou mozaiku pod pevnou vyskou

- **Misto:** `.gb-portfolio-mosaic .pt-grid`, `.pt-tile:nth-child(1..5)`, `.gb-portfolio-more`, 701-1199 px.
- **Problem:** na 768 px se zmenil pocet sloupcu, ale zustaly explicitni desktopove pozice, dva pevne 201px radky a `height:414px!important`. Karty pusobily jako vlozene do sebe a tlacitko `Dalsi` se zasunulo pod jejich vizualni presah.
- **Oprava:** guard resetuje `grid-template-rows:none`, pouziva tri automaticke sloupce a 260px radky, vsech pet karet vraci na `grid-column/grid-row:auto` a ovladac drzi v normalnim toku s 24px odsazenim.
- **Dukaz:** `feedback-detail-portfolio-controls-768x1024.png`.

### [MAJOR] Text procesu pretekl z pevneho vnitrniho radku

- **Misto:** `.gb-shared-process__step`, 701-1199 px.
- **Problem:** kopie sedela v pevnem 84px grid radku; delsi odstavec pretekl pres spodni hranu karty do benefit pasu, aniz vznikl horizontalni nebo page-level clipping nalez.
- **Oprava:** tabletova karta pouziva `grid-template-rows:72px auto`, minimalni vysku 320 px a uzsi mezery/konektory. Text konci uvnitr karty a benefit pas ma samostatny tok.
- **Dukaz:** `feedback-detail-process-768x1024.png` a `feedback-detail-process-bottom-768x1024.png`.

### [MAJOR] Review CTA a footer plytvaly prostorem a lamily text

- **Misto:** `.gb-reviews .hp-section-cta--multiple`, `.gb-footer-rich`, 701-1199 px.
- **Problem:** dve review akce byly pod sebou; pozdejsi petisloupcovy footer prepsal drivejsi tabletovou variantu a lamal kontakt i odkazy po jednotlivych slovech.
- **Oprava:** review wrapper je staticky dvousloupcovy grid. Footer ma jeden vnejsi sloupec, kazda obsahova skupina tvori plnosirkovy radek a kontakt/odkazy/social/legal pouzivaji citelne vnitrni gridy.
- **Dukaz:** `feedback-detail-review-buttons-768x1024.png` a `feedback-detail-footer-clean-768x1024.png`.

### [MAJOR] Cenikovy grid prekryl vlastni nadpis na 1051-1199 px

- **Misto:** `.gb-pricing > .container`, finalni boundary kontrola 1199 px.
- **Problem:** metriky hlasily nulovy overflow, ale rucni kontrola PNG ukazala karty pres nadpis `Vyberte si balicek`.
- **Ocekavany stav:** label, nadpis, intro, karty a CTA jsou v samostatnych radcich v DOM poradi.
- **Pricina:** `.hp-pricing` zdedil desktopove `grid-row:1 / span 5`; automaticka kontrola textovych kandidatu neporovnavala cely grid box s nadpisem.
- **Oprava:** vsechny prime deti cenikoveho containeru maji v guardu `grid-row:auto!important`. Iterace 5 vizualne i geometricky prosla.

### [MINOR] QA parametr nevytvarel skutecne otevrene Golden Bash menu

- **Misto:** `scripts/qa_chrome_cdp_capture.ps1`, `-OpenNavigation`.
- **Problem:** skript hledal jen `.nav-toggle`/`nav-open`, ale tema pouziva `.gb-menu`/`gb-nav-open`; zavreny screenshot mohl byt omylem povazovan za interakcni dukaz.
- **Ocekavany stav:** capture bud prokaze otevrene menu, nebo viditelne selze.
- **Pricina:** nastroj znal pouze obecny navigacni kontrakt.
- **Oprava:** oba kontrakty jsou podporovane, JSON vraci `NavigationState` a neotevreni vyvola chybu. Finalni stav: `.gb-menu`, `aria-expanded=true`, `NavigationOpen=true`.

## Iterace a skore

| Iterace | Viewport / dukaz | Skore | Hlavni delta |
| --- | --- | ---: | --- |
| Baseline | 1280x900, `valid-baseline-desktop-1280.png` | 68 | Rozpad tabletovych gridu, orezany blog/cenik/kontakt. |
| 1 | 1024 a 768, `iteration-01-*` | 76 | Ceny/kontakt se zlepsily, ale seskupeny selector byl v hashovanem CSS minifikovan do nematchujiciho tvaru. |
| 2 | 768, `iteration-02-768.png` | 88 | Blog a recenze dostaly obsahovou vysku; portfolio zustalo v implicitnim druhem sloupci. |
| 3 | 768, `iteration-03-768.png` | 93 | Portfolio opraveno; zustal ancestor clipping tri blogovych odkazu. |
| 4 | 768, `iteration-04-768.png` | 97 | Blogove karty jsou obsahove pruzne; nulovy clipping. |
| Boundary audit | 1051/1150/1200 | 86 | Odhalen MAJOR problem tesne nad puvodnim breakpointem. |
| 5 | 1199, `iteration-05-1199.png` | 98 | Guard konci az na 1199 px a cenik ma automaticke radky; rucni i geometricka kontrola cista. |
| Uzivatelska acceptance | 768x1024 anotovane vyrezy | 78 | Predchozi PASS odvolan: prekryte portfolio, procesni text mimo kartu, CTA pod sebou a necitelny footer. |
| Feedback 1 | 768x1024 full-page + ctyri 100% detaily | 96 | Vsechny ctyri nahlasene vady opraveny; cekalo se na obe strany breakpointu. |
| Feedback acceptance | 700/701, 1199/1200, 1366, 390 | 98 | Breakpointova, desktopova a mobilni regrese cista; zadna CRITICAL ani MAJOR vada. |

## Finalni breakpointova matice

Vsechny uvedene behy maji `HeightStable=true`, 12/12 viditelnych obrazku, 0 image failures, nulovy horizontalni overflow a 0 primych i zdedenych clipping nalezu.

| Sirka | Screenshot | Poznamka |
| ---: | --- | --- |
| 1920 | `storage/qa/visual-css-qa-20260906/acceptance-1920x1080.png` | Presna povinna desktop kontrola 1920x1080. |
| 1366 | `storage/qa/visual-css-qa-20260906/feedback-regression-1366x768.png` | Nova bezna desktop kontrola po feedback oprave. |
| 1200 | `storage/qa/visual-css-qa-20260906/feedback-regression-1200x900.png` | Prvni desktop nad guardem po feedback oprave. |
| 1199 | `storage/qa/visual-css-qa-20260906/feedback-regression-1199x900.png` | Horni hrana radkoveho tabletoveho guardu. |
| 1024 | `storage/qa/visual-css-qa-20260906/final-regression-1024.png` | Tablet/landscape regresni kontrola. |
| 768 | `storage/qa/visual-css-qa-20260906/feedback-acceptance-768x1024.png` | Nova presna tabletova acceptance po uzivatelske anotaci. |
| 701 | `storage/qa/visual-css-qa-20260906/feedback-regression-701x900.png` | Dolni hrana guardu s novym portfolio/proces/footer kontraktem. |
| 700 | `storage/qa/visual-css-qa-20260906/feedback-regression-700x900.png` | Prvni mobilni CSS vetev. |
| 390 | `storage/qa/visual-css-qa-20260906/feedback-regression-390x844.png` | Nova mobilni regrese po feedback oprave. |
| 320 | `storage/qa/visual-css-qa-20260906/final-valid-320.png` | Uzky mobilni edge case. |

## Interakcni stavy

- Mobilni menu: `final-nav-open-390-v2-viewport.png`; otevrene, `aria-expanded=true`, bez overflow/clippingu a bez cookie prekryvu po explicitni volbe.
- FAQ: `final-faq-open-768-viewport.png`; odpoved je cela, section flow se zvetsil bez clippingu.
- Portfolio: `final-portfolio-more-768-viewport.png`; po kliku 9 viditelnych karet, 0 skrytych, 16/16 nactenych viditelnych medii.
- Cookie summary: samostatne overen jako aktivni fixed stav se tremi dostupnymi akcemi. Hlavni finalni screenshoty jsou oznaceny jako stav po kliknuti na `Odmitnout vse`, nikoli jako vypnuta konfigurace.
- Nova 768x1024 acceptance ma 12/12 medii, stabilnich 8647 px, nulovy horizontalni overflow a nulovy primy/ancestor clipping. Detailni dukazy navic vizualne potvrzuji portfolio, review CTA, proces a footer v meritku 1:1.

## Overeni

- `git diff --check`: PASS.
- PowerShell parser `scripts/qa_chrome_cdp_capture.ps1`: PASS.
- `php tests/run.php`: 148 tests, 0 failures.
- Golden-master: 28/28 PASS.
- `golden-bash-diagonal` dostal stejny responzivni guard; kontrakt povoluje nadale pouze identitu a hero geometrii jako rozdil.
- `tests/GoldenBashThemeTest.php` chrani novy tabletovy kontrakt portfolia, review CTA, procesu a footeru.
- Globalni skill `visual-screenshot-score-loop` byl rozsiren o full-page + detail gate, breakpointove pary a povinne odvolani PASS pri protichudnem uzivatelskem dukazu. Oficialni `quick_validate.py` se na tomto hostu nespustil kvuli chybejicimu modulu `yaml`; rucni strukturalni kontrola je zaznamenana oddelene.
- Wiki stranky `patterns/visual-screenshot-score-loop.html`, `projects/seo-editor/tools.html`, projektovy hub a glossary byly aktualizovany o reprodukovatelny postup a false-PASS pripad.
- Wiki HTML/UTF-8/lokalni odkazy a `git diff --check` prosly. Samostatny wiki commit nebyl vytvoren, protoze managed prostredi odmitlo zapis do externiho git indexu jako nepripustnou eskalaci; ctyri obsahove zmeny zustavaji viditelne necommitnute v `C:\Wiki`.

## Zbyvajici POLISH

- Portfolio na tabletu zachovava dve legitimni akce: progresivni `Dalsi` a odkaz na kompletni listing. Jsou oddelene a citelne, ale rytmus je o neco vyssi nez u jedine akce (-1).
- Ctyri procesni karty zustavaji na dolni hrane 701 px hustejsi; text je uvnitr, ale kompozice je mene vzdusna nez na sirsi tabletu (-1).
- Cookie summary je samostatne overen jako zamerne fixed UI. Layoutove acceptance pouziva realny stav po kliknuti na `Odmitnout vse`; konfigurace nebyla vypnuta.

## Neplatne nebo nepouzite behy

- Pokusy s PowerShell policy chybou, Chrome `neterror`, nenactenymi lazy obrazky nebo zkracenym 1200ms startem nemaji skore a nejsou finalnim dukazem.
- `scripts/qa_full_site_visual_audit.ps1` dvakrat ztratil CDP WebSocket pred vznikem vysledku. Jeho data nebyla pouzita; izolovany capture je legitimni pouze pro zadany homepage scope, ne jako ticha nahrada plneho sitemap auditu.
- Oddeleny WMI start PHP serveru byl v omezenem prostredi odmitnut. Validni capture proto pouzil ohraniceny `Start-Process` + HTTP 200 gate + screenshot + `finally Stop-Process` v jednom prikazu.
- Prvni detailni pokus kombinoval `-CaptureViewportOnly` s `-ScrollSelector`; viewport-only vetev skoncila pred scroll capture a pozadovane soubory nevytvorila. Beh nema dukazni hodnotu. Nasledujici normalni CDP behy existenci kazdeho detailniho PNG explicitne overily.
