CREATE TABLE IF NOT EXISTS mail_archive (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 kind VARCHAR(32) NOT NULL,
 recipient VARCHAR(255) NOT NULL,
 subject VARCHAR(255) NOT NULL,
 body MEDIUMTEXT NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'stored',
 error_code VARCHAR(64) NULL,
 created_at DATETIME NOT NULL,
 attempted_at DATETIME NULL,
 INDEX mail_archive_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
