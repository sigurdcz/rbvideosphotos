-- Golden Bash Diagonal supersedes the former Golden Bash default while preserving other explicit theme choices.
ALTER TABLE `sites`
  MODIFY COLUMN `active_theme_key` VARCHAR(120) DEFAULT 'golden-bash-diagonal';

UPDATE `sites`
SET `active_theme_key` = 'golden-bash-diagonal'
WHERE `active_theme_key` IS NULL
   OR TRIM(`active_theme_key`) = ''
   OR `active_theme_key` = 'golden-bash';
