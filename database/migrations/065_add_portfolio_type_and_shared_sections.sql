ALTER TABLE `portfolio_entries`
  ADD COLUMN `content_type` VARCHAR(16) NOT NULL DEFAULT 'photo' AFTER `video_url`,
  ADD KEY `idx_portfolio_entries_listing` (`site_id`, `status`, `content_type`, `sort_order`, `id`);

UPDATE `portfolio_entries`
SET `content_type` = CASE
  WHEN NULLIF(TRIM(`video_url`), '') IS NOT NULL THEN 'video'
  ELSE 'photo'
END;

CREATE TABLE IF NOT EXISTS `shared_sections` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id` INT UNSIGNED NOT NULL,
  `section_key` VARCHAR(80) NOT NULL,
  `title` VARCHAR(190) NOT NULL,
  `content_json` LONGTEXT NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shared_sections_site_key` (`site_id`, `section_key`),
  CONSTRAINT `fk_shared_sections_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `shared_sections` (`site_id`, `section_key`, `title`, `content_json`, `is_active`)
SELECT
  s.id,
  'process_cooperation',
  'Jak to probíhá',
  '{"kicker":"Jak to probíhá","title":"Jak probíhá spolupráce","intro":"Od první zprávy až po předání vašich vzpomínek. Jasný postup, který vám zajistí klid a jistotu v každém kroku.","steps":[{"title":"Nezávazná poptávka","text":"Napište nám základní představu, termín a místo. Ozveme se co nejdříve zpět.","icon":"mail"},{"title":"Konzultace a plán","text":"Probereme vaše focení, harmonogram dne, důležité momenty a vaše přání.","icon":"assignment"},{"title":"Focení / natáčení","text":"V den svatby pracujeme nenápadně a zachytíme emoce, atmosféru i klíčové okamžiky.","icon":"photo_camera"},{"title":"Výběr a dodání","text":"Pečlivě upravíme fotografie a video a předáme vše v domluveném termínu.","icon":"video_library"}],"benefits":[{"title":"Rychlá komunikace","text":"Odpovídáme rychle a jsme vždy k dispozici.","icon":"chat"},{"title":"Jasný postup","text":"Víte, co se děje a co vás čeká v každé fázi.","icon":"event_available"},{"title":"Dodání v termínu","text":"Termíny dodržujeme, abyste měli klid.","icon":"schedule"},{"title":"Osobní přístup","text":"Každou svatbu bereme jako jedinečný příběh.","icon":"favorite"}]}',
  1
FROM `sites` s
ON DUPLICATE KEY UPDATE `section_key` = VALUES(`section_key`);

INSERT INTO `content_blocks` (`page_id`, `block_key`, `block_type`, `label`, `content`, `sort_order`, `autofill_disabled`)
SELECT p.id, 'contact_visual', 'image', 'Kontakt - obrazek vlevo', COALESCE(NULLIF(s.default_hero_poster_url, ''), ''), 91, 1
FROM `pages` p
JOIN `sites` s ON s.id = p.site_id
WHERE TRIM(BOTH '/' FROM p.url_path) = ''
  AND NOT EXISTS (SELECT 1 FROM `content_blocks` b WHERE b.page_id = p.id AND b.block_key = 'contact_visual');
