# UKOL: Znovu opravit RB frontend

Datum: 2026-07-06
Zadani: Znovu projit a skutecne opravit aktualni vizualni chyby RB Videos & Photos: hero text/overlay/parallax, kontakt, cenik, sluzby/detail, homepage media a plynule pozadi sekci.

## Kroky

- [x] 1. Overit skutecnou renderovaci cestu a proc predchozi upravy nejsou videt
- [x] 2. Opravit hero sekce napric kontakt/sluzby/FAQ/blog/nase prace/cenik/detail tak, aby mely svetly text, overlay a parallax media
- [x] 3. Opravit kontakt na dva panely: vlevo orientacni informace, vpravo velky kontaktni formular
- [x] 4. Opravit cenik: zadne hnede CTA pozadi, kratky title, tri cenove panely se zvetsenym prostrednim a cenou hned pod titulkem
- [x] 5. Opravit sluzby a detail sluzby, hlavne duplikujici se fotku pres vysku stranky
- [x] 6. Opravit homepage video: centrovani, parallax pri scrollu, neuseknuta VR fotka a plynule prechody pozadi sekci
- [x] 7. Provest syntax/diff kontrolu a napsat kriticky report zbyvajicich rizik

## Poznamky

- Predchozi stav `UKOL.md` tvrdil hotovo, ale podle aktualniho screenshotu jsou hlavni body porad rozbite. Beru to jako nove opravne zadani.
- Pracovni strom byl pred startem cisty.
- Nebudu schovavat problem fallbackem. Pokud zjistim, ze se upravuje jina sablona/data nez ta, ktera se publikuje, zapisu to jako pricinu a opravim zdrojovou cestu.
- Skutecna runtime cesta je DB/front controller + `public/assets/frontend-runtime.*`, ne pouze Elegant White sablona. Proto byly pridany runtime CSS/JS guardraily a DB injektaz ceniku/kontaktu.
- `PublishService` je srovnany s runtime kontaktem: nedela predcasny return pri nalezenem telefonu, doplnuje chybejici adresu z bloku, deduplikuje telefony a vypisuje oba radky adresy ve footeru.
- `/sluzby` v aktualni DB nemelo zadny `pages` zaznam a vracelo 404. Pridana je runtime DB-first stranka z `service_items`.
- Detail `reklamni-foto-video-olomouc` v aktualnim runtime uz nevykresluje jednu opakovanou fotku pres vysku stranky; ma text a galerii z DB.
- Kriticke zbyvajici datove riziko: `service_items` ma obrazek jen u prvni sluzby. Zbyvajici sluzby maji viditelny prazdny media stav, aby se neskryvalo chybejici DB medium nebo znovu nedosadila stejna fotka.
- Vsechny kontrolovane URL maji po sanitizaci `style=` pocet 0 vcetne GTM noscript iframe.
- Dokumentacne aktualizovan `ARCHITECTURE.md`; `README.md`, `API.md`, `GTM_ADMIN.md` a `INSTRUCTIONS.md` nebyly obsahove dotcene.

## Vysledek

Hotovo. Opraven runtime front controller, frontend CSS/JS, publish/contact logika, homepage cenik, DB seedy a architektura. Overeni: PHP lint dotcenych souboru, `git diff --check`, HTTP smoke testy pro `/`, `/sluzby`, `/kontakt`, `/cenik`, `/faq`, `/blog`, `/nase-prace`, `/reklamni-foto-video-olomouc`; vsechny vratily 200 a `style=` bylo 0.
