ALTER TABLE `gtm_managed_entities`
  MODIFY COLUMN `last_status` ENUM('new','update','noop','conflict','duplicate','deleted','succeeded','failed') NOT NULL DEFAULT 'new';
