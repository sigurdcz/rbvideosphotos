# INSTRUCTIONS

1. Používejte tyto soubory jako zdroj pracovních instrukcí a kontextu:
   - `ARCHITECTURE.md` jako architekturu projektu
   - `README.md` jako úvodní a provozní dokumentaci

2. Pracovní pravidla:
   - Nejprve si načítejte relevantní kontext v repu.
   - Když se dokumentace rozchází s kódem, berte jako zdroj pravdy aktuální implementaci.
   - Změny dělejte co nejmenší a bez zásahu do nesouvisejících částí.
   - Před úpravou ověřujte dopad na admin, frontend, publish a media.
   - Po úpravě dělejte aspoň základní kontrolu výstupu nebo syntaxe.

3. Aktuální stav projektu:
   - Projekt je `MySQL/MariaDB only`.
   - Veřejný web není jen čistě statický export. Běžné stránky se renderují z databáze a část výstupu se stále publikuje do `storage/published`.
   - Portfolio listing a detail jsou generované HTML soubory.
   - Portfolio media mají používat root-relative cesty ze `storage_path`, ne skladání přes uloženou doménu webu.

4. Po dokončení práce se AI vždy zastaví a položí si tyto otázky:
   - Je to, co udělala, správné?
   - Bylo to zbytečné?
   - Lze to udělat lépe?

5. Pokud je odpověď na libovolnou z těchto otázek `ano`, AI se k práci vrátí a:
   - opraví ji správně,
   - nebo ji přepracuje lépe,
   - nebo ji odstraní, pokud byla zbytečná.

6. Pokud není jasné, jaký je lepší postup, AI má zvolit jiný rozumný způsob nebo uživateli stručně vysvětlit, proč je vhodné postupovat jinak.

7. Git commit zprávy pište v této struktuře:
   - `type(scope): stručné shrnutí`
   - `type` má hned na první pohled říct, jestli jde o `feature`, `fix`, `refactor`, `docs`, `chore`, `test` nebo podobný typ změny.
   - `scope` má být krátká oblast změny, například `admin`, `blog`, `deploy`, `seo`.
   - Shrnutí má být stručné, věcné a v přítomném čase.
   - Když je to možné, napište commit tak, aby první přečtení okamžitě řeklo, co se změnilo.
   - Příklady:
     - `fix(admin): add homepage preview button`
     - `feature(blog): switch featured image picker to media grid`
     - `docs: refresh architecture and workflow notes`
