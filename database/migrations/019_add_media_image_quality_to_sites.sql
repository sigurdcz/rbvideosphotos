SET @db_name := DATABASE();

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `sites` ADD COLUMN `media_image_quality` TINYINT UNSIGNED NOT NULL DEFAULT 90 AFTER `default_hero_poster_url`',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = @db_name
    AND table_name = 'sites'
    AND column_name = 'media_image_quality'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
