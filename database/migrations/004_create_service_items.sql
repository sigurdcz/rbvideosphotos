CREATE TABLE IF NOT EXISTS `service_items` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id`      INT UNSIGNED NOT NULL,
  `title`        VARCHAR(255) NOT NULL DEFAULT '',
  `description`  TEXT DEFAULT NULL,
  `image_url`    VARCHAR(1000) DEFAULT NULL,
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

INSERT INTO service_items (site_id, title, description, image_url, button_text, button_url, sort_order, is_active)
SELECT s.id, d.title, d.description, d.image_url, d.button_text, d.button_url, d.sort_order, 1
FROM sites s
JOIN (
  SELECT 1 AS sort_order, 'Svatební foto & video' AS title, 'Zachytíme každý dotek, každý pohled a každý důležitý moment.' AS description, '' AS image_url, 'Poptat službu' AS button_text, '/kontakt' AS button_url
  UNION ALL SELECT 2, 'Rodinné & portrétní focení', 'Přirozené, nenucené a krásné snímky pro rodiny, páry i jednotlivce.', '', 'Poptat službu', '/kontakt'
  UNION ALL SELECT 3, 'Reklamní foto & video', 'Profesionální vizuální obsah pro firmy, produkty, brand i kampaně.', '', 'Poptat službu', '/kontakt'
  UNION ALL SELECT 4, 'Akce & události', 'Konference, plesy, narozeniny i kulturní akce. Každá událost si zaslouží kvalitní dokumentaci.', '', 'Poptat službu', '/kontakt'
) d
LEFT JOIN service_items si ON si.site_id = s.id
WHERE si.id IS NULL;
