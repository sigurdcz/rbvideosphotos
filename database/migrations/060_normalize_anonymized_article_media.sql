-- Complete migration 059 for slug-derived media filenames and spacing.
-- REPLACE is case-sensitive, so lowercase legacy slug fragments need explicit handling.

UPDATE `media` m
JOIN (
    SELECT media_id FROM portfolio_media
    UNION
    SELECT media_id FROM blog_media
) article_media ON article_media.media_id = m.id
SET m.original_filename = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(m.original_filename,
        '-duriskovi', ''), '-hrazdilovi', ''), '-tuikovi', ''), '-cervenkovi', ''),
        '-zackovi', ''), '-novakovi', ''), '-neverilovi', ''), '-stipcakovi', ''),
    m.alt_text = REPLACE(m.alt_text, '  ', ' '),
    m.title = REPLACE(m.title, '  ', ' ');

UPDATE `portfolio_entries`
SET `title` = REPLACE(`title`, '  ', ' '),
    `description` = REPLACE(`description`, '  ', ' '),
    `meta_title` = REPLACE(`meta_title`, '  ', ' '),
    `meta_description` = REPLACE(`meta_description`, '  ', ' ');

UPDATE `blog_posts`
SET `title` = REPLACE(`title`, '  ', ' '),
    `excerpt` = REPLACE(`excerpt`, '  ', ' '),
    `content` = REPLACE(`content`, '  ', ' '),
    `meta_title` = REPLACE(`meta_title`, '  ', ' '),
    `meta_description` = REPLACE(`meta_description`, '  ', ' ');
