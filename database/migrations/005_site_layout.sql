ALTER TABLE `sites`
  ADD COLUMN `layout_html` LONGTEXT DEFAULT NULL AFTER `body_scripts`,
  ADD COLUMN `layout_css` LONGTEXT DEFAULT NULL AFTER `layout_html`,
  ADD COLUMN `layout_js` LONGTEXT DEFAULT NULL AFTER `layout_css`;
