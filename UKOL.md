# UKOL: Svatební ceník do photographer seederu

Datum: 2026-06-29
Zadání: Přidat 4 svatební balíčky + 3 krátké homepage karty do seed_photographer.php.
Hotovo když: Seeder obsahuje nové pricing bloky, PHP lint čistý, commit.

## Kroky

- [x] Přidat pricing_packages div do Ceník template ($htmlCenik)
- [x] Definovat $pricingPackages HTML (4 balíčky, self-contained style)
- [x] block() pro pricing_packages na Ceník stránce
- [x] Update pricing_weddings_heading/sub (svatební balíčky intro)
- [x] Přidat cenik_card_1/2/3 (title/desc/price) pro homepage karty (přepsány existující)
- [x] PHP lint
- [x] Commit

## Poznámky

- Homepage karty: HomepageContentService::buildPricingHtml čte cenik_card_N_title/desc z 'cenik' stránky (3 karty, i=1..3) — ukazuje title + první řádek desc (bez ceny, limitace služby).
- Detailní balíčky: self-contained HTML blok (style scoped ckp-) — robustní napříč tématy, s cenou + features.
- Standardní = featured. 4. balíček (Foto+video) jen v detailu (homepage karty jsou 3).

## Výsledek

Photographer seeder nyní obsahuje nový svatební ceník:
- Ceník stránka: blok `pricing_packages` se 4 detailními balíčky (Základní 14 900, Standardní 24 900 featured,
  Prémiová 34 900, Foto+video 44 900) — cena, popis, „Obsahuje" seznam. Self-contained styl (.ckp-).
- Homepage teaser: cenik_card_1/2/3 přepsány na 3 krátké karty (Základní/Standardní/Prémiový) s archivací.
- pricing_weddings_heading/sub aktualizovány na svatební balíčky intro.
PHP lint OK.
