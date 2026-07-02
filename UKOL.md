# UKOL: Skryt honeypot a opravit stranku Cenik

Datum: 2026-07-02
Zadani: Vysvetlit a znovu spravne skryt anti-spam honeypot ve formulari a opravit samostatnou stranku /cenik, kde je hero text necitelny a layout rozbity.

## Kroky

- [x] Zapsat aktualni zadani do UKOL.md
- [x] Zkontrolovat aktualni markup/CSS pro kontaktni honeypot a stranku /cenik
- [x] Opravit viditelny honeypot bez odstraneni anti-spam ochrany
- [x] Opravit citelnost a rozlozeni samostatne stranky /cenik
- [x] Overit PHP syntaxi, diff a lokalni render /kontakt + /cenik
- [x] Commitnout a pushnout souvisejici zmeny

## Poznamky

- Honeypot je schvalne skryte pole proti spamu. Nesmi byt videt ani zabirat misto ve formulari.
- /cenik musi mit citelny hero text na tmavem prekryvu a obsah nesmi byt nalepeny na levy kraj.
- Renderer pred vykreslenim normalizuje starou cenikovou sablonu `<header class="hero">` na `<section class="page-hero">`.
- DemoContentSeeder uz novou cenikovou sablonu generuje jako `page-hero`, takze se oprava neprepise pri znovunacteni demo obsahu.
- Honeypot zustava ve formulari jako anti-spam pole, ale ma silnejsi CSS skryti i uvnitr `.kt-form-block`.
- Overeno: `php -l public/index.php`, `php -l app/Services/PublishService.php`, `php -l app/Services/DemoContentSeeder.php`, `git diff --check`, lokalni HTTP 200 pro `/kontakt` a `/cenik`.

## Vysledek

Honeypot zustal zachovany jako anti-spam ochrana, ale je znovu skutecne skryty i proti silnejsim formularovym CSS pravidlum. Stranka /cenik se pred renderem a pri publishi normalizuje ze stareho `header.hero` na `section.page-hero`; demo seeder uz zapisuje opravenou sablonu.
