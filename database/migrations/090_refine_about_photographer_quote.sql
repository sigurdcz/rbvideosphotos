UPDATE content_blocks cb
JOIN pages p ON p.id = cb.page_id
SET cb.content = 'Zachycuji emoce a přirozenost tak, abyste se k nim mohli vracet i po letech a znovu je prožívat.',
    cb.updated_at = CURRENT_TIMESTAMP
WHERE p.url_path = 'o-nas'
  AND cb.block_key = 'about_photographer_quote';
