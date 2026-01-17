<?php
/**
 * Migration Script: Copy users from old tables into user_details
 *
 * Prerequisite:
 *   1. Run create_user_details_table.php to create the user_details table.
 *
 * What this does:
 *   - Copies:
 *       customer_login     → user_details (role = 'CUSTOMER')
 *       franchisee_login   → user_details (role = 'FRANCHISEE')
 *       admin_login        → user_details (role = 'ADMIN')
 *       team_members       → user_details (role = 'TEAM')
 *   - Fills legacy_*_id columns so old IDs can still be referenced.
 *   - Links customer sender_token (franchisee id) to sender_user_id in user_details.
 *
 * NOTE:
 *   This script is idempotent only if you TRUNCATE user_details first
 *   (or manually clear migrated rows) before re-running.
 */

require('connect.php');

echo '<h2>Migration: Old Login Tables → user_details</h2>';

// Wrap everything in a transaction for safety where possible
mysqli_begin_transaction($connect);

function mig_info($msg) { echo '<div class="info">'.htmlspecialchars($msg).'</div>'; }
function mig_ok($msg)   { echo '<div class="success">'.htmlspecialchars($msg).'</div>'; }
function mig_err($msg)  { echo '<div class="danger">'.htmlspecialchars($msg).'</div>'; }

// Disable foreign key checks during migration
mysqli_query($connect, "SET FOREIGN_KEY_CHECKS = 0");

// Optional: clear existing data (comment out if you want to append instead)
//$truncate = mysqli_query($connect, "TRUNCATE TABLE user_details");
//if ($truncate) {
//    mig_ok('Cleared existing data from user_details.');
//} else {
//    mig_err('Failed to truncate user_details: '.mysqli_error($connect));
//}

// Helper to run INSERT ... SELECT and show result
function run_migration_query($connect, $label, $sql) {
    mig_info("Migrating: $label");
    $res = mysqli_query($connect, $sql);
    if ($res) {
        $affected = mysqli_affected_rows($connect);
        mig_ok("$label: inserted $affected rows.");
        return true;
    } else {
        mig_err("$label: error - " . mysqli_error($connect));
        return false;
    }
}

// 1) Customers
$sql_customers = "
INSERT IGNORE INTO user_details (
    role, email, phone, name, password, ip, status,
    user_token, refund_status, refund_status_date,
    select_service, collaboration_enabled, referred_by, created_at,
    legacy_customer_id
)
SELECT
    'CUSTOMER' AS role,
    user_email AS email,
    user_contact AS phone,
    user_name AS name,
    user_password AS password,
    ip,
    CASE 
        WHEN user_active = '1' OR UPPER(user_active) = 'ACTIVE' THEN 'ACTIVE'
        ELSE 'INACTIVE'
    END AS status,
    user_token,
    refund_status,
    refund_status_date,
    select_service,
    COALESCE(collaboration_enabled, 'NO') AS collaboration_enabled,
    COALESCE(referred_by, '') AS referred_by,
    uploaded_date,
    id AS legacy_customer_id
FROM customer_login
WHERE user_email <> ''";

$ok1 = run_migration_query($connect, 'Customers (customer_login → user_details)', $sql_customers);

// 2) Franchisees
$sql_franchisees = "
INSERT IGNORE INTO user_details (
    role, email, phone, name, password, ip, status,
    image, google_pay, paytm, rz_pay, rz_pay2,
    select_service, wallet_balance, created_at,
    legacy_franchisee_id
)
SELECT
    'FRANCHISEE' AS role,
    f_user_email AS email,
    f_user_contact AS phone,
    f_user_name AS name,
    f_user_password AS password,
    ip,
    CASE 
        WHEN f_user_active = '1' OR UPPER(f_user_active) = 'ACTIVE' THEN 'ACTIVE'
        ELSE 'INACTIVE'
    END AS status,
    f_user_image AS image,
    f_user_google_pay AS google_pay,
    f_user_paytm AS paytm,
    f_user_rz_pay AS rz_pay,
    f_user_rz_pay2 AS rz_pay2,
    f_select_service AS select_service,
    f_wallet_balance AS wallet_balance,
    uploaded_date AS created_at,
    id AS legacy_franchisee_id
FROM franchisee_login
WHERE f_user_email <> ''";

$ok2 = run_migration_query($connect, 'Franchisees (franchisee_login → user_details)', $sql_franchisees);

// 3) Admins
$sql_admins = "
INSERT IGNORE INTO user_details (
    role, email, phone, name, password, ip, status,
    image, google_pay, paytm, rz_pay, rz_pay2,
    created_at,
    legacy_admin_id
)
SELECT
    'ADMIN' AS role,
    admin_email AS email,
    admin_contact AS phone,
    admin_name AS name,
    admin_password AS password,
    ip,
    'ACTIVE' AS status,
    admin_image AS image,
    admin_google_pay AS google_pay,
    admin_paytm AS paytm,
    admin_rz_pay AS rz_pay,
    admin_rz_pay2 AS rz_pay2,
    uploaded_date AS created_at,
    id AS legacy_admin_id
FROM admin_login
WHERE admin_email <> ''";

$ok3 = run_migration_query($connect, 'Admins (admin_login → user_details)', $sql_admins);

// 4) Team Members
$sql_team = "
INSERT IGNORE INTO user_details (
    role, email, phone, name,
    password, password_hash, ip, status,
    created_at, updated_at,
    legacy_team_id
)
SELECT
    'TEAM' AS role,
    member_email AS email,
    member_phone AS phone,
    member_name AS name,
    '' AS password,
    password_hash,
    '' AS ip,
    status,
    created_at,
    updated_at,
    id AS legacy_team_id
FROM team_members
WHERE member_email <> ''";

$ok4 = run_migration_query($connect, 'Team Members (team_members → user_details)', $sql_team);

// 5) Link sender_token → sender_user_id
// customer_login.sender_token currently stores franchisee_login.id (or similar).
// After migrating franchisees, we set sender_user_id to the corresponding user_details.id.
$sql_link_sender = "
UPDATE user_details c
JOIN customer_login cl ON cl.id = c.legacy_customer_id
LEFT JOIN user_details f
    ON f.role = 'FRANCHISEE'
   AND f.legacy_franchisee_id = cl.sender_token
SET c.sender_user_id = f.id
WHERE c.role = 'CUSTOMER'
  AND cl.sender_token IS NOT NULL
  AND cl.sender_token <> ''";

mig_info('Linking customer sender_token → sender_user_id...');
$res_link = mysqli_query($connect, $sql_link_sender);
if ($res_link) {
    $affected = mysqli_affected_rows($connect);
    mig_ok("Updated sender_user_id for $affected customers.");
} else {
    mig_err('Error linking sender_user_id: ' . mysqli_error($connect));
}

// Re-enable foreign key checks
mysqli_query($connect, "SET FOREIGN_KEY_CHECKS = 1");

if ($ok1 && $ok2 && $ok3 && $ok4) {
    mysqli_commit($connect);
    mig_ok('All user migrations completed successfully and transaction committed.');
} else {
    mysqli_rollback($connect);
    mig_err('One or more migrations failed. Transaction rolled back. Please fix errors and re-run.');
}

?>

<style>
.success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 5px 0; }
.danger  { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 5px 0; }
.info    { padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; margin: 5px 0; }
</style>


