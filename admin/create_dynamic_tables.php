<?php
/**
 * Dynamic Tables Creation Script
 * This creates normalized tables for better management of products, pricing, and gallery images
 * Run this file once to create the new tables
 */

require_once(__DIR__ . '/../app/config/database.php');

// ============================================
// 1. PRODUCTS & SERVICES TABLE (replaces digi_card2)
// ============================================
$create_products_services = "CREATE TABLE IF NOT EXISTS card_products_services (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    card_id VARCHAR(50) NOT NULL,
    user_id INT(11) NOT NULL,
    product_name VARCHAR(200) DEFAULT '',
    product_image LONGBLOB,
    display_order INT(11) DEFAULT 0,
    created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_card_id (card_id),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES customer_login(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$result1 = mysqli_query($connect, $create_products_services);
if($result1) {
    echo '<div class="success">✓ card_products_services table created successfully</div>';
} else {
    echo '<div class="danger">✗ Error creating card_products_services table: ' . mysqli_error($connect) . '</div>';
}

// ============================================
// 2. PRODUCT PRICING TABLE (replaces products table numbered columns)
// ============================================
$create_product_pricing = "CREATE TABLE IF NOT EXISTS card_product_pricing (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    card_id VARCHAR(50) NOT NULL,
    user_id INT(11) NOT NULL,
    product_name VARCHAR(200) DEFAULT '',
    product_image LONGBLOB,
    mrp DECIMAL(10,2) DEFAULT 0.00,
    selling_price DECIMAL(10,2) DEFAULT 0.00,
    tax_rate DECIMAL(10,2) DEFAULT 0.00,
    display_order INT(11) DEFAULT 0,
    created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_card_id (card_id),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES customer_login(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$result2 = mysqli_query($connect, $create_product_pricing);
if($result2) {
    echo '<div class="success">✓ card_product_pricing table created successfully</div>';
} else {
    echo '<div class="danger">✗ Error creating card_product_pricing table: ' . mysqli_error($connect) . '</div>';
}

// ============================================
// 3. IMAGE GALLERY TABLE (replaces digi_card3)
// ============================================
$create_image_gallery = "CREATE TABLE IF NOT EXISTS card_image_gallery (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    card_id VARCHAR(50) NOT NULL,
    user_id INT(11) NOT NULL,
    gallery_image LONGBLOB,
    display_order INT(11) DEFAULT 0,
    created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_card_id (card_id),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES customer_login(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$result3 = mysqli_query($connect, $create_image_gallery);
if($result3) {
    echo '<div class="success">✓ card_image_gallery table created successfully</div>';
} else {
    echo '<div class="danger">✗ Error creating card_image_gallery table: ' . mysqli_error($connect) . '</div>';
}

echo '<br><h3>Table Structure Notes:</h3>';
echo '<div class="info">';
echo '<p><strong>Benefits of New Structure:</strong></p>';
echo '<ul>';
echo '<li>✓ Unlimited items (no fixed column limits)</li>';
echo '<li>✓ Better performance with proper indexing</li>';
echo '<li>✓ Easier to query and manage</li>';
echo '<li>✓ Can add/remove items dynamically</li>';
echo '<li>✓ Proper foreign key relationships (user_id → customer_login.id)</li>';
echo '<li>✓ Data integrity with CASCADE delete</li>';
echo '<li>✓ Display order support for sorting</li>';
echo '<li>✓ Timestamps for tracking</li>';
echo '<li>✓ Uses user_id instead of user_email for better management</li>';
echo '</ul>';
echo '<p><strong>Important:</strong></p>';
echo '<ul>';
echo '<li>✓ Tables use user_id (INT) linked to customer_login.id</li>';
echo '<li>✓ Foreign keys ensure data integrity</li>';
echo '<li>✓ CASCADE delete: if user is deleted, their data is automatically removed</li>';
echo '</ul>';
echo '<p><strong>Next Steps:</strong></p>';
echo '<ul>';
echo '<li>1. Run migration script to copy data from old tables to new tables</li>';
echo '<li>2. Migration will clear existing data and re-migrate with user_id</li>';
echo '<li>3. Update PHP code to use new table structure</li>';
echo '<li>4. Test thoroughly before removing old tables</li>';
echo '</ul>';
echo '</div>';

?>

<style>
.success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 5px 0; }
.danger { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 5px 0; }
.info { padding: 15px; background: #d1ecf1; border: 1px solid #bee5eb; margin: 10px 0; }
.info ul { margin: 10px 0; padding-left: 20px; }
.info li { margin: 5px 0; }
</style>




