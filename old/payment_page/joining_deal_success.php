<?php
session_start();

// Database connection
$db_host = "p004.bom1.mysecurecloudhost.com";
$db_user = "wwwmoody_miniweb_vcard";
$db_pass = "miniweb_vcard";
$db_name = "miniweb_vcard";

try {
    $connect = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($connect->connect_error) {
        die("Connection failed: " . $connect->connect_error);
    }
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

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
 