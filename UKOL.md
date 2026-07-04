# UKOL: Elegant White homepage alignment

Datum: 2026-07-04
Zadani: Upravit homepage sablony `Elegant White` podle prilozeneho aktualniho HTML tak, aby se kompozici a kontrastem priblizila cilovemu screenshotu.

## Cil

Nova sablona ma byt samostatne volitelna v administraci, ale homepage musi z realneho DOMu poskladat cilovou svetlou kompozici: hero vlevo text a vpravo fotka, about blok s fotkou a textem, vodorovne portfolio, samostatne sluzby, recenze, FAQ, proces a kontakt bez tmavych puvodnich bloku.

## Kroky

- [x] 1. Nacist aktualni HTML a zjistit realny DOM homepage
  Popis: Pouzit prilozene HTML jako zdroj pravdy pro tridy, bloky a dostupny obsah.
  Hotovo kdyz: Je jasne, ktere existujici sekce musi CSS/JS prekomponovat.

- [x] 2. Prestavet homepage JS pro cilovou skladbu
  Popis: Upravit `theme.js`, aby z existujicich sekci vytvoril about blok, vodorovne portfolio, sluzby s ikonami, recenze, FAQ, proces a kontakt podle cilove reference.
  Hotovo kdyz: Elegant White nevypada jako puvodni bezova homepage a hlavni sekce maji cilove poradi a strukturu.

- [x] 3. Dodelat CSS pro cilovy svetly vzhled
  Popis: Upravit `theme.css` pro kontrastni text, svetlou navigaci, spravne rozmery, zaobleni, stiny, tlacitka a responsivitu.
  Hotovo kdyz: Sekce uz nejsou vybledle nebo tmave a kompozice odpovida cilovemu screenshotu.

- [x] 4. Validace bez rozbiti ostatnich temat
  Popis: Projit diff, zkontrolovat, ze zmeny zustaly ve `Templates/elegant-white` a `UKOL.md`.
  Hotovo kdyz: Kontroly nehlasi chyby a worktree neobsahuje docasne soubory.

## Poznamky

- Prilozeny aktualni zdrojak uz obsahuje `body.theme-elegant-white`, takze dalsi oprava ma mirit na CSS/JS kompozici homepage.
- Cilovy screenshot ma podstatne jinou skladbu nez aktualni stav: soucasne centrovane sekce jsou prilis uzke, vybledle a chybi cilove bloky `about`, `services`, `process` a kontakt s obrazkem.
- `Templates/elegant-white/assets/theme.js` je prepsany tak, aby homepage skladal cilove poradi sekci: hero, about, portfolio strip, sluzby, recenze, FAQ, proces a kontakt.
- `Templates/elegant-white/assets/theme.css` obsahuje zaverecne homepage-only override pro svetle bile/zlate schema, oble rohy, kontrastni text, skryti homepage blog/cenik sekci a responzivni rozvrzeni.
- Navigace na homepage upravuje odkazy Blog/Cenik na samostatne URL, protoze tyto sekce jsou v cilove kompozici schovane.
- Homepage JS explicitne radi sekce podle cile: recenze jsou pred FAQ a proces zustava pred kontaktem.

## Vysledek

Hotovo. Upraveny byly pouze `Templates/elegant-white/assets/theme.js`, `Templates/elegant-white/assets/theme.css` a `UKOL.md`. `git diff --check` prosel bez chyb; lokalne neni dostupny `node`, proto neslo spustit `node --check`.
