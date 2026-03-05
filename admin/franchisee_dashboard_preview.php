<?php
// Admin-side dashboard view showing franchisee info, wallet, and managed users
require_once(__DIR__ . '/../app/config/database.php');

// Authorize: admin session or admin referrer
$isAdmin = !empty($_SESSION['admin_email']);
if (!$isAdmin && isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], '/admin/') !== false) { $isAdmin = true; }
if (!$isAdmin) { http_response_code(403); echo 'Access denied: admin session not found'; exit; }

$frEmail = isset($_GET['email']) ? trim($_GET['email']) : '';
if ($frEmail === '') { http_response_code(400); echo 'Missing franchisee email'; exit; }

// Query franchisee details
$frEmailEsc = mysqli_real_escape_string($connect, $frEmail);
$fr_details_query = mysqli_query($connect, "SELECT id, name, email, phone, created_at, status FROM user_details WHERE email = '$frEmailEsc' AND role = 'FRANCHISEE' LIMIT 1");
$fr_details = $fr_details_query && mysqli_num_rows($fr_details_query) > 0 ? mysqli_fetch_assoc($fr_details_query) : null;

// Query wallet balance
$wallet_query = mysqli_query($connect, "SELECT w_balance FROM wallet WHERE f_user_email = '$frEmailEsc' ORDER BY id DESC LIMIT 1");
$wallet_balance = 0;
if ($wallet_query && mysqli_num_rows($wallet_query) > 0) {
    $wallet_row = mysqli_fetch_assoc($wallet_query);
    $wallet_balance = floatval($wallet_row['w_balance'] ?? 0);
}

// Count of MWs created by franchisee
$mw_count_query = mysqli_query($connect, "SELECT COUNT(*) as count FROM digi_card WHERE f_user_email = '$frEmailEsc'");
$mw_count = 0;
if ($mw_count_query && mysqli_num_rows($mw_count_query) > 0) {
    $count_row = mysqli_fetch_assoc($mw_count_query);
    $mw_count = intval($count_row['count'] ?? 0);
}

// Query users created by this franchisee - using user_details
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
    <title>Franchisee Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background:#f6f8fb; padding:16px; }
        .dashboard-header { background:#1a2b4c; color:#fff; padding:16px; border-radius:8px; margin-bottom:16px; }
        .dashboard-header h4 { margin:0 0 8px 0; }
        .dashboard-header .info-row { display:flex; gap:24px; margin-top:12px; font-size:14px; }
        .info-item { display:flex; flex-direction:column; }
        .info-label { font-size:12px; color:#b0c4de; }
        .info-value { font-size:16px; font-weight:600; color:#fff; margin-top:4px; }
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:16px; }
        .stat-card { background:#fff; padding:16px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,.06); text-align:center; }
        .stat-card .stat-value { font-size:28px; font-weight:700; color:#1a2b4c; margin:8px 0; }
        .stat-card .stat-label { font-size:13px; color:#666; }
        .table-card { background:#fff; border-radius:12px; box-shadow:0 6px 20px rgba(0,0,0,.06); overflow:hidden; }
        .table-card .card-header { background:#1a2b4c; color:#fff; padding:12px 16px; font-weight:600; }
        .status-badge { padding:.25rem .5rem; border-radius:.5rem; font-size:.75rem; }
        .bg-trial { background:#adb5bd; color:#fff; }
        .bg-inactive { background:#dc3545; color:#fff; }
        .bg-active { background:#28a745; color:#fff; }
        .nowrap { white-space:nowrap; }
        .wallet-section { background:#e7f3ff; border-left:4px solid #2196F3; padding:12px; border-radius:4px; margin-bottom:16px; }
        .wallet-amount { font-size:24px; font-weight:700; color:#0c5aa0; }
    </style>
    </head>
<body>
    <?php if ($fr_details): ?>
    <div class="dashboard-header">
        <h4><i class="fas fa-store me-2"></i><?php echo htmlspecialchars($fr_details['name'] ?? 'Franchisee'); ?></h4>
        <div class="info-row">
            <div class="info-item">
                <span class="info-label">Email</span>
                <span class="info-value"><?php echo htmlspecialchars($fr_details['email']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Mobile</span>
                <span class="info-value"><?php echo htmlspecialchars($fr_details['phone']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Status</span>
                <span class="info-value"><span class="badge bg-success"><?php echo htmlspecialchars($fr_details['status']); ?></span></span>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total MiniWebsites Created</div>
            <div class="stat-value"><?php echo $mw_count; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Wallet Balance</div>
            <div class="stat-value" style="color:#28a745;">₹<?php echo number_format($wallet_balance, 2); ?></div>
        </div>
    </div>

  
    <?php endif; ?>

    <div class="table-card">
        <div class="table-card-header">
            <div class="card-header"><i class="fas fa-users me-2"></i>Manage Users</div>
        </div>
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



