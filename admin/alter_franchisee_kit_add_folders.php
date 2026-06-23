<?php
/**
 * Add folder support for franchisee kit items.
 * Run once: /admin/alter_franchisee_kit_add_folders.php
 */

require_once __DIR__ . '/../app/config/database.php';

echo '<h2>Franchisee Kit — Folder Support Migration</h2>';

$table_check = mysqli_query($connect, "SHOW TABLES LIKE 'franchisee_kit_folders'");
if ($table_check && mysqli_num_rows($table_check) === 0) {
    $create_sql = "CREATE TABLE franchisee_kit_folders (
        id INT(11) NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        category VARCHAR(50) NOT NULL DEFAULT 'sales',
        parent_id INT(11) NULL DEFAULT NULL,
        display_order INT(11) NOT NULL DEFAULT 0,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_category (category),
        KEY idx_status (status),
        KEY idx_display_order (display_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (mysqli_query($connect, $create_sql)) {
        echo '<p style="color:green;">✓ Created franchisee_kit_folders table.</p>';
    } else {
        echo '<p style="color:red;">✗ Error creating table: ' . htmlspecialchars(mysqli_error($connect)) . '</p>';
        exit;
    }
} else {
    echo '<p style="color:#666;">franchisee_kit_folders table already exists.</p>';
}

$col_check = mysqli_query($connect, "SHOW COLUMNS FROM franchisee_kit LIKE 'folder_id'");
if ($col_check && mysqli_num_rows($col_check) === 0) {
    $alter_sql = "ALTER TABLE franchisee_kit ADD COLUMN folder_id INT(11) NULL DEFAULT NULL AFTER category";
    if (mysqli_query($connect, $alter_sql)) {
        echo '<p style="color:green;">✓ Added folder_id column to franchisee_kit.</p>';
    } else {
        echo '<p style="color:red;">✗ Error adding folder_id: ' . htmlspecialchars(mysqli_error($connect)) . '</p>';
        exit;
    }
} else {
    echo '<p style="color:#666;">folder_id column already exists on franchisee_kit.</p>';
}

$parent_col_check = mysqli_query($connect, "SHOW COLUMNS FROM franchisee_kit_folders LIKE 'parent_id'");
if ($parent_col_check && mysqli_num_rows($parent_col_check) === 0) {
    $alter_parent_sql = "ALTER TABLE franchisee_kit_folders ADD COLUMN parent_id INT(11) NULL DEFAULT NULL AFTER category";
    if (mysqli_query($connect, $alter_parent_sql)) {
        echo '<p style="color:green;">✓ Added parent_id column for subfolders.</p>';
    } else {
        echo '<p style="color:red;">✗ Error adding parent_id: ' . htmlspecialchars(mysqli_error($connect)) . '</p>';
        exit;
    }
} else {
    echo '<p style="color:#666;">parent_id column already exists on franchisee_kit_folders.</p>';
}

echo '<p><strong>Migration complete.</strong> <a href="kit_management.php">Go to Kit Management</a></p>';
