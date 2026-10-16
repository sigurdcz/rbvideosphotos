UPDATE `sites`
SET
  `favicon_ico_url` = CASE WHEN COALESCE(TRIM(`favicon_ico_url`), '') = '' THEN '/assets/favicons/favicon.ico' ELSE `favicon_ico_url` END,
  `favicon_png_url` = CASE WHEN COALESCE(TRIM(`favicon_png_url`), '') = '' THEN '/assets/favicons/favicon-32x32.png' ELSE `favicon_png_url` END,
  `apple_touch_icon_url` = CASE WHEN COALESCE(TRIM(`apple_touch_icon_url`), '') = '' THEN '/assets/favicons/apple-touch-icon.png' ELSE `apple_touch_icon_url` END;

UPDATE `content_blocks` b
JOIN `pages` p ON p.id = b.page_id
SET b.content = 'Ceník'
WHERE p.url_path = ''
  AND b.block_key = 'cenik_title'
  AND COALESCE(TRIM(b.content), '') = '';
