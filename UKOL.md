# UKOL: Uklid necommitnutych souboru

Datum: 2026-07-15
Zadani: Projit cely dirty working tree, rozhodnout podle zdrojove hodnoty ktere zmeny commitnout a ktere odstranit, vytvorit logicke commity a pushnout je na `origin/master`.

## Kroky

- [x] Inventarizovat diffy, puvod, velikost a roli vsech zmenenych a untracked souboru.
- [x] Overit a commitnout hodnotne zdrojove zmeny po logickych celcich.
- [x] Odstranit nebo obnovit reprodukovatelne generovane a docasne artefakty.
- [ ] Aktualizovat ledger a commitnout rozhodnuti o uklidu.
- [ ] Pushnout `master` a overit cisty strom i shodu s `origin/master`.

## Poznamky

- Vychozi stav obsahuje rozpracovane zmeny motivu `bezova-lens-tale`, generovany `storage/published`, screenshot workflow, referencni i prubezne obrazky a pomocny PowerShell skript.
- Zadani explicitne opravnuje rozhodnout o commitu nebo odstraneni; zadny soubor nebude klasifikovan bez kontroly diffu, obsahu nebo role.
- Zachovany zdroj: odstraneni jiz zakazane storyline z HTML/JS, screenshot workflow, capture skript a dve autoritativni `preview-*-reviews.png` predlohy.
- Odstraneny experiment: pevne skalovany 641-980px CSS artboard, ktery se neuplatnil na skutecnem 390px mobilnim viewportu a prepisoval hotove desktopove badge/sipky.
- Obnoveny generovane `storage/published/*`; odstraneny neplatne 377px JPEGy, duplicitni i ostatni progress PNG. Budouci lokalni QA captures jsou ignorovane pres `.gitignore`.
- Commit `b25d023` odstranil mrtvou storyline vrstvu z layoutu a theme JS; overeno 27/27 testy a runtime HTTP 200 bez storyline markupu.
- Samostatny JS parser nebyl dostupny (`node`, `bun` ani `deno` nejsou nainstalovane); tato mezera je viditelne uvedena v commit message a nebyla nahrazena tvrzenim o provedene kontrole.

## Vysledek

Rozpracovano.
