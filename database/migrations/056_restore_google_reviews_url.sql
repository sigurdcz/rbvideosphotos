-- Restore the verified Google Business reviews link when a previous Demo seed
-- cleared contact_info.reviews_url after migration 045 had already run.
UPDATE `contact_info`
SET `reviews_url` = 'https://maps.app.goo.gl/DN9Y1ohrSgAePg3n9'
WHERE `reviews_url` IS NULL OR TRIM(`reviews_url`) = '';
