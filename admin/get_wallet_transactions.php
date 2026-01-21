<?php
require_once(__DIR__ . '/../app/config/database.php');

// Authorize admin
$isAdmin = !empty($_SESSION['admin_email']);
if (!$isAdmin && isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], '/admin/') !== false) { $isAdmin = true; }
if (!$isAdmin) { http_response_code(403); echo '<div class="p-4 text-danger">Access denied</div>'; exit; }

$email = isset($_GET['email']) ? trim($_GET['email']) : '';
if ($email === '') { echo '<div class="p-4">Email missing</div>'; exit; }
$emailEsc = mysqli_real_escape_string($connect, $email);

$q = mysqli_query($connect, 'SELECT * FROM wallet WHERE f_user_email="' . $emailEsc . '" ORDER BY id DESC LIMIT 50');

// Render compact table
?>
<div class="table-responsive">
  <table class="table table-striped table-hover mb-0" style="min-width:800px;">
    <thead class="table-light">
      <tr>
        <th>Date</th>
        <th>Time</th>
        <th>Txn ID</th>
        <th>Deposit</th>
        <th>Withdrawal</th>
        <th>Balance</th>
        <th>Details</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($q && mysqli_num_rows($q) > 0): ?>
        <?php while($row = mysqli_fetch_array($q)): ?>
          <?php
            $date = strtotime($row['uploaded_date']);
            $deposit = (float)($row['w_deposit'] ?? 0);
            $withdraw = (float)($row['w_withdraw'] ?? 0);
            $balance = (float)($row['w_balance'] ?? 0);
          ?>
          <tr>
            <td><?php echo date('d-m-Y', $date); ?></td>
            <td><?php echo date('h:i A', $date); ?></td>
            <td><?php echo htmlspecialchars($row['w_order_id']); ?></td>
            <td><?php echo $deposit > 0 ? ('<span class="text-success">₹'.number_format($deposit,2).'</span>') : '<span class="text-muted">-</span>'; ?></td>
            <td><?php echo ($withdraw > 0 || $withdraw < 0) ? ('<span class="text-danger">₹'.number_format($withdraw,2).'</span>') : '<span class="text-muted">-</span>'; ?></td>
            <td><strong>₹<?php echo number_format($balance,2); ?></strong></td>
            <td><?php echo htmlspecialchars($row['w_txn_msg']); ?></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="7" class="text-center py-4">No transactions found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>




