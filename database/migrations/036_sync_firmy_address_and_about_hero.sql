-- Keep public NAP aligned with the verified Firmy.cz company profile.
UPDATE `contact_info`
SET
  `address_1` = 'Mozartova 1113/65',
  `address_2` = '779 00 Olomouc, Nová Ulice'
WHERE `address_1` <> 'Mozartova 1113/65'
   OR `address_2` <> '779 00 Olomouc, Nová Ulice';

-- Give the About page its real team photograph. The explicit media contract
-- fails visibly when the seeded asset is missing; no unrelated hero is used.
INSERT INTO `content_blocks`
  (`page_id`, `block_key`, `block_type`, `label`, `content`, `autofill_disabled`, `sort_order`)
SELECT
  p.id,
  'hero_bg',
  'image',
  'Hero fotografie',
  CONCAT('/', m.storage_path),
  0,
  2
FROM `pages` p
JOIN `media` m
  ON m.site_id = p.site_id
 AND m.original_filename = 'weare2.png'
WHERE p.url_path = 'o-nas'
ON DUPLICATE KEY UPDATE
  `block_type` = VALUES(`block_type`),
  `label` = VALUES(`label`),
  `content` = VALUES(`content`),
  `autofill_disabled` = VALUES(`autofill_disabled`),
  `sort_order` = VALUES(`sort_order`);
