<?php
session_start();
include('config.php');

// Check admin login
if(!isset($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

// Get payments with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? mysqli_real_escape_string($connect, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($connect, $_GET['status']) : '';

$where_clause = "WHERE 1=1";
if($search) {
    $where_clause .= " AND (franchise_email LIKE '%$search%' OR reference_number LIKE '%$search%' OR razorpay_payment_id LIKE '%$search%')";
}
if($status_filter) {
    $where_clause .= " AND payment_status = '$status_filter'";
}

$query = "SELECT * FROM franchise_payments $where_clause ORDER BY payment_date DESC LIMIT $limit OFFSET $offset";
$result = mysqli_query($connect, $query);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM franchise_payments $where_clause";
$count_result = mysqli_query($connect, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Franchise Payments</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #002169; color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .filters { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .filters input, .filters select { margin: 5px; padding: 8px; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .status-success { color: #4CAF50; font-weight: bold; }
        .status-failed { color: #f44336; font-weight: bold; }
        .status-pending { color: #ff9800; font-weight: bold; }
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a { padding: 8px 12px; margin: 0 4px; text-decoration: none; background: #002169; color: white; border-radius: 4px; }
        .pagination a.active { background: #ff9800; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Franchise Payments Management</h1>
        <p>Total Records: <?php echo $total_records; ?></p>
    </div>

    <div class="filters">
        <form method="GET">
            <input type="text" name="search" placeholder="Search by email, reference, or payment ID" value="<?php echo htmlspecialchars($search); ?>">
            <select name="status">
                <option value="">All Status</option>
                <option value="Success" <?php echo $status_filter == 'Success' ? 'selected' : ''; ?>>Success</option>
                <option value="Failed" <?php echo $status_filter == 'Failed' ? 'selected' : ''; ?>>Failed</option>
                <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
            </select>
            <button type="submit">Filter</button>
            <a href="franchise_payments.php">Clear</a>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Email</th>
                <th>Reference</th>
                <th>Payment ID</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['franchise_email']); ?></td>
                <td><?php echo htmlspecialchars($row['reference_number']); ?></td>
                <td><?php echo htmlspecialchars($row['razorpay_payment_id']); ?></td>
                <td>Rs <?php echo number_format($row['amount'], 2); ?></td>
                <td class="status-<?php echo strtolower($row['payment_status']); ?>">
                    <?php echo $row['payment_status']; ?>
                </td>
                <td><?php echo date('d M Y, h:i A', strtotime($row['payment_date'])); ?></td>
                <td>
                    <a href="view_payment.php?id=<?php echo $row['id']; ?>">View</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="pagination">
        <?php for($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" 
               class="<?php echo $i == $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
    </div>
</body>
</html>


