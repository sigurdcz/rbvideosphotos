-- Older demo/bootstrap data copied the Open Graph image into Twitter verbatim.
-- Once a platform-specific global Twitter image exists, those duplicates are
-- legacy fallbacks rather than intentional page-level Twitter overrides.
UPDATE `seo_settings` AS `seo`
INNER JOIN `pages` AS `page` ON `page`.`id` = `seo`.`page_id`
INNER JOIN `sites` AS `site` ON `site`.`id` = `page`.`site_id`
SET `seo`.`twitter_image_id` = NULL
WHERE `site`.`default_twitter_image_id` IS NOT NULL
  AND `seo`.`twitter_image_id` IS NOT NULL
  AND `seo`.`twitter_image_id` = `seo`.`og_image_id`;
