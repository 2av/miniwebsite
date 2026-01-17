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
        die(json_encode(['success' => false, 'message' => 'Database connection failed']));
    }
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database connection error']));
}

// Include coupon functions
require_once('../../../admin/coupon_functions.php');

// Check if it's an AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => ''];
    
    if ($action === 'apply_promo') {
        if (isset($_POST['promo_code']) && !empty($_POST['promo_code'])) {
            $promo_code = strtoupper(trim($_POST['promo_code']));
            $original_amount = isset($_POST['original_amount']) ? (float)$_POST['original_amount'] : 999;
            
            // Save billing details to session before processing promo code
            if(isset($_POST['gst_number'])) $_SESSION['billing_gst_number'] = $_POST['gst_number'];
            if(isset($_POST['gst_name'])) $_SESSION['billing_gst_name'] = $_POST['gst_name'];
            if(isset($_POST['gst_email'])) $_SESSION['billing_gst_email'] = $_POST['gst_email'];
            if(isset($_POST['gst_contact'])) $_SESSION['billing_gst_contact'] = $_POST['gst_contact'];
            if(isset($_POST['gst_address'])) $_SESSION['billing_gst_address'] = $_POST['gst_address'];
            if(isset($_POST['gst_state'])) $_SESSION['billing_gst_state'] = $_POST['gst_state'];
            if(isset($_POST['gst_city'])) $_SESSION['billing_gst_city'] = $_POST['gst_city'];
            if(isset($_POST['gst_pincode'])) $_SESSION['billing_gst_pincode'] = $_POST['gst_pincode'];
            
            // Debug logging
            error_log("Applying promo code: " . $promo_code . " for amount: " . $original_amount);
            
            // Check service type for franchise registration
            $service_type = isset($_POST['service_type']) ? $_POST['service_type'] : '';
            
            // Debug: Check available coupons
            debugCoupons($connect, $promo_code);
            
            $validation = validateCoupon($promo_code, $connect, $service_type);
            
            // Debug logging
            error_log("Validation result: " . json_encode($validation));
            error_log("Service type: " . $service_type);
            
            if ($validation['valid']) {
                $promo_discount = getCouponDiscount($original_amount, $validation['deal']);
                
                // Validate that discount amount is not greater than original amount
                if ($promo_discount > $original_amount) {
                    $response = [
                        'success' => false,
                        'message' => 'Error: Discount amount (₹' . $promo_discount . ') cannot be greater than original amount (₹' . $original_amount . ')'
                    ];
                } else {
                    $_SESSION['promo_code'] = $promo_code;
                    $_SESSION['promo_discount'] = $promo_discount;
                    unset($_SESSION['auto_applied_promo']); // Clear auto-applied flag for manual application
                    
                    // Store service type in session for franchise registration
                    if ($service_type === 'franchise_registration') {
                        $_SESSION['service_type'] = $service_type;
                    }
                    
                    // Apply the coupon (increment usage count)
                    applyCoupon($promo_code, $connect, $service_type);
                    
                    $subtotal = $original_amount - $promo_discount;
                    
                    // Tax calculation - GST is calculated AFTER discount deduction
                    $company_state_code = '06'; // Haryana state code
                    
                    // Get GST number and state from session (if available)
                    $gst_number = isset($_SESSION['billing_gst_number']) ? $_SESSION['billing_gst_number'] : '';
                    $billing_state = isset($_SESSION['billing_gst_state']) ? $_SESSION['billing_gst_state'] : '';
                    
                    // Determine if interstate transaction
                    $is_interstate = false;
                    
                    if (!empty($gst_number) && strlen($gst_number) == 15 && preg_match('/^\d{15}$/', $gst_number)) {
                        // Extract state code from GST number (positions 1-2)
                        $customer_state_code = substr($gst_number, 0, 2);
                        $is_interstate = ($customer_state_code !== $company_state_code);
                    } else {
                        // GST not filled or invalid: use state field instead
                        $is_interstate = (strtolower($billing_state) !== 'haryana');
                    }
                    
                    // Calculate GST based on interstate/intrastate
                    if ($is_interstate) {
                        // IGST (18%)
                        $igst_amount = round($subtotal * 0.18);
                        $cgst_amount = 0;
                        $sgst_amount = 0;
                    } else {
                        // CGST + SGST (9% each)
                        $cgst_amount = round($subtotal * 0.09);
                        $sgst_amount = round($subtotal * 0.09);
                        $igst_amount = 0;
                    }
                    
                    $total_tax = $cgst_amount + $sgst_amount + $igst_amount;
                    $final_amount = $subtotal + $total_tax;
                    
                    // Ensure final amount doesn't go below zero
                    if ($final_amount < 0) {
                        $final_amount = 0;
                    }
                    
                    $_SESSION['amount'] = $final_amount;
                    
                    // Store all calculated tax values in session for verify.php
                    $_SESSION['subtotal_amount'] = $subtotal;
                    $_SESSION['cgst_amount'] = $cgst_amount;
                    $_SESSION['sgst_amount'] = $sgst_amount;
                    $_SESSION['igst_amount'] = $igst_amount;
                    $_SESSION['final_total'] = $final_amount;
                    $_SESSION['is_interstate'] = $is_interstate;
                    $_SESSION['gst_state_code'] = isset($customer_state_code) ? $customer_state_code : '';
                    
                    // Debug logging
                    error_log("Promo applied successfully. Discount: " . $promo_discount . ", Final amount: " . $final_amount);
                    
                    $response = [
                        'success' => true,
                        'message' => 'Promo code applied successfully!',
                        'promo_code' => $promo_code,
                        'discount_amount' => $promo_discount,
                        'subtotal' => $subtotal,
                        'cgst_amount' => $cgst_amount,
                        'sgst_amount' => $sgst_amount,
                        'igst_amount' => $igst_amount,
                        'total_tax' => $total_tax,
                        'final_amount' => $final_amount,
                        'original_amount' => $original_amount,
                        'is_interstate' => $is_interstate
                    ];
                }
            } else {
                $response = [
                    'success' => false,
                    'message' => $validation['message']
                ];
            }
        } else {
            $response = [
                'success' => false,
                'message' => 'Please enter a promo code'
            ];
        }
    } elseif ($action === 'remove_promo') {
        $original_amount = isset($_POST['original_amount']) ? (float)$_POST['original_amount'] : 999;
        
        // Save billing details to session before removing promo code
        if(isset($_POST['gst_number'])) $_SESSION['billing_gst_number'] = $_POST['gst_number'];
        if(isset($_POST['gst_name'])) $_SESSION['billing_gst_name'] = $_POST['gst_name'];
        if(isset($_POST['gst_email'])) $_SESSION['billing_gst_email'] = $_POST['gst_email'];
        if(isset($_POST['gst_contact'])) $_SESSION['billing_gst_contact'] = $_POST['gst_contact'];
        if(isset($_POST['gst_address'])) $_SESSION['billing_gst_address'] = $_POST['gst_address'];
        if(isset($_POST['gst_state'])) $_SESSION['billing_gst_state'] = $_POST['gst_state'];
        if(isset($_POST['gst_city'])) $_SESSION['billing_gst_city'] = $_POST['gst_city'];
        if(isset($_POST['gst_pincode'])) $_SESSION['billing_gst_pincode'] = $_POST['gst_pincode'];
        
        unset($_SESSION['promo_code']);
        unset($_SESSION['promo_discount']);
        unset($_SESSION['auto_applied_promo']);
        
        $subtotal = $original_amount; // No discount
        
        // Tax calculation - GST is calculated AFTER discount deduction
        $company_state_code = '06'; // Haryana state code
        
        // Get GST number and state from session (if available)
        $gst_number = isset($_SESSION['billing_gst_number']) ? $_SESSION['billing_gst_number'] : '';
        $billing_state = isset($_SESSION['billing_gst_state']) ? $_SESSION['billing_gst_state'] : '';
        
        // Determine if interstate transaction
        $is_interstate = false;
        
        if (!empty($gst_number) && strlen($gst_number) == 15 && preg_match('/^\d{15}$/', $gst_number)) {
            // Extract state code from GST number (positions 1-2)
            $customer_state_code = substr($gst_number, 0, 2);
            $is_interstate = ($customer_state_code !== $company_state_code);
        } else {
            // GST not filled or invalid: use state field instead
            $is_interstate = (strtolower($billing_state) !== 'haryana');
        }
        
        // Calculate GST based on interstate/intrastate
        if ($is_interstate) {
            // IGST (18%)
            $igst_amount = round($subtotal * 0.18);
            $cgst_amount = 0;
            $sgst_amount = 0;
        } else {
            // CGST + SGST (9% each)
            $cgst_amount = round($subtotal * 0.09);
            $sgst_amount = round($subtotal * 0.09);
            $igst_amount = 0;
        }
        
        $total_tax = $cgst_amount + $sgst_amount + $igst_amount;
        $final_amount = $subtotal + $total_tax;
        
        $_SESSION['amount'] = $final_amount;
        
        // Store all calculated tax values in session for verify.php
        $_SESSION['subtotal_amount'] = $subtotal;
        $_SESSION['cgst_amount'] = $cgst_amount;
        $_SESSION['sgst_amount'] = $sgst_amount;
        $_SESSION['igst_amount'] = $igst_amount;
        $_SESSION['final_total'] = $final_amount;
        $_SESSION['is_interstate'] = $is_interstate;
        $_SESSION['gst_state_code'] = isset($customer_state_code) ? $customer_state_code : '';
        
        $response = [
            'success' => true,
            'message' => 'Promo code removed successfully',
            'subtotal' => $subtotal,
            'cgst_amount' => $cgst_amount,
            'sgst_amount' => $sgst_amount,
            'igst_amount' => $igst_amount,
            'total_tax' => $total_tax,
            'final_amount' => $final_amount,
            'original_amount' => $original_amount,
            'is_interstate' => $is_interstate
        ];
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

// Close the database connection
$connect->close();
?>
