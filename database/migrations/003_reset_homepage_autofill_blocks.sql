UPDATE content_blocks cb
JOIN pages p ON p.id = cb.page_id
SET cb.autofill_disabled = 0,
    cb.content = ''
WHERE TRIM(p.url_path) = ''
  AND cb.block_key IN (
    'sluzby_items',
    'portfolio-tiles',
    'blog_1',
    'blog_2',
    'blog_3',
    'cenik_body',
    'pricing_body',
    'faq_1',
    'faq_2',
    'faq_3',
    'kontakt_info',
    'contact_info'
  );
