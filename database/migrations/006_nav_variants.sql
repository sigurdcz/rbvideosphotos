ALTER TABLE `sites`
  ADD COLUMN `layout_nav_home` LONGTEXT DEFAULT NULL AFTER `layout_js`,
  ADD COLUMN `layout_nav_sub`  LONGTEXT DEFAULT NULL AFTER `layout_nav_home`;
