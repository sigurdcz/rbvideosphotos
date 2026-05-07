UPDATE pages
SET slug = 'priprava-na-svatebni-foceni',
    url_path = '/priprava-na-svatebni-foceni',
    updated_at = NOW()
WHERE slug = 'blog/priprava-na-svatebni-foceni'
   OR url_path = '/blog/priprava-na-svatebni-foceni';

UPDATE pages
SET slug = 'rodinne-foceni-s-detmi',
    url_path = '/rodinne-foceni-s-detmi',
    updated_at = NOW()
WHERE slug = 'blog/rodinne-foceni-s-detmi'
   OR url_path = '/blog/rodinne-foceni-s-detmi';

UPDATE seo_settings ss
INNER JOIN pages p ON p.id = ss.page_id
SET ss.canonical_url = '/priprava-na-svatebni-foceni',
    ss.schema_json = REPLACE(ss.schema_json, '/blog/priprava-na-svatebni-foceni', '/priprava-na-svatebni-foceni')
WHERE p.slug = 'priprava-na-svatebni-foceni'
   OR p.url_path = '/priprava-na-svatebni-foceni';

UPDATE seo_settings ss
INNER JOIN pages p ON p.id = ss.page_id
SET ss.canonical_url = '/rodinne-foceni-s-detmi',
    ss.schema_json = REPLACE(ss.schema_json, '/blog/rodinne-foceni-s-detmi', '/rodinne-foceni-s-detmi')
WHERE p.slug = 'rodinne-foceni-s-detmi'
   OR p.url_path = '/rodinne-foceni-s-detmi';

UPDATE templates
SET html_source = REPLACE(html_source, '/blog/priprava-na-svatebni-foceni', '/priprava-na-svatebni-foceni')
WHERE html_source LIKE '%/blog/priprava-na-svatebni-foceni%';

UPDATE templates
SET html_source = REPLACE(html_source, '/blog/rodinne-foceni-s-detmi', '/rodinne-foceni-s-detmi')
WHERE html_source LIKE '%/blog/rodinne-foceni-s-detmi%';

UPDATE content_blocks
SET content = REPLACE(content, '/blog/priprava-na-svatebni-foceni', '/priprava-na-svatebni-foceni')
WHERE content LIKE '%/blog/priprava-na-svatebni-foceni%';

UPDATE content_blocks
SET content = REPLACE(content, '/blog/rodinne-foceni-s-detmi', '/rodinne-foceni-s-detmi')
WHERE content LIKE '%/blog/rodinne-foceni-s-detmi%';

UPDATE content_blocks
SET content = REPLACE(content, 'href="/nase-prace/', 'href="/')
WHERE block_key = 'portfolio-tiles'
  AND content LIKE '%href="/nase-prace/%';
