# UKOL: SEO obrazky bez rucniho ID

Datum: 2026-06-30
Zadani: Predelat OG a Twitter image pole v SEO nastaveni stranky tak, aby odpovidala beznemu nahravani a vyberu fotek v projektu misto rucniho zadavani ID media.

## Kroky

- [x] Najit aktualni SEO formular a existujici media workflow v adminu.
- [x] Upravit SEO formular na bezny picker/nahrani fotky pro OG a Twitter image.
- [x] Otestovat PHP syntax a zkontrolovat dotcene workflow.
- [x] Rozhodnout, jestli zmena vyzaduje upravu dokumentace, a pripadne ji dopsat.

## Poznamky

- Soucasny stav v `admin/views/seo/edit.php` pouziva `og_image_id` a `twitter_image_id` jako ciselna pole s rucnimi odkazy na media.
- Existujici vzory v projektu: media upload pres `admin/views/media/index.php` a asset picker modal v `admin/views/settings/index.php`.
- Cil: zachovat ukladani `*_image_id` do SEO tabulky, ale odstranit potrebu rucne hledat a zadavat ID.
- `SeoController` ted natahuje media pres `allBySiteWithVariants()`, aby mel SEO formular k dispozici thumb nahledy.
- `MediaController::upload()` vraci i `storage_path` a `thumb_path`, takze novy obrazek lze po uploadu rovnou vybrat bez reloadu.
- Dokumentaci jsem nemenil; jde o lokalni admin UX zmenu bez dopadu na architekturu, API nebo runtime chovani webu.

## Vysledek

Hotovo:
- OG a Twitter image pole v SEO nastaveni uz nepouzivaji rucni zadavani media ID.
- Formular zobrazuje nahled, aktualni vybrane medium, tlacitka pro vyber z knihovny, upload z pocitace a odebrani.
- Upload pouziva stavajici `/admin/media/upload` endpoint a po uspesnem nahrani obrazek hned vybere do daneho SEO pole.
- Overeno pres `php -l` na `admin/views/seo/edit.php`, `app/Controllers/SeoController.php` a `app/Controllers/MediaController.php`.
