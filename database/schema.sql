-- SEO Mini CMS - Database Schema
-- MySQL 8+ / MariaDB 10.4+

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`         VARCHAR(255) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          ENUM('admin','editor') NOT NULL DEFAULT 'editor',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- sites
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sites` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`                  VARCHAR(255) NOT NULL,
  `domain`                VARCHAR(255) NOT NULL,
  `company_registration_id` VARCHAR(20) DEFAULT NULL,
  `default_language`      VARCHAR(10) NOT NULL DEFAULT 'cs',
  `google_analytics_id`   VARCHAR(50) DEFAULT NULL,
  `google_tag_manager_id` VARCHAR(50) DEFAULT NULL,
  `cookie_consent_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `head_scripts`          TEXT DEFAULT NULL,
  `body_scripts`          TEXT DEFAULT NULL,
  `analytics_head_scripts` LONGTEXT DEFAULT NULL,
  `analytics_body_scripts` LONGTEXT DEFAULT NULL,
  `marketing_head_scripts` LONGTEXT DEFAULT NULL,
  `marketing_body_scripts` LONGTEXT DEFAULT NULL,
  `layout_html`           LONGTEXT DEFAULT NULL,
  `layout_css`            LONGTEXT DEFAULT NULL,
  `layout_js`             LONGTEXT DEFAULT NULL,
  `layout_nav_home`       LONGTEXT DEFAULT NULL,
  `layout_nav_sub`        LONGTEXT DEFAULT NULL,
  `active_theme_key`      VARCHAR(120) DEFAULT 'golden-bash-diagonal',
  `media_image_quality`    TINYINT UNSIGNED NOT NULL DEFAULT 90,
  `default_hero_video_url`        VARCHAR(1000) DEFAULT NULL,
  `default_hero_video_url_tablet` VARCHAR(1000) DEFAULT NULL,
  `default_hero_video_url_mobile` VARCHAR(1000) DEFAULT NULL,
  `default_hero_poster_url`       VARCHAR(1000) DEFAULT NULL,
  `video_autoplay_enabled`        TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_sites_active_theme_key` (`active_theme_key`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- RFC 9116 security.txt configuration
-- --------------------------------------------------------
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
  CONSTRAINT `fk_security_txt_configs_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
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
  CONSTRAINT `fk_security_txt_contacts_config` FOREIGN KEY (`config_id`) REFERENCES `security_txt_configs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- templates
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `templates` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`     INT UNSIGNED NOT NULL,
  `name`        VARCHAR(255) NOT NULL,
  `html_source` LONGTEXT DEFAULT NULL,
  `css_source`  LONGTEXT DEFAULT NULL,
  `js_source`   LONGTEXT DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_templates_site` (`site_id`),
  CONSTRAINT `fk_templates_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- pages
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pages` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`      INT UNSIGNED NOT NULL,
  `template_id`  INT UNSIGNED DEFAULT NULL,
  `title`        VARCHAR(255) NOT NULL,
  `slug`         VARCHAR(255) NOT NULL,
  `url_path`     VARCHAR(500) NOT NULL,
  `language`     VARCHAR(10) NOT NULL DEFAULT 'cs',
  `status`       ENUM('draft','published') NOT NULL DEFAULT 'draft',
  `published_at` DATETIME DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pages_site_slug` (`site_id`, `slug`),
  KEY `fk_pages_site` (`site_id`),
  KEY `fk_pages_template` (`template_id`),
  CONSTRAINT `fk_pages_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pages_template` FOREIGN KEY (`template_id`) REFERENCES `templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- content_blocks
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `content_blocks` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id`    INT UNSIGNED NOT NULL,
  `block_key`  VARCHAR(100) NOT NULL,
  `block_type` ENUM('text','textarea','richtext','image','link','html','video') NOT NULL DEFAULT 'text',
  `label`      VARCHAR(255) DEFAULT NULL,
  `content`    LONGTEXT DEFAULT NULL,
  `autofill_disabled` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blocks_page_key` (`page_id`, `block_key`),
  KEY `fk_blocks_page` (`page_id`),
  CONSTRAINT `fk_blocks_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- media
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `media` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`           INT UNSIGNED NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `storage_path`      VARCHAR(500) NOT NULL,
  `alt_text`          VARCHAR(500) DEFAULT NULL,
  `title`             VARCHAR(255) DEFAULT NULL,
  `width`             SMALLINT UNSIGNED DEFAULT NULL,
  `height`            SMALLINT UNSIGNED DEFAULT NULL,
  `mime_type`         VARCHAR(100) NOT NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_media_site` (`site_id`),
  CONSTRAINT `fk_media_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- media_variants
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `media_variants` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `media_id`   INT UNSIGNED NOT NULL,
  `format`     VARCHAR(20) NOT NULL,
  `width`      SMALLINT UNSIGNED DEFAULT NULL,
  `height`     SMALLINT UNSIGNED DEFAULT NULL,
  `path`       VARCHAR(500) NOT NULL,
  `filesize`   INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_variants_media` (`media_id`),
  CONSTRAINT `fk_variants_media` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- seo_settings
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `seo_settings` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id`             INT UNSIGNED NOT NULL UNIQUE,
  `meta_title`          VARCHAR(255) DEFAULT NULL,
  `meta_description`    VARCHAR(500) DEFAULT NULL,
  `meta_keywords`       VARCHAR(500) DEFAULT NULL,
  `canonical_url`       VARCHAR(500) DEFAULT NULL,
  `robots_index`        TINYINT(1) NOT NULL DEFAULT 1,
  `robots_follow`       TINYINT(1) NOT NULL DEFAULT 1,
  `og_title`            VARCHAR(255) DEFAULT NULL,
  `og_description`      VARCHAR(500) DEFAULT NULL,
  `og_image_id`         INT UNSIGNED DEFAULT NULL,
  `twitter_title`       VARCHAR(255) DEFAULT NULL,
  `twitter_description` VARCHAR(500) DEFAULT NULL,
  `twitter_image_id`    INT UNSIGNED DEFAULT NULL,
  `schema_type`         VARCHAR(100) DEFAULT NULL,
  `schema_name`             VARCHAR(255) DEFAULT NULL,
  `schema_description`      VARCHAR(1000) DEFAULT NULL,
  `schema_image_id`         INT UNSIGNED DEFAULT NULL,
  `schema_contact_auto`     TINYINT(1) NOT NULL DEFAULT 1,
  `schema_telephone`        VARCHAR(100) DEFAULT NULL,
  `schema_email`            VARCHAR(255) DEFAULT NULL,
  `schema_address_street`   VARCHAR(255) DEFAULT NULL,
  `schema_address_locality` VARCHAR(255) DEFAULT NULL,
  `schema_json`         LONGTEXT DEFAULT NULL, -- deprecated: unread/unwritten since migration 079, structured schema_* columns above are authoritative
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_seo_page` (`page_id`),
  KEY `fk_seo_og_image` (`og_image_id`),
  KEY `fk_seo_tw_image` (`twitter_image_id`),
  KEY `fk_seo_schema_image` (`schema_image_id`),
  CONSTRAINT `fk_seo_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_seo_og_image` FOREIGN KEY (`og_image_id`) REFERENCES `media` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_seo_tw_image` FOREIGN KEY (`twitter_image_id`) REFERENCES `media` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_seo_schema_image` FOREIGN KEY (`schema_image_id`) REFERENCES `media` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- redirects
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `redirects` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`     INT UNSIGNED NOT NULL,
  `from_path`   VARCHAR(500) NOT NULL,
  `to_path`     VARCHAR(500) NOT NULL,
  `status_code` SMALLINT UNSIGNED NOT NULL DEFAULT 301,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_redirects_site` (`site_id`),
  CONSTRAINT `fk_redirects_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- publish_history
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `publish_history` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`    INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `version`    INT UNSIGNED NOT NULL DEFAULT 1,
  `status`     ENUM('success','error') NOT NULL DEFAULT 'success',
  `notes`      TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_history_site` (`site_id`),
  KEY `fk_history_user` (`user_id`),
  CONSTRAINT `fk_history_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- blog_posts
-- --------------------------------------------------------
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
  `sort_order`        INT UNSIGNED NOT NULL DEFAULT 0,
  `meta_title`        VARCHAR(255) DEFAULT NULL,
  `meta_description`  VARCHAR(500) DEFAULT NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blog_posts_site_slug` (`site_id`, `slug`),
  KEY `fk_blog_posts_site` (`site_id`),
  KEY `idx_blog_posts_site_status_order` (`site_id`, `status`, `sort_order`, `published_at`),
  KEY `fk_blog_posts_featured_media` (`featured_image_id`),
  CONSTRAINT `fk_blog_posts_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_blog_posts_featured_media` FOREIGN KEY (`featured_image_id`) REFERENCES `media` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- portfolio_entries
-- --------------------------------------------------------
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

-- --------------------------------------------------------
-- portfolio_media
-- --------------------------------------------------------
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

-- Vice videi (YouTube / Vimeo) u polozky portfolia; portfolio_entries.video_url
-- zustava zrcadlem prvniho videa (viz migrace 071).
CREATE TABLE IF NOT EXISTS `portfolio_videos` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `portfolio_id` INT UNSIGNED NOT NULL,
  `video_url`    VARCHAR(1000) NOT NULL,
  `sort_order`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_portfolio_videos_entry_order` (`portfolio_id`, `sort_order`, `id`),
  CONSTRAINT `fk_portfolio_videos_entry` FOREIGN KEY (`portfolio_id`) REFERENCES `portfolio_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- blog_media
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `blog_media` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blog_post_id` INT UNSIGNED NOT NULL,
  `media_id`     INT UNSIGNED NOT NULL,
  `sort_order`   INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_blog_media_post` (`blog_post_id`),
  KEY `fk_blog_media_media` (`media_id`),
  CONSTRAINT `fk_blog_media_post` FOREIGN KEY (`blog_post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_blog_media_media` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- faq_items
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `faq_items` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`    INT UNSIGNED NOT NULL,
  `question`   VARCHAR(500) NOT NULL DEFAULT '',
  `answer`     TEXT NOT NULL,
  `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_faq_items_site` (`site_id`),
  CONSTRAINT `fk_faq_items_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- service_items
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_items` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`      INT UNSIGNED NOT NULL,
  `title`        VARCHAR(255) NOT NULL DEFAULT '',
  `description`  TEXT DEFAULT NULL,
  `image_url`    VARCHAR(1000) DEFAULT NULL,
  `icon_url`     VARCHAR(1000) DEFAULT NULL,
  `button_text`  VARCHAR(255) DEFAULT NULL,
  `button_url`   VARCHAR(1000) DEFAULT NULL,
  `sort_order`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_service_items_site` (`site_id`),
  CONSTRAINT `fk_service_items_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- contact_info
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contact_info` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`       INT UNSIGNED NOT NULL,
  `phone_1`       VARCHAR(60) DEFAULT NULL,
  `phone_2`       VARCHAR(60) DEFAULT NULL,
  `phone_3`       VARCHAR(60) DEFAULT NULL,
  `email`         VARCHAR(255) DEFAULT NULL,
  `address_1`     VARCHAR(500) DEFAULT NULL,
  `address_2`     VARCHAR(500) DEFAULT NULL,
  `maps_url`      VARCHAR(1000) DEFAULT NULL,
  `reviews_url`   VARCHAR(1000) DEFAULT NULL,
  `social_1_type` VARCHAR(30) DEFAULT NULL,
  `social_1_url`  VARCHAR(500) DEFAULT NULL,
  `social_2_type` VARCHAR(30) DEFAULT NULL,
  `social_2_url`  VARCHAR(500) DEFAULT NULL,
  `social_3_type` VARCHAR(30) DEFAULT NULL,
  `social_3_url`  VARCHAR(500) DEFAULT NULL,
  `social_4_type` VARCHAR(30) DEFAULT NULL,
  `social_4_url`  VARCHAR(500) DEFAULT NULL,
  `social_5_type` VARCHAR(30) DEFAULT NULL,
  `social_5_url`  VARCHAR(500) DEFAULT NULL,
  `form_email`    VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contact_info_site` (`site_id`),
  CONSTRAINT `fk_contact_info_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- reviews
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reviews` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`       INT UNSIGNED NOT NULL,
  `author_name`   VARCHAR(160) NOT NULL,
  `context_label` VARCHAR(160) DEFAULT NULL,
  `rating`        TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `review_text`   TEXT NOT NULL,
  `sort_order`    INT NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reviews_site_sort` (`site_id`, `sort_order`, `id`),
  KEY `idx_reviews_site_active` (`site_id`, `is_active`, `sort_order`, `id`),
  CONSTRAINT `fk_reviews_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
