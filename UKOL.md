# UKOL: Hero foto NEBO video (s fallbackem)

Datum: 2026-06-30
Zadání: Umožnit zadat hero foto i hero video; když je video prázdné, použít foto (pokud zadané).
Hotovo když: Admin má foto pole vedle videa; rendering priorita page video → page foto → default video. Lint OK.

## Kroky

- [ ] Admin: přidat hero_bg (foto) pole vedle hero_video v page formu
- [ ] index.php: injectPageHeroVideo 3-úrovňová priorita + buildHeroImageHtml
- [ ] PublishService.php: stejná logika (statický publish)
- [ ] PHP lint
- [ ] Commit

## Poznámky

- hero_video block + hero_bg block už existují. hero_bg se ukazuje jen na pricing stránce — přidám ho i k hero kartě.
- Foto se vykreslí jako `.theme-hero-video` div s background-image (reuse hero stylů: cover + overlay).
- Priorita: page hero_video → page hero_bg foto → site default_hero_video. Foto má přednost před default videem.
- Dvě paralelní implementace (index.php dynamic, PublishService static) — fix v obou.

## Výsledek

[Vyplnit po dokončení]
