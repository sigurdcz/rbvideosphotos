# INSTRUCTIONS

Tento soubor je obecny pracovni navod pro vsechny AI asistenty i lidske vyvojare pracujici na projektu. Nepatri sem pravidla zavisla na konkretnim nastroji.

## Dokumentacni role

- `README.md` je onboarding a provozni rozcestnik.
- `ARCHITECTURE.md` je technicky zdroj pravdy pro request flow, datovy model a systemove chovani.
- `API.md` je zdroj pravdy pro verejne REST API kontrakty.
- `GTM_ADMIN.md` je provozni navod pro `/admin/gtm`.
- Pri rozporu mezi dokumentaci a kodem je zdrojem pravdy aktualni implementace.
- Pri zmene routingu, renderingu, migracniho systemu, sablon, GTM workflow, API nebo media pipeline aktualizujte odpovidajici dokumentaci.
- Sablony a theme assety nesmi obsahovat klientsky obsah. Obsahova data patri do DB a sablony maji pouzivat jen placeholdery a DB-driven renderovane bloky.

## Pracovni postup

- Nejprve si nactete relevantni kontext v repozitari.
- Zmeny delejte co nejmensi a bez zasahu do nesouvisejicich casti.
- Pred upravou overte dopad na admin, frontend, publish, sablony, API a media.
- Po uprave provedte zakladni kontrolu syntaxe, vystupu nebo diffu.
- Pokud si vsimnete nesouvisejicich zmen v pracovnim stromu, nerevertujte je bez explicitniho souhlasu.

## Co nikdy nedelat

- Nepridavejte ORM, Laravel, Symfony ani npm build, pokud to uzivatel vyslovne neschvali.
- Necommitujte `.env`, credentials, produkcni media ani jine tajne soubory.
- Nepouzivejte hardcoded domenu ze `sites.domain` pro URL medii. Verejne URL medii maji byt root-relative pres `/media/...`; DB `storage_path` zustava interni `storage/media/...`.
- Nepridavejte `/blog/` ani `/nase-prace/` prefix do slugu blogovych prispevku a portfolia.
- Nespoustejte `git push --force` na `master` bez explicitniho souhlasu uzivatele.
- Neodstranujte existujici bezpecnostni ochrany jako `realpath()` kontroly, CSRF, `requireAuth()` nebo `requireAdmin()`.
- Nepouzivejte `shell_exec`, `exec` nebo `system` s user inputem.

## Migrace

- Jakakoliv zmena databazove struktury nebo obsahu v databazi musi jit pres novou migraci.
- Stare migrace se nesmeji upravovat ani mazat.
- Nove migrace se cisluji podle nejvyssi existujici migrace a musi jit vzestupne po sobe.
- Neskakejte zbytecne dopredu v cislovani.
- Migrace musi byt idempotentni, pokud je to technicky mozne.
- Pred vytvorenim nove migrace zkontrolujte `database/migrations/` a `database/schema.sql`, aby zmena nebyla duplicitni.
- Pokud je zmena uz obsazena v aktualnim schematu nebo starsi migraci, nevytvarejte dalsi duplicitni migraci.
- Stav migraci drzi `schema_migrations`; nespolehejte na rucni mazani lock souboru.

## Seed a demo obsah

- Vychozi seed `database/seeds/seed.php` musi zustat genericky a bez konkretniho klienta.
- Klientsky nebo ukazkovy obsah patri do samostatne pojmenovaneho seedu nebo seeder sluzby; aktualni referencni obsah spravuje `DemoContentSeeder`, nikoli zavadejici `$argv` alias.
- Seed nesmi menit schema. Pokud je potreba schema upravit, musi vzniknout nova migrace.
- Media generovana seedem maji projit stejnou media pipeline jako uzivatelsky upload.
- Admin Armagedon a lokalni reset musi pouzivat stejnou `SetupEnvironmentService`; druhou kopii DROP/migration/seed poradi nevytvarejte.
- Referencni Demo obsah musi explicitne nastavit `bezova-lens-tale` do DB i `storage/active-theme-key.txt`.
- Paritu lokal/online overujte pres `DemoStateFingerprintService`; rozdil fingerprintu je viditelna chyba, ne povoleny fallback.

## Frontend a URL pravidla

- Interni odkazy preferujte root-relative, napr. `/blog`, `/cenik`, `/nase-prace`.
- Blog a portfolio detaily pouzivaji prime slugy bez prefixu.
- Absolutni URL pouzivejte hlavne pro canonical, sitemap a Open Graph vystupy.
- Pri uprave verejneho renderingu overte homepage, bezne CMS stranky, blog listing/detail a portfolio listing/detail.
- Frontend upravy navrhujte mobile-first.
- Nepridavejte ani neodstranujte sekce jen kvuli kompaktnejsimu vzhledu; upravujte typografii, spacing a grid.
- Nevkladejte velke inline `<style>` bloky z PHP generatoru obsahu. Sdilene styly patri do theme/shared CSS vrstvy.

## Bezpecnostni checklist

- `readfile()` musi mit `realpath()` a kontrolu povoleneho prefixu.
- POST formulare musi overovat CSRF token.
- Admin routes musi volat `requireAuth()` nebo `requireAdmin()`.
- SQL dotazy musi pouzivat prepared statements.
- Tajemstvi patri do `.env` nebo deploy secrets, nikdy do repozitare.

## Git a commit zpravy

- Pred commitem zkontrolujte `git status --short` a commitujte jen souvisejici zmeny.
- Commit zpravy piste tak, aby je AI i clovek pochopili bez dohledavani kontextu.
- Preferovany format je `type(scope): co se zmenilo a proc`.
- `type` ma rict druh zmeny, napr. `feature`, `fix`, `refactor`, `docs`, `chore`, `test`.
- `scope` ma byt kratka oblast zmeny, napr. `admin`, `blog`, `deploy`, `seo`, `themes`.
- Nepouzivejte `Co-Authored-By:` ani jine AI atributy.

Priklady:

```text
fix(admin): add homepage preview button so editors can inspect pages without inline mode
feature(blog): switch featured image picker to media grid for faster selection
docs(project): split API and GTM documentation out of README to remove duplication
```

## Zaver prace

Po dokonceni se zastavte a polozte si tyto otazky:

- Je vysledek spravny?
- Nebyla nektera zmena zbytecna?
- Lze to udelat jednoduseji nebo bezpecneji?

Pokud je odpoved ano, vratte se k praci a upravte vysledek.
