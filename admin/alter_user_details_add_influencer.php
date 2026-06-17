<?php
/**
 * Add influencer column to user_details table.
 *
 * Run this ONCE to add the influencer flag for Franchisee Distributor users.
 */

require_once(__DIR__ . '/../app/config/database.php');

echo '<h2>Add influencer Column to user_details</h2>';

$check_col = mysqli_query($connect, "SHOW COLUMNS FROM user_details LIKE 'influencer'");
$col_exists = $check_col && mysqli_num_rows($check_col) > 0;

if (!$col_exists) {
    $alter_sql = "ALTER TABLE user_details
                 ADD COLUMN influencer ENUM('YES','NO') NOT NULL DEFAULT 'NO'
                 AFTER saleskit_enabled";

    $result = mysqli_query($connect, $alter_sql);

    if ($result) {
        echo '<div class="success">Column influencer added to user_details table.</div>';
    } else {
        echo '<div class="danger">Error adding column: ' . htmlspecialchars(mysqli_error($connect)) . '</div>';
        exit;
    }
} else {
    echo '<div class="info">Column influencer already exists. Skipping creation.</div>';
}

echo '<div class="success"><strong>Done. influencer column is now available in user_details.</strong></div>';

?>

<style>
.success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 5px 0; }
.danger  { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 5px 0; }
.info    { color: #004085; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; margin: 5px 0; }
</style>
