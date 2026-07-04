# UKOL: Odstraneni bileho prechodu pres hero obsah

Datum: 2026-07-04
Zadani: Odstranit bily prechod/overlay z homepage hero casti tam, kde prekryva nadpis, textaci a tlacitka.

## Kroky

- [x] 1. Najit aktualni CSS pravidla pro hero overlay a video prechod
- [x] 2. Upravit overlay tak, aby neprekryval textovy sloupec a CTA
- [x] 3. Overit CSS/PHP diff a commit/push opravy

## Poznamky

- Success signal: hero nadpis, podnadpis a tlacitko nejsou sede/bile zastrene; jemny prechod muze zustat jen nad obrazovou/video casti.
- Upraven `Templates/elegant-white/assets/theme.css`: final override odstranuje bily wash z textoveho sloupce, zveda hero container nad dekorace a ponechava jen jemny prechod v media vrstve.

## Vysledek

Hero overlay uz nema bily linearni wash pres texty a CTA. Textovy container je nad dekoracemi a video prechod zustava jen jemne v media vrstve.
