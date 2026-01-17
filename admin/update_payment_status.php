<?php
/**
 * Manual Payment Status Update Script
 * Use this to update payment status when payment is completed but status is still pending
 */

require_once(__DIR__ . '/../app/config/database.php');

// Get the payment details from URL parameters
$ref = $_GET['ref'] ?? '';
$payment_id = $_GET['payment_id'] ?? '';

if (empty($ref) || empty($payment_id)) {
    echo "Error: Missing reference or payment ID";
    exit;
}

echo "<h2>Payment Status Update</h2>";
echo "<p><strong>Reference:</strong> $ref</p>";
echo "<p><strong>Payment ID:</strong> $payment_id</p>";

// Find the user by reference - using user_details
$user_query = mysqli_query($connect, "SELECT * FROM user_details WHERE (email LIKE '%$ref%' OR email = '$ref') AND role='CUSTOMER' LIMIT 1");

if (!$user_query || mysqli_num_rows($user_query) == 0) {
    echo "<p style='color: red;'>Error: User not found with reference: $ref</p>";
    exit;
}

$user = mysqli_fetch_array($user_query);
echo "<p><strong>User Found:</strong> " . $user['user_email'] . "</p>";

// Find the joining deal mapping for this user
$joining_deal_query = mysqli_query($connect, "SELECT ujdm.*, jd.deal_name 
    FROM user_joining_deals_mapping ujdm 
    JOIN joining_deals jd ON ujdm.joining_deal_id = jd.id 
    WHERE ujdm.user_email = '" . $user['user_email'] . "' 
    AND ujdm.mapping_status = 'ACTIVE' 
    ORDER BY ujdm.created_at DESC LIMIT 1");

if (!$joining_deal_query || mysqli_num_rows($joining_deal_query) == 0) {
    echo "<p style='color: red;'>Error: No active joining deal found for user</p>";
    exit;
}

$joining_deal = mysqli_fetch_array($joining_deal_query);
echo "<p><strong>Joining Deal:</strong> " . $joining_deal['deal_name'] . "</p>";
echo "<p><strong>Current Status:</strong> " . $joining_deal['payment_status'] . "</p>";

// Update the payment status
$update_query = "UPDATE user_joining_deals_mapping SET 
    payment_status = 'PAID',
    transaction_id = '$payment_id',
    payment_date = NOW(),
    amount_paid = " . $joining_deal['total_fees'] . "
    WHERE id = " . $joining_deal['id'];

if (mysqli_query($connect, $update_query)) {
    echo "<p style='color: green; font-weight: bold;'>✅ Payment status updated successfully!</p>";
    echo "<p>Status changed from 'PENDING' to 'PAID'</p>";
    echo "<p>Transaction ID: $payment_id</p>";
    echo "<p>Payment Date: " . date('Y-m-d H:i:s') . "</p>";
    echo "<p>Amount Paid: ₹" . number_format($joining_deal['total_fees'], 2) . "</p>";
    
    // Also update any invoice details if they exist
    $invoice_query = mysqli_query($connect, "SELECT * FROM invoice_details 
        WHERE user_email = '" . $user['user_email'] . "' 
        AND service_name = 'Franchisee Registration' 
        ORDER BY id DESC LIMIT 1");
    
    if ($invoice_query && mysqli_num_rows($invoice_query) > 0) {
        $invoice = mysqli_fetch_array($invoice_query);
        $invoice_update = mysqli_query($connect, "UPDATE invoice_details SET 
            payment_status = 'Success',
            transaction_id = '$payment_id',
            payment_date = NOW()
            WHERE id = " . $invoice['id']);
        
        if ($invoice_update) {
            echo "<p style='color: green;'>✅ Invoice payment status also updated</p>";
        }
    }
    
    echo "<br><p><a href='manage_franchisee_distributor.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>← Back to Management</a></p>";
    
} else {
    echo "<p style='color: red;'>❌ Error updating payment status: " . mysqli_error($connect) . "</p>";
}
?>



