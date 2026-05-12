# INSTRUCTIONS

1. Pouzivejte tyto soubory jako zdroj pracovnich instrukci a kontextu:
   - `ARCHITECTURE.md` jako architekturu projektu
   - `README.md` jako uvodni a provozni dokumentaci

2. Pracovni pravidla:
   - Nejprve si nactete relevantni kontext v repu.
   - Kdyz se dokumentace rozchazi s kodem, berte jako zdroj pravdy aktualni implementaci.
   - Zmeny delejte co nejmensi a bez zasahu do nesouvisejicich casti.
   - Pred upravou overujte dopad na admin, frontend, publish a media.
   - Po uprave delejte aspon zakladni kontrolu vystupu nebo syntaxe.

3. Aktualni stav projektu:
   - Projekt je `MySQL/MariaDB only`.
   - Verejny web neni jen ciste staticky export. Bezne stranky se renderuji z databaze a cast vystupu se stale publikuje do `storage/published`.
   - Portfolio listing a detail jsou generovane HTML soubory.
   - Portfolio media maji pouzivat root-relative cesty ze `storage_path`, ne skladani pres ulozenou domenu webu.
   - Jakakoliv zmena databazove struktury nebo obsahu v databazi musi jit pres novou migraci. Nikdy neupravuj existujici migraci zpetne a nikdy nedelat zmenu DB bez nove migrační verze.

4. Po dokonceni prace se AI vzdy zastavi a polozi si tyto otazky:
   - Je to, co udelala, spravne?
   - Bylo to zbytecne?
   - Lze to udelat lepe?

5. Pokud je odpoved na libovolnou z techto otazek `ano`, AI se k praci vrati a:
   - opravi ji spravne,
   - nebo ji prepracuje lepe,
   - nebo ji odstrani, pokud byla zbytecna.

6. Pokud neni jasne, jaky je lepsi postup, AI ma zvolit jiny rozumny zpusob nebo uzivateli strucne vysvetlit, proc je vhodne postupovat jinak.

7. Git commit zpravy piste tak, aby je AI pochopila na prvni precteni bez dohledavani kontextu:
   - `type(scope): co se zmenilo a proc`
   - `type` ma hned na prvni pohled rict, jestli jde o `feature`, `fix`, `refactor`, `docs`, `chore`, `test` nebo podobny typ zmeny.
   - `scope` ma byt kratka oblast zmeny, napr. `admin`, `blog`, `deploy`, `seo`.
   - Prvni cast zpravy ma shrnout samotnou zmenu.
   - Druha cast ma rict duvod nebo dopad, aby AI chapala ucel zmeny stejne jako clovek.
   - Kdyz je potreba vic kontextu, pridejte kratke body do tela commitu:
     - co presne se upravilo
     - proc to bylo potreba
     - jaky je dopad na chovani
   - Piste strucne, vecne a v pritomném case.
   - Priklady:
     - `fix(admin): add homepage preview button so editors can inspect pages without inline mode`
     - `feature(blog): switch featured image picker to media grid for faster selection`
     - `docs: refresh architecture notes so AI and humans read the same project state`
