-- Replace a multi-megabyte default hero original with its existing responsive
-- Media Library derivative. This is deliberately data-only and compatible
-- with restricted production database accounts.
UPDATE sites AS s
INNER JOIN media AS m
    ON m.site_id = s.id
   AND (
        m.storage_path = TRIM(LEADING '/' FROM s.default_hero_poster_url)
        OR m.storage_path = CONCAT('storage/', TRIM(LEADING '/' FROM s.default_hero_poster_url))
        OR m.storage_path = CONCAT(
            'storage/media/',
            SUBSTRING_INDEX(s.default_hero_poster_url, '/media/', -1)
        )
   )
INNER JOIN media_variants AS mv
    ON mv.media_id = m.id
   AND mv.format = 'medium'
   AND mv.id = (
        SELECT MAX(current_mv.id)
        FROM media_variants AS current_mv
        WHERE current_mv.media_id = m.id
          AND current_mv.format = 'medium'
   )
SET s.default_hero_poster_url = CONCAT('/', TRIM(LEADING '/' FROM mv.path))
WHERE s.default_hero_poster_url IS NOT NULL
  AND s.default_hero_poster_url <> ''
  AND s.default_hero_poster_url <> CONCAT('/', TRIM(LEADING '/' FROM mv.path));
