# UKOL: Administrace Google Tag Manageru

Datum: 2026-06-22
Zadani: Vytvorit samostatnou sekci administrace, ktera pres Google Tag Manager API umozni jednoduse spravovat GTM pripojeni, eventove definice, znacky, triggery, promenne, workspace a verze bez nutnosti pracovat primo v rozhrani Google Tag Manageru.

## Rozsah prvni faze

- Implementovat pouze administracni a backendovou integraci s Google Tag Manager API.
- Nemenit soucasny GA4 tracking, `public/assets/tracking.js`, consent runtime ani frontendove spousteni udalosti.
- Neimplementovat `dataLayer.push()` ani prechod runtime z GA4 na GTM.
- Neoverovat, zda jednotlive eventy na webu uz existuji nebo se skutecne spousteji.
- Umoznit pripravu a spravu GTM konfigurace pro budouci druhou fazi refaktoru.
- Pri chybe API, konfliktu nebo nejednoznacne duplicite operaci zastavit a zobrazit konkretni problem. Nepouzivat tiche fallbacky.

## Pozadovane eventy

Administrace musi obsahovat predpripraveny katalog techto eventu:

| Event | Popis | Typ |
| --- | --- | --- |
| `lead_form_submit` | Uspesne odeslani kontaktniho formulare | hlavni konverze |
| `phone_click` | Kliknuti na telefonni cislo | mikro-konverze |
| `email_click` | Kliknuti na e-mail | mikro-konverze |
| `pricing_click` | Kliknuti na cenik, balicek nebo cenu | mikro-konverze |
| `cta_click` | Kliknuti na hlavni CTA tlacitko | mikro-konverze |
| `gallery_open` | Otevreni galerie nebo detailu fotky | engagement |
| `gallery_image_view` | Zobrazeni konkretni fotky v galerii | engagement |
| `gallery_category_click` | Kliknuti na kategorii galerie | engagement |
| `video_play` | Spusteni prezentacniho videa | engagement |
| `scroll_90` | Doscrollovani na 90 % stranky | engagement |
| `social_click` | Kliknuti na socialni sit | odchozi interakce |
| `external_link_click` | Kliknuti na externi odkaz mimo web | odchozi interakce |
| `file_download` | Stazeni PDF, brozury nebo nabidky | mikro-konverze |
| `thank_you_page_view` | Zobrazeni dekovaci stranky | kontrolni konverzni event |
| `contact_section_view` | Zobrazeni kontaktni sekce | engagement |
| `service_detail_view` | Zobrazeni detailu sluzby | engagement |
| `package_select` | Vyber konkretniho balicku | silna mikro-konverze |
| `booking_start` | Zacatek rezervace terminu | silna mikro-konverze |
| `booking_complete` | Dokonceni rezervace terminu | hlavni konverze |
| `map_click` | Kliknuti na mapu nebo navigaci | mikro-konverze |

## Uzivatelske rozhrani

- Pridat samostatnou polozku hlavni administracni navigace `Google Tag Manager`.
- Pouzit hlavni cestu `/admin/gtm`.
- Rozdelit sekci minimalne na prehled, pripojeni, eventy, znacky, triggery, promenne, synchronizaci a verze.
- Na prehledu zobrazit stav OAuth pripojeni, vybrany GTM ucet, kontejner, workspace, zive publikovanou verzi, posledni synchronizaci a dostupna opravneni.
- U eventu zobrazit nazev, popis, kategorii, stav zapnuto/vypnuto, navazany trigger, znacky, promenne a stav synchronizace.
- Nabidnout jednoduchy formular pro vytvoreni nebo upravu eventove definice bez nutnosti zadavat surovy GTM API JSON.
- Pokrocily editor GTM parametru oddelit od bezneho formulare a jasne oznacit.
- U znacek, triggeru a promennych odlisit entity spravovane aplikaci od externich entit vytvorenych primo v GTM.
- Pred kazdym zapisem zobrazit plan zmen se stavy `vytvorit`, `aktualizovat`, `beze zmeny`, `mozna duplicita` a `konflikt`.
- Publikaci oddelit od ulozeni a aplikovani zmen. Zadna bezna formularova akce nesmi automaticky publikovat produkcni kontejner.
- Vsechny chyby GTM API zobrazit uzivateli srozumitelne a zaroven ulozit jejich technicky detail do auditniho logu.

## Google OAuth a opravneni

- V Google Cloud projektu povolit Google Tag Manager API v2.
- Implementovat OAuth 2.0 web-server flow s `state` ochranou a validovanou callback URL.
- Pozadovat nejmensi nutne scopes podle funkce:
  - `tagmanager.readonly` pro nacitani inventare,
  - `tagmanager.edit.containers` pro znacky, triggery a promenne,
  - `tagmanager.edit.containerversions` pro vytvareni verzi,
  - `tagmanager.publish` pouze pokud ma byt povolena publikace.
- Refresh token ukladat sifrovane. Client secret a sifrovaci klic neukladat do databaze ani repozitare.
- Umoznit odpojeni Google uctu a zneplatneni lokalne ulozenych tokenu.
- Pred kazdou operaci overit, ze autorizovany uzivatel ma pristup k vybranemu uctu a kontejneru.
- Chybejici scope nebo nedostatecne GTM opravneni zobrazit jako explicitni blokujici stav.

## Navrzene backendove komponenty

- `GtmController`: admin routy, formulare a orchestrace uzivatelskych akci.
- `GtmOAuthController`: zahajeni OAuth flow, callback a odpojeni.
- `GtmApiClient`: typovana komunikace s Google Tag Manager REST API v2, timeouty, parsovani chyb a rizeny retry mechanismus.
- `GtmConnectionService`: sprava spojeni, tokenu, vyberu uctu, kontejneru a workspace.
- `GtmInventoryService`: nacteni tagu, triggeru, promennych, workspace a verzi.
- `GtmEventCatalog`: centralni definice podporovanych eventovych sablon.
- `GtmManifestCompiler`: prevod jednoduche eventove definice na pozadovane GTM entity.
- `GtmDuplicateDetector`: detekce existujicich a potencialne duplicitnich entit.
- `GtmDiffService`: porovnani lokalniho pozadovaneho stavu se stavem v GTM.
- `GtmPlanService`: sestaveni nemenneho planu operaci pred zapisem.
- `GtmApplyService`: aplikovani potvrzeneho planu do workspace.
- `GtmVersionService`: synchronizace workspace, quick preview, vytvoreni verze a volitelna publikace.
- `GtmAuditService`: zaznam operaci, vysledku, konfliktu a chyb bez ukladani tajnych tokenu.

## Datovy model

Vytvorit nove append-only migrace; neupravovat historicke migracni soubory.

### `gtm_connections`

- `id`, `site_id`
- Google account identity a zobrazovane jmeno
- sifrovany refresh token a metadata tokenu
- GTM `account_id`, `container_id`, `workspace_id`
- stav spojeni, scopes, posledni overeni
- timestamps

### `gtm_event_definitions`

- `id`, `site_id`
- `event_key`, zobrazovany nazev, popis a kategorie
- `enabled`
- cilovy typ znacky a konfigurace parametru
- konfigurace triggeru
- verze lokalni definice
- timestamps
- unikatni omezeni pro `site_id + event_key`

### `gtm_managed_entities`

- `id`, `site_id`, `event_definition_id`
- typ entity: `tag`, `trigger`, `variable`, pripadne `folder`
- stabilni `management_key`
- GTM resource path a remote ID
- posledni znamy fingerprint
- normalizovany configuration hash
- ownership: `managed`, `adopted`, `external`
- posledni synchronizace a timestamps
- unikatni omezeni pro `site_id + management_key`

### `gtm_sync_runs`

- identifikace spojeni, workspace a uzivatele
- typ behu: inventory, plan, apply, version, publish
- stav: pending, running, succeeded, failed, conflict
- souhrn, GTM request ID, chyba a timestamps

### `gtm_sync_operations`

- vazba na sync run
- poradi operace
- typ entity a akce: create, update, noop, conflict, delete
- lokalni management key a remote path
- bezpecne redigovany request/response payload
- stav a chybova zprava

### `gtm_container_versions`

- GTM version ID a path
- jmeno, poznamka a fingerprint
- priznak publikovani
- vazba na sync run
- timestamps

## Sprava entit a duplicity

- Vsechny aplikaci vytvorene objekty pojmenovat konzistentnim prefixem `[SEO Editor]`.
- Kazde spravovane entite pridelit stabilni `management_key`, napr. `seo-editor:event:phone_click:tag`.
- Shodu neurcovat pouze podle zobrazovaneho nazvu.
- Pri hledani existujici entity postupovat v poradi:
  1. ulozeny GTM resource path nebo remote ID,
  2. stabilni management key ulozeny v lokalni evidenci,
  3. presna semanticka shoda normalizovane konfigurace,
  4. podobny nazev nebo castecna shoda pouze jako mozna duplicita.
- Pokud je nalezeno vice kandidatu, nevytvaret novou entitu a vratit konflikt.
- Pri update posilat posledni znamy GTM fingerprint. Neshoda fingerprintu musi operaci zastavit.
- Externi entity automaticky nemenit, neprejmenovavat ani nemazat.
- Prevzeti existujici entity do spravy aplikace musi byt explicitni akce `adoptovat`.
- Mazani povolit pouze pro `managed` nebo explicitne `adopted` entity a vzdy az po potvrzeni.
- Plan operaci musi byt deterministicky a opakovane spusteni nad nezmenenym stavem musi vratit pouze `beze zmeny`.

## Prace s workspace a verzemi

- Zmeny zapisovat pouze do explicitne vybraneho nebo aplikaci vytvoreneho workspace.
- Pred planem i aplikovanim nacist aktualni stav workspace.
- Pred zapisem zavolat workspace sync proti nejnovejsi verzi kontejneru.
- Merge konflikty zobrazit a zastavit dalsi automaticke operace.
- Po aplikovani znovu nacist inventory a overit, ze vznikl ocekavany stav.
- Umoznit quick preview a zobrazit compiler errors.
- Vytvoreni verze musi mit uzivatelsky zadany nazev a automatickou auditni poznamku.
- Pocitat s tim, ze vytvoreni verze zrusi puvodni workspace a GTM vrati novy workspace path; novou hodnotu ulozit.
- Publikaci povolit pouze samostatnou akci, s novym CSRF overenim a potvrzenim konkretni verze.
- Pri publikaci pouzit fingerprint verze a pri konfliktu nic neprepisovat.

## API klient a odolnost

- Pouzit oficialni GTM REST API v2 nebo oficialni Google PHP klientskou knihovnu.
- Nastavit connect timeout, request timeout a maximalni velikost odpovedi.
- Respektovat GTM API kvotu 0,25 pozadavku za sekundu a 10 000 pozadavku denne.
- Implementovat centralni rate limiter a sekvencni zpracovani operaci.
- Retry pouzit pouze pro docasne chyby, napr. `429` a vybrane `5xx`, s exponential backoff a jitterem.
- Neopakovat automaticky validacni chyby, auth chyby, konflikty fingerprintu ani zapisy s nejasnym vysledkem.
- Do logu neukladat access token, refresh token, client secret ani sifrovaci klic.
- Uchovavat dostatek redigovanych dat pro dohledani konkretni operace.

## Admin routy

Minimalni navrh rout:

- `GET /admin/gtm`
- `GET /admin/gtm/connect`
- `GET /admin/gtm/oauth/callback`
- `POST /admin/gtm/disconnect`
- `POST /admin/gtm/connection/select`
- `GET /admin/gtm/events`
- `GET /admin/gtm/events/edit`
- `POST /admin/gtm/events/update`
- `GET /admin/gtm/entities`
- `POST /admin/gtm/inventory/refresh`
- `POST /admin/gtm/plan`
- `POST /admin/gtm/apply`
- `POST /admin/gtm/entities/adopt`
- `POST /admin/gtm/entities/delete`
- `GET /admin/gtm/versions`
- `POST /admin/gtm/versions/create`
- `POST /admin/gtm/versions/publish`

Vsechny zapisove admin routy musi vyzadovat prihlaseneho administratora a CSRF kontrolu.

## Implementacni kroky

- [ ] Proverit aktualni admin navigaci, routovani, autentizaci, CSRF a databazove konvence projektu.
- [ ] Navrhnout konkretni GTM domenu, DTO a validacni pravidla bez vazby na frontend tracking.
- [ ] Pridat konfiguraci Google OAuth a GTM API do `.env.example` a aplikacni konfigurace.
- [ ] Pridat novou append-only migraci pro GTM tabulky, indexy a unikatni omezeni.
- [ ] Implementovat repository vrstvu pro GTM spojeni, eventy, spravovane entity, sync runy a verze.
- [ ] Implementovat bezpecne sifrovani a rotovatelne ukladani refresh tokenu.
- [ ] Implementovat OAuth connect, callback, scope validaci a disconnect.
- [ ] Implementovat `GtmApiClient` s explicitnimi chybami, rate limitingem a retry pravidly.
- [ ] Implementovat nacteni GTM uctu, kontejneru, workspace, tagu, triggeru, promennych a verzi.
- [ ] Implementovat predpripraveny katalog vsech 20 eventu.
- [ ] Implementovat compiler eventove definice na GTM tagy, triggery a promenne.
- [ ] Implementovat stabilni management keys, normalizaci konfigurace a configuration hash.
- [ ] Implementovat detekci duplicit, konfliktu, externich entit a adoptovani.
- [ ] Implementovat read-only diff a plan operaci bez zapisu do GTM.
- [ ] Implementovat potvrzene aplikovani planu do workspace.
- [ ] Implementovat workspace sync, kontrolu merge konfliktu a overeni vysledku.
- [ ] Implementovat quick preview, vytvoreni verze a ulozeni noveho workspace path.
- [ ] Implementovat samostatnou a chranenou publikaci konkretni GTM verze.
- [ ] Pridat polozku do admin navigace a vytvorit vsechny obrazovky sekce `/admin/gtm`.
- [ ] Pridat srozumitelne prazdne, nacitaci, chybove, konfliktni a uspesne stavy.
- [ ] Pridat auditni historii bez citlivych udaju.
- [ ] Pridat unit testy pro compiler, hashovani, duplicity, diff a plan.
- [ ] Pridat integracni testy API klienta s mockovanymi GTM odpovedmi.
- [ ] Otestovat idempotenci: druhy beh nad stejnym stavem nesmi vytvorit dalsi entity.
- [ ] Otestovat auth chyby, chybejici scopes, API kvotu, fingerprint konflikt, merge konflikt a compiler error.
- [ ] Rucne overit admin workflow na testovacim GTM kontejneru bez publikace do produkce.
- [ ] Aktualizovat `README.md`, `ARCHITECTURE.md`, `.env.example` a dalsi relevantni dokumentaci.
- [ ] Provest zaverecnou kontrolu, ze nebyl zmenen GA4 runtime ani frontendove eventy.

## Akceptacni kriteria

- Administrator se muze bezpecne pripojit ke Google uctu a vybrat GTM ucet, kontejner a workspace.
- Administrace umi nacist a zobrazit existujici GTM tagy, triggery, promenne a verze.
- Katalog obsahuje vsech 20 pozadovanych eventu a umoznuje jejich konfiguraci.
- Administrace umi predem zobrazit presny plan vytvoreni nebo zmen GTM entit.
- Opakovane spusteni nad stejnym stavem nevytvari duplicity.
- Konflikty, nejednoznacne duplicity, chybejici scopes a API chyby jsou viditelne a blokuji zapis.
- Zmeny se aplikuji pouze do workspace a nepublikuji se automaticky.
- Vytvoreni verze a publikace jsou dve samostatne, auditovane akce.
- Externi GTM entity nejsou bez explicitniho potvrzeni meneny ani mazany.
- Citlive OAuth udaje nejsou ulozeny v otevrene podobe ani zapisovany do logu.
- Soucasny GA4 tracking a frontendove eventy zustanou v teto fazi beze zmeny.

## Poznamky

- GTM API spravuje konfiguracni entity. V teto fazi se neresi runtime spousteni udalosti na webu.
- Pro implementaci databaze plati append-only migracni workflow.
- Google API ma nizky QPS limit, proto nesmi UI pri jednom refreshi bezhlave paralelizovat desitky API requestu.
- Samotny surovy GTM JSON nema byt hlavnim uzivatelskym rozhranim. Bezna konfigurace ma byt vedena pres bezpecne sablony a validovane formulare.
- Pred zahajenim implementace overit aktualni dokumentaci GTM API a konkretni payloady pro pouzite typy tagu, triggeru a promennych.

## Vysledek

Nevyplneno. Tento soubor je podrobne zadani pro budouci implementaci; kod GTM administrace zatim nebyl vytvoren.
