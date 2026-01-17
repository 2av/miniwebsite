<?php
// Prevent any output before JSON
if (ob_get_level()) {
    ob_clean();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable display_errors to prevent output

// Set JSON header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    require('connect_ajax.php');
    
    // Check if user_email is provided
    if(!isset($_POST['user_email']) || empty($_POST['user_email'])) {
        echo json_encode(['success' => false, 'message' => 'User email is required']);
        exit;
    }

    $user_email = mysqli_real_escape_string($connect, $_POST['user_email']);
    
    // Check if franchisee_verification table exists
    $table_check = mysqli_query($connect, "SHOW TABLES LIKE 'franchisee_verification'");
    if(mysqli_num_rows($table_check) == 0) {
        echo json_encode(['success' => false, 'message' => 'Verification table does not exist. Please run the database setup.']);
        exit;
    }
    
    // Query for documents
    $query = mysqli_query($connect, "SELECT pan_card_document, aadhaar_front_document, aadhaar_back_document, status FROM franchisee_verification WHERE user_email = '$user_email' ORDER BY id DESC LIMIT 1");
    
    if(!$query) {
        echo json_encode(['success' => false, 'message' => 'Database query failed: ' . mysqli_error($connect)]);
        exit;
    }
    
    if(mysqli_num_rows($query) > 0) {
        $row = mysqli_fetch_assoc($query);
        echo json_encode([
            'success' => true,
            'pan_card_document' => $row['pan_card_document'],
            'aadhaar_front_document' => $row['aadhaar_front_document'],
            'aadhaar_back_document' => $row['aadhaar_back_document'],
            'status' => $row['status'],
            'debug' => [
                'user_email' => $user_email,
                'query_result' => $row
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'No documents found for user: ' . $user_email,
            'debug' => [
                'user_email' => $user_email,
                'table_exists' => true
            ]
        ]);
    }
    
} catch(Exception $e) {
    error_log('Document verification error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Exception: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
} catch(Error $e) {
    error_log('Document verification error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>
