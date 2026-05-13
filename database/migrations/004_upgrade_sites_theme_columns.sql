SET @db_name := DATABASE();

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `sites` ADD COLUMN `layout_html` LONGTEXT DEFAULT NULL',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = @db_name
    AND table_name = 'sites'
    AND column_name = 'layout_html'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `sites` ADD COLUMN `layout_css` LONGTEXT DEFAULT NULL',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = @db_name
    AND table_name = 'sites'
    AND column_name = 'layout_css'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `sites` ADD COLUMN `layout_js` LONGTEXT DEFAULT NULL',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = @db_name
    AND table_name = 'sites'
    AND column_name = 'layout_js'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `sites` ADD COLUMN `layout_nav_home` LONGTEXT DEFAULT NULL',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = @db_name
    AND table_name = 'sites'
    AND column_name = 'layout_nav_home'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `sites` ADD COLUMN `layout_nav_sub` LONGTEXT DEFAULT NULL',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = @db_name
    AND table_name = 'sites'
    AND column_name = 'layout_nav_sub'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `sites` ADD COLUMN `active_theme_key` VARCHAR(120) DEFAULT NULL',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = @db_name
    AND table_name = 'sites'
    AND column_name = 'active_theme_key'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'CREATE INDEX `idx_sites_active_theme_key` ON `sites` (`active_theme_key`)',
    'SELECT 1'
  )
  FROM information_schema.statistics
  WHERE table_schema = @db_name
    AND table_name = 'sites'
    AND index_name = 'idx_sites_active_theme_key'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
