# UKOL: Opravit header obrazky a weare2

Datum: 2026-06-30
Zadani: Opravit prirazeni header obrazku pro sekce, uklidit nahrane obrazky do spravne slozky a zajistit, aby HP sekce `Co nabizime` pouzivala `weare2.png`.

## Kroky

- [x] Zjistit aktualni stav obrazku, media zaznamu, header bloku a service image.
- [x] Uklidit obrazky ze slozky `dalsi data` / `data dalsi` do prislusne projektove slozky.
- [x] Opravit prirazeni header obrazku pro stranky a specialni routy.
- [x] Opravit vyber fotky v HP sekci `Co nabizime` na `weare2.png`.
- [x] Overit staticky cesty a PHP syntaxi bez dalsich DB zapisu.
- [x] Zhodnotit dokumentaci a zapsat vysledek.

## Poznamky

- Screenshot ukazuje, ze `/sluzby` stale bere puvodni header obrazek.
- Screenshot ukazuje, ze HP `jb-about-visual__media` stale bere puvodni medium variantu misto `weare2.png`.
- Lokalni DB uz mela `weare2.png` u prvni sluzby, ale seed logika porad nahravala jen `.webp` demo assety a mapovala headery na stare soubory.
- Obrazky byly presunuty z `data dalsi` do `database/seeds/demo_assets`.
- `DemoContentSeeder` ted bere i PNG/JPG assety, mapuje headery na nove PNG soubory a SEO obrazky hlavnim strankam prepise na stejne nove soubory.
- Po upozorneni uz neprobiha zadne dalsi sahani na lokalni DB; overeni je jen pres `php -l` a kontrolu souboru.
- `database/seeds/seed.php` ted nahrava stejne demo assety pres `MediaIngestionService`, zaklada `hero_bg` bloky pro hlavni stranky a dava `weare2.png` do prvni sluzby.

## Vysledek

Hotovo v seed/demo souborech. Dokumentace se nemenila, protoze jde o obsah seedu a ne zmenu verejneho workflow.
