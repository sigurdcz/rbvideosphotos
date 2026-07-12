# UKOL: Sjednoceni online a lokalniho renderu

Datum: 2026-07-12
Zadani: Pred dalsim vizualnim ladenim zjistit a odstranit pricinu, proc online homepage po uspesnem deployi stejneho commitu vypada jinak nez lokalni `bezova-lens-tale` preview.

## Kroky

- [x] Auditovat produkcni deploy artifact a exclude pravidla
- [x] Zjistit online domenu a porovnat online/lokalni HTML body class a asset URL
- [x] Porovnat hash a obsah online/lokalniho generovaneho CSS/JS
- [x] Rozlisit code drift, DB/template drift, cache a publish/runtime rozdil
- [x] Opravit prokazanou pricinu v deploy nebo runtime vrstve
- [-] Overit shodu online/lokalniho kontraktu a regresni testy
- [ ] Provest Wiki Reflection a uzavrit vysledek

## Poznamky

GitHub Actions screenshot potvrzuje uspesne production deploye commitu `04f4abf` a task commitu `087d47f`. To dokazuje shodu Git commitu, nikoli automaticky shodu DB, `.env`, aktivni sablony, generovanych assetu nebo browser cache.

Online screenshot stale odpovida stare/nekomponovane homepage, zatimco lokalni screenshot ma jine poradi, hero, karty a kontakt. Diagnostika musi porovnat skutecne HTTP odpovedi a asset hashe, ne pouze GitHub stav.

Prime dukazy: theme CSS suffix je bitove shodny (`CBFD5FFFEB969AF23A7C`) a theme JS prefix je bitove shodny (`5E5E52A3E16D7B17CA1D`). Online i lokal vraci `theme-bezova-lens-tale`, `data-blt-home-sources` a no-cache HTML. Nejde o code drift ani browser cache.

DB template drift: online render pouziva `hp-hero`, `hp-services-grid`, samostatne `#faq` a hotovy `hp-contact-grid`; lokal pouziva legacy `hero`, `svc-grid`, `cta-2col`, navic `stats-bar`, `proc-my` a `reference`. Git deploy `.env`/DB zamerne neprenasi.

Fix: theme JS normalizuje oba kontrakty do stejne kanonicke struktury. Online `hp-hero` dostane `hero/hero-body`, online section label/title/intro se skladaji do `sec-hdr`, vnitrni DB gridy se presouvaji na primou uroven kontejneru, dvojite blog cards se zplosti a online FAQ/kontakt se skladaji do stejneho tri-paneloveho kontaktu jako lokalni data source.

Online DB ma u vetsiny `section-title` prazdny obsah. Theme proto bez hardcoded klientskych textu povysi existujici `section-label` na nadpis pouze tehdy, kdyz je DB title prazdny; lokalni neprazdne nadpisy zustanou beze zmeny.

Pri implementacni kontrole bylo opraveno poradi operaci pri zplosteni blog card wrapperu: reference na wrapper se ulozi pred presunem vnitrni karty, aby se omylem neodstranil cely `hp-blog-grid`.

Lokalni overeni noveho normalizatoru: PHP/JS syntax prosla, preview HTTP 200 generuje asset `page-js-440600205a89a1c7.js` s online i legacy selektory a regresni sada prosla 18/18. Dalsi krok je production deploy a kontrola noveho online hashe.

## Vysledek

Probíhá diagnostika.
