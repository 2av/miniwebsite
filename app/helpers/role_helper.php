<?php
/**
 * Role Helper Functions
 * Centralized role detection and access control
 */

// Include database connection
require_once(__DIR__ . '/../config/database.php');

/**
 * Get current user role from session
 * Returns: 'CUSTOMER', 'FRANCHISEE', 'TEAM', 'ADMIN', or null
 */
function get_current_user_role() {
    // Check for admin
    if (isset($_SESSION['admin_email']) && isset($_SESSION['admin_is_logged_in']) && $_SESSION['admin_is_logged_in'] === true) {
        return 'ADMIN';
    }
    
    // Check for franchisee
    if (isset($_SESSION['f_user_email']) && isset($_SESSION['f_is_logged_in']) && $_SESSION['f_is_logged_in'] === true) {
        return 'FRANCHISEE';
    }
    
    // Check for team
    if (isset($_SESSION['t_user_email']) && isset($_SESSION['t_is_logged_in']) && $_SESSION['t_is_logged_in'] === true) {
        return 'TEAM';
    }
    
    // Check for customer
    if (isset($_SESSION['user_email']) && isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
        return 'CUSTOMER';
    }
    
    return null;
}

/**
 * Check if user is logged in (any role)
 */
function is_user_logged_in() {
    return get_current_user_role() !== null;
}

/**
 * Check if user has specific role
 */
function has_role($role) {
    return get_current_user_role() === strtoupper($role);
}

/**
 * Require specific role, redirect if not
 */
function require_role($required_role, $redirect_url = '/panel/login/login.php') {
    $current_role = get_current_user_role();
    if ($current_role !== strtoupper($required_role)) {
        header('Location: ' . $redirect_url);
        exit;
    }
}

/**
 * Get user email based on role
 */
function get_user_email() {
    $role = get_current_user_role();
    switch ($role) {
        case 'ADMIN':
            return $_SESSION['admin_email'] ?? null;
        case 'FRANCHISEE':
            return $_SESSION['f_user_email'] ?? null;
        case 'TEAM':
            return $_SESSION['t_user_email'] ?? null;
        case 'CUSTOMER':
            return $_SESSION['user_email'] ?? null;
        default:
            return null;
    }
}

/**
 * Get user ID based on role
 */
function get_user_id() {
    $role = get_current_user_role();
    switch ($role) {
        case 'ADMIN':
            return $_SESSION['admin_id'] ?? null;
        case 'FRANCHISEE':
            return $_SESSION['f_user_id'] ?? null;
        case 'TEAM':
            return $_SESSION['t_user_id'] ?? null;
        case 'CUSTOMER':
            return $_SESSION['user_id'] ?? null;
        default:
            return null;
    }
}

/**
 * Get user name based on role
 */
function get_user_name() {
    $role = get_current_user_role();
    switch ($role) {
        case 'ADMIN':
            return $_SESSION['admin_name'] ?? null;
        case 'FRANCHISEE':
            return $_SESSION['f_user_name'] ?? null;
        case 'TEAM':
            return $_SESSION['t_user_name'] ?? null;
        case 'CUSTOMER':
            return $_SESSION['user_name'] ?? null;
        default:
            return null;
    }
}

/**
 * Check if user has collaboration enabled (for customers)
 */
function has_collaboration_enabled() {
    if (!has_role('CUSTOMER')) {
        return false;
    }
    
    $user_email = get_user_email();
    if (!$user_email) {
        return false;
    }
    
    global $connect;
    $query = mysqli_query($connect, "SELECT collaboration_enabled FROM user_details WHERE email='$user_email' AND role='CUSTOMER' LIMIT 1");
    if ($query && $row = mysqli_fetch_array($query)) {
        return ($row['collaboration_enabled'] == 'YES');
    }
    
    return false;
}

/**
 * Check if user has saleskit enabled (for customers)
 */
function has_saleskit_enabled() {
    if (!has_role('CUSTOMER')) {
        return false;
    }
    
    $user_email = get_user_email();
    if (!$user_email) {
        return false;
    }
    
    global $connect;
    $query = mysqli_query($connect, "SELECT saleskit_enabled FROM user_details WHERE email='$user_email' AND role='CUSTOMER' LIMIT 1");
    if ($query && $row = mysqli_fetch_array($query)) {
        return ($row['saleskit_enabled'] == 'YES');
    }
    
    return false;
}

/**
 * Require login, redirect if not logged in
 */
function require_login($redirect_url = '/panel/login/login.php') {
    if (!is_user_logged_in()) {
        header('Location: ' . $redirect_url);
        exit;
    }
}

?>
