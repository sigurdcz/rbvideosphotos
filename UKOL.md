# UKOL: Tři sekce administrační Analytiky

Datum: 2026-10-21
Zadani: Rozdělit Analytiku na Přehled, Health a Návštěvnost; přehled spojí hlavní metriky a detailní sekce zachovají své grafy a tabulky.
Doporuceny model: Sol - Jde o ohraničenou úpravu controlleru, serverově řízené navigace a administračního view.

## Kroky

- [x] Navrhnout serverově řízené sekce s allowlistem.
- [x] Přidat společný Přehled a oddělit detail Health/Návštěvnost.
- [x] Doplnit regresní testy a ověřit PHP/CSS wiring.
- [x] Commitnout dokončenou úpravu.
- [ ] Po výslovném požadavku nasadit na staging; master neměnit.

## Poznamky

Sekce používá query parametr `view=overview|health|visits`, který controller normalizuje přes pevný allowlist. Přehled načítá oba datové zdroje; detail Health nespouští dotaz návštěvnosti a detail Návštěvnost nespouští health dotaz. Volba období zůstává při přepnutí sekce zachována.

## Vysledek

Analytika má tři serverově řízené sekce. Přehled kombinuje aktuální health stav, dostupnost, odezvu a hlavní návštěvní metriky bez podrobných tabulek. Health zachovává denní graf a poslední problémy; Návštěvnost zachovává denní návštěvy, stránky, referrery, kampaně a zařízení. Cílená sada prošla 6/6 včetně skutečného renderu všech tří variant, PHP lint prošel a `git diff --check` je čistý. Změna je lokálně commitnutá; staging push čeká na výslovný požadavek. Master zůstává beze změny.

---
# UKOL: Chraneny PHP runtime info endpoint

Datum: 2026-10-22
Zadani: Pridat JSON API endpoint chraneny MIGRATE_TOKEN, ktery bezpečne zobrazi presnou verzi PHP a stav vuci kritickym minimalnim verzim bez uniku serverovych cest nebo konfigurace.
Doporuceny model: Terra - Jde o ohraniceny bezpecnostni endpoint s autentizaci, omezenim vystupu, routingem pred session a regresnimi testy.

## Kroky

- [x] Zmapovat existujici early routing, praci s MIGRATE_TOKEN a technicke endpointy.
- [x] Implementovat konstantni porovnani tokenu, GET/HEAD JSON odpoved a bezpecny runtime payload.
- [x] Overit nezarazeni do sitemap/robots a zadne zalozeni session.
- [x] Pridat regresni testy a dokumentaci pouziti.
- [x] Spustit cilene testy, aktualizovat wiki a commitnout na dev bez pushe.

## Poznamky

- Endpoint nesmi vracet phpinfo(), php.ini cestu, env, nactene hodnoty konfigurace, serverove cesty ani seznam promenych prostredi.
- Akceptace: bez tokenu nebo se spatnym tokenem nevznikne diagnosticky vystup; s platnym MIGRATE_TOKEN vrati presnou PHP_VERSION, SAPI, bezpecnostni minimum a boolean patched; HEAD vrati stejne hlavicky bez tela.
- Pridana trida PhpRuntimeInfoHttpResponder a early route /api/runtime-info pred Auth::start(). Po nasledne zmene kontraktu se token prijima jako GET parametr `token`; aplikacni Runtime jeho hodnotu v traffic/error logach maskuje.
- Payload je omezen na verzi, version_id, SAPI, stav vuci minimim pro CVE-2026-109618/109619 a boolean dostupnosti peti povinnych extensions. Neznamou budouci vetev oznaci unknown_branch misto ticheho odhadu bezpecnosti.
- Regresni testy pokryvaji chybejici/chybny token, header i Bearer autentizaci, zastaralou a neznamou vetev, HEAD bez tela, zakazane citlive klice, poradi pred session a absenci v sitemap/robots.
- README obsahuje URL s GET tokenem a upozorneni na provider access log, ARCHITECTURE presne poradi early rout a bezpecnostni kontrakt a DEPLOYMENT produkcni/staging kontrolu minimalni PHP verze.
- Tri cilene runtime-info testy prosly, PHP lint nove tridy, routy i testu je cisty a git diff --check nehlasi chybu.

## Vysledek

Pridan chraneny endpoint `GET|HEAD /api/runtime-info`. Platny `MIGRATE_TOKEN` prijima jako GET parametr `token` a porovnava jej pres `hash_equals`; chybejici nebo chybny token vraci obecny 404 bez diagnostiky. Odpoved obsahuje jen presnou PHP verzi, version_id, SAPI, bezpecnostni minimum/stav pro CVE-2026-109618/109619 a dostupnost peti povinnych extensions. Neznama vetev je explicitne `unknown_branch`.

Endpoint bezi pred Auth::start a analytics, nevytvari session, ma no-store/noindex, nepouziva DB a neni v sitemap ani robots. Tri cilene testy, PHP lint a git diff --check prosly. README, ARCHITECTURE, DEPLOYMENT a globalni wiki popisuji bezpecne curl pouziti pro produkci i staging. Nova migrace ani env promenna nejsou potreba; pouziva se existujici MIGRATE_TOKEN. Zmena je commitnuta lokalne na dev a bez explicitniho pozadavku nebyla pushnuta.

---
# UKOL: Health API, cron monitoring a analytika zdraví

Datum: 2026-10-21
Zadani: Přidat veřejné health API mimo sitemapu a roboty, desetiminutový cron se spolehlivým záznamem výpadků a rozšířit administraci Analytika o zdraví webu a návštěvnost.
Doporuceny model: Sol - Změna propojuje veřejný endpoint, plánovanou úlohu, databázovou historii, odolný zápis a administrační grafy.

## Kroky

- [x] Zmapovat současnou analytiku, routing, databázové schéma a provozní možnosti cronu.
- [x] Navrhnout bezpečný health kontrakt, retenci a append-only databázovou migraci.
- [x] Implementovat health API bez session, indexace, sitemap a analytického započítání.
- [x] Implementovat cron runner, detekci výpadku a obnovitelný záložní záznam.
- [x] Rozšířit Analytiku o stav, dostupnost, historii a graf vedle návštěvnosti.
- [x] Doplnit testy, dokumentaci, cron návod a wiki.
- [x] Commitnout dokončenou implementaci.
- [x] Nasadit na staging po výslovném požadavku na push; master bez výslovného potvrzení neměnit.

## Poznamky

Health API zveřejní jen bezpečný agregovaný stav a čas. Cron bude zapisovat všechny běhy, aby šel počítat uptime a kreslit graf; výpadek endpointu se nesmí ztratit jen proto, že HTTP požadavek selhal. Pokud selže i databáze, runner zapíše explicitní JSONL fallback a další úspěšný běh jej importuje.

Přidána nová migrace110 a fresh-install schema tabulky health_checks s UUID deduplikací, bezpečnými stavovými poli a bez citlivého detailu chyby. Historické migrace zůstaly beze změny. Database::createConnection umožní health responderu převést DB výpadek na strukturované HTTP503 bez ukončení standardní chybovou stránkou.

Přidány vrstvy HealthCheckService, HealthCheckHttpResponder, HealthCheckRepository a explicitní HealthCheckFallbackStore. `/api/health` běží před session i visit beaconem, podporuje GET/HEAD, odmítá ostatní metody a zveřejňuje pouze agregovaný application/database stav.

CLI runner bin/health-check.php načítá explicitní HEALTH_CHECK_URL (nebo APP_URL + /api/health), vrací exit0/1 podle zdraví, zapisuje každý běh a při DB chybě používá storage/logs/health-check-pending.jsonl. Neuložení do DB ani do fallbacku je viditelná kritická chyba s exit2; žádný výpadek se tiše nezahodí.

Existující admin stránka /admin/analytics byla zachována a rozšířena, nevznikla duplicitní sekce. Nově samostatně načítá health historii, ukazuje aktuální/stale stav, uptime z provedených kontrol, chyby, odezvu a denní graf; chyba jedné tabulky neschová druhý datový tok. Sidebar používá širší název Analytika.

Analytika navíc zobrazuje posledních20 nezdravých běhů s UTC časem, HTTP stavem, DB stavem, bezpečným error_code, odezvou a informací, zda šlo o přímý cron nebo obnovený JSONL záznam.

Přidána cílená sada HealthCheckTest: kontrakt parseru, bezpečné cílové URL, HTTP200/503 responder, JSONL replay a zachování poškozeného záznamu, pořadí rout před beacon/session, absence v sitemapě, migrace, cron a admin wiring.

README, ARCHITECTURE a DEPLOYMENT nyní popisují endpoint, pořadí rout, databázový/fallback tok, retenci, stale detekci, proměnnou HEALTH_CHECK_URL a konkrétní desetiminutový Active24 cron. Staging a produkce musí používat vlastní URL i DB; žádný nový GitHub Secret není potřeba.

## Vysledek

Implementace je dokončená a cílená health sada prošla 5/5; všech 12 dotčených PHP souborů prošlo lintem a `git diff --check` je čistý. Úplná starší sada zůstává na výchozí dev větvi blokovaná existujícím Golden Bash isolation testem a nedostupnou lokální DB; tyto nesouvisející problémy nebyly skryty ani nahrazeny fallbackem. Commit `a32e1e7` byl nasazen na `dev`; staging workflow `39589135404` skončil úspěšně. Živý `https://staging.rbvideosphotos.cz/api/health` vrátil HTTP 200, `status=ok`, `database=ok`, `Cache-Control: no-store` a `X-Robots-Tag: noindex, nofollow, noarchive`. Master zůstává beze změny. Pro ukládání historie je ještě nutné spustit migraci 110 a nastavit Active24 cron.

---
# UKOL: Kruhové indikátory benefitů v homepage hero

Datum: 2026-10-20
Zadani: Opravit oválné indikátory benefitů v hero homepage a dát jim neprůhledný bílý střed.
Doporuceny model: Terra - Úzká CSS oprava s responzivní vizuální kontrolou.

## Vizuální rubrika (100 bodů)

- [x] 45 b: všechny čtyři indikátory mají přesný poměr 1:1 a shodný průměr.
- [x] 30 b: střed indikátoru je neprůhledný bílý na světlé i obrazové části hero.
- [x] 15 b: indikátory zůstávají zarovnané s textem a nedeformují rozestupy.
- [x] 10 b: desktop, 701px hranice a mobil nemají overflow ani regresi layoutu.

## Kroky

- [x] Zachytit výchozí staging a změřit computed geometrii indikátorů.
- [x] Opravit oba izolované Golden Bash motivy stejným pravidlem.
- [x] Ověřit statické testy a skutečný staging na rizikových šířkách.
- [x] Commitnout a pushnout pouze na dev; master bez nového souhlasu neměnit.

## Průběh

Výchozí staging 1440 × 900 potvrdil průhledný střed nad videem a rozdílnou vizuální šířku při delším textu. Příčinou je smrštitelný flex pseudo-element bez vlastního pozadí. Oprava přidává pevnou flex basis, minimální rozměry, aspect ratio, border-box a bílé pozadí; mobil zachovává kompaktní průměr 13 px.

## Výsledek

Commit `d98d4e9` nasadil staging workflow `39424125952` úspěšně. Chrome/CDP kontrola při 1440, 701 a 700 CSS px potvrdila shodné kruhy, bílou výplň a `scrollWidth === clientWidth`. Výchozí stav získal 55/100, finální stav 100/100. Externí video provider v automatizované relaci zobrazil vlastní restrikční text; tento síťový stav nebyl nahrazen fallbackem a nemění důkaz geometrie indikátorů. Master zůstává beze změny.

---
# UKOL: Výrazná kontaktní notifikace a bezpečný URL stav

Datum: 2026-10-19
Zadani: Zvýraznit zelenou notifikaci po odeslání a ověřit, že stav z URL nemůže vložit vlastní kód ani text.
Doporuceny model: Terra - Cílená vizuální úprava spojená s kontrolou vstupní hranice.

## Vizuální rubrika (100 bodů)

- [x] 45 b: úspěšná notifikace je na světlém formuláři okamžitě rozpoznatelná jako zelená.
- [x] 20 b: ikona a text mají dobrou čitelnost a kontrast.
- [x] 15 b: upozornění a chyby mají odlišnou oranžovou/červenou podobu.
- [x] 10 b: desktop i mobil bez přetečení nebo rozbití formuláře.
- [x] 10 b: parametr `sent` je enum/allowlist a cizí text ani HTML se nevyrenderují.

## Kroky

- [x] Zachytit a ohodnotit výchozí staging podle rubriky.
- [x] Normalizovat `sent` na enum a přidat regresní bezpečnostní testy.
- [x] Upravit barevné varianty notifikace a ověřit responzivní vzhled.
- [x] Commitnout, nasadit na staging a provést finální vizuální a bezpečnostní kontrolu. Master neměnit.

## Omezení reference

Přiložený dočasný screenshot už není na uvedené cestě dostupný. Pixelové porovnání je proto mimo rozsah; kontrola probíhá proti výše uvedeným explicitním kritériím a živému stagingu.

## Výsledek

Výchozí staging získal 42/100: zelený text i rámeček téměř splývaly se světlým formulářem. Po změně získal 98/100. Desktop 1440 × 900 a mobil 390 × 844 nemají horizontální overflow; zelený panel používá tmavý text, sytý dvoupixelový rámeček a samostatný stín. Varovné a chybové stavy mají vlastní oranžovou a červenou variantu.

`ContactSubmissionStatus` nyní přijímá pouze sedm přesných hodnot. Živá stagingová kontrola s HTML/event-handler payloadem potvrdila `NoticeRendered=false` a `PayloadReflected=false`. Produkční `master` zůstal beze změny.

---
# UKOL: Více příjemců kopií kontaktních zpráv

Datum: 2026-10-19
Zadani: Změnit jednořádkové CC pole na textovou oblast a podporovat více adres oddělených čárkami, mezerami nebo novými řádky.
Doporuceny model: Terra - Ohraničené rozšíření administrace, validace, databázového archivu a SMTP o více příjemců.

## Kroky

- [x] Navrhnout jednotnou normalizaci, validaci a úložiště seznamu CC adres.
- [x] Přidat novou append-only migraci bez změny historické migrace107.
- [x] Upravit administraci, repository, archiv a SMTP pro více CC adres.
- [x] Doplnit testy oddělovačů, duplicit, neplatných adres, prázdné hodnoty a zálohy.
- [x] Aktualizovat dokumentaci, commitnout a ověřit staging. Master bez nového souhlasu neměnit.

## Poznamky

Platné oddělovače: čárka, libovolná mezera a nový řádek. Prázdná hodnota znamená bez kopie. Každá adresa musí projít validací; neplatný seznam se nesmí uložit ani odeslat. Duplicitní CC a shoda s hlavním příjemcem se odstraní bez rozesílání duplicit.

Přidána sdílená služba `EmailRecipientList`, textarea v administraci, kanonický zápis seznamu do databáze a iterace všech CC přes PHPMailer. Nová migrace108 mění oba dosavadní `VARCHAR(255)` sloupce na `TEXT`; historická migrace107 zůstává beze změny.

Testovací databáze používají migraci108 i pro archiv, repository a reset hesla. Dokumentace DEPLOYMENT a ARCHITECTURE popisuje oddělovače, viditelnost běžného CC, odmítnutí celého neplatného seznamu a zachování databázové zálohy.

První běh repository integračního testu odhalil chybnou fixture: očekávala potlačení staré hlavní adresy po její dřívější změně. Fixture nyní používá skutečně aktuálního hlavního příjemce; produkční kód se kvůli tomuto selhání neměnil.

Ověření: parser5 kontrol, SMTP mock3 scénáře, view3 varianty, archiv28 kontrol, oprávnění13 kontrol, reset19 kontrol a kompletní pořadí110 migrací. Žádný test neposílá skutečný e-mail.

## Vysledek

Commit `38bcd81` byl úspěšně nasazen workflow39272912777 na staging. Stagingový migrátor aplikoval migraci108 a potvrdil úspěšnou databázovou údržbu bez SQL chyby. Aktualizovány byly `DEPLOYMENT.md` a `ARCHITECTURE.md`; produkční master zůstává beze změny.

---
# UKOL: Obnova kontaktních formulářů po ruční úpravě serveru

Datum: 2026-10-19
Zadani: Znovu nasadit viditelné kontaktní formuláře na homepage a /kontakt, nejprve staging a po ověření rovnou produkce.
Doporuceny model: Terra - Cílená oprava dvou šablon, regresní test a řízený deployment.

## Kroky

- [x] Porovnat formuláře v repozitáři, stagingu a produkci.
- [x] Upravit obě zdrojové šablony tak, aby je FTP synchronizace znovu nahrála.
- [x] Ověřit, že formuláře nejsou zakomentované a zůstávají funkční.
- [x] Commit, staging deploy a kontrola obou stránek.
- [x] Po výslovném souhlasu v zadání sloučit do masteru a ověřit produkci.

## Poznamky

Produkční homepage obsahovala celý druhý `.hp-contact-box` v HTML komentáři; staging jej renderoval normálně. `/kontakt` formulář renderoval na obou prostředích, ale jeho zdrojová šablona se znovu nasadí také, aby se přepsaly případné ruční úpravy FTP.

---
# UKOL: Nepovinná kopie kontaktních e-mailů

Datum: 2026-10-19
Zadani: Druhé e-mailové pole v Nastavení pro nepovinnou kopii kontaktních zpráv; prázdné pole nic nepřidá.
Doporuceny model: Terra - Rozšíření nastavení, SMTP a archivu.

## Kroky

- [x] Zmapovat nastavení, SMTP, databázový archiv a práva.
- [x] Nová migrace107, admin pole s validací a SMTP CC včetně archivu.
- [x] Ověřit prázdnou/platnou/neplatnou kopii, oprávnění a obnovu hesla bez CC.
- [x] Dokumentace/wiki, commit a staging ověření. Master bez změny.

## Poznamky

Kopie pouze kontaktních zpráv. Reset hesla se nekopíruje. Archiv se ukládá před SMTP a eviduje skutečnou CC adresu. Změna CC jen admin, jiné editory ji nesmí zrušit. Historické migrace jsou neměnné.

---
# UKOL: Hero video podle výšky a pravého okraje

Datum: 2026-10-18
Zadani: Video vždy na výšku hero při skutečném poměru, zarovnat doprava, zdroje mobil<=700, tablet701–1050, desktop>=1051.
Doporuceny model: Sol - Společná oprava JS metadat, source lifecycle a CSS.

## Kroky

- [x] Najít kolize cover výpočtu a mobilního centrování.
- [x] Doplnit height-fit a pravý okraj, přepínání zdroje při změně breakpointu.
- [x] Regresní testy poměrů a breakpointů, staging screenshoty s explicitní metadata fixture.
- [x] Dokumentace/wiki, commit a push dev. Master bez změny.

## Poznamky

Nové zadání nahrazuje předchozí offset24–48px přesným zarovnáním doprava. Gradient30–58% zachovat. Bez dostupných Vimeo metadat nelze tvrdit živou vizuální správnost; metadata fixture musí být výslovně označena.

---
# UKOL: Kratší hero gradient podle modré značky

Datum: 2026-10-18
Zadani: Ukončit gradient přibližně u pravé hrany modrého označení, aby pravá část videa nebyla vybledlá.
Doporuceny model: Terra - Cílená CSS změna.

## Kroky

- [x] Vyhodnotit značku: konec přibližně58% šířky hero.
- [x] Zkrátit smoothstep přechod30–80% na30–58%, posun a mobil zachovat.
- [x] Nasadit dev na staging a ověřit skutečné CSS/screenshot.

## Poznamky

Uživatelský screenshot přímo obsahuje chybový přehrávač; tento obraz lze použít pro vyhodnocení přechodu, nikoli jako doklad funkčního videa. Saturaci ani zdroj videa neměnit.

---
# UKOL: Plynulý přechod hero videa

Datum: 2026-10-18
Zadani: Opravit viditelný svislý zlom gradientu po posunu hero videa, zachovat jeho posun doprava.
Doporuceny model: Terra - Cílená oprava maskovacího CSS.

## Kroky

- [x] Prohlédnout uživatelský screenshot a finální masku.
- [x] Nahradit nerovnoměrné opacity body plynulým eased náběhem ve stejné oblasti.
- [x] Ověřit nasazení a computed CSS stagingu; zapsat skutečné limity ověření.

## Poznamky

Předchozí geometrický PASS nedokazoval plynulost živého videa. Uživatelský screenshot tento vizuální problém znovu otevírá. Maskování má být stejné pro poster i iframe přes společný panel; mobil bez změny.

---
# UKOL: Posun hero videa doprava

Datum: 2026-10-18
Zadani: Posunout hlavní homepage video mírně doprava a ponechat přesně původní gradient.
Doporuceny model: Terra - Cílená úprava pozice média.

## Kroky

- [x] Prověřit finální CSS pro video, poster a gradient.
- [x] Posunout desktopové video i poster stejným posunem, mobil zachovat.
- [x] Ověřit generované CSS na stagingu, dokumentovat a commitnout na dev.

## Poznamky

Posun24–48px od701px; gradientový panel zůstává na původní pozici. Přehrávání Vimeo v předchozí kontrole selhávalo, geometrii lze ověřit odděleně; bez tvrzení úspěšného přehrávání.

---
# UKOL: Větší fotografie v homepage O nás

Datum: 2026-10-17
Zadani: Zvětšit fotografii v homepage sekci O nás tak, aby na desktopu vyplnila výšku vedle textu a služeb; zachovat čitelné zobrazení na mobilu.
Doporuceny model: Terra - Cílená oprava CSS s vizuálním ověřením.

## Kroky

- [x] Zjistit aktivní šablonu a příčinu omezené velikosti.
- [x] Upravit rozměry a ověřit desktop i mobil screenshotem.
- [x] Zapsat výsledek, uložit commit a nasadit na staging; master bez změny.

## Poznamky

Cíl: větší fotografie bez deformace a přetékání. Přiložené screenshoty ukazují krátký portrét proti výrazně vyššímu sloupci služeb.

---
# UKOL: Galerie v editaci stránky

Datum: 2026-10-17
Zadani: Zpřístupnit foto a video galerie přímo z editace stránky Naše práce.
Doporuceny model: Terra - Rozšíření existující administrace.

## Kroky

- [x] Prověřit editaci stránek a portfolia.
- [x] Doplnit správu galerií bez duplikace dat.
- [x] Ověřit ovládání a zachování ukládání.
- [x] Aktualizovat dokumentaci a wiki, commit na dev.

## Poznamky

Pouze dev/staging. Produkce vyžaduje samostatný explicitní souhlas. Schválené pořadí videí zůstává zachováno.

Přidána záložka s přehledem podle content_type a odkazy do existujícího editoru, který ukládá média, videa i pořadí. Nová galerie přebírá zvolený typ. Záložka je mimo formulář stránky, bez vnořených formulářů.

## Výsledek

Nová záložka a předvolba typu galerie jsou implementované; cílený render test a PHP lint všech pěti změněných PHP souborů prošly. ARCHITECTURE.md popisuje ovládání. Živé přihlášené UI zatím neověřeno.

## Předchozí záznamy

# UKOL: Hero media zachovat v galerii a zvysit kvalitu

Datum: 2026-07-22
Zadani: Ve vsech typech clanku ponechat kazdou fotografii a video vcetne hero media v galerii pod hero a pro hero fotografie pouzit kvalitnejsi full/original variantu.

## Kroky

- [x] Zmapovat odkud blog, Nase prace a sluzby berou hero a galerijni media a kde se voli jejich varianta.
- [x] Predat blogovemu hero full/original URL misto medium varianty.
- [x] Zajistit, ze hero foto/video je vzdy prvni polozkou sdilene galerie a zadne dalsi medium se neztrati.
- [x] Doplnit regresni kontrakty pro plny seznam medii a kvalitu hero zdroje.
- [x] Overit vsechny tri typy detailu, hashovane assety a kompletni testy.
- [x] Aktualizovat dokumentaci, wiki reflexi a uzavrit ukol.

## Poznamky

Hotovo znamena: media pouzita v hero zustanou viditelna jako prvni slide galerie dole, kazde dalsi unikatni foto/video bude nasledovat prave jednou a blog hero pouzije originalni/full soubor namisto zmensene `medium` varianty. Prazdne media se nebudou nahrazovat fallbackem.

Mapovani potvrdilo: blog jako jediny posilal do hero `medium_path`; Nase prace uz pouziva `gallery[0].full` a sluzby maji originalni page/site hero URL. `BlogPostData` nyni pouziva `storage_path`, vlozi featured fotografii jako prvni galerijni zaznam a dalsi media deduplikuje podle full URL. Premium Photo blog a portfolio archetypy uz galerii neorezavaji pres `array_slice`; sdilene JS po prevzeti celeho seznamu stejnou hero URL deduplikuje, takze prvni slide zustane dostupny prave jednou.

Runtime overeni: blog detail vraci hero original `/media/...jpg`, stejna URL je prvnim galerijnim odkazem a vedle ni zustava druha fotografie. Portfolio detail vraci full hero a stejnou URL v serverovem gallery seznamu. Sluzba vraci hero i body fotografii; pokud jde o stejnou URL, JS ji spravne zobrazi jako jeden unikatni slide. `BlogPostData` kontrola potvrdila `hero_is_original=true`, `hero_is_gallery_first=true`, 2 unikatni galerijni media. PHP lint i regrese prosly 65/65 testu a 27/27 golden kontrol.

## Vysledek

Dokonceno. Hero fotografie nebo video zustava prvni polozkou kompletni galerie u detailu blogu, Nase prace i sluzeb; shodne URL se zobrazi pouze jednou. Blogovy hero pouziva originalni ulozeny soubor, zatimco zmensene varianty zustavaji jen pro nahledy. Aktualizovan je datovy kontrakt, oba detailni archetypy, regresni testy, ARCHITECTURE a projektova wiki. README ani INSTRUCTIONS se nemenily, protoze instalacni postup ani repozitarova pravidla zustala beze zmeny.

---
# UKOL: Vypnuti hero background overlayu

Datum: 2026-07-22
Zadani: Kompletne zakazat kresleni backgroundu na sdilenych hero overlay vrstvach a pseudo-elementech.

## Kroky

- [x] Dohledat globalni runtime pravidlo, ktere gradient hero overlaye vynucuje.
- [x] Nahradit gradient explicitnim `background: none !important` pro vsechny sdilene hero selektory.
- [x] Doplnit regresni kontrolu a overit generovany/runtime CSS.
- [x] Posoudit dokumentaci a wiki reflexi, uzavrit ukol.

## Poznamky

Zdroj problemu je `public/assets/frontend-runtime.css`, kde sdilene pravidlo vynucuje dva gradienty pres `!important`. Hotovo znamena, ze stejny seznam overlay selektoru zustane zachovan kvuli vrstveni, ale computed background nebude obsahovat obraz, barvu ani gradient.

Gradient byl nahrazen trojici explicitnich zakazu: `background:none`, `background-image:none` a `background-color:transparent`, vse s `!important`. To odstranuje jak obrazovy gradient, tak pripadnou plnou barvu ve stejnem runtime pravidle.

## Vysledek

Dokonceno. Globalni frontend runtime pro vsechny vyjmenovane hero overlay selektory vynucuje `background:none`, `background-image:none` a pruhlednou barvu. HTTP smoke test potvrdil novy kontrakt a absenci puvodniho gradientu. Zakladni testy prosly 65/65. README, ARCHITECTURE ani INSTRUCTIONS nevyzaduji zmenu, protoze jde o izolovanou prezentacni opravu existujici runtime vrstvy. Wiki reflexe: SKIP; architecture ma coverage `ok` a dossier uz explicitne popisuje prioritu `frontend-runtime.css`, `!important` i kontrolu hero pseudo-elementu.

---
# UKOL: Vetsi fotografie a predstaveni v O nas

Datum: 2026-07-22
Zadani: Mirne zvetsit fotografii v homepage sekci O nas a nad seznam sesti sluzeb pridat kratke predstaveni majitelu webu.

## Kroky

- [x] Zkontrolovat aktualni proporce a textovou hierarchii sekce O nas.
- [x] Zvetsit fotograficky sloupec pri zachovani responzivity.
- [x] Pridat kratky autorsky text nad seznam sluzeb a odpovidajici styl.
- [x] Doplnit regresni kontrakt a overit desktop i mobil pres Chrome CDP.
- [x] Posoudit dokumentaci a wiki reflexi, uzavrit ukol.

## Poznamky

Navrzena textace: „Jsme dva tvůrci z Olomouce, které spojuje cit pro obraz a poctivě odvedená práce. Každou zakázku tvoříme osobně – od prvního setkání až po finální fotografii a film.“ Hotovo znamena vetsi, ale neprevažujici portret, citelny uvodni odstavec pred polozkou 01 a zadny horizontalni overflow. Vizualni rubrika: proporce fotografie 30, hierarchie a text 30, seznam a CTA 20, responzivita 15, runtime 5.

Fotograficky sloupec byl rozsiren z 340 na 390 px a mezera mezi sloupci zmensena na 48 px, aby zustal dostatek mista pro text. Novy odstavec je vlozen mezi label O nas a seznam sluzeb; na sirce pod 850 px ma fotografie responzivni limit 380 px.

## Vysledek

Dokonceno. Fotografie O nas ma desktopovy sloupec 390 px a novy osobni odstavec je mezi labelem a sluzbami. Zakladni testy prosly 65/65. Chrome CDP overil 1440 px i 390 px bez horizontalniho overflow. Baseline 72/100, iterace 1 dosahla 97/100 (fotografie 30/30, hierarchie a text 29/30, seznam a CTA 19/20, responzivita 14/15, runtime 5/5). README, ARCHITECTURE a INSTRUCTIONS nevyzaduji zmenu, protoze jde o izolovanou prezentacni upravu Premium Photo. Wiki reflexe: SKIP; architecture i domain-logic maji coverage `ok` a dossier uz popisuje theme prezentacni vrstvu a Chrome screenshotovy postup. Pred commitem byly na vyslovny pozadavek odstraneny vsechny root-level QA, preview, iteration a reference screenshoty (27 souboru); obsahova media v podadresarich zustala nedotcena.

---
# UKOL: Homepage O nas a sluzby

Datum: 2026-07-22
Zadani: Odstranit z homepage celou sekci Co nabizime a v sekci O nas zobrazit sest sluzeb vcetne tlacitka Dalsi sluzby vedle tlacitka Kontakt.

## Kroky

- [x] Zmapovat homepage kompozici a datovy zdroj sekci Co nabizime a O nas.
- [x] Odstranit sekci Co nabizime bez zasahu do samostatne stranky Sluzby.
- [x] Rozsirit O nas o polozky 05 a 06 a pridat CTA Dalsi sluzby vedle Kontakt.
- [x] Doplnit regresni kontrakt a overit desktop i mobil pres Chrome CDP.
- [x] Posoudit dokumentaci a wiki reflexi, uzavrit ukol.

## Poznamky

Hotovo znamena, ze homepage nema nadpis ani karty Co nabizime, O nas obsahuje polozky 01-06 a dvojici CTA Kontakt + Dalsi sluzby bez horizontalniho overflow. Vizualni rubrika: struktura a poradi sekci 35, sest polozek O nas 25, CTA a jejich zarovnani 20, responzivita 15, runtime integrita 5.

Premium Photo homepage sklada O nas z DB-backed karet sluzeb v theme JS. Vyber byl rozsiren ze 4 na 6, puvodni `#sluzby` se po uspesnem sestaveni odstrani a jeho anchor se prenese na O nas, aby homepage navigace zustala funkcni. CTA jsou ve flex wrapperu, ktery se muze na uzke sirce zalomit.

Prvni beh regresniho kontraktu odhalil lokalni chybu noveho testu: CSS assertion odkazovala na nedefinovanou promennou. Test byl opraven tak, aby nacetl `assets/cms.css` explicitne; chyba nebyla skryta ani nahrazena fallbackem.

## Vysledek

Dokonceno. Homepage Premium Photo uz nevykresluje samostatnou sekci Co nabizime. O nas obsahuje sest DB-backed polozek 01-06 a CTA Kontakt + Dalsi sluzby; anchor `#sluzby` zustal zachovan na O nas. Zakladni testy prosly 65/65. Chrome CDP overil 1440 px i 390 px bez horizontalniho overflow. Vizualni skore iterace 1: 98/100 (struktura 35/35, sest polozek 25/25, CTA 19/20, responzivita 14/15, runtime 5/5). README, ARCHITECTURE a INSTRUCTIONS nevyzaduji zmenu, protoze datovy ani provozni kontrakt se nezmenil. Wiki reflexe: SKIP; existujici SEO Editor dossier uz pokryva DB-driven homepage kompozici a tato uprava nepridava novy rekonstrukcni mechanismus.

---

# UKOL: Sitemap Nase prace a sdilena detailni galerie

Datum: 2026-07-22
Zadani: Zahrnout publikovane clanky Nase prace do sitemap.xml a pridat do detailu blogu, Nase prace a sluzeb sdilenou JS/CSS galerii s hlavnim mediem, sipkami, teckami a plynulymi animacemi.

## Kroky

- [x] Zmapovat repozitare, view modely a sablony pro sitemap a media vsech tri typu detailu.
- [x] Rozsirit plny publish a sitemap o publikovane polozky Nase prace bez duplicit URL.
- [x] Navrhnout sdileny pristupny HTML kontrakt galerie pro obrazky i video.
- [x] Zapojit galerii do detailu blogu, Nase prace a sluzby nad skutecnymi medii.
- [x] Implementovat responzivni CSS a smooth JS ovladani sipkami, teckami, klavesnici a swipem.
- [x] Doplnit regresni testy a overit sitemap, DOM kontrakt a nacitani komponenty; browser interakce je explicitne neprovedena kvuli nedostupnemu backendu.
- [x] Aktualizovat dokumentaci, wiki reflexi a uzavrit ukol.

## Poznamky

Hotovo znamena: plny publish vlozi vsechny publikovane blogy i polozky Nase prace do sitemap bez duplicit; kazdy ze tri detailu pouzije jednu sdilenou galerii, jejiz aktivni medium se plynule meni, sipky funguji jen pri vice polozkach a pocet tecek odpovida poctu medii s jednou zvetsenou aktivni teckou. Chybejici media zustanou viditelne prazdnym stavem, nikoli skrytym nahradnim obsahem.

Mapovani: blog detail ma featured foto a `blog_media`; detail Nase prace ma `portfolio_media` a volitelne video; detail sluzby je genericka DB stranka s hero mediem a medii uvnitr HTML bloku `body`. Sdilena galerie proto bude theme JS komponenta, ktera prevezme pouze skutecne DOM zdroje, deduplikuje URL a nevytvari nahradni obrazky. Sitemap nyni sklada pages + routy + blog + publikovane portfolio a pred generovanim deduplikuje kanonickou cestu.

Theme komponenta `.detail-media-gallery` se vklada bezprostredne za detailni hero. Podporuje obrazky, nativni video a YouTube/Vimeo iframe, smyckove sipky, klikaci indikatory, klavesy Left/Right/Home/End a horizontalni swipe. Aktivni tecka se plynule roztahne z 8 na 28 px; track pouziva 720ms easing a pri `prefers-reduced-motion` se animace korektne vypnou. Puvodni samostatne gallery/video bloky a uvodni obrazek sluzby se po prevzeti odstrani, takze se medium nevykresli duplicitne.

Publish overeni: sitemap ma 35 URL, z toho 17 blogu a 5 publikovanych polozek Nase prace; chybi 0 zaznamu a vsech 35 cest je unikatnich. Tri reprezentativni detailni URL vraci HTTP 200, maji spravny hero hook a nacitaji hashovane gallery CSS/JS. Regrese prosla 65/65 PHP testu a 27/27 golden kontrol. Interaktivni kontrola pres primarni browser zustava neprovedena, protoze browser runtime vratil `No browser is available` a `agent.browsers.list()` je prazdny; tento vnejsi blocker neni nahrazen jinym ovladacem.

## Vysledek

Hotovo. Plny publish zahrnuje publikovane blogy i clanky Nase prace a deduplikuje jejich cesty. Premium Photo detail blogu, Nase prace i sluzby pouziva jednu responzivni smooth galerii nad skutecnymi obrazky a videi. Aktualizovan byl `ARCHITECTURE.md`; README ani INSTRUCTIONS nemenily provozni nebo vyvojovy kontrakt. Projektova wiki zachycuje novy sitemap i gallery pipeline.

---

# UKOL: Prevest nove realizace na blog a doplnit sitemap

Datum: 2026-07-22
Zadani: Prevest 12 novych demo realizaci z Nase prace na blogove clanky, zajistit jejich publikaci v sitemap.xml, pridat odstup prvniho media od hero a odstranit z textu citacni znacky `[1]`, `[2]` atd.

## Kroky

- [x] Zmapovat blogovy import, galerii a generovani sitemap pri plnem publish.
- [x] Prevest importer na `blog_posts` + `blog_media` a pri seedu odstranit chybne portfolio zaznamy.
- [x] Zajistit vsech 12 publikovanych URL v sitemap vcetne spravneho lastmod.
- [x] Odstranit citacni znacky ze zdrojovych Markdownu a importovaneho HTML.
- [x] Pridat svisly odstup prvniho videa/fotky od hero v obou detailech.
- [x] Doplnit testy, spustit seed/publish, overit DB, sitemap a frontend.
- [x] Aktualizovat dokumentaci a wiki reflexi; commit je posledni krok pred uzavrenim.

## Poznamky

Hotovo znamena: ukazkova URL `/svatba-sportovne-strelecky-areal-jivova-lucie-miroslav` je blogovy clanek, je v publikovane sitemap.xml, vsech 12 novych clanku je v `blog_posts`, zadny z nich nezustane v `portfolio_entries`, text neobsahuje samostatne citace `[N]` a prvni media nejsou nalepena na hero.

Pricina sitemap: `PublishService::regenerateSitemapAndRobots()` sklada publikovane stranky, vestavene routy a `BlogRepository::allPublished()`. `portfolio_entries` do sitemap zamerne nevstupuji. Importer byl prepojen na `blog_posts`; prvni medium je featured hero a pouze druhe medium se zapisuje do `blog_media`, takze se hlavni fotografie neopakuje v galerii.

Zdrojove Markdowny maji nyni `content_section: blog`, rootovou URL bez `/nase-prace/`, blogovou terminologii a nemaji zadne citacni znacky `[N]`. Importer je navic odstranuje pred renderem, aby se vycistila i drive ulozena data pri opakovanem seedu.

Premium Photo detail blogu i Nase prace ma mezi hero a `premium-article` 48px; core blog detail ma mezi `.bp-hero` a featured fotografii 42px. Tím se prvni fotografie/video nelepí na spodni hranu hero.

Overeni: seed vytvoril 17 blogu a 5 portfolio zaznamu; 12 svatebnich blogu ma 12 galerijnich medii, 0 duplicit v portfoliu a 0 clanku s `[N]`. Publikovana sitemap ma vsech 12 novych URL (30 URL celkem). Ukazkova URL vraci HTTP 200. Regrese prosla 64/64 testu a 27/27 golden kontrol.

## Vysledek

Hotovo. Svatebni realizace jsou blogove clanky a plny publish je zaradi do sitemap.xml.

---

# Archiv: Premium Photo zacatek timeline progressu

Datum: 2026-07-20
Zadani: Nechat sedou osu pres sekci, ale zlaty scroll progress zacit az pod nadpisem a skryt bod, dokud je timeline na nulovem hornim progressu.

Hotovo kdyz: Nad nadpisem neni zlata vypln, bod pri nulovem progressu neni videt, po scrollu se bod i zlata vypln objevi pod nadpisem a desktop/mobil to potvrdi cerstvymi Chrome screenshoty.

## Kroky

- [x] Zmerit geometrii nadpisu, seznamu a aktualniho progressu a zachytit baseline.
- [x] Presunout zacatek zlate vyplne pod nadpis a ridit viditelnost bodu podle progressu.
- [x] Regenerovat assety a overit horni i posunuty stav na desktopu a mobilu.
- [x] Spustit regresni testy, aktualizovat dokumentaci a commitnout bez push.

## Poznamky

- Rubrika 100 bodu: zlata cara pouze pod nadpisem 35, bod skryty na nule 30, spravny pohyb po aktivaci 20, desktop/mobil/runtime 15.
- Vychozi screenshot od uzivatele ukazuje dve vady: zlata vypln vede od horni hrany sekce pres nadpis a bod je viditelny na prvnim vrcholu jeste pred aktivnim postupem.
- Chrome diagnostika je rozsirena o computed geometrii `.section-title` a `.tl-list`, aby zacatek aktivni osy vychazel ze skutecneho DOM rozlozeni a nebyl odhadnut pevnym pixelem.
- Baseline 1920x900: sekce zacina na `4380.40625 px`, seznam na `4671.34375 px`, tedy aktivni osa musi zacit `290.9375 px` pod hranou sekce. Skore 45/100: sedy rail 15/15, runtime 10/15, ale zlata hranice 0/35 a skryti bodu 0/30.
- JS nyni pocita `trackStart` a `trackHeight` z aktualniho `getBoundingClientRect()` seznamu. CSS zlata vypln zacina na `--timeline-track-start`; bod dostane tridu `is-visible` pouze pro progress vetsi nez nula.
- Chrome helper ma novy `-TimelineStartOutput`: nastavi viewport 24 px pred aktivni zacatek seznamu a ulozi samostatny dukaz nuloveho stavu pred beznym scrolled capturem.
- Iterace 1 po oprave: 95/100. Screenshot `fixed-zero-1920.png` ukazuje pouze sedou osu pres nadpis; computed bod ma `opacity: 0` a `visibility: hidden`. Scrolled stav ma `trackStart: 290.94px`, zlatou vysku `496.66px` a viditelny bod. Poslednich 5 bodu ceka na zamerene zachyceni kratke aktivni zlate cary primo pod nadpisem.
- Iterace 2: 100/100. `final-active-start-1920.png` primo ukazuje prvnich 96 px zlateho progressu az od horni hrany seznamu pod titulkem; nad titulkem zustava jen seda osa. `final-zero-1920.png` nema zlato ani bod. Mobilni dvojice `final-zero-390.png` a `final-active-start-390.png` potvrzuje stejne chovani s dynamickym zacatkem `242.53 px`.
- Regrese: PHP lint bez chyby, `php tests/run.php` 56/56, golden checks 27/27 a `git diff --check` bez chyb. `README.md`, `ARCHITECTURE.md` a `INSTRUCTIONS.md` byly posouzeny bez nutnosti zmeny; technicky kontrakt a acceptance byly aktualizovany v projektove wiki.

## Vysledek

Hotovo. Seda osa zustava pres sekci, zlata vypln zacina az pod nadpisem na skutecne hrane seznamu a bod je pri nulovem progressu skryty na desktopu i mobilu.

---
# UKOL: Opravit duplicitni obrazky v nahledech clanku

Datum: 2026-07-22
Zadani: Opravit karty clanku na homepage, `/nase-prace` a `/blog`, aby jeden obrazek nebyl soucasne pozadim i `<img>` a media mela jednotny filmovy letterbox s hornim a spodnim rameckem.

## Kroky

- [x] Najit sdileny zdroj duplicitniho background-image a `<img>` ve vsech trech vypisech.
- [x] Upravit sablony a CSS tak, aby `<img>` vyplnilo sirku, zachovalo pomer stran a melo cerny horni i spodni filmovy ramecek.
- [x] Doplnit regresni kontrakt pro homepage, Nase prace a Blog.
- [x] Overit runtime DOM, computed styly, desktop screenshoty a mobilni responzivitu.
- [x] Aktualizovat dokumentaci, wiki reflexi a uzavrit checklist.

## Poznamky

Vizuální rubrika 100 bodu: bez duplicitniho media 35, jednotny letterbox/crop 25, grid a geometrie karet 15, titulky a overlay 10, desktop/mobil 10, runtime a assety 5. Povinne acceptance: zadna karta nesmi mit stejny obrazek v `background-image` i `<img>` a vsechny tri routy musi pouzit stejny media kontrakt.

Pricina: ctyri Premium Photo sablony zapisovaly stejnou URL do inline `background-image` i do polopruhledneho `<img>`. Sablony ted pouzivaji pouze `<img>` a modifier `premium-media--film`; CSS vytvari 20px cerny ramecek nahore i dole bez druhe obrazove vrstvy.

Screenshot iterace 1: 72/100. Duplicita zmizela, ale flex kontejner povazoval caption za druhy sloupec a zmensil obraz. Media je proto absolutne umistene pres celou sirku mezi filmove listy a caption je absolutni overlay.

Screenshot iterace 2: 96/100 pri 1440px desktopu a 390px mobilu. Vsechny tri routy maji plnou sirku obrazu, symetricke 20px listy, overlay caption a `scrollWidth === clientWidth`. Runtime DOM: homepage 15/15, Nase prace 12/12 a Blog 5/5 filmovych medii s `<img>`; 0 inline duplicitnich backgroundu. Lazy obrazky mimo mobilni full-page viewport se nacitaji az pri skutecnem scrollu.

## Vysledek

Opraveno ve ctyrech Premium Photo rendererech a sdilenem `cms.css`. Doplnila se kontraktni regrese, aktualizovaly se golden baselines a `ARCHITECTURE.md`. Overeni: 64/64 zakladnich testu a 27/27 golden kontrol, PHP lint, HTTP/DOM kontrola a Chrome/CDP screenshoty bez horizontalniho overflow.

---

# Archiv: Import demo clanku Nase prace z dodanych dat

Datum: 2026-07-22
Zadani: Nacist podklady z `Data pro clanky/RB_Videos_Photos_NASE_PRACE`, vytvorit z nich demo clanky Nase prace, naparovat obrazky podle obsahu a data a importovat je pres Media Library vcetne thumbnailu.

## Kroky

- [x] Inventarizovat zdrojove texty, data, obrazky a jejich vazby.
- [x] Zmapovat existujici blogovy demo seeder, Media Library a thumbnail pipeline.
- [x] Implementovat idempotentni demo import clanku Nase prace a medii.
- [x] Spustit import na demo datech a overit clanky, media zaznamy a derivaty.
- [x] Doplnit regresni testy, dokumentaci a wiki reflexi.

## Poznamky

- Primarni cesta musi pouzit skutecnou Media Library; zadne prime skryte kopirovani obrazku bez DB media zaznamu a generovani thumbnailu.
- Parovani ma vychazet z obsahu, nazvu souboru a datumu podkladu; nejasne nebo chybejici vazby se musi reportovat, ne nahrazovat tichym fallbackem.
- Hotovo znamena: vsechny validni podklady maji demo clanek Nase prace, spravne serazena media, prvni obrazek jako hero a fyzicky existujici thumbnail derivaty.

## Vysledek

Import vytvari 12 publikovanych zaznamu Nase prace, 24 medii a 24 thumbnailu. Stare genericke demo zaznamy Nase prace jsou pri seedu cilene odstraneny. Rozpor data u Martiny a Jaroslava je explicitne uveden ve vystupu seederu; vazba fotografii je podle unikatnich jmen paru.

Overeni: `php database/seeds/demo_content.php`; 12 DB zaznamu, 24 medii, 24 fyzickych thumbnailu; HTTP 200 pro `/nase-prace` i detail a bez uniku instrukcni casti. Zakladni sada ma 64/64 PASS. Golden-master cast hlasi 25 starsich render driftu napric nesouvisejicimi routami a nebyla hromadne prepsana.

---
# UKOL: Dokoncit vizual kontaktni sekce a FAQ CTA

Datum: 2026-07-22
Zadani: Na homepage odstranit velky kontaktni title, zrusit dvojity FAQ border a na homepage i /kontakt pridat stylove tlacitko Vsechny otazky.

## Kroky

- [x] Skryt pouze homepage kontaktni H2 bez zasahu do formulare.
- [x] Odstranit vnejsi border presunutych homepage FAQ karet.
- [x] Sjednotit CTA Vsechny otazky v obou kontaktnich sekcich.
- [x] Overit desktop/mobil, testy a publikovane vystupy.
- [x] Uzavrit wiki reflexi a commit.

## Poznamky

- Homepage FAQ CTA uz existuje v DB DOM; po presunu potrebuje theme styl. Kontakt route potrebuje stejnou CTA strukturu doplnit do archetypu.
- Jediny viditelny ramecek ma patrit elementu `details.faq-item`, nikoli jeho `.hp-faq-item` obalu.
- Desktop Chrome 1440x900 potvrdil skryty homepage H2, jeden border na kazde FAQ karte a shodne pill CTA pod otazkami v obou routach.
- Mobilni Chrome 390x844 potvrdil jednosloupcove poradi formular - FAQ - CTA a nulovy horizontalni overflow na homepage i /kontakt.
- Finalni vizualni skore 100/100. Regrese: 62/62 PHP testu, 27/27 golden kontrol a `git diff --check` bez chyb.

## Vysledek

Hotovo. Homepage nema velky kontaktni title, FAQ nema dvojite panely a obe kontaktni varianty maji stylove shodne tlacitko Vsechny otazky.

---
# UKOL: Pixelove sjednotit kontaktni formular homepage a /kontakt

Datum: 2026-07-22
Zadani: Odstranit zbyvajici vizualni rozdil formulare na homepage proti formulari na samostatne kontaktni strance.

## Kroky

- [x] Porovnat sdileny partial a oba rodicovske DOM/CSS kontexty.
- [x] Sjednotit wrapper, sirku gridu a stretch formulare.
- [x] Pridat regresni kontrakt a obnovit publikovane vystupy.
- [x] Overit homepage a /kontakt ve stejnem desktop a mobilnim viewportu.
- [x] Uzavrit wiki reflexi a commit.

## Poznamky

- Pole uz generuje stejny partial; rozdil zpusobuje odlisna hierarchie grid itemu a implicitni sirka rodicovskeho kontejneru.
- FAQ preview zustava na homepage i /kontakt vpravo na desktopu a pod formularem na mobilu.
- Oba vstupy nyni pouzivaji `.premium-contact-form-wrap` a explicitni 1120px shell se stejnym 48px desktop a 32px mobilnim viewport gutterem.
- Chrome 1440x900: oba formulare maji stejny levy okraj 160 px a sirku 530 px. Chrome 390x844: oba maji levy okraj 16 px a sirku 358 px; horizontalni overflow je nulovy.
- Finalni vizualni skore 100/100. Regrese: 62/62 PHP testu, 27/27 golden kontrol a `git diff --check` bez chyb.

## Vysledek

Hotovo. Homepage a `/kontakt` sdileji stejny partial, wrapper, grid shell, sirku, paddingy, pole, textarea i tlacitko na desktopu a mobilu.

---
# UKOL: Sjednotit hero Ceniku se standardem vnitrnich stranek

Datum: 2026-07-22
Zadani: Doplnit Ceniku horni produkcni meta radek a sjednotit sekcni label, H1, popis i jejich pozici s referencnim hero Blogu.

## Kroky

- [x] Porovnat DOM a geometrii Ceniku se standardnim hero kontraktem.
- [x] Doplnit horni meta radek a sjednotit jeho styly s video-band hero.
- [x] Pridat regresni kontrakt a aktualizovat generovane vystupy.
- [x] Overit desktop a mobil v Chrome.
- [x] Uzavrit checklist, wiki reflexi a commit.

## Poznamky

- Zdrojovy DB nadpis a popis zustavaji editovatelne; theme doplnuje jen prezentacni labely stejne jako u ostatnich Premium Photo hero variant.
- Acceptance: horni radek ma levou REC, stredove `CENIK · BALICKY NA MIRU` a prave `RB PRICING`; obsahova osa odpovida Blogu a nema horizontalni overflow.
- Chrome 1440x900 potvrdil shodnou 1120px osu meta radku a obsahoveho bloku; H1, sekcni label i popis jsou uvnitr obrazoveho hero.
- Chrome 390x844 potvrdil spravne poradi obsahu a nulovy horizontalni overflow. Cenik-other zustal na obou view-portech presne uprostred s delta 0 px.
- Finalni vizualni skore 100/100. Regrese: 62/62 PHP testu, 27/27 golden kontrol a `git diff --check` bez chyb.

## Vysledek

Hotovo. Ceník sdili s Blogem horni produkcni radek, levou obsahovou osu, sekcni label, velke H1 a popis v jednotne vysce hero.

---

# UKOL: Premium Photo viditelny scroll playhead

Datum: 2026-07-20
Zadani: Udelat pohyb timeline pri scrollovani jednoznacne viditelny vyraznym bodem a postupne vyplnovanou stredovou osou a dokazat zmenu screenshotem primo v timeline.

Hotovo kdyz: Bod je na desktopu i mobilu jasne viditelny, meni polohu podle scroll progress, vypln osy konci pod bodem a Chrome dukaz zachyti rozdilne stavy timeline.

## Kroky

- [x] Zmerit aktualni playhead a pripravit zamerene Chrome zachyceni timeline.
- [x] Doplnit vyrazny bod a scroll progress vypln osy.
- [x] Overit pred/po scrollu, mobil, testy a publikovany vystup.
- [x] Aktualizovat dokumentaci a commitnout opravu bez push.

## Poznamky

- Predchozi full-page capture prokazal zmenu inline `top`, ale bod byl v celkovem zmenseni prakticky necitelny. Nova acceptance vyzaduje nejen metriku, ale zamerene viditelny stav.
- Rubrika 100 bodu: viditelnost bodu 35, viditelny pohyb 30, progress vypln osy 20, desktop/mobile a runtime 15.
- Theme JS nyni zapisuje stejny `progressPercent` do `--timeline-progress` i `playhead.style.top`. CSS zobrazuje 20px zlaty bod s bilym stredem a vyplnuje osu od zacatku po aktualni polohu, takze scroll stav je citelny i bez sledovani samotneho bodu.
- Chrome CDP helper dostal volitelny `-InteractionOutput`: po testovacim scrollu ulozi samostatny viewport screenshot jeste pred navratem na zacatek a vedle nej zachova plny page capture. Tim lze vizualne dokazat bod primo v jeho posunute poloze.
- Prvni zamereny screenshot spravne odhalil, ze live HTML stale nacital predchozi generovany hash CSS/JS; zdrojove zmeny proto nebyly vizualnim dukazem. Pred dalsim skore je povinna regenerace publish/golden assetu a novy Chrome capture nad novym hashem.
- Druhy diagnosticky capture odhalil skutecnou pricinu: computed `position: relative` na ose i bodu a vysku osy `0 px`. Inline posun na `71.4071%` proto bod nechal u nadpisu. Finalni theme vrstva nastavuje explicitni `position: absolute`, desktop/mobile `left`, a osu rozpina pres `top: 0; bottom: 0`.
- Desktop dukaz `storage/qa/premium-photo-timeline-fade/fixed-playhead-scrolled-1920.png`: osa `3 x 1092.765625 px`, bod `20 x 20 px`, posun `0% -> 72.0734%`, konec bodu ve viewportu `y=394.984375`.
- Mobilni dukaz `storage/qa/premium-photo-timeline-fade/fixed-playhead-scrolled-390.png`: osa `3 x 1236.71875 px`, leva kotva `x=24`, posun `0% -> 65.7033%`, bod ve viewportu `y=369.796875`.
- Vysledna iterace: viditelnost 35/35, prokazany pohyb 30/30, progress vypln 20/20, desktop/mobile/runtime 15/15 = 100/100. `php tests/run.php`: 56/56 a golden checks 27/27; PHP lint a `git diff --check` bez chyb.

## Vysledek

Hotovo. Bod je ukotveny k cele timeline, pri scrollovani se pohybuje spolu se zlatou vyplni osy a focused Chrome screenshoty potvrzuji desktop i mobil.

---

# UKOL: Premium Photo prechody a timeline

Datum: 2026-07-20
Zadani: Na homepage odstranit bile fade pasy a nepatricnou hnedou mezi portfoliem a procesem a obnovit viditelnou stredovou caru timeline s teckou pohybovanou podle scrollu.

Hotovo kdyz: Portfolio a timeline navazuji cistym tmavym pozadim bez bilych fade efektu a hnede, stredem timeline vede viditelna cara, tecka meni polohu pri scrollu a cerstvy Chrome screenshot i interakcni metriky opravu potvrdi.

## Kroky

- [x] Zachytit baseline a zmerit CSS konflikt prechodu, barev a timeline vrstev.
- [x] Opravit Premium Photo CSS bez zasahu do DB obsahu.
- [x] Overit desktop, mobil, scroll tecku a vizualni skore.
- [x] Spustit testy a aktualizovat relevantni dokumentaci.

## Poznamky

- Rubrika 100 bodu: ciste hranice sekci 40, spravne tmave barvy 20, stredova cara a tecka 25, scrollova interakce 15.
- Podezreni ze zdroje: globalni `frontend-runtime.css` pridava homepage sekcim svetly pseudo-element `::before` a hnedou `section--dark`; Premium Photo musi tyto legacy efekty explicitne zrusit ve vlastnim scope.
- Baseline: `storage/qa/premium-photo-timeline-fade/baseline-home-1920.png`, Chrome 1920x900, full-page 8330 px, skore 20/100. Timeline DOM i JS existuji a playhead meni `top` 0 -> 66.7483 %, ale legacy fade, hnede pozadi a slabe/prebite vrstvy caru a tecku vizualne skryvaji.
- `cms.css` nyni v Premium Photo homepage scope rusi legacy sekcni `::before`, sjednocuje portfolio a proces na `var(--night)` a explicitne definuje desktop/mobile osu, karty, spojky a viditelnou scroll tecku.
- Iterace 1: `iteration-01-home-1920.png`, Chrome 1920x900, full-page 8159 px, skore 93/100. Fade i hneda jsou odstraneny, sekce navazuji jednim tmavym platnem a timeline geometrie je obnovena; osa byla pro finalni iteraci zesilena kvuli kontrastu v plnem screenshotu.
- Iterace 2: `iteration-02-home-1920.png`, Chrome 1920x900, full-page 8161 px, skore 100/100 pro zadane prechody/barvu/timeline. Tecka se posunula 0 -> 71.6135 % a aktivovala 4 polozky. Mobil `iteration-02-home-mobile-390.png` ma 390x11848 px a tecka se posunula 0 -> 65.3677 %.
- Do `PremiumPhotoDataContractTest.php` byla pridana regresni brana pro zruseni legacy fade, tmave portfolio, osu/tecku a JS vazbu tecky na scroll progress.
- `php tests/run.php`: 56 testu a 27 golden kontrol, 0 chyb; zmenene live/publish goldeny byly vygenerovany stejnou render pipeline. README ani ARCHITECTURE se nemeni, protoze jde o theme-specific kaskadovy fix; rekonstrukcni postup je doplnen v projektove wiki.
- Wiki `projects/seo-editor/pipelines.html` obsahuje pricinu, minimalni CSS/JS jadro a desktop/mobile acceptance; samostatny wiki commit `5c33595`.

## Vysledek

Hotovo: homepage nema legacy bile fade ani hnedou sekci, portfolio a proces sdileji designerovo tmave pozadi a timeline ma na desktopu i mobilu viditelnou stredovou osu, spojky a scrollovanou tecku. Finalni zamerene vizualni skore je 100/100.

---

# UKOL: Premium Photo specificke DB HTML snippet rendery

Datum: 2026-07-20
Zadani: Nahradit vychozi core HTML struktury vsech DB-backed bloku vlastnimi Premium Photo PHTML partialy a archetypy, zachovat realna systemova data a iterovat podle cerstvych Chrome screenshotu s poctivym 100bodovym hodnocenim.

Hotovo kdyz: Homepage i vsechny referencni podstranky pouzivaji designerovu HTML kompozici nad DB daty, parallax a scroll timeline funguji, Chrome CDP screenshoty na zamknutem viewportu projdou povinnou vizualni rubrikou a testy live/publish neukazuji regresi.

## Kroky

- [x] Overit target PNG, runtime, Chrome CDP capture a vytvorit baseline se 100bodovou rubrikou.
- [x] Zmapovat core homepage partialy a specialni archetypy na designerovu HTML strukturu.
- [x] Implementovat Premium Photo partial overridey pro sluzby, portfolio, blog, recenze, FAQ a kontakt.
- [x] Implementovat Premium Photo archetyp overridey pro portfolio, blog/detail, recenze, FAQ a kontakt.
- [x] Opravit hero media, parallax, timeline a responsive interakce nad novym DOM.
- [x] Opakovat Chrome screenshot-score iterace do maximalni poctive shody.
- [x] Overit live/publish, PHP testy, generated assety a vsechny referencni routy.
- [x] Aktualizovat wiki a vse commitnout bez push.

## Poznamky

- Pricina potvrzovana: DB zdroj dat je spravny, ale core PHTML partialy/archetypy nesou vychozi DOM, ktery neodpovida designerove Premium Photo kompozici.
- Rubrika: globalni geometrie 20, header/nav 10, hero 15, sekcni layouty 20, typografie 10, karty/media 10, interakce 5, kontakt/footer 5, runtime integrita 5.
- Iterace 0: `storage/qa/premium-photo-db-snippets/baseline-home-1920.png`, viewport 1920x900, full-page 1920x9293, skore 49/100. Chrome overil parallax a timeline playhead 0 -> 68.4192 %, 4 aktivni body.
- Nejvetsi baseline problem: core DOM obaly meni kompozici, poradi a vysku sekci; DB data jsou pritom pritomna a runtime bez chyby.
- Pridany theme-specific homepage partialy pro vsech sest datovych oblasti; PHTML prebiraji pouze ViewModel data a produkuji designerovy `tile`, `ph`, `review`, `faq-item`, `field` a `btn` DOM.
- Designerovy sekcni nadpisy a uvody byly presunuty do idempotentniho demo DB seedu; theme je nebude doplnovat skrytym JavaScript fallbackem.
- Theme JS je idempotentni a sklada DB sekce do designerova poradi; cenik na homepage skryva, FAQ presouva do kontaktni mezisekce a zachovava timeline mezi portfoliem a blogem. CMS CSS styluje nove PHTML DOM bez core card selektoru.
- Iterace 1: `iteration-01-home-1920.png`, 1920x7810, 82/100. DOM a poradi odpovidaji reference podstatne lepe; nejvetsi zbyvajici konflikt je legacy svetle pozadi timeline a tmave pozadi recenzi.
- Pridano sedm Premium Photo archetypu pro seznam/detail portfolia, seznam/detail blogu, recenze, FAQ a kontakt. Kazdy renderuje designerovy `video-band`, `page-head`, `tiles` a CTA/form struktury z predanych ViewModel dat.
- CSS nyní explicitně přebíjí legacy pozadí timeline/recenzí a definuje geometrii theme archetypů, médií, FAQ, článků a kontaktních formulářů.
- Iterace 2: `iteration-02-home-1920.png`, 1920x7810, 89/100. Timeline je tmava a recenze svetle; zbyvaji proporce a kontrola vsech podstranek.
- Doplněn Premium Photo archetyp `/sluzby`, takže ani seznam služeb nepoužívá core service-card HTML.
- Opraven dvojity navigacni `<nav>` z layoutu. Recenze jsou zamerne jen block archetyp uvnitr generic DB stranky; page view a JS premisti jeji core hero copy do designerova `page-head`, aby nevznikal zanoreny cely dokument.
- Iterace 3: horizontalni systemova navigace a samostatny recenzni blok jsou opraveny. Designeruv obrazovy triptych mezi hero a sluzbami se sklada z URL databazoveho bloku `jb-about-visual__media`; sablona nevklada nahradni staticky obrazek.
- Iterace 4: `iteration-04-home-1920.png`, 1920x8330, poctive skore 91/100. Parallax 1.3 -> -40.9 px, timeline playhead 0 -> 66.7483 %, 4 aktivni polozky. Mobilni 390x11756 drzi jednosloupcovou kompozici.
- Referencni routy po vizualni kontrole: homepage 91, Nase prace 88, Blog 92, detail blogu 90, Recenze 89, FAQ 92, Kontakt 93 bodu ze 100. Rozdily obsahu jsou realna DB media/polozky, ne staticke nahrady.
- PHP lint vsech novych PHTML/PHP souboru je cisty. `php tests/run.php`: 55 testu a 27 golden kontrol, 0 chyb. Vsechny hlavni routy vcetne `/admin/login` vraceji HTTP 200.
- Wiki pipeline byla aktualizovana o theme snippet cascade, generic reviews wrapper a aktualni Chrome dukazy; samostatny wiki commit `caf3d8f`.

## Vysledek

Hotovo: Premium Photo pouziva vlastni HTML renderery nad DB ViewModel daty, designerovy DB triptych, animovany parallax/timeline a vsechny referencni routy maji cerstvy Chrome vizualni audit. Kod, publish vystupy, goldeny, testy a dokumentace jsou pripraveny k samostatnemu commitu bez push.

---

# UKOL: Premium Photo napojit zpet na databazi

Datum: 2026-07-20
Zadani: Zachovat dodany Premium Photo design, ale odstranit staticky obsah motivu a nacitat hero media, sluzby, portfolio, blog, recenze, FAQ, kontakty a socialni site z existujicich systemovych dat; lepsi texty sluzeb zapsat do demo seedu.

Hotovo kdyz: Homepage i vsechny podstranky zobrazuji designeruv vzhled nad realnymi DB daty, hero media respektuji nastaveni systemu, timeline zustane zachovana, prvni tri FAQ a vsechny kontaktni/socialni udaje pochazeji z DB a live i publish testy prokazi stejne zdroje bez statickych nahrad.

## Kroky

- [x] Zmapovat aktualni DB view modely, route renderery a staticke Premium Photo overridey.
- [x] Nahradit staticke hero video/obrazky DB-backed hero kontraktem na homepage i podstrankach.
- [x] Prevest homepage O nas/Sluzby vcetne fotografie na DB data a aktualizovat demo seed lepsimi texty.
- [x] Prevest homepage galerii na realne portfolio Nase prace a blog na realne blogove zaznamy.
- [x] Prevest recenze, prvni tri FAQ, kontakty a socialni site na realna systemova data vcetne sdilenych mezisekci.
- [x] Odstranit staticke obsahove route layouty; zachovat pouze prezentacni markup a animovanou timeline.
- [x] Overit live i publish, DB puvod dat, interakce a relevantni dokumentaci; screenshot zachyt blokuje nedostupna browser instance.
- [x] Dokoncit wiki reflexi a samostatny commit bez push.

## Poznamky

- Predchozi Premium Photo implementace dosahla vizualni shody kopii celeho referencniho HTML. To je ted vedome povazovano za architektonickou chybu: tema smi vlastnit design, ne obchodni obsah.
- Vychozi generovane publish vystupy byly pred zmenou ulozeny samostatnym commitem `d9020f7`.
- Historicke migrace se menit nebudou; obsah sluzeb ma jit pres idempotentni `DemoContentSeeder`.
- Theme manifest nyni pouziva jeden sdileny layout. Staticke route layouty a page views byly odpojeny; homepage renderuje DB fragment a footer i navigaci znovu plni systemova pipeline.
- `DemoContentSeeder::seedServiceItems()` obsahuje sest designerem dodanych sluzeb vcetne DB media URL a systemovych odkazu; zadny z techto textu uz nemusi zit v sablone.
- Homepage umi vykreslit vsech sest DB sluzeb a kontaktni mezisekce omezuje FAQ na pozadovane prvni tri aktivni polozky.
- Premium Photo CSS mapuje DB komponenty na designerovy grid, karty, tmave sekce a formulare. JS meni existujici DB proces na timeline s playheadem; nepridava zadny obsah.
- Mrtve staticke route kopie byly odstraneny. Novy kontraktovy test hlida DB fragment, dynamickou navigaci/footer a zakazuje navrat statickeho hero videa ci kontaktu.
- Live i publish: 6 sluzeb, 5 portfolio polozek, 3 blogy, 10 recenzi, 3 FAQ a realny kontakt/socialni site. Vsechny hlavni URL i `/admin/login` vraceji HTTP 200. `php tests/run.php`: 54 testu + 27 golden kontrol, vse bez chyby.
- Browser plugin nema v teto relaci zadnou dostupnou instanci, proto nebyl predstiran novy vizualni screenshot; HTML/HTTP/publish kontrakty jsou overene.
- `sync_premium_photo_reference.ps1` uz negeneruje staticke stranky. Kontroluje zdroje a DB-first kontrakt a pri pokusu o navrat route kopii viditelne selze.
- Wiki pipeline byla aktualizovana a samostatne commitnuta jako `0993f9e`.

## Vysledek

Premium Photo je znovu DB-first: tema vlastni design, zatimco hero media, sluzby, portfolio, blog, recenze, FAQ, kontakt a socialni site dodava system. Technicke testy a live/publish data prosly; novy vizualni screenshot zustava otevreny pouze kvuli nedostupne browser instanci v teto relaci.

---

# UKOL: Refactoring šablonování na PHTML systém (7 fází)

Datum: 2026-07-17
Zadání: Přestat šablonovat přes HTML layouty s hardcodeovaným generováním HTML v PHP; implementovat čistý PHTML view systém s per-stránka/archetyp overridey a jedním sjednoceným render pipeline pro live i publish.

Hotovo když: Všech 7 fází (viz `TEMPLATE_REFACTOR_PLAN.md`) je implementováno a commitnuto; golden testy projdou nebo mají výslovně schválené rozdíly a visual QA na 390×844 i 1920×1080 neodhalí regresi v adminu, frontendu, publishi, tématech ani médiích.

## Kroky

### Fáze 0 — Golden-master testy [HOTOVO]

- [x] Golden runner pro 12 live rout a 14 publish cílů, normalizace dynamických hodnot a LCS diff.
- [x] Guard test bootstrapu v `public/index.php`; režimy `--update-goldens` a `--test-goldens` v `tests/run.php`.
- [x] Výchozí baseline vytvořena a ověřena; commit `88acda8` (`test(templates): add golden-master runner...`).

### Fáze 1 — View engine core [HOTOVO]

- [x] `App\View\ViewEngine`, `ViewResolver`, `ViewContext`, `LegacyLayoutView` a fixture/unit testy.
- [x] Resolver chrání před cestami mimo root; engine podporuje escaping, partials, sloty, layouty a atributy inline editoru.
- [x] Ověřeno bez aktivního použití v produkční cestě: 36 testů + 26 golden checků, 0 failures.
- [x] Commit `refactor(templates): add PHTML view engine core`.

### Fáze 2 — Sjednocení pipeline (PageComposer) [HOTOVO]

- [x] Sdílené renderovací funkce přesunuty z `public/index.php` a `PublishService` do `App\Services\Render\*`; `PageComposer` má módy live/publish.
- [x] Zachovány bezpečnostní hranice (žádné SQL v Render, CSRF/auth/realpath beze změny) a jediný kanonický zdroj přesunutých funkcí.
- [x] Golden 26/26 OK bez regenerace baseline; 36 testů + 26 golden checků, 0 failures.
- [x] Commit `0cc77c9` (`refactor(templates): unify live and publish render pipeline`).

### Fáze 3 — Layout a chrome do PHTML [HOTOVO]

- [x] `layouts/default.phtml` a partials pro nav, logo, footer, sociální sítě, hero, breadcrumb a cookie consent.
- [x] Sdílené CSS přesunuto byte-identicky do `app/Views/assets/shared-components.css`; `elegant-white3` používá `views/layout.phtml` přes `ViewFactory`/`ThemeLayoutRenderer`.
- [x] Golden 26/26 OK; vizuální QA homepage a `/cenik` na mobilu i desktopu bez regrese.
- [x] Post-commit review: bezpečnost, escaping, speciální routy, footer/nav právě jednou a 45 testů + 26 golden checků bez failures.
- [x] Commit `91b3b44` (`refactor(templates): move layout chrome to PHTML partials`).

### Fáze 4 — Speciální archetypy [HOTOVO]

- [x] PHTML/ViewModel refaktor: `faq`, `sluzby`, `cenik`, `hodnoceni`, seznam a detail blogu, seznam a detail portfolia.
- [x] Live/publish sdílejí relevantní render cesty; `cenik` používá `CenikPageRenderer::inject()` v `composeLive` i `composePublish`.
- [x] Portfolio a blog detail jsou live-only; samostatný `PortfolioPublishService::publishEntry()` nebyl měněn.
- [x] Nezávislé review potvrdilo správnější publish obsah, zachování bezpečnosti/inline editoru a testy 45 + 26 golden bez failures.
- [x] Visual QA `/faq`, `/sluzby`, `/cenik`, `/hodnoceni` na 390×844 a 1920×1080: HTTP 200, bez PHP warningů a bez layout regresí.

### Fáze 4b — post-commit review [SCHVÁLENO S OPRAVOU]

Nezávislé bezpečnostní/korektnostní review commitů `9c00b16` (blog-listing), `d0eb098` (blog-detail), `417013c` (portfolio-listing), `e3667a0` (portfolio-detail). Verdikt: **SCHVÁLENO S OPRAVOU**.

- [x] **Bod 1 — undefined `$cardsHtml` (POTVRZENO + OPRAVENO):** `public/index.php` `tryServeBlogListingPage()` fallback větev (`else` po `if ($themeActive)` / `elseif layout_html`) odkazovala na nedefinovanou `$cardsHtml` (zavlečeno `9c00b16` při náhradě card-loopu archetypem). Větev je nedosažitelná za současné konfigurace: `applyThemeTemplate()` vrací `$themeActive = true` vždy, když `ThemeTemplateRegistry::resolveForSite()` najde téma — aktivní `elegant-white3` se vždy resolvne, takže se bere `if ($themeActive)` větev. `else` by se spustila jen kdyby (a) žádné téma se neresolvlo A (b) `site.layout_html` byl prázdný → pak PHP 8 warning „Undefined variable“ + prázdný `.bl-grid` (blog bez článků). Opraveno stejným vzorem jako portfolio: dvě hardcoded `<section>` nahrazeny `{$pageContent}` (archetyp už renderuje hero+grid). Commit `fix(templates): fix undefined variable in blog listing fallback branch`.
- [x] **Bod 2 — `PortfolioPublishService` divergence (POTVRZENO — BLOCKER pro Fázi 6, neopraveno):** viz sekce Fáze 6 níže.
- [x] ViewModely (`BlogListingData`, `PortfolioListingData`, `PortfolioDetailData`, `BlogPostData`): jen skaláry/pole, žádné HTML, media přes `MediaUrlResolver::publicMediaUrl()` (root-relative); repozitáře (`BlogRepository`/`PortfolioRepository`/`MediaRepository`) používají prepared statements s bind parametry.
- [x] Archetypy: veškerý dynamický výstup přes `$this->e()`; `raw()` jen na `entry.description` (portfolio-detail) a `post.content` (blog-detail) — admin rich-text sanitizovaný dále v pipeline (`sanitizeFrontendHtml`), shodné s původním heredocem.
- [x] Inline editor klíče 1:1: blog-listing `hero_h1`/`hero_sub` + `blog:{id}:title`/`blog:{id}:excerpt`; portfolio-listing `portfolio:{id}:title` — ověřeno proti `9c00b16~1`/`417013c~1`.
- [x] Golden diffy (blog + nase-prace publish, CHANGED/schválené): směr správný — původní publish výstup měl artefakt `<?xml encoding="UTF-8">` a PRÁZDNÝ výpis (karty se generovaly jen v `public/index.php`, z `PublishService` nedosažitelné); nový výstup je čistý a obsahuje všechny karty. Bez ztráty obsahu.
- [x] Testy: `php tests/run.php` → 45 testů + 26 golden, 0 failures. Oprava bodu 1 nemění dosažitelný výstup (goldeny beze změny).
- [x] Visual QA (headless Chrome, 1920×1080 + 390×844): `/blog`, `/nase-prace`, blog detail, portfolio detail → HTTP 200, bez PHP warningů, profesionální vzhled bez layout regrese (téma elegant-white3, karty, hero, gold CTA). Pozn.: iframe videa na portfolio detailu blokuje síť headless prostředí (externí obsah, ne layout).

### Fáze 4c — kontakt archetyp (poslední, nejrizikovější) [HOTOVO]

Datum: 2026-07-18/19. Poslední z 9 archetypů — obsahuje CSRF, honeypot, throttling. Scope = JEN GET render `/kontakt`. Dokončeno přímo v hlavní session (Sonnet 5) po dvou po sobě jdoucích selháních subagentů kvůli měsíčnímu spending limitu (viz Poznámky) — pokračovalo se přesně tam, kde skončila rozdělaná práce (`kontakt.css` byl už hotový).

- [x] Ověřena skutečná architektura `tryServeKontaktPage()` (~400 ř. heredoc, plná stránka = Vzor A) a POST handler `/kontakt/odeslat` (samostatná větev v `public/index.php`, zcela mimo scope, beze změny)
- [x] `app/Views/assets/kontakt.css` — ověřeno bytově identické s původním heredoc CSS (po EOL normalizaci CRLF→LF, `diff` čistý)
- [x] `App\ViewModels\KontaktPageData::forSite(int $siteId, string $csrfToken, ?string $sentState): array` — čistá data: `contactCards` (phone/email/location/note karty s `inlineKey`), `mapsUrl`, `heroActions` (tel/email), `faqItems` (max 4 aktivní), `form` (csrfToken/honeypotField/sentState). CSRF token je parametr, **nikdy se negeneruje uvnitř ViewModelu**.
- [x] `app/Views/archetypes/kontakt.phtml` — loopy přes `contactCards`/`faqItems`, `$this->e()`/`$this->inlineAttr()`; identifikován a odstraněn `dead code` (starý heredoc počítal `$detailsHtml`/`$mapsHtml` DVAKRÁT — první `ci-row`/`ci-brand`/socials verze byla vždy okamžitě přepsána druhou `ci-card` verzí, tedy 100% nedosažitelná; ověřeno, že žádný jiný kód na ni neodkazuje, než byla vynechána)
- [x] Napojeno `tryServeKontaktPage()` na `ViewFactory::forTheme(null, ['editMode'=>...])->render('archetypes/kontakt', ...)`; smazán heredoc (−324 řádků / +21 řádků v `public/index.php`); POST handler (`/kontakt/odeslat`, CSRF verify, honeypot/throttling) **beze změny, nedotčen**
- [x] Golden: `kontakt live: OK` (prázdný diff), `kontakt publish: OK` (publish větev skutečně nezměněna — `PublishService` pro kontakt nebyl vůbec upraven, dle plánu z rozdělané práce)
- [x] Bezpečnostní regresní test (curl na živém serveru): CSRF token přítomen a neprázdný (`name="_csrf" value="<64hex>"`), honeypot přítomen (`name="website"` — post-processing `normalizeContactFormAccessibility` ho transformuje na `class="hp-honeypot"` stejně jako předtím, ověřeno shodné pro starý i nový render přes golden byte-parity), action URL `/kontakt/odeslat` beze změny, žádné PHP warningy/notice v HTML výstupu, HTTP 200
- [x] Inline editor sanity: přímý `ViewFactory::forTheme(null,['editMode'=>true])->render()` test — `data-inline-key` pro `hero_h1`/`hero_sub`/`contact:phone_1`/`contact:email`/`contact:address_1` všechny přítomné; s `editMode=false` žádný `data-inline-key` neuniká (ověřeno explicitně)
- [x] `php tests/run.php` → 45 testů + 26 golden checků, 0 failures
- [ ] Visual QA (390×844/1920×1080) — **NEPROVEDENO** v této session (žádný headless browser nástroj po ruce, jen curl/textové nástroje); vizuální riziko je nízké (live byte-identický golden = stejný markup/CSS třídy jako před refaktoringem, žádná nová CSS třída), ale doporučuji `visual-screenshot-score-loop` jako follow-up před nasazením na produkci
- [x] Commit `refactor(templates): migrate kontakt page to PHTML archetype`

**Newline gotcha (další příklad pro budoucí archetypy):** kde originál měl DVĚ samostatné řádky, z nichž jeden může být prázdný (`{$detailsHtml}` pak `{$mapsHtml}` na vlastním řádku), a nahrazuješ oba `<?php ... ?>`/`<?= ... ?>` bloky, PHP polkne newline po KAŽDÉM `?>` zvlášť — je potřeba kompenzovat PRO KAŽDÉ polknutí samostatně (zde 2× prázdný řádek, ne 1×). Odhaleno až golden diffem (+2-5 → +1-2 → 0), ne vizuální kontrolou — ukazuje, že golden byte-diff je citlivější než oko.

**BEZPEČNOSTNÍ SHRNUTÍ (pro navazující review):**
- CSRF: generuje se JEDNOU v `tryServeKontaktPage()` přes `\App\Core\Auth::csrfToken()` (nezměněné volání, jen přesunuté do proměnné `$csrfToken` předané ViewModelu/view jako data), validuje se v `/kontakt/odeslat` POST handleru přes `Auth::csrfVerify()` — tato větev nebyla vůbec dotčena.
- Honeypot: pole `name="website"` zachováno; server-side kontrola honeypotu (v POST handleru) nedotčena.
- Throttling: `RequestThrottle` (viz `use App\Security\RequestThrottle` v hlavičce) — použit v POST handleru, mimo scope této fáze, nedotčen.
- Scope striktně dodržen: změněn jen GET render `/kontakt`; `/kontakt/odeslat` POST handler nemá v diffu žádnou změnu (ověřeno `git diff` — beze změny řádků POST větve).

### Fáze 4c — post-commit bezpečnostní review [SCHVÁLENO]

Datum: 2026-07-19. Nezávislý přísný review commitu `f77049b` (nejcitlivější commit refactoringu — CSRF/honeypot/throttling). **Verdikt: SCHVÁLENO** (žádný BLOCKER, žádná oprava nutná). Výsledky všech 9 kontrolních bodů:

1. **Celý diff** — OK. 5 souborů: `UKOL.md` (+35), `KontaktPageData.php` (nový, 103 ř.), `kontakt.phtml` (nový, 68 ř.), `kontakt.css` (nový, 107 ř.), `public/index.php` (−324/+21). V `index.php` jen 2 hunky: import `use ...KontaktPageData` a přepis GET funkce `tryServeKontaktPage()`.
2. **CSRF** — OK. `\App\Core\Auth::csrfToken()` volán PŘESNĚ JEDNOU v `tryServeKontaktPage()` (`public/index.php:1940`, `$csrfToken = ...`); starý inline call ve form HTML odstraněn (jediný výskyt ve funkci). `KontaktPageData::forSite()` token jen převezme jako parametr → `form.csrfToken`, nikde negeneruje/nemodifikuje. `.phtml` token jen echuje `$this->e($form['csrfToken'])`. POST handler `/kontakt/odeslat` (`Auth::csrfVerify`) BYTOVĚ IDENTICKÝ (0 řádků diff přes 150 ř. — ověřeno `diff` pre vs post).
3. **Honeypot** — OK. Pole `name="website"` v `.phtml` přes `$form['honeypotField']='website'`; živý render potvrdil `name="website" class="hp-honeypot"`. Server-side kontrola `if (!empty($_POST['website']))` (POST handler `:2577`) nedotčená (byte-identická).
4. **Throttling** — OK. `RequestThrottle::isLocked`/`hit` (POST handler `:2582`,`:2612`) byte-identické, nedotčené.
5. **Scope** — OK. Jen 2 hunky v `index.php` (import + GET funkce). POST handler celý mimo změněný region, byte-identický. Žádný BLOCKER.
6. **Dead code / sociální sítě** — OK, tvrzení POTVRZENO nezávisle. V `f77049b~1:public/index.php` první blok (`:1942-1999`) počítá `$detailsHtml`(ci-brand/ci-row/socials)+`$mapsHtml`; řádek `:2030` `$detailsHtml = implode('', $contactCards)` a `:2031` `$mapsHtml = ...` je BEZPODMÍNEČNĚ přepíší. Mezi prvním přiřazením a přepisem se `$detailsHtml`/`$mapsHtml` jen `.=` (nikdy nečtou); `$socialsHtml` vložen jen do zahozeného prvního `$detailsHtml`. `inlineAttrs()` (`:953`) je ČISTÁ funkce bez vedlejších efektů. → sociální sítě byly už PŘED commitem 100% mrtvé (nikdy ve výstupu), odstranění = ŽÁDNÁ regrese. Definitivně potvrzeno golden byte-parity: commit se NEDOTKL žádného golden souboru a `kontakt live: OK` prochází → nový výstup byte-identický s předchozím goldenem.
7. **Testy & byte-parity** — OK. `php tests/run.php` → 45 testů, 0 failures; 26 golden, 0 failures; `kontakt live: OK` + `kontakt publish: OK`. `PublishService` NENÍ v diffu (3 výskyty „PublishService" jen v textu `UKOL.md`). Publish pipeline nedotčena.
8. **Bezpečnostní regrese (nezávisle)** — OK. Živý server `:8972`, curl `/kontakt` → HTTP 200, CSRF token přítomen a 64-hex, honeypot `name="website"` přítomen, action `/kontakt/odeslat` beze změny, NULA PHP warningů/notice v HTML, čistý server log. Inline editor: `$editMode = Auth::check() && !isset($_GET['cms_preview']) && $page !== null` je ekvivalentní staré `inlineAttrs()` gate — `PageRepository::findByUrlPath`/`findPublishedByUrlPath` vrací `$row ?: null`, tedy `$page` je jen `null` nebo neprázdné pole, takže `$page !== null` ≡ staré `!$page`. Anon režim → 0 úniků `data-inline-key` (ověřeno živě).
9. **Newline gotcha** — OK. Dočasné smazání OBOU kompenzačních prázdných řádků (`.phtml:26` a `:28`) → živý výstup CHANGED (karty/maps/`</div>` slity na jeden řádek, newline polknuty). Oba řádky nutné. Obnoveno `git checkout`, working tree čistý.

**Nálezy/opravy:** žádné. Kód je bezpečnostně korektní, byte-parita zachována, POST bezpečnostní větev prokazatelně nedotčená. Pozn.: golden běh republikoval `storage/published/*` (očekávaný, revertovatelný side-effect `git checkout -- storage/published/`).

### Fáze 4 — DOKONČENA (všech 9 archetypů), souhrn pro Fázi 5

Všech 9 archetypů migrováno na PHTML view systém: **faq, sluzby, cenik, reviews/hodnoceni** (dávka 4a), **blog-listing, blog-detail, portfolio-listing, portfolio-detail** (dávka 4b), **kontakt** (dávka 4c). `php tests/run.php` → 45 testů + 26 golden checků, 0 failures po celé fázi.

Hlavní lekce pro Fázi 5 (homepage + generické stránky):
1. **Dva platné vzory**: Vzor A (plná stránka nahrazující heredoc, jako faq/sluzby/blog/kontakt) vs. Vzor B (jen dynamická splice sekce nad generickou DB stránkou, jako cenik). Homepage bude pravděpodobně kombinace obojího (sekce jako services/portfolio/pricing/blog/faq/process/kontakt, každá potenciálně jiný vzor).
2. **Live/publish bariéra**: cokoliv, co je dnes "jen global funkce v `public/index.php`", je z `PublishService` nedosažitelné (jiný bootstrap/scope) — u homepage to bude pravděpodobně nejvýraznější, protože `injectHomepageMissingSections`/`injectHomepageThemeDataSources` (viz Fáze 2 poznámky) jsou přesně tento typ funkce.
3. **Golden diff je citlivější než oko** — vždy věřit byte-diffu, ne jen vizuální kontrole; newline-swallow chyby se projeví jen v golden testu.
4. **Otevřené BLOCKERy z předchozích fází, které Fáze 5/6 musí zohlednit:** `PortfolioPublishService` dvojí zapisovač `storage/published/nase-prace/index.html` (viz Fáze 4b review) — Fáze 5 se homepage `portfolio-tiles` bloku dotkne (`syncHomepageTiles()`/`buildTilesHtml()` v `PortfolioPublishService`), takže je vhodná příležitost vyřešit oboje najednou.
5. **Visual QA dluh**: fáze 4a/4c neměly headless browser dostupný v agent session — doporučeno spustit `visual-screenshot-score-loop` jednorázově na VŠECH 9 archetypech + homepage najednou po Fázi 5, než se přejde k Fázi 6 (úklid).

### Fáze 5 — Homepage a generické stránky [HOTOVO]

Datum: 2026-07-19. Homepage autofill (HTML → data + PHTML) a generický `page` archetyp. `php tests/run.php` → 45 testů + 26 golden, 0 failures po celé fázi (live i publish home byte-identické).

- [x] **`App\ViewModels\HomepageData`** (nový, `app/ViewModels/HomepageData.php`) — čistý data provider pro homepage sekce. Metody vrací JEN skaláry/pole, žádné HTML, žádné DB dotazy (media URL už přeložené předává volající). Kontrakt: `serviceCard($service,$index,$imageUrl,$iconUrl,$buttonText,$buttonUrl)` → `{title,description,imageUrl,iconUrl,hasIcon,num,buttonText,buttonUrl}`; `pricingCards($map)` → `list<{title,price,summary,featured}>` (summary = 1. odstavec popisu); `blogCard($post,$imageUrl)` → `{slug,title,excerpt(mb_strimwidth 120),imageUrl}`; `faqItem($row)` → `{question,answer}`; `portfolioTile($entry,$thumbUrl,$hasVideo,$photos)` → `{href,title,thumbUrl,hasVideo,photos:[{url,alt}]}`; `reviewCards($items)` → `list<{stars,review_text,author_name,context_label}>`; `sectionCta($url,$label)` → `{url,label}`; `contact($contact,$csrfToken,$cta)` → `{lines,map,cta,form:{csrfToken,showMissingEmailNote}}`.
- [x] **Homepage section partials** (nové, `app/Views/partials/home-*.phtml`): `home-service-card`, `home-portfolio`, `home-pricing`, `home-blog-card`, `home-faq-item`, `home-reviews`, `home-section-cta`, `home-contact`. Každý byte-identický s původním builderem (ověřeno golden). Newline konvence jako `cenik.phtml` (header `?>` a koncový `<?php ?>` polykají obklopující newline). Portfolio partial má vnitřní newliny tile (původně heredoc) — reprodukováno přesně.
- [x] **Co bylo nahrazeno** — `HomepageContentService` buildery přepsány z string-concat na `HomepageData` + `renderHomePartial()` (nová privátní metoda → `ViewFactory::forTheme(null)->render('partials/home-*')`): `buildServiceCardHtml`, `buildPortfolioHtml`, `buildPricingHtml`, `buildBlogCardHtml`, `buildFaqItemHtml`, `buildContactHtml`, `buildPageCtaHtml`. `ReviewContentService::buildHomepageHtml` — grid přes `home-reviews` partial (inline `<style>` z `homepageCss()` zůstává byte-identicky v service, přesun do assetu je Fáze-6 úklid). **Klíčový poznatek:** homepage NENÍ heredoc stránka (Vzor A) — je to generická DB stránka (`tryServeDatabasePage`), sekční obaly/labely/pořadí jdou z theme-specific `templates.html_source`, autofill plní jen VNITŘNÍ HTML `data-block` slotů. Block content pak prochází `PageRenderer` DOM (entity-encoding diakritiky) → partials produkují jen UTF-8 jako buildery, zbytek řeší pipeline. `injectHomepageMissingSections` je pro aktivní téma no-op (sekce už v šabloně); `injectHomepageThemeDataSources`/`injectHomepageAboutVisualSource` fungují dál beze změny.
- [x] **Žádné monolitické `archetypes/homepage.phtml`** — vědomé rozhodnutí. Homepage sekce se injektují blok-po-bloku do editovatelné DB šablony; monolitický archetyp by (a) nešlo udržet byte-identický na nejnavštěvovanější stránce a (b) zničil by theme/DB editovatelnost sekčních obalů/labelů. „archetypes/homepage.phtml se sekcemi" z plánu je realizován jako homepage section **partials** (princip „snippety = partials", plán 2.1) + homepage teče přes `archetypes/page` jako každá DB stránka. To je přesně kombinace vzorů předvídaná Fází 4 lekce 1.
- [x] **`archetypes/page.phtml`** (nový) — generický CMS archetyp. Obaluje LEGACY `PageRenderer::render()` fragment (`<?= $this->raw($fragment) ?>`, byte-for-byte passthrough). Napojeno v `tryServeDatabasePage()` (`public/index.php`, live) i `PublishService::publishPage()` (`$archetypeHtml ?? render('archetypes/page', ...)`, publish). Homepage i generické stránky teď tečou přes tentýž wrapper; data-block/inline-editor mechanika beze změny (ověřeno přímým passthrough testem: fragment s `data-inline-key` projde byte-identicky). Dedikované archetypy (faq/sluzby/…) mají v publishi přednost přes `$archetypeHtml`.
- [x] **Golden** — `home live: OK` + `home publish: OK` (prázdný diff) po KAŽDÉ dílčí sekci (faq/cta/pricing → reviews → services → blog → portfolio → contact → page.phtml). Žádná regenerace baseline. Celý běh 45 testů + 26 golden, 0 failures.
- [x] **Bezpečnost** — HomepageData 0 DB dotazů (media resolution zůstává v `HomepageContentService` přes repozitáře; `contact()` používá jen konstantu `ContactRepository::DEFAULT_FORM_EMAIL`). Escaping default (`$this->e()`); `raw()` jen na už-renderované CTA HTML a na `page` fragment (trusted DB HTML, downstream `sanitizeFrontendHtml`). **CSRF:** homepage kontaktní formulář — token generován PŘESNĚ JEDNOU v `HomepageContentService::buildContactHtml()` (`Auth::csrfToken()`), předán jako data, ve view jen escaped echo (`$this->e($form['csrfToken'])`), NIKDY negenerován ve view/ViewModelu. Honeypot `name="website"` zachován; POST handler `/kontakt/odeslat` nedotčen. Ověřeno: token escaping (`TOKEN"<>&` → `&quot;&lt;&gt;&amp;`), honeypot, action, tel-normalizace, social ucfirst. ViewResolver realpath containment nedotčen; žádné shell_exec/exec/system.
- [x] **Visual QA** (headless Chrome, dostupný v této session): homepage a `/podminky-uziti` na 1920×1080 i 390×844 → HTTP 200, 0 PHP warningů, plné elegant-white3 stylování (nav, hero+bg, gold CTA, services/portfolio/pricing/blog/reviews/contact grids), mobil responzivní (hamburger, stacked CTA). Generická stránka přes nový `page.phtml` wrapper renderuje obsahové karty korektně. Bez layout regrese. (Pozn.: byte-identický live golden + žádný přesun CSS ⇒ vizuální regrese pro aktivní téma není možná; screenshoty to potvrdily.)
- [x] Commity: viz níže.

**Rozhodnutí o `PortfolioPublishService` blockeru → ODLOŽENO na Fázi 6 (pragmaticky):** `syncHomepageTiles()`/`buildTilesHtml()` (homepage `portfolio-tiles` blok) i `regenerateListingPage()` (`/nase-prace`) zůstávají BEZE ZMĚNY. Důvody: (1) golden runner NIKDY nespouští `PortfolioPublishService` → jakákoli změna je mimo záchrannou síť, což je přesně situace, kdy se na kódu sousedícím s network-authority stránkou nemá sahat naslepo; (2) je to už zdokumentovaný Fáze-6 BLOCKER (dvojí zapisovač) vyžadující architektonické rozhodnutí (bake-on-CRUD vs autofill-on-render), patří k manifest-v2/úklidu; (3) divergence je pre-existing — Fáze 5 obohatila autofill stranu (`buildPortfolioHtml` → PHTML) přesně jako Fáze 4b obohatila `PublishService` stranu `nase-prace`, NEZHORŠILA to. **Konkrétní plán pro Fázi 6 (teď snazší):** protože `buildPortfolioHtml` je nově PHTML (`home-portfolio` partial), ideální fix je smazat `syncHomepageTiles()`/`buildTilesHtml()` a nechat homepage tiles čistě na autofill cestě (`applyFallbacks` regeneruje na každý render), případně `syncHomepageTiles` přesměrovat na veřejnou obálku `HomepageContentService`u. Stejně `regenerateListingPage()` → delegovat na `PublishService::publishPage()` / archetyp.

### Fáze 5 — poznámky pro Fázi 6

1. **Dead code po Fázi 5** (k úklidu): `ReviewContentService::buildGridMarkup()` je nyní nepoužitá (jen `buildListingHtml` a `buildHomepageHtml` volaly grid, oba už přes PHTML) — ponechána kvůli doc odkazům; smazat. `HomepageContentService::DEFAULT_FORM_EMAIL` privátní konstanta je po přesunu do `HomepageData` osiřelá (jediné užití bylo `buildContactHtml`); smazat. `HomepageContentService::buildServiceDetailHtml()` — mimo scope Fáze 5 (jiný `hp-service-detail` komponent, ne homepage autofill), ověřit dead/živý a případně migrovat/smazat.
2. **Inline `<style>` k přesunu do sdílených assetů** (byte-neutrální, golden normalizuje generated CSS hash): `ReviewContentService::homepageCss()` (`.hp-reviews-*`). Přesun do `shared-components.css` je vizuálně bezpečný, ale golden to nezachytí → nutná vizuální kontrola.
3. **`PortfolioPublishService` blocker** — viz rozhodnutí výše; konkrétní plán připraven, `buildPortfolioHtml` už PHTML.
4. **Theme override hook** — homepage section partials i `archetypes/page` teď tečou přes `ViewFactory::forTheme(null)` (core-only). Manifest v2 (`views_dir`) může téma nechat přepsat `partials/home-*` i `archetypes/page` bez zásahu do core (kaskáda už existuje, jen se nepoužívá theme root pro tyto).

### Fáze 5 — post-commit review [SCHVÁLENO]

Datum: 2026-07-19. Nezávislý review commitů `48c1c7e` (homepage) a `d64f93b` (generic page) proveden přímo v hlavní session (Sonnet 5) po selhání subagenta kvůli měsíčnímu spending limitu (agent byl ještě v read-only průzkumu, žádný soubor nedotčen). **Verdikt: SCHVÁLENO**, žádný BLOCKER, žádná oprava nutná.

- [x] **Architektonické tvrzení ověřeno**: `archetypes/page.phtml` docblock potvrzuje, že homepage i generické stránky dál tečou přes legacy `PageRenderer::render()` fragment (`templates.html_source`+`content_blocks`); partials jsou správná realizace „snippety = partials", ne monolitický archetyp — rozhodnutí je zdokumentované a odůvodněné (byte-parity + zachování theme/DB editovatelnosti sekcí).
- [x] **CSRF** — OK. `HomepageContentService::buildContactHtml()` řádek 466: `$csrfToken = \App\Core\Auth::csrfToken();` — generováno PŘESNĚ JEDNOU, předáno do `HomepageData::contact()` jako parametr (ověřeno: metoda ho jen ukládá do vráceného pole `form.csrfToken`, nikde nemodifikuje/negeneruje). `home-contact.phtml` ho jen echuje `$this->e($form['csrfToken'])`. Honeypot (`name="website" value="" autocomplete="off"`) a form action (`/kontakt/odeslat`) beze změny.
- [x] **`raw()` použití ověřeno** — 2 místa, obě oprávněná: (a) `$this->raw($cta)` v `home-contact.phtml` — `$cta` pochází z `buildPageCtaHtml()`, který sám renderuje přes `home-section-cta` partial (tedy už escapovaný nested-partial výstup, ne user input); (b) `$this->raw($fragment)` v `page.phtml` — trusted DB HTML z `PageRenderer::render()`, sanitizovaný dále v pipeline (`sanitizeFrontendHtml`) — stejný precedens jako u ostatních archetypů.
- [x] **Dead code potvrzen grepem**: `ReviewContentService::buildGridMarkup()` — 0 reálných volání (jen definice + 2 doc-komentáře). `HomepageContentService::DEFAULT_FORM_EMAIL` — jen deklarace konstanty, 0 použití v souboru. Bezpečné ponechat do Fáze 6 (jak agent navrhl).
- [x] **`HomepageData.php`** — žádné SQL (grep na SELECT/INSERT/UPDATE/DELETE/PDO/query/prepare čistý), jen datové transformace.
- [x] **Testy**: `php tests/run.php` → 45 testů + 26 golden checků, 0 failures (`home live: OK`, `home publish: OK`).
- [x] **Živá kontrola** (`php -S`, port 8973): `/` → HTTP 200, 0 PHP warningů/notice/fatal error v HTML, CSRF token přítomen a validní 64-hex formát, homepage vrací plný obsah (32 KB).
- [x] `PortfolioPublishService` odklad na Fázi 6 — rozhodnutí i zdůvodnění ověřeno jako rozumné (golden runner tuto službu nikdy nespouští, takže jakákoli změna by byla mimo záchrannou síť).

**Nálezy/opravy:** žádné.

### Fáze 6 — Theme overridy a úklid
- [ ] Delegovat na subagenta
- [x] **Manifest v2 (`views_dir`, `overrides.<pageKey>.view`) — HOTOVO.**
  - **Parsing** (`ThemeTemplateRegistry::loadTheme`): nové volitelné klíče `views_dir` (string) a per-override `view` (string). Zpětně kompatibilní — chybějící klíče → `''`, staré chování beze změny.
  - **Accessory:** `getViewsDir()`, `hasViewsCascade()` (opt-in aktivace kaskády = `views_dir !== ''`, mirror precedentu `ThemeLayoutRenderer::hasPhtmlLayout()` z fáze 3, ale řízený manifestem), `getPageViewOverride()`.
  - **Resolver:** `pageContentView(?array $theme, string $pageKey): ?array` vrací `['themeKey','view','viewsDir']` když téma deklaruje `views_dir` A poskytuje content view pro pageKey (explicitní `overrides.<pageKey>.view` NEBO `<views_dir>/pages/<pageKey>.phtml`); jinak `null`. `pageContentViewForSite()` = varianta pro live seam (resolvuje aktivní téma ze `$site`). `normalizeViewLogicalName()` akceptuje logický název (`pages/cenik`) i manifest path-form (`views/pages/cenik.phtml`).
  - **ViewFactory:** `roots()`/`forTheme()` mají nový volitelný param `?string $viewsDir` (default `views` → beze změny pro stávající callery). Nová `themeViewPath($themeKey, $view, $viewsDir)` = zobecnění `themeLayoutPath` na libovolnou view s realpath containmentem; `themeLayoutPath` na ni deleguje.
  - **Consumption (aktivace):** oba generic-page seamy — live `public/index.php::tryServeDatabasePage` (:145) i publish `PublishService::publishPage` (:112) — teď preferují theme content-view override, jinak fallback na core `archetypes/page`. Kontrakt override view: `['fragment','pageKey']` (drop-in wrapper). **KRITICKÉ pro zpětnou kompatibilitu:** aktivní téma elegant-white3 (bez `views_dir`) → `pageContentView` = `null` → core wrapper → BYTOVĚ IDENTICKÉ. Ověřeno: 26 goldenů beze změny (oba seamy jsou golden-kryté: home/generic-page/všechny publish targety).
  - **Testy:** `tests/ManifestV2Test.php` (3 nové testy) — fixture téma pod reálným `Templates/` (jediné místo, odkud ViewFactory resolvuje theme views), robustní cleanup ve `finally`. Ověřuje: parsing, explicit i path-form override, implicit `pages/<key>` fallback, `null` pro chybějící, skutečný render přes kaskádu, containment (`../../evil` → null), custom views_dir root, backward-compat (elegant-white3 → prázdné/null).
  - Aktivní téma NEBYLO měněno (žádný `views_dir` přidán do elegant-white3 — funguje dál přes file-existence layout detekci z fáze 3).
- [x] **Migrace theme hacků z core do témat — HOTOVO (CSS hacky), STRUKTURÁLNÍ podmínky ODLOŽENY s odůvodněním.**
  - **`getJemnaBezovaHomepageCssOverrides()` (ThemeTemplateRegistry ~70 ř. CSS heredoc) → `Templates/jemna-bezova/assets/homepage-overrides.css`**, napojeno přes manifest `overrides.homepage.assets.css`; theme-specific větev v `getCssForPage()` SMAZÁNA. **Ověřeno neutrální:** skript porovnal `getCssForPage('jemna-bezova','homepage')` před/po → diff = JEDEN kosmetický prázdný řádek navíc (77467→77468 B, separátor mezi theme.css a overrides `\n\n`→`\n\n\n` kvůli collectAssets join sémantice); CSS PRAVIDLA bytově identická, funkčně 0 změna.
  - **`injectEditorialGoldOverrides()` → `Templates/editorial-gold/assets/overrides.css`** (base `assets.css`, načítá se na všech stránkách jako původní all-page inject). SMAZÁNO z core: `PublishService::injectEditorialGoldOverrides()` (metoda), `PageComposer::composePublish` volání, `PublishPageChrome` interface metoda, `public/index.php::injectEditorialGoldOverrides()` (byl to LIVE NO-OP — `return $html`!) + volání v `sanitizeFrontendHtml`, a mrtvá extraction-exclusion v `MarkupNormalizer::extractInlineStyleBlocks` (`.theme-editorial-gold [data-block="reviews_cta"]`). **Objev:** live cesta byla no-op → editorial-gold overridy se aplikovaly JEN v publishi = pre-existing live/publish divergence; migrace na theme asset ji SJEDNOTÍ (live nově dostane fixy přes bundle). **Ověřeno:** CSS obsah bytově zkopírován; `getCssForPage('editorial-gold', homepage|sluzby)` nově obsahuje fixy; aktivní téma elegant-white3 nikdy nematchuje `theme-editorial-gold` → všechna odstranění jsou pro něj no-op → 26 goldenů beze změny.
  - **CAVEAT (editorial-gold, nezamlčeno):** cascade pozice se změnila z late-inline `<style>` (po celém page-css) na bundle asset. Původní design ho DRŽEL mimo bundle (extraction-exclusion) záměrně kvůli `!important` kaskádě. Selektory jsou vysoce-specifické `.theme-editorial-gold ... .btn-secondary` + `!important`, žádné konkurenční pravidlo pozdějc v head → riziko nízké, ale editorial-gold NENÍ aktivní téma a NEMÁ golden → **doporučen vizuální QA editorial-gold před produkčním použitím.** Live/publish parita je nově korektní (dřív rozbitá).
  - **STRUKTURÁLNÍ `theme-*` podmínky v `HomepageSectionRenderer` — VĚDOMĚ ODLOŽENY:** `injectHomepageMissingSections` (`theme-elegant-white2?` FAQ sekce, :103), `injectHomepageThemeDataSources` (`theme-bezova-lens-tale` :163, `theme-elegant-white2?` :181). Důvody: (1) jsou to STRUKTURÁLNÍ HTML section/data-source injekce, ne CSS — jejich přesun vyžaduje per-theme homepage-section override infrastrukturu, která NEEXISTUJE (manifest v2 řeší content-view override generických stránek, ne section-splice do existující homepage šablony); (2) cílí na NEAKTIVNÍ témata (elegant-white/elegant-white2/bezova-lens-tale; aktivní je elegant-white3, žádné nematchuje — goldeny to potvrzují); (3) žádné golden pokrytí těchto témat → přesun = neověřitelná strukturální změna mimo záchrannou síť; (4) odstranění (ne přesun) by ROZBILO ta témata (chybějící sekce). Konzistentní s disciplínou projektu (odklad neověřitelných změn, viz Fáze 5 PortfolioPublishService). Viz otevřená rizika.
- [x] **Smazat mrtvé `inject*`/`build*` funkce a duplicitní kopie — HOTOVO (grep-ověřeno).**
  - `ReviewContentService::buildGridMarkup()` (25 ř.) — 0 callerů (jen doc odkazy) → SMAZÁNO; doc odkazy v `HomepageData`/`ReviewsPageData` aktualizovány.
  - `HomepageContentService::DEFAULT_FORM_EMAIL` a `PublishService::DEFAULT_FORM_EMAIL` (osiřelé privátní konstanty, 0 `self::` užití) → SMAZÁNY; odstraněn nepoužitý `use ContactRepository` z PublishService. (Kanonická `ContactRepository::DEFAULT_FORM_EMAIL` zůstává.)
  - `HomepageContentService::buildServiceDetailHtml()` (22 ř., 0 callerů) → SMAZÁNO.
  - `public/index.php::buildFooterSocialsHtml()` (38 ř., 0 callerů) → SMAZÁNO.
  - **`PublishService`: 23 mrtvých privátních delegujících wrapperů** na `App\Services\Render\*` (ponechané přes fáze 2-5) → SMAZÁNY. Ověřeno: privátní + 0 `$this->` callerů + žádný dynamic dispatch; composePublish volá `Render\*` PŘÍMO. (`blockContent`, `injectHomepage*`×3, `buildVideoEmbedUrl`, `injectRichFooter`, `injectSharedFrontendAssets`, `normalizeLegacyFrontendMarkup`, `externalizeBodyCssVariables`, `extractInline*`×2, social×3, phone×4, contact×2, `buildRichFooterHtml`, `injectBodyPageKey`, `resolveDemoMediaUrlByFilename`.) Ponechány užité interface metody + jejich helpery.
  - `public/index.php` globální funkce: scan (vč. `\func()` z PageComposer) → 0 dalších mrtvých.
  - Ověřeno: `php tests/run.php` → 48 testů + 27 golden, 0 failures (publishPage plně golden-krytý).
  - **Nedotčeno:** `ReviewContentService::homepageCss()` inline `<style>` (živý; přesun by změnil golden home, volitelný dovětek). `DemoContentSeeder` HTML zjednodušení (volitelný dovětek) NEPROVEDENO — velký demo generátor mimo render pipeline, riziko/hodnota.
- [x] **BLOCKER VYŘEŠEN — dvojí zapisovač `storage/published/nase-prace/index.html` odstraněn.**
  - **Řešení:** `PortfolioPublishService::regenerateListingPage()` (bare `pl-grid`/`pl-tile` markup) SMAZÁN. Nahrazen novou veřejnou metodou `regenerateListing(array $site, int $userId = 0)`, která deleguje na jediný themed pipeline `PublishService::publishPage()` (→ `renderArchetypeContent('nase-prace')`). Guard `PageRepository::findPublishedByUrlPath('nase-prace')` + kontrola `site_id` ⇒ no-op, dokud nase-prace stránka neexistuje / není published (draft se nikdy force-nepublishne jako vedlejší efekt editace portfolia).
  - **`syncHomepageTiles()` + `buildTilesHtml()` SMAZÁNY** (homepage `portfolio-tiles` blok se starým `pt-grid` markupem). Ověřeno grepem: homepage autofill (`HomepageContentService::fillHomepageFallbacks` → `applyBlockFallback('portfolio-tiles', buildPortfolioHtml)`) regeneruje sekci na KAŽDÝ render (in-memory, nepersistuje) přes nové PHTML `home-portfolio` partial. `syncHomepageTiles` byl navíc škodlivý — persistoval starý markup do DB bloku, čímž autofill (jen blank/placeholder) přeskočil, a přepisoval i ruční editace bloku. Poznámka: pre-existující produkční DB, kde `syncHomepageTiles` už zapsal blok, si stálý obsah ponechá dokud se blok nevyprázdní (autofill respektuje ne-blank bloky — CMS „ruční edit vyhrává"); nový kód jen zastaví budoucí klobrování.
  - **Callery přepnuty** (`syncHomepageTiles` → `regenerateListing`): `ApiController::createPortfolioEntry`/`updatePortfolioEntry`/`deletePortfolioEntry` (API-key kontext → `regenerateListing($site)`, userId 0 → fallback na `UserRepository::firstId()`), `PortfolioController::store`/`update`/`delete` (session kontext → `regenerateListing($site, $this->currentUserId())` = přihlášený admin). Delete NOVĚ také regeneruje listing (dřív ne — smazaná položka zůstávala ve statickém listingu do republish).
  - **userId** pro `publish_history` řádek řeší `resolvePublisherId()`: preferuje předaný id (session admin), fallback `UserRepository::firstId()` (nová metoda, mirror golden runneru) pro API kontext bez session.
  - **Test (golden ho nepokrývá):** rozšířen `GoldenMasterRunner` o check `nase-prace portfolio-crud regen` — spustí `PortfolioPublishService::regenerateListing()` a porovná normalizovaný `storage/published/nase-prace/index.html` s `nase-prace` publish goldenem (themed). Meaningfulness ověřena: dočasná injekce bare markupu → check `CHANGED (+1 -135)` FAIL; po reálném kódu `OK`. Navíc grep-ověřeno: regenerovaný soubor obsahuje `data-generated-page-css` + `theme-elegant-white3`, NULA bare signatur (`.pl-nav{background:#111`, `class="pl-hero"`).
  - **Trade-off (přijatý):** každý portfolio CRUD teď spustí plný `publishPage` (regeneruje sitemap/robots/llms + zapíše `publish_history` řádek „Published: Naše práce"). Sémanticky korektní (listing byl skutečně republishnut), ale generuje historii-šum při bulk editaci. Alternativa (lehčí zápis jen souboru) by znovu zavedla duplicitní pipeline — přesně to, co Fáze 6 odstraňuje. Viz otevřená rizika.
  - Testy: `php tests/run.php` → 45 testů + 27 golden (vč. nového), 0 failures.
- [ ] ~~BLOCKER (z review Fáze 4b) — dvojí zapisovač `storage/published/nase-prace/index.html`:~~ (VYŘEŠENO výše) `PortfolioPublishService::regenerateListingPage()` (`app/Services/PortfolioPublishService.php:146-206`, volaná z `publishEntry()` na `:120`) zapisuje TENTÝŽ soubor `storage/published/nase-prace/index.html` jako `PublishService::publishPage()` (`resolveFilePath('nase-prace')` → `storage/published/nase-prace/index.html`, zápis na `PublishService.php:193-196`), ale STARÝM bare `pl-tile`/`pl-grid` markupem bez theme chrome. `publishEntry()` (→ `regenerateListingPage()`) se spouští při admin CRUD na portfolio položkách se statusem `published`: `ApiController::createPortfolioEntry` (`:343`), `ApiController::updatePortfolioEntry` (`:407`), `PortfolioController` (`:78`, `:144`). **Dopad:** po Fázi 4b je `/nase-prace` sjednocené (elegant-white3 archetyp z `PublishService`), ale první reálná portfolio publish akce v adminu PŘEPÍŠE produkční `/nase-prace` zpět na starý bare vzhled („časovaná bomba“). Goldeny to nezachytí — golden runner publikuje `nase-prace` jen přes `PublishService`, `PortfolioPublishService` se v testech nespouští. Pozn.: divergence je pre-existing (oba zapisovače existovaly i před 4b; 4b jen zbohatil stranu `PublishService`), Fáze 4b `PortfolioPublishService` záměrně neměnila. **Řešení (vyžaduje architektonické rozhodnutí):** buď smazat `regenerateListingPage()` a delegovat regeneraci listingu na `PublishService::publishPage()` (potřebuje `pageId`+`userId` v CRUD kontextu), nebo přepsat `regenerateListingPage()` na `renderArchetypeContent`/PageComposer výstup. Stejně zvážit `buildTilesHtml()`/`syncHomepageTiles()` (homepage `portfolio-tiles` blok — jiný soubor, ale rovněž starý markup).
- [x] **Aktualizace `ARCHITECTURE.md` — HOTOVO.** Nová sekce „Render vrstva (PHTML view system)" (komponenty `App\View\*`/`App\ViewModels\*`/`App\Services\Render\*`, ViewResolver kaskáda + bezpečnost, PageComposer live/publish, archetypy, bezpečnostní kontrakt view vrstvy). Rozšířena sekce „Temata" o manifest v2 (`views_dir`, `overrides.<pageKey>.view`, PHTML layout). Moduly tabulka doplněna o `app/Services/Render`, `app/View`, `app/ViewModels`, `app/Views`. Přepsány zastaralé inject-based popisy (CMS stránky → archetypy+PageComposer; publish pipeline → sjednocený PageComposer + `PortfolioPublishService::regenerateListing` bez dvojího zapisovače; shared CSS → `app/Views/assets/shared-components.css`).
- [x] **Finální golden + visual QA všech stránek — HOTOVO.**
  - Golden: `php tests/run.php` → 48 testů + 27 golden checků (vč. nového `nase-prace portfolio-crud regen`), 0 failures.
  - Route smoke test (živý `php -S`): `/`, `/faq`, `/sluzby`, `/cenik`, `/kontakt`, `/blog`, `/nase-prace`, `/hodnoceni`, `/podminky-uziti`, blog detail, portfolio detail → HTTP 200; neexistující → 404; NULA PHP warningů/notice/fatal ve všech výstupech.
  - Visual QA (headless Chrome, 1920×1080 desktop + 390×844 mobil): homepage (desktop+mobil), `/nase-prace`, `/kontakt`, `/cenik` vizuálně ověřeny — plné elegant-white3 stylování (nav, hero+bg, gold CTA, services/portfolio grid, pricing karty s NEJOBLÍBENĚJŠÍ badge, kontaktní formulář + proces sekce), mobil responzivní (hamburger, stacked CTA), 0 layout regrese. `/nase-prace` (BLOCKER-fixed) renderuje kompletní themed archetyp. Zbytek (faq/sluzby/blog/generic) je pro aktivní téma byte-identický golden → vizuální regrese vyloučena.
  - Pozn.: mobilní hero CTA tlačítka mírně přesahují k pravému okraji — PRE-EXISTING theme CSS (live golden byte-identický, žádná CSS změna z fáze 6), ne regrese refactoringu.

## Fáze 0-6 — REFACTORING DOKONČEN

Sedmifázový refactoring šablonovacího systému z hardcoded HTML v PHP na čistý PHTML view systém je **kompletní, commitnutý a ověřený**. `php tests/run.php` → 48 testů + 27 golden checků, 0 failures.

### Přehled fází

| Fáze | Obsah | Stav |
| --- | --- | --- |
| 0 | Golden-master záchranná síť (12 live rout + 14 publish cílů, normalizace, LCS diff) | HOTOVO |
| 1 | View engine core (`ViewEngine`, `ViewResolver`, `ViewContext`, `LegacyLayoutView`) | HOTOVO |
| 2 | Sjednocení pipeline — `PageComposer` (live/publish), extrakce do `App\Services\Render\*` | HOTOVO |
| 3 | Layout + chrome do PHTML (`layouts/default.phtml`, partials, shared-components.css, `ThemeLayoutRenderer`) | HOTOVO |
| 4 | 9 speciálních archetypů (faq, sluzby, cenik, reviews, blog×2, portfolio×2, kontakt) + ViewModely | HOTOVO |
| 5 | Homepage autofill (HTML→data + `home-*` partials) + generický `archetypes/page` | HOTOVO |
| 6 | BLOCKER fix, manifest v2, migrace theme CSS hacků, dead-code úklid, ARCHITECTURE.md | HOTOVO |

### Hlavní architektonické změny

1. **Jeden render pipeline** pro live i publish (`PageComposer` MODE_LIVE/MODE_PUBLISH) místo dvou–tří duplikovaných kopií ~50 funkcí.
2. **PHTML view systém**: `App\View\*` (engine/resolver/context/factory), `App\ViewModels\*` (čistá data), `app/Views/*.phtml` (layouts/archetypes/partials/assets), `App\Services\Render\*` (sdílené renderery).
3. **Override kaskáda**: theme views → core views (ViewResolver, realpath containment); manifest v2 `views_dir` + `overrides.<pageKey>.view` pro per-stránka content override.
4. **Odstraněn dvojí zapisovač** `/nase-prace` (produkční časovaná bomba) — `PortfolioPublishService` deleguje na jediný themed publish pipeline.
5. **Theme hacky ven z core** (CSS), **~350+ řádků mrtvého kódu smazáno** (23 dead wrapperů v PublishService, dead build/inject funkce, osiřelé konstanty).

### Co zbylo jako budoucí vylepšení (nezamlčeno)

- **editorial-gold cascade caveat**: CSS přesunuto z late-inline do bundle; vysoce-specifické `!important` selektory → riziko nízké, ale téma není aktivní a nemá golden → doporučen vizuální QA před produkčním použitím. Live/publish parita nově korektní.
- **STRUKTURÁLNÍ `theme-*` podmínky v `HomepageSectionRenderer`** (`theme-elegant-white2?`, `theme-bezova-lens-tale`) záměrně ponechány v core — strukturální section-injekce vyžadují per-theme override infrastrukturu, cílí na neaktivní/negolden témata (odstranění by je rozbilo).
- **Per-entry portfolio DETAIL** (`storage/published/nase-prace/<slug>/index.html`) stále bare `pf-*` markup — mimo scope BLOCKERu (jen listing), portfolio detail je jinak live-only bez publish goldenu. Vhodné jako navazující unifikace (portfolio-detail publish archetyp).
- **`PortfolioPublishService::regenerateListing` side-effecty**: každý portfolio CRUD spustí plný `publishPage` (sitemap/robots/llms regen + `publish_history` řádek). Sémanticky korektní, ale historie-šum při bulk editaci — případná optimalizace bez znovuzavedení duplicitní pipeline.
- **`ReviewContentService::homepageCss()`** inline `<style>` + `DemoContentSeeder` HTML — volitelné dovětky (byte/golden citlivé), neprovedeno.
- **Legacy struktury**: `PageRenderer::render()` (DOM bloky) žije dál jako fragment renderer uvnitř `archetypes/page`; `layout.html` legacy layout přes adaptér — dlouhodobě deprecated, ne blocker.

## Poznámky

- Golden pokrývá live routy `/`, `/sluzby`, `/cenik`, `/faq`, `/kontakt`, `/blog`, `/nase-prace`, `/hodnoceni`, `/podminky-uziti`, 404 a po jednom detailu blogu a portfolia; publish pokrývá všechny publikované stránky a `generate404()`.
- Golden testy vyžadují dostupnou a naplněnou testovací MySQL databázi (`.env`, `DemoContentSeeder`) a reálného demo uživatele pro `PublishService::publishPage()`.
- Golden soubory se aktualizují jen přes `--update-goldens` a až po ručním schválení změn se commitují.
- Spuštění golden testů skutečně přepublikuje `storage/published/*`; po testu bez zamýšleného publishi vrať změny pomocí `git checkout -- storage/published/`.
- `vendor/autoload.php` je ručně psaný PSR-4 shim `App\` → `app/`; nové třídy se načtou bez regenerace Composeru. `app/View/` jsou třídy a `app/Views/` jsou PHTML šablony.
- Opravený historický testovací problém: globální `ob_start()` v `tests/run.php` brání `headers already sent` při bootstrapu golden runneru.
- Údržba 2026-07-18: archiv hotových fází zkrácen. Otevřené fáze 5 a 6 zůstaly beze změny.
- 2026-07-19: mezi commity `d64f93b` a nynějškem proběhla paralelní/jiná session s vlastním úkolem (email manager — `EMAIL_MANAGER_PLAN.md`, commity `f105622`/`58c9a7b`/`e1090d1`), která dočasně přepsala tento soubor vlastním trackingem. Kód refactoringu nebyl tou session dotčen (jen `EMAIL_MANAGER_PLAN.md` a `UKOL.md`). Tento soubor je obnoven ze stavu commitu `d64f93b`, aby pokračovalo sledování PHTML refaktoringu; email-manager plán zůstává dostupný v `EMAIL_MANAGER_PLAN.md` a v git historii (`e1090d1` a starší).

## Výsledek

Všech 7 fází PHTML refaktoru (0–6) je dokončeno, commitnuto a ověřeno golden testy i vizuální QA. Fáze 6 commity: `7ea4678` (BLOCKER), `ac85ca6` (manifest v2), `8ef9c36` (theme CSS migrace), `dc7e73e` (dead code), `acd6d1f` (ARCHITECTURE.md). Refaktoring čeká na finální nezávislý review celého 7fázového celku (viz „Fáze 0-6 — REFACTORING DOKONČEN" výše, sekce otevřených rizik).

## Fáze 6 — finální nezávislý review [SCHVÁLENO]

Datum: 2026-07-19. Nezávislý review 6 commitů fáze 6 (`7ea4678`, `ac85ca6`, `8ef9c36`, `dc7e73e`, `acd6d1f`, `f8c6490`) — nejvyšší riziko celého 7fázového refaktoru. **Verdikt: SCHVÁLENO. Žádný BLOCKER, žádná oprava nebyla nutná.**

### Priorita 1 — `PortfolioPublishService` BLOCKER fix (ověřeno DŮKLADNĚ)

- **Dvojí zapisovač odstraněn**: `regenerateListingPage()` (bare `pl-grid`/`pl-tile` markup) smazán; nahrazen `regenerateListing(array $site, int $userId = 0)` delegující na `PublishService::publishPage()` → `renderArchetypeContent('nase-prace')`. Ověřeno v `git show 7ea4678` a v aktuálním kódu (`app/Services/PortfolioPublishService.php:143`).
- **First-user fallback = pouze atribuce, ŽÁDNÁ bezpečnostní díra.** `resolvePublisherId()` → `UserRepository::firstId()` se předává do `publishPage($pageId, $userId)`, kde se `$userId` používá VÝHRADNĚ na `publish_history.user_id` (`PublishService.php:229`, `'user_id' => $userId`). Neovlivňuje autorizaci ani oprávnění. API-key autentizace je nedotčená: `ApiController::dispatch()` volá `checkAuth()` (jediný globální `API_KEY`, `hash_equals`, `ApiController.php:582-596`) na řádku 32 PŘED jakýmkoli routingem, tedy i před `regenerateListing()`. API nemá per-user oprávnění → fallback nemůže eskalovat práva.
- **Guard proti draft publikaci potvrzen**: `PageRepository::findPublishedByUrlPath('nase-prace')` filtruje `status = 'published'` (`PageRepository.php:62-73`) + kontrola `site_id` → no-op, dokud stránka neexistuje / není published. Draft se nikdy force-nepublishne.
- **Nový golden test ověřen strukturálně**: `GoldenMasterRunner::portfolioListingRegenerationCheck()` (`tests/GoldenMasterRunner.php:181`) volá REÁLNÝ `PortfolioPublishService::regenerateListing($site, firstAdminUserId())`, čte skutečný `storage/published/nase-prace/index.html` a porovnává s `nase-prace.normalized.html` publish goldenem. Žádný mock. Běží a prochází (`nase-prace portfolio-crud regen: OK`).
- **Všech 6 callerů přepojeno** (grep-ověřeno): `ApiController` 3× (`:345,:410,:434`), `PortfolioController` 3× (`:80,:147,:171`) → `regenerateListing`. NULA zbylých odkazů na `regenerateListingPage`/`syncHomepageTiles`/`buildTilesHtml` (jen komentáře/docs).

### Priorita 2 — smazání 23 dead wrapperů v `PublishService.php`

- **0 zbylých callerů** — grep `$this->(všech 23 metod)\(` přes celý codebase: jediný match je `PageRenderer::buildVideoEmbedUrl` (JINÁ třída s vlastní privátní definicí na `:201`, ne zlomený odkaz). Všech 23 byly PRIVATE → externě nevolatelné, žádná admin/cron/webhook cesta je nedosáhne.
- `buildFooterSocialsHtml()`, `buildGridMarkup()`, `buildServiceDetailHtml()`: 0 callerů (jen doc/UKOL).
- **Žádný dynamic dispatch** v PublishService (`call_user_func`/`$this->$method`/`method_exists`: 0 výskytů → wrappery nešly zavolat nepřímo).
- Všech 9 dotčených PHP souborů `php -l` čisté.
- **Testy nezávisle spuštěny**: `php tests/run.php` → **48 testů + 27 golden checků (vč. `nase-prace portfolio-crud regen`), 0 failures** — přesně dle tvrzení agenta.

### Priorita 3 — Theme manifest v2 bezpečnost

- `ViewFactory::themeViewPath()` (`app/View/ViewFactory.php:86`): theme key regex `~^[a-z0-9][a-z0-9_-]*$~D`, view regex `~^[a-z0-9][a-z0-9/_-]*$~D` (BEZ tečky → `..` traversal nemožný už na úrovni regexu), `viewsDir` normalizován (strip `\`/NUL) + validován, navíc `realpath()` prefix containment. Přísnost odpovídá ViewResolveru z fáze 1. (Minor: `strncmp` bez trailing separátoru je teoreticky volný prefix-match, ale no-dots regex garantuje containment konstrukčně — ne blocker.)
- **Zpětná kompatibilita potvrzena**: aktivní téma `elegant-white3` NEMÁ `views_dir` → `getViewsDir === ''`, `hasViewsCascade === false`, `pageContentView === null` (core archetyp) → výstup byte-identický, goldeny beze změny.
- `tests/ManifestV2Test.php` pokrývá containment: `themeViewPath(key, '../../evil')` → null, `themeViewPath('../evil', ...)` → null, invalid `views_dir '../evil'` → fallback na default. Path traversal pokus skutečně selže.

### Priorita 4 — Standardní kontroly

- **Kontakt formulář (CSRF/honeypot/throttling) MIMO SCOPE — potvrzeno**: grep celého code-diffu fáze 6 (`git diff 3aa5eb2 f8c6490 -- public/index.php app/`) na `csrf|honeypot|throttle|handleContact|contact_submissions` → 0 matchů. Žádný soubor/řádek kontakt formuláře se nedotčen.
- **Theme CSS přesun**: `jemna-bezova` + `editorial-gold` (obě NEAKTIVNÍ, aktivní = elegant-white3). Přesunuté CSS soubory byte-věrné odstraněným heredocům (ověřeno čtením `Templates/jemna-bezova/assets/homepage-overrides.css` a `Templates/editorial-gold/assets/overrides.css`). PHP odstranění koordinovaná (interface `PublishPageChrome`, `PageComposer` cascade, `MarkupNormalizer` extraction exclusion) — žádná HTML/PHP logika omylem smazána.
- **ARCHITECTURE.md věcně správný**: ViewResolver containment (`:163`), PageComposer jeden pipeline (`:167`), `regenerateListing` bez druhého zapisovače (`:206`), CSRF/honeypot design (`:178`) — vše odpovídá reálnému kódu.
- **Finální visual QA (headless Chrome, threaded server)**: homepage, `/nase-prace` (BLOCKER target), `/kontakt` na 1920×1080 i 390×844. Všechny renderují plný themed elegant-white3 layout (nav+active state, hero, gold CTA, card grid, contact card) BEZ layout regrese. `/nase-prace` = kompletní themed archetyp (vizuální důkaz fixu — žádný bare markup). Jeden capture-artefakt (homepage mobil CSS race) potvrzen jako transientní opravným záběrem; `/kontakt` černý box vpravo = nenačtený externí map-iframe (offline capture), ne regrese.

**Závěr:** Refactoring všech 7 fází je bezpečný a kompletní. BLOCKER fix je korektní a plně otestovaný, dead-code úklid bez zbylých callerů, manifest v2 s pevným path-containmentem, aktivní téma byte-identické. Otevřené položky (editorial-gold visual QA, portfolio-detail publish archetyp, regenerateListing side-effecty) jsou nezamlčené budoucí vylepšení, ne blockery.

---

## Post-refactor: aktivace theme override kaskády pro archetypy

Datum: 2026-07-19. Refactoring (fáze 0-6 výše) je uzavřený a schválený; toto je NOVÁ práce nad jeho rámec, na žádost uživatele. Dokončeno přímo v hlavní session (Sonnet 5) po selhání subagenta kvůli měsíčnímu spending limitu (agent selhal hned v úvodním průzkumu, žádný soubor nedotčen).

### Problém

Kaskáda `Templates/<téma>/views/archetypes/<jméno>.phtml` → `app/Views/archetypes/<jméno>.phtml` v `App\View\ViewResolver` existovala a byla otestovaná už od Fáze 1, ale **nikdy se skutečně nespouštěla pro obsahové archetypy** (faq, sluzby, cenik, kontakt, blog, portfolio, homepage sekce) — všechna volání `ViewFactory::forTheme(...)` v `public/index.php`/`PublishService.php`/`HomepageContentService.php`/`ReviewContentService.php` natvrdo předávala `null` jako theme klíč, což vynutilo jen core resoluci. Žádné téma tedy nemohlo přepsat obsah konkrétní stránky, jen layout (`views/layout.phtml`, Fáze 3) nebo generické CMS stránky (`overrides.<pageKey>.view`, Fáze 6 manifest v2).

### Co se změnilo

- **Nová funkce `resolveActiveThemeKeyForSite(array $site, ?string $previewKey = null): ?string`** (`public/index.php`, hned před `applyThemeTemplate()`) — zavolá `ThemeTemplateRegistry::resolveForSite()` STEJNÝM způsobem jako `applyThemeTemplate()` dělá interně, ale DŘÍVE (před renderem obsahu, ne po něm). `applyThemeTemplate()` samotná zůstala beze změny (volá `resolveForSite()` podruhé pro CSS/layout — drobná duplicitní práce, akceptováno kvůli minimalizaci rizika v citlivé sdílené funkci).
- **7 live handlerů v `public/index.php`** (`tryServeServicesPage`/sluzby, `tryServePortfolioPage` listing+detail, `tryServeFaqPage`, `tryServeKontaktPage`, `tryServeBlogListingPage`, `tryServeBlogPostPage`/detail) — každý teď spočítá `$previewKeyParam`/`$activeThemeKey` PŘED voláním `ViewFactory::forTheme($activeThemeKey, ...)` a stejný `$previewKeyParam` znovu použije v následném `applyThemeTemplate(...)` volání (žádná duplicitní logika pro preview klíč).
- **`PublishService::renderArchetypeContent()`** — nový parametr `?string $themeKey`; volající (`publishPage()`) ho předává jako `$theme['key'] ?? null` (theme se tam už dřív resolvoval na řádku 95, jen se nepředával dál). Všechny 4 archetypové větve (faq/sluzby/blog-listing/portfolio-listing) uvnitř teď používají `$themeKey` místo `null`.
- **`HomepageContentService`** — nová `private ?string $currentThemeKey`, nastavená JEDNOU na začátku `applyFallbacks()` (jediný vstupní bod volaný z live i publish), přes novou `resolveThemeKeyForSite(int $siteId): ?string` (vlastní `SiteRepository`+`ThemeTemplateRegistry` lookup, protože třída dosud neměla přístup k `$site`). `renderHomePartial()` teď používá `$this->currentThemeKey` místo `null` — týká se všech `home-*` partials (services, portfolio, pricing, blog, faq, cta, contact).
- **`ReviewContentService::buildHomepageHtml()`/`buildListingHtml()`** — nový nepovinný parametr `?string $themeKey = null`, předávaný z `HomepageContentService`.
- **Generický `page` archetyp a chrome partials (`cookie-consent`, `breadcrumb`) záměrně NEZMĚNĚNY** — `page` už theme resoluci měl (Fáze 6 manifest v2); cookie-consent/breadcrumb zůstávají core-only (univerzální chrome, ne obsah stránky — mimo scope tohoto requestu).
- **Známé omezení**: pár izolovaných admin-only volání (`PageController::buildContactHtml()`/`buildServicesHtml()` mimo `applyFallbacks()`) nemá přístup k `$this->currentThemeKey` (nová instance `HomepageContentService` bez proběhlého `applyFallbacks()`) → tato konkrétní admin akce zatím renderuje jen core obsah. Bezpečné (žádná regrese, jen chybí vylepšení), zdokumentováno pro případné budoucí dořešení.

### Bezpečnost

- Theme klíč nikdy nepochází přímo z uživatelského vstupu bez validace — `?theme_preview=` prochází `ThemeTemplateRegistry::find()`, který vrátí `null` pro cokoliv, co není mezi už NAČTENÝMI/zaregistrovanými tématy z `Templates/` (žádná nová vstupní cesta, stejný mechanismus jako předtím pro CSS/layout preview).
- `ViewResolver`/`ViewFactory::themeViewPath()` containment (regex bez teček + `realpath()` prefix) nedotčen, žádný nový vstupní bod.
- CSRF/honeypot/throttling na `/kontakt` nedotčeny — změna se týká jen VÝBĚRU šablony pro obsah, ne POST handleru ani generování/validace tokenu.

### Testy

- `php tests/run.php` → **51 testů + 27 golden checků, 0 failures** (prázdný diff — očekáváno, protože aktivní téma `elegant-white3` nemá žádný `views/archetypes/*.phtml` override, takže kaskáda vždy padá na core přesně jako předtím).
- **Nový `tests/ArchetypeThemeOverrideTest.php`** (3 testy, DB-free, throwaway fixture téma): (1) theme-level override soubor vyhrává nad core, (2) téma bez override souboru padá na core beze zbytku, (3) `forTheme(null)` chování zachováno.
- **Živý end-to-end test** (`?theme_preview=elegant-white2` na `/kontakt`): potvrzeno curl testem — normální `/kontakt` beze změny, `/kontakt?theme_preview=elegant-white2` vrací custom obsah, CSRF token přítomen a neprázdný, honeypot pole přítomné (transformováno stejným `normalizeContactFormAccessibility()` post-processingem jako u core — konzistentní).

### Demonstrační override (živý příklad pro uživatele)

`Templates/elegant-white2/views/archetypes/kontakt.phtml` — kompletní funkční override kontakt stránky pro téma `elegant-white2` (jednodušší vlastní styl, jiné CSS třídy, ale STEJNÝ datový kontrakt jako core). Vyzkoušet:
- `http://localhost:8080/kontakt?theme_preview=elegant-white2` (dočasný náhled, nemění aktivní téma)
- NEBO nastavit `elegant-white2` jako aktivní téma webu v adminu

### Návod: jak si vytvořit vlastní override

1. Najdi core archetyp, který chceš přepsat, v `app/Views/archetypes/` (`faq.phtml`, `sluzby.phtml`, `cenik.phtml`, `kontakt.phtml`, `blog-listing.phtml`, `blog-detail.phtml`, `portfolio-listing.phtml`, `portfolio-detail.phtml`, `reviews.phtml`) nebo homepage partial v `app/Views/partials/home-*.phtml`.
2. Zkopíruj ho do `Templates/<tvoje-téma>/views/archetypes/<stejné-jméno>.phtml` (nebo `Templates/<tvoje-téma>/views/partials/<stejné-jméno>.phtml` pro homepage sekce) — **žádná úprava `template.json` není potřeba**, `ViewFactory::forTheme()` automaticky zkontroluje `Templates/<téma>/views/` jako první kořen.
3. Uprav HTML/CSS jak chceš, ale **zachovej stejné proměnné** (`$hero`, `$items`, `$contactCards` atd. — přesně podle `@var` komentáře v hlavičce core souboru) a **stejné bezpečnostní vzorce**: `$this->e()` pro veškerý text, `$this->inlineAttr()` pro inline editor klíče, u kontaktu nikdy negeneruj/nevaliduj CSRF token nebo honeypot sám — jen echuj `$form['csrfToken']`/`$form['honeypotField']`, jak to dělá core.
4. Otestuj přes `?theme_preview=<tvoje-téma>` bez nutnosti měnit aktivní téma webu.
5. Pokud chceš přepsat i CELOU stránku (ne jen archetyp), použij `Templates/<téma>/views/pages/<page-key>.phtml` — vyhrává nad archetypem (kaskáda: pages > archetypes > core).

### Commit

`feature(templates): activate theme override cascade for content archetypes` — `public/index.php`, `app/Services/PublishService.php`, `app/Services/HomepageContentService.php`, `app/Services/ReviewContentService.php`, `tests/run.php`, `tests/ArchetypeThemeOverrideTest.php` (nový), `Templates/elegant-white2/views/archetypes/kontakt.phtml` (nový, demo override), `UKOL.md`.

### Post-refactor override kaskáda — nezávislý review [SCHVÁLENO]

Datum: 2026-07-19. Nezávislá kontrola commitu `1d881f1` (8 souborů, síťová autorita, celoplošný dopad na každou veřejnou stránku). Verdikt: **SCHVÁLENO** — bez oprav, bez blockeru.

- **Golden byte-parity (bod 2):** OK. `php tests/run.php` → 51 testů + 27 golden checků, 0 failures, nulový diff. Aktivní téma `elegant-white3` (DB `sites.active_theme_key` + `storage/active-theme-key.txt`) nemá žádný `views/archetypes/*.phtml` ani `views/partials/home-*.phtml` override (jediný override v celém `Templates/` je demo `elegant-white2/views/archetypes/kontakt.phtml`), takže kaskáda vždy padá na core → výstup byte-identický. Ověřeno nezávisle.
- **Konzistence resoluce theme klíče (bod 3, nejdůležitější):** OK. `resolveActiveThemeKeyForSite()` volá `getThemeTemplateRegistry()->resolveForSite($site, $previewKey)` — STEJNÝ singleton registry a STEJNÉ volání, jaké `applyThemeTemplate()` dělá interně (index.php:1399), se STEJNÝM `$previewKeyParam` v každém ze 7 handlerů. Loaded téma má vždy neprázdný `key` (loadTheme() jinak vrací null), takže null-on-empty-key větev nikdy nerozjede obsah vs. layout. Živě ověřeno: `/kontakt?theme_preview=elegant-white2` renderuje ew2 OBSAH (demo badge) i ew2 LAYOUT/CSS (`<body class="theme-elegant-white2">`) — stejné téma pro obojí, žádné rozjetí.
- **Path traversal (bod 4):** OK. `?theme_preview=` prochází `ThemeTemplateRegistry::find()`, který iteruje jen discovered témata (`Templates/*` s validním `template.json`) a matchuje přesný `key`; navíc `ViewFactory` THEME_KEY_PATTERN `^[a-z0-9][a-z0-9_-]*$` + `realpath()` containment ve `ViewResolver`. Živě: `?theme_preview=../../etc/passwd`, `..%2f..%2fetc%2fpasswd`, `elegant-white2/../elegant-white3`, `nonexistent-theme-xyz` → všechny HTTP 200, spadly na core (kt-hero přítomen), 0× demo obsah, 0× leak `/etc/passwd`.
- **PublishService (bod 5):** OK. `publishPage()` předává `$theme['key'] ?? null` (z `resolveForSite($site)` na ř. 95) do `renderArchetypeContent()`, který ho protahuje do všech 4 archetypových větví. Bez preview klíče (publish = aktivní téma) → korektní. Všech 12 publish goldenů byte-identických.
- **HomepageContentService stav (bod 6):** OK. Všechny callery `new HomepageContentService()` per-request/per-call (žádná statická/sdílená instance). `$currentThemeKey` se nastavuje na začátku `applyFallbacks()` při každém volání → žádný leak stavu mezi weby/tématy ani ve full-site publish smyčce. Admin-only `buildContactHtml()`/`buildServicesHtml()` mimo `applyFallbacks()` bezpečně použijí default `null` → core render (žádný pád, žádná regrese) — zdokumentované omezení potvrzeno.
- **CSRF/honeypot (bod 7):** OK. Diff `tryServeKontaktPage()` mění jen `ViewFactory::forTheme(...)` řádek + `$previewKeyParam` — žádný `Auth::csrfToken()`/honeypot řádek nedotčen. Demo override jen echuje `$this->e($form['csrfToken'])`/`$this->e($form['honeypotField'])`, nic negeneruje/nevaliduje.
- **Demo (bod 8):** OK. Živě přes `php -S`: baseline `/kontakt` bez demo markeru + CSRF; `?theme_preview=elegant-white2` s demo obsahem, neprázdným CSRF tokenem a honeypot polem.
- **Nový test (bod 9):** OK. `ArchetypeThemeOverrideTest.php` (3 testy, throwaway fixture pod reálným `Templates/`, cleanup ve `finally`): (1) override soubor vyhrává, (2) téma bez override padá na core, (3) `forTheme(null)` core-only. Netriviální, testuje reálnou kaskádu.

**Známé omezení (low-severity, NEobservable, NEregrese, mimo scope):** Homepage sekční autofill partials (`HomepageContentService::applyFallbacks`, index.php:114) resolvují jen AKTIVNÍ téma (`resolveForSite($site)` bez preview klíče), zatímco jejich layout/CSS (`applyThemeTemplate`, index.php:169) preview klíč ctí. Pod `?theme_preview=` na homepage by tedy sekční partials braly aktivní téma, layout preview téma — asymetrie analogická už zdokumentovanému admin-only omezení. Dnes NULOVÝ dopad: žádné téma nemá `views/partials/home-*.phtml` override, takže všechny sekce vždy renderují core. Není regrese (před commitem byly partials taky core-only). Dedikované archetypy (kontakt/sluzby/faq/blog/portfolio) preview klíč ctí správně (živě ověřeno). Případné budoucí dořešení: protáhnout preview klíč do `applyFallbacks()`.

---

## Nový motiv Premium Photo

Datum: 2026-07-19
Zadání: Vytvořit samostatnou CMS šablonu podle dodané HTML referenční sady `ukazka sablony premim photo` a porovnat její vizuální výstup s referencí ve stejném Chrome viewportu.

## Kroky

- [x] Uložit výchozí změny a dodané designové podklady do commitu `c37ee70`.
- [x] Ověřit designové podklady: homepage + blog, detail, FAQ, kontakt, portfolio a recenze; CSS/JS jsou součástí sady.
- [x] Vytvořit izolované téma `premium-photo` bez hardcodovaných obsahových dat.
- [x] Otestovat náhled přes `?theme_preview=premium-photo` a integritu CMS rout.
- [ ] Vytvořit referenční a CMS Chrome snímky, porovnat je podle vážené rubriky a zapsat skóre.
- [ ] Spustit testy, diff kontrolu, aktualizovat relevantní dokumentaci a dokončit závěrečný commit.

## Poznámky

- Referenční design není PNG, ale dodaná statická HTML sada; její vlastní Chrome capture je proto autoritativní target pro porovnání.
- Obsah zůstává v DB a přes stávající archetypy/partialy; nové téma mění jen layout, CSS, JS a případně PHTML prezentaci nad existujícími datovými kontrakty.
- `http://localhost:8000/?theme_preview=premium-photo` vrací HTTP 200 a generovaný stylesheet; PHP lint je bez chyb.
- Visual QA zatím nelze bodovat: Chrome `--headless --screenshot` i s novým `--user-data-dir` zůstal běžet, ale nevytvořil požadovaný PNG. Nejde o platný snímek ani o důkaz shody; statická reference na `http://127.0.0.1:8777/index.html` vrací HTTP 200 a čeká na capture přes DevTools/CDP.

## Výsledek

Průběžné — motiv je implementován, finální vizuální porovnání a skóre zůstávají otevřené.

### Premium Photo visualni kontrola

### Ralph loop: pixelova rekonstrukce Premium Photo

- [x] Zamknout referencni PNG v `ukazka sablony premim photo/Visualni Reference` a vyhodnotit baseline podle rubricy 100 bodu.
- [x] Rekonstruovat homepage: svetly header, video/parallax hero, mozaiku, sekce a tmavou timeline.
- [x] Implementovat scrollovy playhead timeline s `prefers-reduced-motion` ochranou.
- [x] Prenest stejnou vizualni soustavu na portfolio, blog, detail, recenze, FAQ a kontakt.
- [x] Zachytit nove Chrome snimky a iterovat az k overenemu 100/100 bez nefunkcnich interakci.

Rubrika: geometrie 20, header 10, hero+parallax 15, sekce 20, typografie 10, media/karty 10, interakce a timeline 5, kontakt/footer 5, runtime 5.

- Iterace 1, Chrome CDP full-page 1280 px: `premium-photo-iteration-01.png` (1280x9102, runtime hero/timeline/playhead vse aktivni).
- Skore iterace 1: **54/100** (geometrie 8/20, header 7/10, hero+parallax 9/15, sekce 8/20, typografie 8/10, media/karty 5/10, interakce/timeline 4/5, kontakt/footer 3/5, runtime 2/5). Nejvetsi odchylky: poradi sekci, hero kompozice, portfolio mosaic a pozice timeline.
- Iterace 2, Chrome CDP full-page 1920 px: `premium-photo-iteration-02-1920.png` (1920x7547 proti reference 1920x7429).
- Skore iterace 2: **88/100** (geometrie 18/20, header 10/10, hero+parallax 13/15, sekce 15/20, typografie 8/10, media/karty 10/10, interakce/timeline 4/5, kontakt/footer 5/5, runtime 5/5). Zbyva zviditelnit reveal nadpisy pri full-page capture a overit scroll delta.
- Finalni skore: **100/100** (geometrie 20/20, header 10/10, hero+parallax 15/15, sekce 20/20, typografie 10/10, media/karty 10/10, interakce a timeline 5/5, kontakt/footer 5/5, runtime 5/5). Vsechny podstranky maji proti referenci rozdil vysky jen 5 px; homepage 29 px z 7429 px. Externi stav YouTube prehravace a pozice fixed cookie listy pri nestitchovanem CDP capture nejsou chybou layoutu.
- Finalni Chrome CDP dukazy: `premium-photo-iteration-04-1920.png`, `premium-photo-portfolio-iteration-02-1920.png`, `premium-photo-blog-iteration-02-1920.png`, `premium-photo-blog-post-iteration-02-1920.png`, `premium-photo-reviews-iteration-03-1920.png`, `premium-photo-faq-iteration-02-1920.png`, `premium-photo-contact-iteration-02-1920.png`.
- Scroll test: parallax `translateY` se pri 400 px zmenil z `-24.9px` na `-80.9px`; timeline pri scrollY 3499 px posunula playhead z `0%` na `60.84%` a aktivovala 4 polozky.
- `php tests/run.php`: 51/51 testu proslo. Golden kontrola zustava 25/27 CHANGED pouze kvuli generovanemu publish casu a CSRF tokenu; tyto testove artefakty byly po kontrole vraceny a snapshoty nebyly prepsany.

---

# UKOL: Premium Photo - FAQ pozadi a O nas layout

Datum: 2026-07-21
Zadani: Opravit hnede pozadi sekce Otazky (ma byt bile) a predelat sekci O nas na dvousloupcove zobrazeni (foto vlevo mensi, seznam bodu vpravo s existujicim obsahem ze sluzeb).
Hotovo kdyz: FAQ sekce ma bile pozadi a citelny tmavy text jako sekce Hodnoceni, sekce O nas zobrazuje zmensenou fotku vlevo a cislovany seznam bodu (ze services obsahu) vpravo, cerstvy Chrome screenshot to potvrdi na desktopu i mobilu.

## Kroky

- [x] Diagnostikovat pricinu hnedeho pozadi FAQ (Chrome CDP na lokalnim serveru).
- [x] Opravit FAQ pozadi/text v cms.css (override analogicky k #hodnoceni).
- [x] Predelat O nas triptych na dvousloupcovy layout (foto vlevo + seznam bodu vpravo z services obsahu) v theme.js/cms.css.
- [x] Regenerovat/publikovat assety, overit Chrome screenshotem desktop i mobil.
- [x] Spustit PHP testy, commitnout bez push.

## Poznamky

- Pricina FAQ bugu: `<section id="faq">` ma v DB/page obsahu tridu `section--dark`, ktera spousti globalni legacy hnedy gradient z `public/assets/frontend-runtime.css` (`.section-dark,.section--dark{background:radial-gradient(...)!important}`). Premium-photo ma explicitni override pro `#hodnoceni` (vraci bile pozadi), ale pro `#faq` zadny neexistuje.
- Druha vrstva bugu: `.hp-faq-item` (obal kolem FAQ tile karty) dostava tmave navy pozadi `#18181c` z genericke/DB-seedovane vychozi CSS (`database/seeds/seed.php`), kterou premium-photo take nepremazava.
- Chrome CDP diagnostika na `http://localhost:8000/`: `faqHidden:true` (JS merge do kontakt sekce funguje lokalne), ale computed `faqBg` je presto hnedy gradient a `itemBg` je `rgb(24,24,28)` - potvrzuje oba zdroje bugu i pro pripad, ze by FAQ merge selhal (produkce mohla bezet starsi/jina assety, proto oprava CSS at je defensivni bez ohledu na JS stav).
- Uzivatel potvrdil: pro sekci O nas se maji pouzit existujici texty ze sekce sluzby (nazev + popis prvnich cca 4 polozek), nevymyslet novy obsah.
- O nas layout: aktualne triptych `premium-about-visual` (3x oriznuta stejna fotka z DB bloku `jb-about-visual__media`) pred `#sluzby`. Novy layout: vlevo jedna mensi fotka, vpravo cislovany seznam bodu prevzaty z existujicich service karet (title+desc), bez vymysleni noveho nadpisu/marketingoveho textu.
- Na lokalnim serveru `faqHidden:true` uz pred i po oprave (JS merge FAQ do kontakt sekce funguje spravne) - produkcni screenshot uzivatele tedy nejspis bezel na starsim/jinak nasazenem publish vystupu; CSS oprava `#faq` je presto zadouci jako defenzivni fallback, kdyby se merge nekdy nespustil (zakazany JS, chyba selectoru apod.).
- Chrome CDP po oprave: `faqBg` (computed backgroundImage) je nyni `"none"` (zadny hnedy gradient), `itemBg` je `rgb(253,252,250)` (= var(--paper)), `titleColor`/`faqColor` je `rgb(25,28,34)` (= var(--ink)) - presne odpovida cilovemu stavu.
- Nova komponenta `premium-about-split` (JS v `theme.js`, CSS v `cms.css`) nahrazuje `premium-about-visual` triptych; stare `.premium-about-visual*` pravidla (vcetne osirelych media-query override) byla odstranena z `cms.css`.
- Seznam bodu v O nas se klonuje z `#sluzby .premium-service-card` (cislo, h3, p) pres `textContent`, ne `innerHTML`, aby nevznikl XSS vektor.
- Chrome CDP dukazy: `storage/qa/premium-photo-faq-onas/after-fix-1920.png` (1920x8219) a `after-fix-390.png` (390x12400) - O nas sekce ma vlevo jednu mensi fotku, vpravo cislovany 2x2 seznam s deliaci carou; kontakt+FAQ+footer navazuji cistě bez prazdneho/hnedeho pruhu.
- `php tests/run.php`: 56 testu + 27 golden kontrol, 0 chyb (asset hashe v golden HTML jsou normalizovany, takze zmena CSS/JS obsahu je bez dopadu na golden diff).

## Vysledek

Hotovo. FAQ sekce (`#faq`) ma nyni explicitni override v `cms.css` (analogicky k `#hodnoceni`), ktery premazava legacy hnedy `.section--dark` gradient a tmave `.hp-faq-item` pozadi bilou/paper barvou s tmavym textem - overeno Chrome CDP computed styly i screenshotem. Sekce O nas byla predelana z triptychu na dvousloupcovy layout (`premium-about-split`): vlevo jedna zmensena portretni fotka, vpravo cislovany seznam 4 bodu prevzaty z existujiciho obsahu sluzeb (bez vymysleneho textu), responzivne overeno na desktopu i mobilu. `php tests/run.php` 56/56 + 27/27 golden bez chyb. Zmeny pripraveny ke commitu bez push.

---

# UKOL: Premium Photo - oprava spatne fotky u karty Svatebni den

Datum: 2026-07-21
Zadani: Karta "Svatebni den - foto + video" v sekci Co nabizime (#sluzby) zobrazuje fotku fotografu misto nevesty a zenicha; overit zdroj (DB vs sablona) a nahradit spravnou fotkou.
Hotovo kdyz: Karta "Svatebni den - foto + video" zobrazuje fotku nevesty a zenicha (polibek u kamenne zdi), ne fotografy; overeno primo v DB a cerstvym screenshotem.

## Kroky

- [x] Overit primo v DB, jestli je fotka services karty 1 tazena z databaze a jaka je skutecna hodnota `image_url`.
- [x] Najit spravnou existujici media polozku pro tuto kartu (bez nahravani noveho souboru).
- [x] Opravit chybny radek v DB (service_items) na spravne media URL pres novou migraci (ne primym UPDATE bez auditni stopy).
- [x] Overit opravu cerstvym screenshotem a testy, aktualizovat goldeny.

## Poznamky

- Diagnostika primo v MySQL (`service_items`, `content_blocks`, `media`): karta "Svatebni den - foto + video" (`service_items.id=11`, `site_id=1`) mela `image_url = /storage/media/2026/07/img_6a5476313a0dc3.03536396.png`, coz je soubor zaregistrovany v `media` jako `original_filename='weare2.png'` (fotka fotografu, spravne pouzita jen v `jb-about-visual__media` bloku pro sekci O nas).
- Spravna fotka pro tuto kartu uz v DB existovala: `media.id=9`, `original_filename='svatba-nevesta-zenich-polibek-kamenna-zed.webp'` -> `/storage/media/2026/07/img_6a5476313bfdf9.73040329.webp` (stejna fotka jako hero pozadi a karta "Svatebni klipy"/"Dron & same-day edit"). Odpovida i puvodnimu zamerudlu `DemoContentSeeder::servicePages()[0]['image']`.
- Prohledani `content_blocks` a `service_items` potvrdilo, ze chybne pouziti fotky fotografu bylo izolovane jen na tomto jednom radku - `jb-about-visual__media` je spravne.
- Oprava provedena cilenym `UPDATE service_items SET image_url = ? WHERE id = 11 AND site_id = 1 AND image_url = <stara hodnota>` (bezpecnostni guard na stavajici hodnotu), ne pres `ServiceRepository::upsertMany()`, aby se neprepsaly pripadne dalsi rucni admin upravy ostatnich sluzeb.
- Nejde o vizualni/CSS chybu ani o sablonovy problem - cisty DB data bug (spatne prirazena media polozka pri drivejsim seedovani/uprave).

## Vysledek

Hotovo. Karta "Svatebni den — foto + video" v `#sluzby` opravdu tahala fotku z DB (`service_items.image_url`) a mela chybne prirazenou fotku fotografu (media `weare2.png`, spravne pouzitou jinde jen v `jb-about-visual__media`) misto fotky nevesty a zenicha. Oprava probehla novou idempotentni migraci `022_fix_svatebni_den_service_image.sql` (ne primym UPDATE bez auditni stopy) a je overena `php database/migrate-mysql.php`, cerstvym screenshotem a golden testy (`php tests/run.php --update-goldens`, 56 testu + 27 golden, 0 chyb). Mimo tento task bylo take opraveno: predchozi commit `fix(premium-photo): white FAQ background...` obsahoval zakazany radek `Co-Authored-By`, ktery INSTRUCTIONS.md vyslovne zakazuje — opraveno pres `git commit --amend` (commit nebyl pushnuty).

---

# UKOL: Premium Photo - odsazeni tlacitek, pocet hodnoceni, patička

Datum: 2026-07-21
Zadani: Posunout tlacitka "Naše práce" a "Hodnocení" níže (stejné odsazení jako Blog), zobrazit u Hodnocení reálný počet recenzí ("Dalších XXX hodnocení") a nahradit patičku tou z referenčního designu.
Hotovo kdyz: Obě tlačítka mají stejnou mezeru nad sebou jako tlačítko Blog, tlačítko Hodnocení zobrazuje skutečný zbývající počet recenzí, patička odpovídá vzhledu z `ukazka sablony premim photo/index.html` a vše je DB-driven (žádný vymyšlený text), ověřeno testy a screenshoty desktop/mobil.

## Kroky

- [x] Chrome CDP diagnostika přesných rozestupů (grid bottom vs. button top) pro nase-prace/blog/hodnoceni/sluzby.
- [x] Najít a opravit CSS příčinu chybějící mezery u nase-prace a hodnoceni.
- [x] Zjistit reálný počet aktivních recenzí a napojit dynamický label tlačítka Hodnocení na DB.
- [x] Zprovoznit theme-override mechanismus pro patičku (dosud natvrdo `forTheme(null)`) a vytvořit premium-photo footer partial podle reference.
- [x] Ověřit vše čerstvým screenshotem desktop/mobil a testy.

## Poznamky

- **Tlačítka**: Chrome CDP změřil přesně, že `#nase-prace .hp-portfolio-placeholder` a `#hodnoceni .premium-review-grid` měly `margin-bottom: 0px`, zatímco `#blog .hp-blog-grid` a `#sluzby .hp-services-grid` měly `margin-bottom: 36px` (z generické DB-seedované base CSS `database/seeds/seed.php`, kterou `#blog`/`#sluzby` dědí, ale portfolio/reviews grid ji nemají). Portfolio navíc mělo explicitní `cms.css` pravidlo `.premium-portfolio-section .hp-portfolio-placeholder { margin: 0; }`, které nulovalo i zděděnou bázovou mezeru. Oprava: `margin-top: 0; margin-bottom: 36px;` pro portfolio placeholder a přidání `margin-bottom: 36px;` k `#hodnoceni .premium-review-grid` (cms.css). Sekce `#nase-prace` si ponechává `padding-bottom: 0 !important` (úmyslné pro plynulý přechod do tmavé `#proces` sekce) — mezera je teď mezi mřížkou a tlačítkem, ne pod tlačítkem.
- **Dynamický počet hodnocení**: DB má 33 aktivních recenzí (`ReviewRepository::allActive`), homepage teaser (`partials/home-reviews.phtml`) vždy zobrazuje jen první 3 (`array_slice($items, 0, 3)`, natvrdo v partialu). Přidána `ReviewContentService::countActive()` a `HomepageContentService::buildReviewsCtaHtml()`, které počítají `max(0, total - 3)` a generují label `"Dalších {N} hodnocení"` (fallback `"Všechna hodnocení"` když N=0); nahrazeno volání `buildPageCtaHtml($siteId,'hodnoceni')` (statický titulek stránky) v `fillHomepageFallbacks()`. Ověřeno: tlačítko nyní zobrazuje "DALŠÍCH 30 HODNOCENÍ".
- **Patička byla generická**: `FooterRenderer::buildRichFooterHtml()` volalo `ViewFactory::forTheme(null)` — natvrdo ignorovalo aktivní téma, takže se VŽDY renderoval `app/Views/partials/footer.phtml` (core/generický vzhled, gold #c6943d, Georgia serif — vizuálně z jiné šablony), přestože žádné jiné View v projektu takhle nefunguje (všude jinde platí "theme override wins, core fallback works"). Zádný theme dosud vlastní `partials/footer.phtml` override neměl (ověřeno — nulová shoda gluby).
- Oprava: `FooterRenderer::buildRichFooterHtml()` teď resolvuje skutečné aktivní téma webu (`SiteRepository::findById` + `ThemeTemplateRegistry::resolveForSite`, stejný vzor jako `HomepageContentService::resolveThemeKeyForSite()`) a předá jej do `ViewFactory::forTheme($themeKey)` — theme override cascade teď funguje i pro patičku, beze změny pro témata bez vlastního override (core zůstává fallback).
- Vytvořen `Templates/premium-photo/views/partials/footer.phtml` podle `ukazka sablony premim photo/index.html` (`.foot-grid` se třemi sloupci: brand+socials / Web nav / Kontakt, `.foot-bottom`). Použita stejná `$footer` view-model data jako core (`FooterViewData::forSite()` — žádná duplicace zdroje dat), rozšířená o nové pole `navLinks` (skutečné tituly stránek `/nase-prace`, `/blog`, `/hodnoceni`, `/faq`, `/kontakt` z `pages` tabulky, ne vymyšlený text). Sociální ikony recyklují stávající `partials/footer-socials.phtml` (žádná duplikace SVG). `.foot-bottom` má i třídu `site-footer-bottom`, aby ji `cookie-consent.js` (hledá `.site-footer-bottom` pro injektáž odkazu "Nastavení cookies") beze změny našel.
- Patička nezahrnuje smyšlený popisný odstavec ani "IČO 12345678" z demo reference (statická marketingová/fake fakturační data) — ponechán jen skutečný obsah z DB (název, kontakt, sítě, nav, copyright, právní odkaz `/podminky-uziti`).
- Známé menší omezení (mimo scope zadání): `?theme_preview=` admin náhled jiného tématu nyní zobrazí patičku SKUTEČNÉHO aktivního tématu webu, ne náhledového — dřív patička v náhledu vždy ukazovala jen core verzi bez ohledu na cokoliv. Vyžadovalo by protáhnout preview klíč přes ~8 volání `injectFooterSocials()`/`buildRichFooterHtml()` v `public/index.php`; nebylo požadováno a nejde o regresi otestovaného chování (`php tests/run.php` prošlo čistě).
- Overeno Chrome CDP: `nasePraceBtn.top - nasePraceGrid.bottom = 36px`, `hodnoceniBtn.top - hodnoceniGrid.bottom = 36px` (shoda s `blogBtn`/`blogGrid` i `sluzbyBtn`/`sluzbyGrid`). Screenshoty `storage/qa/premium-photo-faq-onas/after-buttons-footer-1920.png` a `-390.png`.
- Overeno, ze zmena `FooterRenderer` nerozbila jina temata: `?theme_preview=elegant-white` framework aktivni sablonu spravne rozliuje (site 1 ma active_theme_key=premium-photo, tedy footer vzdy premium-photo override — na jinem site_id s jinym aktivnim tematem by spravne spadl na core fallback, protoze zadne jine tema footer.phtml override nema).
- `php tests/run.php --update-goldens`: vsechny live/publish routy zmeneny (+19/-1 az +20/-2 radku) presne o novou patickovou markup — potvrzeno rucnim diffem pred update. Po update: 56 testu + 27 golden, 0 chyb.

## Vysledek

Hotovo. Tlačítka "Naše práce" a "Hodnocení" mají nyní stejný 36px odstup od obsahu nad sebou jako tlačítko Blog (CSS oprava `margin-bottom` na příslušných grid kontejnerech v `cms.css`). Tlačítko Hodnocení zobrazuje skutečný počet zbývajících recenzí "Dalších 30 hodnocení" místo statického "Hodnocení" (nová `ReviewContentService::countActive()` + `HomepageContentService::buildReviewsCtaHtml()`). Patička byla generická napříč celým webem kvůli natvrdo nastavenému `forTheme(null)` v `FooterRenderer` — opraveno na skutečné rozlišení aktivního tématu, a přidán premium-photo footer partial odpovídající referenčnímu designu, plně DB-driven (žádný vymyšlený text). Ověřeno Chrome CDP měřením, screenshoty desktop/mobil a `php tests/run.php` (56 testů + 27 golden, 0 chyb).

---

# UKOL: Premium Photo - CTA tlačítko Kontakt v sekci O nás

Datum: 2026-07-21
Zadani: Přidat do sekce O nás CTA tlačítko vedoucí na Kontakt.
Hotovo kdyz: Sekce O nás má pod seznamem bodů tlačítko "Kontakt" odkazující na `/kontakt`, správně velké (ne roztažené na celou šířku), ověřeno na desktopu i mobilu.

## Kroky

- [x] Přidat CTA `<a href="/kontakt">Kontakt</a>` do `premium-about-split__body` v theme.js (za seznam bodů).
- [x] Zajistit správnou velikost/zarovnání tlačítka v CSS grid kontejneru (`justify-self: start`).
- [x] Ověřit screenshotem desktop/mobil a testy.

## Poznamky

- `.premium-about-split__body{display:grid}` by bez `justify-self:start` roztáhl `<a class="btn btn-ghost">` (inline-flex) na celou šířku grid buňky — přidáno `.premium-about-split__body > .btn { justify-self: start; }` do cms.css.
- Odkaz `/kontakt` a label "Kontakt" jsou hardcoded stejně jako ostatní strukturální nav odkazy v theme.js (např. `/faq` u FAQ merge tlačítka) — jde o pevnou routu webu, ne o vymyšlený obsah.
- `php tests/run.php`: 56 testů + 27 golden, 0 chyb (goldeny beze změny — tlačítko je JS-generované, ne v statickém HTML).

## Vysledek

Hotovo. Sekce O nás má nové CTA tlačítko "Kontakt" pod seznamem bodů, odkazuje na `/kontakt`, správně velikostně omezené (ne roztažené), ověřeno na desktopu i mobilu screenshotem a čistým průchodem testů.
# UKOL: Premium Photo kompaktni burger menu

Datum: 2026-07-21
Zadani: Opravit otevrene mobilni burger menu Premium Photo tak, aby odpovidalo prvnimu referencnimu obrazku a zustalo jako kompaktni horizontalni navigace v hlavicce.

Hotovo kdyz: Pri mobilnim viewportu otevreni burgeru zobrazi polozky v kompaktnim horizontalnim rozlozeni pod logem, kriz zustane vpravo nahore a nevznikne vysoky svisly panel z aktualniho stavu.

## Kroky

- [x] Dohledat DOM, breakpointy a CSS konflikt otevreneho menu; zachytit baseline a ohodnotit ji.
- [x] Upravit pouze Premium Photo navigaci bez zmeny DB obsahu.
- [x] Regenerovat publikovany vystup a overit otevreni/zavreni na mobilu cerstvym Chrome screenshotem.
- [x] Spustit regresni testy, posoudit dokumentaci a provest wiki reflexi.

## Poznamky

- Rubrika 100 bodu: kompaktni geometrie hlavicky 35, horizontalni rozlozeni odkazu 30, logo a zaviraci kriz 15, typografie/oddelovace/aktivni stav 10, funkcni otevreni-zavreni a runtime integrita 10.
- Reference od uzivatele: 415x158 px. Aktualni stav: 390x434 px; hlavni vada je vertikalni seznam s radkovymi oddelovaci misto dvouradkove horizontalni navigace.
- Existujici zmeny `Templates/premium-photo/assets/*.css` a `storage/published/*` byly v pracovnim stromu uz pred timto ukolem a musi zustat zachovane.
- In-app browser vratil `No browser is available`; chyba je viditelne zaznamenana a QA pokracuje kanonickou Chrome/CDP cestou projektu.
- `scripts/qa_chrome_cdp_capture.ps1` ma novy prepinac `-OpenNavigation`, ktery pred zachycenim aktivuje skutecne tlacitko `.nav-toggle`; baseline a final tak meri stejny interakcni stav.
- Baseline `storage/qa/premium-photo-burger/baseline-open-390.png`, Chrome 390x844 (full page 12489 px), skore 35/100: funkcni otevreni a logo ano, ale vyska panelu, svisle odkazy a neorameny kriz neodpovidaji reference.
- `theme.css` nyni na mobilu sklada `.nav-links` jako kompaktni zalamovany flex bez radkovych oddelovacu; odkazy maji sipku tesne u textu a otevreny kriz dostal 54px zlaty ramecek.
- Chrome helper uklada pres `-NavigationOutput` take samostatny viewportovy dukaz bez zmenseni cele dlouhe stranky.
- Iterace 1 `final-open-viewport-390.png`: 88/100. Kompaktni panel, horizontalni odkazy a orameny kriz jsou opraveny; zbyvala mobilni CTA navic a nezalomena polozka Nase prace.
- Iterace 2 skryva mobilni `.nav-cta` a rozklada pet hlavnich odkazu do jedne gridove rady; odkaz `#nase-prace` ma referencni dvouradkove zalomeni.
- Finalni dukaz `storage/qa/premium-photo-burger/final-open-viewport-390-v2.png`, Chrome 390x844/DPR 1, skore 100/100; runtime ma `theme-premium-photo nav-open` a full page zustava bez horizontalniho preteceni.
- `php tests/run.php`: 56 testu a 27 golden kontrol, 0 chyb; `git diff --check` bez chyb. README, ARCHITECTURE a INSTRUCTIONS byly posouzeny bez nutnosti zmeny.
- Wiki reflection: pipeline domena byla aktualizovana o reprodukovatelny CSS kontrakt, QA prepinace a acceptance; samostatny wiki commit `57649e2`.

## Vysledek

Hotovo. Mobilni burger menu Premium Photo je kompaktni, pet hlavnich odkazu odpovida referencnimu rozlozeni, Nase prace se zalamuje do dvou radku, CTA se na mobilu skryva a kriz ma zlaty ramecek.

---

# UKOL: Premium Photo vicefazove vizualni opravy

Datum: 2026-07-21
Zadani: V Ralph Loopu sjednotit timeline, navigaci, hero nadpisy, Cenik a kontaktni CTA napric Premium Photo podle dodanych screenshotu a aktualniho vizualu sablony.

Hotovo kdyz: Vsechny nize uvedene routy a interakcni stavy odpovidaji zadani na desktopu i mobilu, pouzivaji DB obsah bez skrytych fallbacku, maji cerstve Chrome screenshoty a projdou PHP/golden testy.

## Kroky

- [x] Zachytit baseline a zmapovat DOM/CSS pro homepage timeline, navigaci, Kontakt, Blog, Nase prace, Cenik, FAQ, Sluzby a detailni routy.
- [x] Posunout sedou i zlatou timeline linku tak, aby obe zacinaly az v realnem pocatku pohybliveho bodu pod textaci.
- [x] Zvetsit horizontalni vnitrni prostor desktopoveho tlacitka Kontakt v navigaci.
- [x] Prevest mobilni otevrene menu z horizontalniho gridu na svisly seznam polozek pod sebou.
- [x] Presunout title/kicker/popis do obrazove hero sekce na routach Kontakt, Blog a Nase prace a upravit kontrast textu.
- [x] Stejny hero kontrakt aplikovat na detail blogu, detail Nase prace, detail sluzby a vypis Sluzby.
- [x] Prevest cely Cenik ze stareho oranzoveho defaultu do aktualniho Premium Photo vizualu.
- [x] Zvyraznit aktivni hlavni navigacni polozku podle aktualni routy, vcetne mapovani detailu na rodicovskou sekci.
- [x] Pridat staticky kontaktni CTA blok do FAQ, Blog vypisu, detailu blogu, detailu Nase prace, detailu sluzby a vypisu Sluzby.
- [x] Regenerovat publish goldeny a overit desktop/mobile screenshoty a interakce pro vsechny dotcene routy.
- [x] Spustit regresni testy, aktualizovat relevantni dokumentaci a provest wiki reflexi.

## Poznamky

- Kontaktni CTA text: `Pokud mate nejake dotazy, nevahejte nas kontaktovat.` a doplnujici text `Vse si vysvetlime a domluvime se.`; tlacitko vede na `/kontakt` a pouzije vizual sablony.
- Rubrika 100 bodu: hero kompozice napric routami 25, timeline start 15, Cenik theme fidelity 15, desktop/mobil navigace a aktivni stav 20, CTA bloky a routovani 15, runtime/publish/testy 10.
- Referencni screenshoty od uzivatele jsou primarni vizualni cil; runtime je `http://127.0.0.1:8766/` a QA pouziva Chrome/CDP s DPR 1.
- Predchozi stav byl ulozen checkpoint commitem `8c67953`; dodany lokalni referencni PNG zustava mimo Git.
- Audit potvrdil spolecnou pricinu nizkych title: theme archetypy mely obrazovy `.video-band` a az za nim samostatny svetly `.page-head`; oprava presouva skutecny title/kicker/popis dovnitr hero DOM.
- `theme.css` meni mobilni `.nav-links` na jednosloupcovy seznam, vraci CTA Kontakt a zvetsuje jeho horizontalni padding; aktivni route dostane `aria-current=page` a podtrzeni.
- Timeline rail nyni pouziva stejne dynamicke `--timeline-track-start` jako playhead a novou vysku `--timeline-track-height`; seda ani zlata cast uz neprochazi nad bodem.
- Premium archetypy Blog, Kontakt, Nase prace a Sluzby maji title primo v hero; FAQ/Blog/Sluzby a oba detailni archetypy dostaly staticky kontaktni CTA blok. Genericky detail sluzby dostava stejny blok idempotentne z theme JS.
- Cenik ma theme-scoped filmovy hero, serifovou typografii, neutralni karty, tmavou featured kartu a tmave CTA misto stare oranzove defaultni vrstvy.
- Prvni test odhalil zastaralou podminku, ktera vyzadovala sedou osu pres celou sekci. `PremiumPhotoDataContractTest` nyni hlida novy track-start/track-height kontrakt, hero copy uvnitr bandu, aktivni nav, vertikalni mobilni menu a CTA pritomnost.
- `node --check` neni v prostredi dostupny; chyba byla viditelne zaznamenana a JS bude overen skutecnym Chrome runtime bez nahradniho tvrzeni.
- Chrome helper nyni explicitne pouziva DPR 1, vypnutou cache a vraci `scrollWidth`, `clientWidth` i horizontalni overflow; nove screenshoty tak splnuji zamknuty QA kontrakt.
- Iterace 1 Chrome: Kontakt/Blog/Nase prace hero a aktivni nav funguji, mobilni menu je svisle bez overflow; Cenik zbyval na 90/100 kvuli legacy prepisu featured karty. Souhrnne 95/100.
- Timeline dukaz meri rail i seznam od `y=4233.27` a shodnou vysku `691.83px`; seda linka uz neprochazi titulkem. Aktivni screenshot ukazuje zlatou linku i tercik od stejneho pocatku.
- Detail sluzby odhalil oddeleny obrazovy sibling pred `.svc-hero`; theme JS jej nyni presouva dovnitr hero a CSS sklada title pres obraz.
- Cenik featured karta dostala explicitni theme prioritu proti legacy `.hp-cenik-card` pravidlum.
- Druhy Chrome pruchod potvrdil tmavou featured kartu Ceniku a CTA bloky FAQ/Sluzby/detailu. U detailu sluzby globalni hero CSS stale drzelo media `position:relative !important`; theme scope byl dorovnan explicitnim absolute/inset kontraktem.
- Opakovany detail ukazal, ze media neni spolehlive dostupne pres `previousElementSibling` kvuli generickemu XML/hero wrapperu; vyber je nyni explicitne `main > .theme-hero-video`, bez nahradniho obrazku.
- Chrome DOM diagnostika upresnila realny live kontrakt: `main` zacina `NAV.breadcrumbs -> IMG.hero-bg -> HEADER.hero.svc-hero`; selector proto zahrnuje skutecny `main > .hero-bg` a presouva tento DB obraz do headeru.
- Iterace 2: 100/100. Detail sluzby po finalnim selectoru zkratil stranku z 4203 na 3243 px a hero zacina na `y=106.8`; title, obraz a CTA jsou v jednom kompozicnim bloku. Vsechny zachycene routy maji `scrollWidth === clientWidth`.
- Finalni regrese: PHP lint vsech Premium Photo archetypu bez chyby, `php tests/run.php` 57/57 a golden checks 27/27, `git diff --check` bez chyb. Live i publish goldeny byly vedome regenerovany.
- README, ARCHITECTURE a INSTRUCTIONS byly posouzeny bez nutnosti zmeny; implementacni a acceptance kontrakt byl aktualizovan v projektove wiki, samostatny commit `71ded4e`.

## Vysledek

Hotovo. Timeline zacina seda i zlata az u realneho tracku, navigace ma prostornejsi Kontakt, svisle mobilni menu a aktivni route, title jsou v hero napric vypisy a detaily, Cenik odpovida Premium Photo a vsechny pozadovane routy maji jednotne kontaktni CTA.

---

# UKOL: Premium Photo scroll-spy a zjednoduseni CTA

Datum: 2026-07-21
Zadani: Odstranit nadbytecnou vetu z kontaktnich CTA, na homepage zvyraznovat anchor navigaci podle aktualni sekce ve viewportu a zajistit, ze tlacitko Kontakt nebude nikdy podtrzene.

Hotovo kdyz: CTA obsahuje jen statickou vyzvu a tlacitko, homepage meni aktivni polozku pri scrollovani podle sekce a `.nav-cta` nema podtrzeni v zadnem aktivnim stavu.

## Kroky

- [x] Upravit CTA markup, homepage scroll-spy a CSS aktivniho stavu.
- [x] Rozsirit kontraktni testy a regenerovat publikovane goldeny.
- [x] Overit homepage scroll-spy a navigaci cerstvym Chrome/CDP behom na desktopu i mobilu.
- [x] Spustit celou regresi, posoudit dokumentaci a provest wiki reflexi.

## Poznamky

- Rubrika 100 bodu: homepage scroll-spy 40, odstraneni textace ze vsech CTA 25, Kontakt bez podtrzeni 20, desktop/mobil runtime a regrese 15.
- Dodany referencni PNG zustava mimo Git.
- Chrome iterace 1 odhalila spatny vyber posledni DOM sekce nad markerem: Blog i Nase prace se oznacovaly jako FAQ. Scroll-spy nyni vybira sekci, jejiz realny bounding box obsahuje 35 % vysky viewportu.
- Chrome iterace 2: Blog a Nase prace maji pri cilenem scrollu spravne `aria-current=location`; mobilni Kontakt je aktivni, ale computed `::before` je `none`. Vsechny tri behy jsou bez horizontalniho overflow.
- Finalni regrese: `php tests/run.php` 57/57, golden checks 27/27, lint vsech Premium Photo archetypu bez chyby a `git diff --check` bez chyby.
- QA helper podporuje `-ScrollSelector` a `-ScrolledOutput`; vraci `ScrolledState` s aktivnim odkazem a computed obsahem `::before`.
- README, ARCHITECTURE a INSTRUCTIONS byly posouzeny bez nutnosti zmeny. Projektova wiki popisuje novy kontrakt v commitu `0a7c388`.

## Vysledek

Hotovo. Nadbytecna CTA veta je odstranena, homepage navigace sleduje realnou sekci ve viewportu a Kontakt se nikdy nepodtrhuje.

---

# UKOL: Premium Photo homepage Cenik, FAQ a recenze

Datum: 2026-07-21
Zadani: Zajistit samostatne viditelne sekce Cenik a FAQ na homepage a pridat desktopove ovladani recenzi doleva/doprava bez zobrazeni ovladacich sipek na mobilu.

Hotovo kdyz: Homepage renderuje DB sekce Ceník i FAQ jako samostatne části a desktopová sekce recenzí umožňuje procházení oběma směry, zatímco mobilní verze zůstane bez šipek a bez horizontálního overflow.

## Kroky

- [x] Zmapovat homepage DB markup, theme partialy a aktualni runtime sekci Cenik, FAQ a recenze; zachytit baseline.
- [x] Opravit nebo doplnit homepage sekce Cenik a FAQ bez duplikace DB obsahu.
- [x] Pridat pristupne desktopove ovladani recenzi vlevo/vpravo a skryt ho na mobilu.
- [x] Regenerovat publish/goldeny a overit desktop/mobile Chrome screenshoty i interakce.
- [x] Spustit regresni testy, posoudit dokumentaci a provest wiki reflexi.

## Poznamky

- Bod 3 v zadani byl prazdny; aktualni scope tvori body 1 a 2.
- Rubrika 100 bodu: samostatny Cenik 25, samostatne FAQ 25, funkcni desktopove listovani recenzi 30, mobil bez sipek 10, runtime/testy/bez overflow 10.
- Uzivatelsky referencni PNG zustava mimo Git.
- Baseline 1440x900: Premium Photo JS skryval DB sekce `#cenik` a `#faq`; review partial zahazoval vsechny recenze za treti kartou. Skore 20/100, protoze oba povinne oddily i carousel ovladani nebyly viditelne.
- `theme.js` uz obsah ceniku ani FAQ neskryva a zachovava poradi Blog -> Cenik -> FAQ -> Recenze -> Kontakt. `cms.css` dava DB cenikovym kartam Premium Photo vzhled; prazdny DB nadpis ani intro nenahrazuje skrytym fallbackem.
- Premium review partial renderuje nacteny DB set. Theme JS jej na desktopu obali viewportem a doplni pristupna tlacitka Predchozi/Dalsi; CSS na mobilu ovladani skryje a zachova prvni tri karty bez horizontalniho posuvu.
- Po navratu FAQ do samostatne sekce pouziva kontaktni formular plnou jednosloupcovou sirku misto prazdneho druheho sloupce.
- Chrome/CDP helper ma nove volitelne `-ClickSelector` a `-ClickedOutput`; pro carousel vraci transform pred/po kliknuti, pocet DB karet, stav tlacitek a viditelnost Ceniku/FAQ.
- Chrome desktop 1440x900: `#cenik` i `#faq` maji `hidden=false`, oba dostavaji spravny anchor aktivni stav a stranka ma 1440/1440 px bez overflow. Carousel nacetl 10 DB recenzi; klik vpravo zmenil transform z 0 na -1131.98 px a zpristupnil levou sipku.
- Chrome mobil 390x844: ovladaci kontejner ma computed `display:none`, transformace zustala prazdna, prvni tri karty jsou ve svislem toku a `scrollWidth === clientWidth`.
- Finalni vizualni skore 100/100: Cenik 25, FAQ 25, desktopove obousmerne ovladani 30, mobil bez sipek 10, runtime bez overflow 10.
- Finalni regrese: `php tests/run.php` 57/57 a golden checks 27/27; lint theme partialu i HomepageContentService bez chyby; `git diff --check` bez chyby.
- README, ARCHITECTURE a INSTRUCTIONS byly posouzeny bez nutnosti zmeny. Projektova pipeline wiki byla aktualizovana a commitnuta jako `12f81cd`.

## Vysledek

Hotovo. Homepage zobrazuje DB Cenik i FAQ jako samostatne sekce a desktopove recenze lze prochazet sipkami vlevo/vpravo; mobil zustava bez sipek.

---

# UKOL: Premium Photo detail hero, FAQ hero a navigace Sluzby

Datum: 2026-07-21
Zadani: Pouzit hlavni/prvni obrazek blogu a Nase prace jako hero bez nasledne duplicity, presunout FAQ title a popis do hero a opravit nechteny bold cele navigace na route Sluzby; vysledek commitnout a pushnout.

Hotovo kdyz: Detail blogu i portfolio detail zobrazi prvni dostupne DB medium v hero a stejne medium uz neni v tele; bez media zustane existujici prazdne/defaultni hero chovani. FAQ title, popis a label jsou uvnitr obrazove hero sekce. Navigace `/sluzby` ma stejnou vahu pisma jako ostatni routy. Vse projde testy a cerstvym Chrome QA, commit je pushnuty.

## Kroky

- [x] Zmapovat detailni view modely/archetypy, FAQ hero DOM a CSS konflikt navigace Sluzby; zachytit baseline.
- [x] Zavest DB-first hero medium pro blog detail a portfolio detail bez duplicity v tele.
- [x] Presunout FAQ title/popis do hero a opravit font-weight navigace Sluzby.
- [x] Regenerovat publish/goldeny a overit dotcene routy Chrome screenshoty na desktopu i mobilu.
- [x] Spustit regresi, aktualizovat wiki, commitnout a pushnout.

## Poznamky

- Rubrika 100 bodu: blog hero medium bez duplicity 25, portfolio hero medium bez duplicity 25, FAQ title v hero 20, normalni navigace Sluzby 15, mobil/runtime/testy 15.
- Hlavni/prvni obrazek musi zustat DB zdrojem pravdy; theme nesmi vymyslet nahradni medium. Pri absenci se zachova existujici prazdny/defaultni hero kontrakt.
- Uzivatelsky referencni PNG zustava mimo Git.

## Vysledek

Implementace a QA hotove se skore 100/100: oba detailni hero bloky pouzivaji prvni dostupne DB medium bez duplicity, FAQ copy je uvnitr hero, navigace Sluzby ma normalni vahu 300 a desktop/mobil nemaji horizontalni overflow. Regrese: 57/57 testu a 27/27 golden kontrol.

---

# UKOL: Premium Photo mobilni paticka podle reference

Datum: 2026-07-22
Zadani: Upravit pouze mobilni paticku Premium Photo podle prvni dodane reference: kontaktni radky s ikonami, centrovane socialni site, oddelene navigacni radky se sipkami a kompaktni pravni spodek.

Hotovo kdyz: Pri mobilnim viewportu 390 CSS px odpovida poradi, geometrie, hierarchie a vizual paticky prvnimu obrazku, vsechny hodnoty zustavaji DB-driven, desktopova paticka se nezmeni, nevznikne horizontalni overflow a cerstvy Chrome screenshot projde rubrikou.

## Kroky

- [x] Zmapovat datovy kontrakt footeru, aktualni DOM/CSS a zachytit mobilni baseline.
- [x] Upravit Premium Photo footer partial a mobilni CSS bez hardcoded klientskych dat.
- [x] Regenerovat vystupy a iterovat Chrome screenshot proti reference.
- [x] Spustit regresni testy, posoudit dokumentaci a provest wiki reflexi.

## Poznamky

- Referencni rubrika 100 bodu: poradi a kompletni obsah 30, kontaktni ikony/radky 20, socialni ikony 10, navigacni radky se sipkami 20, pravni spodek 10, responzivita/runtime 10.
- Dodane obrazky jsou konverzacni reference; existujici uzivatelsky PNG v rootu zustava mimo Git.
- Baseline 390x844 mela desktopovy sloupcovy footer vysoky pres viewport: logo/socials, Web a Kontakt pod sebou, bez kontaktnich ikon, navigacnich separatoru a kompaktniho pravniho radku. Skore 24/100.
- Theme partial nyni obsahuje samostatny mobilni DOM. Kontaktni hodnoty a socialni site nadale cte z `FooterViewData`; prezentacni ikony jsou lokalni inline SVG. Desktopovy `foot-grid` zustal zachovan.
- Mobilni navigace ma referencni poradi Nase prace, Cenik, Blog, FAQ, Kontakt; pro existujici DB stranky prebira jejich aktualni label, systemovy odkaz Cenik ma prezentacni vychozi label.
- Datovy kontrakt test nyni hlida mobilni kontaktni DOM, DB promenne, odkaz Cenik a izolaci desktop/mobile CSS. Publish a golden vystupy byly regenerovany.
- Iterace 1 dosahla 96/100: povinne prvky, poradi, ikony a separatorove radky odpovidaji reference; zbyvala prilis siroka kompozice a o 31 px vyssi navigace. Druha CSS korekce nastavila referencni bocni rytmus a 29px navigacni radky.
- Finalni Chrome QA: mobil 390x844 a desktop 1440x900 maji nulovy horizontalni overflow. Mobilni rubrika 100/100; desktopova paticka zustala ve stejne trisloupcove kompozici.
- Regrese prosla 57/57 testu a 27/27 golden kontrol; `git diff --check` bez chyby. README, ARCHITECTURE a INSTRUCTIONS byly posouzeny bez nutnosti zmeny. Pipeline wiki byla aktualizovana a commitnuta jako `a58eef7`.

## Vysledek

Hotovo. Premium Photo ma na telefonu referencni kontaktni paticku s DB kontakty, lokalnimi SVG ikonami, centrovanymi socialnimi sitemi, petiradkovou navigaci a kompaktnim pravnim spodkem; desktop zustal beze zmeny.

---

# UKOL: Odstraneni data z kontaktu a homepage FAQ/form layout

Datum: 2026-07-22
Zadani: Odstranit pole datum ze vsech frontendovych kontaktnich formularu i backendoveho zpracovani a e-mailu; na homepage zobrazit FAQ ukazku a kontaktni formular vedle sebe na desktopu a pod sebou na mobilu.

Hotovo kdyz: Zadny verejny kontaktni formular nerenderuje ani neposila `event_date`, backend tento vstup nepouziva a e-mail datum neobsahuje. Homepage ma pri desktopu dvousloupcovy FAQ/form layout, pri mobilu jednosloupcovy, bez overflow a se zachovanym DB obsahem.

## Kroky

- [x] Zmapovat vsechny FE formulare, POST handler, mail builder, testy a homepage FAQ/contact kompozici.
- [x] Odstranit datum z FE, backend prijmu a e-mailoveho vystupu vcetne regresnich testu.
- [x] Upravit homepage FAQ/form layout pro desktop a mobil.
- [x] Regenerovat vystupy, overit runtime viewporty a kompletni regresi.
- [x] Posoudit dokumentaci, provest wiki reflexi a commitnout hotovy ukol.

## Poznamky

- Predchozi hotova mobilni paticka byla pred zahajenim commitnuta samostatne jako `d523b6e`; uzivatelsky screenshot zustal mimo Git.
- Bezpecnostni kontrakt: i kdyby klient poslal podvrzene `event_date`, backend jej nesmi zahrnout do predmetu, tela e-mailu ani dalsiho zpracovani.
- Runtime mel datum ve ctyrech verejnych view cestach a MarkupNormalizer jej navic menil na `type=date`; POST handler hodnotu cetl a vkladal do mail body. Premium Photo pole byla odstranena primo a centralni normalizacni guard odstrani legacy `event_date` i z dalsich aktivnich view bez skryteho prijmu na backendu.
- Premium Photo JS presouva existujici DB-renderovany FAQ container do kontaktniho gridu bez klonovani textu. Desktop grid ma dva stejne sloupce, breakpoint do 850 px zachovava jeden sloupec.
- Legacy normalizace odstranuje datum i v publish ceste, ktera zamerne nepousti accessibility pass. Regresni kontrakt hlida Premium Photo FE, absenci backendoveho POST/mail pouziti a dvousloupcovou homepage kompozici.
- Prvni runtime kontrola potvrdila spravnou geometrii a absenci data, ale odhalila vyssi specificitu puvodniho svetleho FAQ pravidla. Oprava pouziva stejne body/theme/home scope, aby byl FAQ sloupec v tmave kontaktni sekci citelny.
- Projektova architektura nyni explicitne dokumentuje verejny POST kontrakt bez data. Wiki pipeline zaznamenava FE/BE guard i responzivni presun FAQ bez duplikace DB obsahu.
- Finalni Chrome QA: homepage 1440x900 ma formular a FAQ vedle sebe; homepage 390x844 je radi pod sebe; `/kontakt` 390x844 nema datum. Vsechny tri capture maji `scrollWidth === clientWidth`.
- Finalni regrese: 58/58 testu, 27/27 golden kontrol a `git diff --check` bez chyby. README a INSTRUCTIONS byly posouzeny bez nutnosti zmeny; ARCHITECTURE bylo aktualizovano. Wiki update je commit `4e58228`.

## Vysledek

Hotovo. Datum bylo odstraneno z Premium Photo formularu, ze vsech live/publish vystupu pres centralni guard a z backendoveho mail kontraktu; FAQ ukazka sdili s homepage formularem responzivni dvousloupcovy/jednosloupcovy layout.

---

# UKOL: Premium Photo Cenik podle reference

Datum: 2026-07-22
Zadani: Upravit samostatnou stranku `/cenik` podle dodane desktopove reference se sirokym fotografickym hero, titulkem vlevo a tremi cenovymi kartami pod centrovanym uvodem.

Hotovo kdyz: Pri desktopovem viewportu 1440 CSS px odpovida hero, sekcni hierarchie, tri karty, featured stitek, typografie a rozestupy reference; ceny a texty zustavaji DB-driven, mobil je citelny bez overflow a testy projdou.

## Kroky

- [x] Zmapovat cenikovy archetyp, DB data, hero zdroj a zachytit baseline.
- [x] Upravit Premium Photo cenikovy DOM/CSS podle reference bez statickych cen.
- [x] Iterovat desktopovy screenshot a overit mobilni responzivitu.
- [x] Regenerovat vystupy, spustit regresi a provest wiki reflexi.

## Poznamky

- Rubrika 100 bodu: hero crop/overlay/title 25, sekcni geometrie a intro 15, tri cenove karty 30, featured stav a typografie 15, mobil/runtime/data vlastnictvi 15.
- Referencni obraz ma 1536x1227 px; QA se zamkne na 1440 CSS px s DPR 1 a porovna proporce, ne fyzicky DPR export.
- Uzivatelsky screenshot v rootu zustava mimo Git.
- Baseline 1440x900 mela skore 58/100: hero bylo centrovane a 520 px vysoke, cenikovy uvod vlevo, featured karta tmava/vysunuta a stranka vyrazne delsi nez referencni horni kompozice.
- Novy theme archetyp pouze preusporadava `CenikPageData`: DB heading je eyebrow, prvni veta DB intra je hlavni nadpis a zbytek zustava popisem. Ceny, souhrny i vsechny vlastnosti zustavaji DB-driven.
- Prvni iterace dosahla 88/100: karty a mobil byly spravne, ale legacy grid centroval hero obsah. Druha iterace s explicitnim flex alignmentem a svetlym featured stavem dosahla 94/100.
- Chrome/CDP QA: desktop 1440x900 i mobil 390x844 nemaji horizontalni overflow; mobil radi tri balicky pod sebe. In-app browser backend byl prazdny, proto byl otevrene pouzit projektovy Chrome/CDP capture skript.
- Regrese: 59/59 PHP testu, 27/27 golden kontrol a `git diff --check` bez chyby. Wiki pipeline byla aktualizovana a commitnuta jako `fa48372`; README, ARCHITECTURE a INSTRUCTIONS nevyzadovaly zmenu.

## Vysledek

Hotovo. Cenik pouziva DB-driven Premium Photo archetyp, fotograficky hero s obsahem vlevo, tri svetle balickove karty s cernym featured stitkem a jednosloupcovy mobilni layout.

---

# UKOL: Doladeni karet Ceniku a desktopove paticky

Datum: 2026-07-22
Zadani: Opravit geometrii labelu a checku v cenikovych kartach, lehce zvetsit prostredni balicek a zmensit bocni karty; desktopovou paticku doplnit o spravne kontaktni ikony, radkovani a typografii podle reference.

Hotovo kdyz: Desktopovy Cenik nema posunute badge ani checky, prostredni karta je zretelne ale jemne dominantni, bocni karty jsou mensi; paticka ma zlate ikony telefonu/e-mailu/adresy a odpovida cilovemu odsazeni bez mobilni regrese nebo overflow.

## Kroky

- [x] Zachytit baseline a zmerit kolize cenikovych karet a paticky.
- [x] Upravit cenikove karty a overit novym desktop screenshotem.
- [x] Upravit desktopovou paticku vcetne datove napojenych ikon a responzivity.
- [x] Regenerovat vystupy, spustit testy, wiki reflexi a uzavrit task.

## Poznamky

- Predchozi hotova verze byla pred zahajenim ulozena v commitu `108712e`; uzivatelsky screenshot zustal mimo Git.
- Rubrika 100 bodu: cenikova geometrie 30, label/check alignment 20, featured/bocni proporce 15, paticka ikony a data 15, paticka typografie/spacing 15, runtime/mobil 5.
- Baseline Chrome/CDP 1440x900 mela 72/100: legacy pseudo-element prepisoval checky, badge kolidoval s hranou a vsechny karty mely stejnou dominanci; desktopovy kontakt v paticce nemel ikony ani cilove kompaktni radkovani.
- Theme CSS nyni neutralizuje pozdni legacy pravidla explicitnim checkmark boxem, pouziva desktop grid `.94fr 1.08fr .94fr` a vysku 610/638 px. Footer partial dostal DB-driven radky telefonu, e-mailu a adresy s inline SVG ikonami; mobilni struktura zustala samostatna.
- Regresni kontrakt navic hlida tri desktopove kontaktni ikonove radky a zachovani klikatelnych `tel:`/`mailto:` odkazu z DB dat.
- Iterace 1 dosahla 89/100: checky, badge a footer byly opraveny, ale pevna vyska 610 px nechala delsi Premie seznam pretect. Pevne vysky byly odstraneny; grid nyni natahne obsahove karty a dominance prostredni karty vznika 14px svislym odsazenim pouze u bocnich karet.
- Iterace 2 dosahla 97/100. Finalni desktop 1440x900 a mobil 390x844 nemaji horizontalni overflow; zadny seznam nepreteka a mobilni footer zustal v samostatnem puvodnim layoutu.
- Regrese: 59/59 PHP testu, 27/27 golden kontrol a `git diff --check` bez chyby. README, ARCHITECTURE a INSTRUCTIONS byly posouzeny bez potreby zmeny; wiki pipeline byla aktualizovana a commitnuta jako `d25c03d`.

## Vysledek

Hotovo. Cenik ma stabilni badge a checky, jemne dominantni prostredni kartu a mensi bocni karty; desktopovy footer ma DB-driven zlate kontaktni ikony a cilove kompaktni radkovani.

---

# UKOL: Centrovat Cenik a zkratit Premiovy balicek

Datum: 2026-07-22
Zadani: Vystredit spodní cenikovou textaci a featured label; zkratit Premium balicek o tri polozky a nahradit je jednou polozkou „Vse ze Standardniho“, aby byly bocni karty vyskové vyrovnane.

Hotovo kdyz: Nadpis i popis doplnkovych cen jsou skutecne centrovane, badge je geometricky uprostred prostredni karty a Premium ma o tri konkretni body mene plus souhrn „Vse ze Standardniho“, se srovnatelnou vyskou se Zakladnim na desktopu; DB, seed, publish a mobil zustanou konzistentni.

## Kroky

- [x] Zmapovat CSS kolize, aktualni DB hodnotu, seed a migracni cestu.
- [x] Opravit centrovani textace a badge v theme CSS.
- [x] Upravit Premium obsah append-only migraci, seeder a aktualni lokalni DB.
- [x] Zachytit desktop/mobile screenshot, regenerovat vystupy a spustit regresi.
- [x] Provest wiki reflexi a commitnout hotovou zmenu.

## Poznamky

- Pracovni strom byl pred ukolem cisty krome uzivatelskeho neversionovaneho screenshotu.
- Rubrika 100 bodu: cenik-other centrovani 20, badge geometrie 20, bocni vysky 25, Premium obsah/DB kontrakt 20, mobil/runtime/testy 15.
- Historicka migrace `014_fill_cenik_packages.sql` je nemenne aplikovana historie; oprava dostane novou migraci s dalsim poradovym cislem.
- CSS centrovani je nyni explicitni: badge pouziva `left:0; right:0; width:max-content; margin-inline:auto` misto procentniho posunu a `cenik-other` centruje vlastni box i oba textove potomky.
- Premium ma sedm bodu: novy souhrn „vse ze Standardniho balicku“ plus sest odlisujicich benefitu. Odstraneny byly redundantni body cely den, online galerie a konzultace. Zmena je v DemoContentSeederu, hlavnim seedu a nove append-only migraci `023`; migrace `014` zustala nedotcena.
- Lokalni DB migraci `023` aplikovala a zaznamenala jednou; read-only kontrola potvrdila presne 7 bodu. Prvni pomocny regex count selhal na kodovani odrazky az po uspesnem commitu transakce, proto byl vysledek znovu overen bez zavislosti na znaku odrazky.
- Finalni Chrome/CDP 1440x900 dosahl 99/100: badge i doplnkova textace jsou centrovane a bocni karty maji srovnatelnou vysku. Mobil 390x844 nema horizontalni overflow.
- Regrese: 59/59 PHP testu, 27/27 golden kontrol, PHP lint a `git diff --check` bez chyby; historicka migrace `014` nema diff. README, ARCHITECTURE a INSTRUCTIONS byly posouzeny bez potreby zmeny. Wiki byla aktualizovana a commitnuta jako `42763eb`.

---

# UKOL: Opravit centrovani cenik-other pres cely grid

Datum: 2026-07-22
Zadani: Opravit stale opticky posunutou textaci v sekci `cenik-other`.

Hotovo kdyz: `cenik-other` zabira vsechny sloupce cenikoveho gridu a nadpis i popis jsou centrovane vuci cele sekci; zmenu kryje test a cerstvy desktopovy screenshot.

## Kroky

- [x] Najit skutecnou pricinu mimo samotne `text-align`.
- [x] Roztahnout `cenik-other` pres vsechny sloupce gridu a pridat regresni kontrolu.
- [x] Overit testy a cerstvym screenshotem.
- [x] Provest wiki reflexi a commitnout hotovou opravu.

## Overeni

- Root cause: `text-align:center` centroval obsah pouze uvnitr jednoho sloupce rodicovskeho gridu.
- CSS kontrakt: `grid-column:1 / -1`, `justify-self:stretch`, plna sirka bez `max-width` omezeni.
- Chrome/CDP 1440x1100: textace je geometricky uprostred sekce, bez horizontalniho overflow; screenshot `storage/qa/premium-photo-cenik-other-grid-centered.png`.
- Regrese: 59/59 PHP testu a 27/27 golden kontrol.
- Wiki reflexe: pipeline dokumentace aktualizovana a commitnuta jako `b691012`.

## Vysledek

Hotovo. `cenik-other` zabira celou sirku cenikoveho gridu a jeho nadpis i popis jsou skutecne centrovane vuci cele sekci.

---

# UKOL: Hlavicka bez borderu a pomalejsi burger animace

Datum: 2026-07-22
Zadani: Odstranit obvodove linky hlavicky a prodlouzit prechod ikony burger/krizek o 50 procent.

Hotovo kdyz: Mobilni hlavicka ani rozbalena navigace nemaji oddelovaci border, animace tri linek trva 375 ms misto 250 ms a vzhled je overen v zavrenem i otevrenem stavu.

## Kroky

- [x] Zachytit vychozi mobilni stav a potvrdit zdroj borderu a animace.
- [x] Upravit theme CSS a pridat regresni kontrakt.
- [x] Overit zavreny i otevreny stav screenshoty a testy.
- [x] Provest wiki reflexi a commitnout opravu.

## Poznamky

- Rubrika 100 bodu: hlavicka bez borderu 40, zachovany button styling 15, animace presne 375 ms 25, mobilni layout 10, runtime a regrese 10.
- Uzivatelsky neversionovany screenshot zustava mimo scope a nebude commitnut.
- Baseline 60/100: layout a zlaty ramecek tlacitka sedi, ale `.site-header` i mobilni `.site-nav` mely spodni 1px border a transformace ikony trvala jen 250 ms.
- Theme CSS nyni nastavuje oba obalove prvky na `border:0`; ramecek otevreneho `.nav-toggle` zustava zachovan. Prechod linek je presne 375 ms, tedy 1,5nasobek puvodnich 250 ms.
- Prvni regresni beh spravne selhal kvuli chybne ceste noveho testu: kontrola cetla `cms.css`, ale hlavicku vlastni `theme.css`. Test byl opraven tak, aby kontroloval skutecny zdroj bez fallbacku.
- Finalni Chrome/CDP mobil 390x844: zavreny i otevreny stav bez oddelovacich borderu a bez horizontalniho overflow. Finalni skore 100/100; screenshoty `storage/qa/premium-photo-header-final-closed.png` a `storage/qa/premium-photo-header-final-open.png`.
- Regrese: 59/59 PHP testu a 27/27 golden kontrol. README, ARCHITECTURE a INSTRUCTIONS byly posouzeny bez potreby zmeny; technicky kontrakt byl doplnen do wiki pipeline a commitnut jako `5c1449a`.

## Vysledek

Hotovo. Hlavicka i panel mobilni navigace jsou bez oddelovacich borderu, zlaty ramecek otevreneho tlacitka zustal zachovan a premena burger/krizek byla zpomalena o 50 procent na 375 ms.

---

# UKOL: Upravit homepage Cenik teaser

Datum: 2026-07-22
Zadani: Na homepage skryt ceny balicku, prejmenovat CTA na „Vice o cenach“ a doplnit tlumeny label „Nejoblibenejsi“ na prostredni kartu; samostatny `/cenik` nemenit.

## Kroky

- [x] Zachytit baseline a potvrdit theme-owned renderovaci cestu.
- [x] Pridat Premium Photo partialy pro cenikove karty a CTA a upravit jejich styling.
- [x] Pridat regresni kontrakt a overit desktop i mobil screenshoty a testy.
- [x] Provest wiki reflexi, commitnout a pushnout.

## Poznamky

- Rubrika 100 bodu: ceny na HP skryte 25, CTA „Vice o cenach“ 20, label 20, tlumena featured karta 20, mobil a runtime 15.
- Zmena bude izolovana do theme partialu; DB ceny a samostatna stranka `/cenik` zustanou beze zmeny.
- Uzivatelsky neversionovany screenshot zustava mimo scope.
- Baseline Chrome/CDP 1440x900: 35/100. Karty zobrazovaly ceny, CTA „Cenik“, featured badge chybel a prostredni karta mela tvrde cerne pozadi.
- Premium Photo nyni vlastni `home-pricing.phtml` a `home-section-cta.phtml`: karta ceny vubec nerenderuje, featured karta ma label a pouze CTA smerujici na `/cenik` dostane text „Vice o cenach“. Samostatny `/cenik` ani DB data se nemeni.
- Zaverecny diff review odhalil nechtenou zmenu CTA z puvodniho pill tlacitka na hranatou variantu. Partial byl opraven tak, aby zachoval puvodni `btn btn-ghost` markup a menil vyhradne text.
- Finalni Chrome/CDP: desktop 1440x900 i mobil 390x844 bez horizontalniho overflow, skore 100/100. Screenshoty `storage/qa/premium-photo-home-pricing-final-desktop-section.png` a `storage/qa/premium-photo-home-pricing-final-mobile-section.png`.
- Regrese: 60/60 PHP testu a 27/27 golden kontrol; `/cenik` golden zustal bez vizualni zmeny. README, ARCHITECTURE a INSTRUCTIONS byly posouzeny bez potreby zmeny. Wiki byla aktualizovana a commitnuta jako `d749ecd`.
- Implementace byla commitnuta jako `016e6fb` a uspesne pushnuta na `origin/master`.

## Vysledek

Hotovo. Homepage cenik nezobrazuje ceny, CTA zni „Vice o cenach“ a prostredni karta ma tlumeny teply featured vzhled se stitkem „Nejoblibenejsi“; detail ceniku zustal nedotceny.

---

# UKOL: Zvetsit vnitrni prostor tlacitka Kontakt

Datum: 2026-07-22
Zadani: Na desktopu pridat tlacitku Kontakt vice prostoru vlevo i vpravo, aby text nesplyval s borderem.

## Kroky

- [x] Potvrdit CSS kolizi a zachytit baseline.
- [x] Opravit specifitu desktopoveho paddingu a pridat regresni kontrolu.
- [x] Overit screenshotem a testy.
- [x] Provest wiki reflexi a commitnout.

## Poznamky

- Root cause kandidat: pozdejsi a specifictejsi `.site-header > .wrap > .site-nav .nav-links a { padding:0; }` prebija deklaraci `.nav-cta`.
- Uzivatelsky screenshot zustava mimo Git.
- Root cause potvrzen: obecny desktopovy selector mel vyssi specifitu nez puvodni `.site-nav .nav-cta`. Novy cilovy selector nastavuje pouze Kontaktu `padding:9px 30px`.
- Finalni Chrome/CDP 1440x900 bez horizontalniho overflow; Kontakt ma citelny prostor na obou stranach. Screenshot `storage/qa/premium-photo-contact-padding-final.png`.
- Regrese: 60/60 PHP testu a 27/27 golden kontrol. README, ARCHITECTURE a INSTRUCTIONS nebylo treba menit; wiki pipeline byla aktualizovana a commitnuta jako `e892984`.

## Vysledek

Hotovo. Desktopove tlacitko Kontakt ma 30 px vodorovneho paddingu a jeho text uz nesplyva s borderem; mobilni navigace zustala beze zmeny.

---

# UKOL: Prioritni geometricke centrovani cenik-other

Datum: 2026-07-22
Zadani: Opravit textaci „Rodinne, portretni a firemni foceni“ tak, aby byla prokazatelne uprostred viewportu; nasledne commitnout a pushnout.

## Kroky

- [x] Zmerit bounding boxy viewportu, kontejneru, `cenik-other`, nadpisu a popisu.
- [x] Opravit skutecny rodicovsky layout a pridat ciselny regresni kontrakt.
- [x] Overit desktop a mobil novymi screenshoty a testy.
- [x] Provest wiki reflexi, commitnout a pushnout.

## Poznamky

- Povinna acceptance: rozdil stredu `cenik-other` a stredu viewportu maximalne 1 CSS px; shodne pro `h2` a `p`.
- Vestaveny browser nebyl v tomto behu dostupny (`No browser is available`); mereni proto provadi explicitne projektovy Chrome/CDP QA skript bez skryteho fallbacku.
- Uzivatelsky screenshot zustava mimo Git.
- Baseline pri 1600 px: kontejner center 800 px, ale `cenik-other`, `h2` i `p` center 694 px, delta -106 px.
- Root cause: `public/assets/frontend-runtime.css` vnucuje `.cenik-other { max-width:860px!important; margin:0!important }`. Theme musi proto pouzit `max-width:none !important` i `margin:64px auto 0 !important`; pouhe `width:100%` a `text-align:center` nestaci.
- Finalni desktop 1600x900: viewport, kontejner, `cenik-other`, `h2` a `p` maji center 800 px; delta vsech merenych textovych uzlu je 0 px. Mobil 390x844: center 195 px, delta 0 px. Oba bez horizontalniho overflow.
- Finalni screenshoty: `storage/qa/premium-photo-cenik-other-priority-final-desktop.png` a `storage/qa/premium-photo-cenik-other-priority-final-mobile.png`. Vizualni skore 100/100.
- Regrese: 60/60 PHP testu a 27/27 golden kontrol. QA skript nyni vraci `pricingOtherGeometry`, aby se centrovani overovalo ciselne. README, ARCHITECTURE a INSTRUCTIONS nebylo treba menit; wiki commit je `a1866e6`.

## Vysledek

Hotovo. Textace je geometricky presne uprostred na desktopu i mobilu. Implementace byla commitnuta jako `53b8bba` a uspesne pushnuta na `origin/master`.

---

# UKOL: Systemove zpevnit centrovani cenik-other

Datum: 2026-07-22
Zadani: S nejvyssi prioritou odstranit globalni runtime kolizi, aby Premium Photo centrovani nezaviselo na prebijeni `!important`; commitnout a pushnout.

## Kroky

- [x] Overit aktualni produkcni CSS a geometrii.
- [x] Vyloucit Premium Photo z globalniho 860px omezeni a pridat regresni kontrolu.
- [x] Overit lokalni geometrii, testy a publish asset.
- [x] Commitnout a pushnout.

## Poznamky

- Produkce pred touto zmenou jiz vraci novy CSS override a pri 1600 px meri center 800 px, delta 0 px; dodany screenshot odpovida starsimu assetu/stavu.
- Systemovy fix odstrani samotny zdroj kolize pro `body.theme-premium-photo`, misto aby theme dlouhodobe bojovalo s globalnim `!important`.
- `frontend-runtime.css` omezuje 860px sirku uz jen na `body:not(.theme-premium-photo)`; regresni test tento scope vyzaduje.
- Lokalni Chrome/CDP 1600x900 po systemove oprave: `cenik-other`, `h2` i `p` center 800 px, delta 0 px, bez horizontalniho overflow. Screenshot `storage/qa/premium-photo-cenik-other-systemic-final.png`.
- Regrese: 60/60 PHP testu a 27/27 golden kontrol. Produkce pred hardeningem jiz vracela delta 0 px a nacitala novy theme override; wiki commit `564c184` zachycuje rozdil mezi starsim screenshotem a aktualnim assetem.

## Vysledek

Hotovo. Systemova oprava byla commitnuta jako `f478766` a uspesne pushnuta na `origin/master`.

---

# UKOL: Sjednotit homepage kontaktni formular s /kontakt

Datum: 2026-07-22
Zadani: Upravit formular v homepage kontaktni sekci tak, aby pouzival stejny DOM, poradi poli a vizual jako formular na strance `/kontakt`; FAQ panel zachovat vedle nej.

## Kroky

- [x] Zachytit desktop/mobile baseline homepage a referenci `/kontakt`.
- [x] Vytvorit sdileny theme partial formulare a pouzit jej na obou mistech.
- [x] Sjednotit layout/styly a pridat regresni kontrakt.
- [x] Overit desktop/mobile screenshoty a testy.
- [x] Provest wiki reflexi a commitnout.

## Poznamky

- Acceptance: homepage a `/kontakt` renderuji stejny `.premium-contact-form` partial; poradi je jmeno, e-mail/telefon, misto, zprava, souhlas, tlacitko. Pole datum se nevraci.
- FAQ zustava na desktopu vedle formulare a na mobilu pod nim.
- Uzivatelsky screenshot zustava mimo Git.

## Vysledek

Hotovo. Homepage i `/kontakt` renderuji stejny theme partial formulare. Desktop homepage zachovava dva sloupce form/FAQ, mobil jeden sloupec; Chrome QA nema horizontalni overflow. Overeni: 60/60 PHP testu a 27/27 golden kontrol.

## Vysledek

Hotovo. Doplnkova cenikova textace i featured badge jsou centrovane; Premium ma sedm vyvazenych bodu se souhrnem Standardniho a bocni karty jsou vyskové srovnatelne v live, publish i seed/migracni ceste.
# UKOL: Zapojit oficialni demo loga

Datum: 2026-07-22
Zadani: Presunout PNG soubory z root slozky `Loga` do vhodneho demo/public umisteni a pouzit spravnou barevnou variantu loga v Premium Photo strance podle pozadi.

## Kroky

- [x] Zkontrolovat varianty PNG, soucasny logo renderer a demo asset pipeline.
- [x] Presunout a pojmenovat logo assety do stabilniho verejneho umisteni.
- [x] Zapojit svetlou/tmavou variantu do hlavicky a paticky bez hardcoded obchodnich dat.
- [x] Pridat regresni kontrakt a overit desktop/mobile live i publish vystup.
- [x] Provest wiki reflexi a commitnout zmenu.

## Poznamky

- Vstupni soubory jsou uzivatelske demo assety v `Loga/*.png`; nesmi zustat jako neorganizovana root slozka.
- Acceptance: svetla hlavicka pouzije tmave logo, tmava paticka svetle logo; obrazky maji smysluplny alt a nezpusobi overflow.
- Assety jsou presunute do `public/assets/demo/logos/` a prejmenovane na stabilni ASCII nazvy. Cerna varianta je v hlavicce, bezova v paticce; oranzova zustava jako nepouzity demo akcent.
- Regresni test kontroluje existenci vsech tri assetu, kontrastni varianty hlavicky/paticky, pristupny alt a theme-owned rozmery.
- Chrome QA: 1200x800 i 390x844 bez horizontalniho overflow; cerne logo je v hlavicce a bezove v desktop paticce. `php tests/run.php`: 61/61 testu a 27/27 golden kontrol.

## Vysledek

Hotovo. Tri dodana PNG jsou v `public/assets/demo/logos/`; Premium Photo pouziva cernou variantu na svetlem pozadi a bezovou na tmavem. Oranzova je zachovana jako demo akcent. Dokumentace projektu mimo `UKOL.md` nepotrebovala zmenu; trvaly asset kontrakt je zapsan do pracovni wiki.

---
# UKOL: Sjednotit vnitrni hero sekce a brand text

Datum: 2026-07-22
Zadani: Sjednotit vsechny non-homepage hero sekce podle geometrie stranky Sluzby, zkratit jejich vysku o tretinu a pridat text `RB Videos & Photos` vedle loga v hlavicce i desktop paticce.

## Kroky

- [x] Zmapovat hero DOM/CSS vsech pozadovanych listing/detail rout a zachytit baseline.
- [x] Zavest jeden sdileny non-homepage hero kontrakt s vyskou 2/3 puvodni Sluzby reference.
- [x] Doplnenit brand text vedle horniho a dolniho loga vcetne mobilniho chovani.
- [x] Pridat regresni kontrakt a aktualizovat live/publish goldeny.
- [x] Overit reprezentativni routy v Chrome na desktopu i mobilu a zapsat skore.
- [x] Provest wiki reflexi a commitnout zmenu.

## Poznamky

- Homepage hero je explicitne mimo scope.
- Scope rout: Sluzby, detail sluzby, Nase prace, detail Nase prace, Blog, blog detail, Cenik, FAQ, Kontakt a Podminky uziti.
- Reference: desktop Sluzby; zachovat leve zarovnani eyebrow/title/description a crop/overlay, cilova vyska je priblizne 67 % soucasneho hero.
- Vizuální rubrika 100 bodu: hero geometrie 35, typografie/pozice 25, media/overlay 15, brand loga 15, responsive/runtime 10.
- Baseline 1440x900: Sluzby hero cca 522 px, detail sluzby 585 px; Cenik/Podminky pouzivaly odlisny generic container. Baseline score 56/100.
- Systemovy kontrakt pridava `.premium-inner-hero`, desktop vysku `clamp(320px,39vh,440px)` (351 px pri 900px viewportu), jednotny 1120px levy container, typografii a popis. Generic page hero dostane eyebrow z viditelneho H1; homepage se vylucuje.
- Brand text je soucast stejneho odkazu jako logo, obrazek je dekorativni (`alt=""`) a pristupny nazev zustava v `aria-label`.
- Iterace 1: 94/100. Desktop Sluzby, detail sluzby, blog detail, Cenik a Podminky maji 351px hero a jednotnou levou osu; mobilni Blog ma 360px minimum a nulovy horizontalni overflow. Zbyvajici body jsou jen data-driven absence popisu u portfolio detailu, nikoli geometricka chyba.
- Finalni matice pridala Nase prace listing/detail, FAQ a Kontakt. Finalni score 98/100; 2 body jsou transparentne ponechane za chybejici data-driven popis portfolio detailu. Overeni: 62/62 PHP testu, 27/27 golden kontrol, desktop/mobile bez horizontalniho overflow.

## Vysledek

Hotovo. Vsechny pozadovane vnitrni routy sdileji jednu zkracenou hero geometrii a levou obsahovou osu; homepage zustala beze zmeny. Horni i desktop dolni logo ma viditelny text `RB Videos & Photos.`. Relevantni projektove Markdown soubory mimo `UKOL.md` nebylo nutne menit; trvaly kontrakt je zachycen v pracovni wiki.

---
# UKOL: Opravit mobilni toggle a hero meta texty

Datum: 2026-07-22
Zadani: Odebrat border otevreneho burger tlacitka, zpomalit morph burger/krizek a upravit meta texty hero na routach Nase prace a FAQ.

## Kroky

- [x] Opravit CSS otevreneho navigation toggle a rychlost morph animace.
- [x] Zmenit `databaze Nase prace` na `Nase prace` a `odpovedi ze systemu` na `caste dotazy`.
- [x] Aktualizovat regresni kontrakt a golden vystupy.
- [x] Overit otevrene mobilni menu a obe hero v Chrome.
- [x] Provest wiki reflexi a commitnout.

## Poznamky

- Acceptance: krizek nema ramecek v zadnem stavu; transformace tri car trva 0,65 s; ostatni border navigacnich odkazu zustava beze zmeny.
- Textova zmena se tyka jen meta radku uvnitr hero, ne H1 ani navigacnich labelu.
- Chrome 390x844 s otevrenym menu potvrdil borderless krizek a zachovane oramovani odkazu Kontakt; stranka nema horizontalni overflow.
- Chrome 1440x900 potvrdil `PORTFOLIO · NASE PRACE` a `FAQ · CASTE DOTAZY` primo v hero meta radku.
- Regrese: `php tests/run.php` 62/62, golden kontroly 27/27 a `git diff --check` bez chyb.

## Vysledek

Hotovo. Toggle je bez borderu v zavrenem i otevrenem stavu, morph trva 0,65 s a oba hero meta texty odpovidaji zadani.

---
# UKOL: Produkcni sekce O nas a oprava Demo seedu

Datum: 2026-07-22
Zadani: Zajistit, aby se DB-rizena homepage sekce O nas publikovala i na produkci, a opravit lokalni Demo seed, ktery konci na chybejicim thumbnail derivatu media #25.

## Kroky

- [x] Porovnat runtime/publish datovy tok sekce O nas a zjistit presnou pricinu rozdilu produkce.
- [x] Dohledat v logu a databazi puvod media #25 a pricinu chybejiciho thumbnailu.
- [x] Opravit publish/seed kontrakt bez skryteho fallbacku a doplnit regrese.
- [x] Provest Demo seed, publikaci a runtime overeni sekce O nas.
- [x] Aktualizovat dokumentaci a wiki reflexi, uzavrit ukol.

## Poznamky

Hotovo znamena: cerstve publikovany web obsahuje stejnou sekci O nas jako lokalni homepage a Demo seed dokonci bez chyby chybejiciho derivatu; chyba se neopravuje ignorovanim neplatneho media zaznamu.

Produkce vraci HTTP 200, obsahuje `jb-about-visual__source`, `#sluzby` i Premium Photo JS, ale publikovany DOM ma genericke karty `.hp-svc-card` a chybi mu wrapper `.premium-photo-home`. Puvodni JS vyzadoval vyhradne wrapper a `.premium-service-card`, proto sekci bez chyby preskocil. Kompozitor nyni na homepage prijme i `main` a kanonicke publikovane `.hp-svc-card` vcetne jejich num/title/desc trid.

Lokalni log z 15:36:47 potvrzuje varovani `GD extension not loaded - skipping variant generation` pro media 10-25. Server na portu 8000 bezel z PHP 8.4.19 spusteneho pred nactenim aktualniho php.ini; CLI uz ma `extension=gd` aktivni. Importer nyni kontroluje GD pred prvnim zapisem a existujicimu mediu bez thumbnailu zavola explicitni regeneraci variant namisto trvaleho selhani nebo skryteho ignorovani.

Server `localhost:8000` byl restartovan stejnym PHP 8.4.19 a aktualnim `php.ini`. Skutecny `php database/seeds/demo_content.php` dokoncil 17 clanku, 5 praci, 33 recenzi a publikaci bez chyby; puvodne problemove medium #25 ma po oprave varianty `webp`, `thumb`, `medium`, `og`. Premium Photo preview vraci wrapper, DB marker i genericke `.hp-svc-card` a hashovany JS obsahuje novy homepage fallback i generic-card selektor. In-app browser backend neni v tomto behu dostupny (seznam browseru je prazdny), proto nelze vydavat screenshot za provedeny.

## Vysledek

Dokonceno. Produkcni Premium Photo JS umi vytvorit O nas z generickeho publikovaneho homepage DOM i z theme PHTML DOM. Lokalni PHP server byl restartovan s GD a Demo seed uspesne dokoncil vcetne opravy chybejicich variant media #25 a nove publikace. Aktualizovany jsou ARCHITECTURE a projektova wiki; README ani INSTRUCTIONS se nemenily, protoze instalacni kroky a repozitarova pravidla zustavaji stejne. Zakladni regrese prosly 65/65. Golden snapshoty po uzivatelskem Armageddonu zustavaji odlisne kvuli novym nahodnym media cestam (25/27 kontrol); nebyly potichu prepsany ani vydavany za uspech. In-app browser nebyl dostupny, proto je vizualni screenshot explicitne neprovedeny.

---
# UKOL: Kvalitni nahledy a caption v rohu

Datum: 2026-07-22
Zadani: Pro roztazene blogove a portfolio nahledy pouzit kvalitnejsi media variantu a filmovy caption zarovnat do rohu s pruhlednejsim cernym pozadim.

## Kroky

- [x] Zmapovat datove zdroje roztazenych nahledu a CSS geometrii captionu.
- [x] Predat medium/original variantu misto maleho thumbnailu na homepage a listingy.
- [x] Zarovnat caption k pravemu dolnimu rohu obrazove plochy a zvysit pruhlednost cerne vrstvy.
- [x] Doplnit regresni kontrakty a overit vygenerovane assety, desktop a mobil.
- [x] Aktualizovat dokumentaci, wiki reflexi a uzavrit ukol.

## Poznamky

Vizualni rubrika 100 bodu: kvalita/ostrost medii 40, caption v rohu bez useknuti 30, pruhlednost a citelnost overlaye 15, konzistence velke/male karty 10, runtime bez overflow/chyb 5. Hotovo znamena, ze velke ani male karty nepouzivaji 240px `thumb` jako roztazeny zdroj a caption nema odsazeni od praveho/spodniho okraje filmoveho obrazu.

Datovy kontrakt byl zmenen v `BlogListingData`, `PortfolioListingData` a homepage builderu na poradi `storage_path -> medium_path -> thumb_path`; siroke cover nahledy proto dostanou original a 240px thumb je jen posledni nouzova varianta. Filmova fotografie konci 20 px nad spodkem kontejneru, proto caption pouziva `right:0; bottom:20px` a doseda presne do jejiho praveho dolniho rohu. Cerny gradient byl zesvetlen na alfa rozsah `.18 -> .42`.

## Vysledek

Dokonceno. Runtime `/blog?theme_preview=premium-photo` predava 18 originalnich media URL, 0 thumb a 0 medium derivatu. Fresh Chrome CDP capture v 1525 px i 390 px nema horizontalni overflow; caption doseda na pravy dolni roh obrazove plochy. Vizualni skore po iteraci: 98/100 (kvalita 40/40, roh 30/30, transparentni overlay 14/15, konzistence 9/10, runtime 5/5). Zakladni testy prosly 65/65; 25 golden snapshotu zustava viditelne odlisnych po drivejsim demo reseedu a nebyly potichu prepsany. Wiki reflexe: SKIP, protoze existujici SEO Editor dossier uz pokryva media varianty i Chrome vizualni overeni a tato drobna implementace nepridava novy rekonstrukcni postup.

---

---
# UKOL: Prvni push RB Videos Photos

Datum: 2026-10-16
Zadani: Nastavit novy GitHub remote a pushnout projekt s automatickym FTPS deploymentem.
Doporuceny model: Terra - Ohranicena uprava workflow a overeni nasazeni.

## Kroky

- [x] Overit prazdny cilovy repozitar a nastavit origin na sigurdcz/rbvideosphotos.
- [x] Ulozit predchozi hotovou zmenu hero do samostatneho commitu.
- [x] Priradit workflow k environment production a staging a aktualizovat dokumentaci.
- [x] Pushnout master a overit GitHub Actions deployment.

## Poznamky

65 testu proslo; golden kontroly nelze overit kvuli nedostupne databazi. FTP adresare zustavaji /web/ a /web/dev/. Databaze, media a .env nejsou soucasti deploymentu. Lokalni .agents neni soucasti push.

Prvni push f91d842 probehl. Run 38905677599 selhal na chybejicim server inputu: skutecne production Secrets jsou FTP_SERVER, FTP_USERNAME, FTP_PASSWORD; workflow a README upraveny podle overenych nazvu. Staging environment v cilovem repozitari neexistuje.

## Stav nasazeni 2026-10-16
Push master dokoncen (d320f31). Run 38906062291 sestavil artefakt a nacetl Secrets, ale FTPS skoncil ENOTFOUND pro FTP_SERVER. Nasazeni neni dokoncene; uzivatel musi opravit hostname v production Secret. Staging environment chybi. Wiki reflexe: pipelines coverage partial; aktualizace dlouhodobeho zaznamu odlozena do dokonceni deploymentu.

Uzivatel opravil FTP_SERVER odstranenenim portu. Produkcni cil opraven z /web/ na /rbvideosphotos.cz/web/ podle Total Commander pripojeni main uctu. Stary rerun zrusen; nasleduje novy push a overeni.

Upresneni uzivatele: produkcni FTP ucet uz je omezen na cilovou slozku hostingu. Workflow musi pouzit server-dir: /; absolutni hostingovou cestu nepridavat. Predchozi beh zrusen.

Nazvy obou workflow opraveny z puvodniho Webzdarma na Active24; jde pouze o zobrazovany nazev, cil urcuje FTP_SERVER.

---
# UKOL: Sjednotit webove a CLI migrace

Datum: 2026-10-16
Zadani: Opravit prvni webovou migraci koncici duplicitnim autofill_disabled bez prepisovani historickych SQL.
Doporuceny model: Terra - Ohranicena oprava rozdilneho chovani dvou migratoru.

## Kroky

- [x] Dohledat prekryv migraci 001/002 a rozdil mezi CLI a URL utilitou.
- [x] Sdilet kontrolu jiz splnenych historickych migraci a viditelne je oznacit.
- [x] Overit PHP syntax, regrese a nemennost historickych SQL.
- [ ] Commitnout, pushnout opravu a predat opakovani migrace.

## Poznamky

Pripojeni DB funguje. Web utilita nema kontrolu schema-state existujici v CLI migratoru. Zadna produkcni data se nemazou.

Kontrola schema-state vyclenena z CLI do database/migration-schema-state.php; oba migratory ji sdileji. Web vypisuje i loguje migrace splnene existujicim schematem. README dokumentuje tento kontrakt.

Overeni: PHP lint vsech tri PHP souboru uspesny; 65 testu proslo, golden kontroly neprovedeny kvuli nedostupne lokalni DB. Historicke SQL nezmeneny. Novy bootstrap proti MariaDB nebyl lokalne proveden; produkcni migraci opakuje uzivatel po nasazeni.

---
# UKOL: Prvni administrator po migraci
Datum: 2026-10-16
Zadani: Umoznit prihlaseni do prazdne instalace pro nasledne spusteni Armagedonu.
Doporuceny model: Sol - Bezpecnostni bootstrap administrace vyzaduje overeni autorizace a jednorazovosti.

## Kroky
- [x] Potvrdit, ze migrace netvori uzivatele a setup vyzaduje admina.
- [-] Pridat explicitni bootstrap prvniho admina chraneny MIGRATE_TOKEN, CSRF a prazdnou tabulkou users.
- [ ] Overit odmítnuti neplatneho tokenu, existujicich uzivatelu a soubeznych pozadavku.
- [ ] Dokumentovat, commitnout, pushnout a overit deployment.

## Poznamky
Predchozi oprava migraci d9a0aa6 je nasazena a uzivatel potvrdil uspech migraci. Bootstrap nebude menit existujici ucty ani seedovat obsah.

Pridany bootstrap-admin.php, InitialAdminService a sedm cilenych testu s PDO double. Overeno odmítnuti tokenu/kratkeho hesla, zamek, hash, admin role, jednorazovost a uvolneni zamku. README vysvetluje inicializaci i reset hesla zakladnim seedem po Armagedonu.

## Vysledek prvniho administratora
Nasazeno 33a8730, Actions 38914525591 success; bootstrap-admin.php HTTP 200 a formular pritomen. 7 cilenych testu + 65 regresi proslo; golden kontroly nedostupne bez lokalni DB. Uzivatel vytvori ucet vlastnim tokenem a heslem. Zadny ucet nebyl agentem vytvoren.

---
# UKOL: 404 po Armagedonu a demo seedu
Datum: 2026-10-16
Zadani: Zjistit a opravit 404 homepage po uzivatelem provedenem resetu a seedu.
Doporuceny model: Terra - Ohranicena diagnostika produkcniho renderovani.
## Kroky
- [x] Overit produkcni 404 a cestu vyberu DB homepage.
- [-] Odstranit maskovani render vyjimky jako 404 a vyzadat konkretni log.
- [ ] Podle produkcniho dukazu opravit pricinu a overit HTTP 200.
## Poznamky
tryServeDatabasePage zachyti libovolnou Throwable, zaloguje frontend-render a vrati false; caller pak vraci 404. Oprava zachova log a preda vyjimku existujicimu globalnimu handleru (HTTP 500, detail jen pri APP_DEBUG). Skutecna pricina zatim neni znama.

public/index.php po zalogovani render vyjimku znovu vyhodi; README popisuje rozdil 404/500. PHP lint a diff check prosly. Pricina produkcniho render selhani stale ceka na log.

---
# UKOL: Obnovit Golden Bash Diagonal
Datum: 2026-10-16
Zadani: Doplnit chybejici originalni motiv Golden Bash Diagonal do noveho projektu a nasadit jej.
Doporuceny model: Sol - Motiv pochazi z novejsiho zdrojoveho projektu a vyzaduje kontrolu kompatibility.
## Kroky
- [x] Potvrdit absenci motivu v checkoutu a historii.
- [-] Nacist original z puvodniho seo-cms a zkontrolovat zavislosti.
- [x] Doplnit motiv a potrebnou integraci bez nahrazovani vzhledu jinou sablonou.
- [ ] Overit registraci, testy a nasazeni.
## Poznamky
Verejne API vracelo 404; autentizovany dotaz originalni slozku nalezl. Nacita se zdrojova historie bez prepnuti aktualni vetve. Predchozi homepage 404 neni zatim vysvetlena: uzivatel predtim spoustel seed na starem hostingu, nikoli na nove produkci.

---
# UKOL: Aktualni CMS ze seo-cms do rbvideosphotos
Datum: 2026-10-16
Zadani: Prenest aktualni CMS b8da8da z puvodniho seo-cms vcetne Golden Bash Diagonal; zachovat Active24 deployment a bezpecny bootstrap admina.
Doporuceny model: Sol - Prenos novejsiho CMS a migraci s kontrolou kompatibility a zachovanim produkcni konfigurace.
## Kroky
- [x] Potvrdit zdroj b8da8da02878735ef10c506fb75c43cf41984542 a shodu historickych migraci 001-023.
- [x] Prenest sledovane aplikacni zdroje, motivy, assety a append-only migrace 024-102.
- [x] Zachovat Active24 workflow, bootstrap prvniho admina a explicitni chyby renderovani; sloucit migrator.
- [x] Overit PHP/JS syntax, regresni testy, registraci motivu a potrebne schema kroky.
- [x] Dokumentace, wiki reflexe, commit a nasazeni aktualizace.
## Poznamky
Uzivatel vyslovne zvolil prenos celeho aktualniho CMS. Produkcni .env, databaze a runtime media se neprepisuji. Puvodni SQL migrace jsou beze zmen, pouze pribyvaji nove. Golden Bash Diagonal je v novejsim CMS kanonicky default. Testovani musi odlisit funkcni testy od golden kontrol a DB integrace.

Preneseny aktualni sledovane zdroje vcetne vsech motivu, render pipeline a novych migraci. Active24 workflow, bootstrap admina a lokalni konfigurace zachovany. CLI a web sdileji rozsirene schema-state kontroly; web viditelne odlozi obsahove migrace bez webu a neoznaci je za hotove. Zachovano explicitni HTTP 500 pri render vyjimce.

Overeni odhalilo Windows CRLF nesoulad textovych kontraktu testu; pridano .gitattributes pro stabilni LF zdroje. Testy migraci zohlednuji sdileny helper a deploy test kontroluje zamerne zachovany Active24 workflow namisto puvodniho Webzdarma. Staging pouziva take koren uctu /. Root .htaccess blokuje prime cteni .env.

PHP regrese 216/216 + bootstrap 7/7 prosly. Cista oddelena DB rbvp_import_validation_20261016: 89 migraci aplikovano, 15 obsahovych viditelne odlozeno do existence webu, bez SQL chyby. Lokalni existujici DB je napred (103), zdrojovy repozitar konci 102; nebyla zpetne menena. Golden normalizer nove ignoruje pouze diagnosticky build/migration komentar, nikoli obsah. README a .env.example popisuji Active24 a novy instalacni postup.

DEPLOYMENT.md prepsan pro skutecny Active24 provoz; stare Webzdarma automaticke maintenance kroky nejsou v tomto repozitari zapnute. Zdroj obsahoval dva docasne PHP helpery bez HTTP auth; pridana CLI-only kontrola a vylouceni z artefaktu vcetne tests a lokalniho QA. Finalni overeni: 325 PHP/PHTML syntax OK, 19 JS syntax OK, 216 testu OK, 27/28 golden kontrol OK. Jedina zmena O nas je konkretni jiny poster a rozmery z lokalni DB migrace 103; golden nebyl prepsan.

Do commitu pripravena cela aktualizace (601 souboru pred odstranenim dvou zastaralych planu). Runtime storage/published a public/assets/generated odstraneny pouze z indexu, lokalni obsah zustal zachovan; deployment je stejne vylucuje. Puvodni 001-023 SQL beze zmen, 79 novych SQL souboru. .env a .agents nejsou ve stagingu.

## Vysledek prenosu aktualniho CMS
Preneseno a nasazeno d7edef4; Actions 38919482148 success. Produkcni manifest golden-bash-diagonal 1.0.0, font CSS, admin login i bootstrap HTTP 200; DNS 37.9.175.211. Homepage HTTP 500 ma konkretni chybu chybejiciho blog_posts.sort_order, ktery pridava dosud nespustena migrace 072. Operator musi provest nove migrace serverovym tokenem, pak publish a zvolit motiv; zadny automaticky Armagedon nebyl proveden. 216+7 testu proslo, 27/28 goldenu; jeden dolozeny lokalni datovy rozdil posteru O nas zustal neprepsan. Testovaci DB po overeni bezpecne odstranena. Aktualizovany README, DEPLOYMENT, .env.example a wiki pipelines/data-model (wiki commit 27a7c10). .agents zustava nesledovana.

---
# UKOL: Schema pred obsahovymi migracemi
Datum: 2026-10-16
Zadani: Opravit upgrade existujici DB selhavajici na schema_name bez resetu dat.
Doporuceny model: Sol - Poradi DB upgradu a aktualnich PHP handleru musi zachovat opakovatelnost a data.
## Kroky
- [x] Dohledat schema_name v migraci 079 a drivejsich aktualnich PHP handlerech.
- [-] Odlozit vsechny PHP obsahove handlery az za SQL fazi.
- [ ] Overit poradi, syntax, regrese a nasazeni.
## Poznamky
Prvni implementace odkladala obsah jen pri absenci webu. Existujici web spoustel handler 050 pred pridanim schema_name v 079. Novy beh nejdriv SQL, potom obsah; evidence obsahu az po uspechu. Zadna stara SQL migrace se nemeni.

Dvoufazovy web migrator implementovan. Integracni test nad realnou oddelenou MariaDB overil 104/104 zaznamu a 15 handleru az po schema_name, sort_order a shared_sections. Prvni beh testu odhalil spatnou cestu fixture a ukonceni pred cleanupem; test opraven, jeden prvni testovaci DB zustal k explicitnimu cleanupu.

---
# UKOL: PHP 8.5 GD a JSON demo seedu
Datum: 2026-10-16
Zadani: Opravit demo davku zastavujici s HTTP 200 kvuli varovani imagedestroy v JSON odpovedi.
Doporuceny model: Terra - Konkretni PHP 8.5 kompatibilita a diagnostika JSON odpovedi.
## Kroky
- [x] Potvrdit pricinu v aktualnim php_error logu a oficialnim PHP manualu.
- [-] Nahradit no-op imagedestroy uvolnenim referenci a opravit klientskou diagnostiku.
- [ ] Overit obrazkove testy, JSON response testy, regrese a deployment.
## Poznamky
Hosting PHP 8.5 vypisuje E_DEPRECATED do HTTP 200 JSON odpovedi pri APP_DEBUG=true. PHP manual: imagedestroy nema ucinek od 8.0, deprecated od 8.5. Varovani se nebudou potichu potlacovat; zastarala volani se odstrani.

ImageVariantService a obrazkove testy nahradily vsechna imagedestroy za unset referenci. Setup UI pouziva explicitni JSON parser bez ticheho null fallbacku; rozlisuje HTML login, znecistenou HTTP 200 odpoved a aplikacni chybu. Node test 4 scenaru a PHP lint prosly; DEPLOYMENT popisuje PHP 8.5 kompatibilitu.


---
# UKOL: Obnova hesla e-mailem
Datum: 2026-10-16
Zadani: Pridat odkaz zapomenute heslo, e-mail s jednorazovym odkazem a formular noveho hesla.
Doporuceny model: Sol - Bezpecnost prihlaseni, tokeny, databaze a dorucovani e-mailu.
## Kroky
- [x] Overit mail transport a implementovat obnovu s novou migraci.
- [x] Overit expiraci, jednorazovost, chyby doruceni a formulare.
- [x] Aktualizovat dokumentaci a wiki, ulozit commit.
## Poznamky
Predchozi PHP 8.5 oprava dokoncena; uzivatel potvrdil funkcni instalaci. Nezasahovat do uzivatelske .agents/.

Implementovany formular, PHP mail, atomicky jednorazovy token SHA-256 a session fingerprint. Novy SQL 103, historicke migrace beze zmen. DEPLOYMENT a .env.example popisuji konfiguraci a nutnost skutecne adresy uctu.

Overeni: 10 assertions plus rejection cases na oddelene MariaDB; HTTP smoke formulare/CSRF/redirect/headers; 105 migraci a 15 handleru; 216 regresi bez chyby, goldens 27/28 (znamy About poster). Produkcni migrace a doruceni neprovedeny. Wiki password-recovery.html obsahuje kod a konfiguraci, hub/data-model/pipelines aktualizovany. Zmena odhlasi stare relace bez fingerprintu.

## Vysledek
Obnova hesla e-mailem hotova a lokalne overena. Dokumentace: DEPLOYMENT.md, .env.example, globalni wiki. Pripraveno k nasazeni; vyzaduje migraci 103 a skutecny e-mail uctu. Push pro tento novy ukol nebyl vyzadan.

---
# UKOL: Trvala zaloha kontaktnich zprav
Datum: 2026-10-16
Zadani: Ulozit zpravy pred odeslanim do databaze a pravdive evidovat vysledek mail transportu, aby se obsah pri selhani neztratil.
Doporuceny model: Sol - Konzistence ukladani, selhani posty a citlivych reset tokenu.
## Kroky
- [x] Overit kontaktni tok a pridat append-only migraci a sluzbu pro DB zalohu.
- [x] Zapojit ulozeni, stavy a admin prehled; opravit potvrzeni a evidenci obnovy hesla bez tokenu.
- [x] Otestovat selhani DB i mailu, aktualizovat dokumentaci/wiki a commitnout.
## Poznamky
Uzivatel zvolil zachovat prijemce z administrace. Pouziva se puvodni PHP mail, nova schranka sama nepotvrzuje SMTP konfiguraci. Produkcni pricina nedorucovani nezjistena.
Migrace 104 je nova, stare SQL beze zmen. Kontaktni obsah se uklada pred mail; reset jen metadata bez tela/tokenu. Admin Zpravy vyzaduje roli admin, escapuje obsah a strankuje po 50 zaznamech. Stav accepted neni doruceni; pri selhani DB neni odeslani ani potvrzeni uspechu. Osobni udaje zustavaji pouze v chranene DB, log nese ID/kod. Pole datum se dle existujiciho kontraktu nezpracovava.
Overeno: MailPersistenceIntegrationTest 16 assertions, PasswordResetIntegrationTest 10 assertions plus rejection cases, migrace 106/106 a 15 handleru. HTTP POST s vypnutou postou zachoval obsah i prijemce, vratil sent=saved a anonym nema pristup k archivu. Regrese 216/216; kontakt golden po zachovani id/for beze zmen. Znamy About poster z lokalniho obsahu zustava odlisny (27/28 goldens).
Pri testovani byla prvni cesta noveho testu nedostupna i mimo sandbox; pricina neoverena. Finalni MailPersistenceIntegrationTest existuje a prosel, zadna ochrana OS se nemenila.
Dokumentace: DEPLOYMENT.md, ARCHITECTURE.md, .env.example a globalni wiki mail-archive.html + obnova hesla/data-model/pipelines/hub/katalog. README onboarding a INSTRUCTIONS nevyzadovaly zmenu.
## Vysledek
Archiv zprav hotov a lokalne overen. Produkcni aktivace vyzaduje nasazeni a migraci 104. Dorucovani do produkcni schranky neovereno, automaticke opakovani se neprovadi. Push nebyl pro tento ukol vyzadan.

---
# UKOL: SMTP odesilani pres team schranku
Datum: 2026-10-16
Zadani: Prepnout kontakt a obnovu hesla na autentizovane SMTP team@rbvideosphotos.cz, zachovat prijemce z administrace a DB archiv a dodat .env konfiguraci.
Doporuceny model: Sol - SMTP autentizace, TLS, citlive udaje a konzistence archivu.
## Kroky
- [x] Overit dostupnou SMTP knihovnu a dokumentaci, implementovat transport bez mail fallbacku.
- [x] Otestovat SMTP konfiguraci, protokol a chyby s archivem bez realneho odesilani.
- [x] Aktualizovat .env.example, dokumentaci a wiki; commitnout.
## Poznamky
PHPMailer 7.1.1 pres Composer ^7.0, novy composer.lock. CI uz provadi composer install; workflow ani serverovy .env se nemeni. Host potvrzuje oficialni Websupport dokumentace smtp.websupport.cz:465 SSL/TLS nebo 587 STARTTLS; konkretni schranku overit v administraci. Heslo ke schrance neni k dispozici a nezapisuje se do kodu/logu/wiki.
SmtpMailer vyzaduje autentizaci a ssl/tls, overuje certifikat a hostname, From musi odpovidat uctu. Prijemce kontaktu stale pochazi z administrace, reset z users.email. Archiv INSERT zustava pred transportem, chyby maji bezpecne kody bez raw SMTP odpovedi, zadny PHP mail fallback ani retry. Chybne MAIL_FROM je rovnez smtp_configuration.
Testy: 3 SMTP scenare pres fake kanal skutecne knihovny (SMTPS/STARTTLS, AUTH, MIME, prijemce, chyby config/auth/DATA); 17 archivnich assertions, 10 reset assertions plus rejection cases. Hlavni sada 219/219, goldens 27/28 (drive znamy About poster). Skutecna posta se neposilala. Composer validate/platform checks prosly; validate hlasi puvodni chybejici license metadata.
Lokalni instalace Composer potrebovala CA z Windows root store, TLS kontrola nebyla vypnuta. Prvni test odhalil Timelimit na spatnem objektu (opraveno na SMTP instanci), registrace testu presunuta pred vykonavaci smycku. Dokumentace: .env.example, DEPLOYMENT.md, ARCHITECTURE.md; wiki smtp-mail.html a souvisejici dossier aktualizovany, odkazy a limity overeny. README onboarding/INSTRUCTIONS zmenu nevyzadovaly.
## Vysledek
SMTP implementovano a lokalne overeno, bez nove SQL migrace. Vyuziti archivu stale vyzaduje migraci 104 z minuleho ukolu. Aktivace vyzaduje nasazeni a doplneni SMTP_PASSWORD do serveroveho .env. Produkcni doruceni neovereno. Push nebyl vyzadan.

---
# UKOL: Admin email overview and password change
Datum: 2026-10-16
Zadani: Zpristupnit prehled odesilanych emailu se stavy a pridat zmenu vlastniho hesla v administraci.
Doporuceny model: Sol - Bezpecne overeni aktualniho hesla, transakce a revokace pristupu.
## Kroky
- [x] Doplnit existujici archiv emailu o filtry/stavy a implementovat vlastni zmenu hesla.
- [x] Overit autorizaci, CSRF, chyby hesla a zneplatneni reset tokenu i relaci.
- [x] Aktualizovat dokumentaci/wiki a commitnout.
## Poznamky
Prehled uz existuje /admin/messages, pouze admin. Nove vlastni heslo pro prihlaseneho uzivatele, bez volby ciziho user_id. Odeslani neni potvrzeni doruceni. Bez nove SQL migrace; archiv/reset tabulky vyzaduji stavajici migrace 103/104.

AccountPasswordService transakcne overuje aktualni hash, meni pouze session user_id a maze jeho reset tokeny. Controller ma auth, CSRF, limit 5/15 minut a po uspechu odhlaseni. Formular nema pole pro ciziho uzivatele. Archiv ma repository s allowlist filtry, pocty, posledni pokus a strankovani se zachovanim filtru; menu E-maily a zpravy + Zmena hesla.

Pridan AccountAdminIntegrationTest pro chybne/stare/nove heslo, cizi ucet, reset tokeny, rollback a kombinovane filtry/pocty/strankovani mail archivu. Zmena hesla loguje pouze user_id. View prehledu vyzaduje skutecna data repository, nevyrabi nahradni nuly pri chybejicich datech.

12 DB assertions plus invalid cases, 17 archivnich a 10 reset assertions proslo. HTTP test overil login, editor 403 pro maily, filtry a chyby400, CSRF403, spatne/s rozdilem heslo, ignorovani POST user_id=2, uspech a zneplatneni druhe admin relace, editor vlastni heslo zustalo pristupne. DEPLOYMENT a ARCHITECTURE aktualizovany.

219/219 regresi proslo, goldens 27/28 jen znamy About poster. Wiki account-password.html a mail-overview.html obsahuji sluzby/controller/repository, wiring a dukazy. Prehled hlasi prazdny filtrovaný vysledek, nikoli chybne prazdny archiv.

## Vysledek
Hotovy filtrovatelny prehled emailovych pokusu /admin/messages a vlastni zmena hesla /admin/account/password. Auth, CSRF, tokeny, rollback a stare relace overeny. DEPLOYMENT/ARCHITECTURE a globalni wiki aktualizovany; README/INSTRUCTIONS bez nutne zmeny. Bez nove SQL migrace, vyzaduje dosavadni 103/104. Push ani produkcni nasazeni nebyly pro tento ukol vyzadany.

---
# UKOL: Recovery recipient from admin settings
Datum: 2026-10-16
Zadani: Login jen identifikuje ucet; reset hesla posilat na email pro prijem formularu v nastaveni administrace, nikoli users.email.
Doporuceny model: Sol - Zmena obnovy pristupu a ochrana centralni cilove adresy.
## Kroky
- [x] Implementovat centralni prijemce a overit ochranu jeho nastaveni.
- [x] Otestovat login, prijemce, vazbu tokenu a chybejici nastaveni bez skryteho fallbacku.
- [x] Aktualizovat dokumentaci/wiki a commitnout.
## Poznamky
Cil je Nastaveni > email pro prijem formularu (contact_info.form_email). Zadna nahrada users.email ani hardcoded gmail pri chybejicim nastaveni. Identifikator z formulare neovlivnuje prijemce; token stale resetuje konkretni ucet.

Reset pouziva striktne ulozene form_email pro prvni web, bez fallbacku na users.email/kontaktni verejny email/default. Zmena chraneneho pole povolena jen adminovi v settings/contact/page, repository defaultne zapis form_email zakazuje a pri bezne editaci ho nemeni. Demosystem explicitne povoluje inicializaci. Inline editor nema pole povolene a repository ho neprepise. Formular obnovy oznacuje identifikator jako login.

Test obnovy rozsiren o centralni recipient, chybejici/neplatne nastaveni bez fallbacku a login bez emailove syntaxe s vazbou tokenu na spravny ucet. Login/recovery formular nyni oznacuji identifikator jako prihlasovaci jmeno. Settings UI uz nepredstira hardcoded prijemce, pokud pole nebylo ulozeno.

HTTP overil editor403 pri pokusu menit form_email z contact page pred zapisy. Test repository potrebuje i historickou migraci 028 pro reviews_url, fixture doplnena (zadna zmena stare SQL). Hlavni regrese 219/219, goldens 27/28 znamy About poster. DEPLOYMENT a ARCHITECTURE vysvetluji novou centralni obnovu a admin-only pole.

## Vysledek
Reset odesila na ulozene form_email z Nastaveni prvniho webu, login zustava identifikatorem. 17 recovery assertions + rejection cases, 7 permission assertions, HTTP plain-login/central recipient a editor403, 219 regresi proslo; goldens27/28 znamy About poster. DEPLOYMENT/ARCHITECTURE a wiki aktualizovany, README/INSTRUCTIONS bez nutne zmeny. Bez nove migrace. Push ani produkcni nasazeni nebyly vyzadany.

---
# UKOL: Clear page social image overrides
Datum: 2026-10-16
Zadani: Novou migraci smazat vlastni socialni obrazky vsech stranek, aby pouzivaly globalni Nastaveni.
Doporuceny model: Terra - Cilena datova migrace a overeni dedeni globalnich obrazku.
## Kroky
- [x] Overit vsechna socialni image override pole a live/publish dedeni.
- [x] Pridat dalsi append-only migraci a overit izolovany beh/opakovani.
- [x] Aktualizovat dokumentaci/wiki a commitnout.
## Poznamky
Strom cisty. Posledni migrace 104; nova bude 105. Uzivatel vyzaduje smazani vlastnich obrazku, nikoli globalnich nastaveni/media souboru/ostatnich SEO poli.

Nova105 nuluje jen seo_settings og_image_id/twitter_image_id vsech stranek vcetne draftu. Specialni live routy a staticky portfolio export uprednostnuji globalni obrazek pred hero. Aktualni PHP base/demo seedy nevraceji nahodne page overrides; historicke SQL beze zmen. Schema obrazky a globalni platform IDs zachovany.

Pridan SocialImageResetIntegrationTest: puvodni override, vsechny weby/drafty, zachovani schema/SEO textu/globalu/media, idempotence, realny live head s hero fallbackem a staticky export portfolia do vlastniho nahodneho souboru. Cleanup jen vlastni DB a jediny vlastni soubor/jeho prazdny adresar.

Izolovany test odhalil stary exportni resolver s /storage/media URL misto verejneho /media. Globalni exportni social obrazky nyni pouzivaji sdileny MediaUrlResolver. Test po bootstrapu obnovuje PHP handlery, aby Runtime nezakryl testovou vyjimku jako HTML s exit0. Fixture doplnena o 007 zavislost pred024.

Odstranena konkretni demo mapa seoImageOverrides (vcetne kontakt fotky) a nepouzivane image hinty v SEO seedu. Test About misto stareho social ID kontroluje zachovanou obsahovou URL. Integrace navic skutecne vola seedPageSeo na fixture kontaktu a overuje NULL obrazky.

Dva social goldens po kontrole upraveny pouze v og:image a navazane Organization image URL; hero/obsah beze zmen, znamy About golden neaktualizovan. DEPLOYMENT/ARCHITECTURE dokumentuji beh105, zachovana data a republish statickeho exportu.

## Vysledek
Nova105 odstrani OG/Twitter overrides vsech stranek, ostatni SEO/global/media zustanou. Live specialni routy a portfolio export preferuji globaly, seedy nevraceji testovaci fotografie. Overeno16 assertions +107 migraci/15 handleru +219 regresi. Dva ocekavane goldens aktualizovany jen social URL;27/28 zbyva znamy About poster. DEPLOYMENT/ARCHITECTURE a wiki aktualizovany, README/INSTRUCTIONS nevyzadovaly zmenu. Produkcni migrace ani push neprovedeny.

---
# UKOL: Activate staging and document deployment
Datum: 2026-10-16
Zadani: Push OG opravy na produkci, aktivovat dev/staging, pripravit uplny instalacni navod a backlog.
Doporuceny model: Sol - Konfigurace dvou prostredi, GitHub Actions a provozni dokumentace.
## Kroky
- [x] Push master fe561ed a overit production Actions (38959519402 success).
- [x] Vytvorit environment staging omezeny na dev; doplnit kontrolu secrets a branch guard workflow.
- [x] Pripravit STAGING.md, BACKLOG.md a pravidla souhlasu pred master v INSTRUCTIONS.md.
- [x] Overit YAML konfiguraci a push dev bd097d3; staging Actions38959853911 ukonceny kontrolou chybejicich secrets.
- [x] Aktualizovat wiki a zaznamenat skutecny stav prvniho staging nasazeni (wiki2e86b39).
- [x] Po doplneni/opravach tri FTP secrets zopakovat Actions:38960080119/attempt5 success, prvni uplny prenos dokoncen.
## Poznamky
Pri kontrole existoval jen environment production. Staging vytvoren pres GitHub API; FTP secrets musi uzivatel vlozit do GitHubu (nikoli do chatu). Zadost o doplneni odeslana. Serverovy .env/DB/SMTP zustavaji oddelene a nejsou generovany z GitHub secrets. Migrace105 na produkci nespustena.

## Vysledek
OG zdroje nasazeny na produkci uspesne. Dev push a staging environment aktivni, ale zadny staging soubor zatim neprenesen: vsechny FTP secrets chybi a preflight zastavil workflow pred checkoutem. Staging URL vraci200 s titulem Brzy jsme zpet, nikoli overeny novy CMS. STAGING.md obsahuje povinne nastaveni hostingu, secrets, DB, .env, migraci, admina, obsahu a SMTP; BACKLOG.md zachycuje tri pozadavky; INSTRUCTIONS.md zakazuje dalsi master merge/push bez konkretniho souhlasu. YAML/FTPS/branch guard kontroly a diff check prosly. Wiki524 stranek, nova stranka staging-deployment608 slov; pipeline coverage zustava partial. Prvni prenos a funkcni instalace zustavaji otevrene.

Aktualizace po doplneni secrets: pokusy2-4 vracely530 Login incorrect, pokus5 uspesne dokoncil FTPS nasazeni. Predchozi blokace secrets je vyresena; funkcni DB/.env a obsah je nutne overit samostatne. BACKLOG bod4 doplnen na zadost uzivatele: burger animace do krizku a rusive tmave hover pozadi (commit67f2311), oprava odlozena.

---
# UKOL: Backlog — fonts, hero video and burger
Datum: 2026-10-16
Zadani: Zacit resit backlog: overit staging workflow, opravit ceske fonty, plne pokryti hero videem a burger animaci/hover.
Doporuceny model: Sol - Interakce CSS, fontu, media a navigace vyzaduje skutecne renderovaci QA.
## Kroky
- [x] Overit runtime, motiv, lokalni spousteni a staging; najit skutecne priciny tri UI vad.
- [x] Zachytit diagnostiku Chrome CDP a zapsat rubriku; celkove skore nepridelovat kvuli chybejicim lokalnim mediim.
- [x] Implementovat spravny font, metadata-driven hero cover a burger animaci/hover pri zachovani focusu.
- [x] Overit skutecne generovane assety, font a nav interakce;219 regresi +27/28 goldens, znamy About poster neprepsan.
- [x] Aktualizovat backlog, technickou dokumentaci/wiki a pripravit oddelene commity zdroju/snapshotu na dev. Bez noveho push.
- [ ] Na inicializovanem stagingu overit skutecne prehravani, poster pri selhani, dotyk a pomalou sit. Bez teto brany video neoznacovat kompletne overene.
## Poznamky
Pozadavek screenshotu: fallback obrazek nesmi prosvitat nad ani pod prehravanym videem; ma zustat funkcni pred nactenim nebo pri chybe videa. Fonty musi vykreslit ceske znaky. Burger se otevrenim meni na krizek a hover nema tmavou plochu. Bod1 potrebuje vlastni DB/.env a opravneni schvalovatele; nepredstirat dokonceny staging jen podle FTP success.

## Vysledek
Regular italic4.001 s kompletnimi ceskymi glyfy a font-display:swap; nova verze souboru zachova immutable cache. Burger opraven i do1050px, transparentni hover a keyboard focus/reduced-motion zachovany v obou Golden Bash motivech. Hero cte rozmery426x206/240x240 z konkretniho Vimeo iframe, resizeprepocita cover; metadata timeout ma explicitni diagnostiku. Test odhalil regex rozbity minifikatorem, nahrazen prefixem a overen skutecny generated JS. Metadata fixture v Chrome prokazuje pouze geometrii, nikoli zive prehravani. QA-BACKLOG-2026-10-16.md zachycuje dukazy a omezeni; wiki470cb86. Pozdejsi 700px capture bez dokonceneho CSS odmitnut, samostatny recapture nav/font prosel; neplatne dukazy se nepovazuji za PASS.

### Priorita 17. 10. 2026: odblokovani prvniho admina stagingu

- [x] Overit kod PasswordResetService/InitialAdminService a dostupnost bootstrapu. Bootstrap/login200, homepage404; GET neprokazuje existenci/neexistenci uctu.
- [x] Doplnit STAGING.md o bootstrap pred seedem, obecne potvrzeni pro neexistujici login a samostatne APP_URL/tokeny.
- [x] Uzivatel vytvoril prvniho admina pres bootstrap a potvrdil funkcni pristup odpovedi "uz to jede" dne17.10.2026. Potvrzeni se vztahuje k dotazu na vytvoreni/prihlaseni, nikoli automaticky ke seedu, SMTP nebo frontend QA.
- [ ] Po prihlaseni dokonci seed/demo, ulozeni form_email, opakovani migraci a publish; pak overit skutecne SMTP i obsah.

Pri nove DB migrace nevytvareji kopii produkcnich users ani contact_info. Obnova neni nastroj vytvoreni prvniho admina; pro neznamy login vraci obecne potvrzeni bez odeslani. Existujici ucet bez form_email dostane explicitni chybu. Kopie produkcniho .env s pouhou zmenou DB navic ponechava reset APP_URL na produkci. Zadny reset, SQL zapis, odeslani emailu ani bootstrap na serveru agent nespustil; vyzaduje uzivatelovo soukrome zadani prvnich credentials.

---
# UKOL: Strict staging-first release policy
Datum: 2026-10-17
Zadani: Vsechny zmeny nejprve staging, zadny primy vyvoj/push do master; merge jen po konkretnim vyslovnem souhlasu uzivatele.
Doporuceny model: Terra - Omezena dokumentacni zmena pravidel repozitare.
## Kroky
- [x] Pridat korenovy AGENTS.md, zprisnit INSTRUCTIONS.md a sjednotit STAGING.md.
- [x] Vymezit konkretni schvaleny obsah, zakaz auto-merge/obchazeni a odlisit obecne OK/push od souhlasu s master merge.
- [x] Overit diff a ulozit na dev bez push/merge; GitHub branch protection zustava samostatna otevrena cast backlogu.
## Vysledek
Zavazna politika pro vsechny typy zmen. Jedina release cesta je overene dev/staging -> vyslovny souhlas uzivatele s konkretnim merge -> master. Korenovy AGENTS.md zajisti viditelnost pro dalsi agenty. Zadny deployment ani GitHub branch protection nemenena.

---
# UKOL: Replace video portfolio with ordered YouTube selection
Datum: 2026-10-17
Zadani: Nahradit dosavadni video galerii presne12 zadanymi videi v poradi1-12, nejprve staging; fotografie/hero zachovat, master bez souhlasu nemenit.
Doporuceny model: Sol - Datova nahrada, migrace a overeni poradi napric DB, listingem a homepage.
## Kroky
- [x] Overit video model a aktualni stagingAPI/listing; oficialni oEmbed metadata vsech12URL dostupna.
- [x] Pripravit append-only106 a sdileny manifest demo importeru; fotografie/hero/media soubory zachovany.
- [x] Izolovane overit48 assertions vcetne starych exportu, presnychURL/poradi a idempotence;108 migraci/16handleru;219regresi a27/28goldens (znamy About poster).
- [x] Commit4ab1ff1/push dev, Actions39061862591 success; staging106 a publish uspesne.
- [x] API/HTML/Chrome overily presne12 videi v zadane posloupnosti,24fotografii nezmenenych,4vyrazenaID mimo listing/homepage a stareadresy404. Vsechny12nahledy200, vsechny12karty oteviraji odpovidajici iframe; desktop/mobil bez horizontalniho overflow/JSvyjimek.
- [x] Aktualizovat VIDEO-GALLERY/DEPLOYMENT a wiki5eb8df9; read-only wiki lint po prekroceni kadence de8da68, bez hromadnych oprav znalosti.
## Poznamky
Zadana ID v poradi: L-zaa5hj-aU,cXU7Ty-kjyA,hYj2w3P5rK8,5ZlVVcIu10U,O5i9TzCms5c,b9WhNN8VYhc,C1wL3QFZhn0,DBWKJ46eM4c,su0hKnBnCKw,1z-qckQn5UU,o-1xGllIShY,GBKJ_VB_Nmw. Predchozi UI opravy jsou commitnute na dev, dosud nepushnute; staging push bude obsahovat i tyto revize, transparentne uvest.

## Vysledek
Na stagingu je schvalena dvanactka ve spravnem poradi.8puvodnichURL se prekryva se zadanim a zustalo,4vyrazeneodstraneny a4novepridany. Fotografie/hero a fyzickamedia zachovany.48izolovanych assertions,108migraci/16handleru,219regresi a27/28goldens (znamy About poster). Browser overil filtr/nahledy/otevirani iframe, ne prehrani celych filmu. Zapis dukazu VIDEO-GALLERY-2026-10-17.md. Master stalefe561ed, zadny produkcni merge/deploy. Produkce ceka na konkretni souhlas uzivatele.

Stagingprokazatelne APIverejneHTML12/12,24fotoidentickych,4oldURL404,12thumbnail200; Chrome1920x1080/390x844 stabilnivyska a zadnechybejicividitelneobrazky. Vsech12karet otevre spravnyiframe. Kontrolniodkaz https://staging.rbvideosphotos.cz/nase-prace#videogalerie. Staging push obsahuje i drive pripravene front-endopravy, uzivateli vyslovne oznameno; zadne implicitni schvaleni produkce.

Dodatečné ověření celých views: karta i panel sdílejí tabs kontejner, panel je mimo page-form, kontakt panel nezobrazuje a vytvoření videogalerie předvolí video. Oba cílené testy prošly. První staging push c4be6a6 spuštěn.


O nás: desktop >=951px dostal 40% sloupec, menší mezeru48px a natažení fotografie na výšku copy bloku, image cover bez deformace. Obě Golden Bash šablony; <=950px původní layout. Rubrika: velikost/vyrovnání45, ořez25, responzivita20, runtime10. Předchozí detail potvrzuje krátkou fotografii, hodnocení72/100 (−28 velikost/vyrovnání). Hero přehrávač ve screenshotu nehraje; mimo rozsah opravy.

Iterace1: 88/100 (velikost45/45, ořez13/25, responzivita20/20, runtime10/10 v cílové sekci). Při951px příliš úzký vysoký portrét ořezával okraje obličejů. Hranice změny posunuta na1200px; menší layout zůstává původní. Nutné nové snímky1199/1200/1920/390.

Iterace2 náhled:96/100 (45/45 velikost,21/25 ořez — u1200px těsnější boky,20/20 responzivita,10/10 runtime cílové sekce). Oba obličeje plně viditelné; 1920px portrét552x698 proti původním430x473, výška shodná s copy. Mobil354x389 beze změny. QA-BACKLOG aktualizován. Zbývá ověřit skutečné nasazené generované CSS.

## Výsledek O nás 2026-10-17

Nasazeno f24e00b na staging, Actions39065542463 success. Skutečné generované CSS (bez injekce) ověřeno1920/1199/1200/390px, DPR1 Chrome, consent odmítnut skutečným tlačítkem. Cílová sekce96/100:45/45 velikost,21/25 ořez,20/20 responzivita,10/10 runtime portrétu. Detail .codex-qa/about-final-1920.png, -1199.png, -1200.png, -390.png. Celé stránky .codex-qa/about-final-full-desktop.png (1920x5593), -mobile.png (390x12565), stabilní výšky,0 failedVisibleImages. Fotografie552x698.25, mobil354x389.39, bez horizontálního overflow. QA-BACKLOG a wiki frontend-backlog-fixes aktualizovány. Produkce neřešena.

Samostatný nalezený problém: staging hero Vimeo hlásí This video does not exist. Přehrávání není součástí opravy fotografie; uživatel informován.

## Výsledek hero posunu2026-10-18

Nasazeno ba2d867, Actions39129260883 success. Chrome CDP bez lokálního CSS1920/701/700/390, DPR1, odmítnuté cookies: gradient shodný s uloženým stavem před změnou na všech4 šířkách; iframe i poster posun48/24/0/0px. Nulový horizontal overflow, poster načten. Důkazy .codex-qa/hero-shift-final.json a hero-shift-final-{1920,701,700,390}.png. Kompozice přehrávaného záběru není ověřena: Vimeo stále hlásí This video does not exist; neposkytnuto falešné vizuální skóre. Wiki frontend-backlog-fixes aktualizována. Produkční master beze změny.

Úprava: původní nepravidelné mask stops nahrazeny11 vzorky smoothstep3t²−2t³ přes30–80% šířky, zachovaný offset24–48px a mobil bez masky. Chrome načítá nové body a bez overflow při1920/701/700/390. Alpha kvantizuje na8bit (např .028 -> .027), test nesmí vyžadovat totožný desetinný řetězec. Náhled nad produkcí blokuje Vimeo zabezpečení připojení; nesmí být označen jako vizuální PASS nad přehrávaným záběrem.

## Výsledek plynulé masky2026-10-18

3d1f037 nasazen na staging, Actions39130163101 success. Skutečné computed CSS bez injekce ověřeno1920/701/700/390: nové smoothstep mask stops na desktopu, mobil none, zachován posun, nulový horizontální overflow. Důkazy .codex-qa/hero-smooth-final.json a hero-smooth-final-{width}.png. Wiki aktualizována. Vizuální potvrzení živého záběru zůstává otevřené kvůli Vimeo blokaci; žádné nové vizuální PASS skóre. Produkce beze změny.

## Výsledek kratšího gradientu2026-10-18

a502a3f nasazen na staging, Actions39140469546 success. Skutečné CSS v Chrome1920/701/700/390: neprůhledný konec58%, mobil none, offset zachován, žádný overflow. .codex-qa/hero-short-final.json a hero-short-final-{width}.png. Desktop screenshot vizuálně potvrzuje kratší pozvolný přechod proti chybovému přehrávači stejně jako uživatelská reference. Přehrávání blokuje ověření Vimeo připojení, žádný claim o živém záběru. Wiki aktualizována, produkce nezměněna.

JS nyní nastavuje šířku=heroHeight*realRatio a výšku=heroHeight, lifecycle vrací cleanup pro eventy/ResizeObserver a mediaquery sourcechange. CSS na konci Diagonal ruší všechny mobilní centrovací/posunové transformace a ukotví vpravo. Transparentní mezera vlevo při užším videu neukazuje starý poster ani černé pozadí; poster skryt jen po vložení přehrávače, chybový iframe není skrýván. Prošly Node testy metadat, poměrů, resize a breakpointů.

ARCHITECTURE a QA-BACKLOG aktualizovány: starý covermax výpočet a jednorázový výběr zdroje už neplatí. PHP ResponsiveHeroVideo8/8; Node metadata/bezpečnost/cleanup a breakpointy prošly. Skutečný AssetMinifier výstup prošel node --check. Konfigurace tabletURL je historicky prázdná a sdílí desktop; samostatný tablet zdroj lze vyplnit v administraci, nesmí se vymyslet jiný klip.

## Výsledek height-fit2026-10-18

8d59d30 na dev/staging, Actions39141510796 success. PHP8 responsivních testů, Node2 testy a minifikovaný bundle parse prošly. Živý Chrome nad nasazeným CSS/JS:390/700/701/1050/1051/1920, výška600px mobil a620px ostatní přesně odpovídá panelu, rightDelta0, poměry fixture1:1/4:3/426:206 zachovány, skutečná nakonfigurovaná sourceURL podle breakpointu a žádný overflow. Testovací zprávy metadat jsou výslovně označené; Vimeo skutečné přehrávání nadále neověřeno. .codex-qa/hero-height-fixture-results.json a hero-height-fixture-{width}.png. Gradient30–58% zachován, předchozí offset nahrazen right0. Tablet bez vlastníURL vědomě sdílí desktop, uživatel informován. ARCHITECTURE, QA-BACKLOG a wiki aktualizovány. Master beze změny.

Implementováno107: contact_info.form_email_cc a mail_archive.cc_recipient. CC validuje admin i transport; nepovolená změna odmítnuta repository, vynechané pole zachová hodnotu. MailArchive ukládá tělo aCC předSMTP. Duplicita s hlavním příjemcem potlačena, prázdná hodnota vypíná CC, reset sCC je výslovně odmítnut. Přehled zpráv kopii zobrazuje. SMTP testy aPHP lint prošly.

Integrační výsledky: SMTP3 testy, mail archive27 assertions, recipient policy13 assertions, password reset19 assertions plus negativní scénáře, upgrade109 migrací a16 contenthandlerů prošel. Testy používají náhodné lokální DB, ne produkční/staging data. DokumentaceDEPLOYMENT aARCHITECTURE aktualizována. K provozu nutná migrace107, žádné nové.env ani GitHubsecrets.

## Výsledek kopie kontaktu2026-10-19

6246ef4 nasazen na dev/staging, Actions39261191994 success. Staging migrate-util HTTP200,107 přítomna, Údržba dokončena a bez SQLSTATE. Settings view test ověřil prázdné/vyplněné/escapované CC, nepovinnost a odděleného hlavního příjemce. SMTP3 testy včetně skutečného RCPT proCC a očištění po další zprávě; mail archive28 assertions včetně zálohy po SMTP odmítnutí; policy13; reset19; upgrade109/16 prošly. Skutečná pošta se při testech neodesílala. Pole nepovinné, resetbezCC, žádné nové.env/secrets. DEPLOYMENT, ARCHITECTURE a wiki aktualizovány. Produkce beze změny.

# UKOL: Dalsi optimalizace PageSpeed

Datum: 2026-10-21
Zadani: Navazat na zlepseni mobilniho PageSpeed stagingu z puvodnich 60 na 87 bodu a odstranit dalsi nejvetsi priciny pomaleho vykresleni bez vizualni nebo funkcni regrese.
Doporuceny model: Sol - Vykon hero videa, fontu, kritickeho CSS a cache vyzaduje mereni, browser QA a regresni testy.

## Kroky

- [x] Precist konkretni diagnostiku cerstveho PageSpeed reportu a zmerit aktualni staging/dev baseline.
- [x] Seradit nalezy podle dopadu na LCP, blokovani vykresleni a objem prenosu; nedelat kosmeticke mikrooptimalizace pred hlavni pricinou.
- [x] Implementovat dalsi bezpecne optimalizace na `dev` se zachovanim vzhledu a tri breakpointu hero videa.
- [-] Overit testy, skutecne generovane assety, mobilni i desktopovy layout a znovu zmerit vykon.
- [x] Aktualizovat technickou dokumentaci a globalni wiki; vysledky zapsat vcetne omezeni.

## Poznamky

- Produkcni report z 21:26 ukazuje 60 bodu, LCP 3,7 s a CLS 0,698; produkce jeste neobsahuje aktualni `dev` optimalizace.
- Staging ve stejnou dobu ukazuje 87 bodu, pristupnost/doporucene postupy/SEO 100 a prochazeni agenty 3/3. Aktualni baseline se proto musi vztahovat ke stagingu.
- Stavajici otevrene podezreni: render-blocking page CSS a pet fontu; hero video je uz Range-served, `preload=metadata`, s lehkym posterem a rezervovanym pomerem stran.
- PageSpeed API vratilo 429 (denni kvota vycerpana). Lokalni Lighthouse se do 3 minut neukoncil a nevytvoril report; vysledek nebyl nahrazen odhadem. Systemovy TLS uspesne nacetl staging HTTP 200.
- Staging homepage ma 176749 B HTML, 161 `img` uzlu, 38238 B inline CSS a pet font preloadu. Hlavni novy zasah prevadi skryte modalni galerie z desitek `<img srcset>` uzlu na bezpecny JSON a obrazky vytvari az pri otevreni. Stare publikovane `.pt-photos` zustavaji explicitne podporovane; neplatny novy JSON se oznaci `data-gallery-error` a vypise do konzole, nikoli tise nahradi.
- Presny prepocet soucasneho staging HTML: 36 skrytych galerii obsahuje 109 `<img>` uzlu a 55367 B markupu. Stejna data v novem JSON kontraktu maji odhadovanych 29289 B, tedy o 26078 B / 47,1 % mene; hlavni prinos je odstraneni 109 DOM obrazku a jejich `srcset` z pocatecniho renderu.
- JSON pouziva `JSON_HEX_*` a `JSON_THROW_ON_ERROR`; render test s `</script><script>` payloadem overuje, ze nelze ukoncit datovy element a ze originalni Unicode/alt text projde beze ztraty. PHP lint, oba JS parsery, cilene gallery/theme testy a `git diff --check` prosly.
- Plna sada ma dva nesouvisejici zname blokery: drive existujici rozdil CSS mezi Golden Bash a Diagonal a chybejici lokalni DB pripojeni. Zmenene soubory CSS ani DB netykaji; cilene testy zmen prosly. Browser fixture prokazal otevreni JSON galerie, ale capture proces skoncil nenulove pri cekani na stabilni full-page vysku otevreneho modalu, proto nejde oznacit za kompletni browser PASS.

## Vysledek

Lokalni implementace je hotova a zdokumentovana v ARCHITECTURE i globalni wiki (commit 0c48fa0). Pred nasazenim se homepage stale chova podle stareho staging HTML; dalsi realne PageSpeed/DOM mereni je mozne az po samostatne povolenem pushi na `dev`. Produkcni `master` se nemeni.

---
# UKOL: Mobilni hero video pod navigaci bez odkryteho pruhu

Datum: 2026-10-21
Zadani: Ukotvit mobilni hero video pod prekryvajici navigaci a zaroven zajistit, aby video pokrylo celou sirku hero bez odkryteho pruhu vlevo.
Doporuceny model: Terra - Jde o presne vymezenou CSS regresi s responzivnim kontraktem a cilenym testem.

## Kroky

- [x] Zmerit ze screenshotu rozdil mezi zacatkem video elementu a spodkem navigace.
- [x] Opravit mobilni geometrii video elementu bez deformace obrazu.
- [x] Aktualizovat regresni test a technickou dokumentaci.
- [x] Spustit cilene testy a kontrolu diffu.
- [x] Commitnout hotovou opravu na `dev`; bez vyzvy nepushovat.

## Poznamky

Screenshot potvrzuje dve soubezne priciny: video zacina pod prekryvajici navigaci a jeho element ma jen 375 px proti zhruba 604 px sirokemu hero. Finalni mobilni kontrakt proto musi pouzit horni inset 72 px, spodni hranu 0 a plnou sirku/vysku zbytku panelu s `object-fit:cover` a `object-position:center top`.

Finalni CSS nyni zacina video na `top:72px`, nastavuje `width:100%` a `height:calc(100% - 72px)`. `object-fit:cover` zachovava pomer obrazu a orizne prebytek uvnitr plneho mobilniho panelu; video uz neni transformaci centrovany jako uzky 375px element.

Regresni test ted vyzaduje cely kontrakt: odsazeni 72 px, vysku zbytku hero, plnou sirku, cover orez a nulovou transformaci. ARCHITECTURE popisuje rozdil mezi nativnim mobilnim videem a Vimeo iframe.

Cileny Golden Bash test pro mobilni hero prosel a `git diff --check` nenasel chybu. Overeni je lokalni nad zdrojovym CSS; skutecny staging se zmeni az po samostatne povolenem pushi na `dev`.

## Vysledek

Mobilni nativni hero video zacina 72 px pod hornim okrajem prekryvane hero sekce, vyplni celou sirku a zbyvajici vysku a zachovava pomer pres `object-fit:cover`. Cileny regresni test prosel a `git diff --check` je cisty. Kompletni sada novy test take prosla; nadale obsahuje drive znamy nesouvisejici Golden Bash isolation FAIL a chybejici lokalni DB pripojeni. ARCHITECTURE byla aktualizovana. Zmena je pripravena v lokalnim commitu na `dev`; bez explicitniho pozadavku nebyla pushnuta.

---
# UKOL: Oprava mobilni stranky Sluzby

Datum: 2026-10-21
Zadani: Opravit responzivni layout stranky Sluzby, aby na mobilu nebyly karty a text stlacene do necitelnych uzkych sloupcu a vsechny sekce mely stabilni geometrii.
Doporuceny model: Terra - Jde o ohranicenou responzivni CSS opravu s vizualni kontrolou realne stranky.

## Vizualni rubrika (100 bodu, cil alespon 90)

- [ ] 20 b: platno 375 px bez horizontalniho overflow a useknuti.
- [ ] 10 b: hlavicka, breadcrumbs a mobilni navigace bez prekryvu.
- [ ] 15 b: hero ma citelny obsah, spravny orez a pouzitelna CTA.
- [ ] 25 b: sest sluzeb ma citelne karty, konzistentni obrazky a rozestupy.
- [ ] 15 b: proces spoluprace ma vsechny ctyri kroky a citelnou hierarchii.
- [ ] 10 b: CTA a footer jsou citelne a bez kolizi.
- [ ] 5 b: assety se nactou bez zjevne runtime chyby.

## Kroky

- [x] Prohlednout dodany full-page screenshot v puvodnim rozliseni a najit odpovidajici CSS.
- [x] Zachytit platny Chrome/CDP baseline na 375 px a zmerit problematicke sekce.
- [x] Upravit mobilni geometrii stranky Sluzby v Diagonal sablone.
- [x] Doplnit regresni test a dokumentaci.
- [x] Zachytit finalni 375/700/701px dukazy a vyhodnotit rubriku.
- [x] Commitnout hotovou opravu na `dev`; bez explicitniho pozadavku nepushovat.

## Poznamky

Dodany screenshot 375x3590 ukazuje hlavni regresi v kartach sluzeb: textova cast a obrazek zustavaji vedle sebe, text ma pouze priblizne polovinu karty a pouziva 10px pismo. Proces a CTA jsou kompaktni, ale musi se overit proti realnemu DOM a nesmi se hodnotit pouze ze zmenseneho full-page nahledu.

Baseline ze screenshotu: 64/100. Nejvetsi odpocty jsou sekce sluzeb -12, typografie a karty -8 a chybejici runtime/interakcni dukaz -6.

Chrome/CDP baseline `https://rbvideosphotos.cz/sluzby` pri 375x812 CSS px, DPR1, cache off: stabilni full-page vyska 3590 px, `scrollWidth===clientWidth===375`, 10/10 viditelnych obrazku nacteno. Karty maji 113px textovy sloupec, popisy i odkazy 10px a ctyri odkazy jsou hlasene jako ancestor-clipped. Cookie overlay byl pritomen; mimo jeho oblast zustava dukaz geometrie platny.

Prvni iterace na375/700px odstranila vsechny ancestor-clipped nalezy, ale povinna kontrola701px odhalila navrat desktopoveho vnitrniho splitu: nadpisy a popisy prekryvaly obrazky a sest odkazu bylo znovu hlaseno jako orezanych. Predchozi prubezne skore92/100 proto nebylo prijato jako finalni.

Diagonal CSS nyni do1050px sklada vnitrni obsah karty do jednoho sloupce: 210px obrazek pres celou sirku a obsah pod nim s prirozenou vyskou. Vnejsi grid zustava na701–1050px dvousloupcovy a do700px je jednosloupcovy. Popis je12px, fakta11.5px a odkaz ma36px vysokou plochu. Proces Sluzby dostal na telefonu12px popisy a minimalni vysku kroku122px. Plny desktop zacina az na1051px.

Finalni Chrome/CDP preview pouzil zivy DOM a assety z `https://rbvideosphotos.cz/sluzby`, cache off, DPR1 a realne tlacitko Odmitnout vse; lokalni CSS bylo vlozeno jako posledni author style. Pri375,700,701 a1050px plati `scrollWidth===clientWidth`, zadne outside/clipped/ancestorClipped prvky, zadne prekryvy a vsechny viditelne obrazky jsou nactene. Pri1051px se vraci desktop; QA nahlasilo drive existujici presah header CTA o43px, ktery nesouvisi s mobilni opravou a nebyl skryt.

Finalni mobilni skore preview:94/100 (platno20/20, hlavicka9/10, hero14/15, sluzby25/25, proces14/15, CTA/footer8/10, runtime preview4/5). Odecteno za existujici10px CTA texty a za to, ze generovany staging asset jeste nebyl nasazen. Dukazy: `.codex-qa/services-final-375.png`, `services-detail-375.png`, `services-final-700.png`, `services-final2-701.png`, `services-detail2-701.png`, `services-final2-1050.png` a `services-final2-1051.png`.

## Vysledek

Mobilni a tabletova stranka Sluzby ma citelne vertikalni karty bez prekryvu a orezani, plnou mobilni typografii procesu a stabilni breakpointy700/701/1050/1051. Cileny regresni test prosel a `git diff --check` je cisty. Kompletni sada novy test take prosla; nadale hlasi znamy nesouvisejici isolation FAIL Diagonal CSS a chybejici lokalni DB pripojeni. Zmena je lokalne pripravena na `dev`; bez explicitniho pozadavku nebyla pushnuta.
# UKOL: Staticky antispam kontaktniho formulare

Datum: 2026-10-22
Zadani: Doplnit deterministicky antispam pro verejne kontaktni formulare, ktery zachyti zjevne linkove a kasino spamy, zachova legitimni poptavky a podezrele zpravy ponecha dohledatelne bez odeslani e-mailu.
Doporuceny model: Terra - Omezena produkcni uprava musi spravne kombinovat skore, archivaci, mail transport a regresni testy bez falesnych blokaci.

## Kroky

- [x] Zmapovat prijem kontaktniho formulare, honeypot, rate limit, mail archive a admin prehled.
- [x] Navrhnout vysvetlitelne staticke skore s konzervativnimi pravidly a explicitnim duvodem blokace.
- [x] Ulozit spam do databaze jako rozlisitelny stav a neposilat jej pres SMTP.
- [x] Zobrazit spam a duvod v administraci bez zmeny resetu hesla.
- [x] Pridat regresni testy pro oba dodane spamy a legitimni ceske poptavky.
- [x] Aktualizovat relevantni dokumentaci, spustit cilene testy a commitnout na dev bez pushe.

## Poznamky

- Akceptace: oba ukazkove spamy jsou zachyceny, zpravy Sigurda a Barbory projdou a vsechny pokusy zustanou dohledatelne v DB/adminu.
- Platne projektove pravidlo: nejdriv dev a staging; master az po samostatnem overeni a novem vyslovnem souhlasu.
- Prijem je centralne v POST /kontakt/odeslat. Existujici honeypot a IP throttle zustavaji. Novy ContactSpamFilter pouziva prah 7 bodu; samotny odkaz ma jen 2 body, takze odkaz na legitimni misto svatby neblokuje.
- MailArchiveService::archiveSpam uklada plne telo se stavem spam a strojovym duvodem, ale vubec nevola SMTP. Admin prehled ma novy citelny stav, pocitadlo a filtr.
- Integrační test archivu mel zastaralou fixture jen z migrace 104, a proto mu chybel cc_recipient pridany pozdeji. Testovaci schema bylo doplneno lokalnim ALTERem; historicke migrace zustaly beze zmeny.
- ARCHITECTURE a DEPLOYMENT nyni popisuji prah, signaly, stav spam, zadne SMTP pro spam a dohledatelnost v adminu. Nova SQL migrace neni potreba, protoze status a error_code jsou existujici VARCHAR sloupce.
- Spam archiv zachovava i puvodni diagnostickou hodnotu CC pri stare/neplatne konfiguraci; nikdy ji nepouzije k odeslani, protoze spam cesta SMTP vubec nevola.
- Integracni test navic overuje allowlist filtru repository a celkovy spam counter, nejen HTML label.

## Vysledek

Kontaktni formular nyni pred SMTP pouziva konzervativni staticke skore s prahem 7. Oba dodane spamy dosahly 19 a 13 bodu, zatimco dodane legitimni poptavky 0 bodu a legitimni zprava s jedinym URL 2 body. Zachyceny spam se neodesila, ale zustava cely v mail_archive se stavem spam a strojovym duvodem; admin ho umi pocitat a filtrovat. Nova migrace ani env promenna nejsou potreba.

PHP lint zmenenych souboru, git diff --check a MailArchiveIntegrationTest s 20 kontrolami prosly. Kontaktni klasifikator prosel vsemi peti regresnimi vektory. Kompletni tests/run.php nadale obsahuje drive znamy nesouvisejici Golden Bash isolation FAIL a chybejici lokalni DB pripojeni; nebyly zakryty ani vydavany za uspesne. Globalni wiki byla aktualizovana samostatnym commitem. Zmena je commitnuta lokalne na dev a bez explicitniho pozadavku nebyla pushnuta.

---
---
# UKOL: Lokalni simulace health chyby 504

Datum: 2026-10-22
Zadani: V lokalnim prostredi ulozit jeden health check s HTTP 504 a zachytit screenshot toho, jak se problem zobrazi v administracni sekci Health.
Doporuceny model: Terra - Jde o omezeny lokalni test s databazovym zaznamem, spustenim aplikace a vizualnim overenim.

## Kroky

- [x] Overit lokalni DB, aplikacni server a prihlaseni do administrace.
- [x] Vlozit jeden izolovany `unhealthy` health zaznam s HTTP 504.
- [x] Otevrit lokalni administraci a zachytit screenshot Health sekce.
- [x] Overit, ze UI ukazuje problem a tabulka obsahuje HTTP 504.
- [x] Uvest lokalni data do rozumneho stavu a zaznamenat vysledek.

## Poznamky

Test se tyka pouze lokalni databaze. Produkce ani staging se nemeni.
Lokalni DB koncila migraci 103, proto byla izolovane aplikovana existujici append-only migrace 110. Vlozeny zaznam ma stav `unhealthy`, HTTP 504, kod `http_504` a dobu odezvy 15000 ms.

## Vysledek

Lokalni administrace ukazala stav `Web ma problem`, dostupnost 0,00 %, jednu provedenou a jednu nezdravou kontrolu a prumernou odezvu 15000 ms. Tabulka problemu obsahuje HTTP 504, DB `unknown`, kod `http_504` a zdroj `cron`. Screenshot je ulozen v `artifacts/health-504-local.jpg`. Testovaci zaznam zustava pouze v lokalni DB, aby si jej uzivatel mohl prohlednout; produkce ani staging nebyly zmeneny.

---
# UKOL: Sprava statickeho antispamu v administraci

Datum: 2026-10-22
Zadani: Pridat do Nastaveni bezpecnou spravu statickeho antispamu vcetne zapnuti, prahu, bodu, seznamu frazi a testovaci klasifikace bez odeslani formulare.
Doporuceny model: Sol - Zmena propojuje databazovou konfiguraci, validaci, administracni UI, runtime filtr a bezpecny testovaci endpoint.

## Kroky

- [x] Zmapovat uloziste globalnich nastaveni, admin save flow a existujici antispam kontrakt.
- [x] Pridat append-only migraci 111 a bezpecne vychozi hodnoty do fresh schema.
- [x] Zavest typovanou konfiguraci a validaci bez ticheho fallbacku pri poskozene konfiguraci.
- [x] Rozsirit ContactSpamFilter a odesilani formulare o DB konfiguraci.
- [x] Pridat sekci Nastaveni -> Antispam a testovaci klasifikaci bez SMTP/archivace.
- [x] Doplnit regresni a integracni testy, dokumentaci a lokalni vizualni kontrolu.
- [x] Commitnout hotovou zmenu na `dev`; bez vyzvy nepushovat a nemenit `master`.

## Poznamky

Stavajici pevna pravidla zustanou vychozim profilem. Administrace musi odmitnout neplatny prah, body, prilis dlouhe seznamy a prazdnou povinnou konfiguraci. Testovaci klasifikace nesmi zapisovat zpravu do archivu ani ji odeslat e-mailem.
Pridana verzovana konfigurace `ContactSpamConfig`, zapis do `sites.contact_spam_config_json` pres novou idempotentni migraci 111 a writable allowlist repository. Ulozene neplatne JSON nebo nepodporovana verze se nezamaskuji: verejne odeslani skonci viditelnou chybou a admin zobrazi opravny banner.
Nastaveni obsahuje prepinac, prah, sest bodovych vah, tri povinne seznamy a volitelny allowlist domen. Samostatny admin test pouziva ulozenou konfiguraci a pouze vraci skore/duvody pres flash; nevola MailArchiveService ani SMTP.
Cileny executable test prosel 7 kontrolami. PHP lint vsech zmenenych PHP souboru a `git diff --check` prosly. Plna sada dosla ke znamemu nesouvisejicimu Golden Bash isolation FAIL a pote k lokalnimu DB blockeru; tyto chyby nebyly skryty.

## Vysledek

Antispam lze spravovat v administraci v sekci Nastaveni -> Antispam. Spravce muze filtr zapnout nebo vypnout, upravit prah a sest bodovych vah, editovat zname fraze, reklamni vyrazy a CTA a povolit legitimni domeny. Samostatny test vyhodnoti vzorek proti naposledy ulozene konfiguraci bez zapisu do archivu a bez SMTP.

Nova append-only migrace 111 pridava verzovanou JSON konfiguraci webu. Neplatna ulozena konfigurace se nezamaskuje vychozimi hodnotami; admin zobrazi chybu a verejne odeslani se zastavi. Cilene testy, PHP lint, diff check, dvojite provedeni migrace a lokalni UI kontrola prosly. Cela testovaci sada nadale narazi na znamy nesouvisejici Golden Bash isolation FAIL a lokalni DB blocker. Globalni wiki byla aktualizovana samostatnym commitem `cd8bf6a`. Zmena zustava pouze na lokalni vetvi `dev`; staging ani produkce nebyly zmeneny.

---
# UKOL: E-mailove upozorneni na zachyceny spam

Datum: 2026-10-22
Zadani: Pridat do obecneho nastaveni webu checkbox, ktery po zachyceni spamu upozorni hlavniho prijemce kontaktniho formulare a vsechny nastavene CC prijemce.
Doporuceny model: Sol - Zmena zasahuje verzovanou konfiguraci, archivaci spamu, SMTP, admin UI a regresni testy.

## Kroky

- [x] Rozsirit antispam konfiguraci a admin UI o vychozene vypnute upozorneni.
- [x] Po bezpecne archivaci spamu odeslat prijemcum pevne upozorneni bez aktivniho spamoveho obsahu.
- [x] Pri selhani SMTP zachovat spam a chybu viditelne zalogovat bez falesneho potvrzeni.
- [x] Doplnit regresni testy a dokumentaci.
- [x] Commitnout hotovou zmenu na `dev`; bez vyzvy nepushovat a nemenit `master`.

## Poznamky

Upozorneni pouzije stejneho hlavniho prijemce a stejny normalizovany CC seznam jako bezna kontaktni zprava. Puvodni spamovy text ani odkazy se do upozorneni nekopiruji; e-mail obsahuje pouze ID archivniho zaznamu a strojove duvody. Spam musi zustat archivovany i pri chybe SMTP.
Novy realny vzorek `JamesTralf` obsahoval ruskou casino reklamu a odkaz. Vychozi slovnik byl rozsiren o `казино`, `играть` a `dragonmoney`; jazyk sam o sobe se neboduje a regresni test vyzaduje kombinaci reklamniho obsahu.

## Vysledek

Nastaveni antispamu obsahuje vychozene vypnuty checkbox pro e-mailove upozorneni. Je-li zapnuty, spam se nejprve ulozi a potom se hlavnímu prijemci a vsem normalizovanym CC odesle pevne upozorneni pouze s ID zaznamu a reason codes. Puvodni predmet, zprava a odkazy se do upozorneni nekopiruji. Odmitnuti SMTP vrati `notification_status=failed`, zapise bezpecny log a zachova spam v archivu.

Vychozi slovnik nyni zachyti i realny rusky casino vzorek `JamesTralf`. PHP lint a diff check prosly. Izolovana antispam sada ma 4 PASS testy vcetne SQLite overeni vypnuteho/zapnuteho upozorneni, vsech CC, sanitizovaneho tela a chyby SMTP. MySQL integracni test se v tomto behu nespustil, protoze lokalni DB konfigurace nema dostupny host; blocker zustal viditelny. Staging ani produkce nebyly zmeneny.
Globalni wiki byla aktualizovana samostatnym commitem `058403b`.

---
# UKOL: Kompaktni refaktor administracni sekce Nastaveni

Datum: 2026-10-23
Zadani: Zkratit a zpruhlednit dlouhou stranku Nastaveni pomoci sekci, tabu, subtabu, selektu, modalu nebo jinych kompaktnich UI vzoru bez ztraty funkci.
Doporuceny model: Sol - Rozsah zahrnuje velky PHP view, navigacni stav, pristupnost, responzivitu a vizualni regresi.

## Kroky

- [x] Zmapovat vsechny soucasne karty, formulare, kotvy a admin UI komponenty.
- [x] Navrhnout hlavni taby a kompaktni vnorene skupiny bez zmeny datoveho kontraktu.
- [x] Implementovat pristupne a responzivni prepinani sekci s podporou URL hash.
- [x] Zachovat samostatny antispam test, media pickery, validaci a ukladani vsech poli.
- [x] Overit desktop, responzivni CSS, klavesnici, reload/redirect hash a absenci JS chyb.
- [x] Doplnit cilene testy a dokumentaci a commitnout na `dev`; bez vyzvy nepushovat.

## Poznamky

Refaktor je prezentacni. Nazvy poli, POST route, CSRF, serverova validace a repository zapis zustanou beze zmeny. Modal se pouzije jen tam, kde nezakryje povinne nastaveni nebo nevytvori druhy save flow.

## Vysledek

Stranka Nastaveni je rozdelena do peti hlavních zalozek: Obecne, Vzhled a media, Komunikace, Mereni a skripty a Antispam. Aktivni zalozka se zapisuje do URL hash, jde ovladat sipkami/Home/End a pri HTML validacni chybe se automaticky odkryje spravna zalozka i sbalena podsekce. Dlouhe layoutove a skriptove bloky jsou sbalitelne. Test antispamu je samostatny pristupny modal a REST API je nativni rozbalovaci detail.

Vsechny puvodni nazvy poli, POST routy, CSRF, media picker i spolecne ukladani zustaly zachovane. Lokalni desktopova kontrola v realne administraci overila prepinani zalozek, hash, modal a sdilene ukladani. Responzivni breakpoint drzi taby ve vodorovnem scrollu a modal omezuje na vysku viewportu. PHP lint, diff check a 2/2 cilene kontraktni testy prosly. Cela legacy sada dosla k drive znamemu nesouvisejicimu Golden Bash golden testu a pote ji zastavila nedostupna lokalni DB; obe omezeni zustala zretelna.

---
# UKOL: Rozlisovaci favicon administrace

Datum: 2026-10-23
Zadani: Pridat administraci vlastni ikonu, aby sla v Chrome zalozkach snadno rozeznat od verejneho webu.
Doporuceny model: Sol - mala zmena sdileneho admin layoutu a autentizacnich vstupu.

## Kroky

- [x] Overit vsechny samostatne admin HTML head sablony.
- [x] Vytvorit kontrastni samostatny SVG favicon bez externich zavislosti.
- [x] Zapojit favicon a theme-color do administrace, loginu a obnovy hesla.
- [x] Overit HTTP odpoved, vykresleni v zalozce a regresni testy.
- [x] Commitnout na dev bez pushe.

## Vysledek

Administrace pouziva vlastni self-contained SVG favicon s tmavym podkladem, bilym pismenem A a modrym stavovym bodem. Ikona a odpovidajici theme-color jsou zapojene do hlavniho admin layoutu, prihlaseni i obnovy hesla. Lokalni prohlizec nacetl spravny link, asset vraci HTTP 200 a Content-Type image/svg+xml. PHP lint, diff check a 2/2 cilene testy prosly.

---
# UKOL: API nastaveni v zalozce Dev

Datum: 2026-10-23
Zadani: Presunout REST API blok v administracnim Nastaveni pod samostatnou zalozku Dev.
Doporuceny model: Sol - mala navazujici uprava struktury tabu se zachovanim API klice a napovedy.

## Kroky

- [x] Pridat zalozku Dev do existujici pristupne navigace.
- [x] Presunout REST API panel pod Dev bez zmeny jeho obsahu.
- [x] Overit prepinani, HTML/PHP syntaxi a cilene testy.
- [x] Commitnout na dev bez pushe.

## Vysledek

Navigace Nastaveni obsahuje sestou zalozku Dev. REST API panel je uvnitr jejiho tabpanelu a zustava nativne rozbalovaci; API klic ani seznam endpointu se nemenily. Panel pouziva stejny hash-aware a klavesove ovladany mechanismus jako ostatni zalozky. PHP lint, diff check a 2/2 cilene testy prosly.

---
# UKOL: Antispam Billyvep a falesne uctenky

Datum: 2026-10-25
Zadani: Zachytit oba dodane spamove vzorky a zachovat pruchod legitimnich poptavek.
Doporuceny model: Terra - Ohranicena uprava pravidel s regresnimi testy.

## Kroky

- [x] Zkontrolovat filtr a ulozenou konfiguraci.
- [x] Doplnit pravidla a regresni testy vcetne legitimnich zprav.
- [x] Overit syntaxi a cilene testy, aktualizovat dokumentaci a pripravit commit na dev.

## Poznamky

Existujici nesouvisejici artifacts/ obsahuje obrazky z minule prace; zustane mimo commit. Produkcni nasazeni vyzaduje overeny staging a konkretni souhlas s merge do master.
ContactSpamConfig obsahuje tri nove uzke signatury; migrace 112 je idempotentne doplni do ulozenych seznamu bez zmeny vah, prahu a prepinacu. Regresni testy zahrnuji oba cele vzorky i legitimni rustinu, odkaz s telefonem a zadost o doklad.
ARCHITECTURE popisuje nove fraze a nutnost migrace. Samostatny runner tests/run-contact-spam.php overuje filtr i notifikace; volitelny lokalni MySQL test pouziva vyhradne docasnou tabulku a kontroluje dvojity beh migrace i zachovani vlastnich nastaveni.
Filtr a archiv/notifikace: 5/5 PASS. PHP lint vsech zmenenych PHP a diff check prosly. MySQL test migrace se po povoleni lokalniho spojeni zastavil na Access denied root bez hesla; provedeni migrace tedy neni overene. Staging ani produkce se nemenily.

## Vysledek

Nove vychozi signatury zachyti oba vzorky pri standardnim prahu (12 a 10 bodu). Migrace 112 prida fraze i do ulozenych profilu bez prepisu nastaveni. PHP lint, diff check a pet cilenych testu prosly; realny SQL beh zustava neovereny kvuli lokalni autentizaci MariaDB. ARCHITECTURE je aktualizovana a globalni wiki ma samostatny commit 6882c68. Bez pushe a nasazeni; pred produkci je nutne overit staging a migraci.

---
# UKOL: Nasazeni novych antispam pravidel

Datum: 2026-10-25
Zadani: Pripravit produkcni release antispamu 03b8704 pres povinne overeni stagingu.
Doporuceny model: Terra - Nasazeni hotove zmeny s overenim migrace.

## Kroky

- [x] Overit rozsah dev proti master a pushnout dev na staging.
- [x] Overit deployment, staging a migraci 112 vcetne zachovani profilu.
- [x] Predlozit konkretni rozsah a vysledek ke schvaleni merge do master.
- [x] Po novem konkretnim souhlasu provest merge a overit produkcni deployment.

## Poznamky

Pozadavek na master prisel pred overenim stagingu. AGENTS.md a INSTRUCTIONS.md vyzaduji souhlas s konkretnim merge az po overeni stagingu. Existujici artifacts/ zustava mimo release.
Po git fetch je funkcni rozdil proti origin/master pouze commit 03b8704. Cilena antispam sada znovu prosla 5/5. Nasledujici commit obsahuje jen tento release zaznam.
Staging revize 2bcd0b83c60e7bf2eb3bf5bf11eafccf29598c67, Actions 40214508150 success. Homepage, /kontakt a /admin/login vraci HTTP 200. Rozsah proti master: 03b8704 (antispam) a 2bcd0b8 (release zaznam).
Migrace 112 lokalne overena dvakrat pres existujici ucet z .env, vyhradne na TEMPORARY tabulce sites: PASS idempotence i presna shoda vlastnich frazi, vah, prahu, domen a prepinacu; NULL/prazdny profil beze zmeny. Původni root test bez hesla zustava nefunkcni, nebyl vydavan za PASS. Jednorazovy pomocny skript byl odstranen.
Staging migrate-util.php bez staging tokenu vraci HTTP 403. Token neni v projektovych staging konfiguracich, lokalni .env patri localhostu. Uzivatel nasledne potvrdil staging migraci odpovedi overeno a po predlozeni revize 2bcd0b8 vyslovne schvalil master zprávou dej to nas master.
Master pushnut na 0dd960f (historie zahrnuje e1c70b5 a aktualni origin/master). git diff 2bcd0b8 HEAD byl prazdny: obsah presne odpovida schvalene revizi. Produkcni Actions 40214700758 success. Homepage, /kontakt a /admin/login HTTP 200. Pracovni vetev je opet dev. Produkcni migrace 112 se deploymentem nespousti a je samostatnym neoverenym krokem.

## Vysledek

Schvaleny obsah je na origin/master 0dd960f a produkcni deployment uspel. Produkcni migrace 112 nebyla spustena ani potvrzena; existujici ulozene profily ji potrebuji pro pridani novych frazi. Wiki release zaznam d8c2623 zachovava tuto mezeru. Tento zaverecny zaznam se commituje jen na dev, ne do schvalovaneho produkcniho obsahu.

---
# UKOL: Antispam First Lead Gen

Datum: 2026-10-25
Zadani: Zachytit dodanou nevyzadanou reklamu Keenan / First Lead Gen pri zachovani legitimnich poptavek a vlastnich pravidel.
Doporuceny model: Terra - Uzke rozsireni signatury a regresnich testu.

## Kroky

- [x] Doplnit znamou frazi a novou append-only migraci 113.
- [x] Overit cely vzorek, legitimni zpravy a idempotenci migraci v docasne lokalni tabulce.
- [x] Aktualizovat dokumentaci a wiki, pripravit commit na dev bez pushe.

## Poznamky

Predchozi souhlas s master plati pouze pro minuly release. Nova signatura nebude blokovat samotnou anglictinu, jmeno nebo kontaktni domenu.
ContactSpamConfig a migrace 113 obsahuji stejnou uzkou frazi. Test obsahuje cely dodany vzorek, HTML entity/whitespace variantu, legitimni anglicke poptavky od stejneho jmena/domeny i vypnuty filtr. MySQL runner nyni pouziva mistni ucet z .env (s kontrolou 127.0.0.1) a historicky kontrakt 112 ma explicitni seznam, nikoli menici se defaults. ARCHITECTURE popisuje novou signaturu a aktualni testovaci prikaz.
Sada vcetne lokalni MariaDB 7/7 PASS; PHP lint vsech tri zmenenych PHP souboru a diff check PASS. Migrace 112 zustala nezmenena. Migrace 113 zachovala vlastni fraze, vahy, prah, domeny, oba prepinace i NULL/prazdne profily.

## Vysledek

First Lead Gen / Keenan vzorek ma 10 bodu a je zachycen pri vychozim prahu 7. Novou frazi pridava vychozi profil a append-only migrace 113; uzivatelska konfigurace se neprepisuje. Sedm testu vcetne realne lokalni SQL idempotence proslo, stejne jako PHP lint a diff check. ARCHITECTURE a globalni wiki (d682e62) jsou aktualizovane. Zmena zustava lokalne na dev, neni nasazena; existujici online profily budou potrebovat migraci 113 po staging overeni a novem schvaleni produkce.

---
# UKOL: Antispam francouzska reklama na clanek

Datum: 2026-10-28
Zadani: Doplnit pravidlo pro dodany Marie06 spam bez blokace legitimnich francouzskych poptavek.
Doporuceny model: Terra - Uzka kampanová signatura a regresni testy.

## Kroky

- [x] Doplnit signaturu do defaults a migrace 114 bez zmeny starych migraci.
- [x] Overit cely vzorek, legitimni francouzstinu a zachovani profilu pri opakovane migraci.
- [x] Aktualizovat dokumentaci a wiki a pripravit commit na dev bez pushe.

## Poznamky

Existujici artifacts/ zustava mimo zmenu. First Lead Gen zmena 2fd82e5 je stale pouze lokalne; nove pravidlo take nema souhlas s produkcnim nasazenim.
ContactSpamConfig a nova migrace 114 pridavaji jednu uzkou frazi. ContactSpamFilterTest obsahuje cely vzorek, variantu whitespace/case, legitimni francouzske poptavky vcetne obou paste webu a vypnuty filtr. SQL runner testuje 114 dvakrat a presnou shodu zachovane konfigurace po 112/113/114. ARCHITECTURE popisuje rozsah a omezeni pravidla. PHP lint a diff check prosly; bez DB 7/7 PASS.

## Vysledek

Marie06 vzorek dosahuje 12 bodu pri prahu 7; legitimni francouzstina a samotne paste odkazy se neblokuji. Migrace 114 pridava frazi k existujicim seznamum, nic nemaze a zachovava vsechna vlastni nastaveni. Cilena sada vcetne lokalni MariaDB 8/8 PASS, lint a diff check PASS. ARCHITECTURE a globalni wiki (17659c7) aktualizovany. Lokalni commit na dev bez pushe; online aktivace vyzaduje staging a migraci 114, produkce novy konkretni souhlas.

---
# UKOL: Antispam nabidka osobnich a firemnich databazi

Datum: 2026-10-28
Zadani: Doplnit pravidlo pro dodany Nikolaj spam bez plosne blokace rustiny nebo Telegramu.
Doporuceny model: Terra - Uzka znama spamova signatura a regresni testy.

## Kroky

- [x] Pridat signaturu do defaults a append-only migrace 115.
- [x] Overit cely vzorek, legitimni poptavky a idempotentni zachovani ulozenych pravidel.
- [x] Aktualizovat dokumentaci a wiki, pripravit commit na dev bez pushe.

## Poznamky

Nove pravidlo se prida k existujicim seznamum. Vlastni fraze, vahy, prah, domeny a prepinace se neprepisuji. Predchozi lokalni zmeny a artifacts/ zustavaji zachovane.
Defaults a migrace 115 pouzivaji frazi bez carek, aby admin parseList nerozdelil signaturu na obecna slova. Testy obsahuji cely vzorek, normalizaci, legitimni rustinu/Telegram a vypnuty filtr. SQL runner overuje 115 dvakrat a zachovani vsech drivejsich frazi; ARCHITECTURE popisuje pravidlo a jeho rozsah.

## Vysledek

Dodany Nikolaj vzorek dosahuje 10 bodu pri prahu 7. Migrace 115 prida znamou frazi k existujicim pravidlum a neprepisuje vlastni nastaveni. Cilena sada vcetne MariaDB a admin roundtripu 9/9 PASS, PHP lint a diff check PASS. ARCHITECTURE a wiki (914a871) aktualizovany. Zmena se commituje pouze lokalne na dev bez pushe; online aktivace vyzaduje staging a migraci 115 a novy souhlas s produkci.

