-- Flat product_categories schema (CSV import format).
-- WARNING: Truncates and drops existing hierarchical data.

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE product_categories;
DROP TABLE IF EXISTS product_categories;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE product_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_profile_type VARCHAR(100) NOT NULL DEFAULT '',
    business_heading VARCHAR(255) NOT NULL DEFAULT '',
    business_category VARCHAR(255) NOT NULL,
    business_category_slug VARCHAR(255) NOT NULL,
    product_category VARCHAR(255) NOT NULL,
    product_category_slug VARCHAR(255) NOT NULL,
    directory_priority INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    keywords TEXT,
    tags TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY uk_business_product_slug (business_category_slug, product_category_slug),
    INDEX idx_active (is_active),
    INDEX idx_business_slug (business_category_slug),
    INDEX idx_product_slug (product_category_slug),
    INDEX idx_directory_priority (directory_priority),
    INDEX idx_business_heading (business_heading)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
