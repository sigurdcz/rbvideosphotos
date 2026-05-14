SET @db_name := DATABASE();

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `sites` ADD COLUMN `cookie_consent_analytics_enabled` TINYINT(1) NOT NULL DEFAULT 1',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = @db_name
    AND table_name = 'sites'
    AND column_name = 'cookie_consent_analytics_enabled'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `sites` ADD COLUMN `cookie_consent_marketing_enabled` TINYINT(1) NOT NULL DEFAULT 1',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = @db_name
    AND table_name = 'sites'
    AND column_name = 'cookie_consent_marketing_enabled'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
