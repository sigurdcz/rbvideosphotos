ALTER TABLE `blog_posts`
  ADD COLUMN `hero_image_id` INT UNSIGNED DEFAULT NULL AFTER `featured_image_id`,
  ADD COLUMN `thumbnail_image_id` INT UNSIGNED DEFAULT NULL AFTER `hero_image_id`,
  ADD COLUMN `hero_focus_y` TINYINT UNSIGNED DEFAULT NULL AFTER `thumbnail_image_id`,
  ADD COLUMN `thumbnail_focus_y` TINYINT UNSIGNED DEFAULT NULL AFTER `hero_focus_y`,
  ADD KEY `fk_blog_posts_hero_media` (`hero_image_id`),
  ADD KEY `fk_blog_posts_thumbnail_media` (`thumbnail_image_id`),
  ADD CONSTRAINT `fk_blog_posts_hero_media` FOREIGN KEY (`hero_image_id`) REFERENCES `media` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_blog_posts_thumbnail_media` FOREIGN KEY (`thumbnail_image_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;

ALTER TABLE `portfolio_entries`
  ADD COLUMN `hero_image_id` INT UNSIGNED DEFAULT NULL AFTER `video_url`,
  ADD COLUMN `thumbnail_image_id` INT UNSIGNED DEFAULT NULL AFTER `hero_image_id`,
  ADD COLUMN `hero_focus_y` TINYINT UNSIGNED DEFAULT NULL AFTER `thumbnail_image_id`,
  ADD COLUMN `thumbnail_focus_y` TINYINT UNSIGNED DEFAULT NULL AFTER `hero_focus_y`,
  ADD KEY `fk_portfolio_entries_hero_media` (`hero_image_id`),
  ADD KEY `fk_portfolio_entries_thumbnail_media` (`thumbnail_image_id`),
  ADD CONSTRAINT `fk_portfolio_entries_hero_media` FOREIGN KEY (`hero_image_id`) REFERENCES `media` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_portfolio_entries_thumbnail_media` FOREIGN KEY (`thumbnail_image_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;
