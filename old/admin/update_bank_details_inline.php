<?php
require('connect.php');

// Debug: Log the received data
error_log("Bank details update request received");
error_log("POST data: " . print_r($_POST, true));

if(isset($_POST['update_bank_details'])) {
    $user_email = mysqli_real_escape_string($connect, $_POST['user_email']);
    $account_holder_name = mysqli_real_escape_string($connect, $_POST['account_holder_name']);
    $account_number = mysqli_real_escape_string($connect, $_POST['account_number']);
    $ifsc_code = mysqli_real_escape_string($connect, $_POST['ifsc_code']);
    $bank_name = mysqli_real_escape_string($connect, $_POST['bank_name']);
    $upi_id = mysqli_real_escape_string($connect, $_POST['upi_id']);
    $upi_name = mysqli_real_escape_string($connect, $_POST['upi_name']);
    
    // Check if bank details exist
    $check_query = mysqli_query($connect, "SELECT * FROM user_bank_details WHERE user_email='$user_email'");
    
    if(!$check_query) {
        echo "error: Check query failed - " . mysqli_error($connect);
        exit;
    }
    
    if(mysqli_num_rows($check_query) > 0) {
        // Update existing record
        $update_query = mysqli_query($connect, "UPDATE user_bank_details SET 
            account_holder_name='$account_holder_name',
            account_number='$account_number',
            ifsc_code='$ifsc_code',
            bank_name='$bank_name',
            upi_id='$upi_id',
            upi_name='$upi_name',
            updated_at=NOW()
            WHERE user_email='$user_email'");
    } else {
        // Insert new record
        $update_query = mysqli_query($connect, "INSERT INTO user_bank_details 
            (user_email, account_holder_name, account_number, ifsc_code, bank_name, upi_id, upi_name, created_at, updated_at) 
            VALUES 
            ('$user_email', '$account_holder_name', '$account_number', '$ifsc_code', '$bank_name', '$upi_id', '$upi_name', NOW(), NOW())");
    }
    
    if($update_query) {
        echo "success";
    } else {
        echo "error: " . mysqli_error($connect);
    }
} else {
    echo "error: Invalid request - update_bank_details not set";
}
?>
