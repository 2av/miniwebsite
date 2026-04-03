<?php
require_once __DIR__ . '/../app/config/database.php';

// Get payment details from URL parameters
$ref = $_GET['ref'] ?? '';
$payment_id = $_GET['payment_id'] ?? '';

// Get payment details from database
$payment_details = null;
if (!empty($payment_id)) {
    $query = "SELECT ujdm.*, jd.deal_name, jd.deal_code 
              FROM user_joining_deals_mapping ujdm 
              JOIN joining_deals jd ON ujdm.joining_deal_id = jd.id 
              WHERE ujdm.transaction_id = '" . mysqli_real_escape_string($connect, $payment_id) . "' 
              LIMIT 1";
    
    $result = mysqli_query($connect, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $payment_details = mysqli_fetch_assoc($result);
    }

    echo '<meta http-equiv="refresh" content="3;URL=download_receipt.php?ref=' . $_SESSION['reference_number'] . '&payment_id=' . $payment_id . '">';
}
?>
 

