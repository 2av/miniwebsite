<?php
/**
 * Migration Script: Copy data from old tables to new dynamic tables
 * Run this AFTER creating the new tables with create_dynamic_tables.php
 */

require_once(__DIR__ . '/../app/config/database.php');

echo '<h2>Migration Script: Old Tables → New Dynamic Tables</h2>';
echo '<p><strong>Note:</strong> This will clear existing data in new tables and re-migrate with proper user_id management.</p>';

// ============================================
// 0. CLEAR EXISTING DATA AND UPDATE TABLE STRUCTURE
// ============================================
echo '<h3>0. Clearing existing data and updating table structure...</h3>';

// Disable foreign key checks temporarily
mysqli_query($connect, "SET FOREIGN_KEY_CHECKS = 0");

// Clear existing data
$clear1 = mysqli_query($connect, "TRUNCATE TABLE card_products_services");
$clear2 = mysqli_query($connect, "TRUNCATE TABLE card_product_pricing");
$clear3 = mysqli_query($connect, "TRUNCATE TABLE card_image_gallery");

if($clear1 && $clear2 && $clear3) {
    echo "<div class='success'>✓ Cleared existing data from new tables</div>";
} else {
    echo "<div class='warning'>⚠ Some tables may not have been cleared: " . mysqli_error($connect) . "</div>";
}

// Check and update table structure for user_id
$tables_to_check = [
    'card_products_services' => ['user_email', 'user_id'],
    'card_product_pricing' => ['user_email', 'user_id'],
    'card_image_gallery' => ['user_email', 'user_id']
];

foreach($tables_to_check as $table => $columns) {
    $check_user_email = mysqli_query($connect, "SHOW COLUMNS FROM $table LIKE 'user_email'");
    $check_user_id = mysqli_query($connect, "SHOW COLUMNS FROM $table LIKE 'user_id'");
    
    if(mysqli_num_rows($check_user_email) > 0 && mysqli_num_rows($check_user_id) == 0) {
        // Need to add user_id column and remove user_email
        $alter1 = "ALTER TABLE $table ADD COLUMN user_id INT(11) NOT NULL AFTER card_id";
        $alter2 = "ALTER TABLE $table ADD INDEX idx_user_id (user_id)";
        $alter3 = "ALTER TABLE $table ADD FOREIGN KEY (user_id) REFERENCES customer_login(id) ON DELETE CASCADE";
        $alter4 = "ALTER TABLE $table DROP COLUMN user_email";
        
        if(mysqli_query($connect, $alter1) && mysqli_query($connect, $alter2) && mysqli_query($connect, $alter3) && mysqli_query($connect, $alter4)) {
            echo "<div class='success'>✓ Updated $table to use user_id</div>";
        } else {
            echo "<div class='danger'>✗ Error updating $table: " . mysqli_error($connect) . "</div>";
        }
    } elseif(mysqli_num_rows($check_user_id) > 0) {
        echo "<div class='info'>ℹ $table already uses user_id</div>";
    }
}

// Check tax_rate column size
$check_column = mysqli_query($connect, "SHOW COLUMNS FROM card_product_pricing LIKE 'tax_rate'");
if($check_column && mysqli_num_rows($check_column) > 0) {
    $col_info = mysqli_fetch_array($check_column);
    if(strpos($col_info['Type'], 'decimal(5,2)') !== false || strpos($col_info['Type'], 'decimal(5, 2)') !== false) {
        $alter = "ALTER TABLE card_product_pricing MODIFY tax_rate DECIMAL(10,2) DEFAULT 0.00";
        if(mysqli_query($connect, $alter)) {
            echo "<div class='success'>✓ Updated tax_rate column to DECIMAL(10,2)</div>";
        }
    }
}

// Keep foreign key checks disabled during migration
// We'll re-enable them at the end

// ============================================
// 1. MIGRATE PRODUCTS & SERVICES (digi_card2 → card_products_services)
// ============================================
echo '<h3>1. Migrating Products & Services (digi_card2 → card_products_services)</h3>';

$migrate_count1 = 0;
$error_count1 = 0;
$skipped_count1 = 0;

for($i = 1; $i <= 10; $i++) {
    // Get user_id from user_email by joining with customer_login
    // Only get records where user exists in customer_login
    $query = "SELECT d2.id as card_id, d2.user_email, d2.d_pro_name$i, d2.d_pro_img$i, cl.id as user_id
              FROM digi_card2 d2
              INNER JOIN customer_login cl ON d2.user_email = cl.user_email
              WHERE ((d2.d_pro_name$i IS NOT NULL AND d2.d_pro_name$i != '') 
                 OR (d2.d_pro_img$i IS NOT NULL))
              AND cl.id IS NOT NULL
              AND cl.id > 0";
    
    $result = mysqli_query($connect, $query);
    if($result && mysqli_num_rows($result) > 0) {
        while($row = mysqli_fetch_array($result)) {
            $card_id = mysqli_real_escape_string($connect, $row['card_id']);
            $user_id = intval($row['user_id']);
            $product_name = !empty($row["d_pro_name$i"]) ? $row["d_pro_name$i"] : '';
            $product_image = !empty($row["d_pro_img$i"]) ? $row["d_pro_img$i"] : null;
            
            // Double-check user_id is valid
            if($user_id > 0) {
                // Verify user exists in customer_login
                $verify_user = mysqli_query($connect, "SELECT id FROM customer_login WHERE id = $user_id");
                if($verify_user && mysqli_num_rows($verify_user) > 0) {
                    if($product_image) {
                        $product_image_escaped = mysqli_real_escape_string($connect, $product_image);
                        $product_name_escaped = mysqli_real_escape_string($connect, $product_name);
                        $insert = "INSERT INTO card_products_services (card_id, user_id, product_name, product_image, display_order) 
                                   VALUES ('$card_id', $user_id, '$product_name_escaped', '$product_image_escaped', $i)";
                    } else {
                        $product_name_escaped = mysqli_real_escape_string($connect, $product_name);
                        $insert = "INSERT INTO card_products_services (card_id, user_id, product_name, display_order) 
                                   VALUES ('$card_id', $user_id, '$product_name_escaped', $i)";
                    }
                    
                    if(mysqli_query($connect, $insert)) {
                        $migrate_count1++;
                    } else {
                        $error_count1++;
                        echo "<div class='warning'>⚠ Error migrating product for card_id: $card_id, slot: $i - " . mysqli_error($connect) . "</div>";
                    }
                } else {
                    $skipped_count1++;
                    echo "<div class='warning'>⚠ User ID $user_id not found in customer_login for card_id: $card_id, slot: $i</div>";
                }
            } else {
                $skipped_count1++;
                echo "<div class='warning'>⚠ Invalid user_id for email: ".htmlspecialchars($row['user_email']).", card_id: $card_id, slot: $i</div>";
            }
        }
    }
}
if($error_count1 > 0) {
    echo "<div class='warning'>⚠ $error_count1 products/services records failed to migrate</div>";
}
if($skipped_count1 > 0) {
    echo "<div class='info'>ℹ $skipped_count1 products/services records skipped (user not found)</div>";
}
echo "<div class='success'>✓ Migrated $migrate_count1 products/services records</div>";

// ============================================
// 2. MIGRATE PRODUCT PRICING (products → card_product_pricing)
// ============================================
echo '<h3>2. Migrating Product Pricing (products → card_product_pricing)</h3>';

$migrate_count2 = 0;
$error_count2 = 0;
$skipped_count2 = 0;
for($i = 1; $i <= 20; $i++) {
    // Get user_id from user_email by joining with customer_login
    // Also try to get user_email from digi_card table if products table doesn't have it
    // Use INNER JOIN to ensure user exists
    $query = "SELECT p.id as card_id, 
                     COALESCE(p.user_email, dc.user_email) as user_email,
                     p.pro_name$i, p.pro_img$i, p.pro_mrp$i, p.pro_price$i, p.pro_tax$i,
                     cl.id as user_id
              FROM products p
              LEFT JOIN digi_card dc ON p.id = dc.id
              INNER JOIN customer_login cl ON COALESCE(p.user_email, dc.user_email) = cl.user_email
              WHERE ((p.pro_name$i IS NOT NULL AND p.pro_name$i != '') 
                 OR (p.pro_img$i IS NOT NULL))
              AND cl.id IS NOT NULL
              AND cl.id > 0";
    
    $result = mysqli_query($connect, $query);
    if($result && mysqli_num_rows($result) > 0) {
        while($row = mysqli_fetch_array($result)) {
            $card_id = mysqli_real_escape_string($connect, $row['card_id']);
            $user_id = intval($row['user_id']);
            $product_name = !empty($row["pro_name$i"]) ? $row["pro_name$i"] : '';
            $product_image = !empty($row["pro_img$i"]) ? $row["pro_img$i"] : null;
            $mrp = !empty($row["pro_mrp$i"]) ? floatval($row["pro_mrp$i"]) : 0.00;
            $price = !empty($row["pro_price$i"]) ? floatval($row["pro_price$i"]) : 0.00;
            
            // Handle tax rate - validate and limit to reasonable range
            $tax = 0.00;
            if(!empty($row["pro_tax$i"])) {
                $tax_raw = trim($row["pro_tax$i"]);
                // Remove any non-numeric characters except decimal point
                $tax_raw = preg_replace('/[^0-9.]/', '', $tax_raw);
                
                if($tax_raw !== '' && is_numeric($tax_raw)) {
                    $tax_raw = floatval($tax_raw);
                    // If tax is > 100, it might be a percentage, divide by 100
                    if($tax_raw > 100 && $tax_raw <= 10000) {
                        $tax = $tax_raw / 100;
                    } else {
                        $tax = $tax_raw;
                    }
                    // Limit to max 999.99
                    if($tax > 999.99) {
                        echo "<div class='warning'>⚠ Tax rate too large for card_id: $card_id, slot: $i, value: $tax_raw, capped to 999.99</div>";
                        $tax = 999.99;
                    }
                    if($tax < 0) {
                        $tax = 0.00;
                    }
                } else {
                    // Invalid tax value, log it
                    if($row["pro_tax$i"] != '') {
                        echo "<div class='warning'>⚠ Invalid tax value for card_id: $card_id, slot: $i, value: '".htmlspecialchars($row["pro_tax$i"])."', using 0.00</div>";
                    }
                    $tax = 0.00;
                }
            }
            
            // Double-check user_id is valid
            if($user_id > 0) {
                // Verify user exists in customer_login
                $verify_user = mysqli_query($connect, "SELECT id FROM customer_login WHERE id = $user_id");
                if($verify_user && mysqli_num_rows($verify_user) > 0) {
                    if($product_image) {
                        $product_image_escaped = mysqli_real_escape_string($connect, $product_image);
                        $product_name_escaped = mysqli_real_escape_string($connect, $product_name);
                        $insert = "INSERT INTO card_product_pricing (card_id, user_id, product_name, product_image, mrp, selling_price, tax_rate, display_order) 
                                   VALUES ('$card_id', $user_id, '$product_name_escaped', '$product_image_escaped', $mrp, $price, $tax, $i)";
                    } else {
                        $product_name_escaped = mysqli_real_escape_string($connect, $product_name);
                        $insert = "INSERT INTO card_product_pricing (card_id, user_id, product_name, mrp, selling_price, tax_rate, display_order) 
                                   VALUES ('$card_id', $user_id, '$product_name_escaped', $mrp, $price, $tax, $i)";
                    }
                    
                    if(mysqli_query($connect, $insert)) {
                        $migrate_count2++;
                    } else {
                        $error_count2++;
                        echo "<div class='danger'>Error migrating product pricing for card_id: $card_id, slot: $i - " . mysqli_error($connect) . "</div>";
                    }
                } else {
                    $skipped_count2++;
                    echo "<div class='warning'>⚠ User ID $user_id not found in customer_login for card_id: $card_id, slot: $i</div>";
                }
            } else {
                $skipped_count2++;
                echo "<div class='warning'>⚠ Invalid user_id for email: ".htmlspecialchars($row['user_email']).", card_id: $card_id, slot: $i</div>";
            }
        }
    }
}
if($error_count2 > 0) {
    echo "<div class='warning'>⚠ $error_count2 product pricing records failed to migrate</div>";
}
if($skipped_count2 > 0) {
    echo "<div class='info'>ℹ $skipped_count2 product pricing records skipped (user not found)</div>";
}
echo "<div class='success'>✓ Migrated $migrate_count2 product pricing records</div>";

// ============================================
// 3. MIGRATE IMAGE GALLERY (digi_card3 → card_image_gallery)
// ============================================
echo '<h3>3. Migrating Image Gallery (digi_card3 → card_image_gallery)</h3>';

$migrate_count3 = 0;
$error_count3 = 0;
$skipped_count3 = 0;
for($i = 1; $i <= 10; $i++) {
    // Get user_id from user_email by joining with customer_login
    // Use INNER JOIN to ensure user exists
    $query = "SELECT d3.id as card_id, d3.user_email, d3.d_gall_img$i, cl.id as user_id
              FROM digi_card3 d3
              INNER JOIN customer_login cl ON d3.user_email = cl.user_email
              WHERE d3.d_gall_img$i IS NOT NULL
              AND cl.id IS NOT NULL
              AND cl.id > 0";
    
    $result = mysqli_query($connect, $query);
    if($result && mysqli_num_rows($result) > 0) {
        while($row = mysqli_fetch_array($result)) {
            $card_id = mysqli_real_escape_string($connect, $row['card_id']);
            $user_id = intval($row['user_id']);
            $gallery_image = $row["d_gall_img$i"];
            
            // Double-check user_id is valid
            if($user_id > 0) {
                // Verify user exists in customer_login
                $verify_user = mysqli_query($connect, "SELECT id FROM customer_login WHERE id = $user_id");
                if($verify_user && mysqli_num_rows($verify_user) > 0) {
                    $gallery_image_escaped = mysqli_real_escape_string($connect, $gallery_image);
                    $insert = "INSERT INTO card_image_gallery (card_id, user_id, gallery_image, display_order) 
                               VALUES ('$card_id', $user_id, '$gallery_image_escaped', $i)";
                    
                    if(mysqli_query($connect, $insert)) {
                        $migrate_count3++;
                    } else {
                        $error_count3++;
                        echo "<div class='danger'>Error migrating gallery image for card_id: $card_id, slot: $i - " . mysqli_error($connect) . "</div>";
                    }
                } else {
                    $skipped_count3++;
                    echo "<div class='warning'>⚠ User ID $user_id not found in customer_login for card_id: $card_id, slot: $i</div>";
                }
            } else {
                $skipped_count3++;
                echo "<div class='warning'>⚠ Invalid user_id for email: ".htmlspecialchars($row['user_email']).", card_id: $card_id, slot: $i</div>";
            }
        }
    }
}
if($error_count3 > 0) {
    echo "<div class='warning'>⚠ $error_count3 gallery image records failed to migrate</div>";
}
if($skipped_count3 > 0) {
    echo "<div class='info'>ℹ $skipped_count3 gallery image records skipped (user not found)</div>";
}
echo "<div class='success'>✓ Migrated $migrate_count3 gallery image records</div>";

// Re-enable foreign key checks after migration
mysqli_query($connect, "SET FOREIGN_KEY_CHECKS = 1");
echo "<div class='success'>✓ Re-enabled foreign key constraints</div>";

echo '<br><div class="info">';
echo '<h3>Migration Complete!</h3>';
echo '<p><strong>Total Records Migrated:</strong></p>';
echo '<ul>';
echo "<li>Products & Services: $migrate_count1 records (with user_id)</li>";
echo "<li>Product Pricing: $migrate_count2 records (with user_id)</li>";
echo "<li>Image Gallery: $migrate_count3 records (with user_id)</li>";
echo '</ul>';

$total_errors = $error_count1 + $error_count2 + $error_count3;
$total_skipped = $skipped_count1 + $skipped_count2 + $skipped_count3;
if($total_errors > 0) {
    echo "<p><strong>⚠ Errors:</strong> $total_errors records failed to migrate</p>";
}
if($total_skipped > 0) {
    echo "<p><strong>ℹ Skipped:</strong> $total_skipped records skipped (user not found in customer_login table)</p>";
}

echo '<p><strong>Key Improvements:</strong></p>';
echo '<ul>';
echo '<li>✓ All tables now use user_id (INT) instead of user_email (VARCHAR)</li>';
echo '<li>✓ Foreign key relationships ensure data integrity</li>';
echo '<li>✓ CASCADE delete: if user is deleted, their data is automatically removed</li>';
echo '<li>✓ Better performance with proper indexing</li>';
echo '<li>✓ Unlimited items (no fixed column limits)</li>';
echo '</ul>';

echo '<p><strong>Next Steps:</strong></p>';
echo '<ol>';
echo '<li>Update PHP code to use new table structure with user_id</li>';
echo '<li>Test all functionality thoroughly</li>';
echo '<li>Once confirmed working, you can backup and remove old tables (optional)</li>';
echo '</ol>';
echo '</div>';

?>

<style>
.success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 5px 0; }
.danger { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 5px 0; }
.warning { color: #856404; padding: 10px; background: #fff3cd; border: 1px solid #ffeeba; margin: 5px 0; }
.info { padding: 15px; background: #d1ecf1; border: 1px solid #bee5eb; margin: 10px 0; }
.info ul, .info ol { margin: 10px 0; padding-left: 20px; }
.info li { margin: 5px 0; }
h2, h3 { color: #333; }
</style>




