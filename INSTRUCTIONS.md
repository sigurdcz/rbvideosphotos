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

7. Git commit zprávy pište tak, aby je AI pochopila na první přečtení bez dohledávání kontextu:
   - `type(scope): co se změnilo a proč`
   - `type` má hned na první pohled říct, jestli jde o `feature`, `fix`, `refactor`, `docs`, `chore`, `test` nebo podobný typ změny.
   - `scope` má být krátká oblast změny, například `admin`, `blog`, `deploy`, `seo`.
   - První část zprávy má shrnout samotnou změnu.
   - Druhá část má říct důvod nebo dopad, aby AI chápala účel změny stejně jako člověk.
   - Když je potřeba víc kontextu, přidejte krátké body do těla commitu:
     - co přesně se upravilo
     - proč to bylo potřeba
     - jaký je dopad na chování
   - Pište stručně, věcně a v přítomném čase.
   - Příklady:
     - `fix(admin): add homepage preview button so editors can inspect pages without inline mode`
     - `feature(blog): switch featured image picker to media grid for faster selection`
     - `docs: refresh architecture notes so AI and humans read the same project state`
