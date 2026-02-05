<?php
/**
 * Migration: Copy referral_code and referred_by from customer_login to user_details
 *
 * Purpose:
 *   - Copies existing referral_code and referred_by from customer_login
 *     into the unified user_details table (matching by email).
 *   - Does not remove or truncate any data; only updates user_details
 *     with values from customer_login. Where customer_login has empty
 *     values, user_details keeps its existing values (no overwrite with empty).
 *   - For any user_details row (any role) with blank referral_code, generates
 *     a unique 8-char referral code (same format as registration).
 *
 * Usage:
 *   - Run once after ensuring user_details has a referral_code column.
 *   - Optional: run as admin (check session) or use ?run=1 for one-time.
 *
 * Safe to run multiple times (idempotent for data copy and generation).
 */

require_once __DIR__ . '/../app/config/database.php';

// Optional: allow only when run=1 is passed or admin is logged in (uncomment if needed)
// $allow = (isset($_GET['run']) && $_GET['run'] === '1') || (isset($_SESSION['admin_email']) && isset($_SESSION['admin_is_logged_in']) && $_SESSION['admin_is_logged_in'] === true);
// if (!$allow) { header('Location: login.php'); exit; }

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration: Referral data to user_details</title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        h1 { color: #333; }
        .msg { padding: 0.5rem 1rem; margin: 0.5rem 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        table { border-collapse: collapse; width: 100%; margin: 1rem 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
<h1>Migration: customer_login → user_details (referral_code &amp; referred_by)</h1>

<?php
function mig_msg($type, $msg) {
    echo '<div class="msg ' . $type . '">' . htmlspecialchars($msg) . '</div>';
}

// 1) Ensure user_details has referral_code column
$col_check = @mysqli_query($connect, "SHOW COLUMNS FROM user_details LIKE 'referral_code'");
if (!$col_check || mysqli_num_rows($col_check) === 0) {
    $alter = @mysqli_query($connect, "ALTER TABLE user_details ADD COLUMN referral_code VARCHAR(50) DEFAULT '' AFTER referred_by");
    if ($alter) {
        mig_msg('success', 'Added column user_details.referral_code.');
    } else {
        mig_msg('error', 'Could not add referral_code column: ' . mysqli_error($connect));
        echo '</body></html>';
        exit;
    }
} else {
    mig_msg('info', 'Column user_details.referral_code already exists.');
}

// 2) Count how many rows in customer_login have referral_code or referred_by
$count_sql = "SELECT 
    COUNT(*) AS total,
    SUM(CASE WHEN COALESCE(TRIM(referral_code), '') != '' THEN 1 ELSE 0 END) AS with_code,
    SUM(CASE WHEN COALESCE(TRIM(referred_by), '') != '' THEN 1 ELSE 0 END) AS with_referred_by
FROM customer_login
WHERE user_email IS NOT NULL AND user_email != ''";
$count_q = mysqli_query($connect, $count_sql);
$counts = $count_q && mysqli_num_rows($count_q) > 0 ? mysqli_fetch_assoc($count_q) : ['total' => 0, 'with_code' => 0, 'with_referred_by' => 0];
mig_msg('info', sprintf(
    'customer_login: %d rows with email; %d with referral_code; %d with referred_by.',
    (int)$counts['total'],
    (int)$counts['with_code'],
    (int)$counts['with_referred_by']
));

// 3) Update user_details from customer_login (match by email; do not overwrite with empty)
$update_sql = "
UPDATE user_details ud
INNER JOIN customer_login cl 
    ON TRIM(ud.email) = TRIM(cl.user_email) 
    AND ud.role = 'CUSTOMER'
SET 
    ud.referral_code = CASE 
        WHEN COALESCE(TRIM(cl.referral_code), '') != '' THEN TRIM(cl.referral_code) 
        ELSE COALESCE(ud.referral_code, '') 
    END,
    ud.referred_by = CASE 
        WHEN COALESCE(TRIM(cl.referred_by), '') != '' THEN TRIM(cl.referred_by) 
        ELSE COALESCE(ud.referred_by, '') 
    END
";
$updated = mysqli_query($connect, $update_sql);
if ($updated) {
    $affected = mysqli_affected_rows($connect);
    mig_msg('success', "Updated user_details: $affected row(s) updated with referral_code and/or referred_by from customer_login.");
} else {
    mig_msg('error', 'Update failed: ' . mysqli_error($connect));
}

// 4) Generate referral_code for any user_details row where it is blank (all roles)
$blank_sql = "SELECT id, email FROM user_details WHERE COALESCE(TRIM(referral_code), '') = ''";
$blank_q = mysqli_query($connect, $blank_sql);
$generated = 0;
$generated_ids = [];
if ($blank_q && mysqli_num_rows($blank_q) > 0) {
    while ($row = mysqli_fetch_assoc($blank_q)) {
        $uid = (int)($row['id'] ?? 0);
        $email = $row['email'] ?? '';
        $code = '';
        $max_attempts = 20;
        for ($i = 0; $i < $max_attempts; $i++) {
            $candidate = strtoupper(substr(md5($email . $uid . uniqid('', true) . mt_rand()), 0, 8));
            $exists = mysqli_query($connect, "SELECT 1 FROM user_details WHERE referral_code = '" . mysqli_real_escape_string($connect, $candidate) . "' LIMIT 1");
            if ($exists && mysqli_num_rows($exists) === 0) {
                $code = $candidate;
                break;
            }
        }
        if ($code === '') {
            $code = strtoupper(substr(md5($email . $uid . microtime(true)), 0, 8));
        }
        $esc_code = mysqli_real_escape_string($connect, $code);
        $up = mysqli_query($connect, "UPDATE user_details SET referral_code = '$esc_code' WHERE id = $uid");
        if ($up && mysqli_affected_rows($connect) > 0) {
            $generated++;
            $generated_ids[] = ['id' => $uid, 'email' => $email, 'referral_code' => $code];
        }
    }
    if ($generated > 0) {
        mig_msg('success', "Generated referral_code for $generated user(s) that had blank referral_code.");
    }
} else {
    mig_msg('info', 'No user_details rows with blank referral_code; nothing to generate.');
}

// 5) Optional: show sample of migrated data (first 10)
$sample_sql = "
SELECT ud.email, ud.referral_code, ud.referred_by, ud.role
FROM user_details ud
INNER JOIN customer_login cl ON TRIM(ud.email) = TRIM(cl.user_email) AND ud.role = 'CUSTOMER'
WHERE COALESCE(TRIM(cl.referral_code), '') != '' OR COALESCE(TRIM(cl.referred_by), '') != ''
LIMIT 10
";
$sample_q = mysqli_query($connect, $sample_sql);
if ($sample_q && mysqli_num_rows($sample_q) > 0) {
    echo '<p><strong>Sample of user_details rows that had referral data in customer_login:</strong></p>';
    echo '<table><thead><tr><th>Email</th><th>referral_code</th><th>referred_by</th><th>role</th></tr></thead><tbody>';
    while ($row = mysqli_fetch_assoc($sample_q)) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['email'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['referral_code'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['referred_by'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['role'] ?? '') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

if ($generated > 0 && count($generated_ids) > 0) {
    echo '<p><strong>Sample of generated referral codes (first 10):</strong></p>';
    echo '<table><thead><tr><th>ID</th><th>Email</th><th>referral_code (new)</th></tr></thead><tbody>';
    foreach (array_slice($generated_ids, 0, 10) as $r) {
        echo '<tr><td>' . (int)$r['id'] . '</td><td>' . htmlspecialchars($r['email']) . '</td><td>' . htmlspecialchars($r['referral_code']) . '</td></tr>';
    }
    echo '</tbody></table>';
}

echo '<p><strong>Done.</strong> No data was deleted; user_details was updated from customer_login and blank referral_codes were generated where needed.</p>';
?>
</body>
</html>
