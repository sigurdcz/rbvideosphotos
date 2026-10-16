-- Persistent progress for browser-driven Demo content seeding on shared hosting.
-- One row per site deliberately prevents concurrent Demo jobs for the same web.

CREATE TABLE IF NOT EXISTS demo_seed_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    job_token CHAR(64) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    phase VARCHAR(64) NOT NULL DEFAULT 'validate',
    phase_cursor INT UNSIGNED NOT NULL DEFAULT 0,
    completed_units INT UNSIGNED NOT NULL DEFAULT 0,
    total_units INT UNSIGNED NOT NULL DEFAULT 1,
    message VARCHAR(255) NOT NULL DEFAULT '',
    last_error TEXT NULL,
    result_json LONGTEXT NULL,
    lock_token CHAR(64) NULL,
    lock_expires_at DATETIME NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    finished_at DATETIME NULL,
    UNIQUE KEY uq_demo_seed_jobs_site (site_id),
    UNIQUE KEY uq_demo_seed_jobs_token (job_token),
    KEY idx_demo_seed_jobs_status (status),
    CONSTRAINT fk_demo_seed_jobs_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    CONSTRAINT fk_demo_seed_jobs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
