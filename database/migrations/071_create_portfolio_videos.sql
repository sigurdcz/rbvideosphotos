-- Vice videi (YouTube / Vimeo) u jedne polozky portfolia.
-- portfolio_entries.video_url zustava zrcadlem PRVNIHO videa, aby vypis, homepage,
-- lightbox, JSON-LD i API cetly dal beze zmeny; jediny zapisovac obou mist je
-- PortfolioRepository::setVideos().

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

INSERT INTO `portfolio_videos` (`portfolio_id`, `video_url`, `sort_order`)
SELECT p.`id`, TRIM(p.`video_url`), 0
FROM `portfolio_entries` p
WHERE NULLIF(TRIM(p.`video_url`), '') IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `portfolio_videos` v WHERE v.`portfolio_id` = p.`id`);
