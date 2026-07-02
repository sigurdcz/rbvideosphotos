# UKOL: Opravit media picker a editaci obsahu sluzeb

Datum: 2026-07-02
Zadani: V adminu musi media picker zobrazovat nahledy, obsah sluzby se ma editovat pres WYSIWYG editor a obrazkova pole maji pouzivat vyber z medii misto samotneho textoveho URL pole.

## Kroky

- [x] Najit implementaci media pickeru, editoru bloku a typu poli pro obrazky.
- [x] Opravit nahledy medii v pickup modalu.
- [x] Prepnout obsah sluzby z plain HTML textarea na WYSIWYG editor.
- [x] Doplnit media picker ovladani pro obrazkova URL/media pole.
- [x] Opravit nahledy medii v portfolio admin gridu.
- [x] Obnovit socialni site ve footeru / zjistit proc se renderuje prazdny fallback.
- [x] Overit syntaxi a relevantni admin assety.
- [x] Zhodnotit dokumentaci a zapsat vysledek.

## Poznamky

- Pracovni kopie byla pred zacatkem cista.
- Uspech: v media modalu jsou viditelne thumbnail nahledy, pole `Obsah sluzby` se edituje jako WYSIWYG a obrazkova pole v adminu maji tlacitko pro vyber z medii.
- Nalezeno: picker helpery u variant vraci `/storage/media/...`, coz na produkci pada na soukromy storage/403. Spravne musi vracet verejne `/media/...`.
- Nalezeno: media modal v `admin/views/pages/form.php` je omylem uvnitr FAQ podminky, proto neni obecne dostupny pro generic/service editaci.
- Nalezeno: HTML bloky jsou textarea `.code`; blog/portfolio uz maji jednoduchy `contenteditable` editor, ktery lze zobecnit pro bloky.
- `admin/views/pages/form.php` ted normalizuje media URL na `/media/...`, renderuje `html/richtext` bloky jako WYSIWYG a dava image blokum tlacitko `Vybrat z medii`.
- Strankovy media modal je dostupny pro vsechny editovane stranky a umi vlozit URL do image/service pole nebo obrazek primo do WYSIWYG editoru.
- `admin/views/settings/index.php` pouziva stejnou verejnou URL normalizaci pro thumbnail varianty.
- `admin/assets/admin.js` umi inicializovat vice WYSIWYG editoru na jedne strance a obsluhuje vlozeni obrazku pres media picker.
- `admin/assets/admin.css` doplnuje vzhled richtext editoru a radku s media inputem.
- `app/Controllers/ApiController.php` ted vraci verejna media URL pres `/media/...`, zatimco `storage_path` zustava interni.
- Dokumentace `README.md`, `ARCHITECTURE.md` a `INSTRUCTIONS.md` byla srovnana na pravidlo `/media/...` pro verejne URL.
- Novy pozadavek: v `/admin/portfolio` se nahledy stale renderuji jako `/storage/media/...` a padaji; opravit na `/media/...`.
- Novy pozadavek: ve footeru zmizely socialni site a renderuje se pouze vyzva `Doplnte socialni site v kontaktu.`
- `admin/views/portfolio/index.php` uz pouziva verejne `/media/...` URL pro prvni prirazene medium v admin gridu.
- Pricina zmizelych socialnich siti: `seedDemoContactInfo()` volal `ContactRepository::upsert()` bez `social_*` poli, cimz je pri demo obnove nastavil na `NULL`. Seeder je znovu doplnuje.
- Stejny media URL fix byl doplnen i do blog media gridu, portfolio edit galerie a SEO media pickeru, aby se problem neopakoval v dalsich admin obrazkovych vyberech.

## Vysledek

Hotovo: media pickery, blog media grid, portfolio grid a SEO media picker v adminu pouzivaji verejne `/media/...` URL pro nahledy i vyber. HTML/richtext bloky v editoru stranek se edituji pres WYSIWYG s moznosti vlozit obrazek z medii. Obrazkova pole ve strankach a sluzbach maji tlacitko pro vyber z medii. API i dokumentace byly srovnany na verejne media URL `/media/...`; `storage/media/...` zustava interni uloziste. Demo contact seed znovu nastavuje socialni site.
