-- First-party visit measurement (fallback next to GA4), see ARCHITECTURE.md.
-- Rows are written by POST /api/visit (public/index.php tryRecordVisitBeacon).
-- No IP address is stored: visitor_hash is a daily rotating salted HMAC and
-- visitor_id (cookie cms_vid) is filled only with explicit analytics consent.
-- site_id matches sites.id (INT UNSIGNED); no FK so the write path stays cheap
-- and lazy retention deletes do not cascade.
CREATE TABLE IF NOT EXISTS site_visits (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  site_id INT UNSIGNED NOT NULL,
  visited_at DATETIME NOT NULL,
  visited_on DATE NOT NULL,
  path VARCHAR(255) NOT NULL,
  referrer_host VARCHAR(255) NULL,
  utm_source VARCHAR(100) NULL,
  utm_medium VARCHAR(100) NULL,
  utm_campaign VARCHAR(100) NULL,
  device_type ENUM('desktop','mobile','tablet','unknown') NOT NULL DEFAULT 'unknown',
  visitor_hash CHAR(32) NOT NULL,
  visitor_id CHAR(32) NULL,
  consent_analytics TINYINT(1) NOT NULL DEFAULT 0,
  is_bot TINYINT(1) NOT NULL DEFAULT 0,
  INDEX site_visits_site_day (site_id, visited_on),
  INDEX site_visits_site_path (site_id, path(100)),
  INDEX site_visits_day_visitor (visited_on, visitor_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @db_name := DATABASE();

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `sites` ADD COLUMN `visit_analytics_enabled` TINYINT(1) NOT NULL DEFAULT 1',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = @db_name
    AND table_name = 'sites'
    AND column_name = 'visit_analytics_enabled'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
