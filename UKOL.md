# UKOL: Hero media cover, default background a parallax

Datum: 2026-07-04
Zadani: Opravit hero sekce tak, aby video/foto vyplnilo celou sirku a vysku hero, defaultni background obrazek z nastaveni se propsal i bez videa a vsechny hero sekce mely parallax efekt.

## Kroky

- [x] 1. Najit renderovani defaultniho hero obrazku, videa a CSS hero vrstev
- [x] 2. Opravit propsani defaultniho background obrazku bez videa
- [x] 3. Nastavit hero media cover pres celou plochu a parallax pro vsechny hero sekce
- [x] 4. Overit a commit/push

## Poznamky

- Success signal: hero media se roztahne pres celou hero sekci, nastaveny defaultni background obrazek se zobrazi i bez videa a hero sekce maji parallax vrstvu.
- Nalezeno: Elegant White CSS stale obsahovalo starsi `left: 42%` / `width: 58%` pro hero media, ktere prebilo full-bleed zamer.
- Opraveno: `default_hero_poster_url` se pouzije jako defaultni hero image bez videa; hero media je full-bleed a Elegant White JS nastavuje parallax promennou pro vsechny hero sekce.
- Oprava pokryva injektovanou `.theme-hero-video` i nativni `<video class="hero-video">`, ktere je videt v DOM na live screenshotu.

## Vysledek

Hero media se explicitne roztahuje pres celou hero plochu, defaultni poster z nastaveni funguje jako background bez videa a Elegant White hero sekce maji parallax posun pro video i foto vrstvy.
