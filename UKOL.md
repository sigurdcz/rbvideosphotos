# UKOL: RFC 9116 security.txt generator

Datum: 2026-07-10
Zadani: Pridat do jadra SEO CMS plnohodnotny DB-driven generator `security.txt` podle RFC 9116 vcetne administrace, validace, verejnych GET/HEAD endpointu, HTTP cache, testu, dokumentace a samostatneho Git commitu.

Hotovo kdyz: konfigurace se bezpecne uklada pres novou append-only migraci; admin pouziva stavajici autentizaci, opravneni a CSRF; generator produkuje deterministicky UTF-8 text; `/.well-known/security.txt` podporuje 200/304/404 a GET/HEAD, `/security.txt` kanonicky presmeruje; validace blokuje neplatne URI, expiraci, jazyky a injection; automaticke testy i regresni kontroly stavajicich technickych souboru projdou.

## Kroky

- [x] Zmapovat router, globalni konfiguraci, admin formulare, opravneni, cache, generatory a testy
- [x] Overit RFC 9116 a IANA pravidla v oficialnich zdrojich
- [x] Navrhnout DB model a pridat novou append-only migraci
- [x] Implementovat config repository, validator a generator
- [x] Implementovat verejne GET/HEAD endpointy, redirect a HTTP cache
- [x] Implementovat administracni controller, formulare, live preview a stav expirace
- [x] Dopsat automaticke unit/integracni testy a regresni kontroly
- [x] Aktualizovat README/ARCHITECTURE a projektovou wiki
- [x] Provest finalni diff review, commitnout samostatny commit a overit cisty strom

## Poznamky

- Existujici migrace jsou historicke a nebudou se menit; schema vznikne pouze novym migracnim souborem s dalsim poradovym cislem.
- Zadani vyzaduje analyzu oficialniho RFC/IANA zdroje pred navrhem validatoru.
- Push nebyl vyzadan; po dokonceni se provede pouze samostatny commit.
- Projekt je vlastni PHP 8 aplikace bez frameworku. Public request flow ridi `public/index.php`, admin `App\Core\Router`, globalni web data jsou v MySQL/MariaDB a admin pouziva session role, `requireAdmin()` a CSRF.
- Existujici technicke generatory jsou samostatne sluzby (`RobotsGenerator`, `SitemapGenerator`, `LlmsTxtGenerator`) volane z `PublishService`. Obecna cache chybi; pouziva se pouze jednoducha souborova cache u throttlingu, proto security.txt dostane uzce zamerenou souborovou cache.
- Projekt nema PHPUnit ani adresar `tests`; vznikne lehky PHP test runner bez nove Composer zavislosti.
- Oficialni RFC 9116 potvrzuje povinny `Contact`, prave jedno `Expires`, LF/CRLF, UTF-8 plain text, HTTPS web URI, BCP 47 jazyky a kanonickou well-known cestu. IANA registr potvrzuje opakovatelnost podporovanych poli.
- DB navrh: `security_txt_configs` s jednim radkem na web a `security_txt_contacts` se `sort_order`; chybejici tabulky budou explicitne hlasi schema stav a verejny endpoint bez nich korektne vrati 404.
- Nova append-only migrace `020_create_security_txt_tables.sql` vytvari jeden config na `site_id` a kontaktni radky s deterministickym `sort_order`; existujici migrace zustaly beze zmeny.
- `database/schema.sql` byl synchronizovan pro nove instalace; historicke migracni soubory ani seed data se nemenily.
- Lokalni `php database/migrate-mysql.php` skoncil pred migraci `020`: DB ma cast starsiho schematu bez odpovidajicich zaznamu v `schema_migrations` a MySQL DDL implicitne ukoncilo transakci, na coz runner chybne volal `rollBack()`. Nova tabulka nevznikla. Historicke SQL soubory se menit nebudou; runner dostane pouze bezpecnou kontrolu `inTransaction()`, aby nezakryl puvodni PDO chybu, a `020` bude overena izolovane.
- `database/migrate-mysql.php` nyni vola rollback jen pri aktivni transakci, takze MySQL DDL chyba zustane puvodni a diagnostikovatelna misto sekundarniho fatalu `There is no active transaction`.
- Nasledna diagnostika potvrdila, ze MySQL DDL implicitne ukoncuje i uspesnou transakci a runner padal na `commit()`. Runner proto pouziva transakcni DDL jen pro ne-MySQL driver; u MySQL zapise `schema_migrations` az po uspesnem SQL. Nova `020` je opakovatelna pres `CREATE TABLE IF NOT EXISTS`.
- Opraveny runner nasledne bez chyby aplikoval vsechny cekajici migrace vcetne `020`; obe security.txt tabulky byly overeny v `information_schema`.
- `SecurityTxtValidator` implementuje serverovou validaci kontaktu, striktniho RFC 3339 `Expires`, limitu pod jeden rok, HTTPS optional URL, BCP 47-like jazykovych tagu, delek a CR/LF i percent-encoded header injection. Vystup normalizuje do UTC a vraci expiracni stav 30/7 dnu.
- `SecurityTxtGenerator` sklada deterministicky LF dokument, zachovava poradi Contact, vynechava prazdna volitelna pole, bezpecne prefixuje komentare, brani vice radkum i pri primem volani a vzdy konci novym radkem bez BOM.
- `SecurityTxtRepository` detekuje dostupnost obou tabulek, pri chybejici migraci vraci explicitni `schema_available=false`, nacita kontakty v preferovanem poradi a uklada config i serazene kontakty atomicky v DB transakci.
- `SecurityTxtCache` uklada verzovany JSON payload po webu do `storage/cache/security_txt`, pouziva file lock a poskytuje explicitni invalidaci; chyba vytvoreni, zapisu nebo smazani cache se nezamlcuje.
- `SecurityTxtService` koordinuje DB, validaci, generator a cache; vytvari prvni default s expiraci +180 dnu a canonical podle primarni domeny, pri ulozeni cache invaliduje/obnovi a pro public endpoint vraci obsah, ETag, Last-Modified i explicitni disabled/invalid/expired stav.
- `SecurityTxtHttpResponder` pripravuje ciste 200/304/308/404/405 odpovedi pro GET/HEAD, kratkou revalidovatelnou cache, ETag/Last-Modified, `nosniff`, root redirect a nulove telo pro HEAD/304 bez HTML layoutu.
- `public/index.php` obsluhuje oba security.txt endpointy jeste pred API, assety a obecnym CMS routerem; nacita DB/cache payload, predava conditional request hlavicky a vypisuje pouze status, HTTP hlavicky a plain-text telo.
- `PublishService` synchronizuje validni obsah i do `storage/published/.well-known/security.txt`, pri vypnuti/invalidite odstrani stary soubor a do generovaneho `.htaccess` prida legacy 301 jen pro aktivni validni konfiguraci.
- `SecurityTxtController` pouziva `requireAdmin()`, CSRF, session zachovani chybneho formulare a stavajici Logger jako audit. Po ulozeni okamzite obnovi dynamickou cache, staticky well-known soubor i `.htaccess`; chyby cache/exportu jsou viditelne a zalogovane.
- Nova admin view obsahuje zapnuti, seraditelny repeater kontaktu, expiraci, vsechna pozadovana URL/jazyky/komentare, serverove stavy, expiracni upozorneni, odkaz na endpoint, zivou textovou ukazku a vyslovne varovani, ze soubor neuděluje opravneni k testovani.
- Admin router registruje GET/POST security.txt routy a hlavni sidebar obsahuje samostatnou polozku `Security.txt` vedle publikovani a nastaveni.
- `admin.css` doplnuje responzivni stavove karty, dvousloupcovy formular/náhled, ovladani poradi kontaktu a mobilni skladani bez nove UI knihovny.
- Security admin styly pouzivaji existujici admin tokeny vcetne `--c-error`; nebyl zaveden paralelni barevny system.
- PHP lint vsech novych a dotcenych runtime/admin souboru prosel. Prime volani validatoru potvrdilo normalizaci minimalni platne konfigurace do UTC a bez chyb.
- `tests/run.php` pridava 18 automatickych scenaru bez externiho frameworku: vsech 17 pozadovanych pripadu a navic `If-None-Match` -> 304.
- Prvni HTTP smoke test potvrdil disabled 404 na obou URL, ale odhalil zbytecne zalozeni PHP session a `Set-Cookie`. Security endpoint je proto obslouzen jeste pred `Auth::start()`, zatimco ostatni public routy zachovavaji puvodni session tok.
- Aktivni HTTP smoke test potvrdil GET 200, HEAD bez tela, 308 redirect a spravny plain-text obsah. Redirect nyni explicitne vraci `text/plain`; `Last-Modified` se pocita z okamziku obnoveni cache v UTC, ne z lokalne interpretovaneho DB `CURRENT_TIMESTAMP`.
- Automaticky redirect test byl rozsiren o explicitni kontrolu `text/plain; charset=utf-8`, aby se PHP default `text/html` nemohl vratit bez regresniho selhani.
- Conditional HTTP smoke test vratil 304, ale PHP doplnilo default `text/html`, kdyz responder Content-Type odebral. 304 nyni zachovava reprezentacni `text/plain` hlavicku a automaticky test ji hlida.
- Prihlasený Chrome audit overil admin route, CSRF, ctyri stavove karty, pridani a presun kontaktu, okamzite prehozeni poradi v nahledu, nulove konzolove chyby a `scrollWidth == clientWidth` pri 360 px. Form nebyl odeslan.
- Publish integrace docasne vytvorila `.well-known/security.txt` a 301 v `.htaccess`; po navratu DB do puvodniho disabled stavu byly soubor i redirect odstraneny. Lokalni DB opet obsahuje 0 configu a 0 kontaktu.
- README nyni popisuje ucel, admin cestu, povinna/volitelna pole, oba endpointy, expiraci, HTTPS/PGP omezeni, pravni disclaimer, curl overeni a prikaz automatickych testu.
- ARCHITECTURE nyni zachycuje casnou public dispatch vetev bez session, service/repository/cache hranice, publish synchronizaci, nove tabulky a MySQL DDL migracni pravidlo.
- Publish admin vypisuje security.txt mezi generovanymi technickymi vystupy.
- Projektova wiki zachycuje request dispatch bez session, DB/cache/publish hranice a MySQL DDL gotcha v samostatnem commitu `b7e8122`; wiki repozitar po commitu zustal cisty.
- `API.md` nebylo meneno, protoze security.txt je systemovy plain-text endpoint mimo JSON `/api/v1` kontrakt. `INSTRUCTIONS.md` zustava beze zmeny, protoze jeho DB-first/theme guardrails nejsou novou funkcionalitou dotceny.
- Finalni review odstranil tichy cache miss pri necitelnem/poskozenem JSON: cache nyni vyhazuje konkretni chybu, public service ji zaloguje a pouzije vyslovny cerstvy DB fallback. Kazdy payload ma petiminutovy refresh timestamp, takze se invalidita i expirace pravidelne znovu vyhodnoti.
- Automaticky test minimalniho dokumentu navic explicitne hlida absenci UTF-8 BOM a finalni LF.
- Repository uz neprevadi libovolnou DB chybu na stav `schema_missing`; pouze skutecne chybejici tabulky funkci vypnou, zatimco porucha DB zustane viditelna. Publish cleanup take vyhodi chybu, pokud nelze odstranit zastaraly security.txt soubor.
- Cache test nyni overuje, ze poskozeny JSON vyvola viditelnou chybu misto ticheho cache missu.

## Vysledek

SEO CMS ma novy DB-driven generator RFC 9116 security.txt se samostatnou append-only migraci, admin-only konfiguraci a audit logem, serverovou validaci, deterministickym UTF-8/LF vystupem, petiminutovou souborovou cache s viditelnym DB fallbackem, dynamickymi GET/HEAD/304/404 endpointy a trvalym legacy redirectem. Publish pipeline synchronizuje aktivni `.well-known/security.txt` a odstranuje zastarale vystupy pri vypnuti nebo invalidite. Dokumentace, publish UI a projektova wiki byly aktualizovany. Vsech 18 automatickych testu, PHP lint, opakovany migrator, aktivni i disabled HTTP smoke testy, admin desktop/mobilni audit a `git diff --check` prosly; lokalni DB i publish storage byly vraceny do puvodniho vypnuteho stavu bez testovacich dat.
