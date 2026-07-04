# UKOL: SEO a accessibility audit verejneho webu

Datum: 2026-07-04
Zadani: Provest audit verejneho webu v `seo-editor` a udelat cilene upravy pro SEO, accessibility, agentic browsing, crawlery a stabilitu frontendu bez rozbiti designu.

## Kroky

- [x] 1. Zmapovat runtime vystupy a auditovat kriticke body
  Popis: Projit `public/index.php`, SEO renderery, publish pipeline a verejne routy, najit hlavni problemy v semantice, metadatach, formularech, schema datech, `llms.txt`, `robots.txt` a `sitemap.xml`.
  Hotovo kdyz: Existuje konkretni seznam problemu a mist v kodu, kde se maji opravy udelat.

- [x] 2. Upravit globalni runtime pro semantiku, accessibility a metadata
  Popis: Opravit sdileny frontend rendering tak, aby mel spolehlivejsi landmarky, programove nazvy, skip-link, focus states, bezpecne odkazy, favicon/meta injekci a stabilnejsi HTML pro crawlery.
  Hotovo kdyz: Verejne stranky dostanou lepsi semanticky a pristupny zaklad bez zbytecnych vizualnich regresi.

- [x] 3. Upravit specialni stranky a formulare
  Popis: Zkontrolovat blog, portfolio, FAQ, kontakt a dalsi specialni handlery, doplnit chybejici metadata, JSON-LD, popisy formulare a strojove citelne prvky.
  Hotovo kdyz: Kriticke verejne stranky splnuji pozadovane SEO/accessibility minimum a formular je srozumitelny i bez vizualniho kontextu.

- [x] 4. Zrevidovat publish vystupy pro crawlery a AI
  Popis: Overit a upravit generatory `llms.txt`, `robots.txt` a `sitemap.xml`, pripadne navrh WebMCP pouze dokumentacne, pokud v projektu nema realnou podporu.
  Hotovo kdyz: Publikovane discovery soubory odpovidaji realnym URL a neblokuji dulezity obsah.

- [x] 5. Overit zmeny a zapsat dopady do dokumentace
  Popis: Spustit dostupne kontroly, zkontrolovat diff, vyhodnotit relevantni `.md` dokumentaci a pripravit zaverecny checklist i rizika.
  Hotovo kdyz: Zmeny jsou overene v dostupnem rozsahu a finalni shrnuti pokryje audit, upravy i otevrene body k rucnimu testu.

## Poznamky

- V worktree uz existuji nesouvisejici rozdelane zmeny v `Templates/elegant-white/assets/theme.js`, `app/Services/DemoContentSeeder.php` a `app/Services/HomepageContentService.php`; nesmi se potichu prepsat ani revertovat.
- Audit se soustredi hlavne na verejny runtime `public/index.php`, SEO/render sluzby a publish pipeline, protoze odtud se generuje HTML i discovery soubory pro crawlery.
- Dosavadni nalezy: relativni canonical na homepage, specialni stranky bez sdileneho SEO headu, FAQ akordeon bez skutecnych button ovladacu, blog post title zdvojoval nazev webu, contact POST neoveroval CSRF a `llms.txt`/`sitemap.xml` mely zastaralou strukturu URL.
- Rozpracovane soubory tohoto ukolu: `app/Services/SeoRenderer.php`, `app/Services/SitemapGenerator.php`, `app/Services/LlmsTxtGenerator.php`, `public/index.php`, `public/assets/frontend-runtime.css`.
- Overeni: `php -l` probehl pro vsechny upravene PHP soubory; HTTP kontrola probehla pro `/`, `/faq`, `/kontakt`, `/blog`, jeden blog post, `llms.txt`, `sitemap.xml` a `robots.txt`.
- Dokumentace upravena v `README.md`, aby odpovidala nove strukture `llms.txt` a zahrnuti dynamickych rout do sitemap.

## Vysledek

Verejny web ma opravene canonical a social metadata, specialni stranky dostaly sdileny SEO head a JSON-LD, FAQ je ovladatelne pres skutecna tlacitka, kontaktnimu POST pribyla CSRF a explicitni validace, a discovery soubory byly zregenerovany do aktualni podoby.
