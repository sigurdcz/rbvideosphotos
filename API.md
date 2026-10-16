# API

REST API je dostupne na `/api/v1/*`. Vsechny endpointy vraceji JSON a vyzaduji autentizaci klicem z `.env`.

## Autentizace

```env
API_KEY=<vygeneruj: php -r "echo bin2hex(random_bytes(32));">
```

Podporovane zpusoby:

| Zpusob | Format |
| --- | --- |
| Header | `X-Api-Key: {klic}` |
| Bearer token | `Authorization: Bearer {klic}` |

Neplatny nebo chybejici klic vraci `401 Unauthorized`.

## Spolecne konvence

- `POST` a `PUT` pro JSON endpointy cekaji `Content-Type: application/json`
- upload medii pouziva `multipart/form-data`
- `PUT` ma patch-like semantiku; chybejici pole se zachovaji
- duplicitni slug vraci `409 Conflict`
- chybejici povinne pole vraci `400 Bad Request`
- nenalezeny zdroj vraci `404 Not Found`
- `OPTIONS` vraci `204 No Content`; CORS hlavicka `Access-Control-Allow-Origin` se posila jen pro origin odpovidajici `APP_URL`

## Dostupne endpointy

| Metoda | Endpoint | Popis |
| --- | --- | --- |
| `GET` | `/api/v1/articles` | seznam publikovanych clanku |
| `POST` | `/api/v1/articles` | vytvoreni clanku |
| `GET` | `/api/v1/articles/{slug}` | detail clanku |
| `PUT` | `/api/v1/articles/{slug}` | uprava clanku |
| `DELETE` | `/api/v1/articles/{slug}` | smazani clanku |
| `GET` | `/api/v1/portfolio` | seznam publikovanych portfolio polozek |
| `POST` | `/api/v1/portfolio` | vytvoreni portfolio polozky |
| `GET` | `/api/v1/portfolio/{slug}` | detail portfolio polozky |
| `PUT` | `/api/v1/portfolio/{slug}` | uprava portfolio polozky |
| `DELETE` | `/api/v1/portfolio/{slug}` | smazani portfolio polozky |
| `POST` | `/api/v1/media` | upload jednoho nebo vice souboru |
| `DELETE` | `/api/v1/media/{id}` | smazani media a jeho variant |
| `GET` | `/api/v1/key` | vrati aktualni API klic po uspesne autentizaci |

## Articles

### `GET /api/v1/articles`

Vraci vsechny publikovane clanky:

```json
{
  "posts": [],
  "total": 0
}
```

### `POST /api/v1/articles`

Telo:

| Pole | Typ | Povinne | Popis |
| --- | --- | --- | --- |
| `title` | `string` | ano | nadpis clanku |
| `slug` | `string` | ne | kdyz chybi, generuje se z `title` |
| `excerpt` | `string` | ne | kratky plain-text popis |
| `content` | `string` | ne | telo clanku, muze obsahovat HTML |
| `status` | `"draft"` / `"published"` | ne | vychozi `draft` |
| `sort_order` | `integer` | ne | rucni poradi ve vypisu; `1` = prvni, `0` = bez rucniho poradi (radi se podle data, za ocislovanymi) |
| `featured_image_id` | `integer` / `null` | ne | medium ID |
| `gallery_ids` | `integer[]` | ne | ID medii do galerie |
| `gallery_sort` | `object` | ne | poradi fotek v galerii jako `{"<media id>": n}`; bez nej plati poradi `gallery_ids` |
| `meta_title` | `string` / `null` | ne | SEO title |
| `meta_description` | `string` / `null` | ne | SEO popis |

Odpoved `201` vraci objekt `Article`.

### `GET /api/v1/articles/{slug}`

Vraci detail clanku podle slugu.

### `PUT /api/v1/articles/{slug}`

Vsechna pole jsou volitelna. Chovani je patch-like: neposlana pole se zachovaji.

### `DELETE /api/v1/articles/{slug}`

Vraci:

```json
{ "success": true, "deleted_slug": "muj-clanek" }
```

### Objekt `Article`

```json
{
  "id": 1,
  "title": "Nazev clanku",
  "slug": "nazev-clanku",
  "excerpt": "Kratky popis",
  "content": "<p>HTML obsah</p>",
  "featured_image_url": "https://example.cz/media/2026/05/foto.webp",
  "meta_title": "SEO title",
  "meta_description": "SEO popis",
  "status": "published",
  "published_at": "2026-05-18 12:00:00",
  "created_at": "2026-05-18 10:00:00",
  "updated_at": "2026-05-18 12:00:00"
}
```

## Portfolio

### `GET /api/v1/portfolio`

Vraci vsechny publikovane polozky:

```json
{
  "entries": [],
  "total": 0
}
```

### `POST /api/v1/portfolio`

Telo:

| Pole | Typ | Povinne | Popis |
| --- | --- | --- | --- |
| `title` | `string` | ano | nazev polozky |
| `slug` | `string` | ne | kdyz chybi, generuje se z `title` |
| `description` | `string` | ne | HTML popis |
| `video_url` | `string` / `null` | ne | jedno video; zkratka za `video_urls` s jednim prvkem |
| `video_urls` | `string[]` / `object[]` | ne | vsechna videa polozky; prvek je bud URL, nebo `{"url": "...", "sort_order": n}`. Hlavni video (dlazdice, lightbox, JSON-LD) je to s nejnizsim cislem. Nepodporovane adresy se zahodi. Kdyz pole neposlete, videa se nemeni |
| `status` | `"draft"` / `"published"` | ne | vychozi `draft` |
| `sort_order` | `integer` | ne | rucni poradi ve vypisu; `1` = prvni, `0` = bez rucniho poradi (zaradi se za ocislovane) |
| `media_ids` | `integer[]` | ne | galerie media ID |
| `media_sort` | `object` | ne | poradi fotek jako `{"<media id>": n}`; bez nej plati poradi `media_ids` |
| `meta_title` | `string` / `null` | ne | SEO title |
| `meta_description` | `string` / `null` | ne | SEO popis |

### `GET /api/v1/portfolio/{slug}`

Vraci detail polozky.

### `PUT /api/v1/portfolio/{slug}`

Vsechna pole jsou volitelna. Neposlana pole zustavaji beze zmeny.

### `DELETE /api/v1/portfolio/{slug}`

Vraci:

```json
{ "success": true, "deleted_slug": "moje-polozka" }
```

### Objekt `PortfolioEntry`

```json
{
  "id": 1,
  "title": "Svatba Jana a Petra",
  "slug": "svatba-jana-a-petra",
  "description": "<p>Popis</p>",
  "video_url": "https://vimeo.com/123456789",
  "video_embed_url": "https://player.vimeo.com/video/123456789",
  "video_thumbnail": "https://img.youtube.com/vi/xxx/hqdefault.jpg",
  "videos": [
    {
      "url": "https://vimeo.com/123456789",
      "embed_url": "https://player.vimeo.com/video/123456789",
      "thumbnail": "",
      "sort_order": 1
    }
  ],
  "photos": [
    {
      "url": "https://example.cz/media/2026/05/foto.webp",
      "alt": "Popis fotky",
      "width": 1920,
      "height": 1080
    }
  ],
  "meta_title": "SEO title",
  "meta_description": "SEO popis",
  "status": "published",
  "sort_order": 0,
  "published_at": "2026-05-18 12:00:00",
  "created_at": "2026-05-18 10:00:00",
  "updated_at": "2026-05-18 12:00:00"
}
```

## Media

### `POST /api/v1/media`

Pouziva `multipart/form-data`.

| Pole | Typ | Povinne | Popis |
| --- | --- | --- | --- |
| `file` nebo `files[]` | `file` / `file[]` | ano | jeden nebo vice souboru |
| `alt_text` | `string` | ano | alt text pro vsechny nahravane soubory |
| `title` | `string` | ne | kdyz chybi, pouzije se `alt_text` |

Aktualne povolene obrazky jsou `jpeg`, `png`, `webp` a `gif`. Video upload podporuje `mp4`, `mov`, `webm`, `avi` a `mkv`; server overuje MIME typ i priponu podle `config/app.php`.

Odpoved:

```json
{
  "results": [
    {
      "success": true,
      "media_id": 42,
      "storage_path": "storage/media/2026/05/foto.webp",
      "url": "/media/2026/05/foto.webp",
      "variant_count": 3,
      "name": "foto.jpg",
      "width": 1920,
      "height": 1080,
      "mime_type": "image/webp"
    }
  ]
}
```

Pri chybe konkretniho souboru vraci polozka `error`.

### `DELETE /api/v1/media/{id}`

Smaze medium vcetne vsech variant ze souboroveho systemu a DB.

## Vedlejsi efekty write operaci

| Operace | Vedlejsi efekt |
| --- | --- |
| `POST` / `PUT` article | sync homepage bloku pres `BlogSyncService` |
| `DELETE` article | odebrani clanku z homepage bloku |
| `POST` portfolio se stavem `published` | publikace statickeho HTML do `storage/published/nase-prace/` |
| `PUT` portfolio se stavem `published` | prepsani publikovanych souboru a sync homepage dlazdic |
| `DELETE` portfolio | smazani publikovanych souboru a sync homepage dlazdic |
| `POST` media | generovani variant `webp`, `medium`, `thumb` a `og`, pokud je dostupne GD |
| `DELETE` media | smazani vsech variant ze souboroveho systemu a DB |
