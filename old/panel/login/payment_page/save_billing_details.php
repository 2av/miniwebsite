<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
$db_host = "p004.bom1.mysecurecloudhost.com";
$db_user = "wwwmoody_miniweb_vcard";
$db_pass = "miniweb_vcard";
$db_name = "miniweb_vcard";

// Create database connection
try {
    $connect = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($connect->connect_error) {
        http_response_code(500);
        header('Content-Type: application/json');
        die(json_encode(['success' => false, 'message' => 'Connection failed: ' . $connect->connect_error]));
    }
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'message' => 'Database connection error: ' . $e->getMessage()]));
}

// Check if form data is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $card_id = isset($_POST['card_id']) ? mysqli_real_escape_string($connect, $_POST['card_id']) : '';
    $gst_number = isset($_POST['gst_number']) ? mysqli_real_escape_string($connect, $_POST['gst_number']) : '';
    $gst_name = isset($_POST['gst_name']) ? mysqli_real_escape_string($connect, $_POST['gst_name']) : '';
    $gst_email = isset($_POST['gst_email']) ? mysqli_real_escape_string($connect, $_POST['gst_email']) : '';
    $gst_contact = isset($_POST['gst_contact']) ? mysqli_real_escape_string($connect, $_POST['gst_contact']) : '';
    $gst_address = isset($_POST['gst_address']) ? mysqli_real_escape_string($connect, $_POST['gst_address']) : '';
    $gst_state = isset($_POST['gst_state']) ? mysqli_real_escape_string($connect, $_POST['gst_state']) : '';
    $gst_city = isset($_POST['gst_city']) ? mysqli_real_escape_string($connect, $_POST['gst_city']) : '';
    $gst_pincode = isset($_POST['gst_pincode']) ? mysqli_real_escape_string($connect, $_POST['gst_pincode']) : '';
    
    // Check if card_id is provided
    if (empty($card_id)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: Card ID is required']);
        exit;
    }
    
    // First, check if the columns exist in the table
    $check_columns_query = "SHOW COLUMNS FROM digi_card LIKE 'd_gst%'";
    $columns_result = $connect->query($check_columns_query);
    
    // If columns don't exist, add them
    if ($columns_result->num_rows < 8) {
        $alter_table_queries = [
            "ALTER TABLE digi_card ADD COLUMN IF NOT EXISTS d_gst VARCHAR(50) DEFAULT NULL",
            "ALTER TABLE digi_card ADD COLUMN IF NOT EXISTS d_gst_name VARCHAR(100) DEFAULT NULL",
            "ALTER TABLE digi_card ADD COLUMN IF NOT EXISTS d_gst_email VARCHAR(100) DEFAULT NULL",
            "ALTER TABLE digi_card ADD COLUMN IF NOT EXISTS d_gst_contact VARCHAR(20) DEFAULT NULL",
            "ALTER TABLE digi_card ADD COLUMN IF NOT EXISTS d_gst_address VARCHAR(255) DEFAULT NULL",
            "ALTER TABLE digi_card ADD COLUMN IF NOT EXISTS d_gst_state VARCHAR(50) DEFAULT NULL",
            "ALTER TABLE digi_card ADD COLUMN IF NOT EXISTS d_gst_city VARCHAR(50) DEFAULT NULL",
            "ALTER TABLE digi_card ADD COLUMN IF NOT EXISTS d_gst_pincode VARCHAR(20) DEFAULT NULL"
        ];
        
        foreach ($alter_table_queries as $query) {
            $connect->query($query);
        }
    }
    
    // Update the digi_card table with billing details
    $update_query = "UPDATE digi_card SET 
        d_gst = '$gst_number',
        d_gst_name = '$gst_name',
        d_gst_email = '$gst_email',
        d_gst_contact = '$gst_contact',
        d_gst_address = '$gst_address',
        d_gst_state = '$gst_state',
        d_gst_city = '$gst_city',
        d_gst_pincode = '$gst_pincode'
        WHERE id = '$card_id'";
    
    if ($connect->query($update_query) === TRUE) {
        // Store the card ID in session for the payment process
        $_SESSION['card_id'] = $card_id;
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Billing details saved successfully']);
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error updating record: ' . $connect->error]);
    }
} else {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// Close the database connection
$connect->close();
?>