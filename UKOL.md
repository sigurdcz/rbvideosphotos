# UKOL: Opravit homepage hero a mobilni mezeru

Datum: 2026-07-01
Zadani: Opravit homepage hero video a odstranit velkou bilou mezeru nad sekci `Nase sluzby` na mobilu.

## Kroky

- [x] Najit CSS vrstvy pro `hp-hero`, `theme-hero-video`, overlay a obsah hero sekce.
- [x] Upravit rozmer videa, iframe cover a z-index/opacity textu v aktivni sablone.
- [x] Najit zdroj velke mobilni mezery v aktualni sablone `jemna-bezova`.
- [x] Odstranit prazdnou mobilni vysku ve `jb-about-shell`.
- [x] Overit syntaxi/diff bez sahani na lokalni DB.
- [x] Zhodnotit dokumentaci a zapsat vysledek.

## Poznamky

- Screenshot ukazuje, ze video je jen uprostred hero sekce a text/tlacitka pusobi pod tmavou clonou.
- `public/index.php` a `PublishService` vkladaji obecne styly pro `.theme-hero-video`, ale `Templates/editorial-gold/assets/theme.css` je pozdeji prebiji opacity/filter/overlay pravidly.
- `theme.css` ma finalni pravidla pro homepage: container je staticky, video se absolutne vztahuje k cele `hp-hero`, iframe cover vyplni 16:9 plochu a text/tlacitka jsou nad overlayem.
- Aktualni screenshot je `theme-jemna-bezova`; mezera je vyska `.jb-about-shell` / `.jb-about-visual`, ne `editorial-gold`.
- `Templates/jemna-bezova/assets/theme.css` ma finalni mobilni override na konci souboru: about vizual se na mobilu nevykresli a nedrzi prazdnou vysku.
- Overeno pres `git diff --check`. DB se nepouzivala.
- Dokumentaci neni potreba menit: jde o vizualni CSS opravu existujicich sablon.

## Vysledek

Homepage hero video vyplnuje celou hlavicku a text/tlacitka jsou nad overlayem. V aktualni `jemna-bezova` sablone uz mobilni `jb-about-visual` nedrzi prázdnou bilou vysku nad sekci `Nase sluzby`.
