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
 * Website builder sidebar items from user/menu_config.json → website_menu_items.
 */
function load_website_menu_config() {
    $config_file = __DIR__ . '/../../user/menu_config.json';

    if (!file_exists($config_file)) {
        return [];
    }

    $json = file_get_contents($config_file);
    $config = json_decode($json, true);

    return is_array($config['website_menu_items'] ?? null) ? $config['website_menu_items'] : [];
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
    
    // Role access settings gate (from admin/manage_role_access_settings.php matrix)
    if (!empty($menu_item['role_access_feature'])) {
        if (!function_exists('role_access_tables_exist')) {
            require_once __DIR__ . '/role_access_helper.php';
        }
        global $connect;
        if (!isset($connect) || !is_object($connect)) {
            require_once __DIR__ . '/../config/database.php';
        }
        $profile_key = $user_conditions['role_access_profile_key'] ?? null;
        if (!$profile_key || !role_access_tables_exist($connect)) {
            if (is_menu_debug_enabled()) {
                error_log("=== MENU DEBUG: " . $menu_id . " - HIDDEN (no role access profile)");
            }
            return false;
        }
        $field_type = $menu_item['role_access_type'] ?? 'text';
        if (!function_exists('get_user_email')) {
            require_once __DIR__ . '/role_helper.php';
        }
        $user_email = get_user_email() ?? '';
        if (!is_role_access_feature_visible_for_user($connect, $profile_key, $menu_item['role_access_feature'], $field_type, $user_email, $current_role)) {
            if (is_menu_debug_enabled()) {
                error_log("=== MENU DEBUG: " . $menu_id . " - HIDDEN (role access feature " . $menu_item['role_access_feature'] . ")");
            }
            return false;
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

/**
 * Theme tokens from user/menu_config.json (sidebar nav active color, etc.).
 */
function get_menu_theme() {
    static $theme = null;
    if ($theme !== null) {
        return $theme;
    }

    $defaults = [
        'nav_icon_active' => '#2b4ba9',
    ];

    $config_file = __DIR__ . '/../../user/menu_config.json';
    if (!file_exists($config_file)) {
        $theme = $defaults;
        return $theme;
    }

    $json = json_decode(file_get_contents($config_file), true);
    $theme = array_merge($defaults, is_array($json['theme'] ?? null) ? $json['theme'] : []);

    return $theme;
}

/**
 * Validated hex color for active sidebar menu icons (from menu_config theme).
 */
function get_menu_nav_active_color() {
    $color = get_menu_theme()['nav_icon_active'] ?? '#2b4ba9';
    if (!is_string($color) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        return '#2b4ba9';
    }
    return $color;
}

/**
 * Render a sidebar nav icon (Font Awesome 4.x class names).
 * Accepts bare icon names ("tachometer") or prefixed ("fa-tachometer").
 * Legacy image filenames (*.png, etc.) still render as <img> for backward compatibility.
 */
function render_nav_icon($icon, $assets_base = '') {
    $icon = trim((string) $icon);
    if ($icon === '') {
        return '<i class="fa fa-circle-o mw-nav-icon" aria-hidden="true"></i>';
    }

    if (preg_match('/\.(png|jpe?g|gif|webp|svg)$/i', $icon)) {
        $src = htmlspecialchars(rtrim((string) $assets_base, '/') . '/assets/images/' . $icon, ENT_QUOTES, 'UTF-8');
        return '<img src="' . $src . '" class="img-fluid h-5 w-5 object-contain" alt="" srcset="">';
    }

    $fa = preg_replace('/^fa-/', '', $icon);
    // FA4 names that break when FA7 is loaded without v4 shims (see header FA v4-font-face).
    $fa_aliases = [
        'pencil-square-o' => 'edit',
        'picture-o'       => 'image',
        'clock-o'         => 'clock',
        'money'           => 'credit-card',
    ];
    if (isset($fa_aliases[$fa])) {
        $fa = $fa_aliases[$fa];
    }
    $fa = htmlspecialchars($fa, ENT_QUOTES, 'UTF-8');

    return '<i class="fa fa-' . $fa . ' mw-nav-icon" aria-hidden="true"></i>';
}

?>
