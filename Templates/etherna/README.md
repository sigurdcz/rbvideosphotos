# ETHERNA

`ETHERNA` je prazdne DB-first tema s podtitulem **Eterna — Luxury Wedding Photography & Film Template**.

Vychazi z predaneho multipage UI/UX prototypu v `C:\Users\sigur\Downloads\rb-videos-multipage\rb-videos-multipage`. Prototypovy designovy system je prenesen do layoutu, typografie, hero archetypu, karet, ceniku, detailu, formularu, FAQ a responzivni navigace. Neni vsak kopirovan klientsky obsah ani prototypove obrazky: tyto hodnoty prichazeji z CMS databaze.

Struktura:

- `layout.html` — sdilena header/footer kostra a placeholdery `{site_name}`, `{nav}`, `{page_content}`.
- `assets/theme.css` — globalni designovy system a mapovani DB-driven markup tríd.
- `assets/homepage.*` — filmovy homepage hero a editorialni sekce.
- `assets/inner-pages.css` — listingy, detaily a legalni CMS obsah.
- `template.json` — registrace theme a page-specific assetu.

Nezasahovat do core kvuli vzhledu. Pokud nejaka route neposkytuje DB data nebo markup potrebny pro konkretni prototypovy blok, musi se mezera pojmenovat a vyresit datovym kontraktem, nikoli hardcoded klientskym obsahem v tematu.
