# UKOL: Refactoring šablonování na PHTML systém — analýza a návrh

Datum: 2026-07-17
Zadání: Analyzovat současné šablonování (hardcoded HTML v PHP) a navrhnout čistý PHTML templating systém s per-page/per-archetype overridy, včetně plánu implementace.
Hotovo když: Existuje kompletní dokument s analýzou současného stavu, návrhem architektury PHTML systému (flow diagram), fázovaným plánem refactoringu a implementačními kontrakty/konvencemi.

## Kroky

- [x] Analýza současného templatingu: layout flow (`sites.layout_html`, `applyThemeTemplate`), theme registry, publish pipeline
- [x] Analýza speciálních rendererů (sluzby, faq, kontakt, blog, portfolio, cenik) a míst s hardcoded HTML v PHP
- [x] Analýza admin integrace (inline editor, content_blocks, `data-block`) a frontend request flow
- [x] Návrh architektury PHTML systému: View/TemplateEngine třída, struktura souborů, override resoluce, escaping, data kontrakty
- [x] Identifikace integračních bodů a dopadů (admin, frontend, publish, témata, média)
- [x] Sestavení fázovaného plánu implementace s prioritami
- [x] Sepsání implementačních kontraktů a konvencí (podklad pro implementaci)
- [x] Finalizace dokumentu návrhu a commit

## Poznámky

- Bez frameworků (Laravel/Symfony/ORM/npm build) — čistý PHP 8+, PDO, Composer autoload `App\`.
- Bezpečnost: prepared statements, CSRF, `realpath()` path validace, escaping v šablonách.
- Témata nesmí být zdrojem obsahových dat — obsah vždy z DB (`content_blocks`, `service_items`, `faq_items`, `contact_info`, `blog_posts`, `portfolio_entries`).
- Tento úkol je analýza + návrh; implementace následuje po schválení návrhu.
- Předchozí úkol (EW3 QA loop) je zachován v git historii — poslední commit d9cc38f.

## Výsledek

Vytvořen `TEMPLATE_REFACTOR_PLAN.md` — kompletní podklad pro refactoring šablonování:

1. **Analýza**: duplikovaný render pipeline live (`public/index.php`, 3 673 ř.) vs. publish (`PublishService`, `PortfolioPublishService`) s ~50 funkcemi ve 2–3 kopiích; speciální stránky (faq/kontakt/sluzby/blog/portfolio) celé v heredocích; ~20 regex `inject*` post-průchodů; theme hacky a ~800 ř. CSS v PHP core.
2. **Architektura**: `App\View\{ViewEngine, ViewResolver, ViewContext, PageComposer}` + `App\ViewModels\*` (čistá data, žádné HTML); PHTML views v `app/Views/{layouts,archetypes,partials}` s override kaskádou theme-page → theme-archetype → core-page → core-archetype; theme manifest v2 (`views_dir`, `overrides.<pageKey>.view`); legacy `layout.html` přes adaptér.
3. **Plán**: Fáze 0 golden-master testy → 1 view engine → 2 sjednocení pipeline → 3 layout/chrome do PHTML → 4 speciální archetypy (faq→…→kontakt) → 5 homepage+generické stránky → 6 theme overridy a úklid. Jeden archetyp = jeden commit `refactor(templates): ...`.
4. **Kontrakty**: společný view-data obal + archetypové klíče, bezpečnostní pravidla view vrstvy (realpath containment, escaping default, CSRF z kontraktu), zachování inline editoru přes `inlineAttr()`.

Implementace zatím neproběhla — čeká se na schválení návrhu.
