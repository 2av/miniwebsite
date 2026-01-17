<?php
/**
 * Add saleskit_enabled column to user_details table
 * 
 * This script adds the saleskit_enabled column if it doesn't exist,
 * and backfills values from customer_login for existing CUSTOMER records.
 */

require_once(__DIR__ . '/../app/config/database.php');

echo '<h2>Add saleskit_enabled Column to user_details</h2>';

// Check if column exists
$check_col = mysqli_query($connect, "SHOW COLUMNS FROM user_details LIKE 'saleskit_enabled'");
$col_exists = mysqli_num_rows($check_col) > 0;

if (!$col_exists) {
    // Add the column
    $alter_sql = "ALTER TABLE user_details 
                 ADD COLUMN saleskit_enabled ENUM('YES','NO') NOT NULL DEFAULT 'NO' 
                 AFTER collaboration_enabled";
    
    $result = mysqli_query($connect, $alter_sql);
    
    if ($result) {
        echo '<div class="success">✓ Column saleskit_enabled added to user_details table.</div>';
    } else {
        echo '<div class="danger">✗ Error adding column: ' . htmlspecialchars(mysqli_error($connect)) . '</div>';
        exit;
    }
} else {
    echo '<div class="info">Column saleskit_enabled already exists. Skipping creation.</div>';
}

// Backfill saleskit_enabled from customer_login for CUSTOMER role
echo '<div class="info">Backfilling saleskit_enabled values from customer_login...</div>';

$backfill_sql = "
    UPDATE user_details u
    INNER JOIN customer_login cl 
        ON u.legacy_customer_id = cl.id
        AND u.role = 'CUSTOMER'
    SET u.saleskit_enabled = COALESCE(cl.saleskit_enabled, 'NO')
    WHERE cl.saleskit_enabled IS NOT NULL
";

$backfill_result = mysqli_query($connect, $backfill_sql);

if ($backfill_result) {
    $affected = mysqli_affected_rows($connect);
    echo '<div class="success">✓ Backfilled saleskit_enabled for ' . $affected . ' customer records.</div>';
} else {
    echo '<div class="warning">⚠ Could not backfill saleskit_enabled: ' . htmlspecialchars(mysqli_error($connect)) . '</div>';
    echo '<div class="info">This is okay if customer_login table doesn\'t have saleskit_enabled column yet.</div>';
}

echo '<div class="success"><strong>✓ All done! saleskit_enabled column is now available in user_details.</strong></div>';

?>

<style>
.success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 5px 0; }
.danger  { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 5px 0; }
.warning { color: #856404; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; margin: 5px 0; }
.info    { color: #004085; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; margin: 5px 0; }
</style>




