-- Shorten the visible About-page hero heading for existing installations.
-- Team identities and SEO metadata remain unchanged outside this presentation copy.

UPDATE content_blocks b
JOIN pages p ON p.id = b.page_id
SET b.content = 'Fotograf a kameraman z Olomouce.'
WHERE p.url_path IN ('o-nas', '/o-nas')
  AND b.block_key IN ('hero_h1', 'hero_heading');
