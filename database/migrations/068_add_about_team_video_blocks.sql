-- Add optional video content blocks for the two About-page team members.
-- When filled in (admin "Bloky" tab), the o-nas template plays this video
-- instead of the static portrait photo for that person. Tomas Sincl's
-- block ships with the client-supplied default YouTube URL; Zdenek's stays
-- empty (photo keeps showing) until an admin fills it in.
INSERT INTO `content_blocks`
  (`page_id`, `block_key`, `block_type`, `label`, `content`, `autofill_disabled`, `sort_order`)
SELECT
  p.id,
  video_source.block_key,
  'video',
  video_source.label,
  video_source.content,
  0,
  video_source.sort_order
FROM `pages` p
JOIN (
  SELECT
    'about_photographer_video' AS block_key,
    'Zdeněk Černohlávek - video místo fotky (nepovinné)' AS label,
    '' AS content,
    5 AS sort_order
  UNION ALL
  SELECT
    'about_cameraman_video',
    'Tomáš Šincl - video místo fotky (nepovinné)',
    'https://www.youtube.com/watch?v=YKRu1l0Rb6U',
    6
) video_source
WHERE p.url_path = 'o-nas'
ON DUPLICATE KEY UPDATE
  `block_type` = VALUES(`block_type`),
  `label` = VALUES(`label`),
  `sort_order` = VALUES(`sort_order`);
