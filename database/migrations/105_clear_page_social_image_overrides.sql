-- Remove legacy/test per-page sharing images. All pages inherit site defaults.
-- Keep global platform defaults, media files, structured-data images and SEO copy.
UPDATE `seo_settings`
SET `og_image_id` = NULL,
    `twitter_image_id` = NULL
WHERE `og_image_id` IS NOT NULL
   OR `twitter_image_id` IS NOT NULL;
