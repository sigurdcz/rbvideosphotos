CREATE TABLE IF NOT EXISTS `security_txt_configs` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`               INT UNSIGNED NOT NULL,
  `enabled`               TINYINT(1) NOT NULL DEFAULT 0,
  `expires_at`            DATETIME DEFAULT NULL,
  `canonical_url`         VARCHAR(2048) DEFAULT NULL,
  `encryption_url`        VARCHAR(2048) DEFAULT NULL,
  `policy_url`            VARCHAR(2048) DEFAULT NULL,
  `acknowledgments_url`   VARCHAR(2048) DEFAULT NULL,
  `preferred_languages`   VARCHAR(255) DEFAULT NULL,
  `hiring_url`            VARCHAR(2048) DEFAULT NULL,
  `comments`              TEXT DEFAULT NULL,
  `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_security_txt_configs_site` (`site_id`),
  CONSTRAINT `fk_security_txt_configs_site`
    FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `security_txt_contacts` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `config_id`   INT UNSIGNED NOT NULL,
  `contact_uri` VARCHAR(2048) NOT NULL,
  `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_security_txt_contacts_order` (`config_id`, `sort_order`),
  KEY `idx_security_txt_contacts_config` (`config_id`),
  CONSTRAINT `fk_security_txt_contacts_config`
    FOREIGN KEY (`config_id`) REFERENCES `security_txt_configs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
