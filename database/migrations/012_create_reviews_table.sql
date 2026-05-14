CREATE TABLE IF NOT EXISTS reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT UNSIGNED NOT NULL,
    author_name VARCHAR(160) NOT NULL,
    context_label VARCHAR(160) DEFAULT NULL,
    rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
    review_text TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_reviews_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    INDEX idx_reviews_site_sort (site_id, sort_order, id),
    INDEX idx_reviews_site_active (site_id, is_active, sort_order, id)
);
