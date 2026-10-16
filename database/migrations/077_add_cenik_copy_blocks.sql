-- Cenik: the last section labels that were still hardcoded in the theme view.
--
-- The pricing cards, the FAQ pairs, the transport copy and the closing CTA
-- already lived in `content_blocks` (they only lacked admin fields and inline
-- wiring, which the form and the archetype now provide). What had no database
-- row at all were the five fixed labels below, so only those are created here.
--
-- As in 074-076, `content` is never overwritten on conflict.
INSERT INTO `content_blocks`
  (`page_id`, `block_key`, `block_type`, `label`, `content`, `autofill_disabled`, `sort_order`)
SELECT p.id, src.block_key, src.block_type, src.label, src.content, 0, src.sort_order
FROM `pages` p
JOIN (
  SELECT 'cenik_packages_kicker' AS block_key, 'text' AS block_type, 'Balíčky - štítek nad nadpisem' AS label, 'Ceník služeb' AS content, 100 AS sort_order
  UNION ALL SELECT 'cenik_transport_kicker', 'text', 'Doprava - štítek nad nadpisem', 'Doprava', 101
  UNION ALL SELECT 'cenik_faq_kicker', 'text', 'Časté otázky - štítek nad nadpisem', 'Časté otázky', 102
  UNION ALL SELECT 'cenik_faq_heading', 'text', 'Časté otázky - nadpis', 'Nejčastější dotazy k cenám', 103
  UNION ALL SELECT 'cenik_cta_kicker', 'text', 'Závěrečné CTA - štítek', 'Rezervace termínu', 104
  UNION ALL SELECT 'cenik_cta_button', 'text', 'Závěrečné CTA - záložní tlačítko', 'Nezávazná poptávka', 105
) src
WHERE p.url_path = 'cenik'
ON DUPLICATE KEY UPDATE
  `block_type` = VALUES(`block_type`),
  `label` = VALUES(`label`),
  `sort_order` = VALUES(`sort_order`);
