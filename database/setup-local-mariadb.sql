CREATE DATABASE IF NOT EXISTS `seo_editor_cms`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'seo_editor_app'@'127.0.0.1'
  IDENTIFIED BY 'change_me_local_db_password';

GRANT ALL PRIVILEGES ON `seo_editor_cms`.* TO 'seo_editor_app'@'127.0.0.1';
FLUSH PRIVILEGES;
