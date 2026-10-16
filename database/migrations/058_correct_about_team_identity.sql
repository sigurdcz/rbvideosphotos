-- Correct the About hero identity and related metadata for existing installations.
-- Historical migrations remain immutable; current seed sources contain the same copy.

UPDATE content_blocks b
JOIN pages p ON p.id = b.page_id
SET b.content = 'Zdeněk Černohlávek a Tomáš Šincl - fotograf a kameraman z Olomouce'
WHERE p.url_path IN ('o-nas', '/o-nas')
  AND b.block_key IN ('hero_h1', 'hero_heading');

UPDATE seo_settings s
JOIN pages p ON p.id = s.page_id
SET s.meta_description = 'Poznejte fotografa Zdeňka Černohlávka a kameramana Tomáše Šincla z RB Videos & Photos. Sehraná dvojice pro přirozené fotografie a filmové video.',
    s.og_description = 'Zdeněk Černohlávek a Tomáš Šincl - fotograf a kameraman z Olomouce pro přirozené fotografie a filmové video.',
    s.twitter_description = 'Zdeněk Černohlávek a Tomáš Šincl - fotograf a kameraman z Olomouce pro přirozené fotografie a filmové video.'
WHERE p.url_path IN ('o-nas', '/o-nas');
