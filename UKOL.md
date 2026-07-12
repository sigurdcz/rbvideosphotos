# UKOL: Lokalni screenshot iterace bezova-lens-tale

Datum: 2026-07-12
Zadani: Zprovoznit stabilni lokalni server SEO Editoru, overit porizovani desktop/mobile screenshotu na realnem theme preview a provest prvni vizualni iteraci podle budouciho ciloveho screenshotu.

## Kroky

- [x] Spustit lokalni PHP server na stabilnim portu a overit theme preview
- [-] Pripojit vestaveny prohlizec a overit screenshot desktopu
- [ ] Overit mobilni viewport a screenshot bez horizontalniho overflow
- [ ] Zaznamenat vychozi vizualni stav a technicka kriteria skore
- [ ] Provest prvni iteraci podle aktualne dostupneho srovnani
- [ ] Spustit regresni testy a Wiki Reflection

## Poznamky

Cilovy screenshot pro dalsi presne skore posle uzivatel po zprovozneni workflow. Do te doby se prvni iterace zameri na prokazatelne fungovani `body[data-blt-ready="true"]`, cilove poradi sekci, desktop/mobile screenshot a absenci horizontalniho overflow.

Lokální URL: `http://127.0.0.1:8765/?theme_preview=bezova-lens-tale`.

Server bezi skryte jako PHP proces PID `11748`, nasloucha na `127.0.0.1:8765`; theme preview vratil HTTP 200 a spravnou theme body class.

Browser blocker: vestaveny prohlizec se zastavil pred navigaci, protoze jeho volani nedostalo povinne pole `sandboxPolicy`. Screenshot ani DOM mereni proto nebyly provedeny a nebyly nahrazeny jinym skrytym rendererem.

### Kriteria screenshot skore

1. Desktop viewport `1440x1000`, full-page screenshot, stejna URL a stejna lokalni DB.
2. Mobilni viewport `390x844`, full-page screenshot.
3. `body[data-blt-ready="true"]` a prave jedna viditelna navigace.
4. Poradi: hero -> O nas/sluzby -> portfolio -> proces -> cenik -> reference -> blog -> kontakt.
5. Film bez poskoceni pri scrollu a funkcni lightbox po kliknuti.
6. `scrollWidth === clientWidth` na desktopu i mobilu.
7. Zadna neodchycena konzolova chyba pred nastavenim `data-blt-ready`.
8. Vizualni skore po sekcich: geometrie 30 %, typografie 20 %, barvy/overlay 15 %, karty/media 20 %, responzivita 15 %.

Prvni iterace bude po odblokovani browseru vychazet z baseline screenshotu a opravovat nejdrive tri nejvetsi rozdily podle vah skore, potom znovu screenshot a prepocet.

## Vysledek

Probíhá zprovozneni prostredi.
