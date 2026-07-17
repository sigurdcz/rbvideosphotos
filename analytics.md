# Analytics snapshot

Zdroj: uzivatelem dodane screenshoty GA4, prehled technologie a detail `Rozliseni obrazovky`.

Obdobi reportu: **19. 1. 2025 - 16. 7. 2026**. Tento dokument je staticky zaznam predanych dat, ne zivy export z GA4.

## Zarizeni

| Kategorie | Podil |
| --- | ---: |
| Mobile | 51,8 % |
| Desktop | 47,4 % |
| Tablet | 0,8 % |

Mobil a desktop dohromady tvori 99,2 % zaznamenane navstevnosti. QA proto povinne pokryva jeden reprezentativni telefon a jeden desktop; tablet se testuje jen pri konkretni chybe.

## Platforma a operacni system

- Platforma: Web 100,0 %.
- Platforma/kategorie zarizeni: web/mobile 576, web/desktop 528, web/tablet 9 aktivnich uzivatelu.
- Operacni systemy: iOS 337, Windows 303, Android 248, Linux 140, Macintosh 80, Chrome OS 2.
- Nejvyraznejsi prohlizece: Chrome, Safari a Safari in-app WebView.

## Rozliseni obrazovky

| Poradi | Rozliseni | Aktivni uzivatele | Podil | Mira zapojeni | Prumerna doba zapojeni | Pocet udalosti |
| ---: | --- | ---: | ---: | ---: | ---: | ---: |
| 1 | 2000 x 2000 | 165 | 14,97 % | 32,93 % | 2 s | 547 |
| 2 | 1920 x 1080 | 150 | 13,61 % | 42,28 % | 44 s | 2 195 |
| 3 | 390 x 844 | 90 | 8,17 % | 45,00 % | 13 s | 447 |
| 4 | 393 x 852 | 79 | 7,17 % | 49,49 % | 26 s | 469 |
| 5 | 800 x 600 | 71 | 6,44 % | 9,86 % | 1 s | 222 |
| 6 | 1280 x 720 | 43 | 3,90 % | 9,30 % | 2 s | 141 |

## Zvolene QA viewporty

| Ucel | CSS viewport | Duvod |
| --- | --- | --- |
| Telefon | **390 x 844** | Nejvyssi samostatne uvedene mobilni rozliseni v reportu (90 aktivnich uzivatelu); reprezentuje hlavni mobilni sirku vedle velmi blizkeho 393 x 852. |
| PC | **1920 x 1080** | Nejsilnejsi bezny desktopovy format v reportu: 150 aktivnich uzivatelu, 42,28 % mira zapojeni a 44 s prumerneho zapojeni. |

`2000 x 2000` neni zvolen jako desktopovy QA cil: sam report nedokazuje, zda jde o standardni desktopovy viewport; ma take pouze 2 s prumerneho zapojeni. `800 x 600` a `1280 x 720` maji nizkou miru i dobu zapojeni, proto nejsou prioritni referencni viewporty.
