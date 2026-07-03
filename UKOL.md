# UKOL: Elegant White template

Datum: 2026-07-04
Zadani: Dopracovat novou sablonu `Elegant White` tak, aby vizualne odpovidala aktualni referenci z noveho screenshotu a zustala samostatnou kopii `Jemne bezova`.

## Cil

Nova sablona ma byt samostatne volitelna v administraci, ale ma vychazet z existujiciho rozvrzeni `Jemne bezova`. Hlavni zmena je vizualni: vice bileho prostoru, jemne zlato, svetle bezove akcenty, zaoblene prvky a bez tvrdych ramecku, s kompozici blizsi aktualni referenci.

## Kroky

- [x] 1. Zkontrolovat zdrojovou sablonu a loader
  Popis: Overit, jak se sablony nacitaji z `Templates/` a jak se registruje jejich `key`, `name` a layout.
  Hotovo kdyz: Je jasne, co je treba kopirovat a co jen prejmenovat.

- [x] 2. Vytvorit novou sablonu z kopie `Jemne bezova`
  Popis: Nakopirovat existujici sablonu do nove slozky `Templates/elegant-white` a upravit identitu sablony.
  Hotovo kdyz: V repu existuje nova samostatna slozka sablony s vlastnim manifestem.

- [x] 3. Prevest identitu na `Elegant White`
  Popis: Zmenit `template.json`, layout a podporne skripty tak, aby se sablona v administraci zobrazovala jako `Elegant White`.
  Hotovo kdyz: Sablona ma novy `key`, novy display name a bez konfliktu zustava kompatibilni s existujicim renderem.

- [x] 4. Prepsat vizual na bily, zlaty a svetle bezovy
  Popis: Pridat nove CSS override vrstvy pro svetly minimalni vzhled bez ostrych hran, ramecku a tvrdych kontrastu.
  Hotovo kdyz: Navbar, hero, karty, formulare, footer i CTA pusobi jako svetla elegantni varianta bezove sablony.

- [x] 5. Projit layouty a komponenty po vizualni strance
  Popis: Zkontrolovat, ze stary motiv zustal nedotcen a novy motiv se vizualne blizi aktualni referenci.
  Hotovo kdyz: Zmeny jsou omezeny na novou sablonu a odpovidaji nove cilove kompozici.

- [x] 6. Validace a commit
  Popis: Projit diff, zkontrolovat syntax relevantnich souboru a vytvorit commit.
  Hotovo kdyz: Repo je pripraveno k pushi nebo uz je pushnuto, podle dalsiho zadani.

## Poznamky

- Nova sablona vyuziva stavajici layout jako vychozi bod, ale dostava vlastni `theme-elegant-white` vrstvu pro vizualni override.
- Zachovavam kompatibilitu se starym `theme-jemna-bezova`, aby nedoslo k rozbiti existujiciho obsahu a JS logiky.
- Cilem je samostatna sablona v administraci, ne prepis stavajiciho motivu.
- Kontrola diffu probehla; novy motiv ma vlastni manifest, layout, JS guard i CSS override vrstvu.
- Aktualni reference posouva design vic do bilych ploch, jemneho zlata a svetle bezeva, s lehcimi kartami a vyrazne cistsim hero/layoutem.

## Vysledek

Nova sablona `Elegant White` je pripravena jako samostatna kopie `Jemne bezova` a priblizuje se aktualni svetle luxusni reference.
