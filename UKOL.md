# UKOL: Favicon na všech stránkách + SEO audit

Datum: 2026-06-30
Zadání: Doplnit chybějící favicon na všechny stránky/podstránky + zkontrolovat rozdíly v SEO hlavičkách.
Hotovo když: Favicon je na všech stránkách (centrálně), PHP lint OK, SEO rozdíly zreportovány.

## Kroky

- [x] Najít příčinu (speciální handlery vkládají do <head> jen <title>, ne renderHead)
- [x] Centrální injectFaviconLinks() v sanitizeFrontendHtml (idempotentní)
- [x] PHP lint
- [x] Commit
- [x] SEO audit — report rozdílů hlaviček

## Poznámky

- Příčina: /faq, /kontakt, /nase-prace (listing i detail), hodnocení — vlastní <head> přes
  str_replace('</head>', titulek) bez renderHead → chybí favicon + SEO meta.
- sanitizeFrontendHtml($html, $site) se volá pro KAŽDOU stránku → ideální centrální bod.
- Idempotentní: pokud <head> už má rel="icon" (běžné stránky přes renderHead), nepřidá nic.

## Výsledek

Favicon doplněn centrálně přes injectFaviconLinks() v sanitizeFrontendHtml — pokrývá všechny
dynamické stránky. Běžné stránky (renderHead) přeskočeny (idempotence).

SEO audit hlaviček (před fixem) — viz zpráva uživateli: běžné stránky mají plný renderHead
(description, canonical, OG, twitter, robots, JSON-LD), speciální handlery jen title (±desc).
Favicon nyní jednotný; ostatní SEO meta zůstávají nekonzistentní — návrh na centralizaci čeká
na rozhodnutí uživatele.
