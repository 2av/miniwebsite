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
 * Parse yes/no setting values (supports "YES", "YES (note)", "NO", etc.).
 */
function is_role_access_yes_value($value) {
    if ($value === null) {
        return false;
    }
    $v = strtoupper(trim((string) $value));
    if ($v === '') {
        return false;
    }
    if ($v === 'NO' || strpos($v, 'NO ') === 0 || strpos($v, 'NO(') === 0) {
        return false;
    }
    return ($v === 'YES' || strpos($v, 'YES ') === 0 || strpos($v, 'YES(') === 0);
}

/**
 * Check yes/no style feature (YES = enabled/visible).
 */
function is_role_access_feature_enabled($connect, $profile_key, $feature_key) {
    $value = get_role_access_feature_value($connect, $profile_key, $feature_key);
    if ($value === null) {
        return false;
    }
    return is_role_access_yes_value($value);
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
        'influencer' => $influencer,
        'collaboration_enabled' => $collab,
    ];
}

/**
 * Whether a feature row is configured (not marked N/A) for a profile.
 */
function is_role_access_feature_applicable($connect, $profile_key, $feature_key) {
    if (!$profile_key || !role_access_tables_exist($connect)) {
        return false;
    }
    $settings = get_role_access_settings_for_profile($connect, $profile_key);
    if (!isset($settings[$feature_key])) {
        return false;
    }
    return empty($settings[$feature_key]['is_not_applicable']);
}

/**
 * Menu / dashboard visibility for yes_no or textarea features.
 */
function is_role_access_feature_visible($connect, $profile_key, $feature_key, $field_type = 'text') {
    if (!$profile_key || !role_access_tables_exist($connect)) {
        return false;
    }
    if ($field_type === 'yes_no') {
        return is_role_access_feature_enabled($connect, $profile_key, $feature_key);
    }
    return is_role_access_feature_applicable($connect, $profile_key, $feature_key);
}

/**
 * Complimentary website rules from profile settings.
 */
function get_complimentary_website_rules($connect, $profile_key) {
    $value = get_role_access_feature_value($connect, $profile_key, 'complimentary_website');
    if ($value === null || trim($value) === '') {
        return ['apply' => false];
    }
    $v = strtolower($value);
    if (strpos($v, 'always free') !== false) {
        return ['apply' => true, 'always_free' => true];
    }
    if (strpos($v, '1 year') !== false || strpos($v, 'yes') !== false) {
        return ['apply' => true, 'one_year' => true];
    }
    return ['apply' => false];
}

/**
 * SQL validity expression for complimentary card on create.
 */
function complimentary_validity_sql(array $rules) {
    if (!empty($rules['always_free'])) {
        return "DATE_ADD(NOW(), INTERVAL 10 YEAR)";
    }
    if (!empty($rules['one_year'])) {
        return "DATE_ADD(NOW(), INTERVAL 1 YEAR)";
    }
    return "DATE_ADD(NOW(), INTERVAL 7 DAY)";
}

/**
 * Resolve role access profile key from a user email (for payment pages).
 */
function resolve_role_access_profile_for_email($connect, $email) {
    if ($email === '' || !role_access_tables_exist($connect)) {
        return null;
    }
    $email_esc = mysqli_real_escape_string($connect, strtolower(trim($email)));
    $q = mysqli_query($connect, "SELECT role, collaboration_enabled, influencer FROM user_details WHERE LOWER(TRIM(email))='$email_esc' LIMIT 1");
    if (!$q || !($r = mysqli_fetch_assoc($q))) {
        return null;
    }
    return resolve_role_access_profile_key(
        $r['role'] ?? '',
        (($r['collaboration_enabled'] ?? '') === 'YES'),
        $r['influencer'] ?? 'NO'
    );
}

/**
 * Default checked MW plan radio from allowed list.
 */
function mw_default_checked_plan(array $allowed_plans) {
    return $allowed_plans[0] ?? 'plan_1year';
}

/**
 * Parse MW plan keys allowed for a profile (pay_miniwebsite.php).
 */
function parse_mw_plans_from_setting($setting_value, $use_team_500_pricing = false) {
    if ($setting_value === null || trim((string) $setting_value) === '') {
        return $use_team_500_pricing
            ? ['plan_team500', 'plan_1year', 'plan_2year', 'plan_3year']
            : ['plan_1year', 'plan_2year', 'plan_3year'];
    }

    $v = strtolower((string) $setting_value);
    $plans = [];

    if ((strpos($v, '6 month') !== false || strpos($v, '6month') !== false) && $use_team_500_pricing) {
        $plans[] = 'plan_team500';
    }
    if (preg_match('/\b1\s*(?:year|,|&)/', $v) || preg_match('/\b1\s*year\b/', $v)) {
        $plans[] = 'plan_1year';
    }
    if (preg_match('/\b2\s*(?:year|,|&)/', $v) || preg_match('/\b2\s*year\b/', $v)) {
        $plans[] = 'plan_2year';
    }
    if (preg_match('/\b3\s*(?:year|,|&)/', $v) || preg_match('/\b3\s*year\b/', $v)) {
        $plans[] = 'plan_3year';
    }

    if (empty($plans)) {
        return $use_team_500_pricing
            ? ['plan_team500', 'plan_1year', 'plan_2year', 'plan_3year']
            : ['plan_1year', 'plan_2year', 'plan_3year'];
    }

    return array_values(array_unique($plans));
}

/**
 * Parse franchise plan visibility (starter / full).
 */
function parse_franchise_plans_from_setting($setting_value) {
    if ($setting_value === null || trim((string) $setting_value) === '') {
        return ['starter' => true, 'full' => true];
    }
    $v = strtolower((string) $setting_value);
    return [
        'starter' => (strpos($v, 'starter') !== false),
        'full' => (strpos($v, 'full') !== false),
    ];
}

/**
 * Map agreement document labels (from admin matrix) to public CMS pages.
 */
function role_access_resolve_document_link($line, $assets_base = '') {
    $raw = trim((string) $line);
    $base = rtrim((string) $assets_base, '/');
    $norm = strtolower(str_replace(['_', '.docx', '.doc'], [' ', '', ''], $raw));

    $patterns = [
        'full franchise agreement' => ['label' => 'MW Full Franchise Agreement', 'path' => '/mw-full-franchise-agreement.php'],
        'terms,conditions & refund' => ['label' => 'MW Terms, Conditions & Refund Policy', 'path' => '/terms_conditions.php'],
        'terms conditions & refund' => ['label' => 'MW Terms, Conditions & Refund Policy', 'path' => '/terms_conditions.php'],
        'terms,conditions' => ['label' => 'MW Terms, Conditions & Refund Policy', 'path' => '/terms_conditions.php'],
        'franchisee operation policy' => ['label' => 'MW Franchisee Operation Policy', 'path' => '/mw-franchisee-operation-policy.php'],
        'operation policy' => ['label' => 'MW Franchisee Operation Policy', 'path' => '/mw-franchisee-operation-policy.php'],
        'franchise agreement' => ['label' => 'MW Franchise Agreement', 'path' => '/franchise_agreement.php'],
        'privacy policy' => ['label' => 'MW Privacy Policy', 'path' => '/privacy_policy.php'],
        'privacy' => ['label' => 'MW Privacy Policy', 'path' => '/privacy_policy.php'],
        'creator agreement' => ['label' => 'MW Creator Agreement', 'path' => '/franchisee-distributer-agreement.php'],
        'sales partner agreement' => ['label' => 'MW Sales Partner Agreement', 'path' => '/franchisee-distributer-agreement.php'],
        'refund policy' => ['label' => 'Refund Policy', 'path' => '/terms_conditions.php'],
        'terms' => ['label' => 'Terms & Conditions', 'path' => '/terms_conditions.php'],
    ];

    foreach ($patterns as $needle => $meta) {
        if (strpos($norm, $needle) !== false) {
            return [
                'label' => $meta['label'],
                'url' => $base . $meta['path'],
            ];
        }
    }

    $label = preg_replace('/\.docx$/i', '', $raw);
    $label = str_replace('_', ' ', $label);
    return ['label' => $label, 'url' => $base . '/terms_conditions.php'];
}

/**
 * Agreement links for MW or franchise payment pages from role_access_settings.
 */
function get_role_access_agreement_links($connect, $profile_key, $feature_key, $assets_base = '') {
    $base = rtrim((string) $assets_base, '/');
    $defaults = [
        'mw_payment_agreements' => [
            ['label' => 'MW Terms, Conditions & Refund Policy', 'url' => $base . '/terms_conditions.php'],
            ['label' => 'MW Privacy Policy', 'url' => $base . '/privacy_policy.php'],
        ],
        'franchisee_payment_agreements' => [
            ['label' => 'MW Franchise Agreement', 'url' => $base . '/franchise_agreement.php'],
            ['label' => 'MW Franchisee Operation Policy', 'url' => $base . '/mw-franchisee-operation-policy.php'],
            ['label' => 'MW Privacy Policy', 'url' => $base . '/privacy_policy.php'],
        ],
    ];

    if (!$profile_key || !role_access_tables_exist($connect)) {
        return $defaults[$feature_key] ?? [];
    }

    $settings = get_role_access_settings_for_profile($connect, $profile_key);
    if (!isset($settings[$feature_key])) {
        return $defaults[$feature_key] ?? [];
    }
    if (!empty($settings[$feature_key]['is_not_applicable'])) {
        return [];
    }

    $value = trim((string) ($settings[$feature_key]['setting_value'] ?? ''));
    if ($value === '') {
        return $defaults[$feature_key] ?? [];
    }

    $links = [];
    foreach (preg_split('/\r\n|\r|\n/', $value) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $links[] = role_access_resolve_document_link($line, $assets_base);
    }
    return $links;
}

/**
 * Whether the user has a successful MW payment (paid user, not free/trial).
 * TEAM / FRANCHISEE / ADMIN are always treated as paid for feature gating.
 */
function is_mw_paid_user($connect, $email, $role = null) {
    $role = strtoupper(trim((string) $role));
    if (in_array($role, ['TEAM', 'FRANCHISEE', 'ADMIN'], true)) {
        return true;
    }

    $email = strtolower(trim((string) $email));
    if ($email === '' || !($connect instanceof mysqli)) {
        return false;
    }

    $email_esc = mysqli_real_escape_string($connect, $email);
    $sql = "SELECT id FROM digi_card
            WHERE UPPER(TRIM(d_payment_status)) = 'SUCCESS'
              AND (LOWER(TRIM(user_email)) = '$email_esc' OR LOWER(TRIM(f_user_email)) = '$email_esc')
            LIMIT 1";
    $res = mysqli_query($connect, $sql);
    return $res && mysqli_num_rows($res) > 0;
}

/**
 * Features that require a paid MW (not free/trial) for CUSTOMER users.
 */
function role_access_feature_requires_mw_paid($feature_key) {
    return in_array($feature_key, ['grow_with_mw', 'customer_manager'], true);
}

/**
 * Menu / dashboard visibility including paid-user gate for applicable features.
 */
function is_role_access_feature_visible_for_user($connect, $profile_key, $feature_key, $field_type, $email, $role) {
    if (!is_role_access_feature_visible($connect, $profile_key, $feature_key, $field_type)) {
        return false;
    }
    if (strtoupper(trim((string) $role)) === 'CUSTOMER'
        && role_access_feature_requires_mw_paid($feature_key)
        && !is_mw_paid_user($connect, $email, $role)) {
        return false;
    }
    return true;
}

/**
 * Build user_conditions array for menu_helper from role access profile.
 */
function build_role_access_user_conditions($connect, $current_role, $base_conditions = []) {
    $conditions = $base_conditions;
    if (!role_access_tables_exist($connect)) {
        return $conditions;
    }

    $ras = get_current_user_role_access_settings($connect);
    $profile_key = $ras['profile_key'] ?? null;
    $conditions['role_access_profile_key'] = $profile_key;
    $conditions['influencer'] = (($ras['influencer'] ?? 'NO') === 'YES');

    if ($profile_key === 'frd_freelancer' && is_role_access_feature_applicable($connect, $profile_key, 'kit_management')) {
        $conditions['saleskit_enabled'] = true;
    }

    if (!function_exists('get_user_email')) {
        require_once __DIR__ . '/role_helper.php';
    }
    $email = get_user_email() ?? '';
    $conditions['mw_paid'] = is_mw_paid_user($connect, $email, $current_role);

    return $conditions;
}

/**
 * Render agreement checkbox label HTML for payment pages.
 */
function render_role_access_agreement_label_html(array $links) {
    if (empty($links)) {
        return 'I have read and agree to the applicable terms and policies.';
    }
    $parts = [];
    foreach ($links as $link) {
        $label = htmlspecialchars($link['label'] ?? 'Document', ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars($link['url'] ?? '#', ENT_QUOTES, 'UTF-8');
        $parts[] = '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>';
    }
    $count = count($parts);
    if ($count === 1) {
        return 'I have read and agree to the ' . $parts[0] . '.';
    }
    $last = array_pop($parts);
    return 'I have read and agree to the ' . implode(', ', $parts) . ' and ' . $last . '.';
}

/**
 * Load franchise plan + agreement rules for franchise_agreement.php / franchise pay flow.
 */
function get_franchise_payment_role_rules($connect, $profile_key) {
    if (!$profile_key) {
        return [
            'profile_key' => null,
            'plan_visibility' => ['starter' => true, 'full' => true],
            'default_plan' => 'full',
            'agreement_links' => get_role_access_agreement_links($connect, 'fse_team', 'franchisee_payment_agreements', ''),
            'agreement_label_html' => render_role_access_agreement_label_html(
                get_role_access_agreement_links($connect, 'fse_team', 'franchisee_payment_agreements', '')
            ),
        ];
    }

    $plan_setting = get_role_access_feature_value($connect, $profile_key, 'franchise_plan_visible');
    $plan_visibility = parse_franchise_plans_from_setting($plan_setting);
    $default_plan = 'full';
    if (!$plan_visibility['starter'] && $plan_visibility['full']) {
        $default_plan = 'full';
    } elseif ($plan_visibility['starter'] && !$plan_visibility['full']) {
        $default_plan = 'starter';
    } elseif ($plan_visibility['starter'] && $plan_visibility['full']) {
        $default_plan = 'full';
    }

    $agreement_links = get_role_access_agreement_links($connect, $profile_key, 'franchisee_payment_agreements', '');

    return [
        'profile_key' => $profile_key,
        'plan_visibility' => $plan_visibility,
        'default_plan' => $default_plan,
        'agreement_links' => $agreement_links,
        'agreement_label_html' => render_role_access_agreement_label_html($agreement_links),
    ];
}
