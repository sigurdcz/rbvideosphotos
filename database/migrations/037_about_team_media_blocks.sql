-- Bind the two explicitly imported team portraits to the About page.
-- The import script fails visibly when either source file is unavailable.
INSERT INTO `content_blocks`
  (`page_id`, `block_key`, `block_type`, `label`, `content`, `autofill_disabled`, `sort_order`)
SELECT
  p.id,
  media_source.block_key,
  'image',
  media_source.label,
  CONCAT('/', m.storage_path),
  0,
  media_source.sort_order
FROM `pages` p
JOIN (
  SELECT
    'about_cameraman_image' AS block_key,
    'Tomáš Šincl – kameraman' AS label,
    'tomas-sincl-kameraman.png' AS original_filename,
    3 AS sort_order
  UNION ALL
  SELECT
    'about_photographer_image',
    'Zdeněk Černohlávek – fotograf',
    'zdenek-cernohlavek-fotograf.png',
    4
) media_source
JOIN `media` m
  ON m.site_id = p.site_id
 AND m.original_filename = media_source.original_filename
WHERE p.url_path = 'o-nas'
ON DUPLICATE KEY UPDATE
  `block_type` = VALUES(`block_type`),
  `label` = VALUES(`label`),
  `content` = VALUES(`content`),
  `autofill_disabled` = VALUES(`autofill_disabled`),
  `sort_order` = VALUES(`sort_order`);
