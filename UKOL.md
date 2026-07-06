# UKOL: Oprava hero, kontaktu, ceniku a homepage layoutu

Datum: 2026-07-06
Zadani: Opravit rozbite stranky a sablony v RB Videos & Photos tak, aby hero sekce mely svetly text a overlay, kontakt mel dva panely, cenik a sluzby byly vizualne konzistentni a homepage nemela posunute video ani usekane sekce.

## Kroky

- [x] 1. Zmapovat aktualni layouty a najit zdroj rozbitych hero, kontaktu, ceniku, sluzeb a homepage sekci
- [x] 2. Upravit globalni hero pravidla: svetly text, overlay/fade, parallax/video chovani a konzistentni pozicovani
- [x] 3. Prestavet kontaktni stranku na dva panely s levym infopanelem a pravym formularovym panelem
- [x] 4. Opravit cenik: odstranit konfliktni panel, zkratit title, upravit layout karet a poradi obsahu
- [x] 5. Opravit stranku sluzeb a detail sluzby, aby se neplikovala jedna fotka a obsah odpovidal DB
- [x] 6. Doladit homepage: video posun, parallax, VR foto crop a plynule prechody sekcnich pozadi
- [x] 7. Projit dotcene MD soubory a aktualizovat dokumentaci / report k nove realite
- [x] 8. Overit syntax, diff a pripadne pripravit commit

## Poznamky

- Upraveny `public/index.php`: hero overlay/parallax pouziva stejnou promennou jako JS a footer konecne vypisuje obe adresni radky.
- Upraveny `Templates/elegant-white/assets/theme.css`: posledni override vrstva drzi hero text svetly, sjednocuje hero media a vraci cenik a sluzby do svetleho layoutu.
- Upraveny `database/seeds/seed_photographer.php`: cenik ma kratky hero title, lehky CTA panel a LocalBusiness schema doplnenou o `PostalAddress`.
- Priorita zustava vizualni konzistence a zachovani DB-driven obsahu, ne hardcoded obchazeni problemu v sablonach.
- Kontakt musi byt citelny a dvousloupcovy, hero sekce musi mit vzdy svetle prekryti/text a homepage nesmi rozbijet rozmery nebo crop media.

## Vysledek

Hotovy runtime fix pro hero, kontakt, cenik, services, homepage a local SEO footer. Aktualizovany byly `UKOL.md`, `public/index.php`, `Templates/elegant-white/assets/theme.css` a `database/seeds/seed_photographer.php`. Overeni: `php -l public/index.php`, `php -l database/seeds/seed_photographer.php`, `git diff --check`. Commit: `978f819` (`fix(frontend): restore hero, contact and pricing layouts`). Push na `origin/master` probehl uspesne.
