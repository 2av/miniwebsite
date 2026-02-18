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

?>
