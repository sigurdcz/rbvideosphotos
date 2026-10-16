-- Golden Bash is the canonical default for new and previously unconfigured sites.
ALTER TABLE `sites`
  MODIFY COLUMN `active_theme_key` VARCHAR(120) DEFAULT 'golden-bash';

UPDATE `sites`
SET `active_theme_key` = 'golden-bash'
WHERE `active_theme_key` IS NULL OR TRIM(`active_theme_key`) = '';
