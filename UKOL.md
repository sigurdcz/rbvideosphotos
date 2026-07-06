# UKOL: Elegant White — ikony, Kontakt tlacitko, prekryv inputu

Datum: 2026-07-06
Zadani: V sablone Elegant White nahradit placeholder ikony za Font Awesome, opravit barvu textu aktivniho tlacitka Kontakt v navu (ma zustat bila, ne zlata) a opravit prekryvajici se pole kontaktniho formulare na homepage.
Hotovo kdyz: (1) ew-service-icon ikony jsou realne FA glyfy misto unicode/mojibake znaku, (2) .nav-cta ma za vsech stavu (hover/aktivni) bily text, ostatni nav odkazy zustavaji zlate, (3) .ew-field pole na homepage kontakt formulari se v uzsich sirkach nepretekaji/neprekryvaji. Overeno staticky (php/CSS/JS syntax) a primo na zivem produkcnim webu (rbvideosphotos.kvalitne.cz) pres curl — bez lokalniho serveru.

## Diagnostika (hotovo pred zapisem kroku)

- **Ikony**: jediny zivy ikonovy system v elegant-white je `.ew-service-icon` generovany `theme.js` (`iconClassFromTitle`), vykreslovany jako prazdny `<div>` s CSS `::before{content:"◉"/"▣"/"▤"/"□"/"♪"}` (hrube geometricke unicode znaky). `.jb-price-icon`/`.jb-contact-social-btn` a `.ew-process-icon` jsou MRTVE tridy zdedene z jemna-bezova sablony — v elegant-white theme.js je nikdo negeneruje, takze se nikde nevykresluji. Nechavam je bez zmeny (mimo rozsah, nulovy vizualni efekt).
- **Kontakt tlacitko**: `frontend-runtime.js` (`initActiveNav`) prida tride `.nav-active` na odkaz podle aktualni cesty/hashe — vcetne `.nav-cta` (Kontakt). Pravidlo `body...[data-cms-page-key="homepage"] .theme-nav-wrap .nav-links a:hover, ...a.nav-active { color: var(--ew-gold-dark) !important }` ma specificitu (0,5,2) a prebiji VSECHNA existujici "bily text" pravidla pro `.nav-cta` (max specificita (0,4,0) v souboru). Vysledek: tmave zlaty text na zlatem gradientovem pozadi tlacitka = splyva. Overeno primo v zdrojich `Templates/elegant-white/assets/theme.css`.
- **Prekryvajici se inputy**: Screenshoty odpovidaji HOMEPAGE inline `#kontakt` sekci (`.hp-contact-form`, JS-transformovane theme.js na `.ew-field` dvojice), NE standalone `/kontakt` strance (ta ma jina labely: "Jmeno a prijmeni *"). Overeno primym stazenim zive homepage HTML a jejiho generovaneho CSS (`page-css-*.css`) pres curl. `.hp-contact-form{grid-template-columns:repeat(2,minmax(0,1fr))}` je spravne, ALE `.ew-field` (grid polozka) nikde v cele theme.css nema `min-width:0`. Bez toho grid polozka odmita zmensit se pod intrinsic min-content sirku vlozeneho `<input>`, coz pri uzsi sirce zpusobi preteceni/prekryv dvou vedle sebe stojicich poli. Analogicka, JIZ OPRAVENA situace na standalone `/kontakt` strance ma presne tento safeguard (`.kt-form-grid>div{min-width:0}`) — homepage varianta ho postrada. To je prima pricina.

## Kroky

- [x] Zjistit skutecny zdroj vsech 3 problemu (staticka analyza + zivy web pres curl, bez lokalniho serveru)
- [x] Task 2: Pridat final vysoce specificky override pro `.nav-cta` text (bily za vsech stavu)
- [x] Task 3: Pridat `min-width:0` na `.ew-field` (a safety na inputy) v theme.css
- [x] Task 1: Pridat Font Awesome import do theme.css (stejny vzor jako existujici Google Fonts @import) — overeno primo pres curl (CDN vraci 200, obsahuje fa-camera/fa-users/fa-briefcase/fa-calendar-days/fa-music)
- [x] Task 1: Upravit theme.js — vykreslit `<i class="fa-solid fa-...">` v ramci `.ew-service-icon` divu
- [x] Task 1: Odstranit/neutralizovat stare unicode `::before` glyfy pro service ikony (aby se nezobrazovaly dve ikony najednou)
- [x] Overit PHP/CSS/JS syntax (brace/paren balance CSS 785/785, JS 109/109 + 523/523; PHP nedotceno)
- [x] Overit diagnozu proti aktualnimu zivemu stavu (curl) a spocitat CSS specificitu opravy oproti realne nasazenym pravidlum
- [ ] Commitnout (pouze git, zadne SQL, zadny rucni zasah na serveru) — skutecne nasazeni na produkci provede uzivatel, neni v mych rukou

## Poznamky

- Zmeny jsou vyhradne v `Templates/elegant-white/assets/theme.css` a `theme.js` — soubory nasazovane pres git, ne DB seed. Zadny seeder ani SQL neni potreba, protoze jde o statické sablonove assety.
- Vedlejsi zjisteni (MIMO rozsah tohoto ukolu, jen k vedomi): `enhanceContact()` v theme.js pri transformaci homepage kontakt sekce kompletne prepise `.container` (`container.innerHTML = ''`), coz odstrani puvodni `.hp-contact-box` s telefonem/emailem/socialnimi odkazy a CTA tlacitkem "Kontakt" z teto sekce (zustavaji jen v patickovem footeru, ktery se renderuje serverove nezavisle). Needelal jsem zadnou zmenu, protoze to uzivatel nepozadoval — jen upozornuji.
- Font Awesome nacitan pres CDN (`@import` v theme.css, stejny vzor jako existujici `fonts.googleapis.com` import) — v projektu neni build/npm pipeline, takze CDN import je konzistentni s existujici konvenci (theme.css uz externe nacita Google Fonts stejnym zpusobem).

## Vysledek

Vsechny tri opravy hotove, izolovane pouze do `Templates/elegant-white/assets/theme.css` a `theme.js`:

1. **Ikony** — pridan `@import` Font Awesome 6.5.2 (cdnjs, stejny vzor jako existujici Google Fonts import),
   overeno primo curl (CDN vraci 200, balicek obsahuje fa-camera/fa-users/fa-briefcase/fa-calendar-days/fa-music).
   `theme.js` (`buildServicesOverview`) ted vklada `<i class="fa-solid fa-...">` misto prazdneho divu; pridana
   mapovaci funkce `faIconFromKey`. Stare `::before{content:"◉"/"▣"/"▤"/"□"/"♪"}` unicode glyfy pro
   `.ew-service-icon--*` odstraneny (na obou mistech v souboru), aby se nezobrazovaly dve ikony pres sebe.
   `.ew-process-icon--*` (potvrzeno mrtve — zadny JS je negeneruje) ponechano beze zmeny, mimo rozsah.

2. **Kontakt tlacitko** — pricina: `frontend-runtime.js` prida `.nav-active` na aktualni/aktivni nav odkaz
   vcetne `.nav-cta`; obecne pravidlo pro nav odkazy melo specificitu (0,5,2) a prebijelo vsechna existujici
   "bily text" pravidla pro `.nav-cta` (max (0,4,0)) → tmave zlaty text na zlatem pozadi tlacitka splyval.
   Pridan final guardrail na konec souboru cilici vyhradne na `a.nav-cta` (vc. `:hover`/`.nav-active`
   a homepage-scoped varianty) se specificitou (0,6,2)/(0,5,2) — vzdy vyhrava, bily text za vsech stavu.
   Ostatni nav odkazy (FAQ, Blog...) zustavaji zlate beze zmeny.

3. **Prekryvajici se inputy** — screenshoty odpovidaly HOMEPAGE inline `#kontakt` sekci (`.hp-contact-form`
   transformovane `theme.js` na `.ew-field` dvojice), ne standalone `/kontakt` strance. `.hp-contact-form`
   ma spravnou 2-sloupcovou grid definici (`repeat(2,minmax(0,1fr))`), ale `.ew-field` (grid polozka) nikde
   nemela `min-width:0` — bez toho polozka odmita zmensit se pod intrinsic min-content sirku vlozeneho
   inputu, coz pri uzsi sirce zpusobi preteceni/prekryv dvou poli vedle sebe. Analogicka, jiz opravena
   standalone `/kontakt` stranka ma presne tento safeguard (`.kt-form-grid>div{min-width:0}`) — homepage
   varianta ho postradala. Pridan `min-width:0` + `max-width:100%` + `box-sizing:border-box` na `.ew-field`
   a primo na inputy/textarea/select uvnitr `.hp-contact-form`.

**Postup diagnostiky**: bez lokalniho serveru — primy HTTP pristup (curl) na zivy produkcni web
(rbvideosphotos.kvalitne.cz), stazeni skutecneho vygenerovaneho CSS (`/assets/generated/page-css-*.css`)
a rucni vypocet CSS specificity proti realne nasazenym pravidlum, porovnani s analogickymi, jiz funkcnimi
implementacemi (`/kontakt` standalone stranka) pro potvrzeni diagnozy.

**Vedlejsi zjisteni mimo rozsah** (jen k vedomi, nezasahovano): `enhanceContact()` v theme.js pri
transformaci homepage kontakt sekce kompletne prepise `.container`, coz odstrani puvodni `.hp-contact-box`
s telefonem/emailem/socialnimi odkazy a CTA tlacitkem "Kontakt" z teto konkretni sekce (kontaktni udaje
zustavaji zachovane v patickovem footeru, ktery se renderuje serverove nezavisle).

**Nasazeni**: zmeny jsou pouze v git-verzovanych sablonovych assetech, zadne SQL ani seed. Projevi se
automaticky pri nejblizsim nasazeni/publikovani webu s timto commitem — samotny deploy provadi uzivatel.
