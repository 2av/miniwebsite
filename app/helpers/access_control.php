<?php
/**
 * Access Control Helper
 * Prevents unauthorized access to pages by redirecting to dashboard
 */

// Include required helpers (database and role_helper should already be included, but include them to be safe)
if (!function_exists('get_current_user_role')) {
    require_once(__DIR__ . '/role_helper.php');
}
require_once(__DIR__ . '/menu_helper.php');

/**
 * Get base path for redirects
 */
function get_redirect_base_path() {
    $script_name = $_SERVER['SCRIPT_NAME'];
    $script_dir = dirname($script_name);
    // Remove /user and everything after it
    $base = preg_replace('#/user(/.*)?$#', '', $script_dir);
    // Normalize: if it's just '/', return empty string; otherwise return as is
    if ($base === '/' || $base === '') {
        return '';
    }
    return $base;
}

/**
 * Check if user has access to a specific page/URL
 */
function has_page_access($page_url, $current_role, $user_conditions = []) {
    // Extract page identifier from URL (e.g., /dashboard -> dashboard, /kit -> kit)
    $page_id = trim($page_url, '/');
    if (strpos($page_id, '/') !== false) {
        $parts = explode('/', $page_id);
        $page_id = end($parts);
    }
    
    // Remove .php extension if present
    $page_id = str_replace('.php', '', $page_id);
    
    // Load menu config
    $menu_items = load_menu_config();
    
    // Find ALL matching menu items (some pages like /kit can have multiple menu items)
    $matching_items = [];
    foreach ($menu_items as $item) {
        $item_url = trim($item['url'], '/');
        $item_id = str_replace('.php', '', $item_url);
        
        // Check if this menu item matches the requested page
        if ($item_id === $page_id || $item_url === $page_url || $page_url === $item['url']) {
            $matching_items[] = $item;
        }
    }
    
    // If we found matching items, check if ANY of them are visible
    // This handles cases where multiple menu items point to the same URL
    // (e.g., "Sales Kit" and "Marketing Kit" both point to /kit)
    if (!empty($matching_items)) {
        foreach ($matching_items as $item) {
            if (is_menu_visible($item, $current_role, $user_conditions)) {
                return true; // At least one matching menu item is visible
            }
        }
        return false; // None of the matching items are visible
    }
    
    // If page not found in menu config, check if it's a common page
    // Dashboard is always accessible
    if ($page_id === 'dashboard') {
        return true;
    }
    
    // Default: deny access if not explicitly allowed
    return false;
}

/**
 * Require page access - redirects to dashboard if access denied
 */
function require_page_access($page_url = null) {
    // Get current role
    $current_role = get_current_user_role();
    if (!$current_role) {
        // Not logged in, redirect to login
        $base_path = get_redirect_base_path();
        header('Location: ' . $base_path . '/login/customer.php');
        exit;
    }
    
    // If no page URL provided, get from current request
    if ($page_url === null) {
        $page_url = $_SERVER['REQUEST_URI'];
        // Remove query string
        $page_url = strtok($page_url, '?');
        // Remove base path
        $base_path = get_redirect_base_path();
        if ($base_path && strpos($page_url, $base_path) === 0) {
            $page_url = substr($page_url, strlen($base_path));
        }
    }
    
    // Get user conditions - read from session (set during login)
    $user_conditions = [];
    if ($current_role == 'CUSTOMER') {
        // Read from session instead of querying database
        $user_conditions['collaboration_enabled'] = isset($_SESSION['collaboration_enabled']) ? (bool)$_SESSION['collaboration_enabled'] : false;
        $user_conditions['saleskit_enabled'] = isset($_SESSION['saleskit_enabled']) ? (bool)$_SESSION['saleskit_enabled'] : false;
    } elseif ($current_role == 'FRANCHISEE') {
        require_once(__DIR__ . '/verification_helper.php');
        $user_email = get_user_email();
        $user_conditions['is_verified'] = isFranchiseeVerified($user_email);
    }
    
    // Check access
    if (!has_page_access($page_url, $current_role, $user_conditions)) {
        // Access denied - redirect to dashboard
        $base_path = get_redirect_base_path();
        header('Location: ' . $base_path . '/user/dashboard');
        exit;
    }
}

?>
