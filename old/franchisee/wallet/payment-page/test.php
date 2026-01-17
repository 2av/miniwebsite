<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Payment Page Test</h2>";

// Test database connection
echo "<h3>Testing Database Connection:</h3>";
try {
    require('../../../connect.php');
    echo "<p style='color: green;'>✅ Database connection successful</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

// Test session
echo "<h3>Testing Session:</h3>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "<p style='color: green;'>✅ Session started</p>";

// Test POST data
echo "<h3>POST Data:</h3>";
if ($_POST) {
    echo "<pre>" . print_r($_POST, true) . "</pre>";
} else {
    echo "<p>No POST data received</p>";
}

// Test session data
echo "<h3>Session Data:</h3>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

// Test file paths
echo "<h3>File Paths:</h3>";
echo "<p>Current file: " . __FILE__ . "</p>";
echo "<p>Config file exists: " . (file_exists('../../../common/config.php') ? 'Yes' : 'No') . "</p>";
echo "<p>Payment page exists: " . (file_exists('../../payment_page/pay.php') ? 'Yes' : 'No') . "</p>";
?>
