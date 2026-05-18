<?php
/**
 * Admin category CSV helpers — uses product_categories (flat schema).
 */

function adminCategoryTableName() {
    return 'product_categories';
}

function ensureAdminCategoryTable($connect) {
    $table = adminCategoryTableName();
    $check = mysqli_query($connect, "SHOW TABLES LIKE '$table'");
    if ($check && mysqli_num_rows($check) > 0) {
        $col = mysqli_query($connect, "SHOW COLUMNS FROM `$table` LIKE 'business_profile_type'");
        return $col && mysqli_num_rows($col) > 0;
    }

    $sql = "CREATE TABLE IF NOT EXISTS `$table` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    return (bool) mysqli_query($connect, $sql);
}

function adminCategoryCsvHeaders() {
    return [
        'Business Profile Type',
        'Business Heading',
        'Business Category',
        'Business Category Slug',
        'Product Category',
        'Product Category Slug',
        'Directory Priority',
        'Is Active',
        'Keywords',
        'Tags',
    ];
}

function adminCategoryRequiredHeaderKeys() {
    return [
        'business profile type',
        'business heading',
        'business category',
        'business category slug',
        'product category',
        'product category slug',
        'directory priority',
        'is active',
        'keywords',
        'tags',
    ];
}

function adminParseIsActive($value) {
    $v = strtolower(trim((string) $value));
    if ($v === '0' || $v === 'no' || $v === 'n' || $v === 'false' || $v === 'inactive') {
        return 0;
    }
    return 1;
}

function adminSlugify($text) {
    $text = strtolower(trim((string) $text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-') ?: 'category';
}
