ALTER TABLE `sites`
  ADD COLUMN `default_og_image_id` INT UNSIGNED DEFAULT NULL AFTER `apple_touch_icon_url`,
  ADD KEY `fk_sites_default_og_image` (`default_og_image_id`),
  ADD CONSTRAINT `fk_sites_default_og_image`
    FOREIGN KEY (`default_og_image_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;
