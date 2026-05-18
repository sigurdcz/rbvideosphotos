# UKOL: Výzkum llms.txt + implementace pro web

Datum: 2026-05-18
Zadání: Zjistit, zda existuje standard llms.txt pro AI/LLM (jako robots.txt pro crawlery) a případně ho implementovat.

## Kroky

- [x] Nastudovat llms.txt — co to je, kdo ho definoval, jak vypadá
- [x] Zjistit, zda existují alternativy (ai.txt, llm-instructions.txt, atp.)
- [x] Rozhodnout, co implementovat pro rbvideosphotos.kvalitne.cz
- [x] Implementovat llms.txt do projektu
- [x] Commitnout výsledek

## Poznámky

- llms.txt navržen Jeremy Howardem (Answer.AI) v 2024, spec: <https://llmstxt.org/>
- Alternativy: ai.txt (jiný standard, méně rozšířený), llms-full.txt (rozšířená verze)
- Rozhodnutí: implementovat llms.txt (llmstxt.org spec) — jednoduchý Markdown se seznamem stránek
- Generuje se při každém globálním publish (publishSite) vedle robots.txt a sitemap.xml
- Servíruje se přes existující static file handler (index.php → storage/published/llms.txt)
- ai.txt je jiná věc (meta značky pro AI crawlery) — neimplementováno, není nutné

## Výsledek

- Nový soubor: `app/Services/LlmsTxtGenerator.php` — generuje /llms.txt dle llmstxt.org spec
  (H1 název webu, blockquote popis, seznam stránek s URL a meta popisem, odkaz na sitemap)
- `PublishService::regenerateSitemapAndRobots` — přidáno generování llms.txt
- `public/index.php` a `.htaccess` — beze změny, .txt soubory se servírují automaticky
- Soubor vznikne automaticky při nejbližším globálním publish webu
