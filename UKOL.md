# UKOL: Update README a ARCHITECTURE.md

Datum: 2026-05-18
Zadání: Aktualizovat README.md a ARCHITECTURE.md o vše co přibylo — téma systém, minifikátor, llms.txt, tracking, settings bug fix.

## Kroky

- [x] Aktualizovat README.md
- [x] Aktualizovat ARCHITECTURE.md
- [x] Commitnout výsledek

## Poznámky

Co přibylo od posledního update:

- Templates/ adresář — 5 šablon (jemna-bezova, soft-memories, modern-atelier, minimal-dark, editorial-gold)
- ThemeTemplateRegistry — správa šablon témat
- app/Support/AssetMinifier.php — regex minifikátor CSS/JS
- app/Support/FrontendAssetManager.php — zapisuje minifikované CSS/JS do public/assets/generated/
- app/Services/LlmsTxtGenerator.php — generuje /llms.txt dle llmstxt.org spec
- PublishService — při publishování čistí generated/ a generuje llms.txt
- tracking.js — konverze bez /dekujeme: ?sent=1 detekce, generate_lead GA4, fbq Contact pro social
- SiteRepository — SHOW COLUMNS místo information_schema, +6 tracking sloupců
- DB: tabulky reviews, faq_items, service_items, contact_info, blog_media (chybí v docs)

## Výsledek

README.md: přidána sekce Témata, CSS/JS minifikace, /llms.txt, konverzní tracking, public/assets/generated/ v adresářové struktuře, app/Support/ namespace.
ARCHITECTURE.md: přidány tabulky (faq_items, service_items, contact_info, reviews, blog_media), sekce Témata, CSS/JS minifikace, konverzní tracking; klíčové sloupce sites; všechny tabulky přepsány do compact formátu, code blocky dostaly language identifikátor.
