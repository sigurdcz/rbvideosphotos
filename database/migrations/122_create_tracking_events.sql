-- First-party copy of allowlisted frontend events emitted by tracking.js.
-- Event parameters are deliberately not stored because labels may contain
-- contact details or arbitrary URLs. The IP is used only for a daily HMAC.
CREATE TABLE IF NOT EXISTS tracking_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  site_id INT UNSIGNED NOT NULL,
  occurred_at DATETIME NOT NULL,
  occurred_on DATE NOT NULL,
  event_name VARCHAR(64) NOT NULL,
  path VARCHAR(255) NOT NULL,
  visitor_hash CHAR(32) NOT NULL,
  consent_analytics TINYINT(1) NOT NULL DEFAULT 0,
  is_bot TINYINT(1) NOT NULL DEFAULT 0,
  INDEX tracking_events_site_day (site_id, occurred_on),
  INDEX tracking_events_site_event_day (site_id, event_name, occurred_on),
  INDEX tracking_events_day_visitor (occurred_on, visitor_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
