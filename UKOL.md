# UKOL: Jemna bezova homepage a subpages opravy

Datum: 2026-06-30
Zadani: Opravit homepage a podstranky v aktivnim fotografickem motivu tak, aby hero a obsah pouzivaly spravne obrazky/video, fungovaly kliky ve sluzbach, byly bile hero texty a navbar zvyraznoval aktualni sekci/stranku.

## Kroky

- [x] Najit aktivni soubory a bugy souvisejici s homepage, subpages a navbar chovanim.
- [x] Upravit tema a runtime tak, aby homepage a podstranky mely spravne hero media, kontrast textu a funkcni interakce.
- [ ] Overit klikani, hero media a navbar aktivni stav na lokalnim webu.
- [x] Otestovat syntax dotcenych PHP souboru a zapsat vysledek.
- [x] Rozhodnout o dokumentaci a doplnit jen pokud je to vecna zmena chovani systemu.

## Poznamky

- Bugy smeruji hlavne do `Templates/jemna-bezova/assets/theme.js`, `Templates/jemna-bezova/assets/theme.css` a casti renderingu v `public/index.php`.
- `theme.js` ted umi rozbit sekci sluzeb tim, ze skryje puvodni grid a vlozi placeholder `[ Vase fotografie ]`.
- Subpage hero CSS obsahuje fallback gradient a zaroven explicitne skryva `.theme-hero-video`, coz muze byt primy duvod chybejiciho videa/obrazku v hero oblastech.
- Navbar aktivni stav je potreba resit podle aktualni URL, ne jen podle hover stylu.
- `HomepageContentService.php` ted generuje na `/sluzby` detail sekce pod gridem a stejne-page tlacitka prepina z `/sluzby` na kotvy `#sluzba-*`, aby se stranka jen nerefreshovala.
- Homepage portfolio thumby jsem prepnul z `thumb_path` na `medium_path`, aby grid na uvodu netahal zbytecne male nahledy.
- Hero prioritu jsem zmenil v `public/index.php` i `app/Services/PublishService.php`: homepage preferuje site default video pred pevnou fotkou, pokud je video nastavene.
- Lokalni PHP server na `127.0.0.1:8000` bezi, ale dynamicky root vraci 404 z aplikace; plne browser overeni nad zivou DB strankou je tim blokovane a nesmi se vydavat za hotove.
- PHP syntax overena: `public/index.php`, `app/Services/PublishService.php`, `app/Services/HomepageContentService.php`.
- Dokumentaci jsem nemenil; jde o konkretni theme/runtime opravy, ne o novou architekturu nebo API kontrakt.

## Vysledek

Hotovo s overovacim blokrem:
- Homepage hero ma preferovat video pred pevnou fotkou, pokud je nastavene defaultni hero video webu.
- Sekce `Nase sluzby` v jemne bezove uz neukazuje placeholder panel, ale realny obrazek z obsahu.
- `/sluzby` uz nemaji tlacitka vedouci na refresh te same stranky; stejne-page akce skacou na vygenerovane detaily sluzeb pod gridem.
- Homepage `Nase prace` pouziva kvalitnejsi `medium` nahledy.
- `/faq`, `/blog`, `/nase-prace`, `/kontakt` a dalsi subpage hero vrstvy znovu zobrazuji hero media a maji svetlejsi text v hlavicce.
- Navbar runtime umi pri nacteni zvyraznit aktualni podstranku podle URL a na homepage dal reaguje i na sekce.
