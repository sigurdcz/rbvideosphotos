-- O nas, part two: the values, reviews, trust, stories and closing CTA copy.
--
-- Same contract as 074: the theme template already reads each key and falls
-- back to the literal it used to print, so this only creates the rows that make
-- the text reachable from the admin and the inline editor. `content` is never
-- overwritten on conflict, so re-running cannot undo an admin edit.
--
-- The story cards themselves are real blog posts: their title and excerpt are
-- edited through the existing blog:<id>:title / blog:<id>:excerpt inline keys,
-- not through content blocks, so only the section's fixed labels appear here.
INSERT INTO `content_blocks`
  (`page_id`, `block_key`, `block_type`, `label`, `content`, `autofill_disabled`, `sort_order`)
SELECT
  p.id,
  src.block_key,
  src.block_type,
  src.label,
  src.content,
  0,
  src.sort_order
FROM `pages` p
JOIN (
  SELECT 'about_values_heading' AS block_key, 'text' AS block_type, 'Hodnoty - nadpis sekce' AS label, 'Naše zkušenosti a přístup' AS content, 33 AS sort_order
  UNION ALL SELECT 'about_value_1_title', 'text', 'Hodnota 1 - nadpis', 'Profesionální technika', 34
  UNION ALL SELECT 'about_value_2_title', 'text', 'Hodnota 2 - nadpis', 'Individuální přístup', 35
  UNION ALL SELECT 'about_value_3_title', 'text', 'Hodnota 3 - nadpis', 'Důvěra a diskrétnost', 36
  UNION ALL SELECT 'about_value_4_title', 'text', 'Hodnota 4 - nadpis', 'Vkus a cit pro detail', 37
  UNION ALL SELECT 'about_reviews_heading', 'text', 'Reference - nadpis sekce', 'Reference klientů', 38
  UNION ALL SELECT 'about_trust_heading', 'text', 'Důvěra - nadpis sekce', 'Důvěra a transparentnost', 39
  UNION ALL SELECT 'about_trust_link_1', 'text', 'Důvěra - odkaz 1', 'IČO a fakturační údaje', 40
  UNION ALL SELECT 'about_trust_link_2', 'text', 'Důvěra - odkaz 2', 'Obchodní podmínky', 41
  UNION ALL SELECT 'about_trust_link_3', 'text', 'Důvěra - odkaz 3', 'GDPR ochrana', 42
  UNION ALL SELECT 'about_trust_link_4', 'text', 'Důvěra - odkaz 4', 'Orientační ceník', 43
  UNION ALL SELECT 'about_trust_link_5', 'text', 'Důvěra - odkaz 5', 'Dodací termíny', 44
  UNION ALL SELECT 'about_trust_link_6', 'text', 'Důvěra - odkaz 6', 'Rezervační postup', 45
  UNION ALL SELECT 'about_stories_heading', 'text', 'Příběhy - nadpis sekce', 'Příběhy z praxe / případové studie', 46
  UNION ALL SELECT 'about_stories_kicker', 'text', 'Příběhy - štítek karty', 'Blog', 47
  UNION ALL SELECT 'about_stories_link_label', 'text', 'Příběhy - text odkazu', 'Zobrazit příběh ›', 48
  UNION ALL SELECT 'about_cta_heading', 'text', 'Závěrečné CTA - nadpis', 'Pojďme si vytvořit vzpomínky na celý život', 49
  UNION ALL SELECT 'about_cta_primary', 'text', 'Závěrečné CTA - tlačítko 1', 'Získat nabídku', 50
  UNION ALL SELECT 'about_cta_secondary', 'text', 'Závěrečné CTA - tlačítko 2', 'Napište nám', 51
) src
WHERE p.url_path = 'o-nas'
ON DUPLICATE KEY UPDATE
  `block_type` = VALUES(`block_type`),
  `label` = VALUES(`label`),
  `sort_order` = VALUES(`sort_order`);
