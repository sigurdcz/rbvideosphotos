# UKOL: Elegant White — sjednoceni designu podle navrhu

Datum: 2026-07-10
Zadani: Upravit HTML, CSS a JavaScript sablony Elegant White tak, aby homepage a vnitrni stranky odpovidaly dodanym referencnim navrhum, pritom vsechen obsah zustal nacitan z existujicich databazovych dat a zasahy do jadra editoru byly minimalni.

Hotovo kdyz: hlavni rozlozeni, typografie, svetla barevnost, navigace, hero sekce, karty, formulare, FAQ, cenik, detailove stranky a paticka vizualne odpovidaji referencim na desktopu i mobilu; aktualni prekryvani/duplikace prvku jsou odstraneny; sablona dal pouziva data predana editorem a zmeny projdou statickou i dostupnou runtime kontrolou.

## Kroky

- [x] Zmapovat sablonu, datovy kontrakt a rozdily mezi referencemi a aktualnim webem
- [x] Upravit sdileny layout, navigaci, typografii, barevnost a paticku
- [x] Upravit homepage sekce podle referencniho navrhu
- [x] Upravit prehledove stranky Sluzby, Nase prace, Blog, Cenik a FAQ
- [x] Upravit detail prace a detail clanku bez duplikace databazovych dat
- [x] Opravit responzivni chovani a prekryvani obsahu
- [x] Provest statickou a dostupnou vizualni/runtime kontrolu, opravit nalezy
- [x] Zkontrolovat relevantni dokumentaci a uzavrit vysledek

## Poznamky

- Zdroj obsahu zustava databaze; sablona ma data pouze prezentovat.
- Zasahy do jadra editoru nejsou planovane a budou zvazeny jen pri prokazatelnem chybejicim datovem vystupu.
- Predchozi UKOL byl implementacne hotovy; nevyzadany commit nebyl proveden a nove zadani jej nahradilo.
- Produkcni HTML potvrdilo, ze obsahova data jsou v DOM; problem je prevazne v prezentaci sablony.
- `public/index.php`: jediny nutny zasah do jadra — route `/sluzby` obchazela `applyThemeTemplate`. Nyni pouziva stejny sablonovy pipeline jako ostatni stranky, bere hero texty z page blocks a zachovava dosavadni databazove sluzby.
- `Templates/elegant-white/assets/theme.js`: navigace doplni chybejici odkaz Uvod z existujiciho menu, rozdeli databazovy nazev znacky pro barevne logo a na strance sluzeb doplni pouze prezentacni ikony bez prepisovani obsahu karet.
- `Templates/elegant-white/assets/theme.css`: pridana finalni prezentacni vrstva pro svetlou zlatobezovou paletu, jednotny header/footer, svetle hero bloky, sluzby s ikonami, prehledove karty, cenik, FAQ, kontakt, detaily a breakpointy 1080/820/600 px. Pravidla jsou na konci, aby stabilne prepsala starsi inline page CSS bez zmeny dat.
- Vizualni kontrola 1440x1400 odhalila prazdny header pri chybejicim lokalnim menu, nenactene CDN ikony, tmavy homepage overlay a zdedeny cerny prechod kontaktniho hero. Sablona ted zachova databazovy brand i bez menu, pouziva lokalni inline SVG ikony, zesvetluje homepage a rusi cerny prechod; souhlas formulare ma vlastni stabilni tok a rozmery.
- Druhy render potvrdil lokalni SVG ikony a opraveny kontaktni formular. Doplneno skryti prazdneho nav wrapperu (bez zdvojene vysky headeru) a vysoce specificky svetly homepage overlay, protoze starsi homepage-scoped pravidlo melo vyssi specificitu.
- Synteticky test se stejnou DOM strukturou menu jako na produkci potvrdil 7 odkazu vcetne doplneneho Uvodu, aktivni mobilni toggle a nulove horizontalni preteceni. Mobilni header je finalne uzamcen na 68 px.
- Zastarale komentare a selektory pro Font Awesome `<i>` byly prepsany na aktualni lokalni SVG implementaci; sablona uz nema externi ikonovou zavislost.
- `ARCHITECTURE.md`: doplnen kontrakt, ze i specialni DB-driven renderery vcetne `/sluzby` musi projit pres `applyThemeTemplate()`.
- Finalni produkcni DOM test se skutecnymi daty potvrdil: header 76 px, citelny Kontakt, jednoradkove kratke hero titulky, nulove horizontalni preteceni a zadne konzolove JS chyby. Jediny produkcni 404 je existujici `/favicon.ico`, mimo rozsah designu.

## Vysledek

Elegant White je sjednocena do svetleho editorialniho vzhledu podle referenci. Hlavni obsah zustava DB-driven; sablona resi strukturu prezentace, lokalni SVG ikony, interakce a responzivni pravidla. Jediny zasah do jadra napojuje `/sluzby` na standardni theme pipeline, protoze drive aktivni sablonu zcela obchazela.

Overeno pres `php -l`, vyvazeni CSS/JS zavorek, `git diff --check`, HTTP smoke test deviti URL a jejich hashovanych CSS/JS assetu, desktop/mobilni Selenium render, FAQ interakci a docasnou aplikaci lokalnich theme assetu nad skutecnym produkcnim DOM. Relevantni dokumentace: aktualizovan `ARCHITECTURE.md`; `README.md` a `INSTRUCTIONS.md` uz aktualni kontrakt popisovaly, proto nebyly meneny.
