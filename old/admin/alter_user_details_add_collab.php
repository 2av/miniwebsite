<?php
/**
 * One‑time patch: ensure collaboration fields exist on user_details
 * and backfill them from customer_login for CUSTOMER records.
 *
 * Run this ONCE after create_user_details_table.php and migrate_users_to_user_details.php
 * if you see errors like:
 *   "Unknown column 'u.collaboration_enabled' in 'SELECT'"
 */

require('connect.php');

echo '<h2>Patch: Add collaboration columns to user_details</h2>';

function msg($cls, $text) {
    echo '<div class="'.$cls.'">'.htmlspecialchars($text).'</div>';
}

// Check if column exists helper
function column_exists($connect, $table, $column) {
    $res = mysqli_query($connect, "SHOW COLUMNS FROM `$table` LIKE '".mysqli_real_escape_string($connect, $column)."'");
    return $res && mysqli_num_rows($res) > 0;
}

$table = 'user_details';
$need_alter = false;

$has_collab = column_exists($connect, $table, 'collaboration_enabled');
$has_referred_by = column_exists($connect, $table, 'referred_by');

if ($has_collab && $has_referred_by) {
    msg('info', 'Columns collaboration_enabled and referred_by already exist on user_details.');
} else {
    $alterParts = [];
    if (!$has_collab) {
        $alterParts[] = "ADD COLUMN collaboration_enabled ENUM('YES','NO') NOT NULL DEFAULT 'NO' AFTER mw_referral_id";
    }
    if (!$has_referred_by) {
        $alterParts[] = "ADD COLUMN referred_by VARCHAR(255) DEFAULT '' AFTER collaboration_enabled";
    }

    if (!empty($alterParts)) {
        $alterSql = "ALTER TABLE `$table` ".implode(', ', $alterParts);
        if (mysqli_query($connect, $alterSql)) {
            msg('success', 'Added missing collaboration columns to user_details.');
            $has_collab = true;
            $has_referred_by = true;
        } else {
            msg('danger', 'Error altering user_details: '.mysqli_error($connect));
        }
    }
}

// Backfill data from customer_login for CUSTOMER rows
if ($has_collab && $has_referred_by) {
    $updateSql = "
        UPDATE user_details u
        JOIN customer_login cl ON u.legacy_customer_id = cl.id
        SET 
            u.collaboration_enabled = COALESCE(cl.collaboration_enabled, 'NO'),
            u.referred_by = COALESCE(cl.referred_by, '')
        WHERE u.role = 'CUSTOMER'
    ";

    if (mysqli_query($connect, $updateSql)) {
        $affected = mysqli_affected_rows($connect);
        msg('success', "Backfilled collaboration_enabled and referred_by for $affected customer records.");
    } else {
        msg('danger', 'Error backfilling data: '.mysqli_error($connect));
    }
}

?>

<style>
.success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 5px 0; }
.danger  { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 5px 0; }
.info    { padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; margin: 5px 0; }
</style>


