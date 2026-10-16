-- Add the eighth pricing card: video-only coverage of a full wedding day.
-- Cards 1-7, their prices, the `Nejžádanější` badge (card 4) and the FAQ stay
-- untouched. Historical pricing migrations remain immutable.
--
-- As in 074-077, `content` is never overwritten on conflict, so a re-run (or a
-- database where the admin already edited card 8) keeps the edited text.
INSERT INTO `content_blocks`
  (`page_id`, `block_key`, `block_type`, `label`, `content`, `autofill_disabled`, `sort_order`)
SELECT p.id, src.block_key, src.block_type, src.label, src.content, 1, src.sort_order
FROM `pages` p
JOIN (
  SELECT 'cenik_card_8_title' AS block_key, 'text' AS block_type, 'Karta 8 - title' AS label, 'Jen natáčení celodenní svatby' AS content, 66 AS sort_order
  UNION ALL SELECT 'cenik_card_8_desc', 'textarea', 'Karta 8 - desc', 'Celodenní svatební video bez focení zachytí atmosféru celého dne od důležitých okamžiků až po přirozené emoce hostů. Vhodné pro páry, které chtějí mít ze svatby živou vzpomínku v pohybu.\n\nObsahuje:\n• natáčení celého svatebního dne\n• zachycení obřadu, hostů a atmosféry\n• reportážní styl videa', 67
  UNION ALL SELECT 'cenik_card_8_price', 'text', 'Karta 8 - price', '17 000 Kč', 68
  UNION ALL SELECT 'cenik_card_8_unit', 'text', 'Karta 8 - unit', 'celý den', 69
  UNION ALL SELECT 'cenik_card_8_category', 'text', 'Karta 8 - category', 'Svatební video', 70
  UNION ALL SELECT 'cenik_card_8_badge', 'text', 'Karta 8 - badge', '', 71
  UNION ALL SELECT 'cenik_card_8_cta_label', 'text', 'Karta 8 - cta_label', 'Poptat natáčení svatby', 72
  UNION ALL SELECT 'cenik_card_8_cta_url', 'text', 'Karta 8 - cta_url', '/kontakt?sluzba=jen-natoceni-svatby', 73
) src
WHERE TRIM(BOTH '/' FROM p.url_path) = 'cenik'
ON DUPLICATE KEY UPDATE
  `block_type` = VALUES(`block_type`),
  `label` = VALUES(`label`),
  `sort_order` = VALUES(`sort_order`);

-- Legacy static fallback blocks: append the same card once, before the closing
-- grid tag, exactly as DemoContentSeeder::seedRealPricingContent() renders it.
UPDATE content_blocks cb
JOIN pages p ON p.id = cb.page_id
SET cb.content = CONCAT(
        SUBSTRING(cb.content, 1, CHAR_LENGTH(cb.content) - CHAR_LENGTH('</div>')),
        '<article class="ckp-card"><span class="ckp-category">Svatební video</span><h3 class="ckp-title">Jen natáčení celodenní svatby</h3><div class="ckp-price">17 000 Kč</div><p class="ckp-desc">Celodenní svatební video bez focení zachytí atmosféru celého dne od důležitých okamžiků až po přirozené emoce hostů. Vhodné pro páry, které chtějí mít ze svatby živou vzpomínku v pohybu.</p><ul class="ckp-feats"><li>natáčení celého svatebního dne</li><li>zachycení obřadu, hostů a atmosféry</li><li>reportážní styl videa</li></ul><a href="/kontakt?sluzba=jen-natoceni-svatby" class="btn-primary">Poptat natáčení svatby</a></article>',
        '</div>'
    ),
    cb.label = CASE WHEN cb.label = 'Ceník - sedm reálných balíčků' THEN 'Ceník - osm reálných balíčků' ELSE cb.label END,
    cb.updated_at = CURRENT_TIMESTAMP
WHERE TRIM(BOTH '/' FROM p.url_path) = 'cenik'
  AND cb.block_key IN ('pricing_packages', 'pricing_body')
  AND cb.content LIKE '%</article></div>'
  AND cb.content NOT LIKE '%Jen natáčení celodenní svatby%';
