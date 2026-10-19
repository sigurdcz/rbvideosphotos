# Brand fonts

Cormorant Garamond italic v4.001 je skutečný statický řez Regular 400, odvozený z oficiálního proměnného fontu. Staré italic soubory měly interně Light outlines, přestože CSS deklarovalo 400. Nový soubor obsahuje celou Unicode mapu (979 znaků), takže české a základní latinské znaky používají tentýž řez. Původní soubory se nepřepisují: hosting cache fontů je immutable.

Zdroj: [Google Fonts Cormorant Garamond](https://github.com/google/fonts/tree/main/ofl/cormorantgaramond), soubor `CormorantGaramond-Italic[wght].ttf`, revize fontu 4.001. Licence: `OFL-cormorantgaramond.txt`.

SHA-256 zdrojového TTF: `0f48ea6abb2084537854f7174c470991a463b13036309e3b50a81511611c530d`.

Reprodukce (jen vývoj, hosting Python nepotřebuje):

```powershell
python -m pip install 'fonttools[woff]==4.65.0'
python scripts/build_cormorant_italic.py downloaded-upstream.ttf
```

CSS používá `font-display: swap`, aby se pozdě načtený řez skutečně uplatnil. `optional` může systémový font ponechat po celou návštěvu. Oba upright subsety a kompletní kurzíva jsou preloaded; swap může při pomalém načtení změnit rozměry textu, proto ověřovat geometrii a posuny layoutu. Nepotlačovat chyby stažení ani tvrdit, že samotná přítomnost souboru dokazuje jeho použití: v Chrome ověřit `CSS.getPlatformFontsForNode` a screenshot.
