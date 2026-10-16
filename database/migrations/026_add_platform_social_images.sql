ALTER TABLE `sites`
  ADD COLUMN `default_instagram_image_id` INT UNSIGNED DEFAULT NULL AFTER `default_og_image_id`,
  ADD COLUMN `default_twitter_image_id` INT UNSIGNED DEFAULT NULL AFTER `default_instagram_image_id`,
  ADD KEY `fk_sites_default_instagram_image` (`default_instagram_image_id`),
  ADD KEY `fk_sites_default_twitter_image` (`default_twitter_image_id`),
  ADD CONSTRAINT `fk_sites_default_instagram_image`
    FOREIGN KEY (`default_instagram_image_id`) REFERENCES `media` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sites_default_twitter_image`
    FOREIGN KEY (`default_twitter_image_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;
