-- SEO Editor CMS — kompletní schéma databáze
-- Verze: 1.0 (konsolidováno z původních migrací 001–006)
-- Databáze: MySQL 5.7+ / MariaDB 10.3+

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Uživatelé ────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`         VARCHAR(255) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          ENUM('admin','editor') NOT NULL DEFAULT 'editor',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Weby ─────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `sites` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`                  VARCHAR(255) NOT NULL,
  `domain`                VARCHAR(255) NOT NULL,
  `default_language`      VARCHAR(10) NOT NULL DEFAULT 'cs',
  `google_analytics_id`   VARCHAR(50) DEFAULT NULL,
  `google_tag_manager_id` VARCHAR(50) DEFAULT NULL,
  `head_scripts`          TEXT DEFAULT NULL,
  `body_scripts`          TEXT DEFAULT NULL,
  -- Sdílený layout (wrapper kolem page_content)
  `layout_html`           LONGTEXT DEFAULT NULL,
  `layout_css`            LONGTEXT DEFAULT NULL,
  `layout_js`             LONGTEXT DEFAULT NULL,
  -- Navigační varianty: home = hash anchory, sub = přímé URL
  `layout_nav_home`       LONGTEXT DEFAULT NULL,
  `layout_nav_sub`        LONGTEXT DEFAULT NULL,
  `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Šablony (layouty stránek) ────────────────────────────────────────────────

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

-- ─── Stránky ──────────────────────────────────────────────────────────────────

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

-- ─── Obsahové bloky ───────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `content_blocks` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id`    INT UNSIGNED NOT NULL,
  `block_key`  VARCHAR(100) NOT NULL,
  `block_type` ENUM('text','textarea','richtext','image','link','html') NOT NULL DEFAULT 'text',
  `label`      VARCHAR(255) DEFAULT NULL,
  `content`    LONGTEXT DEFAULT NULL,
  `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blocks_page_key` (`page_id`, `block_key`),
  KEY `fk_blocks_page` (`page_id`),
  CONSTRAINT `fk_blocks_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Média ────────────────────────────────────────────────────────────────────

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

-- ─── Varianty médií ───────────────────────────────────────────────────────────

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

-- ─── SEO nastavení ────────────────────────────────────────────────────────────

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
  `schema_json`         LONGTEXT DEFAULT NULL,
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_seo_page` (`page_id`),
  KEY `fk_seo_og_image` (`og_image_id`),
  KEY `fk_seo_tw_image` (`twitter_image_id`),
  CONSTRAINT `fk_seo_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_seo_og_image` FOREIGN KEY (`og_image_id`) REFERENCES `media` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_seo_tw_image` FOREIGN KEY (`twitter_image_id`) REFERENCES `media` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Přesměrování ─────────────────────────────────────────────────────────────

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

-- ─── Historie publikování ─────────────────────────────────────────────────────

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

-- ─── Blogové příspěvky ────────────────────────────────────────────────────────
-- URL: /{slug} (bez prefixu /blog/)

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

-- ─── Portfolio (Naše práce) ───────────────────────────────────────────────────
-- URL: /{slug} (bez prefixu /nase-prace/)

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

-- ─── Média portfolia ──────────────────────────────────────────────────────────

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

-- ─── Galerie blogu ────────────────────────────────────────────────────────────

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

-- ─── FAQ položky ─────────────────────────────────────────────────────────────

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

-- ─── Kontaktní informace ──────────────────────────────────────────────────────

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

SET FOREIGN_KEY_CHECKS = 1;
