ALTER TABLE `contact_info`
  ADD COLUMN `reviews_url` VARCHAR(1000) DEFAULT NULL AFTER `maps_url`;

UPDATE `contact_info`
SET `reviews_url` = 'https://maps.app.goo.gl/mi3f1qMJENJY2bVN7'
WHERE `reviews_url` IS NULL OR TRIM(`reviews_url`) = '';
