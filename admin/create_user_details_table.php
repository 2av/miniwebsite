<?php
/**
 * Create Unified User Table: user_details
 *
 * This script creates a single users table that can store:
 * - Customers           (role = 'CUSTOMER')
 * - Franchisees         (role = 'FRANCHISEE')
 * - Team Members        (role = 'TEAM')
 * - Admin Users         (role = 'ADMIN')
 *
 * Run this ONCE before running the user migration script.
 */

require_once(__DIR__ . '/../app/config/database.php');

echo '<h2>Create Unified Users Table (user_details)</h2>';

$sql = "CREATE TABLE IF NOT EXISTS user_details (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Role for this account
    role ENUM('CUSTOMER','FRANCHISEE','TEAM','ADMIN') NOT NULL,

    -- Identity / contact
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(25) DEFAULT NULL,
    name VARCHAR(150) NOT NULL,

    -- Authentication
    password VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) DEFAULT NULL,

    -- Meta
    ip VARCHAR(100) DEFAULT '',
    status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Customer‑specific
    user_token VARCHAR(200) DEFAULT '',
    refund_status VARCHAR(20) DEFAULT 'None',
    refund_status_date DATETIME NULL,
    mw_referral_id INT UNSIGNED DEFAULT NULL,
    collaboration_enabled ENUM('YES','NO') NOT NULL DEFAULT 'NO',
    saleskit_enabled ENUM('YES','NO') NOT NULL DEFAULT 'NO',
    referred_by VARCHAR(255) DEFAULT '',

    -- Referrer / parent (e.g. franchisee, admin, etc.)
    sender_user_id INT UNSIGNED NULL,

    -- Services / wallet
    select_service VARCHAR(200) DEFAULT '',
    wallet_balance VARCHAR(200) DEFAULT '',

    -- Image + payment handles (used by franchisee/admin)
    image LONGBLOB,
    google_pay VARCHAR(200) DEFAULT '',
    paytm VARCHAR(200) DEFAULT '',
    rz_pay VARCHAR(300) DEFAULT '',
    rz_pay2 VARCHAR(300) DEFAULT '',

    -- Legacy identifiers to keep links during transition
    legacy_customer_id INT DEFAULT NULL,
    legacy_franchisee_id INT DEFAULT NULL,
    legacy_team_id INT DEFAULT NULL,
    legacy_admin_id INT DEFAULT NULL,

    UNIQUE KEY uniq_email_role (email, role),
    KEY idx_role (role),
    KEY idx_email (email),
    KEY idx_phone (phone),
    KEY idx_sender_user_id (sender_user_id),

    CONSTRAINT fk_user_details_sender
        FOREIGN KEY (sender_user_id) REFERENCES user_details(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$result = mysqli_query($connect, $sql);

if ($result) {
    echo '<div class="success">✓ user_details table created / already exists.</div>';
} else {
    echo '<div class="danger">✗ Error creating user_details table: ' . htmlspecialchars(mysqli_error($connect)) . '</div>';
}

?>

<style>
.success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 5px 0; }
.danger  { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 5px 0; }
</style>





