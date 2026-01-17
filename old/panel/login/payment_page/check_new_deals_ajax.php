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

try {
    $connect = new mysqli($db_host, $db_user, $db_pass, $db_name);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Include coupon functions
require_once('../../../admin/coupon_functions.php');

// Set JSON header
header('Content-Type: application/json');

// Check if it's an AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => '', 'new_deal_applied' => false];
    
    if ($action === 'check_new_deals') {
        if (isset($_POST['card_id']) && isset($_POST['user_email'])) {
            $card_id = mysqli_real_escape_string($connect, $_POST['card_id']);
            $user_email = mysqli_real_escape_string($connect, $_POST['user_email']);
            
            // Get card details
            $card_query = mysqli_query($connect, "SELECT * FROM digi_card WHERE id='$card_id' AND user_email='$user_email'");
            if (mysqli_num_rows($card_query) == 1) {
                $card_row = mysqli_fetch_array($card_query);
                $status = $card_row['d_payment_status'];
                
                // Get customer details
                $customer_query = mysqli_query($connect, "SELECT referred_by FROM customer_login WHERE user_email='$user_email'");
                if (mysqli_num_rows($customer_query) == 1) {
                    $customer_row = mysqli_fetch_array($customer_query);
                    $referred_by = $customer_row['referred_by'];
                    
                    // Check if customer was created from referral and this is first payment
                    $is_referral_customer = !empty($referred_by);
                    $is_first_payment = ($status == "Created" || $status != "Success");
                    
                    if ($is_referral_customer && $is_first_payment) {
                        // Check if promo is already applied in session
                        $promo_applied = isset($_SESSION['promo_code']) && !empty($_SESSION['promo_code']);
                        
                        // Check if we've already processed this card for auto-promo
                        $refresh_check_key = 'promo_check_' . $user_email . '_' . $card_id;
                        $already_checked = isset($_SESSION[$refresh_check_key]);
                        
                        if (!$promo_applied && !$already_checked) {
                            // Get original amount
                            $original_amount = 999; // Default amount
                            if (isset($card_row['d_payment_amount']) && $card_row['d_payment_amount'] > 0) {
                                $original_amount = $card_row['d_payment_amount'];
                            }
                            
                            // Check for newly mapped deals (created in last hour) - mapped to referral code
                            $new_deal_query = mysqli_query($connect, "SELECT d.* FROM deals d 
                                INNER JOIN deal_customer_mapping dcm ON d.id = dcm.deal_id 
                                WHERE dcm.customer_email = '$referred_by' 
                                AND d.deal_status = 'Active' 
                                AND d.plan_type = 'MiniWebsite'
                                AND dcm.created_date > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                                ORDER BY dcm.created_date DESC LIMIT 1");
                            
                            if (mysqli_num_rows($new_deal_query) > 0) {
                                $new_deal = mysqli_fetch_array($new_deal_query);
                                $new_promo_code = $new_deal['coupon_code'];
                                
                                // Validate and apply the new promocode
                                $validation = validateCoupon($new_promo_code, $connect, 'card_payment');
                                if ($validation['valid']) {
                                    $new_discount = getCouponDiscount($original_amount, $validation['deal']);
                                    if ($new_discount > 0 && $new_discount <= $original_amount) {
                                        $_SESSION['promo_code'] = $new_promo_code;
                                        $_SESSION['promo_discount'] = $new_discount;
                                        $_SESSION['auto_applied_promo'] = true; // Mark as auto-applied
                                        
                                        // Apply the coupon (increment usage count)
                                        applyCoupon($new_promo_code, $connect, 'card_payment');
                                        
                                        // Mark as checked to prevent duplicate applications
                                        $_SESSION[$refresh_check_key] = true;
                                        
                                        $response = [
                                            'success' => true, 
                                            'message' => 'New referral promocode ' . $new_promo_code . ' applied automatically!',
                                            'new_deal_applied' => true,
                                            'promo_code' => $new_promo_code,
                                            'discount' => $new_discount
                                        ];
                                        
                                        // Log the auto-application for debugging
                                        error_log("AJAX: Auto-applied new referral promocode: " . $new_promo_code . " for customer: " . $user_email . " with discount: " . $new_discount);
                                    }
                                }
                            } else {
                                // Check for any existing mapped deals that might not have been applied - mapped to referral code
                                $existing_deal_query = mysqli_query($connect, "SELECT d.* FROM deals d 
                                    INNER JOIN deal_customer_mapping dcm ON d.id = dcm.deal_id 
                                    WHERE dcm.customer_email = '$referred_by' 
                                    AND d.deal_status = 'Active' 
                                    AND d.plan_type = 'MiniWebsite'
                                    ORDER BY d.id DESC LIMIT 1");
                                
                                if (mysqli_num_rows($existing_deal_query) > 0) {
                                    $existing_deal = mysqli_fetch_array($existing_deal_query);
                                    $existing_promo_code = $existing_deal['coupon_code'];
                                    
                                    // Debug logging
                                    error_log("AJAX: Found mapped deal for referral: " . $referred_by . " - Deal: " . $existing_promo_code);
                                    
                                    // Validate and apply the existing promocode
                                    $validation = validateCoupon($existing_promo_code, $connect, 'card_payment');
                                    if ($validation['valid']) {
                                        $existing_discount = getCouponDiscount($original_amount, $validation['deal']);
                                        if ($existing_discount > 0 && $existing_discount <= $original_amount) {
                                            $_SESSION['promo_code'] = $existing_promo_code;
                                            $_SESSION['promo_discount'] = $existing_discount;
                                            $_SESSION['auto_applied_promo'] = true; // Mark as auto-applied
                                            
                                            // Apply the coupon (increment usage count)
                                            applyCoupon($existing_promo_code, $connect, 'card_payment');
                                            
                                            // Mark as checked to prevent duplicate applications
                                            $_SESSION[$refresh_check_key] = true;
                                            
                                            $response = [
                                                'success' => true, 
                                                'message' => 'Referral promocode ' . $existing_promo_code . ' applied automatically!',
                                                'new_deal_applied' => true,
                                                'promo_code' => $existing_promo_code,
                                                'discount' => $existing_discount
                                            ];
                                            
                                            // Log the auto-application for debugging
                                            error_log("AJAX: Auto-applied existing referral promocode: " . $existing_promo_code . " for customer: " . $user_email . " with discount: " . $existing_discount);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    
    echo json_encode($response);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
