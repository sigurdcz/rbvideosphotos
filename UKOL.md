# UKOL: Audit projektovych znalostnich Markdownu

Datum: 2026-07-11
Zadani: Overit vsechny projektove `.md` soubory proti aktualnimu kodu, konfiguraci, databazovemu schematu a runtime cestam a opravit nepresna nebo zastarala tvrzeni.

Hotovo kdyz: vsechny projektove Markdowny maji jasnou roli, jejich overitelna tvrzeni odpovidaji aktualnimu repozitari, odkazy a prikazy jsou platne a dokumentace si vzajemne neodporuje.

## Kroky

- [x] Zmapovat role a obsah vsech projektovych `.md` souboru
- [x] Overit dokumentovana tvrzeni proti aktualnimu kodu, routam, konfiguraci a databazi
- [x] Opravit nalezene nepresnosti a zaznamenat rozsah zmen
- [x] Provest kontrolu odkazu, prikazu, Markdown formatovani a finalni diff review

## Poznamky

- Audit zahrnuje `README.md`, `ARCHITECTURE.md`, `API.md`, `GTM_ADMIN.md`, `INSTRUCTIONS.md` a tento prubezny `UKOL.md`.
- Osobni HTML wiki v `C:\Wiki` neni soucasti zadani; kontroluji se znalostni Markdowny aktualniho projektu `seo-editor`.
- `README.md`: seznam media variant a PHP rozsireni byl srovnan s `MediaIngestionService`, `ImageVariantService`, GTM klientem a token cipherem.
- `ARCHITECTURE.md`: request flow nyni obsahuje POST kontaktniho formulare a presneji popisuje obecnou published-file vetev; media pipeline doplnuje `og` variantu a zavislost na GD.
- `API.md`: odstranena nepravdiva podpora SVG uploadu, doplneny aktualni video typy, `og` varianta a skutecne CORS omezeni na origin z `APP_URL`.
- `GTM_ADMIN.md`: opraveno tvrzeni o encryption key; kod prijima neprazdny secret a odvozuje z nej 32byte klic pres SHA-256. Doplneny OpenSSL a cURL zavislosti.
- `INSTRUCTIONS.md`: obsah byl overen proti migracnimu, DB-first, URL, security a git workflow; nebyla nalezena vecna nepresnost, proto zustal beze zmeny.
- Finalni kontrola prosla: `git diff --check` bez chyb a `php tests/run.php` vratil 18/18 uspesnych testu. Odkazy na projektove Markdowny a konkretni runtime soubory existuji; zbyvajici relativni nazvy jsou zamerne priklady souboru uvnitr temat nebo generovane verejne vystupy.

## Vysledek

Vsech sest projektovych Markdownu bylo zkontrolovano proti aktualnimu repozitari. Vecne opravy byly provedeny v `README.md`, `ARCHITECTURE.md`, `API.md` a `GTM_ADMIN.md`; `INSTRUCTIONS.md` zustal beze zmeny, protoze odpovida implementaci. Audit opravil media varianty a zavislosti, public request flow, podporovane upload formaty, CORS popis a skutecne zpracovani GTM encryption secretu. Kontroly diffu a vsech 18 automatickych testu prosly.
