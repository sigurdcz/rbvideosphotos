-- Production drift found via live audit (2026-09-22): meta_title, og_title,
-- twitter_title and schema_name for homepage/sluzby had reverted to the
-- original pre-078 DemoContentSeeder defaults (e.g. "Svatební fotograf a
-- kameraman Olomouc", which breaks the "svatební fotograf olomouc" phrase
-- adjacency), while meta_description/og_description/twitter_description/
-- schema_description correctly carried 081's newer copy. Something replayed
-- the demo SEO content (DemoContentSeeder::seedPageSeo, migration markers
-- 050/055) between 078 and 081 - that REPLACE INTO writes every column,
-- including the title-family ones 081 didn't re-touch because it assumed
-- 078's title was still intact. This reasserts the full, current set of
-- title-family fields so the page is consistent again regardless of what
-- caused the drift.
UPDATE `seo_settings` s
JOIN `pages` p ON p.id = s.page_id
SET
  s.meta_title = CASE p.url_path
    WHEN '' THEN 'Svatební fotograf Olomouc – foto a video ze svatby | RB Videos & Photos'
    WHEN 'sluzby' THEN 'Svatební fotograf Olomouc – přehled služeb (foto i video) | RB Videos & Photos'
    ELSE s.meta_title
  END,
  s.og_title = CASE p.url_path
    WHEN '' THEN 'Svatební fotograf Olomouc – foto a video ze svatby | RB Videos & Photos'
    WHEN 'sluzby' THEN 'Svatební fotograf Olomouc – přehled služeb (foto i video) | RB Videos & Photos'
    ELSE s.og_title
  END,
  s.twitter_title = CASE p.url_path
    WHEN '' THEN 'Svatební fotograf Olomouc – foto a video ze svatby | RB Videos & Photos'
    WHEN 'sluzby' THEN 'Svatební fotograf Olomouc – přehled služeb (foto i video) | RB Videos & Photos'
    ELSE s.twitter_title
  END,
  s.schema_name = CASE p.url_path
    WHEN '' THEN 'Svatební fotograf Olomouc – foto a video ze svatby | RB Videos & Photos'
    WHEN 'sluzby' THEN 'Svatební fotograf Olomouc – přehled služeb (foto i video) | RB Videos & Photos'
    ELSE s.schema_name
  END
WHERE p.url_path IN ('', 'sluzby');
