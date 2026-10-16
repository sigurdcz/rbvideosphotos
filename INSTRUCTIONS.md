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
- Referencni Demo obsah musi explicitne nastavit vychozi `Golden Bash Diagonal` (`golden-bash-diagonal`, verze `1.0.0`) do DB i `storage/active-theme-key.txt`.
- Hlavni seed musi pro INSERT i UPDATE nastavit `cookie_consent_enabled=0`; analyticke a marketingove kategorie tim nevypinejte.
- Hlavni i Demo seed musi nastavit `default_hero_poster_url` na konkretni existujici zaznam demo media knihovny; chybejici povinny hero soubor musi seed viditelne zastavit.
- Paritu lokal/online overujte pres `DemoStateFingerprintService`; rozdil fingerprintu je viditelna chyba, ne povoleny fallback.

## Frontend a URL pravidla

- Interni odkazy preferujte root-relative, napr. `/blog`, `/cenik`, `/nase-prace`.
- Blog a portfolio detaily pouzivaji prime slugy bez prefixu.
- Absolutni URL pouzivejte hlavne pro canonical, sitemap a Open Graph vystupy.
- Pri uprave verejneho renderingu overte homepage, bezne CMS stranky, blog listing/detail a portfolio listing/detail.
- Pri vizualni uprave aktivni sablony overte screenshot homepage na viewportu alespon 1280 CSS px. Desktopove korekce patri do aktivni theme CSS vrstvy; nepouzivejte nedostupne podminky typu `@media (max-width: 0px)` jako docasne vypnuti hotoveho layoutu.
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

- Dalsi vyvoj a opravy provadejte na `dev` (nebo pracovni `codex/*` vetvi sloucene do `dev`). Push `dev` nasazuje pouze staging; samostatna vetev `staging` se nepouziva.
- `master` je produkce. Merge, cherry-pick ani primy push nove prace do `master` neprovadejte bez vyslovneho souhlasu uzivatele pro konkretni zmeny. Souhlas se staging pushem neni souhlas s produkcnim nasazenim; automaticky merge je zakazany.
- Pred zadosti o produkcni nasazeni overte staging, migrace a relevantni testy; uvedte revizi, vysledek kontrol a pripadne mezery. Vyjimky musi uzivatel vyslovne schvalit.
- Odlozene zavady a navazujici praci zapisujte do `BACKLOG.md` s kriterii dokonceni. Neopravujte je potichu v nesouvisejicim ukolu.
- Podrobnosti prostredi, povinne secrets a postup prvni instalace stagingu jsou v `STAGING.md`. Tato pravidla jsou projektova politika; GitHub branch protection je samostatne nastaveni a nenahrazuje souhlas uzivatele.

- Pred commitem zkontrolujte `git status --short` a commitujte jen souvisejici zmeny.
- Rebuild/regenerovane zmeny (napr. `tests/goldens/**` po `--update-goldens`, prekompilovane assety, katalogy generovane skriptem) commitujte **oddelene** od zdrojove zmeny, ktera je vyvolala. Duvod: recenzent (clovek i AI) potrebuje v jednom commitu videt bud logiku/obsah, nebo jeji mechanicky odvozeny vystup - ne oboje smichane, protoze pak nejde poznat, jestli se zmenila jen forma, nebo i chovani.
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

## Sdilene obsahove kontrakty

- Opakovany marketingovy obsah nekopiruj do route views. Rozsir `shared_sections` nebo existujici centralni repository (FAQ/reviews).
- Hero, CTA, nadpisy, popisy a obrazky patri do `content_blocks`, `sites` nebo domenove tabulky. Theme smi natvrdo drzet technicke/taxonomicke popisky a prezentacni markup.
- Portfolio listing musi respektovat `content_type`, `sort_order ASC, id ASC` a oddelene foto/video skupiny v live i publish ceste.
