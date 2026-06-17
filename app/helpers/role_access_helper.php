<?php
/**
 * Role Access Settings Helper
 * Resolves which profile applies to a user and reads feature settings from DB.
 */

function role_access_tables_exist($connect) {
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    $res = mysqli_query($connect, "SHOW TABLES LIKE 'role_access_profiles'");
    $exists = $res && mysqli_num_rows($res) > 0;
    return $exists;
}

/**
 * Map a logged-in user to a role access profile key.
 */
function resolve_role_access_profile_key($role, $collaboration_enabled = false, $influencer = 'NO') {
    $role = strtoupper(trim((string)$role));
    $influencer = strtoupper(trim((string)$influencer));

    if ($role === 'ADMIN') {
        return 'admin';
    }
    if ($role === 'TEAM') {
        return 'fse_team';
    }
    if ($role === 'FRANCHISEE') {
        return 'franchisee';
    }
    if ($role === 'CUSTOMER') {
        if ($collaboration_enabled) {
            return ($influencer === 'YES') ? 'frd_influencer' : 'frd_freelancer';
        }
        return 'mw_user';
    }
    return null;
}

/**
 * Resolve profile key from user_details row or session-like array.
 */
function resolve_role_access_profile_from_user($user = []) {
    $role = $user['role'] ?? '';
    $collab = ($user['collaboration_enabled'] ?? 'NO') === 'YES';
    $influencer = $user['influencer'] ?? 'NO';
    return resolve_role_access_profile_key($role, $collab, $influencer);
}

function get_role_access_profiles($connect, $active_only = true) {
    if (!role_access_tables_exist($connect)) {
        return [];
    }
    $sql = "SELECT * FROM role_access_profiles";
    if ($active_only) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY sort_order ASC, id ASC";

    $rows = [];
    $q = mysqli_query($connect, $sql);
    while ($q && ($r = mysqli_fetch_assoc($q))) {
        $rows[] = $r;
    }
    return $rows;
}

function get_role_access_features($connect, $active_only = true) {
    if (!role_access_tables_exist($connect)) {
        return [];
    }
    $sql = "SELECT * FROM role_access_features";
    if ($active_only) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY sort_order ASC, id ASC";

    $rows = [];
    $q = mysqli_query($connect, $sql);
    while ($q && ($r = mysqli_fetch_assoc($q))) {
        $rows[] = $r;
    }
    return $rows;
}

/**
 * Full settings matrix: [profile_key][feature_key] => ['is_not_applicable'=>bool,'setting_value'=>string,...]
 */
function get_role_access_settings_matrix($connect) {
    if (!role_access_tables_exist($connect)) {
        return [];
    }

    $matrix = [];
    $sql = "SELECT p.profile_key, f.feature_key, s.is_not_applicable, s.setting_value, s.id AS setting_id
            FROM role_access_settings s
            INNER JOIN role_access_profiles p ON p.id = s.profile_id
            INNER JOIN role_access_features f ON f.id = s.feature_id
            ORDER BY p.sort_order, f.sort_order";

    $q = mysqli_query($connect, $sql);
    while ($q && ($r = mysqli_fetch_assoc($q))) {
        $matrix[$r['profile_key']][$r['feature_key']] = [
            'setting_id' => (int)$r['setting_id'],
            'is_not_applicable' => (bool)$r['is_not_applicable'],
            'setting_value' => $r['setting_value'],
        ];
    }
    return $matrix;
}

/**
 * Settings for one profile keyed by feature_key.
 */
function get_role_access_settings_for_profile($connect, $profile_key) {
    if (!role_access_tables_exist($connect) || !$profile_key) {
        return [];
    }

    $profile_key = mysqli_real_escape_string($connect, $profile_key);
    $settings = [];

    $sql = "SELECT f.feature_key, f.feature_label, f.field_type, s.is_not_applicable, s.setting_value
            FROM role_access_settings s
            INNER JOIN role_access_profiles p ON p.id = s.profile_id
            INNER JOIN role_access_features f ON f.id = s.feature_id
            WHERE p.profile_key = '$profile_key'
            ORDER BY f.sort_order ASC";

    $q = mysqli_query($connect, $sql);
    while ($q && ($r = mysqli_fetch_assoc($q))) {
        $settings[$r['feature_key']] = $r;
    }
    return $settings;
}

/**
 * Get a single feature value for a user profile.
 * Returns null when N/A or not configured.
 */
function get_role_access_feature_value($connect, $profile_key, $feature_key) {
    $settings = get_role_access_settings_for_profile($connect, $profile_key);
    if (!isset($settings[$feature_key])) {
        return null;
    }
    $row = $settings[$feature_key];
    if (!empty($row['is_not_applicable'])) {
        return null;
    }
    return $row['setting_value'];
}

/**
 * Check yes/no style feature (YES = enabled/visible).
 */
function is_role_access_feature_enabled($connect, $profile_key, $feature_key) {
    $value = get_role_access_feature_value($connect, $profile_key, $feature_key);
    if ($value === null) {
        return false;
    }
    return strtoupper(trim($value)) === 'YES';
}

/**
 * Resolve profile + settings for current session user.
 */
function get_current_user_role_access_settings($connect) {
    if (!function_exists('get_current_user_role')) {
        require_once __DIR__ . '/role_helper.php';
    }

    $role = get_current_user_role();
    if (!$role) {
        return ['profile_key' => null, 'settings' => []];
    }

    $collab = false;
    $influencer = 'NO';

    if ($role === 'CUSTOMER') {
        $collab = !empty($_SESSION['collaboration_enabled']);
        $email = $_SESSION['user_email'] ?? '';
        if ($email !== '') {
            $email_esc = mysqli_real_escape_string($connect, $email);
            $q = mysqli_query($connect, "SELECT influencer FROM user_details WHERE email='$email_esc' AND role='CUSTOMER' LIMIT 1");
            if ($q && ($r = mysqli_fetch_assoc($q))) {
                $influencer = $r['influencer'] ?? 'NO';
            }
        }
    }

    $profile_key = resolve_role_access_profile_key($role, $collab, $influencer);
    return [
        'profile_key' => $profile_key,
        'settings' => get_role_access_settings_for_profile($connect, $profile_key),
    ];
}
