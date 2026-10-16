-- Rucni poradi blogovych clanku ve vypisech (/blog, karty na homepage, API).
-- 0 = bez rucniho cisla. Razeni je
-- `(sort_order = 0) ASC, sort_order ASC, published_at DESC, id DESC`, takze
-- ocislovane clanky jdou napred v poradi cisel a zbytek za nimi podle data.
-- Zamerne bez backfillu: dokud nikdo cislo nevyplni, zustava dnesni poradi podle
-- data uplne beze zmeny.

ALTER TABLE `blog_posts`
  ADD COLUMN `sort_order` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `published_at`,
  ADD KEY `idx_blog_posts_site_status_order` (`site_id`, `status`, `sort_order`, `published_at`);
