UPDATE `content_blocks` b
JOIN `pages` p ON p.id=b.page_id
SET b.content='Svatební fotograf a kameraman v Olomouci'
WHERE p.url_path IN ('', '/') AND b.block_key IN ('hero_heading', 'hero_h1');
