-- The admin "Schema.org" tab used to expose a raw JSON-LD textarea
-- (seo_settings.schema_json). The client must never hand-edit JSON, so the
-- page-level schema is now stored as named columns and the JSON-LD payload
-- is assembled at render time (App\Services\SeoRenderer::buildSchemaPayload).
--
-- Contact-derived fields (telephone / email / address) carry one shared
-- schema_contact_auto flag: 1 = take them from Kontakt settings at render
-- time (today's LocalBusiness behaviour), 0 = use the manual columns.
--
-- Existing schema_json rows are backfilled best-effort: name/description
-- from the top-level object or the first @graph node; contact fields only
-- for rows that are NOT LocalBusiness (LocalBusiness values were always
-- overwritten from Kontakt at render time, so auto=1 reproduces them).
-- Image URLs in the JSON cannot be mapped back to a media id reliably, so
-- schema_image_id stays NULL and the renderer falls back to the OG image,
-- which is what the old code forced for LocalBusiness anyway.
--
-- schema_json itself is kept for one release as an unread, unwritten
-- safety net; TODO drop it in a later 08x_drop_seo_schema_json.sql after
-- the structured render has been verified on production.
ALTER TABLE `seo_settings`
  ADD COLUMN `schema_name` VARCHAR(255) DEFAULT NULL AFTER `schema_type`,
  ADD COLUMN `schema_description` VARCHAR(1000) DEFAULT NULL AFTER `schema_name`,
  ADD COLUMN `schema_image_id` INT UNSIGNED DEFAULT NULL AFTER `schema_description`,
  ADD COLUMN `schema_contact_auto` TINYINT(1) NOT NULL DEFAULT 1 AFTER `schema_image_id`,
  ADD COLUMN `schema_telephone` VARCHAR(100) DEFAULT NULL AFTER `schema_contact_auto`,
  ADD COLUMN `schema_email` VARCHAR(255) DEFAULT NULL AFTER `schema_telephone`,
  ADD COLUMN `schema_address_street` VARCHAR(255) DEFAULT NULL AFTER `schema_email`,
  ADD COLUMN `schema_address_locality` VARCHAR(255) DEFAULT NULL AFTER `schema_address_street`,
  ADD KEY `fk_seo_schema_image` (`schema_image_id`),
  ADD CONSTRAINT `fk_seo_schema_image`
    FOREIGN KEY (`schema_image_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;

-- name / description: top-level object first, then first @graph node,
-- then the page's own meta title/description so every typed row renders.
UPDATE `seo_settings`
SET
  `schema_name` = LEFT(COALESCE(
    CASE WHEN `schema_json` IS NOT NULL AND JSON_VALID(`schema_json`)
         THEN COALESCE(
           JSON_UNQUOTE(JSON_EXTRACT(`schema_json`, '$.name')),
           JSON_UNQUOTE(JSON_EXTRACT(`schema_json`, '$."@graph"[0].name'))
         ) END,
    `meta_title`
  ), 255),
  `schema_description` = LEFT(COALESCE(
    CASE WHEN `schema_json` IS NOT NULL AND JSON_VALID(`schema_json`)
         THEN COALESCE(
           JSON_UNQUOTE(JSON_EXTRACT(`schema_json`, '$.description')),
           JSON_UNQUOTE(JSON_EXTRACT(`schema_json`, '$."@graph"[0].description'))
         ) END,
    `meta_description`
  ), 1000)
WHERE `schema_type` IS NOT NULL AND `schema_type` <> '';

-- Manual contact values only for non-LocalBusiness rows that actually had
-- any of telephone / email / address in their JSON (top-level object only).
UPDATE `seo_settings`
SET
  `schema_contact_auto` = 0,
  `schema_telephone` = LEFT(JSON_UNQUOTE(JSON_EXTRACT(`schema_json`, '$.telephone')), 100),
  `schema_email` = LEFT(JSON_UNQUOTE(JSON_EXTRACT(`schema_json`, '$.email')), 255),
  `schema_address_street` = LEFT(JSON_UNQUOTE(JSON_EXTRACT(`schema_json`, '$.address.streetAddress')), 255),
  `schema_address_locality` = LEFT(NULLIF(TRIM(CONCAT_WS(' ',
      JSON_UNQUOTE(JSON_EXTRACT(`schema_json`, '$.address.postalCode')),
      JSON_UNQUOTE(JSON_EXTRACT(`schema_json`, '$.address.addressLocality'))
  )), ''), 255)
WHERE `schema_json` IS NOT NULL
  AND JSON_VALID(`schema_json`)
  AND COALESCE(`schema_type`, '') <> 'LocalBusiness'
  AND JSON_CONTAINS_PATH(`schema_json`, 'one', '$.telephone', '$.email', '$.address');
