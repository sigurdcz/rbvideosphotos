# UKOL: Elegant White 3 visual fix-up a poradi homepage

Datum: 2026-07-17
Zadani: Spustit lokalni server, aktivovat Elegant White 3, opravit poradi DB-renderovanych homepage sekci a opravit desktopove vizualni kolize na homepage.

## Kroky

- [x] Spustit lokalni server na standardni URL projektu.
- [x] Aktivovat `elegant-white3` v lokalni DB i stavovem souboru a znovu publikovat web.
- [x] Overit homepage bez preview parametru a pripravit prostredi pro dalsi fix-up.
- [x] Seradit homepage na Hero -> Co nabizime / Sluzby (O nas) -> Portfolio -> Recenze -> Cenik -> Jak to probiha -> Blog -> Kontakt + FAQ.
- [x] Overit skutecne poradi ve fresh runtime HTML a vygenerovanem assetu.
- [x] Zapsat predanou analytiku a pevny QA postup pro telefon a PC.
- [x] Doplnit oba QA dokumenty do dokumentacni mapy README.
- [x] Opravit desktopovou sekci Sluzby, duplicitni review aside, geometrii Procesu a velikost checkboxu souhlasu v Elegant White 3.
- [x] Overit opravu ve fresh runtime assetech a automatickych kontrolach.

## Poznamky

- Predchozi ETHERNA ukol je dokoncen a commitnuty; pracovni strom byl pred zahajenim cisty.
- Aktivace musi odpovidat `ThemeController::activate()`: zapsat `sites.active_theme_key`, ulozit `storage/active-theme-key.txt` a provest publish, aby runtime i publikovany vystup souhlasily.
- Server bezi na `http://127.0.0.1:8766/` (PID 47060). `sites.active_theme_key` i `storage/active-theme-key.txt` uz obsahuji `elegant-white3`; homepage bez `theme_preview` vraci `theme-elegant-white3`.
- Prvni publish byl viditelne odmitnut cizim klicem `publish_history.user_id`, protoze pomocny CLI prikaz pouzil ID 0. Lokalni admin existuje jako ID 1; publish se zopakuje s timto platnym ID.
- Publish s lokalnim adminem ID 1 probehl uspesne. Homepage bez preview parametru vratila HTTP 200 a `theme-elegant-white3`; stejna trida je v `storage/published/index.html`.
- `#sluzby` je aktualni DB-renderovany blok `Co nabizime` a zaroven zamyslena sekce `O nas`; samostatne `#o-nas` se nevytvari ani nevyzaduje.
- `homepage-v3.js` serazuje existujici sekce podle pozadovane konverzni cesty bez odvozeneho trust stripu; Recenze zustavaji jedinym zdrojem tohoto obsahu.
- Predane GA4 screenshoty urcuji pevne QA viewporty: telefon 390 x 844 a PC 1920 x 1080. Vznikaji `analytics.md` a `qa-testing.md` jako zdroj dat a opakovatelny postup.
- README dokumentacni mapa odkazuje na `analytics.md` a `qa-testing.md` vcetne jejich role a pevných viewportu.

- Reportovane chyby se tykaji pouze aktivniho override `elegant-white3`: prekryti obsahu fotografii ve Sluzbach, duplicitni odvozeny review aside, deformovane kroky Procesu a globalni minimalni vyska aplikovana na checkbox souhlasu.
- Sluzby maji na desktopu mensi levou fotografii (420 px vyska) a mensi mezeru; proces ma pevne kruhy 60 px nad textem a osu ve vysce jejich stredu. Finalni desktopovy override neutralizuje starsi absolutni pozicovani cisel. Consent checkbox explicitne prepisuje obecnou minimalni vysku na 20 px i po normalizaci kontaktniho formulare.
- Funkce a styly pro `.ew3-trust-strip` byly odebrany, takze pod kartami Recenzi nezustava zadny zduplikovany odvozeny review blok. Publish s lokalnim adminem ID 1 probehl po oprave uspesne.
- `php tests/run.php` proslo 27/27; `git diff --check` proslo. Fresh runtime (HTTP 200) vydal nove hashovane assety `/assets/generated/page-css-8f6268db0c99064a.css` a `/assets/generated/page-js-39d4b8692c060672.js`, ktere obsahuji opravne CSS a uz nevolaji ani neresadi trust strip. Fresh Chrome screenshoty pro desktop vizualne potvrdily oddeleny obraz a text Sluzeb, absenci review aside, osu prochazejici stredem cisel procesu a maly checkbox vedle souhlasu.

## Vysledek

Hotovo. Lokalni server bezi na `http://127.0.0.1:8766/`; Elegant White 3 je aktivni v DB, stavovem souboru i publikovanem vystupu. Homepage ma poradi Hero -> Co nabizime / Sluzby (O nas) -> Portfolio -> Recenze -> Cenik -> Proces -> Blog -> Kontakt -> FAQ. `analytics.md` uklada predany GA4 snapshot a `qa-testing.md` zavadi pevne viewporty 390 x 844 a 1920 x 1080 pro dalsi fix-up.
