<?php
require('connect.php');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if(isset($_POST['card_id'])) {
    $card_id = mysqli_real_escape_string($connect, $_POST['card_id']);
    
    // Log the received data
    error_log("Received card_id: " . $card_id);
    
    if(isset($_POST['complimentary_status'])) {
        $new_status = mysqli_real_escape_string($connect, $_POST['complimentary_status']);
        error_log("Received status: " . $new_status);
    } else {
        // Toggle existing status (fallback)
        $query = mysqli_query($connect, "SELECT complimentary_enabled FROM digi_card WHERE id='$card_id'");
        $row = mysqli_fetch_array($query);
        $new_status = ($row['complimentary_enabled'] == 'Yes') ? 'No' : 'Yes';
        error_log("Toggled status to: " . $new_status);
    }
    
    // Check if record exists first
    $check_query = mysqli_query($connect, "SELECT id, complimentary_enabled FROM digi_card WHERE id='$card_id'");
    if(mysqli_num_rows($check_query) == 0) {
        echo "error: Card ID not found";
        exit;
    }
    
    $current_row = mysqli_fetch_array($check_query);
    error_log("Current status in DB: " . $current_row['complimentary_enabled']);
    
    // Update database
    $update_query = "UPDATE digi_card SET complimentary_enabled='$new_status' WHERE id='$card_id'";
    error_log("Update query: " . $update_query);
    
    $update = mysqli_query($connect, $update_query);
    
    if($update) {
        // Verify the update worked
        $verify_query = mysqli_query($connect, "SELECT complimentary_enabled FROM digi_card WHERE id='$card_id'");
        $verify_row = mysqli_fetch_array($verify_query);
        error_log("Status after update: " . $verify_row['complimentary_enabled']);
        
        echo "success - Updated to: " . $verify_row['complimentary_enabled'];
    } else {
        error_log("MySQL Error: " . mysqli_error($connect));
        echo "error: " . mysqli_error($connect);
    }
} else {
    echo "error: No card_id provided";
}
?>



