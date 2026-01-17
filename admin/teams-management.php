<?php


require_once(__DIR__ . '/../app/config/database.php');

if (!isset($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

// Refresh admin session to prevent expiration
// Update session access time and refresh cookie
if (isset($_SESSION['admin_email'])) {
    $_SESSION['last_activity'] = time();
    // Refresh session cookie to extend lifetime
    $session_lifetime = 2592000; // 30 days
    $secure = isset($_SERVER['HTTPS']);
    setcookie(session_name(), session_id(), time() + $session_lifetime, '/', '', $secure, true);
}

// Handle AJAX popups FIRST - before any HTML output
if (isset($_GET['ajax'])) {
    header('Content-Type: text/html; charset=utf-8');
    
    if (!function_exists('teams_h')) {
        function teams_h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
    }

    // Ensure table exists for AJAX queries
    $createTableSql = "CREATE TABLE IF NOT EXISTS team_members (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        member_name VARCHAR(150) NOT NULL,
        member_email VARCHAR(255) NOT NULL,
        member_phone VARCHAR(25) DEFAULT NULL,
        password_hash VARCHAR(255) NOT NULL,
        status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
        last_login_at DATETIME NULL DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_member_email (member_email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $connect->query($createTableSql);

    if ($_GET['ajax'] === 'tracker' && isset($_GET['team_id'])) {
        $teamId = (int)$_GET['team_id'];

        // Load basic member info - using user_details
        $member = null;
        // Try to find by legacy_team_id first, then by id
        $stmt = $connect->prepare('SELECT name AS member_name, email AS member_email FROM user_details WHERE (legacy_team_id = ? OR id = ?) AND role="TEAM" LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('ii', $teamId, $teamId);
            $stmt->execute();
            $res = $stmt->get_result();
            $member = $res->fetch_assoc();
            $stmt->close();
        }

        if (!$member) {
            echo '<div class="p-3 text-danger">Team member not found.</div>';
            exit;
        }

        // Get tracker records for this team member
        $records = [];
        $sql = "
            SELECT ct.*,
                   COALESCE(
                       (SELECT MAX(f.followup_datetime)
                          FROM customer_tracker_followups f
                         WHERE f.tracker_id = ct.id),
                       ct.created_at
                   ) AS last_updated
            FROM customer_tracker ct
            WHERE ct.team_member_id = ?
            ORDER BY ct.date_visited DESC, last_updated DESC
        ";
        $stmt = $connect->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $teamId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $records[] = $row;
            }
            $stmt->close();
        }

        echo '<div class="p-3">';
        echo '<h6 class="mb-3">Customer Tracker - ' . teams_h($member['member_name']) . ' (' . teams_h($member['member_email']) . ')</h6>';

        if (empty($records)) {
            echo '<div class="alert alert-info mb-0">No customer visits recorded for this team member.</div>';
            exit;
        }

        echo '<div class="table-responsive" style="max-height:520px;overflow:auto;">';
        echo '<table class="table table-sm table-striped table-hover align-middle">';
        echo '<thead class="table-light">';
        echo '<tr>
                <th>Shop/Person Name</th>
                <th>Contact Number</th>
                <th>Approached For</th>
                <th>Address</th>
                <th>Date Visited</th>
                <th>Final Status</th>
                <th>Last Updated</th>
              </tr>';
        echo '</thead><tbody>';

        foreach ($records as $r) {
            $statusClass = 'badge bg-warning';
            if ($r['final_status'] === 'Joined') {
                $statusClass = 'badge bg-success';
            } elseif ($r['final_status'] === 'Not Interested') {
                $statusClass = 'badge bg-danger';
            }

            echo '<tr>';
            echo '<td>' . teams_h($r['shop_name']) . '</td>';
            echo '<td>' . teams_h($r['contact_number'] ?: '-') . '</td>';
            echo '<td>' . teams_h($r['approached_for'] ?? '-') . '</td>';
            echo '<td>' . teams_h($r['address'] ?: '-') . '</td>';
            echo '<td>' . teams_h(date('d-m-Y', strtotime($r['date_visited']))) . '</td>';
            echo '<td><span class="' . $statusClass . '">' . teams_h($r['final_status']) . '</span></td>';
            $lastUpdated = $r['last_updated'] ? date('d-m-Y H:i', strtotime($r['last_updated'])) : '-';
            echo '<td>' . teams_h($lastUpdated) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div></div>';
        exit;
    }

    if ($_GET['ajax'] === 'referrals' && isset($_GET['team_id'])) {
        $teamId = (int)$_GET['team_id'];

        // Find member email - using user_details
        $member = null;
        // Try to find by legacy_team_id first, then by id
        $stmt = $connect->prepare('SELECT name AS member_name, email AS member_email FROM user_details WHERE (legacy_team_id = ? OR id = ?) AND role="TEAM" LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('ii', $teamId, $teamId);
            $stmt->execute();
            $res = $stmt->get_result();
            $member = $res->fetch_assoc();
            $stmt->close();
        }

        if (!$member || empty($member['member_email'])) {
            echo '<div class="p-3 text-danger">Referral details not available for this team member.</div>';
            exit;
        }

        $email = $member['member_email'];

        // Pull referral earnings with user_details information
        // Also include records from user_details where referred_by matches (even if no referral_earnings entry)
        $sql = "
            SELECT DISTINCT
                COALESCE(re.referred_email, ud_referred.email) AS referred_email,
                COALESCE(re.amount, 0) AS amount,
                COALESCE(re.is_collaboration, 'NO') AS is_collaboration,
                COALESCE(re.referral_date, ud_referred.created_at) AS referral_date,
                COALESCE(re.status, 'Pending') AS referral_status,
                ud_referred.id AS user_id,
                ud_referred.name AS referred_name,
                ud_referred.phone AS referred_phone,
                ud_referred.role AS referred_role,
                dc.id AS card_id,
                dc.d_payment_status,
                dc.d_payment_date
            FROM user_details ud_referred
            LEFT JOIN referral_earnings re 
                ON CONVERT(re.referred_email USING utf8mb4) = CONVERT(ud_referred.email USING utf8mb4)
                AND CONVERT(re.referrer_email USING utf8mb4) = CONVERT(? USING utf8mb4)
            LEFT JOIN digi_card dc 
                ON CONVERT(dc.user_email USING utf8mb4) = CONVERT(ud_referred.email USING utf8mb4)
            WHERE (CONVERT(ud_referred.referred_by USING utf8mb4) = CONVERT(? USING utf8mb4)
                   AND ud_referred.referred_by != ''
                   AND ud_referred.referred_by IS NOT NULL)
               OR (re.id IS NOT NULL AND CONVERT(re.referrer_email USING utf8mb4) = CONVERT(? USING utf8mb4))
            ORDER BY COALESCE(re.id, 0) DESC, ud_referred.created_at DESC
            LIMIT 300
        ";

        $rows = [];
        $stmt = $connect->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('sss', $email, $email, $email);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $rows[] = $row;
                }
            } else {
                // Log error for debugging
                error_log("Referral query error: " . $stmt->error);
                echo '<div class="alert alert-danger">Error loading referral data. Please try again.</div>';
                exit;
            }
            $stmt->close();
        } else {
            error_log("Failed to prepare referral query: " . $connect->error);
            echo '<div class="alert alert-danger">Error preparing query. Please try again.</div>';
            exit;
        }

        echo '<div class="p-3">';
        echo '<h6 class="mb-3">Referral Details - ' . teams_h($member['member_name']) . ' (' . teams_h($member['member_email']) . ')</h6>';

        if (empty($rows)) {
            echo '<div class="alert alert-info mb-0">No referrals found for this team member.</div>';
            exit;
        }

        echo '<div class="table-responsive" style="max-height:520px;overflow:auto;">';
        echo '<table class="table table-sm table-striped table-hover align-middle">';
        echo '<thead class="table-light">
                <tr>
                    <th>User ID</th>
                    <th>Referred Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>MW Payment Status</th>
                    <th>Paid On</th>
                    <th>Referral Date</th>
                </tr>
              </thead><tbody>';

        foreach ($rows as $r) {
            $type = ($r['is_collaboration'] ?? 'NO') === 'YES' ? 'Franchise' : 'Mini Website';
            $referredName = !empty($r['referred_name']) ? teams_h($r['referred_name']) : teams_h($r['referred_email']);
            $referredPhone = !empty($r['referred_phone']) ? teams_h($r['referred_phone']) : '—';
            $amount = !empty($r['amount']) ? '₹' . number_format((float)$r['amount'], 2) : '—';
            $referralDate = !empty($r['referral_date']) ? date('d-m-Y', strtotime($r['referral_date'])) : '—';

            $statusBadge = '<span class="badge bg-secondary">N/A</span>';
            $paidOn = '-';
            if (!empty($r['d_payment_status'])) {
                if ($r['d_payment_status'] === 'Success' && !empty($r['d_payment_date']) && $r['d_payment_date'] !== '0000-00-00 00:00:00') {
                    $statusBadge = '<span class="badge bg-success">Paid</span>';
                    $paidOn = date('d-m-Y', strtotime($r['d_payment_date']));
                } elseif ($r['d_payment_status'] === 'Failed') {
                    $statusBadge = '<span class="badge bg-danger">Failed</span>';
                } elseif (in_array($r['d_payment_status'], ['Created', 'Pending', ''], true)) {
                    $statusBadge = '<span class="badge bg-warning text-dark">Pending</span>';
                } else {
                    $statusBadge = '<span class="badge bg-secondary">' . teams_h($r['d_payment_status']) . '</span>';
                }
            }

            $userId = !empty($r['user_id']) ? (int)$r['user_id'] : '—';
            
            echo '<tr>';
            echo '<td><strong>' . $userId . '</strong></td>';
            echo '<td>' . $referredName . '</td>';
            echo '<td>' . teams_h($r['referred_email']) . '</td>';
            echo '<td>' . $referredPhone . '</td>';
            echo '<td>' . teams_h($type) . '</td>';
            echo '<td>' . $amount . '</td>';
            echo '<td>' . $statusBadge . '</td>';
            echo '<td>' . teams_h($paidOn) . '</td>';
            echo '<td>' . teams_h($referralDate) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div></div>';
        exit;
    }

    echo '<div class="p-3 text-danger">Invalid request.</div>';
    exit;
}

// Normal page flow - include header and continue
require_once(__DIR__ . '/header.php');

$tableError = '';
$errors = [];
$successMessage = '';

$createTableSql = "CREATE TABLE IF NOT EXISTS team_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_name VARCHAR(150) NOT NULL,
    member_email VARCHAR(255) NOT NULL,
    member_phone VARCHAR(25) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    last_login_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_member_email (member_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$connect->query($createTableSql)) {
    $tableError = 'Failed to ensure team members table exists: ' . $connect->error;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($tableError)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_member') {
        $memberName = trim($_POST['member_name'] ?? '');
        $memberEmail = strtolower(trim($_POST['member_email'] ?? ''));
        $memberPhone = trim($_POST['member_phone'] ?? '');
        $memberPassword = trim($_POST['member_password'] ?? '');

        if ($memberName === '') {
            $errors[] = 'Member name is required.';
        }
        if ($memberEmail === '' || !filter_var($memberEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid member email address is required.';
        }
        if ($memberPassword === '' || strlen($memberPassword) < 6) {
            $errors[] = 'Password must be at least 6 characters long.';
        }

        if (empty($errors)) {
            // Global uniqueness: check email in unified user_details table (any role)
            $safeEmail = mysqli_real_escape_string($connect, $memberEmail);
            $email_check = mysqli_query($connect, "SELECT role, email FROM user_details WHERE email='$safeEmail' LIMIT 1");
            
            if ($email_check && mysqli_num_rows($email_check) > 0) {
                $email_data = mysqli_fetch_array($email_check);
                $source = ucfirst(strtolower($email_data['role'] ?? 'user'));
                $errors[] = "This email address is already registered as a $source. Please use a different email.";
            }
            
            // Global uniqueness: check mobile number in unified user_details table (any role)
            if (!empty($memberPhone)) {
                $safePhone = mysqli_real_escape_string($connect, $memberPhone);
                $mobile_check = mysqli_query($connect, "SELECT role, phone FROM user_details WHERE phone='$safePhone' LIMIT 1");
                
                if ($mobile_check && mysqli_num_rows($mobile_check) > 0) {
                    $mobile_data = mysqli_fetch_array($mobile_check);
                    $source = ucfirst(strtolower($mobile_data['role'] ?? 'user'));
                    $errors[] = "This mobile number is already registered as a $source. Please use a different mobile number.";
                }
            }
            
            if (empty($errors)) {
                // Check in user_details table for team members
                $checkStmt = $connect->prepare('SELECT id FROM user_details WHERE email = ? AND role="TEAM" LIMIT 1');
                if ($checkStmt) {
                    $checkStmt->bind_param('s', $memberEmail);
                    $checkStmt->execute();
                    $checkStmt->store_result();

                    if ($checkStmt->num_rows > 0) {
                        $errors[] = 'This email address is already registered as a team member.';
                    }
                    $checkStmt->close();
                } else {
                    $errors[] = 'Failed to validate member uniqueness: ' . $connect->error;
                }
            }
        }

        if (empty($errors)) {
            $passwordHash = password_hash($memberPassword, PASSWORD_DEFAULT);
            // Insert into user_details table with role='TEAM'
            $insertStmt = $connect->prepare('INSERT INTO user_details (role, email, phone, name, password_hash, status) VALUES ("TEAM", ?, ?, ?, ?, "ACTIVE")');
            if ($insertStmt) {
                $insertStmt->bind_param('ssss', $memberEmail, $memberPhone, $memberName, $passwordHash);
                if ($insertStmt->execute()) {
                    $successMessage = 'Team member added successfully.';
                } else {
                    $errors[] = 'Failed to add team member: ' . $insertStmt->error;
                }
                $insertStmt->close();
            } else {
                $errors[] = 'Failed to prepare insert statement: ' . $connect->error;
            }
        }
    } elseif ($action === 'toggle_status') {
        $memberId = (int)($_POST['member_id'] ?? 0);
        $newStatus = $_POST['new_status'] === 'ACTIVE' ? 'ACTIVE' : 'INACTIVE';
        if ($memberId > 0) {
            // Update in user_details - try by legacy_team_id first, then by id
            $toggleStmt = $connect->prepare('UPDATE user_details SET status = ?, updated_at = NOW() WHERE (legacy_team_id = ? OR id = ?) AND role="TEAM"');
            if ($toggleStmt) {
                $toggleStmt->bind_param('sii', $newStatus, $memberId, $memberId);
                if ($toggleStmt->execute()) {
                    $successMessage = 'Member status updated.';
                } else {
                    $errors[] = 'Failed to update status: ' . $toggleStmt->error;
                }
                $toggleStmt->close();
            } else {
                $errors[] = 'Failed to prepare status update statement: ' . $connect->error;
            }
        }
    } elseif ($action === 'reset_password') {
        $memberId = (int)($_POST['member_id'] ?? 0);
        $newPassword = trim($_POST['new_password'] ?? '');
        if ($memberId <= 0 || $newPassword === '' || strlen($newPassword) < 6) {
            $errors[] = 'Please provide a valid password (minimum 6 characters).';
        }
        if (empty($errors)) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            // Update in user_details - try by legacy_team_id first, then by id
            $resetStmt = $connect->prepare('UPDATE user_details SET password_hash = ?, updated_at = NOW() WHERE (legacy_team_id = ? OR id = ?) AND role="TEAM"');
            if ($resetStmt) {
                $resetStmt->bind_param('sii', $newHash, $memberId, $memberId);
                if ($resetStmt->execute()) {
                    $successMessage = 'Password reset successfully.';
                } else {
                    $errors[] = 'Failed to reset password: ' . $resetStmt->error;
                }
                $resetStmt->close();
            } else {
                $errors[] = 'Failed to prepare password reset statement: ' . $connect->error;
            }
        }
    }
}

$teamMembers = [];
$teamStats   = [];
if (empty($tableError)) {
    // Load team members from user_details
    $membersSql = 'SELECT id, legacy_team_id, name AS member_name, email AS member_email, phone AS member_phone, status, created_at FROM user_details WHERE role="TEAM" ORDER BY created_at DESC';
    $membersResult = $connect->query($membersSql);
    if ($membersResult) {
        while ($row = $membersResult->fetch_assoc()) {
            $teamMembers[] = $row;
        }
    } else {
        $tableError = 'Failed to load team members: ' . $connect->error;
    }
}

// Compute stats in a simple, per‑member way to avoid collation issues
if (empty($tableError) && !empty($teamMembers)) {
    foreach ($teamMembers as $tm) {
        $mid   = (int)$tm['id'];
        $email = $tm['member_email'];

        // Total MW Created = Count all referred users from referral_earnings (regardless of payment status)
        // Use CONVERT for better performance
        $mwCount = 0;
        $mwStmt = $connect->prepare("
            SELECT COUNT(*) as cnt
            FROM referral_earnings re
            WHERE CONVERT(re.referrer_email USING utf8mb4) = CONVERT(? USING utf8mb4)
        ");
        if ($mwStmt) {
            $mwStmt->bind_param('s', $email);
            if ($mwStmt->execute()) {
                $mwRes = $mwStmt->get_result();
                if ($mwRow = $mwRes->fetch_assoc()) {
                    $mwCount = (int)$mwRow['cnt'];
                }
            }
            $mwStmt->close();
        }

        // Total Sales = Count MWs that have "Paid on Date" (payment status = 'Success' and has payment date)
        // Use CONVERT for better performance
        $salesCount = 0;
        $salesSql = "
            SELECT COUNT(*) as cnt
            FROM referral_earnings re
            LEFT JOIN digi_card dc ON CONVERT(dc.user_email USING utf8mb4) = CONVERT(re.referred_email USING utf8mb4)
            WHERE CONVERT(re.referrer_email USING utf8mb4) = CONVERT(? USING utf8mb4)
            AND dc.d_payment_status = 'Success'
            AND dc.d_payment_date IS NOT NULL
            AND dc.d_payment_date != '0000-00-00 00:00:00'
        ";
        $salesStmt = $connect->prepare($salesSql);
        if ($salesStmt) {
            $salesStmt->bind_param('s', $email);
            if ($salesStmt->execute()) {
                $salesRes = $salesStmt->get_result();
                if ($salesRow = $salesRes->fetch_assoc()) {
                    $salesCount = (int)$salesRow['cnt'];
                }
            }
            $salesStmt->close();
        }

        // Count Customer Tracker records
        $trackerCount = 0;
        $trackerStmt = $connect->prepare('SELECT COUNT(*) as cnt FROM customer_tracker WHERE team_member_id = ?');
        if ($trackerStmt) {
            $trackerStmt->bind_param('i', $mid);
            $trackerStmt->execute();
            $trackerRes = $trackerStmt->get_result();
            if ($trackerRow = $trackerRes->fetch_assoc()) {
                $trackerCount = (int)$trackerRow['cnt'];
            }
            $trackerStmt->close();
        }

        // Count Referral Details records
        // Check both referral_earnings table AND user_details.referred_by field
        // This ensures we count all referrals, even if referral_earnings entry is missing
        $referralCount = 0;
        
        // First, count from referral_earnings table
        $referralSql = "
            SELECT COUNT(*) as cnt
            FROM referral_earnings re
            WHERE CONVERT(re.referrer_email USING utf8mb4) = CONVERT(? USING utf8mb4)
        ";
        $referralStmt = $connect->prepare($referralSql);
        if ($referralStmt) {
            $referralStmt->bind_param('s', $email);
            if ($referralStmt->execute()) {
                $referralRes = $referralStmt->get_result();
                if ($referralRow = $referralRes->fetch_assoc()) {
                    $referralCount = (int)$referralRow['cnt'];
                }
            }
            $referralStmt->close();
        }
        
        // Also count from user_details where referred_by matches this team member's email
        // This catches cases where referred_by is set but referral_earnings entry doesn't exist
        $referredBySql = "
            SELECT COUNT(*) as cnt
            FROM user_details ud
            WHERE CONVERT(ud.referred_by USING utf8mb4) = CONVERT(? USING utf8mb4)
            AND ud.referred_by != ''
            AND ud.referred_by IS NOT NULL
        ";
        $referredByStmt = $connect->prepare($referredBySql);
        if ($referredByStmt) {
            $referredByStmt->bind_param('s', $email);
            if ($referredByStmt->execute()) {
                $referredByRes = $referredByStmt->get_result();
                if ($referredByRow = $referredByRes->fetch_assoc()) {
                    $referredByCount = (int)$referredByRow['cnt'];
                    // Use the higher count (in case there are duplicates or missing entries)
                    if ($referredByCount > $referralCount) {
                        $referralCount = $referredByCount;
                    }
                }
            }
            $referredByStmt->close();
        }

        $teamStats[$mid] = [
            'total_sales'      => $salesCount,
            'total_mw_created' => $mwCount,
            'tracker_count'    => $trackerCount,
            'referral_count'   => $referralCount,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teams Management - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #f8f9fc; }
        .card { border-radius: 16px; box-shadow: 0 10px 30px rgba(31,45,61,0.1); }
        .badge-status { font-size: 0.75rem; }
        .table-responsive { border-radius: 12px; }
        
        /* Prevent modal from shifting page content */
        body.modal-open {
            overflow: hidden;
            padding-right: 0 !important;
        }
        .modal {
            padding-right: 0 !important;
        }
        .modal-backdrop {
            padding-right: 0 !important;
        }
    </style>
</head>
<body class="main-bg">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Teams Management</h3>
        <a href="index.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
    </div>

    <?php if (!empty($tableError)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($tableError); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success" role="alert">
            <?php echo htmlspecialchars($successMessage); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Create Team Member</h5>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="action" value="create_member">
                <div class="col-md-4">
                    <label for="member_name" class="form-label">Member Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="member_name" name="member_name" placeholder="Enter member name" required>
                </div>
                <div class="col-md-4">
                    <label for="member_email" class="form-label">Member Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="member_email" name="member_email" placeholder="name@example.com" required>
                </div>
                <div class="col-md-4">
                    <label for="member_phone" class="form-label">Mobile Number</label>
                    <input type="text" class="form-control" id="member_phone" name="member_phone" placeholder="Optional mobile number">
                </div>
                <div class="col-md-4">
                    <label for="member_password" class="form-label">Password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="member_password" name="member_password" placeholder="Minimum 6 characters" required>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Create Team Member</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Team Members</h5>
        </div>
            <div class="card-body">
            <?php if (!empty($teamMembers)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Teams Id</th>
                                <th scope="col">Member</th>
                                <th scope="col">Mobile</th>
                                <th scope="col">Total MW Created</th>
                                <th scope="col">Total Sales</th>
                                <th scope="col">Referral Details</th>
                                <th scope="col">Customer Tracker</th>
                                <th scope="col">Email</th>
                                <th scope="col">Status</th>
                                <th scope="col">Last Login</th>
                                <th scope="col">Created</th>
                                <th scope="col" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teamMembers as $member): ?>
                                <?php
                                    // Use actual id from user_details table
                                    $mid   = (int)$member['id'];
                                    $stats = $teamStats[$mid] ?? [
                                        'total_sales' => 0, 
                                        'total_mw_created' => 0,
                                        'tracker_count' => 0,
                                        'referral_count' => 0
                                    ];
                                    $trackerCount = $stats['tracker_count'] ?? 0;
                                    $referralCount = $stats['referral_count'] ?? 0;
                                    $trackerExcelUrl = 'customer-tracker.php?team_member=' . $mid . '&export=excel';
                                ?>
                                <tr>
                                    <!-- Teams Id - Show actual ID from user_details table -->
                                    <td><strong><?php echo (int)$member['id']; ?></strong></td>
                                    
                                    <!-- Member -->
                                    <td><strong><?php echo htmlspecialchars($member['member_name']); ?></strong></td>
                                    
                                    <!-- Mobile -->
                                    <td><?php echo htmlspecialchars($member['member_phone'] ?: '—'); ?></td>
                                    
                                    <!-- Total MW Created -->
                                    <td><?php echo (int)$stats['total_mw_created']; ?></td>
                                    
                                    <!-- Total Sales -->
                                    <td><?php echo (int)$stats['total_sales']; ?></td>
                                    
                                    <!-- Referral Details view -->
                                    <td>
                                        <?php if ($referralCount > 0): ?>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-primary btn-open-referral"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#referralTeamsModal"
                                                    data-team-id="<?php echo $mid; ?>"
                                                    data-member-name="<?php echo htmlspecialchars($member['member_name']); ?>">
                                                View
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Customer Tracker actions -->
                                    <td>
                                        <?php if ($trackerCount > 0): ?>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button"
                                                        class="btn btn-outline-primary btn-open-tracker"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#trackerModal"
                                                        data-team-id="<?php echo $mid; ?>"
                                                        data-member-name="<?php echo htmlspecialchars($member['member_name']); ?>">
                                                    View
                                                </button>
                                                <a href="<?php echo $trackerExcelUrl; ?>" class="btn btn-outline-success">
                                                    Excel
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Email -->
                                    <td><?php echo htmlspecialchars($member['member_email']); ?></td>
                                    
                                    <!-- Status -->
                                    <td>
                                        <span class="badge <?php echo $member['status'] === 'ACTIVE' ? 'bg-success' : 'bg-secondary'; ?> badge-status">
                                            <?php echo htmlspecialchars($member['status']); ?>
                                        </span>
                                    </td>
                                    
                                    <!-- Last Login (without time) -->
                                    <td>
                                        <?php echo (isset($member['last_login_at']) && !empty($member['last_login_at'])) ? htmlspecialchars(date('d M Y', strtotime($member['last_login_at']))) : '<span class="text-muted">Never</span>'; ?>
                                    </td>
                                    
                                    <!-- Created -->
                                    <td><?php echo htmlspecialchars(date('d M Y', strtotime($member['created_at']))); ?></td>
                                    <td class="text-end">
                                        <div class="btn-group" role="group">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="member_id" value="<?php echo (int)$member['id']; ?>">
                                                <input type="hidden" name="new_status" value="<?php echo $member['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE'; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-<?php echo $member['status'] === 'ACTIVE' ? 'secondary' : 'success'; ?>">
                                                    <?php echo $member['status'] === 'ACTIVE' ? 'Deactivate' : 'Activate'; ?>
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#resetPasswordModal<?php echo (int)$member['id']; ?>">
                                                Reset Password
                                            </button>
                                        </div>

                                        <div class="modal fade" id="resetPasswordModal<?php echo (int)$member['id']; ?>" tabindex="-1" aria-labelledby="resetPasswordLabel<?php echo (int)$member['id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <form method="post">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="resetPasswordLabel<?php echo (int)$member['id']; ?>">Reset Password</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="action" value="reset_password">
                                                            <input type="hidden" name="member_id" value="<?php echo (int)$member['id']; ?>">
                                                            <div class="mb-3">
                                                                <label for="new_password_<?php echo (int)$member['id']; ?>" class="form-label">New Password</label>
                                                                <input type="password" class="form-control" id="new_password_<?php echo (int)$member['id']; ?>" name="new_password" placeholder="Enter new password" required>
                                                                <small class="text-muted">Minimum 6 characters.</small>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Reset Password</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">No team members found yet. Create a member using the form above.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Customer Tracker Modal -->
<div class="modal fade" id="trackerModal" tabindex="-1" aria-labelledby="trackerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="trackerModalLabel">Customer Tracker</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="trackerModalContent" class="p-3">
                    <div class="text-center text-muted">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        <span class="ms-2">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Referral Details Modal (Teams) -->
<div class="modal fade" id="referralTeamsModal" tabindex="-1" aria-labelledby="referralTeamsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="referralTeamsModalLabel">Referral Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="referralTeamsContent" class="p-3">
                    <div class="text-center text-muted">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        <span class="ms-2">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Prevent modal from shifting page content
(function() {
    var originalPaddingRight = document.body.style.paddingRight;
    var scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
    
    // Override Bootstrap's modal padding behavior
    document.addEventListener('show.bs.modal', function(e) {
        document.body.style.paddingRight = '0px';
    });
    
    document.addEventListener('hidden.bs.modal', function(e) {
        document.body.style.paddingRight = originalPaddingRight;
    });
})();

document.addEventListener('DOMContentLoaded', function () {
    var trackerModal = document.getElementById('trackerModal');
    var trackerContent = document.getElementById('trackerModalContent');
    if (trackerModal && trackerContent) {
        trackerModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (!button) return;
            var teamId = button.getAttribute('data-team-id');
            var memberName = button.getAttribute('data-member-name') || '';

            var titleEl = trackerModal.querySelector('.modal-title');
            if (titleEl) {
                titleEl.textContent = 'Customer Tracker - ' + memberName;
            }

            trackerContent.innerHTML = '<div class="text-center text-muted"><div class="spinner-border spinner-border-sm" role="status"></div><span class="ms-2">Loading...</span></div>';

            // Add timeout to prevent infinite loading
            var timeoutId = setTimeout(function() {
                trackerContent.innerHTML = '<div class="alert alert-warning m-0">Request is taking longer than expected. Please try again.</div>';
            }, 30000); // 30 second timeout

            fetch('teams-management.php?ajax=tracker&team_id=' + encodeURIComponent(teamId))
                .then(function (resp) { 
                    clearTimeout(timeoutId);
                    if (!resp.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return resp.text(); 
                })
                .then(function (html) { 
                    trackerContent.innerHTML = html; 
                })
                .catch(function (error) {
                    clearTimeout(timeoutId);
                    console.error('Error:', error);
                    trackerContent.innerHTML = '<div class="alert alert-danger m-0">Failed to load customer tracker details. Please refresh the page and try again.</div>';
                });
        });
    }

    var referralModal = document.getElementById('referralTeamsModal');
    var referralContent = document.getElementById('referralTeamsContent');
    if (referralModal && referralContent) {
        referralModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (!button) return;
            var teamId = button.getAttribute('data-team-id');
            var memberName = button.getAttribute('data-member-name') || '';

            var titleEl = referralModal.querySelector('.modal-title');
            if (titleEl) {
                titleEl.textContent = 'Referral Details - ' + memberName;
            }

            referralContent.innerHTML = '<div class="text-center text-muted"><div class="spinner-border spinner-border-sm" role="status"></div><span class="ms-2">Loading...</span></div>';

            // Add timeout to prevent infinite loading
            var timeoutId = setTimeout(function() {
                referralContent.innerHTML = '<div class="alert alert-warning m-0">Request is taking longer than expected. Please try again.</div>';
            }, 30000); // 30 second timeout

            fetch('teams-management.php?ajax=referrals&team_id=' + encodeURIComponent(teamId))
                .then(function (resp) { 
                    clearTimeout(timeoutId);
                    if (!resp.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return resp.text(); 
                })
                .then(function (html) { 
                    referralContent.innerHTML = html; 
                })
                .catch(function (error) {
                    clearTimeout(timeoutId);
                    console.error('Error:', error);
                    referralContent.innerHTML = '<div class="alert alert-danger m-0">Failed to load referral details. Please refresh the page and try again.</div>';
                });
        });
    }
});
</script>
<?php require_once(__DIR__ . '/footer.php'); ?>



