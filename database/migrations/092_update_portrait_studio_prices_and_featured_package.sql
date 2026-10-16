-- Apply the approved portrait/studio prices and move the featured package.
-- Historical pricing migrations remain immutable.

UPDATE content_blocks cb
JOIN pages p ON p.id = cb.page_id
SET cb.content = CASE cb.block_key
    WHEN 'cenik_card_4_badge' THEN 'Nejžádanější'
    WHEN 'cenik_card_5_badge' THEN ''
    WHEN 'cenik_card_6_price' THEN '2 000 Kč'
    WHEN 'cenik_card_7_price' THEN '3 000 Kč'
    WHEN 'cenik_faq_4_answer' THEN 'Ano. Portrétní a rodinné focení stojí 2 000 Kč. Fotografování v ateliéru stojí 3 000 Kč.'
    ELSE cb.content
END,
cb.updated_at = CURRENT_TIMESTAMP
WHERE TRIM(BOTH '/' FROM p.url_path) = 'cenik'
  AND cb.block_key IN (
      'cenik_card_4_badge',
      'cenik_card_5_badge',
      'cenik_card_6_price',
      'cenik_card_7_price',
      'cenik_faq_4_answer'
  );

UPDATE content_blocks cb
JOIN pages p ON p.id = cb.page_id
SET cb.content = REPLACE(
        REPLACE(cb.content, '1 800 Kč', '2 000 Kč'),
        '2 600 Kč',
        '3 000 Kč'
    ),
    cb.updated_at = CURRENT_TIMESTAMP
WHERE TRIM(BOTH '/' FROM p.url_path) = 'cenik'
  AND cb.block_key IN ('pricing_packages', 'pricing_body');

UPDATE content_blocks cb
JOIN pages p ON p.id = cb.page_id
SET cb.content = REPLACE(
        REPLACE(
            cb.content,
            '<article class="ckp-card"><span class="ckp-category">Svatební focení</span><h3 class="ckp-title">Celodenní svatební focení</h3>',
            '<article class="ckp-card ckp-card--featured"><span class="ckp-badge">Nejžádanější</span><span class="ckp-category">Svatební focení</span><h3 class="ckp-title">Celodenní svatební focení</h3>'
        ),
        '<article class="ckp-card ckp-card--featured"><span class="ckp-badge">Nejžádanější</span><span class="ckp-category">Kompletní balíček</span><h3 class="ckp-title">Kompletní balíček foto + video</h3>',
        '<article class="ckp-card"><span class="ckp-category">Kompletní balíček</span><h3 class="ckp-title">Kompletní balíček foto + video</h3>'
    ),
    cb.updated_at = CURRENT_TIMESTAMP
WHERE TRIM(BOTH '/' FROM p.url_path) = 'cenik'
  AND cb.block_key IN ('pricing_packages', 'pricing_body');
