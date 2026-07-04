# UKOL: Oprava regresi Elegant White frontendu

Datum: 2026-07-04
Zadani: Opravit regresi verejneho webu po poslednich upravach grafiky a SEO, hlavne chybejici HP sekce, kontrast paticky, kontaktni blok, hero overlay a nacitani obrazku.

## Kroky

- [x] 1. Najit zdroj regresi v aktualnim HTML/CSS runtime
- [x] 2. Vratit chybejici HP sekce Blog/clanky, Cenik a Jak to probiha
- [x] 3. Opravit hero overlay tak, aby se nerozmazavaly CTA a obsah
- [x] 4. Opravit kontrast kontaktni sekce a paticek vcetne kontaktu
- [x] 5. Opravit nacitani obrazku na kartach a verejnych strankach
- [x] 6. Overit verejne routy lokalne a pripravit commit/push

## Poznamky

- Success signal: homepage znovu obsahuje sekce Blog/clanky, Cenik a Jak to probiha; hero tlacitka a obsah nejsou bile zastrene; kontakt ma citelne zlute pozadi/panel; paticky jsou citelne na vsech typech stran; kontakty v paticce maji spravnou textaci; obrazky se znovu nacitaji.
- Nalezeno: aktualni homepage HTML vubec neobsahovalo `#blog`, `#cenik` ani `#proces`; `theme.js` je navic umel skryvat tridou `ew-home-hidden`.
- Upraveno `public/index.php`: chybejici homepage sekce se doplnuji z fallback bloku a footer umi rozdelit/stylovat slepene telefonni hodnoty.
- Upraveno `PublishService.php`: stejne doplneni homepage sekci a cisteni telefonu se pouzije i pro publikovane HTML.
- Upraveno `Templates/elegant-white/assets/theme.js` a `theme.css`: `#blog`/`#cenik` se neschovavaji, hero iframe neni nulove pruhledny a kontakt/footer dostaly citelny kontrast.
- Upraveny deploy workflow exclude: `storage/media`, `storage/cache` a `storage/logs` zustavaji serverove adresare a FTP sync je nema mazat.

## Vysledek

Opraveny regresni chyby Elegant White: homepage runtime doplnuje chybejici sekce Cenik, Blog a Jak to probiha, hero video zustava viditelne, kontaktni blok ma zlute pozadi, paticky maji tmavy kontrast a ciste telefony. Deploy workflow uz necha `storage/media`, cache a logy mimo FTP sync, aby serverova media nemizela.
