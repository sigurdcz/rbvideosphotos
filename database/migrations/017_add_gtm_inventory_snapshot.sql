ALTER TABLE `gtm_connections`
  ADD COLUMN `inventory_snapshot_json` LONGTEXT DEFAULT NULL AFTER `last_error`,
  ADD COLUMN `inventory_snapshot_at` DATETIME DEFAULT NULL AFTER `inventory_snapshot_json`;

