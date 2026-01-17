<?php
require_once(__DIR__ . '/../app/config/database.php');

if(isset($_POST['card_id']) && isset($_POST['card_status'])) {
    $card_id = mysqli_real_escape_string($connect, $_POST['card_id']);
    $new_status = mysqli_real_escape_string($connect, $_POST['card_status']);
    
    // Validate status
    if($new_status != 'Active' && $new_status != 'Inactive') {
        echo "error: Invalid status";
        exit;
    }
    
    // Log the received data
    error_log("Received card_id: " . $card_id . ", status: " . $new_status);
    
    // Check if record exists first
    $check_query = mysqli_query($connect, "SELECT id, d_card_status FROM digi_card WHERE id='$card_id'");
    if(mysqli_num_rows($check_query) == 0) {
        echo "error: Card ID not found";
        exit;
    }
    
    $current_row = mysqli_fetch_array($check_query);
    error_log("Current status in DB: " . $current_row['d_card_status']);
    
    // Update database
    $update_query = "UPDATE digi_card SET d_card_status='$new_status' WHERE id='$card_id'";
    error_log("Update query: " . $update_query);
    
    $update = mysqli_query($connect, $update_query);
    
    if($update) {
        // Verify the update worked
        $verify_query = mysqli_query($connect, "SELECT d_card_status FROM digi_card WHERE id='$card_id'");
        $verify_row = mysqli_fetch_array($verify_query);
        error_log("Status after update: " . $verify_row['d_card_status']);
        
        echo "success - Updated to: " . $verify_row['d_card_status'];
    } else {
        error_log("MySQL Error: " . mysqli_error($connect));
        echo "error: " . mysqli_error($connect);
    }
} else {
    echo "error: Missing card_id or card_status";
}
?>


