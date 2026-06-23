<?php
/**
 * Create role-wise access settings tables and seed default values.
 *
 * Run ONCE: admin/create_role_access_settings_table.php
 */

require_once(__DIR__ . '/../app/config/database.php');

echo '<h2>Role Access Settings — Database Setup</h2>';

function ras_msg($cls, $text) {
    echo '<div class="'.$cls.'">'.htmlspecialchars($text).'</div>';
}

function ras_table_exists($connect, $table) {
    $table = mysqli_real_escape_string($connect, $table);
    $res = mysqli_query($connect, "SHOW TABLES LIKE '$table'");
    return $res && mysqli_num_rows($res) > 0;
}

// --- Tables ---
$sql_profiles = "CREATE TABLE IF NOT EXISTS role_access_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    profile_key VARCHAR(50) NOT NULL,
    profile_label VARCHAR(150) NOT NULL,
    base_role ENUM('ADMIN','CUSTOMER','TEAM','FRANCHISEE') NOT NULL,
    requires_collaboration ENUM('ANY','YES','NO') NOT NULL DEFAULT 'ANY',
    requires_influencer ENUM('ANY','YES','NO') NOT NULL DEFAULT 'ANY',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_profile_key (profile_key),
    KEY idx_base_role (base_role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$sql_features = "CREATE TABLE IF NOT EXISTS role_access_features (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    feature_key VARCHAR(80) NOT NULL,
    feature_label VARCHAR(200) NOT NULL,
    feature_group VARCHAR(80) NOT NULL DEFAULT 'General',
    field_type ENUM('yes_no','text','textarea') NOT NULL DEFAULT 'text',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_feature_key (feature_key),
    KEY idx_feature_group (feature_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$sql_settings = "CREATE TABLE IF NOT EXISTS role_access_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    profile_id INT UNSIGNED NOT NULL,
    feature_id INT UNSIGNED NOT NULL,
    is_not_applicable TINYINT(1) NOT NULL DEFAULT 0,
    setting_value TEXT NOT NULL,
    updated_by VARCHAR(255) DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_profile_feature (profile_id, feature_id),
    KEY idx_profile_id (profile_id),
    KEY idx_feature_id (feature_id),
    CONSTRAINT fk_ras_profile FOREIGN KEY (profile_id) REFERENCES role_access_profiles(id) ON DELETE CASCADE,
    CONSTRAINT fk_ras_feature FOREIGN KEY (feature_id) REFERENCES role_access_features(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

foreach ([$sql_profiles, $sql_features, $sql_settings] as $sql) {
    if (!mysqli_query($connect, $sql)) {
        ras_msg('danger', 'Error creating table: '.mysqli_error($connect));
        exit;
    }
}
ras_msg('success', 'Tables role_access_profiles, role_access_features, role_access_settings are ready.');

// --- Seed profiles (only if empty) ---
$count_profiles = mysqli_query($connect, "SELECT COUNT(*) AS c FROM role_access_profiles");
$profile_count = ($count_profiles && ($r = mysqli_fetch_assoc($count_profiles))) ? (int)$r['c'] : 0;

$profiles = [
    ['admin',           'Admin',                                      'ADMIN',     'ANY', 'ANY', 1],
    ['mw_user',         'MW User',                                    'CUSTOMER',  'NO',  'ANY', 2],
    ['fse_team',        'FSE (Team)',                                 'TEAM',      'ANY', 'ANY', 3],
    ['frd_freelancer',  'Franchisee Distributer (All Freelancers)',   'CUSTOMER',  'YES', 'NO',  4],
    ['frd_influencer',  'Franchisee Distributer (Creator/Influencers)','CUSTOMER',  'YES', 'YES', 5],
    ['franchisee',      'Franchisee',                                 'FRANCHISEE','ANY', 'ANY', 6],
];

if ($profile_count === 0) {
    foreach ($profiles as $p) {
        $stmt = mysqli_prepare($connect,
            "INSERT INTO role_access_profiles (profile_key, profile_label, base_role, requires_collaboration, requires_influencer, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, 'sssssi', $p[0], $p[1], $p[2], $p[3], $p[4], $p[5]);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    ras_msg('success', 'Seeded '.count($profiles).' role profiles.');
} else {
    ras_msg('info', 'Role profiles already exist — skipping profile seed.');
}

// --- Seed features (only if empty) ---
$count_features = mysqli_query($connect, "SELECT COUNT(*) AS c FROM role_access_features");
$feature_count = ($count_features && ($r = mysqli_fetch_assoc($count_features))) ? (int)$r['c'] : 0;

$features = [
    ['complimentary_website',       'Complimentary Website',              'Dashboard & Access',       'textarea', 1],
    ['collaboration',               'Collaboration (On/Off)',             'Dashboard & Access',       'yes_no',   2],
    ['kit_management',              'KIT Management',                     'Dashboard & Access',       'textarea', 3],
    ['grow_with_mw',                'Grow with MW',                       'Dashboard & Access',       'yes_no',   4],
    ['customer_manager',            'Customer Manager',                   'Dashboard & Access',       'textarea', 5],
    ['id_card',                     'ID Card',                            'Dashboard & Access',       'yes_no',   6],
    ['create_mw_button',            'Create your MW Button',              'Dashboard & Access',       'textarea', 7],
    ['mw_plan_visible',             'MW Plan Visible to them',            'Plans & Payments',         'textarea', 8],
    ['mw_payment_agreements',       'MW Payment Page (Agreement Details)','Plans & Payments',         'textarea', 9],
    ['franchise_plan_visible',      'Franchise Plan Visible to them',     'Plans & Payments',         'textarea', 10],
    ['franchisee_payment_agreements','Franchisee Payment Page (Agreements)','Plans & Payments',      'textarea', 11],
    ['agreement_documents',         'Any Agreement/Document for them',    'Agreements & Documents',   'textarea', 12],
];

if ($feature_count === 0) {
    foreach ($features as $f) {
        $stmt = mysqli_prepare($connect,
            "INSERT INTO role_access_features (feature_key, feature_label, feature_group, field_type, sort_order)
             VALUES (?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, 'ssssi', $f[0], $f[1], $f[2], $f[3], $f[4]);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    ras_msg('success', 'Seeded '.count($features).' features.');
} else {
    ras_msg('info', 'Features already exist — skipping feature seed.');
}

// --- Seed settings (only if empty) ---
$count_settings = mysqli_query($connect, "SELECT COUNT(*) AS c FROM role_access_settings");
$settings_count = ($count_settings && ($r = mysqli_fetch_assoc($count_settings))) ? (int)$r['c'] : 0;

if ($settings_count === 0) {
    // profile_key => [ feature_key => ['na'=>bool, 'value'=>string] ]
    $defaults = [
        'admin' => [
            'complimentary_website' => ['na' => true,  'value' => ''],
            'collaboration'         => ['na' => true,  'value' => ''],
            'kit_management'        => ['na' => true,  'value' => ''],
            'grow_with_mw'          => ['na' => true,  'value' => ''],
            'customer_manager'      => ['na' => true,  'value' => ''],
            'id_card'               => ['na' => true,  'value' => ''],
            'mw_plan_visible'       => ['na' => true,  'value' => ''],
            'mw_payment_agreements' => ['na' => true,  'value' => ''],
            'franchise_plan_visible'=> ['na' => false, 'value' => 'MW Full Franchise Plan'],
            'franchisee_payment_agreements' => ['na' => false, 'value' => "MW Franchise Agreement.docx\nMW_Franchisee Operation Policy.docx\nMW_Privacy Policy.docx"],
            'agreement_documents'   => ['na' => true,  'value' => ''],
            'create_mw_button'      => ['na' => true,  'value' => ''],
        ],
        'mw_user' => [
            'complimentary_website' => ['na' => true,  'value' => ''],
            'collaboration'         => ['na' => true,  'value' => ''],
            'kit_management'        => ['na' => true,  'value' => ''],
            'grow_with_mw'          => ['na' => false, 'value' => 'YES'],
            'customer_manager'      => ['na' => false, 'value' => 'Yes (For MW user) tracker'],
            'id_card'               => ['na' => true,  'value' => ''],
            'mw_plan_visible'       => ['na' => false, 'value' => '1, 2 & 3 year plan'],
            'mw_payment_agreements' => ['na' => false, 'value' => "MW_Terms,Conditions & Refund Policy.docx\nMW_Privacy Policy.docx"],
            'franchise_plan_visible'=> ['na' => true,  'value' => ''],
            'franchisee_payment_agreements' => ['na' => true,  'value' => ''],
            'agreement_documents'   => ['na' => true,  'value' => ''],
            'create_mw_button'      => ['na' => false, 'value' => "Can create 1st MW without pay. After that if user want to 'Create new MW'. To Business Name fill karne ke baad hi 'Pay Now' ka option aayega. Bina payment kiye uska account create nahi hoga"],
        ],
        'fse_team' => [
            'complimentary_website' => ['na' => false, 'value' => 'YES. Always free'],
            'collaboration'         => ['na' => true,  'value' => ''],
            'kit_management'        => ['na' => false, 'value' => 'Auto visible - (MW Sales Kit/Franchise Sales Kit)'],
            'grow_with_mw'          => ['na' => false, 'value' => 'NO'],
            'customer_manager'      => ['na' => false, 'value' => 'Yes (For Team & AI Franchisee Distributor) tracker'],
            'id_card'               => ['na' => false, 'value' => 'YES'],
            'mw_plan_visible'       => ['na' => false, 'value' => '6 months, 1, 2 & 3 year plan'],
            'mw_payment_agreements' => ['na' => false, 'value' => "MW_Terms,Conditions & Refund Policy.docx\nMW_Privacy Policy.docx"],
            'franchise_plan_visible'=> ['na' => false, 'value' => "MW Starter Franchise Plan\nMW Full Franchise Plan"],
            'franchisee_payment_agreements' => ['na' => false, 'value' => "MW Franchise Agreement.docx\nMW_Franchisee Operation Policy.docx\nMW_Privacy Policy.docx"],
            'agreement_documents'   => ['na' => false, 'value' => 'Appointment letter'],
            'create_mw_button'      => ['na' => false, 'value' => "Can create 1st MW without pay. After that if user want to 'Create new MW'. To Business Name fill karne ke baad hi 'Pay Now' ka option aayega. Bina payment kiye uska account create nahi hoga"],
        ],
        'frd_freelancer' => [
            'complimentary_website' => ['na' => false, 'value' => 'YES for 1 year. Pay after that.'],
            'collaboration'         => ['na' => false, 'value' => 'YES'],
            'kit_management'        => ['na' => false, 'value' => 'Auto visible - (MW Sales Kit/Franchise Sales Kit)'],
            'grow_with_mw'          => ['na' => false, 'value' => 'NO'],
            'customer_manager'      => ['na' => false, 'value' => 'Yes (For Team & AI Franchisee Distributor) tracker'],
            'id_card'               => ['na' => false, 'value' => 'YES'],
            'mw_plan_visible'       => ['na' => false, 'value' => '1, 2 & 3 year plan'],
            'mw_payment_agreements' => ['na' => false, 'value' => "MW_Terms,Conditions & Refund Policy.docx\nMW_Privacy Policy.docx"],
            'franchise_plan_visible'=> ['na' => false, 'value' => 'MW Full Franchise Plan'],
            'franchisee_payment_agreements' => ['na' => false, 'value' => "MW Full Franchise Agreement.docx\nMW_Franchisee Operation Policy.docx\nMW_Privacy Policy.docx"],
            'agreement_documents'   => ['na' => false, 'value' => 'MW Sales Partner Agreement'],
            'create_mw_button'      => ['na' => false, 'value' => "Can create 1st MW without pay. After that if user want to 'Create new MW'. To Business Name fill karne ke baad hi 'Pay Now' ka option aayega. Bina payment kiye uska account create nahi hoga"],
        ],
        'frd_influencer' => [
            'complimentary_website' => ['na' => false, 'value' => 'YES for 1 year. Pay after that.'],
            'collaboration'         => ['na' => false, 'value' => 'YES'],
            'kit_management'        => ['na' => false, 'value' => 'Auto visible - (MW Sales Kit/Creator Kit)'],
            'grow_with_mw'          => ['na' => false, 'value' => 'NO'],
            'customer_manager'      => ['na' => true,  'value' => ''],
            'id_card'               => ['na' => true,  'value' => ''],
            'mw_plan_visible'       => ['na' => false, 'value' => '1, 2 & 3 year plan'],
            'mw_payment_agreements' => ['na' => false, 'value' => "MW_Terms,Conditions & Refund Policy.docx\nMW_Privacy Policy.docx"],
            'franchise_plan_visible'=> ['na' => false, 'value' => 'MW Full Franchise Plan'],
            'franchisee_payment_agreements' => ['na' => false, 'value' => "MW Full Franchise Agreement.docx\nMW_Franchisee Operation Policy.docx\nMW_Privacy Policy.docx"],
            'agreement_documents'   => ['na' => false, 'value' => 'MW Creator Agreement'],
            'create_mw_button'      => ['na' => false, 'value' => "Can create 1st MW without pay. After that if user want to 'Create new MW'. To Business Name fill karne ke baad hi 'Pay Now' ka option aayega. Bina payment kiye uska account create nahi hoga"],
        ],
        'franchisee' => [
            'complimentary_website' => ['na' => true,  'value' => ''],
            'collaboration'         => ['na' => true,  'value' => ''],
            'kit_management'        => ['na' => false, 'value' => 'Auto visible - MW Sales Kit'],
            'grow_with_mw'          => ['na' => false, 'value' => 'NO'],
            'customer_manager'      => ['na' => false, 'value' => 'Yes (For Franchise Partner) tracker'],
            'id_card'               => ['na' => false, 'value' => 'YES'],
            'mw_plan_visible'       => ['na' => false, 'value' => '1, 2 & 3 year plan'],
            'mw_payment_agreements' => ['na' => true,  'value' => ''],
            'franchise_plan_visible'=> ['na' => true,  'value' => ''],
            'franchisee_payment_agreements' => ['na' => true,  'value' => ''],
            'agreement_documents'   => ['na' => true,  'value' => ''],
            'create_mw_button'      => ['na' => true,  'value' => ''],
        ],
    ];

    $profile_map = [];
    $pq = mysqli_query($connect, "SELECT id, profile_key FROM role_access_profiles");
    while ($pq && ($pr = mysqli_fetch_assoc($pq))) {
        $profile_map[$pr['profile_key']] = (int)$pr['id'];
    }

    $feature_map = [];
    $fq = mysqli_query($connect, "SELECT id, feature_key FROM role_access_features");
    while ($fq && ($fr = mysqli_fetch_assoc($fq))) {
        $feature_map[$fr['feature_key']] = (int)$fr['id'];
    }

    $inserted = 0;
    foreach ($defaults as $profile_key => $feature_settings) {
        if (!isset($profile_map[$profile_key])) {
            continue;
        }
        $profile_id = $profile_map[$profile_key];
        foreach ($feature_settings as $feature_key => $setting) {
            if (!isset($feature_map[$feature_key])) {
                continue;
            }
            $feature_id = $feature_map[$feature_key];
            $na = $setting['na'] ? 1 : 0;
            $value = $setting['value'];
            $stmt = mysqli_prepare($connect,
                "INSERT INTO role_access_settings (profile_id, feature_id, is_not_applicable, setting_value)
                 VALUES (?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt, 'iiis', $profile_id, $feature_id, $na, $value);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $inserted++;
        }
    }
    ras_msg('success', "Seeded $inserted default settings from role matrix.");
} else {
    ras_msg('info', 'Settings already exist — skipping settings seed.');
}

// --- Move Create your MW Button into Dashboard (idempotent for existing installs) ---
$mw_btn_moved = false;
if (ras_table_exists($connect, 'role_access_features')) {
    $chk = mysqli_query($connect,
        "SELECT id FROM role_access_features
         WHERE feature_key = 'create_mw_button' AND feature_group <> 'Dashboard & Access' LIMIT 1"
    );
    if ($chk && mysqli_num_rows($chk) > 0) {
        $sort_updates = [
            ['create_mw_button',             'Dashboard & Access', 7],
            ['mw_plan_visible',              'Plans & Payments',   8],
            ['mw_payment_agreements',        'Plans & Payments',   9],
            ['franchise_plan_visible',       'Plans & Payments',   10],
            ['franchisee_payment_agreements','Plans & Payments',  11],
            ['agreement_documents',          'Agreements & Documents', 12],
        ];
        $stmt = mysqli_prepare($connect,
            "UPDATE role_access_features SET feature_group = ?, sort_order = ? WHERE feature_key = ?"
        );
        foreach ($sort_updates as $row) {
            mysqli_stmt_bind_param($stmt, 'sis', $row[1], $row[2], $row[0]);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
        $mw_btn_moved = true;
    }
}
if ($mw_btn_moved) {
    ras_msg('success', 'Moved "Create your MW Button" into Dashboard & Access section.');
}

ras_msg('success', 'Setup complete. Open admin/manage_role_access_settings.php to manage settings.');

?>
<style>
.success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 5px 0; }
.danger  { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 5px 0; }
.info    { color: #004085; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; margin: 5px 0; }
</style>
