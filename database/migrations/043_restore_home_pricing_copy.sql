UPDATE `content_blocks` b
JOIN `pages` p ON p.id = b.page_id
SET b.content = CASE b.block_key
  WHEN 'cenik_title' THEN 'Ceník'
  WHEN 'cenik_intro' THEN 'Orientační přehled cen. Každá zakázka je individuální.'
  ELSE b.content
END
WHERE p.url_path = ''
  AND b.block_key IN ('cenik_title', 'cenik_intro')
  AND COALESCE(TRIM(b.content), '') = '';
