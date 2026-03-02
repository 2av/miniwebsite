<?php
// Suppress any output before JSON
ob_start();

// Temporarily suppress error display (we'll handle errors ourselves)
$old_error_reporting = error_reporting(E_ALL);
$old_display_errors = ini_get('display_errors');
ini_set('display_errors', 0);

// Include centralized configs
require_once(__DIR__ . '/../app/config/database.php');
require_once(__DIR__ . '/../app/config/payment.php');

// Restore error settings (payment.php might have changed them)
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'])) {
    $amount = (float)$_POST['amount'];
    
    // Check if Razorpay SDK exists
    $razorpay_path = __DIR__ . '/razorpay-php/Razorpay.php';
    if (!file_exists($razorpay_path)) {
        ob_end_clean(); // Clear any output
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Razorpay SDK not found']);
        exit;
    }
    
    require_once($razorpay_path);
    
    try {
        $keyId = 'rzp_live_xU57a1JhH7To1G';
        $keySecret = 'VHJzQnCxqF5HTNsE3LUTZtnI';
        $api = new \Razorpay\Api\Api($keyId, $keySecret);
        
        // Check if logged-in user is a shared email ID
        $shared_emails = [
            'cdmwkr@yopmail.com',      // Customer
            'ccdmwkr@yopmail.com',     // Collaborator
            'tdmwkr@yopmail.com',      // Team
            'fdmwkr@yopmail.com'       // Franchisee
        ];
        
        $user_email = isset($_SESSION['user_email']) ? strtolower(trim($_SESSION['user_email'])) : '';
        $is_shared_email = in_array($user_email, array_map('strtolower', $shared_emails));
        
        // For shared emails, charge only 1 rupee; for others, charge actual amount
        $payment_amount = $is_shared_email ? 1 : $amount;
        
        $orderData = [
            'receipt' => isset($_SESSION['reference_number']) ? $_SESSION['reference_number'] : rand(100, 9000) . date('dhsi'),
            'amount' => round($payment_amount * 100), // amount in paise
            'currency' => 'INR',
            'payment_capture' => 1
        ];
        
        $razorpayOrder = $api->order->create($orderData);
        $razorpayOrderId = $razorpayOrder['id'];
        $_SESSION['razorpay_order_id'] = $razorpayOrderId;
        $_SESSION['amount'] = $amount; // Store actual amount in session
        $_SESSION['payment_amount'] = $payment_amount; // Store payment amount (1 or actual)
        $_SESSION['is_shared_email'] = $is_shared_email; // Flag for verification
        
        ob_end_clean(); // Clear any output
        echo json_encode([
            'success' => true,
            'order_id' => $razorpayOrderId,
            'amount' => $payment_amount, // Return payment amount (1 for shared emails, actual for others)
            'is_shared_email' => $is_shared_email
        ]);
        exit;
    } catch (Exception $e) {
        ob_end_clean(); // Clear any output
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error creating order: ' . $e->getMessage()
        ]);
        exit;
    }
} else {
    ob_end_clean(); // Clear any output
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}
?>
