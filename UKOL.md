# UKOL: Mobile-first UI/UX opravy — jemna-bezova

Datum: 2026-05-18
Zadání: 4 vizuální opravy šablony jemna-bezova pro mobilní zobrazení.

## Kroky

- [x] 1. Odstranit černý pruh nahoře a dole při načtení homepage
- [x] 2. Opravit burger menu — odsazení, styl položek, přidat položku Úvod/Home
- [x] 3. Opravit barvy na stránce Kontakt (font, pozadí, inputy) — mobile + desktop
- [x] 4. Opravit patičku mobile — telefon label + hodnota na jednom řádku
- [x] Commitnout vše

## Poznámky

## Výsledek

(1) Hero background změněn z čisté černé (#0e0c0a) na teplou tmavou hnědou (#1a1610) + gradient fade na spodní hraně hero sekce. (2) Burger menu — odstraněny konfliktní dropdown CSS pravidla z 760px bloků, ponechán fullscreen overlay z 768px bloku; přidány text-align:center + width:auto; přidán odkaz "Úvod" přes JS. (3) Kontaktní stránka — inputy (.kt-form-block) dostaly viditelné pozadí rgba(255,255,255,.07) s gold border, fonty přepsány do theme palety. (4) Patička mobil — telefon label + hodnota na jednom řádku (auto minmax(0,1fr)).
