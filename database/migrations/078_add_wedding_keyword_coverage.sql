-- SEO audit (2026-09-21) found 0/100 keyword coverage: the target phrases
-- "svatební fotograf Olomouc" and "video svatba" appeared in none of
-- title/H1/meta/text on the homepage (the only page the audit crawls).
-- This adds both phrases to the homepage title/meta/H1 and, for
-- consistency, to the sluzby hub's title/meta, following the same
-- per-url_path UPDATE pattern as 047_complete_current_seo_metadata.sql.
--
-- "video svatba" is not idiomatic Czech word order (natural phrasing is
-- "svatební video"); it is placed literally in meta_keywords only, where
-- exact-match keyword strings are conventional, rather than forced into
-- visible copy where it would read as stuffing - the audit already flags
-- this site for keyword stuffing on "focení".
--
-- The H1 update is guarded by the current known content so a site whose
-- admin already rewrote this heading keeps their wording.
UPDATE `content_blocks` cb
JOIN `pages` p ON p.id = cb.page_id
SET cb.`content` = 'Svatební fotograf Olomouc a video ze svatby'
WHERE p.url_path = ''
  AND cb.`block_key` = 'hero_h1'
  AND cb.`content` = 'Svatební fotograf a kameraman v Olomouci';

UPDATE `seo_settings` s
JOIN `pages` p ON p.id = s.page_id
SET
  s.meta_title = CASE p.url_path
    WHEN '' THEN 'Svatební fotograf Olomouc – foto a video ze svatby | RB Videos & Photos'
    WHEN 'sluzby' THEN 'Svatební fotograf Olomouc – přehled služeb (foto i video) | RB Videos & Photos'
    ELSE s.meta_title
  END,
  s.meta_description = CASE p.url_path
    WHEN '' THEN 'Svatební fotograf Olomouc a profesionální video ze svatby – zachytíme váš den citlivě a přirozeně, v Olomouci i po celé ČR.'
    WHEN 'sluzby' THEN 'Svatební fotograf Olomouc i svatební video na míru, dále rodinné, portrétní a reklamní focení. Vyberte si službu a domluvte si nezávaznou konzultaci.'
    ELSE s.meta_description
  END,
  s.meta_keywords = CASE p.url_path
    WHEN '' THEN 'svatební fotograf olomouc, video svatba, svatební video olomouc, svatební kameraman olomouc'
    WHEN 'sluzby' THEN 'svatební fotograf olomouc, video svatba, svatební video, rodinné focení, portrétní focení'
    ELSE s.meta_keywords
  END,
  s.og_title = CASE p.url_path
    WHEN '' THEN 'Svatební fotograf Olomouc – foto a video ze svatby | RB Videos & Photos'
    WHEN 'sluzby' THEN 'Svatební fotograf Olomouc – přehled služeb (foto i video) | RB Videos & Photos'
    ELSE s.og_title
  END,
  s.og_description = CASE p.url_path
    WHEN '' THEN 'Svatební fotograf Olomouc a profesionální video ze svatby – zachytíme váš den citlivě a přirozeně, v Olomouci i po celé ČR.'
    WHEN 'sluzby' THEN 'Svatební fotograf Olomouc i svatební video na míru, dále rodinné, portrétní a reklamní focení. Vyberte si službu a domluvte si nezávaznou konzultaci.'
    ELSE s.og_description
  END,
  s.twitter_title = CASE p.url_path
    WHEN '' THEN 'Svatební fotograf Olomouc – foto a video ze svatby | RB Videos & Photos'
    WHEN 'sluzby' THEN 'Svatební fotograf Olomouc – přehled služeb (foto i video) | RB Videos & Photos'
    ELSE s.twitter_title
  END,
  s.twitter_description = CASE p.url_path
    WHEN '' THEN 'Svatební fotograf Olomouc a profesionální video ze svatby – zachytíme váš den citlivě a přirozeně, v Olomouci i po celé ČR.'
    WHEN 'sluzby' THEN 'Svatební fotograf Olomouc i svatební video na míru, dále rodinné, portrétní a reklamní focení. Vyberte si službu a domluvte si nezávaznou konzultaci.'
    ELSE s.twitter_description
  END,
  s.schema_json = CASE p.url_path
    WHEN '' THEN JSON_SET(s.schema_json,
      '$.name', 'Svatební fotograf Olomouc – foto a video ze svatby | RB Videos & Photos',
      '$.description', 'Svatební fotograf Olomouc a profesionální video ze svatby – zachytíme váš den citlivě a přirozeně, v Olomouci i po celé ČR.')
    WHEN 'sluzby' THEN JSON_SET(s.schema_json,
      '$.name', 'Svatební fotograf Olomouc – přehled služeb (foto i video) | RB Videos & Photos',
      '$.description', 'Svatební fotograf Olomouc i svatební video na míru, dále rodinné, portrétní a reklamní focení. Vyberte si službu a domluvte si nezávaznou konzultaci.')
    ELSE s.schema_json
  END
WHERE p.url_path IN ('', 'sluzby');
