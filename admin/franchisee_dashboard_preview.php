<?php
// Admin-side compact view that shows only "Manage Users" list for a franchisee (no iframe)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once('../common/config.php');

// Authorize: admin session or admin referrer
$isAdmin = !empty($_SESSION['admin_email']);
if (!$isAdmin && isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], '/admin/') !== false) { $isAdmin = true; }
if (!$isAdmin) { http_response_code(403); echo 'Access denied: admin session not found'; exit; }

$frEmail = isset($_GET['email']) ? trim($_GET['email']) : '';
if ($frEmail === '') { http_response_code(400); echo 'Missing franchisee email'; exit; }

// Query users created by this franchisee - using user_details
$frEmailEsc = mysqli_real_escape_string($connect, $frEmail);
$sql = "
    SELECT 
        cl.id,
        cl.name AS user_name,
        cl.phone AS user_contact,
        cl.email AS user_email,
        cl.created_at AS uploaded_date,
        dc.id AS card_id,
        dc.d_payment_status
    FROM user_details cl
    LEFT JOIN digi_card dc ON cl.email = dc.user_email
    WHERE dc.f_user_email = '$frEmailEsc' AND cl.role='CUSTOMER'
    ORDER BY cl.created_at DESC
    LIMIT 200
";
$res = mysqli_query($connect, $sql);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Users</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        body { background:#f6f8fb; padding:16px; }
        .table-card { background:#fff; border-radius:12px; box-shadow:0 6px 20px rgba(0,0,0,.06); overflow:hidden; }
        .table-card .card-header { background:#1a2b4c; color:#fff; padding:12px 16px; font-weight:600; }
        .status-badge { padding:.25rem .5rem; border-radius:.5rem; font-size:.75rem; }
        .bg-trial { background:#adb5bd; color:#fff; }
        .bg-inactive { background:#dc3545; color:#fff; }
        .bg-active { background:#28a745; color:#fff; }
        .nowrap { white-space:nowrap; }
    </style>
    </head>
<body>
    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0" style="min-width:900px">
                <thead class="table-light">
                    <tr>
                        <th class="nowrap">User ID</th>
                        <th class="nowrap">MW ID</th>
                        <th class="nowrap">User Email</th>
                        <th class="nowrap">User Name</th>
                        <th class="nowrap">User Number</th>
                        <th class="nowrap">Date Created</th>
                        <th class="nowrap">Validity Date</th>
                        <th class="nowrap">MW Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($res && mysqli_num_rows($res) > 0) {
                    while ($u = mysqli_fetch_assoc($res)) {
                        $validity = !empty($u['uploaded_date']) ? date('d-m-Y', strtotime($u['uploaded_date'].' +1 year')) : '-';
                        $status = $u['d_payment_status'] ?? '';
                        if ($status === 'Success') { $badge='bg-active'; $statusText='Active'; }
                        elseif ($status === 'Failed') { $badge='bg-inactive'; $statusText='InActive'; }
                        else { $badge='bg-trial'; $statusText='Trial'; }
                        echo '<tr>';
                        echo '<td>'.(int)$u['id'].'</td>';
                        echo '<td>'.(!empty($u['card_id']) ? (int)$u['card_id'] : '-').'</td>';
                        echo '<td>'.htmlspecialchars($u['user_email']).'</td>';
                        echo '<td>'.htmlspecialchars($u['user_name']).'</td>';
                        echo '<td>'.htmlspecialchars($u['user_contact']).'</td>';
                        echo '<td>'.(!empty($u['uploaded_date']) ? date('d-m-Y', strtotime($u['uploaded_date'])) : '-').'</td>';
                        echo '<td>'.$validity.'</td>';
                        echo '<td><span class="status-badge '.$badge.'">'.$statusText.'</span></td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="8" class="text-center py-4">No referred users found.</td></tr>';
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>



