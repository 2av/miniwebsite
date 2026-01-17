<?php
require_once(__DIR__ . '/../app/config/database.php');

if(isset($_GET['referral_id'])) {
    $referral_id = mysqli_real_escape_string($connect, $_GET['referral_id']);
    
    // Get referral info with fixed collation - using user_details
    $referral_query = mysqli_query($connect, "SELECT re.*, 
        u.name as referred_name,
        r.name as referrer_name
        FROM referral_earnings re 
        LEFT JOIN user_details u ON BINARY re.referred_email = BINARY u.email AND u.role='CUSTOMER'
        LEFT JOIN user_details r ON BINARY re.referrer_email = BINARY r.email AND r.role='CUSTOMER'
        WHERE re.id='$referral_id'");
    
    $referral_data = mysqli_fetch_array($referral_query);
    
    echo '<h4>Payment History</h4>';
    echo '<p><strong>Referrer:</strong> ' . $referral_data['referrer_name'] . '</p>';
    echo '<p><strong>Referred User:</strong> ' . $referral_data['referred_name'] . '</p>';
    echo '<p><strong>Referral Amount:</strong> ₹' . number_format($referral_data['amount'], 0) . '</p>';
    
    // Get payment history
    $history_query = mysqli_query($connect, "SELECT * FROM referral_payment_history 
        WHERE referral_id='$referral_id' 
        ORDER BY payment_date DESC");
    
    if(mysqli_num_rows($history_query) > 0) {
        echo '<table class="history-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Date</th>';
        echo '<th>Amount</th>';
        echo '<th>Transaction No.</th>';
        echo '<th>Method</th>';
        echo '<th>Notes</th>';
        echo '<th>Processed By</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        while($history = mysqli_fetch_array($history_query)) {
            echo '<tr>';
            echo '<td>' . date('d-m-Y H:i', strtotime($history['payment_date'])) . '</td>';
            echo '<td>₹' . number_format($history['amount'], 0) . '</td>';
            echo '<td>' . htmlspecialchars($history['transaction_number']) . '</td>';
            echo '<td>' . htmlspecialchars($history['payment_method']) . '</td>';
            echo '<td>' . htmlspecialchars($history['payment_notes'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($history['processed_by']) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p>No payment history found.</p>';
    }
}
?>



