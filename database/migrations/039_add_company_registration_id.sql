ALTER TABLE `sites`
  ADD COLUMN `company_registration_id` VARCHAR(20) DEFAULT NULL AFTER `domain`;

UPDATE `contact_info`
SET `reviews_url` = 'https://maps.app.goo.gl/mi3f1qMJENJY2bVN7'
WHERE `reviews_url` IS NULL OR TRIM(`reviews_url`) = '';
