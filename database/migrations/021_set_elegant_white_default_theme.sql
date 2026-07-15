UPDATE `sites`
SET `active_theme_key` = 'elegant-white'
WHERE `active_theme_key` IS NULL OR TRIM(`active_theme_key`) = '';

ALTER TABLE `sites`
  MODIFY COLUMN `active_theme_key` VARCHAR(120) DEFAULT 'elegant-white';
