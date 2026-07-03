# UKOL: Media WebP a kontakt checkbox

Datum: 2026-07-03
Zadani: Zajistit, aby homepage about fotografie pouzivala zmenseny WebP vystup do cca 100 kB, a opravit checkbox souhlasu na kontaktni strance na jednu linku se stylovanym vzhledem.

## Kroky

- [x] Zapsat aktualni zadani do UKOL.md
- [x] Najit media URL transformaci pro homepage about vizual
- [x] Upravit vystup tak, aby verejny web pouzival WebP/medium variantu misto original PNG
- [x] Opravit CSS/markup checkboxu na kontaktni strance
- [x] Overit syntax a relevantni vystupy
- [x] Commit a push

## Poznamky

- Akceptace: homepage obrazek nesmi tahat original PNG kolem 2 MB; ma pouzit WebP optimalizovanou variantu.
- Akceptace: kontaktni checkbox a text souhlasu jsou na jedne lince, checkbox neni defaultni/nepekny.
- `HomepageContentService::resolveOptimizedMediaUrl()` je novy spolecny helper pro vyber optimalizovane media varianty; limit 100 kB se predava jen pro homepage about vizual.
- Verejny render, publish i ulozeni homepage about bloku pouzivaji optimalizovanou WebP variantu misto originalu.
- Kontaktni CSS ma finalni pravidla pro `.kt-consent` a `input:checked`, aby checkbox neprebily obecne input styly.

## Vysledek

Oprava je dokoncena: homepage about obrazek se pri ulozeni i renderu mapuje na optimalizovanou media variantu s limitem 100 kB, kontaktni checkbox ma finalni styl pro jednu linku a vlastni checked stav. Overeno pres `php -l` pro upravene PHP soubory a `git diff --check`.
