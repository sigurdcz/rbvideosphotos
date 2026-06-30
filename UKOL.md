# UKOL: Zkompaktnit mobilni vypisy

Datum: 2026-06-30
Zadani: Na mobilu zmensit roztahane vypisy a nastavit `Nase prace` a `Blog` jako kompaktni 2x2 galerii misto velkych jednosloupcovych panelu.

## Kroky

- [x] Najit CSS a markup pro mobilni vypisy `Nase prace`, `Blog`, `FAQ` a `Cenik`.
- [x] Upravit mobilni gridy pro `Nase prace` a `Blog` na kompaktni dvousloupcove nahledy.
- [x] Zmensit mobilni spacing, padding, marginy a typografii u `FAQ` a `Cenik`.
- [x] Overit syntaxi a zkontrolovat diff bez sahani na lokalni DB.
- [x] Zhodnotit dokumentaci a zapsat vysledek.

## Poznamky

- Uživatel chce na mobilu mensi rozhrani, ne odstraneni sekci. DB se nema pouzivat.
- `public/index.php` ma mobilni fallback pro portfolio/blog ve dvou sloupcich s mensimi nahledy a skrytymi sekundarnimi texty.
- `Templates/editorial-gold/assets/theme.css` ma finalni mobilni override, ktery prebije starsi `1fr` pravidla a zmensuje FAQ/Cenik.
- `database/seeds/seed.php` ma stejne kompaktni mobilni styly pro nove zalozeny demo obsah `/cenik` a `/faq`.
- Overeno pres `php -l public/index.php` a `php -l database/seeds/seed.php`.
- Dokumentaci neni potreba menit: jde o vizualni responzivni upravu existujicich sablon, ne o zmenu routingu, workflow nebo API.

## Vysledek

Mobilni vypisy `Nase prace` a `Blog` jsou nastavene jako kompaktni dvousloupcove galerie. `FAQ` a `Cenik` maji na mobilu mensi paddingy, mezery a pismo v runtime CSS i demo seed sablonach.
