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

// Database connection based on environment
if ($_SERVER['HTTP_HOST'] == "test.miniwebsite.in") {
    $connect = mysqli_connect("localhost", "wwwmoody_miniweb_vcard_test", "8s5@1lX8u", "miniweb_vcard_test") 
        or die('Database not available...');
} elseif ($_SERVER['HTTP_HOST'] == "localhost") {
    $db_host = "p004.bom1.mysecurecloudhost.com";
    $db_user = "wwwmoody_miniweb_vcard";
    $db_pass = "miniweb_vcard";
    $db_name = "miniweb_vcard";
    
    $connect = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($connect->connect_error) {
        die("Database connection failed: " . $connect->connect_error);
    }
} else {
    // Production database connection
    $db_host = "p004.bom1.mysecurecloudhost.com";
    $db_user = "wwwmoody_miniweb_vcard";
    $db_pass = "miniweb_vcard";
    $db_name = "miniweb_vcard";
    
    $connect = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($connect->connect_error) {
        die("Database connection failed: " . $connect->connect_error);
    }
}

// Current date/time
$date = date('Y-m-d H:i:s');

// Helper function to get database connection (for PDO if needed in future)
function get_db_connection() {
    global $connect;
    return $connect;
}

?>
