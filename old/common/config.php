<?php
// common/config.php
$keyId ='rzp_live_xU57a1JhH7To1G';// 'rzp_test_bbItJwu8YucZS7';
$keySecret = 'VHJzQnCxqF5HTNsE3LUTZtnI'; // 'CjDmiACVQleLZYhNC0WBZKqH';
$displayCurrency = 'INR';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set default timezone
date_default_timezone_set("Asia/Kolkata");

// Configure session parameters for better persistence
// Set session cookie to be accessible from all subdomains
// Extended session lifetime for admin - 30 days (2592000 seconds)
// This ensures admin sessions don't expire until manual logout
$session_lifetime = 2592000; // 30 days in seconds (2592000)
$secure = isset($_SERVER['HTTPS']); // Set to true if using HTTPS
$httponly = true; // Prevents JavaScript access to session cookie

// Set PHP session configuration to prevent expiration
ini_set('session.gc_maxlifetime', $session_lifetime); // Server-side session lifetime
ini_set('session.cookie_lifetime', $session_lifetime); // Cookie lifetime
ini_set('session.gc_probability', 1); // Lower probability of garbage collection
ini_set('session.gc_divisor', 1000); // Only run GC 1 in 1000 requests

// Check if session is already active before setting cookie parameters
if (session_status() === PHP_SESSION_NONE) {
    // Set the session cookie parameters
    session_set_cookie_params([
        'lifetime' => $session_lifetime,
        'path' => '/',
        'domain' => '',  // Current domain
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => 'Lax'  // Allows session to persist across same-site requests
    ]);
    
    // Start session
    session_start();

    // For admin users, extend session on each page load to prevent expiration
    if (isset($_SESSION['admin_email'])) {
        // Update session access time to keep it alive
        $_SESSION['last_activity'] = time();
        // Refresh session cookie to extend lifetime
        setcookie(session_name(), session_id(), time() + $session_lifetime, '/', '', $secure, true);
        // Don't regenerate ID for admin to maintain session continuity
    } else {
        // Regenerate session ID periodically for non-admin users to prevent session fixation
        if (!isset($_SESSION['last_regeneration']) ||
            (time() - $_SESSION['last_regeneration']) > 1800) { // 30 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
} else {
    // Session is already active
    // For admin users, extend session on each page load
    if (isset($_SESSION['admin_email'])) {
        // Update session access time to keep it alive
        $_SESSION['last_activity'] = time();
        // Refresh session cookie to extend lifetime
        setcookie(session_name(), session_id(), time() + $session_lifetime, '/', '', $secure, true);
    } else {
        // Regenerate session ID periodically for non-admin users
        if (!isset($_SESSION['last_regeneration']) ||
            (time() - $_SESSION['last_regeneration']) > 1800) { // 30 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
}

// Database configuration
$db_host = "p004.bom1.mysecurecloudhost.com";
$db_user = "wwwmoody_miniweb_vcard";
$db_pass = "miniweb_vcard";
$db_name = "miniweb_vcard";

// Create database connection
$connect = new mysqli($db_host, $db_user, $db_pass, $db_name);
// Check connection
if ($connect->connect_error) {
    die("Database connection failed: " . $connect->connect_error);
}
$date = date('Y-m-d H:i:s');

 
?>
