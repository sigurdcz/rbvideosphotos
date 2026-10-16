UPDATE `content_blocks` b
JOIN `pages` p ON p.id = b.page_id
JOIN `media` m
  ON m.site_id = p.site_id
 AND m.original_filename = CASE p.url_path
   WHEN 'sluzby' THEN 'svatba-nevesta-zenich-polibek-kamenna-zed.webp'
   WHEN 'cenik' THEN 'snubni-prsteny-listy-zapad-slunce-detail.webp'
   WHEN 'faq' THEN 'svatba-nevesta-kytice-zapad-slunce-u-vody.webp'
   WHEN 'kontakt' THEN 'zamilovany-par-objeti-zapad-slunce-podzim.webp'
   WHEN 'nase-prace' THEN 'par-v-prirode-romanticky-portret-zapad-slunce.webp'
   WHEN 'blog' THEN 'koncert-zpevak-mikrofon-cernobila-fotografie.webp'
   WHEN 'podminky-uziti' THEN 'rodinne-foceni-rodina-dite-louka.webp'
   ELSE ''
 END
SET b.content = CONCAT('/', m.storage_path)
WHERE b.block_key = 'hero_bg'
  AND p.url_path IN (
    'sluzby',
    'cenik',
    'faq',
    'kontakt',
    'nase-prace',
    'blog',
    'podminky-uziti'
  );
