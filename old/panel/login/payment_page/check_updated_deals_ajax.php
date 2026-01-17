<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Database connection
$db_host = "p004.bom1.mysecurecloudhost.com";
$db_user = "wwwmoody_miniweb_vcard";
$db_pass = "miniweb_vcard";
$db_name = "miniweb_vcard";

try {
    $connect = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($connect->connect_error) {
        throw new Exception('Database connection failed: ' . $connect->connect_error);
    }
    
    // Include coupon functions
    require_once('../../../admin/coupon_functions.php');
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_updated_deals') {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $service_type = isset($_POST['service_type']) ? trim($_POST['service_type']) : 'franchise_registration';
        
        if (empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Email is required']);
            exit;
        }
        
        // Get franchisee data
        $query = "SELECT referred_by FROM franchisee_login WHERE f_user_email = '" . mysqli_real_escape_string($connect, $email) . "'";
        $result = mysqli_query($connect, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $user_data = mysqli_fetch_assoc($result);
            $referred_by = $user_data['referred_by'];
            
            if (!empty($referred_by)) {
                // Check for the latest mapped deal
                $latest_deal_query = mysqli_query($connect, "SELECT d.* FROM deals d 
                    INNER JOIN deal_customer_mapping dcm ON d.id = dcm.deal_id 
                    WHERE dcm.customer_email = '" . mysqli_real_escape_string($connect, $referred_by) . "' 
                    AND d.deal_status = 'Active' 
                    AND d.plan_type = 'Franchise'
                    ORDER BY dcm.created_date DESC LIMIT 1");
                
                $latest_deal_code = '';
                $latest_discount = 0;
                
                if (mysqli_num_rows($latest_deal_query) > 0) {
                    $latest_deal = mysqli_fetch_array($latest_deal_query);
                    $latest_deal_code = $latest_deal['coupon_code'];
                    
                    // Get the discount amount
                    $validation = validateCoupon($latest_deal_code, $connect, $service_type);
                    if ($validation['valid']) {
                        $original_amount = 30000; // Franchise registration amount
                        $latest_discount = getCouponDiscount($original_amount, $validation['deal']);
                    }
                } else {
                    // Use default deal
                    $latest_deal_code = 'DFRAN101';
                    $validation = validateCoupon($latest_deal_code, $connect, $service_type);
                    if ($validation['valid']) {
                        $original_amount = 30000; // Franchise registration amount
                        $latest_discount = getCouponDiscount($original_amount, $validation['deal']);
                    }
                }
                
                // Compare with current session
                $current_session_promo = isset($_SESSION['promo_code']) ? $_SESSION['promo_code'] : '';
                $current_session_discount = isset($_SESSION['promo_discount']) ? $_SESSION['promo_discount'] : 0;
                
                // Check if deal has been updated
                $deal_updated = false;
                if ($latest_deal_code !== $current_session_promo || $latest_discount !== $current_session_discount) {
                    $deal_updated = true;
                    
                    // Log the update for debugging
                    error_log("Deal update detected for franchisee: " . $email . 
                             " - Old: " . $current_session_promo . " (₹" . $current_session_discount . ")" .
                             " - New: " . $latest_deal_code . " (₹" . $latest_discount . ")");
                }
                
                echo json_encode([
                    'success' => true,
                    'deal_updated' => $deal_updated,
                    'current_deal' => $latest_deal_code,
                    'current_discount' => $latest_discount,
                    'session_deal' => $current_session_promo,
                    'session_discount' => $current_session_discount
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No referral found']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Franchisee not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
    }
    
} catch (Exception $e) {
    error_log("Error in check_updated_deals_ajax.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
