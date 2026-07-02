# UKOL: Nastaveni e-mailu pro formulare

Datum: 2026-07-02
Zadani: Pridat do administrace Nastaveni pole pro e-mail prijemce kontaktni komunikace a nastavit vychozi hodnotu na sigurd.gnezdilov@gmail.com.

## Kroky

- [x] Zapsat aktualni zadani do UKOL.md
- [x] Najit aktualni zdroj prijemce e-mailu a formular Nastaveni
- [x] Pridat nove nastaveni do administrace a ulozeni
- [x] Napojit odesilani formulare na nove nastaveni a doplnit vychozi hodnotu
- [x] Overit syntaxi a funkcni tok

## Poznamky

- Cilem je, aby administrace umela menit prijemce e-mailu bez upravy kodu.
- Vychozi hodnota ma byt `sigurd.gnezdilov@gmail.com`, pokud neni nic nastavene.
- Zdroj pravdy zustava v `contact_info.form_email`; pole v Nastaveni je jen druhe UI nad stejnymi daty.
- Runtime i publish fallbackuji na `ContactRepository::DEFAULT_FORM_EMAIL`, aby formular fungoval i na starsich datech bez ulozene hodnoty.

## Vysledek

Do administrace Nastaveni pribylo pole pro e-mail prijemce formularovych zprav. Uklada se do `contact_info.form_email`, a pokud neni nic nastavene, frontend i publish automaticky pouziji `sigurd.gnezdilov@gmail.com`. Demo seed tuto hodnotu nastavuje take jako vychozi.
