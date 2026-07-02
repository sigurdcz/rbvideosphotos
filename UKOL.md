# UKOL: Oprava homepage about visual media

Datum: 2026-07-03
Zadani: Najit proc se ulozeny obrazek `jb-about-visual__media` z administrace realne nepropisuje na homepage a zobrazuje se stale stejna fotka.

## Kroky

- [x] Zapsat aktualni zadani do UKOL.md
- [x] Projit ulozeni bloku, publish injekci a frontend pouziti obrazku
- [x] Opravit misto, kde se ulozeny obrazek ignoruje nebo prepisuje
- [x] Overit syntax a konkretni vystup homepage
- [-] Commit a push podle aktualniho kontextu

## Poznamky

- V pracovnim stromu jsou pred zacatkem starsi generovane zmeny `storage/published/*` a `public/assets/generated/*`; nebudou zahrnuty do teto opravy.
- Pricina: verejny DB render v `public/index.php` nevkladal `jb-about-visual__source`, tak si `Templates/jemna-bezova/assets/theme.js` bral fallback z prvni sluzby.
- Oprava: `public/index.php` vklada source pred theme JS; `PublishService` uz nevklada `data-media`, aby se source znovu neprepisoval media mapou.

## Vysledek

Oprava je pripravena: verejny render homepage uz pred spustenim theme JS vklada ulozeny `jb-about-visual__source`; staticky publish source uz nema `data-media`, aby nedoslo k nechtenemu premapovani. Overeno pres `php -l` pro `public/index.php` a `app/Services/PublishService.php`.
