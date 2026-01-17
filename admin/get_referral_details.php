<?php
require_once(__DIR__ . '/../app/config/database.php');

if(isset($_GET['referrer_email'])) {
    $referrer_email = mysqli_real_escape_string($connect, $_GET['referrer_email']);
    
    // Get referrer info from user_details
    $referrer_query = mysqli_query($connect, "SELECT name FROM user_details WHERE email='$referrer_email' AND role='CUSTOMER'");
    $referrer_data = mysqli_fetch_array($referrer_query);
    
    echo '<h4>Referrals by: ' . ($referrer_data['name'] ?? $referrer_email) . ' (' . $referrer_email . ')</h4>';
    
    // Get detailed referral data with payment history
    $details_query = mysqli_query($connect, "SELECT re.*, 
        COALESCE(u.name, fl.name) as referred_name,
        dc.d_payment_status as user_payment_status,
        dc.d_payment_date as user_payment_date,
        COALESCE(SUM(rph.amount), 0) as total_paid
        FROM referral_earnings re 
        LEFT JOIN user_details u ON BINARY re.referred_email = BINARY u.email AND u.role='CUSTOMER'
        LEFT JOIN user_details fl ON BINARY re.referred_email = BINARY fl.email AND fl.role='FRANCHISEE'
        LEFT JOIN digi_card dc ON BINARY re.referred_email = BINARY dc.user_email
        LEFT JOIN referral_payment_history rph ON re.id = rph.referral_id
        WHERE re.referrer_email = '$referrer_email'
        GROUP BY re.id
        ORDER BY re.referral_date DESC");
    
    if(mysqli_num_rows($details_query) > 0) {
        echo '<table class="details-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Referred User</th>';
        echo '<th>Referral Date</th>';
        echo '<th>User Payment Status</th>';
        echo '<th>Total Amount</th>';
        echo '<th>Paid Amount</th>';
        echo '<th>Pending Amount</th>';
        echo '<th>Status</th>';
        echo '<th>Action</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        while($row = mysqli_fetch_array($details_query)) {
            $total_amount = $row['amount'];
            $paid_amount = $row['total_paid'];
            $pending_amount = $total_amount - $paid_amount;
            
            echo '<tr>';
            echo '<td>';
            echo '<strong>' . htmlspecialchars($row['referred_name'] ?? 'Unknown') . '</strong><br>';
            echo '<small>' . htmlspecialchars($row['referred_email']) . '</small>';
            echo '</td>';
            echo '<td>' . date('d-m-Y', strtotime($row['referral_date'])) . '</td>';
            
            // User Payment Status
            echo '<td>';
            if($row['user_payment_status'] == 'Success') {
                $payment_date = $row['user_payment_date'] ? date('d-m-Y', strtotime($row['user_payment_date'])) : 'N/A';
                echo '<span class="status-paid">Paid on ' . $payment_date . '</span>';
            } else {
                echo '<span class="status-pending">Not Paid</span>';
            }
            echo '</td>';
            
            echo '<td>₹' . number_format($total_amount, 0) . '</td>';
            echo '<td>₹' . number_format($paid_amount, 0) . '</td>';
            echo '<td>₹' . number_format($pending_amount, 0) . '</td>';
            
            // Status based on pending amount
            echo '<td>';
            if($pending_amount <= 0) {
                echo '<span class="status-paid">Fully Paid</span>';
            } else if($paid_amount > 0) {
                echo '<span class="status-partial">Partial</span>';  // Updated for shorter status
            } else {
                echo '<span class="status-pending">Pending</span>';
            }
            echo '</td>';
            
            echo '<td>';
            
            // Show payment button if there's pending amount
            if($pending_amount > 0) {
                echo '<button onclick="showPaymentForm(' . $row['id'] . ', ' . $pending_amount . ')" class="btn-manual">Add Payment</button><br>';
            }
            
            // Always show history button if there are payments
            if($paid_amount > 0) {
                echo '<button onclick="showPaymentHistory(' . $row['id'] . ')" class="btn-history">View History</button>';
            }
            
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p>No referral details found.</p>';
    }
}
?>





