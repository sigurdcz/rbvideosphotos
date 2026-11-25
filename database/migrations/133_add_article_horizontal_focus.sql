-- MySQL and MariaDB can retry this after either table has already been altered.
SET @article_focus_columns := (
  SELECT CONCAT_WS(', ',
    IF(COUNT(CASE WHEN column_name = 'hero_focus_x' THEN 1 END) = 0,
      'ADD COLUMN `hero_focus_x` TINYINT UNSIGNED DEFAULT NULL AFTER `thumbnail_image_id`', NULL),
    IF(COUNT(CASE WHEN column_name = 'thumbnail_focus_x' THEN 1 END) = 0,
      'ADD COLUMN `thumbnail_focus_x` TINYINT UNSIGNED DEFAULT NULL AFTER `hero_focus_y`', NULL)
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'blog_posts'
);
SET @article_focus_sql := IF(@article_focus_columns = '', 'SELECT 1',
  CONCAT('ALTER TABLE `blog_posts` ', @article_focus_columns));
PREPARE article_focus_stmt FROM @article_focus_sql;
EXECUTE article_focus_stmt;
DEALLOCATE PREPARE article_focus_stmt;

SET @article_focus_columns := (
  SELECT CONCAT_WS(', ',
    IF(COUNT(CASE WHEN column_name = 'hero_focus_x' THEN 1 END) = 0,
      'ADD COLUMN `hero_focus_x` TINYINT UNSIGNED DEFAULT NULL AFTER `thumbnail_image_id`', NULL),
    IF(COUNT(CASE WHEN column_name = 'thumbnail_focus_x' THEN 1 END) = 0,
      'ADD COLUMN `thumbnail_focus_x` TINYINT UNSIGNED DEFAULT NULL AFTER `hero_focus_y`', NULL)
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'portfolio_entries'
);
SET @article_focus_sql := IF(@article_focus_columns = '', 'SELECT 1',
  CONCAT('ALTER TABLE `portfolio_entries` ', @article_focus_columns));
PREPARE article_focus_stmt FROM @article_focus_sql;
EXECUTE article_focus_stmt;
DEALLOCATE PREPARE article_focus_stmt;
