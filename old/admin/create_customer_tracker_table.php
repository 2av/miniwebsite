<?php
/**
 * Customer Tracker Table Creation Script
 * Creates the customer_tracker table for tracking customer visits by team members
 */

require('connect.php');

// First, ensure team_members table exists
$ensureTeamTable = "CREATE TABLE IF NOT EXISTS team_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_name VARCHAR(150) NOT NULL,
    member_email VARCHAR(255) NOT NULL,
    member_phone VARCHAR(25) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    last_login_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_member_email (member_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

mysqli_query($connect, $ensureTeamTable);

// Create customer_tracker table with matching data type
$createTableSql = "CREATE TABLE IF NOT EXISTS customer_tracker (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    team_member_id INT UNSIGNED NOT NULL,
    shop_name VARCHAR(255) NOT NULL DEFAULT '',
    contact_number VARCHAR(50) DEFAULT '',
    address TEXT,
    date_visited DATE NOT NULL,
    final_status ENUM('Joined', 'Not Interested', 'Followup required') NOT NULL DEFAULT 'Followup required',
    comments TEXT,
    created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_team_member_id (team_member_id),
    INDEX idx_date_visited (date_visited),
    INDEX idx_final_status (final_status),
    FOREIGN KEY (team_member_id) REFERENCES team_members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$result = mysqli_query($connect, $createTableSql);

if($result) {
    echo '<div style="padding: 20px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px;">';
    echo '✓ customer_tracker table created successfully';
    echo '</div>';
} else {
    echo '<div style="padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px;">';
    echo '✗ Error creating customer_tracker table: ' . mysqli_error($connect);
    echo '</div>';
}

// Create customer_tracker_history table for tracking changes
$createHistoryTableSql = "CREATE TABLE IF NOT EXISTS customer_tracker_history (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    tracker_id INT(11) NOT NULL,
    team_member_id INT UNSIGNED NOT NULL,
    changed_field VARCHAR(50) DEFAULT NULL,
    old_value TEXT,
    new_value TEXT,
    change_type ENUM('status_change', 'comment_change', 'other_change') NOT NULL DEFAULT 'other_change',
    changed_by INT UNSIGNED NOT NULL,
    changed_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tracker_id (tracker_id),
    INDEX idx_team_member_id (team_member_id),
    INDEX idx_changed_at (changed_at),
    FOREIGN KEY (tracker_id) REFERENCES customer_tracker(id) ON DELETE CASCADE,
    FOREIGN KEY (team_member_id) REFERENCES team_members(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES team_members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$historyResult = mysqli_query($connect, $createHistoryTableSql);

if($historyResult) {
    echo '<div style="padding: 20px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px;">';
    echo '✓ customer_tracker_history table created successfully';
    echo '</div>';
} else {
    echo '<div style="padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px;">';
    echo '✗ Error creating customer_tracker_history table: ' . mysqli_error($connect);
    echo '</div>';
}

mysqli_close($connect);
?>

