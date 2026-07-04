# UKOL: Diagnostika obrazku na live webu

Datum: 2026-07-04
Zadani: Zjistit proc se obrazky na live webu stale nevykresluji, i kdyz request v prohlizeci vraci 200 OK a `Content-Type: image/webp`.

## Kroky

- [x] 1. Overit live URL obrazku a obsah odpovedi
- [x] 2. Porovnat live HTML/CSS s aktualnim kodem a zjistit, zda obrazky nekryje styl nebo runtime
- [x] 3. Opravit primarni pricinu bez tiche nahrady
- [x] 4. Overit vysledek a podle potreby commit/push

## Poznamky

- Success signal: konkretni live URL obrazku vraci platny obrazovy soubor a karta/sekce na homepage ho viditelne vykresli, ne jen placeholder s 200 odpovedi.
- Nalezeno: live obrazek vracel `EF BB BF` pred `RIFF`, tedy PHP posilalo UTF-8 BOM pred binarni WebP data.
- Oprava: odstraneny BOM z `public/index.php`, ktery obsluhuje `/media/...`; odstranen i BOM z `admin/views/settings/index.php`, aby se stejny problem nevracel v admin renderu.
- Overeno lokalne: media response zacina `RIFF....WEBP`, bez `EF BB BF`.

## Vysledek

Primarni pricina byla UTF-8 BOM na zacatku `public/index.php`, ktery obsluhuje `/media/...`. Po odstraneni BOM lokalni media response zacina validni WebP hlavickou `RIFF....WEBP`; stejne byl odstranen BOM z admin settings view.
