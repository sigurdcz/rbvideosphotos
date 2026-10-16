-- Replace the posterized homepage About visual with two explicit high-quality
-- team portraits already owned by the site's Media Library.
INSERT INTO `content_blocks`
  (`page_id`, `block_key`, `block_type`, `label`, `content`, `autofill_disabled`, `sort_order`)
SELECT
  p.id,
  media_source.block_key,
  'image',
  media_source.label,
  CONCAT('/', m.storage_path),
  1,
  media_source.sort_order
FROM `pages` p
JOIN (
  SELECT 'jb-about-photographer__media' AS block_key,
         'Homepage O nas - fotograf' AS label,
         'zdenek-cernohlavek-fotograf.png' AS original_filename,
         86 AS sort_order
  UNION ALL
  SELECT 'jb-about-cameraman__media',
         'Homepage O nas - kameraman',
         'tomas-sincl-kameraman.png',
         87
) media_source
JOIN `media` m
  ON m.site_id = p.site_id
 AND m.original_filename = media_source.original_filename
WHERE p.url_path = ''
ON DUPLICATE KEY UPDATE
  `block_type` = VALUES(`block_type`),
  `label` = VALUES(`label`),
  `content` = VALUES(`content`),
  `autofill_disabled` = VALUES(`autofill_disabled`),
  `sort_order` = VALUES(`sort_order`);
