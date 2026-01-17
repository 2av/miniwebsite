<?php
// Include database connection and role helper to detect role before destroying session
require_once(__DIR__ . '/../app/config/database.php');
require_once(__DIR__ . '/../app/helpers/role_helper.php');

// Get user role before destroying session
$user_role = get_current_user_role();

// Get base path for redirects (works for both localhost subfolder and production root)
function get_base_path() {
    $script_name = $_SERVER['SCRIPT_NAME'];
    $script_dir = dirname($script_name);
    // Remove /login from path
    $base = preg_replace('#/login(/.*)?$#', '', $script_dir);
    // Normalize: if it's just '/', return empty string; otherwise return as is
    if ($base === '/' || $base === '') {
        return '';
    }
    return $base;
}

$base_path = get_base_path();

// Destroy session
session_destroy();
session_unset();

// Redirect based on role
if ($user_role == 'CUSTOMER') {
    header('Location: ' . $base_path . '/login/customer.php');
} elseif ($user_role == 'FRANCHISEE') {
    header('Location: ' . $base_path . '/login/franchisee.php');
} elseif ($user_role == 'TEAM') {
    header('Location: ' . $base_path . '/login/team.php');
} else {
    // Default to customer login if role not detected
    header('Location: ' . $base_path . '/login/customer.php');
}
exit;
?>
