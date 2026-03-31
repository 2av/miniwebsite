<?php
/**
 * Centralized Database Configuration
 * Single source of truth for all database connections
 */

// Set default timezone
date_default_timezone_set("Asia/Kolkata");

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Configure session parameters
    $session_lifetime = 86400; // 24 hours in seconds
    $secure = isset($_SERVER['HTTPS']); // Set to true if using HTTPS
    $httponly = true; // Prevents JavaScript access to session cookie

    // Set the session cookie parameters
    session_set_cookie_params([
        'lifetime' => $session_lifetime,
        'path' => '/',
        'domain' => '',  // Current domain
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => 'Lax'  // Allows session to persist across same-site requests
    ]);
    
    session_start();
}


    // Production database connection
    $db_host = "p004.bom1.mysecurecloudhost.com";
    $db_user = "wwwmoody_akhilesh";
    $db_pass = "akhilesh@admin";
    $db_name = "miniwebsite_live";
    
    $connect = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($connect->connect_error) {
        die("Database connection failed: " . $connect->connect_error);
    }


// Current date/time
$date = date('Y-m-d H:i:s');

// Helper function to get database connection (for PDO if needed in future)
function get_db_connection() {
    global $connect;
    return $connect;
}

/**
 * Stores last previous MiniWebsite URL slug per card (avoids ALTER on wide digi_card row).
 */
function mw_ensure_digi_card_previous_slug_table() {
    global $connect;
    static $done = false;
    if ($done || !($connect instanceof mysqli) || $connect->connect_error) {
        return;
    }
    $done = true;
    $sql = 'CREATE TABLE IF NOT EXISTS digi_card_previous_slug (
  digi_card_id INT UNSIGNED NOT NULL PRIMARY KEY,
  previous_slug VARCHAR(255) NOT NULL,
  UNIQUE KEY uk_previous_slug (previous_slug(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    @$connect->query($sql);
}

mw_ensure_digi_card_previous_slug_table();

?>
