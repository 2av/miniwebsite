<?php
/**
 * Menu Helper Functions
 * Manages menu visibility based on JSON configuration
 */

// Debug mode helper function
function is_menu_debug_enabled() {
    return isset($_GET['menu_debug']) || (isset($_SESSION['menu_debug']) && $_SESSION['menu_debug'] === true);
}

/**
 * Load menu configuration from JSON
 */
function load_menu_config() {
    $config_file = __DIR__ . '/../../user/menu_config.json';
    
    if (is_menu_debug_enabled()) {
        error_log("=== MENU DEBUG: Loading config from: " . $config_file);
        error_log("=== MENU DEBUG: File exists: " . (file_exists($config_file) ? 'YES' : 'NO'));
    }
    
    if (!file_exists($config_file)) {
        if (is_menu_debug_enabled()) {
            error_log("=== MENU DEBUG: Config file not found!");
        }
        return [];
    }
    
    $json = file_get_contents($config_file);
    $config = json_decode($json, true);
    
    if (is_menu_debug_enabled()) {
        error_log("=== MENU DEBUG: JSON decode error: " . json_last_error_msg());
        error_log("=== MENU DEBUG: Total menu items loaded: " . count($config['menu_items'] ?? []));
    }
    
    return $config['menu_items'] ?? [];
}

/**
 * Check if a menu item should be visible for the current user
 */
function is_menu_visible($menu_item, $current_role, $user_conditions = []) {
    $menu_id = $menu_item['id'] ?? 'unknown';
    
    if (is_menu_debug_enabled()) {
        error_log("=== MENU DEBUG: Checking visibility for menu item: " . $menu_id);
        error_log("=== MENU DEBUG: Current role: " . $current_role);
        error_log("=== MENU DEBUG: Allowed roles: " . implode(', ', $menu_item['roles'] ?? []));
        error_log("=== MENU DEBUG: User conditions: " . json_encode($user_conditions));
    }
    
    // Check if role is allowed
    if (!in_array($current_role, $menu_item['roles'])) {
        if (is_menu_debug_enabled()) {
            error_log("=== MENU DEBUG: " . $menu_id . " - HIDDEN (role not allowed)");
        }
        return false;
    }
    
    // If always visible, show it
    if (isset($menu_item['always_visible']) && $menu_item['always_visible'] === true) {
        if (is_menu_debug_enabled()) {
            error_log("=== MENU DEBUG: " . $menu_id . " - VISIBLE (always_visible = true)");
        }
        return true;
    }
    
    // Check conditions if they exist
    if (isset($menu_item['conditions'][$current_role])) {
        $conditions = $menu_item['conditions'][$current_role];
        if (is_menu_debug_enabled()) {
            error_log("=== MENU DEBUG: " . $menu_id . " - Has conditions: " . json_encode($conditions));
        }
        
        foreach ($conditions as $key => $value) {
            // Check if condition matches user's actual condition
            if (isset($user_conditions[$key])) {
                if ($user_conditions[$key] !== $value) {
                    if (is_menu_debug_enabled()) {
                        error_log("=== MENU DEBUG: " . $menu_id . " - HIDDEN (condition mismatch: $key = " . ($user_conditions[$key] ? 'true' : 'false') . " but required " . ($value ? 'true' : 'false') . ")");
                    }
                    return false;
                }
            } else {
                if (is_menu_debug_enabled()) {
                    error_log("=== MENU DEBUG: " . $menu_id . " - HIDDEN (condition $key not set in user_conditions)");
                }
                return false;
            }
        }
    }
    
    if (is_menu_debug_enabled()) {
        error_log("=== MENU DEBUG: " . $menu_id . " - VISIBLE (all checks passed)");
    }
    
    return true;
}

/**
 * Get visible menu items for current user
 */
function get_visible_menu_items($current_role, $user_conditions = []) {
    if (is_menu_debug_enabled()) {
        error_log("=== MENU DEBUG: ========================================");
        error_log("=== MENU DEBUG: Getting visible menu items");
        error_log("=== MENU DEBUG: Current role: " . $current_role);
        error_log("=== MENU DEBUG: User conditions: " . json_encode($user_conditions));
    }
    
    $menu_items = load_menu_config();
    $visible_items = [];
    $hidden_items = []; // For debug
    
    foreach ($menu_items as $item) {
        if (is_menu_visible($item, $current_role, $user_conditions)) {
            $visible_items[] = $item;
        } else {
            if (is_menu_debug_enabled()) {
                $hidden_items[] = ($item['id'] ?? 'unknown') . " - HIDDEN";
            }
        }
    }
    
    if (is_menu_debug_enabled()) {
        error_log("=== MENU DEBUG: Total visible items: " . count($visible_items));
        error_log("=== MENU DEBUG: Visible menu IDs: " . implode(', ', array_column($visible_items, 'id')));
        if (!empty($hidden_items)) {
            error_log("=== MENU DEBUG: Hidden items: " . implode(', ', $hidden_items));
        }
        error_log("=== MENU DEBUG: ========================================");
    }
    
    return $visible_items;
}

?>
