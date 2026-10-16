-- Blog and Nase prace listings: make their own copy editable.
--
-- Both listing archetypes are rendered from code (BlogListingData /
-- PortfolioListingData) and until now only their H1 and hero subtitle came from
-- the database. Everything else - the kickers, the portfolio filter labels, the
-- section heading, the empty-state sentence and the whole closing CTA band -
-- was hardcoded in the theme view. The views now read these keys and fall back
-- to the same literals, so this migration only creates the rows.
--
-- As in 074/075, `content` is never overwritten on conflict.
INSERT INTO `content_blocks`
  (`page_id`, `block_key`, `block_type`, `label`, `content`, `autofill_disabled`, `sort_order`)
SELECT p.id, src.block_key, src.block_type, src.label, src.content, 0, src.sort_order
FROM `pages` p
JOIN (
  SELECT 'listing_kicker' AS block_key, 'text' AS block_type, 'Hero - štítek nad nadpisem' AS label, 'Blog' AS content, 10 AS sort_order
  UNION ALL SELECT 'blog_list_kicker', 'text', 'Výpis - štítek nad články', 'Nejnovější články', 11
  UNION ALL SELECT 'blog_empty_text', 'text', 'Výpis - text při prázdném blogu', 'Zatím žádné články.', 12
  UNION ALL SELECT 'blog_more_label', 'text', 'Karta článku - text odkazu', 'Číst článek', 13
  UNION ALL SELECT 'route_cta_kicker', 'text', 'Závěrečné CTA - štítek', 'Váš den, váš příběh', 14
  UNION ALL SELECT 'route_cta_heading', 'text', 'Závěrečné CTA - nadpis', 'Chcete podobné vzpomínky?', 15
  UNION ALL SELECT 'route_cta_text', 'textarea', 'Závěrečné CTA - text', 'Řekněte nám o svém termínu a představě. Společně vybereme focení nebo video, které vám bude sedět.', 16
  UNION ALL SELECT 'route_cta_button', 'text', 'Závěrečné CTA - tlačítko', 'Nezávazná poptávka', 17
) src
WHERE p.url_path = 'blog'
ON DUPLICATE KEY UPDATE
  `block_type` = VALUES(`block_type`),
  `label` = VALUES(`label`),
  `sort_order` = VALUES(`sort_order`);

INSERT INTO `content_blocks`
  (`page_id`, `block_key`, `block_type`, `label`, `content`, `autofill_disabled`, `sort_order`)
SELECT p.id, src.block_key, src.block_type, src.label, src.content, 0, src.sort_order
FROM `pages` p
JOIN (
  SELECT 'listing_kicker' AS block_key, 'text' AS block_type, 'Hero - štítek nad nadpisem' AS label, 'Portfolio' AS content, 10 AS sort_order
  UNION ALL SELECT 'portfolio_filter_aria', 'text', 'Filtr - popis pro čtečky', 'Filtr portfolia', 11
  UNION ALL SELECT 'portfolio_filter_all', 'text', 'Filtr - tlačítko 1', 'Vše', 12
  UNION ALL SELECT 'portfolio_filter_photo', 'text', 'Filtr - tlačítko 2', 'Fotky', 13
  UNION ALL SELECT 'portfolio_filter_video', 'text', 'Filtr - tlačítko 3', 'Videa', 14
  UNION ALL SELECT 'portfolio_list_kicker', 'text', 'Výpis - štítek nad nadpisem', 'Foto i video příběhy', 15
  UNION ALL SELECT 'portfolio_list_heading', 'text', 'Výpis - nadpis', 'Naše práce', 16
  UNION ALL SELECT 'portfolio_empty_text', 'text', 'Výpis - text při prázdné kategorii', 'V teto kategorii zatim neni publikovany obsah.', 17
  UNION ALL SELECT 'route_cta_kicker', 'text', 'Závěrečné CTA - štítek', 'Máte podobnou představu?', 18
  UNION ALL SELECT 'route_cta_heading', 'text', 'Závěrečné CTA - nadpis', 'Proberme váš termín společně', 19
  UNION ALL SELECT 'route_cta_text', 'textarea', 'Závěrečné CTA - text', 'Napište nám pár slov o tom, co plánujete. Nezávazně vám poradíme a domluvíme se na dalším postupu.', 20
  UNION ALL SELECT 'route_cta_button', 'text', 'Závěrečné CTA - tlačítko', 'Nezávazná konzultace', 21
) src
WHERE p.url_path = 'nase-prace'
ON DUPLICATE KEY UPDATE
  `block_type` = VALUES(`block_type`),
  `label` = VALUES(`label`),
  `sort_order` = VALUES(`sort_order`);
