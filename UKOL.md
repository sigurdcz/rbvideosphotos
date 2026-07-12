# UKOL: Deterministicky reset Armageddon a Demo data

Datum: 2026-07-12
Zadani: Upravit resetovaci pipeline tak, aby stejny commit a sekvence `Armageddon -> Demo data` vytvorily lokalne i online stejny vychozi datovy a renderovaci stav pro `bezova-lens-tale`.

## Kroky

- [x] Auditovat vsechny persistentni a generovane stavy, ktere mohou prezit Armageddon
- [x] Sjednotit a explicitne pojmenovat seed spousteny Armageddonem
- [x] Zajistit uplne vycisteni motivu, cache a generovanych vystupu
- [x] Zajistit explicitni aktivaci `bezova-lens-tale` v Demo data pipeline
- [x] Pridat deterministicky environment fingerprint a porovnani vysledku
- [x] Doplnit automaticke testy pro Armageddon a Demo data bez produkcni destrukce
- [x] Overit lokalni sekvenci, dokumentaci a vsechny regresni testy
- [x] Provest Wiki Reflection a uzavrit vysledek

## Poznamky

Primarni acceptance signal: po stejnem commitu a sekvenci `Armageddon -> Demo data` musi mit lokal a produkce stejny fingerprint sablon, stranek, obsahovych bloku, aktivniho motivu a demo medii. Hodnoty zavisle na prostredi, napriklad `APP_URL`, musi byt ve fingerprintu explicitne vynechane nebo oznacene, nikoli skryte tolerovane.

Armageddon je destruktivni. Produkce se pri implementaci ani automatickych testech nespousti; reset se overi izolovane nebo statickymi/integracnimi testy.

Pridan `SetupFilesystemResetService`: jednotne cisti media, publikovany web, runtime cache, generovane frontend assety a souborovy stav aktivniho motivu. Logy zustavaji zachovane, protoze render neovlivnuji a jsou potreba pro diagnostiku.

`SetupController` ted vola explicitne `runBaseSeed()` nad `database/seeds/seed.php`; zavadejici odkaz na `seed_photographer.php` byl odstranen. Diagnostika je pripravena pro zobrazeni deterministickeho demo fingerprintu.

`ThemeTemplateRegistry` jiz neskryva selhani pri zapisu nebo mazani aktivniho motivu. Stavovy soubor lze v testu izolovat vlastni cestou a chyba filesystemu zustane viditelna.

Demo pipeline pred zmenou dat overi existenci motivu `bezova-lens-tale`; pred publikaci jej zapise do DB i do souboroveho stavu. Chybejici motiv je explicitni chyba, ne tichy fallback.

Pridan `DemoStateFingerprintService` a CLI `php bin/demo-state-fingerprint.php --hash`. Fingerprint kanonizuje domenove URL a nahodne cesty medii na logicke nazvy, vynechava casy publikace, ale zahrnuje theme state, layout, sablony, stranky, bloky, SEO, demo obsah, vazby medii a SHA-256 originalu. Stejny hash se zobrazuje v administraci Setup.

Regresni sada byla rozsirena o izolovany filesystem reset, zapis/mazani theme state bez ticheho fallbacku, pritomnost vychoziho demo motivu a stabilitu/citlivost kanonickeho fingerprintu. Testy nepouzivaji produkcni databazi.

Prvni pokus o skutecny lokalni reset selhal na syntaxi jednorazoveho prikazu jeste pred DROP. Nasledne CLI seedy sice data prepsaly, ale zobrazily session warnings; tento beh se nepovazuje za dukaz shodne Armageddon sekvence.

Pridan sdileny `SetupEnvironmentService`, ktery je jedinou cestou pro filesystem reset, DROP, append-only migracni runner a zakladni seed. Admin Armageddon i lokalni `php bin/reset-demo-local.php --confirm-local` pouzivaji stejnou sluzbu; CLI navic tvrde odmita jiny DB host nez localhost.

Prvni beh sdilene pipeline uspesne aplikoval 21 migraci, aktivoval `bezova-lens-tale` a vratil fingerprint `1b73b03986b344c275571f2ccc2fa56ad34fb5e1f4ba5131c786e1be3b4ef089`. CLI odhalilo warning o dvojim `BASE_PATH`; seed bootstrap ted respektuje jiz definovanou konstantu. Historicke migrace zustaly beze zmeny.

Druhy uplny beh byl bez warningu, znovu aplikoval 21 migraci a vratil stejny fingerprint. README popisuje admin i lokalni postup, ARCHITECTURE sdilenou reset/fingerprint pipeline a INSTRUCTIONS zakazuji druhou kopii reset logiky i tichy fingerprint fallback.

HTTP kontrola: `http://127.0.0.1:8765/` vraci 200 a `theme-bezova-lens-tale`. Na `localhost:8000` bezi jina/stara instance bez motivu; pro toto overeni neni autoritativni.

Trackovane generovane assety a `storage/published` byly po testu vraceny bitove na HEAD; do zmen nepatri. Nahodne vytvorene publikovane adresare byly bezpecne odstraneny. `storage/active-theme-key.txt` je nove explicitne v `.gitignore`, aby se environment state nikdy neprenesl Git deployem.

Produkci i dev deploy workflow ted na rsync i FTPS vrstve vylucuji `storage/media`, `storage/published`, `storage/active-theme-key.txt` a `public/assets/generated`. Git tak uz nemuze prepsat runtime data nebo stare generovane assety; potrebne prazdne adresare se v artefaktu stale vytvareji.

End-to-end overeni pres skutecne lokalni admin POST formulare na routeru `127.0.0.1:8766`: Armageddon 200, Demo obsah 200, admin fingerprint se rovna CLI fingerprintu `1b73b03986b344c275571f2ccc2fa56ad34fb5e1f4ba5131c786e1be3b4ef089` a homepage obsahuje `theme-bezova-lens-tale`. Testovaci runtime assety byly po overeni odstraneny a nejsou staged.

## Vysledek

Sdilena Armageddon/Demo pipeline je implementovana a lokalne overena pres CLI i skutecne admin formulare. Dva uplne reset behy i admin diagnostika vratily stejny fingerprint `1b73b03986b344c275571f2ccc2fa56ad34fb5e1f4ba5131c786e1be3b4ef089`; homepage na spravnem routeru vraci HTTP 200 s aktivnim `bezova-lens-tale`.

Aktualizovany relevantni dokumenty: `README.md`, `ARCHITECTURE.md` a `INSTRUCTIONS.md`. API kontrakt se nemenil, proto `API.md` aktualizaci nepotreboval. Regresni sada prosla 22/22, vsechny zmenene PHP soubory prosly lintem, `git diff --check` je cisty a historicke migrace zustaly beze zmeny.

Wiki Reflection: domena `pipelines` zustava `ok`; dossier a hub byly aktualizovany a samostatne commitnuty v `C:\Wiki` jako `cc7af3f docs(seo editor): document deterministic demo reset`.

Zaverecny compatibility review odstranil zavislost fingerprintu na `array_is_list()` z PHP 8.1; vlastni list check zachovava deklarovanou kompatibilitu projektu s PHP 8.0+.
