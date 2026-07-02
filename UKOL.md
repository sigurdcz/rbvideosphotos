# UKOL: Oprava rozbité stránky /cenik

Datum: 2026-07-02
Zadání: Samostatná stránka /cenik má nečitelný hero (tmavý/nekontrastní text na fotce) a rozbitý layout (zdvojený obrázek).
Hotovo když: /cenik pod aktivním tématem editorial-gold zobrazuje čitelný hero s tmavým překryvem a světlým textem, bez zdvojeného obrázku — stejně jako ostatní podstránky.

## Příčina (zjištěno)

- Šablona /cenik používá `<header class="hero">` (holá třída `.hero`).
- Ostatní podstránky používají tématové třídy (`page-hero`, `fq-hero`, `kt-hero`, `bl-hero`, `pf-hero`),
  které téma editorial-gold plně styluje (tmavý overlay, min-height, absolutně napozicovaný hero obrázek, světlý text).
- Holé `.hero` téma nestyluje → injektovaný hero obrázek se zobrazí jako druhý pás a text je nečitelný.
- Referenční funkční vzor: stránka `podminky-uziti` používá `<section class="page-hero">`.

## Kroky

- [x] Reprodukovat lokálně přes `?theme_preview=editorial-gold`
- [x] Najít příčinu (mismatch hero třídy vs. téma)
- [x] Ověřit vzor funkční podstránky (page-hero)
- [x] Upravit šablonu Ceník v lokální DB: `hero` → `page-hero`, přidat `class="hero-sub"` na podnadpis
- [x] Upravit seed (seed_photographer.php) na stejný vzor
- [x] Verifikovat re-renderem (čitelný hero, jeden obrázek)
- [x] Připravit SQL pro produkci
- [x] Commit

## Poznámky

- Produkce je řízená z DB (templates), téma je soubor (deploy přes FTP).
- `page-hero` stylují jen editorial-gold a jemna-bezova; ostatní 3 témata ji nestylují — stejné chování jako u
  existující `podminky-uziti`, takže nejde o regresi.

## Výsledek

Šablona /cenik používala holé `<header class="hero">`, které aktivní téma editorial-gold nestyluje jako
podstránkový hero — injektovaný hero obrázek se proto zobrazil bez tmavého překryvu a text (bílý na světlé
fotce) byl nečitelný, plus vznikal dojem zdvojeného obrázku. Změněno na `<section class="page-hero">`
s `class="hero-sub"` na podnadpisu, čímž hero získává stejné tématové stylování jako ostatní podstránky
(tmavý overlay `::before`/`::after`, absolutně napozicovaný cover obrázek, světlý nadpis `--eg-text` a
podnadpis `#d5cec2`). Struktura renderu je teď identická s funkční stránkou `podminky-uziti`.

Změněno (přes git, žádné ruční SQL ani zásahy na serveru):
- `database/seeds/seed_photographer.php` (`$htmlCenik`) — hero → page-hero + `hero-sub`.

Nasazení na produkci: čistě přes git + seeder. Funkce `tpl()` v seederu je idempotentní upsert podle
`site_id + name` — při běhu seederu se existující šablona „Ceník" přepíše (UPDATE html_source), takže
oprava se propíše i do existující produkční DB bez resetu a bez ručního SQL. Postup: deploy commitu →
spustit seeder (admin Setup → načtení dat). Zdroj pravdy je seeder, ne ručně editovaná DB.
