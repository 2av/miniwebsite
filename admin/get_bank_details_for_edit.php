<?php
require_once(__DIR__ . '/../app/config/database.php');

header('Content-Type: application/json');

if(isset($_GET['user_email'])) {
    $user_email = mysqli_real_escape_string($connect, $_GET['user_email']);
    
    // Get bank details
    $bank_query = mysqli_query($connect, "SELECT * FROM user_bank_details WHERE user_email='$user_email'");
    
    if(mysqli_num_rows($bank_query) > 0) {
        $bank_details = mysqli_fetch_assoc($bank_query);
        echo json_encode([
            'success' => true,
            'bank_details' => $bank_details
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'bank_details' => [
                'account_holder_name' => '',
                'account_number' => '',
                'ifsc_code' => '',
                'bank_name' => '',
                'upi_id' => '',
                'upi_name' => ''
            ]
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'User email is required'
    ]);
}
?>



