# Backlog

## Mobilni galerie a hero hover — 2026-10-29, opraveno lokalne, ceka staging QA

- Galerie: opravena interakce skryteho dialogu, listingovy cover a zaviraci timer;
  JSON pipeline zavisi take na lokalnim performance commitu `2c16fd6`.
- Hero: samostatny papirovy fade misto masky na zive videoplose, bez zmeny klipu.
- Chrome a WebKit dotykova regrese prosla. Generovane CSS proslo hover/vrstvy a
  700/701px kontrolou na syntetickem iframe; to neni dukaz skutecneho video playbacku.
- Dokonceni: po staging nasazeni otevrit nekolik galerii v iPhone 15 Firefoxu,
  prepinat fotky, zavrit a znovu otevrit bez posunu stranky; na pocitaci z nahravky
  opakovane rychle prejizdet CTA a navigaci nad skutecnym videem bez svisleho pruhu.
- Bez automatickeho merge do master; fyzicke zarizeni ani stejna GPU nejsou lokalne dostupne.

Odložená práce. Před zahájením vybereme prioritu; rozpracovaný úkol se vede v `UKOL.md`.

## Performance homepage: overeni na skutecnych mediich — 2026-10-29

- Po lokalni oprave SSR fotografie, mobilniho preloadu, observer navigace a zachovani galerijniho JSON overit staging se skutecnymi medii a publish vystupem. Lokalni DB obsahuje chybejici obrazky/logo a neplatny externi videoodkaz; lokalni Lighthouse neni s produkcnim reportem srovnatelny.
- Hotovo: tri mobilni a jeden desktopovy beh za stejnych podminek, zaznamenany median a LCP element, zadne zhorseni CLS/layoutu ani galerie. Potvrdit, ze mobilni preload a obrazek sdileji stejny vybrany zdroj a ze nevznika druhy download obrazku.
- Optimalizace videa je podle uzivatele odlozena na dodani klipu; tato oprava jeho zdroje, autoplay ani prehravani nemeni. Velky objem videa tedy zustava.
- Cela sada ma pred i po oprave tri starsi FAIL (Golden Bash CSS isolation, long-dash audit admin analytics, zastaraly source kontrakt galerie) a osm golden rozdilu. Neresit je automatickym prijetim goldenu; samostatna oprava musi prokazat spravny obsah/vzhled a soulad kontraktu.

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

## 6. SEO, PageSpeed a navazující kontroly — P3, odloženo po urgentních úkolech, 2026-11-16

**Stav: pouze evidence, implementace nezahájena.** Na výslovný pokyn uživatele řešit až po dokončení urgentnějších věcí. Pořadí bodů určuje postup uvnitř tohoto odloženého balíku, nepovyšuje je nad aktuální úkoly. Při zahájení znovu ověřit stav webu a vybrat konkrétní rozsah do `UKOL.md`.

Navazuje na výše uvedený bod „Performance homepage: overeni na skutecnych mediich“ a kontrolu českých fontů. Společné ověření provést jednou a výsledek promítnout do obou souvisejících záznamů. Nadále platí dřívější odklad optimalizace videa do dodání klipu; tento zápis není pokynem měnit video ani jeho přehrávání nyní.

### Podklady a omezení

- Uživatelem dodané screenshoty v `C:/Users/sigur/Downloads/FullPageScreenshots/`:
  - `fullpage-2026-11-11_01-49-37-seoptimer.com-SEO-Audit-for-rbvideosphotos.cz-SEOptimer.png`
  - `fullpage-2026-11-11_02-33-35-pagespeed.web.dev-PageSpeed-Insights.png`
- Jde o historická měření z 2026-11-11, nikoli o nově ověřený stav k datu zápisu. SEOptimer hlásil chybu crawleru a možné neúplné výsledky: D+, mobil 88, desktop 94, 34 odkazů z 32 domén (9 dofollow).
- PageSpeed mobil: výkon 74, FCP 1,1 s, LCP 6,3 s, TBT 70 ms, CLS 0; přenos 17 878 KiB, dva řádky MP4 dohromady přibližně 17 019 KiB (95 %). Zkrácené URL nedokazují duplicitní stahování. Rozdíl 88/74 bodů sám nedokazuje regresi.
- LCP prvkem byla fotografie „RB Videos & Photos při práci“; rozklad uváděl čekání na zdroj 350 ms, stahování 190 ms a čekání na vykreslení 1 960 ms. Součet rozkladu s TTFB 30 ms je přibližně 2,53 s, nikoli 6,3 s; pozorované části a simulovaná hlavní metrika se nesmějí zaměňovat. Příčina zdržení ani přesná úspora dosud nejsou prokázané.
- CSS: odhad úspory blokování 160 ms, nevyužité CSS asi 14 KiB. Google skripty asi 298 KiB, odhad nevyužitého JS 140 KiB. Cache náhledu YouTube: odhad úspory 11 KiB. Odhady různých auditů nesčítat jako garantované zrychlení.

### Odložený seznam doporučení a podmínky dokončení

- [ ] **1. Dostupnost pro SEOptimer:** prověřit logy hostingu, 403/429/5xx, timeouty a případná omezení konkrétního crawleru. Hotovo: zdokumentovaná příčina, úplný nový audit bez varování a úspěšný živý test URL v Search Console; bezpečnostní ochrany měnit pouze cíleně podle důkazů.
- [ ] **2. Mobilní video:** po dodání klipu připravit přiměřené rozlišení a datový tok, případně odstranit nepotřebnou zvukovou stopu. Hotovo: doložené snížení přenosu proti nové baseline při přijatelném vzhledu a funkčním přehrávání.
- [ ] **3. Dva velké požadavky na MP4:** porovnat úplné URL, iniciátory, odpovědi a `Range`/`Content-Range`. Hotovo: vysvětlené části přenosu a odstraněná případná zbytečná opakování; dva řádky reportu nepovažovat automaticky za dva celé downloady.
- [ ] **4. Pozdní vykreslení LCP fotografie:** pořídit Chrome Performance trace a najít skutečnou příčinu čekání po načtení obrázku. Hotovo: doložená příčina a opakovatelně nižší LCP bez regresí. Mobilní preload a eager loading již byly přítomné, mobilní welcome vrstva vypnutá v CSS; jejich další úpravy musí vycházet z měření.
- [ ] **5. Současné načítání videa a první obrazovky:** ověřit soutěž o síťové prostředky a přednostní zobrazení obsahu. Hotovo: síťový záznam dokládá správné pořadí a potřebnou variantu videa bez zhoršení přehrávání. `preload="metadata"` při autoplay není zárukou malého přenosu.
- [ ] **6. Relevantní zpětné odkazy:** získávat přirozené zmínky od skutečných svatebních míst, koordinátorů a dodavatelů. Hotovo pro první etapu: evidované 3–5 relevantních zmínek v dohodnutém období a sledování návštěv/poptávek; nehodnotit jen počet či skóre domén.
- [ ] **7. Obsah a důvěryhodnost:** připravit konkrétní reportáže ze zakázek s místem, vlastními fotografiemi, průběhem spolupráce a autentickými referencemi; ověřit údaje ve firemních profilech. Hotovo: publikovaný dohodnutý rozsah a sjednocené ověřené údaje, bez vymyšlených referencí.
- [ ] **8. GTM a analytika:** zkontrolovat potřebnost tagů, spouštění a duplicitní události; odstranit nepotřebné integrace. Hotovo: zdokumentovaný seznam aktivních tagů, ověřené události a menší zátěž bez ztráty důležitých návštěv/poptávek. Přítomnost GTM i Google tagu sama neprokazuje duplicitu.
- [ ] **9. Fonty:** přednačítat pouze řezy první obrazovky, prověřit velkou kurzívu a rozsahy znaků. Hotovo: menší přenos fontů, zachované české znaky a vzhled, bez nechtěného náhradního fontu či zhoršení CLS.
- [ ] **10. Blokující CSS:** zmenšit hlavní balík a podle měření oddělit styly nepotřebné pro první obrazovku. Hotovo: nižší blokování a stejný vzhled při pomalém načítání; nepovažovat odhad 160 ms za vysvětlení celého LCP.
- [ ] **11. Nevyužité CSS:** ověřit označená pravidla také v otevřeném menu, galeriích, formulářích a všech responzivních stavech. Hotovo: odstraněna jen skutečně nepotřebná pravidla a ověřeny interakce.
- [ ] **12. Srovnatelné měření:** minimálně tři mobilní a jeden desktopový běh stejné finální URL za srovnatelných podmínek se skutečnými médii. Hotovo: uložený medián, LCP prvek, přenosy a srovnání před/po; kontrola vzhledu, CLS a funkčnosti. Výsledky propojit s existujícím performance bodem.
- [ ] **13. Reálné Core Web Vitals:** sledovat Search Console; při nedostatku dat posoudit vlastní měření LCP, INP a CLS. Hotovo: doložený zdroj a rozsah dat, případně výslovně zaznamenaný nedostatek dat. Chybějící data neznamenají neúspěšný test. Cíle pro reálná data na 75. percentilu: LCP ≤ 2,5 s, INP ≤ 200 ms, CLS ≤ 0,1.
- [ ] **14. Cache náhledu YouTube:** zvážit vlastní spravovaný náhled s odpovídající cache. Hotovo: ověřený přínos nebo zdůvodněné ponechání; malá úspora 11 KiB neospravedlňuje složité řešení. Vlastní hlavičky nemění cache cizího serveru.
- [ ] **15. Podezřelé odkazy:** posoudit původ a relevanci automatických „Domain Report“, „Website Stats“ a podobných stránek. Hotovo: doložené rozhodnutí; nepoužívat automatický disavow podle názvu či skóre domény.
- [ ] **16. Přístupnost:** ručně ověřit klávesnici a alternativní texty; u videí s důležitým mluveným obsahem titulky. Hotovo: relevantní obsah a ovládání jsou přístupné, nálezy mají konkrétní důkaz. Rozbalovací doporučení reportu ani skóre 100 nejsou úplným auditem.
- [ ] **17. Bezpečnostní doporučení:** prověřit CSP, HSTS, COOP a vhodnost Trusted Types. Hotovo: zdokumentované rozhodnutí a kompatibilita případných úprav s administrací, videem a analytikou; obecná doporučení nejsou potvrzené zranitelnosti.
- [ ] **18. Strukturovaná data a úplnost SEO:** ověřit platnost dat, shodu s viditelným obsahem a důležité podstránky. Hotovo: uložené výsledky validace a vyřešené konkrétní nálezy; skóre SEO 100 / On-Page A+ nevydávat za kompletní audit.

### Dříve ověřené body, které nyní nejsou samostatnou opravou

- Dne 2026-11-11 vedly HTTP/www varianty jedním přesměrováním na `https://rbvideosphotos.cz/`; finální URL neměla přesměrování. Před změnou pravidel znovu změřit.
- Homepage, robots.txt a sitemap vracely HTTP 200, sitemap byla platné XML se 68 URL; obecné procházení bylo povoleno. Homepage měla title, description, H1, canonical a `index,follow`. To samo nepotvrzuje přístup skutečného Googlebotu ani indexaci.
- Reporty neprokazovaly zásadní chybu minifikace, explicitních rozměrů obrázků ani cache vlastních statických souborů. Nízké TBT a CLS zachovat.

### Společné podmínky dokončení technických změn

- Začít až po urgentnějších úkolech, s čerstvou baseline a konkrétním vybraným rozsahem. Neprovádět automaticky všechny hypotetické opravy.
- Implementace na `dev` nebo `codex/*` založené z `dev`, ověření na stagingu se skutečnými médii; kontrola vzhledu, videa, galerií, formulářů, analytiky a českých fontů. Produkce pouze po výslovně schváleném konkrétním merge ověřeného `dev` do `master`.
- U každého vybraného bodu uložit výsledek a důkaz, případně zdůvodnění, proč oprava není potřebná. Nezaměňovat laboratorní skóre za reálné Core Web Vitals a neslibovat pořadí ve vyhledávání.
