ALTER TABLE `sites`
  ADD COLUMN `active_theme_key` VARCHAR(120) DEFAULT NULL AFTER `layout_nav_sub`;

CREATE INDEX `idx_sites_active_theme_key` ON `sites` (`active_theme_key`);
