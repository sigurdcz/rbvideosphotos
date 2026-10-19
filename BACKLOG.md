# Backlog

Odložená práce. Před zahájením vybereme prioritu; rozpracovaný úkol se vede v `UKOL.md`.

## 1. Stabilní workflow produkce a stagingu — P1, částečně hotovo

- Hotovo: `dev` → environment `staging`, `master` → `production`; samostatné FTP secrets, staging povoluje jen větev `dev`; pravidla v `INSTRUCTIONS.md`, instalační návod `STAGING.md`.
- Hotovo také: první FTPS nasazení, DNS/TLS odpovídá. Uživatel potvrdil vlastní DB a `.env`; homepage nyní vrací 404 místo chyby připojení, login formulář HTTP 200.
- Zbývá: inicializovat/publikovat staging obsah, ověřit média/SMTP a celý postup staging → uživatelem schválená produkce i zpětnou obnovu ze zálohy.
- Zbývá: dohodnout a nastavit ochranu `master` na GitHubu (PR/review, žádný force push), vyřešit oprávnění schvalovatele. Samotná pravidla v dokumentaci tuto ochranu nevynucují.
- Hotovo bude, když staging funguje včetně administrace, médií, migrací a SMTP a další změna projde kontrolou před výslovně schváleným merge do `master`. Bez automatického merge.

## 2. České znaky ve fontech — P2, opraveno lokálně, čeká staging

- Některé řezy nezobrazují správně `ř`, `š` a další české znaky.
- Ověřit skutečně načtený font, řezy a Latin Extended subset; opravit zdroj/font konfiguraci, nikoli nahradit český text.
- Hotovo: `ěščřžýáíéůúďťň` správně v nadpisech i textu, na desktopu i mobilu, bez nechtěného náhradního fontu.
- Implementováno: skutečný Regular italic 400 v4.001 s kompletní Unicode mapou; nový immutable filename, licence a reprodukovatelný build. `font-display: swap` opraví pozdě načtené fonty. Chrome potvrdil použití nového řezu; ještě ověřit na inicializovaném stagingu, včetně případných posunů layoutu při pomalém načtení.

## 3. Výška hero videa homepage — P2, implementováno, čeká živé video QA

- Pod videem prosvítá fallback obrázek; video nepokrývá celou výšku hero.
- Změřit skutečné rozměry a computed CSS ve vybrané šabloně včetně generovaných assetů; prověřit `height`, `min-height`, `object-fit` a přepnutí poster/video.
- Hotovo: video pokrývá celou hero bez pruhu obrázku na desktopu i mobilu. Před načtením a při nedostupném videu zůstává záměrný fallback správně viditelný.
- Příčina: skutečné Vimeo video má 426×206, původní CSS počítalo s 16:9. Runtime nově čte rozměry od konkrétního Vimeo iframe a přepočítává cover přes ResizeObserver; poster pokrývá panel samostatně. Nedostupná metadata mají viditelnou diagnostiku a výslovný provizorní poměr 16:9.
- Ověřeno: jednotkový test a skutečné generované CSS v Chrome s explicitními metadata fixtures. Lokální video není dostupné a média se liší od produkce; finální obraz přehrávaného videa a chybový fallback tím nejsou ověřeny. Neuzavírat jen podle geometrie.

## 4. Burger tlačítko navigace — P2, opraveno lokálně, čeká staging

- Po otevření menu se burger přestal animovat do křížku.
- Při hoveru se pod tlačítkem zobrazuje nechtěné tmavé pozadí.
- Ověřit stav otevřeného menu, vazbu na `aria-expanded`, animační CSS a pravidla pro hover/focus v aktivní šabloně i generovaných assetech.
- Hotovo: otevření plynule změní burger na křížek, zavření vrátí burger; hover nemá rušivé tmavé pozadí. Ověřit myš, dotyk i klávesnici a zachovat viditelný focus pro ovládání klávesnicí.
- Implementováno pro oba Golden Bash motivy: animace platí do 1050 px, transparentní hover/active, přístupný focus a omezení animace při reduced motion. Chrome ověřil klik/hover, otevřený křížek, zavření přes Escape a vrácení focusu. Finální ověření dotyku a stagingu zůstává otevřené.

## 5. Zásady o vlastním měření návštěvnosti a retence logů — P2, čeká text a rozhodnutí

- Doplnit zásady o vlastní měření návštěvnosti (cookieless hash + cookie `cms_vid` po souhlasu) do právního textu „Cookies a souhlasy“ na `/podminky-uziti`. Text vede uživatel; změna obsahu jde přes novou migraci.
- Popsat: bez souhlasu se ukládá jen serverový záznam (cesta, doména odkazující stránky, UTM, typ zařízení, denně rotující anonymní otisk z IP a prohlížeče; IP adresa se do `site_visits` neukládá); po souhlasu s analytickými cookies cookie `cms_vid` (náhodný identifikátor, platnost cca 13 měsíců); retence 14 celých měsíců + běžící měsíc; správce dat; měření lze vypnout v Nastavení.
- Pozor, „IP se neukládá“ platí jen pro `site_visits`: `Runtime::startRequest()`/`finishRequest()` zapisuje do `storage/logs/traffic-*.log` u každého requestu (i `POST /api/visit`) IP, User-Agent a Referer. Rozhodnout a zdokumentovat zásady a retenci aplikačních logů (doba uchování, rotace/mazání `storage/logs`, případně zkrácení IP nebo vynechání `/api/visit` z traffic logu) a promítnout to do textu zásad.
- Hotovo bude, když `/podminky-uziti` na stagingu obsahuje uvedené body včetně názvu cookie `cms_vid`, její doby platnosti a retence, text odpovídá chování `public/assets/visit-analytics.js` i skutečnému logování, retence logů je nastavená a uživatel text schválí.
