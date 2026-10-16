UPDATE content_blocks cb
JOIN pages p ON p.id = cb.page_id
SET cb.content = 'https://vimeo.com/1228181473',
    cb.updated_at = CURRENT_TIMESTAMP
WHERE p.url_path = 'o-nas'
  AND cb.block_key = 'about_cameraman_video';
