<?php
require_once('connect.php');

header('Content-Type: text/html; charset=UTF-8');

$user_email = isset($_GET['user_email']) ? mysqli_real_escape_string($connect, $_GET['user_email']) : '';
if ($user_email === '') {
    echo '<div class="alert alert-danger">Missing user email</div>';
    exit;
}

// Get user details from user_details
$user_query = mysqli_query($connect, "SELECT name FROM user_details WHERE email='".$user_email."' AND role='CUSTOMER' LIMIT 1");
$user_data = $user_query ? mysqli_fetch_array($user_query) : array();
$user_name = $user_data['name'] ?? $user_email;

// Get joining deals history
$history_query = mysqli_query($connect, "SELECT 
    ujdm.*,
    jd.deal_name,
    jd.deal_type,
    jd.fees,
    jd.total_fees,
    jd.commission_amount
    FROM user_joining_deals_mapping ujdm
    LEFT JOIN joining_deals jd ON ujdm.joining_deal_id = jd.id
    WHERE ujdm.user_email='".$user_email."'
    ORDER BY ujdm.mapped_date DESC");

?>

<div style="padding:20px; background:#fff;">
    <div class="container-fluid px-2">
        <div class="mb-3">
            <h5 style="margin:0;">Joining Deals History - <?php echo htmlspecialchars($user_name); ?></h5>
            <small class="text-muted"><?php echo htmlspecialchars($user_email); ?></small>
        </div>

        <?php if($history_query && mysqli_num_rows($history_query) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="bg-secondary" style="color:#fff;">
                        <tr>
                            <th>Deal Name</th>
                            <th>Deal Type</th>
                            <th>Fees</th>
                            <th>Commission</th>
                            <th>Status</th>
                            <th>Email Sent</th>
                            <th>Mapped By</th>
                            <th>Mapped Date</th>
                            <th>Email Date</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_array($history_query)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['deal_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($row['deal_type'] ?? 'N/A'); ?></span>
                                </td>
                                <td>
                                    <?php if(($row['total_fees'] ?? 0) > 0): ?>
                                        ₹<?php echo number_format($row['total_fees'], 0); ?>
                                    <?php else: ?>
                                        <span class="text-success">Free</span>
                                    <?php endif; ?>
                                </td>
                                <td>₹<?php echo number_format($row['commission_amount'] ?? 0, 0); ?></td>
                                <td>
                                    <?php 
                                    $status = $row['mapping_status'] ?? 'ACTIVE';
                                    $status_class = '';
                                    switch($status) {
                                        case 'ACTIVE':
                                            $status_class = 'bg-success';
                                            break;
                                        case 'INACTIVE':
                                            $status_class = 'bg-secondary';
                                            break;
                                        case 'CANCELLED':
                                            $status_class = 'bg-danger';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>"><?php echo $status; ?></span>
                                </td>
                                <td>
                                    <?php 
                                    $email_sent = $row['email_sent'] ?? 'NO';
                                    $email_class = $email_sent === 'YES' ? 'bg-success' : 'bg-warning';
                                    ?>
                                    <span class="badge <?php echo $email_class; ?>"><?php echo $email_sent; ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($row['mapped_by'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php 
                                    $mapped_date = $row['mapped_date'] ?? '';
                                    echo $mapped_date ? date('d-m-Y H:i', strtotime($mapped_date)) : 'N/A';
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $email_date = $row['email_sent_date'] ?? '';
                                    echo $email_date ? date('d-m-Y H:i', strtotime($email_date)) : 'N/A';
                                    ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo htmlspecialchars($row['notes'] ?? 'N/A'); ?></small>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No joining deals history found for this user.
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.badge {
    font-size: 0.75em;
    padding: 0.375rem 0.75rem;
    border-radius: 0.375rem;
}

.table th {
    font-weight: 600;
    border-top: none;
}

.table td {
    vertical-align: middle;
}

.alert {
    border-radius: 10px;
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
</style>



