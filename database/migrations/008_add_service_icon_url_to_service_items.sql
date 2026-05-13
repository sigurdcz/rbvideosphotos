SET @db_name := DATABASE();

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `service_items` ADD COLUMN `icon_url` VARCHAR(1000) DEFAULT NULL AFTER `image_url`',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = @db_name
    AND table_name = 'service_items'
    AND column_name = 'icon_url'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
