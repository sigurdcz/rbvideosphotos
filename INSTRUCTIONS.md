# INSTRUCTIONS

Tento soubor je obecny pracovni navod pro vsechny AI asistenty i lidske vyvojare pracujici na projektu. Nepatri sem pravidla vazana na konkretni nastroj nebo konkretni AI.

## Dokumentacni zdroje

- `README.md` je uvodni a provozni dokumentace projektu.
- `ARCHITECTURE.md` popisuje technickou architekturu, request flow, databazove oblasti a systemove koncepty.
- Pri rozporu mezi dokumentaci a kodem je zdrojem pravdy aktualni implementace.
- Pri zmene routingu, renderingu, migracniho systemu, sablon, publish flow nebo medii aktualizujte odpovidajici dokumentaci.

## Pracovni postup

- Nejprve si nactete relevantni kontext v repozitari.
- Zmeny delejte co nejmensi a bez zasahu do nesouvisejicich casti.
- Pred upravou overte dopad na admin, frontend, publish, sablony a media.
- Po uprave provedte zakladni kontrolu syntaxe, vystupu nebo diffu.
- Pokud si vsimnete nesouvisejicich zmen v pracovnim stromu, nerevertujte je bez explicitniho souhlasu.

## Co nikdy nedelat

- Nepridavejte ORM, Laravel, Symfony ani npm build, pokud to uzivatel vyslovne neschvali.
- Necommitujte `.env`, credentials, produkcni media ani jine tajne soubory.
- Nepouzivejte hardcoded domenu ze `sites.domain` pro URL medii. Media na webu maji byt root-relative, napr. `/storage/media/2026/05/foto.webp`.
- Nepridavejte `/blog/` ani `/nase-prace/` prefix do slugu blogovych prispevku a portfolia.
- Nespoustejte `git push --force` na `master` bez explicitniho souhlasu uzivatele.
- Neodstranujte existujici bezpecnostni ochrany jako `realpath()` kontroly, CSRF, `requireAuth()` nebo `requireAdmin()`.
- Nepouzivejte `shell_exec`, `exec` nebo `system` s user inputem.

## Migrace

- Jakakoliv zmena databazove struktury nebo obsahu v databazi musi jit pres novou migraci.
- Stare migrace se nesmeji upravovat ani mazat. I mala oprava, bugfix nebo datova zmena musi vzniknout jako nova migrace.
- Nove migrace se cisluji vzdy podle nejvyssi uz existujici migrace a musi jit vzestupne po sobe.
- Nikdy nevytvarejte novou migraci s nizsim cislem, nez ma aktualni posledni migrace.
- Neskakejte zbytecne daleko dopredu v cislovani migraci.
- Migrace musi byt idempotentni, pokud je to technicky mozne.
- Migracni utilita si stav drzi v tabulce `schema_migrations`; nespolehejte na rucni mazani lock souboru.

## Seed a demo obsah

- Vychozi seed `database/seeds/seed.php` musi zustat genericky a bez konkretniho klienta.
- Klientsky nebo ukazkovy obsah patri do samostatne pojmenovaneho seedu, napr. `seed_photographer.php` nebo `demo_content.php`.
- Seed nesmi rusit schema. Pokud je potreba schema upravit, musi vzniknout nova migrace.
- Media generovana seedem maji projit stejnym media pipeline jako uzivatelsky upload, vcetne variant a metadat.

## Frontend a URL pravidla

- Interni odkazy preferujte root-relative, napr. `/blog`, `/cenik`, `/nase-prace`.
- Blog a portfolio detaily pouzivaji prime slugy bez prefixu.
- Absolutni URL pouzivejte hlavne pro canonical, sitemap a Open Graph vystupy.
- Pri uprave verejneho renderingu overte homepage, bezne CMS stranky, blog listing/detail a portfolio listing/detail.
- Frontend upravy navrhujte mobile-first: zakladni styly musi fungovat na mobilu a desktopove rozsirovani pride az pres `min-width` breakpointy.
- Nepridavejte ani neodstranujte sekce jen kvuli kompaktnejsimu vzhledu. Kompaktni layout resit primarne typografii, spacingem, gridem a responzivnim chovanim.

## Bezpecnostni checklist

- `readfile()` musi mit `realpath()` a kontrolu povoleneho prefixu.
- POST formulare musi overovat CSRF token.
- Admin routes musi volat `requireAuth()` nebo `requireAdmin()`.
- SQL dotazy musi pouzivat prepared statements (`prepare()` + `execute()`), ne interpolaci user inputu.
- Tajemstvi patri do `.env` nebo deploy secrets, nikdy do repozitare.

## Git a commit zpravy

- Pred commitem zkontrolujte `git status --short` a commitujte jen souvisejici zmeny.
- Commit zpravy piste tak, aby je AI i clovek pochopili bez dohledavani kontextu.
- Preferovany format je `type(scope): co se zmenilo a proc`.
- `type` ma rict druh zmeny, napr. `feature`, `fix`, `refactor`, `docs`, `chore`, `test`.
- `scope` ma byt kratka oblast zmeny, napr. `admin`, `blog`, `deploy`, `seo`, `themes`.
- Prvni cast zpravy shrnuje samotnou zmenu.
- Druha cast nebo telo commitu vysvetluje duvod nebo dopad.
- Pokud je potreba vice kontextu, pridejte kratke body do tela commitu: co se upravilo, proc to bylo potreba, jaky je dopad.
- Piste strucne, vecne a v pritomnem case.

Priklady:

```text
fix(admin): add homepage preview button so editors can inspect pages without inline mode
feature(blog): switch featured image picker to media grid for faster selection
docs(project): merge AI instructions into shared documentation so all assistants follow one source
```

## Zaver prace

Po dokonceni se zastavte a polozte si tyto otazky:

- Je vysledek spravny?
- Nebyla nektera zmena zbytecna?
- Lze to udelat jednoduseji nebo bezpecneji?

Pokud je odpoved ano, vratte se k praci a opravte ji, prepracujte ji, nebo odstrante zbytecnou cast.
