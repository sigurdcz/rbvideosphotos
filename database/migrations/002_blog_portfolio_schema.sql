CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`           INT UNSIGNED NOT NULL,
  `title`             VARCHAR(255) NOT NULL,
  `slug`              VARCHAR(255) NOT NULL,
  `excerpt`           TEXT DEFAULT NULL,
  `content`           LONGTEXT DEFAULT NULL,
  `featured_image_id` INT UNSIGNED DEFAULT NULL,
  `status`            ENUM('draft','published') NOT NULL DEFAULT 'draft',
  `published_at`      DATETIME DEFAULT NULL,
  `meta_title`        VARCHAR(255) DEFAULT NULL,
  `meta_description`  VARCHAR(500) DEFAULT NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blog_posts_site_slug` (`site_id`, `slug`),
  KEY `fk_blog_posts_site` (`site_id`),
  KEY `fk_blog_posts_featured_media` (`featured_image_id`),
  CONSTRAINT `fk_blog_posts_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_blog_posts_featured_media` FOREIGN KEY (`featured_image_id`) REFERENCES `media` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `portfolio_entries` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`          INT UNSIGNED NOT NULL,
  `title`            VARCHAR(255) NOT NULL,
  `slug`             VARCHAR(255) NOT NULL,
  `description`      LONGTEXT DEFAULT NULL,
  `video_url`        VARCHAR(1000) DEFAULT NULL,
  `status`           ENUM('draft','published') NOT NULL DEFAULT 'draft',
  `published_at`     DATETIME DEFAULT NULL,
  `sort_order`       INT UNSIGNED NOT NULL DEFAULT 0,
  `meta_title`       VARCHAR(255) DEFAULT NULL,
  `meta_description` VARCHAR(500) DEFAULT NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_portfolio_entries_site_slug` (`site_id`, `slug`),
  KEY `fk_portfolio_entries_site` (`site_id`),
  CONSTRAINT `fk_portfolio_entries_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `portfolio_media` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `portfolio_id` INT UNSIGNED NOT NULL,
  `media_id`     INT UNSIGNED NOT NULL,
  `sort_order`   INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_portfolio_media_entry` (`portfolio_id`),
  KEY `fk_portfolio_media_media` (`media_id`),
  CONSTRAINT `fk_portfolio_media_entry` FOREIGN KEY (`portfolio_id`) REFERENCES `portfolio_entries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_portfolio_media_media` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
