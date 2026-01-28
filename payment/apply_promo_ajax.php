<?php
// Include centralized configs
require_once(__DIR__ . '/../app/config/database.php');
require_once(__DIR__ . '/../admin/coupon_functions.php');

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
            
            $service_type = isset($_POST['service_type']) ? $_POST['service_type'] : 'card_payment';
            
            $validation = validateCoupon($promo_code, $connect, $service_type);
            
            if ($validation['valid']) {
                $promo_discount = getCouponDiscount($original_amount, $validation['deal']);
                
                if ($promo_discount > $original_amount) {
                    $response = [
                        'success' => false,
                        'message' => 'Error: Discount amount (₹' . $promo_discount . ') cannot be greater than original amount (₹' . $original_amount . ')'
                    ];
                } else {
                    $_SESSION['promo_code'] = $promo_code;
                    $_SESSION['promo_discount'] = $promo_discount;
                    unset($_SESSION['auto_applied_promo']);
                    
                    if ($service_type === 'franchise_registration') {
                        $_SESSION['service_type'] = $service_type;
                    }
                    
                    applyCoupon($promo_code, $connect, $service_type);
                    
                    $subtotal = $original_amount - $promo_discount;
                    
                    // Tax calculation
                    $company_state_code = '06';
                    $gst_number = isset($_SESSION['billing_gst_number']) ? $_SESSION['billing_gst_number'] : '';
                    $billing_state = isset($_SESSION['billing_gst_state']) ? $_SESSION['billing_gst_state'] : '';
                    $is_interstate = false;
                    
                    if (!empty($gst_number) && strlen($gst_number) == 15 && preg_match('/^\d{2}[A-Z0-9]{13}$/', $gst_number)) {
                        $customer_state_code = substr($gst_number, 0, 2);
                        $is_interstate = ($customer_state_code !== $company_state_code);
                    } else {
                        $billing_state_lower = strtolower(trim($billing_state));
                        $is_interstate = !in_array($billing_state_lower, ['haryana', 'hariyana']);
                    }
                    
                    if ($is_interstate) {
                        $igst_amount = round($subtotal * 0.18, 2);
                        $cgst_amount = 0;
                        $sgst_amount = 0;
                    } else {
                        $cgst_amount = round($subtotal * 0.09, 2);
                        $sgst_amount = round($subtotal * 0.09, 2);
                        $igst_amount = 0;
                    }
                    
                    $total_tax = $cgst_amount + $sgst_amount + $igst_amount;
                    $final_amount = $subtotal + $total_tax;
                    
                    if ($final_amount < 0) {
                        $final_amount = 0;
                    }
                    
                    $_SESSION['amount'] = $final_amount;
                    $_SESSION['subtotal_amount'] = $subtotal;
                    $_SESSION['cgst_amount'] = $cgst_amount;
                    $_SESSION['sgst_amount'] = $sgst_amount;
                    $_SESSION['igst_amount'] = $igst_amount;
                    $_SESSION['final_total'] = $final_amount;
                    $_SESSION['is_interstate'] = $is_interstate;
                    $_SESSION['gst_state_code'] = isset($customer_state_code) ? $customer_state_code : '';
                    
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
        
        // Save billing details to session
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
        
        $subtotal = $original_amount;
        
        // Tax calculation
        $company_state_code = '06';
        $gst_number = isset($_SESSION['billing_gst_number']) ? $_SESSION['billing_gst_number'] : '';
        $billing_state = isset($_SESSION['billing_gst_state']) ? $_SESSION['billing_gst_state'] : '';
        $is_interstate = false;
        
        if (!empty($gst_number) && strlen($gst_number) == 15 && preg_match('/^\d{2}[A-Z0-9]{13}$/', $gst_number)) {
            $customer_state_code = substr($gst_number, 0, 2);
            $is_interstate = ($customer_state_code !== $company_state_code);
        } else {
            $billing_state_lower = strtolower(trim($billing_state));
            $is_interstate = !in_array($billing_state_lower, ['haryana', 'hariyana']);
        }
        
        if ($is_interstate) {
            $igst_amount = round($subtotal * 0.18, 2);
            $cgst_amount = 0;
            $sgst_amount = 0;
        } else {
            $cgst_amount = round($subtotal * 0.09, 2);
            $sgst_amount = round($subtotal * 0.09, 2);
            $igst_amount = 0;
        }
        
        $total_tax = $cgst_amount + $sgst_amount + $igst_amount;
        $final_amount = $subtotal + $total_tax;
        
        $_SESSION['amount'] = $final_amount;
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
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
} else {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
