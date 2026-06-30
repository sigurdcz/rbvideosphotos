# UKOL: Reálná demo data (fotky + videa) + SEO

Datum: 2026-06-30
Zadání: Nahrát reálné placeholder fotky/videa do media a sekcí (portfolio, blog, hero), doplnit SEO všech stránek.
Hotovo když: Fotky jsou v media library + přiřazené do sekcí, videa v portfoliu, SEO doplněné, lint OK.

## Kroky

- [ ] Přesun demo_data/ → database/seeds/demo_assets/
- [ ] DemoContentSeeder: ingestDemoAsset() (lokální soubor → media)
- [ ] Nahrát všechny fotky do media library (uživatel je má k dispozici)
- [ ] Portfolio: reálné fotky (dle tématu) + svatební videa z videa.txt
- [ ] Blog: featured fotky
- [ ] Hero pozadí stránek (kde chybí)
- [ ] SEO: meta_title/description pro všechny stránky
- [ ] PHP lint + commit

## Poznámky

- ingestUploadedFile(..., requireUploadedFile:false) kopíruje lokální soubor → media.
- 17 fotek (svatba, rodinné, portrét, těhotenské, dětské, koncert, auto) + 19 videí (videa.txt).
- Mapování fotek na portfolio dle tématu (svatba→wedding, rodinné→family, atd.).

## Výsledek

[Vyplnit po dokončení]
