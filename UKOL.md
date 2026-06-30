# UKOL: Skryt detail sluzby na /sluzby

Datum: 2026-06-30
Zadani: Nechat na /sluzby klikatelny detail sluzby, ale nenechat ho zbytecne viditelny pod gridem pri nacitani.

## Kroky

- [x] Najit zdroj viditelneho detailu na /sluzby.
- [x] Upravit render detailu tak, aby byl skryty do kliknuti na konkretni sluzbu.
- [ ] Rychle overit syntax a layout /sluzby po skryti detailu.
- [ ] Rozhodnout o dokumentaci a dopsat jen pokud se meni dlouhodobe chovani nebo workflow.

## Poznamky

- Detail sluzby byl predtim vypsan jako normalni blok pod kartami; to bylo dobre pro kotvu, ale spatne pro vizual.
- Cilem je zachovat klikatelnost service CTA, ale nezobrazovat detail hned po nacteni.
- Zasah zustava v `app/Services/HomepageContentService.php`; jde o prezentačni CSS, ne o jadernou logiku.

## Vysledek

Rozpracovano.
