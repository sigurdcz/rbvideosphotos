# UKOL: Blog clanky v sitemap

Datum: 2026-07-03
Zadani: Doplnit publikovane blog clanky do sitemap.xml jako samostatne URL na root slugu, bez prefixu `/blog/`.

## Kroky

- [x] Zapsat aktualni zadani do UKOL.md
- [x] Projit sitemap generator a publish pipeline
- [x] Doplnit publikovane blog clanky do sitemap vstupu
- [x] Overit syntax a generovani URL bez `/blog/`
- [x] Commit a push

## Poznamky

- Akceptace: v sitemap ma byt napr. `https://rbvideosphotos.kvalitne.cz/jak-vybrat-misto-na-foceni`, ne `/blog/jak-vybrat-misto-na-foceni`.
- Aktualne se do sitemap predavaji jen CMS stranky, proto je tam pouze `/blog/`.
- `PublishService` pridava publikovane `blog_posts` do sitemap jako virtualni polozky s `url_path = slug`.
- Test `SitemapGenerator` vratil `https://rbvideosphotos.kvalitne.cz/jak-vybrat-misto-na-foceni/`; bez `/blog/`, s koncovym lomitkem jako ostatni sitemap URL.

## Vysledek

Oprava je dokoncena: pri regeneraci sitemap se k publikovanym CMS strankam pridaji publikovane blog clanky jako root slugy. Overeno pres `php -l app/Services/PublishService.php`, `git diff --check` a izolovany test `SitemapGenerator`.
