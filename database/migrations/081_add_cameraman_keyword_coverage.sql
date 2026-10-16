-- SEO audit still reported low keyword coverage after 078: the site had
-- "svatební fotograf Olomouc" covered, but never "svatební kameraman Olomouc"
-- (Tomáš Šincl's role) anywhere in title/H1/meta - only "kameraman" alone,
-- broken up from "Olomouc" by other words. This adds the exact phrase
-- alongside the existing fotograf one on the two pages the audit checks
-- (homepage, sluzby), without repeating it enough to read as stuffed - the
-- team genuinely is one fotograf + one kameraman, so pairing the two phrases
-- is a true, natural sentence, not an invented claim.
UPDATE `content_blocks` cb
JOIN `pages` p ON p.id = cb.page_id
SET cb.`content` = 'Svatební fotograf Olomouc a svatební kameraman Olomouc'
WHERE p.url_path = ''
  AND cb.`block_key` = 'hero_h1'
  AND cb.`content` = 'Svatební fotograf Olomouc a video ze svatby';

UPDATE `seo_settings` s
JOIN `pages` p ON p.id = s.page_id
SET
  s.meta_description = CASE p.url_path
    WHEN '' THEN 'Svatební fotograf Olomouc a svatební kameraman Olomouc v jednom týmu – zachytíme váš den citlivě a přirozeně, po celé ČR.'
    WHEN 'sluzby' THEN 'Svatební fotograf Olomouc i svatební kameraman Olomouc, dále rodinné, portrétní a reklamní focení. Vyberte si službu a domluvte si nezávaznou konzultaci.'
    ELSE s.meta_description
  END,
  s.meta_keywords = CASE p.url_path
    WHEN '' THEN 'svatební fotograf olomouc, svatební kameraman olomouc, video svatba, svatební video olomouc'
    WHEN 'sluzby' THEN 'svatební fotograf olomouc, svatební kameraman olomouc, video svatba, svatební video, rodinné focení, portrétní focení'
    ELSE s.meta_keywords
  END,
  s.og_description = CASE p.url_path
    WHEN '' THEN 'Svatební fotograf Olomouc a svatební kameraman Olomouc v jednom týmu – zachytíme váš den citlivě a přirozeně, po celé ČR.'
    WHEN 'sluzby' THEN 'Svatební fotograf Olomouc i svatební kameraman Olomouc, dále rodinné, portrétní a reklamní focení. Vyberte si službu a domluvte si nezávaznou konzultaci.'
    ELSE s.og_description
  END,
  s.twitter_description = CASE p.url_path
    WHEN '' THEN 'Svatební fotograf Olomouc a svatební kameraman Olomouc v jednom týmu – zachytíme váš den citlivě a přirozeně, po celé ČR.'
    WHEN 'sluzby' THEN 'Svatební fotograf Olomouc i svatební kameraman Olomouc, dále rodinné, portrétní a reklamní focení. Vyberte si službu a domluvte si nezávaznou konzultaci.'
    ELSE s.twitter_description
  END,
  s.schema_description = CASE p.url_path
    WHEN '' THEN 'Svatební fotograf Olomouc a svatební kameraman Olomouc v jednom týmu – zachytíme váš den citlivě a přirozeně, po celé ČR.'
    WHEN 'sluzby' THEN 'Svatební fotograf Olomouc i svatební kameraman Olomouc, dále rodinné, portrétní a reklamní focení. Vyberte si službu a domluvte si nezávaznou konzultaci.'
    ELSE s.schema_description
  END
WHERE p.url_path IN ('', 'sluzby');
