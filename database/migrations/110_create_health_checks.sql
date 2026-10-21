-- Ten-minute application health history. Written by bin/health-check.php.
-- No foreign key: an outage record must remain writable even while related
-- application data is being repaired.
CREATE TABLE IF NOT EXISTS health_checks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  check_uuid CHAR(36) NOT NULL,
  site_id INT UNSIGNED NOT NULL,
  checked_at DATETIME NOT NULL,
  status ENUM('healthy','unhealthy') NOT NULL,
  http_status SMALLINT UNSIGNED NULL,
  response_time_ms INT UNSIGNED NULL,
  database_status ENUM('ok','error','unknown') NOT NULL DEFAULT 'unknown',
  error_code VARCHAR(64) NULL,
  source ENUM('cron','recovered') NOT NULL DEFAULT 'cron',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY health_checks_uuid (check_uuid),
  KEY health_checks_site_time (site_id, checked_at),
  KEY health_checks_status_time (status, checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
