# UKOL: Elegant White data driven audit

Datum: 2026-07-04
Zadani: Zrevidovat sablonu `Elegant White` a odstranit obsahove hardcody tak, aby fotky, texty a bloky vychazely z DB/demo seederu misto z JS nebo statickeho HTML.

## Cil

`Elegant White` smi v JS/CSS resit strukturu, rozlozeni a styl, ale nesmi si vymyslet obsah. Texty, fotky, sluzby, reference, FAQ, kontakt a proces musi prichazet z runtime DOMu vygenerovaneho z databaze, pripadne byt zalozene demo seederem jako normalni obsah.

## Kroky

- [x] 1. Najit obsahove hardcody v `Elegant White`
  Popis: Projit JS/HTML sablony a identifikovat staticke texty, obrazky a fallbacky, ktere obchazi DB.
  Vysledek: `theme.js` obsahoval hardcoded hero, navigacni CTA, about texty, fallback sluzby, FAQ, proces a `/media/2026/07/kontakt.png`.

- [x] 2. Zkontrolovat dostupna DB/demo data
  Popis: Projit demo seeder, runtime renderery a existujici bloky/sluzby/FAQ/reference, aby bylo jasne, odkud se obsah ma brat.
  Vysledek: Sluzby, portfolio, FAQ, reference a kontakt uz maji DB zdroje. Pro Elegant White chybely explicitni homepage bloky pro proces a kontaktni vizual.

- [x] 3. Upravit `Elegant White` na datove rizene transformace
  Popis: Odstranit hardcoded texty/fotky/fallback obsah z JS a nechat JS pouze prekladat existujici DOM/data atributy do cilove kompozice.
  Vysledek: JS uz neprepisuje obsahove texty ani nevklada staticke obrazky. Pokud data chybi, sekci nevymysli skrytym fallbackem.

- [x] 4. Doplnit demo seeder, pokud chybi obsah
  Popis: Presunout potrebne vychozi textace/media do demo obsahu misto do sablonoveho JS.
  Vysledek: Demo seeder zaklada `process_*` bloky a `contact_visual` image blok a doplni pro ne placeholdery do homepage template.

- [x] 5. Otestovat diff a dostupne kontroly
  Popis: Spustit dostupne kontroly a overit, ze nezustaly staticke obsahove fallbacky.
  Vysledek: PHP lint pro upravene sluzby prosel, `git diff --check` prosel. `node --check` neslo spustit, protoze v prostredi neni `node`.

## Poznamky

- Web se podle zadani nema brat jako lokalne provozovana instance, proto nebylo delano runtime/browser overeni.
- Dodrzeno pravidlo bez tichych fallbacku: chybejici obsah se nema zamaskovat smyslenymi texty nebo obrazky v JS.

## Vysledek

Elegant White je prepracovana na datove rizeny frontend: obsah bere z DB renderu nebo z demo seederu, ne z hardcodu v sablonovem JS.
