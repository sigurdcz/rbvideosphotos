UPDATE `content_blocks` b
JOIN `pages` p ON p.id = b.page_id
SET b.content = CONCAT(
  b.content,
  CASE p.url_path
    WHEN '/rodinne-a-portretni-foceni' THEN '<p>Na přání předem zohledníme také místo, kde mají výsledné obrazy viset, aby série barevně i formátem zapadla do vašeho domova.</p>'
    WHEN '/reklamni-foto-video' THEN '<p>Po předání zůstáváme k dispozici pro technický dotaz grafika nebo správce kampaně, aby se výstupy použily ve správné kvalitě.</p>'
    ELSE ''
  END
)
WHERE b.block_key = 'body'
  AND p.url_path IN ('/rodinne-a-portretni-foceni', '/reklamni-foto-video');
