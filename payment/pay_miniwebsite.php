<?php
// Suppress deprecation warnings and notices for PHP 8.1+ compatibility with old Razorpay SDK
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', 0); // Hide errors from users, log them instead
ini_set('display_startup_errors', 0);

// Include centralized configs
require_once(__DIR__ . '/../app/config/database.php');
require_once(__DIR__ . '/../app/config/payment.php');

// Include coupon functions
require_once(__DIR__ . '/../admin/coupon_functions.php');

// Initialize promo variables at the start
$promo_discount = 0;
$promo_message = '';
$promo_applied = false;
$is_auto_applied = false;

// Initialize promo variables from session if they exist
if(isset($_SESSION['promo_code']) && isset($_SESSION['promo_discount'])) {
    $promo_applied = true;
    $promo_discount = $_SESSION['promo_discount'];
    $is_auto_applied = isset($_SESSION['auto_applied_promo']) && $_SESSION['auto_applied_promo'] === true;
    $promo_message = '<div class="promo-success">Promo code applied successfully! Discount: ₹' . $promo_discount . '</div>';
} else {
    $promo_applied = false;
    $promo_discount = 0;
    $promo_message = '';
    $is_auto_applied = false;
}

// Helper: capture plan details from posted franchise plan key for invoice storage.
function mw_store_invoice_plan_meta_from_post() {
    $selected_plan = isset($_POST['selected_plan']) ? trim((string) $_POST['selected_plan']) : '';
    $plan_meta = [
        'starter' => ['Starter Franchise Plan', '4 Months'],
        'full' => ['Full Franchise Plan', 'Lifetime'],
    ];
    if (isset($plan_meta[$selected_plan])) {
        $_SESSION['invoice_plan_name'] = $plan_meta[$selected_plan][0];
        $_SESSION['invoice_plan_validity'] = $plan_meta[$selected_plan][1];
    }
}
 

// Process form data if coming from franchise_agreement.php
if ($_POST) {
    mw_store_invoice_plan_meta_from_post();
    $_SESSION['gst_number'] = $_POST['gst_number'] ?? '';
    $_SESSION['user_name'] = $_POST['name'] ?? '';
    $_SESSION['user_email'] = $_POST['email'] ?? '';
    $_SESSION['user_contact'] = $_POST['contact'] ?? '';
    $_SESSION['address'] = $_POST['address'] ?? '';
    $_SESSION['state'] = $_POST['state'] ?? '';
    $_SESSION['city'] = $_POST['city'] ?? '';
    $_SESSION['pincode'] = $_POST['pincode'] ?? '';
    $_SESSION['amount'] = $_POST['amount'] ?? 35400;
    $_SESSION['original_amount'] = $_POST['original_amount'] ?? 30000;
    $_SESSION['discount_amount'] = $_POST['discount_amount'] ?? 0;
    $_SESSION['subtotal_amount'] = $_POST['subtotal_amount'] ?? 30000;
    $_SESSION['cgst_amount'] = $_POST['cgst_amount'] ?? 2700;
    $_SESSION['sgst_amount'] = $_POST['sgst_amount'] ?? 2700;
    $_SESSION['igst_amount'] = $_POST['igst_amount'] ?? 0;
    $_SESSION['final_total'] = $_POST['final_total'] ?? 35400;
    $_SESSION['promo_code'] = $_POST['promo_code'] ?? '';
    $_SESSION['promo_discount'] = $_POST['promo_discount'] ?? 0;
    $_SESSION['service_type'] = $_POST['service_type'] ?? 'franchise_registration';
    $_SESSION['reference_number'] = 'FRAN'.rand(1000,9999).date('dmYHis');
    
    // Store franchise registration data
    $_SESSION['franchise_registration_data'] = array(
        'name' => $_POST['name'],
        'email' => $_POST['email'],
        'password' => $_POST['password'] ?? '123456',
        'contact' => $_POST['contact'],
        'address' => $_POST['address'],
        'state' => $_POST['state'],
        'city' => $_POST['city'],
        'pincode' => $_POST['pincode'],
        'gst_number' => $_POST['gst_number'] ?? '',
        'referral_code' => $_POST['referral_code'] ?? '',
        'referred_by' => $_POST['referred_by'] ?? ''
    );
    $_SESSION['franchise_password'] = $_POST['password'] ?? '123456';
    $_SESSION['referral_code'] = $_POST['referral_code'] ?? '';
    $_SESSION['referred_by'] = $_POST['referred_by'] ?? '';
}

// Ensure we have required session data
if (!isset($_SESSION['reference_number'])) {
    $_SESSION['reference_number'] = 'FRAN'.rand(1000,9999).date('dmYHis');
}

// Check if Razorpay SDK exists - use local razorpay-php folder
$razorpay_path = __DIR__ . '/razorpay-php/Razorpay.php';

if (!file_exists($razorpay_path)) {
    echo '<div style="color: red; padding: 20px; background: #ffeeee; border: 1px solid #ffcccc; margin: 20px; border-radius: 5px; font-family: Arial, sans-serif; text-align: center;">
        <h2>Payment SDK Error</h2>
        <p>The payment processing library is missing. Please contact the administrator.</p>
        <p><a href="' . (isset($_GET['id']) ? '../user/dashboard' : '../franchise_agreement.php') . '" style="display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-top: 15px;">Go Back</a></p>
    </div>';
    exit;
}

require_once($razorpay_path);
use Razorpay\Api\Api;

// Get Razorpay credentials from config
$keyId = 'rzp_live_xU57a1JhH7To1G';
$keySecret = 'VHJzQnCxqF5HTNsE3LUTZtnI';
$displayCurrency = 'INR';

// Create Razorpay API instance
$api = new Api($keyId, $keySecret);

$is_referred_by_team = false;
$use_team_500_pricing = false;

// Check if this is a customer payment (with id parameter)
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $_SESSION['miniwebsite_team_plan_eligible'] = false;
    // Customer payment flow - fetch digi_card details
    $card_id = mysqli_real_escape_string($connect, $_GET['id']);
    $query = mysqli_query($connect, "SELECT * FROM digi_card WHERE id='$card_id' LIMIT 1");
    
    if ($query && mysqli_num_rows($query) == 1) {
        $row = mysqli_fetch_array($query);
        $status = $row['d_payment_status'];
        
        // Check if payment is already done
        if ($status == "Success") {
            echo '<div style="color: green; padding: 20px; background: #eeffee; border: 1px solid #ccffcc; margin: 20px; border-radius: 5px; font-family: Arial, sans-serif; text-align: center;">
                <h2>Payment Already Completed</h2>
                <p>This payment has already been processed successfully.</p>
                <p><a href="../user/dashboard" style="display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-top: 15px;">Go to Dashboard</a></p>
            </div>';
            exit;
        }
        
        // Get customer contact and referral info
        $user_email_lower = strtolower(trim($row['user_email']));
        $customer_query = mysqli_query($connect, "SELECT phone as user_contact, referred_by FROM user_details WHERE LOWER(TRIM(email))='$user_email_lower' LIMIT 1");
        $contactno = "";
        $referred_by = "";
        $is_referred_by_team = false;
        if ($customer_query && mysqli_num_rows($customer_query) > 0) {
            $customer_row = mysqli_fetch_array($customer_query);
            $contactno = $customer_row['user_contact'] ?? '';
            $referred_by = $customer_row['referred_by'] ?? '';
            
            // Check if referred_by is a team member
            if (!empty($referred_by)) {
                $rb_esc = mysqli_real_escape_string($connect, $referred_by);
                $referrer_query = mysqli_query($connect, "SELECT role FROM user_details WHERE email='$rb_esc' LIMIT 1");
                if ($referrer_query && mysqli_num_rows($referrer_query) > 0) {
                    $referrer_row = mysqli_fetch_array($referrer_query);
                    $referrer_type = $referrer_row['role'] ?? '';
                    if ($referrer_type === 'TEAM') {
                        $is_referred_by_team = true;
                    }
                }
            }
        }
        
        $is_team_source = isset($_GET['source']) && $_GET['source'] === 'team';
        $use_team_500_pricing = $is_team_source || $is_referred_by_team;
        $_SESSION['miniwebsite_team_plan_eligible'] = $use_team_500_pricing;
        $is_direct_customer = empty(trim((string) $referred_by));

        // No auto-applied referral promo for team / team-link payments, or stale auto session for direct signups
        if (!empty($_SESSION['auto_applied_promo']) && ($use_team_500_pricing || $is_direct_customer)) {
            unset($_SESSION['promo_code'], $_SESSION['promo_discount'], $_SESSION['auto_applied_promo']);
            $promo_applied = false;
            $promo_discount = 0;
            $is_auto_applied = false;
            $promo_message = '';
        }

        // Determine payment amount (team / team-referred customers default to ₹500)
        if (isset($row['user_email']) && ($row['user_email'] == 'ajeetcreative93@gmail.com' || $row['user_email'] == 'akhilesh@yopmail.com')) {
            $original_amount = 3; // Test account
        } else if ($use_team_500_pricing) {
            $original_amount = 500;
        } else if (isset($row['d_payment_amount']) && $row['d_payment_amount'] > 0) {
            $stored_amt = (float) $row['d_payment_amount'];
            // ₹500 / 6-month plan is team-only; normal users minimum is 1-year
            $original_amount = ($stored_amt == 500) ? 847 : $stored_amt;
        } else {
            $original_amount = 847; // Default: 1-year plan for normal users
        }
        
        // Set session variables
        $_SESSION['reference_number'] = rand(100, 9000) . date('dhsi');
        $_SESSION['user_name'] = ($row['d_f_name'] ?? '') . ' ' . ($row['d_l_name'] ?? '');
        $_SESSION['user_email'] = $row['user_email'];
        $_SESSION['user_contact'] = $contactno;
        $_SESSION['card_id'] = $card_id;
        $_SESSION['service_type'] = 'card_payment';
        $_SESSION['payment_card_id'] = $card_id;
        $_SESSION['payment_user_email'] = $row['user_email'];
        
        // Check for referral auto-promo: only non-team referrers (direct signup and team-referred never auto-apply DMW001)
        $is_referral_customer = !empty($referred_by);
        $is_first_payment = ($status == "Created" || $status != "Success");
        
        if ($is_referral_customer && !$is_referred_by_team && $is_first_payment && !$promo_applied && !$is_team_source) {
            // Try to auto-apply DMW001 or mapped deal
            $auto_promo_code = 'DMW001';
            $validation = validateCoupon($auto_promo_code, $connect, 'card_payment');
            if ($validation['valid']) {
                $auto_discount = getCouponDiscount($original_amount, $validation['deal']);
                if ($auto_discount > 0 && $auto_discount <= $original_amount) {
                    $_SESSION['promo_code'] = $auto_promo_code;
                    $_SESSION['promo_discount'] = $auto_discount;
                    $_SESSION['auto_applied_promo'] = true;
                    $promo_applied = true;
                    $promo_discount = $auto_discount;
                    $is_auto_applied = true;
                    $promo_message = '<div class="promo-success">Referral promocode ' . $auto_promo_code . ' applied automatically! Discount: ₹' . $auto_discount . '</div>';
                    applyCoupon($auto_promo_code, $connect, 'card_payment');
                }
            }
        }
        
        // Calculate tax (will be updated when billing details are filled)
        $discount_amount = $promo_applied ? $promo_discount : 0;
        $subtotal = $original_amount - $discount_amount;
        
        // Initial tax calculation (default to intrastate - CGST+SGST)
        $gst_number = isset($_SESSION['billing_gst_number']) ? $_SESSION['billing_gst_number'] : '';
        $billing_state = isset($_SESSION['billing_gst_state']) ? $_SESSION['billing_gst_state'] : '';
        $company_state_code = '06'; // Haryana
        $is_interstate = false;
        
        if (!empty($gst_number) && strlen($gst_number) == 15 && preg_match('/^\d{2}[A-Z0-9]{13}$/', $gst_number)) {
            $customer_state_code = substr($gst_number, 0, 2);
            $is_interstate = ($customer_state_code !== $company_state_code);
        } else if (!empty($billing_state)) {
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
        
        // Store in session
        $_SESSION['amount'] = $final_amount;
        $_SESSION['subtotal_amount'] = $subtotal;
        $_SESSION['cgst_amount'] = $cgst_amount;
        $_SESSION['sgst_amount'] = $sgst_amount;
        $_SESSION['igst_amount'] = $igst_amount;
        $_SESSION['final_total'] = $final_amount;
        $_SESSION['is_interstate'] = $is_interstate;
        $_SESSION['original_amount'] = $original_amount;
    } else {
        echo '<div style="color: red; padding: 20px; background: #ffeeee; border: 1px solid #ffcccc; margin: 20px; border-radius: 5px; font-family: Arial, sans-serif; text-align: center;">
            <h2>Error</h2>
            <p>Card not found. Please check the payment link.</p>
            <p><a href="../user/dashboard" style="display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-top: 15px;">Go to Dashboard</a></p>
        </div>';
        exit;
    }
} else {
    // Franchise registration payment flow
    // Process form data if coming from franchise_agreement.php
    if ($_POST) {
        mw_store_invoice_plan_meta_from_post();
        $_SESSION['gst_number'] = $_POST['gst_number'] ?? '';
        $_SESSION['user_name'] = $_POST['name'] ?? '';
        $_SESSION['user_email'] = $_POST['email'] ?? '';
        $_SESSION['user_contact'] = $_POST['contact'] ?? '';
        $_SESSION['address'] = $_POST['address'] ?? '';
        $_SESSION['state'] = $_POST['state'] ?? '';
        $_SESSION['city'] = $_POST['city'] ?? '';
        $_SESSION['pincode'] = $_POST['pincode'] ?? '';
        $_SESSION['amount'] = $_POST['amount'] ?? 35400;
        $_SESSION['original_amount'] = $_POST['original_amount'] ?? 30000;
        $_SESSION['discount_amount'] = $_POST['discount_amount'] ?? 0;
        $_SESSION['subtotal_amount'] = $_POST['subtotal_amount'] ?? 30000;
        $_SESSION['cgst_amount'] = $_POST['cgst_amount'] ?? 2700;
        $_SESSION['sgst_amount'] = $_POST['sgst_amount'] ?? 2700;
        $_SESSION['igst_amount'] = $_POST['igst_amount'] ?? 0;
        $_SESSION['final_total'] = $_POST['final_total'] ?? 35400;
        $_SESSION['promo_code'] = $_POST['promo_code'] ?? '';
        $_SESSION['promo_discount'] = $_POST['promo_discount'] ?? 0;
        $_SESSION['service_type'] = $_POST['service_type'] ?? 'franchise_registration';
        $_SESSION['reference_number'] = 'FRAN'.rand(1000,9999).date('dmYHis');
        
        // Store franchise registration data
        $_SESSION['franchise_registration_data'] = array(
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'password' => $_POST['password'] ?? '123456',
            'contact' => $_POST['contact'],
            'address' => $_POST['address'],
            'state' => $_POST['state'],
            'city' => $_POST['city'],
            'pincode' => $_POST['pincode'],
            'gst_number' => $_POST['gst_number'] ?? '',
            'referral_code' => $_POST['referral_code'] ?? '',
            'referred_by' => $_POST['referred_by'] ?? ''
        );
        $_SESSION['franchise_password'] = $_POST['password'] ?? '123456';
        $_SESSION['referral_code'] = $_POST['referral_code'] ?? '';
        $_SESSION['referred_by'] = $_POST['referred_by'] ?? '';
    }
    
    // Ensure we have required session data for franchise registration
    if (!isset($_SESSION['reference_number'])) {
        $_SESSION['reference_number'] = 'FRAN'.rand(1000,9999).date('dmYHis');
    }

    $franchise_ref = isset($_SESSION['reference_number']) && is_string($_SESSION['reference_number'])
        && strncmp($_SESSION['reference_number'], 'FRAN', 4) === 0;
    if (!isset($_SESSION['franchise_registration_data'])
        && (($_SESSION['service_type'] ?? '') === 'franchise_registration' || $franchise_ref)) {
        if (!empty($_SESSION['user_email']) && !empty($_SESSION['user_name'])) {
            $_SESSION['franchise_registration_data'] = [
                'name' => $_SESSION['user_name'],
                'email' => $_SESSION['user_email'],
                'password' => $_SESSION['franchise_password'] ?? '123456',
                'contact' => $_SESSION['user_contact'] ?? '',
                'address' => $_SESSION['address'] ?? '',
                'state' => $_SESSION['state'] ?? '',
                'city' => $_SESSION['city'] ?? '',
                'pincode' => $_SESSION['pincode'] ?? '',
                'gst_number' => $_SESSION['gst_number'] ?? '',
                'referral_code' => $_SESSION['referral_code'] ?? '',
                'referred_by' => $_SESSION['referred_by'] ?? '',
            ];
        }
    }
    
    // For franchise, use values from session
    $original_amount = $_SESSION['original_amount'] ?? 30000;
    $discount_amount = $_SESSION['discount_amount'] ?? 0;
    $subtotal = $_SESSION['subtotal_amount'] ?? 30000;
    $cgst_amount = $_SESSION['cgst_amount'] ?? 2700;
    $sgst_amount = $_SESSION['sgst_amount'] ?? 2700;
    $igst_amount = $_SESSION['igst_amount'] ?? 0;
    $final_amount = $_SESSION['final_total'] ?? 35400;
    
    // Update promo status from session
    if (isset($_SESSION['promo_code']) && isset($_SESSION['promo_discount'])) {
        $promo_applied = true;
        $promo_discount = $_SESSION['promo_discount'];
        $is_auto_applied = isset($_SESSION['auto_applied_promo']) && $_SESSION['auto_applied_promo'] === true;
    }
}

// Only show billing form if payment is not already completed (for customer payments)
$show_billing_form = true;
if (isset($_GET['id']) && isset($status) && $status == "Success") {
    $show_billing_form = false;
}

// Validate required fields
if (!isset($_SESSION['amount']) || !isset($_SESSION['user_name']) || !isset($_SESSION['user_email'])) {
    $back_url = (isset($_GET['id']) ? '../user/dashboard' : '../franchise_agreement.php');
    echo '<div style="color: red; padding: 20px; background: #ffeeee; border: 1px solid #ffcccc; margin: 20px; border-radius: 5px; font-family: Arial, sans-serif; text-align: center;">
        <h2>Error</h2>
        <p>Required information is missing. Please fill all required fields.</p>
        <p><a href="' . $back_url . '" style="display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-top: 15px;">Go Back</a></p>
    </div>';
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta name='viewport' content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0' />
    <title><?php echo (isset($_GET['id']) ? 'Customer Payment' : 'Franchise Registration Payment'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        :root {
            --mw-navy: #002169;
            --mw-yellow: #ffc107;
            --mw-light-blue: #f0f5ff;
            --mw-green: #4CAF50;
            --mw-orange: #FF9800;
            --mw-text: #333333;
            --mw-text-muted: #666666;
            --mw-border: #e0e0e0;
        }
        html {
            overflow-x: hidden;
            background: #f5f5f5;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
            color: var(--mw-text);
        }
        .back-button {
            display: inline-flex;
            align-items: center;
            background: var(--mw-navy);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,33,105,0.3);
            margin-bottom: 20px;
        }
        .back-button:hover {
            background: #001a4d;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,33,105,0.4);
        }
        .back-wrap {
            text-align: center;
            margin-bottom: 20px;
        }
        .payment-wrapper {
            max-width: 480px;
            margin: 0 auto 24px;
        }
        .payment-header {
            background: var(--mw-navy);
            padding: 28px 20px 48px;
        }
        .payment-header-top {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            margin: 0 auto 12px;
            width: fit-content;
            max-width: 100%;
        }
        .payment-header-icon {
            width: 52px;
            height: 52px;
            background: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .payment-header-icon i {
            font-size: 24px;
            color: var(--mw-navy);
        }
        .payment-header-text {
            flex: 0 1 auto;
            min-width: 0;
        }
        .payment-header h1 {
            color: #fff;
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 8px;
            letter-spacing: 0.3px;
            text-align: left;
        }
        .payment-header-line {
            width: 48px;
            height: 3px;
            background: var(--mw-yellow);
            margin: 0;
            border-radius: 2px;
        }
        .payment-header p {
            color: rgba(255,255,255,0.9);
            font-size: 13px;
            margin: 0;
            line-height: 1.5;
            text-align: center;
        }
        .payment-card {
            background: #fff;
            margin: -17px 0px 0;
            padding: 24px 18px 20px;
            border-radius: 20px 20px 12px 12px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.1);
        }
        .form-input {
            width: 100%;
            padding: 13px 15px;
            margin-bottom: 12px;
            border: 1px solid var(--mw-border);
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            background: #fff;
            color: var(--mw-text);
        }
        .form-input::placeholder {
            color: #999;
            font-size: 13px;
        }
        #gst_number::placeholder {
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.3px;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--mw-navy);
            box-shadow: 0 0 0 2px rgba(0,33,105,0.1);
        }
        .form-row {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
        }
        .form-row .form-input {
            margin-bottom: 0;
        }
        .section-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 28px 0 18px;
        }
        .section-divider::before,
        .section-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--mw-border);
        }
        .section-divider span {
            font-size: 12px;
            font-weight: 700;
            color: var(--mw-text-muted);
            letter-spacing: 1px;
            white-space: nowrap;
        }
        .plan-label {
            display: flex;
            align-items: flex-start;
            background: #fff;
            padding: 14px 14px;
            border-radius: 10px;
            border: 1.5px solid var(--mw-border);
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 10px;
        }
        .plan-label:hover {
            border-color: #b0b8d0;
        }
        .plan-label.plan-selected {
            background: var(--mw-light-blue);
            border-color: var(--mw-navy);
            border-width: 2px;
        }
        .plan-label input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            margin: 3px 12px 0 0;
            accent-color: var(--mw-navy);
            flex-shrink: 0;
        }
        .plan-content {
            flex: 1;
            min-width: 0;
        }
        .plan-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
        }
        .plan-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--mw-text);
            display: block;
        }
        .plan-label.plan-selected .plan-title {
            color: var(--mw-navy);
        }
        .plan-sub {
            font-size: 12px;
            color: var(--mw-text-muted);
            font-weight: 400;
            display: block;
            margin-top: 2px;
        }
        .plan-price {
            font-size: 16px;
            font-weight: 700;
            color: var(--mw-text);
            white-space: nowrap;
            margin-left: auto;
            text-align: right;
            align-self: flex-start;
            flex-shrink: 0;
        }
        .plan-label.plan-selected .plan-price {
            color: var(--mw-navy);
        }
        .plan-price-orange {
            color: var(--mw-orange) !important;
        }
        .plan-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            color: #fff;
            margin-top: 8px;
        }
        .plan-badge-green {
            background: var(--mw-green);
        }
        .plan-badge-orange {
            background: var(--mw-orange);
        }
        .plan-save {
            font-size: 11px;
            color: var(--mw-green);
            margin-top: 4px;
            display: block;
        }
        .calculation-display {
            margin: 20px 0;
            background: var(--mw-light-blue);
            padding: 16px;
            border-radius: 10px;
            border: 1px solid rgba(0,33,105,0.15);
        }
        .calculation-display table {
            width: 100%;
            font-size: 14px;
            color: var(--mw-text);
            border-collapse: collapse;
        }
        .calculation-display td {
            padding: 6px 0;
        }
        .calculation-display td:last-child {
            text-align: right;
            font-weight: 600;
        }
        .calculation-display tr:last-child td {
            border-top: 1px dashed rgba(0,33,105,0.25);
        }
        .calculation-display td.final-total {
            padding-top: 12px;
            font-size: 18px;
            font-weight: 800;
            color: var(--mw-navy);
        }
        .promo-wrap {
            display: flex;
            gap: 0;
            margin-top: 16px;
            border: 1px solid var(--mw-border);
            border-radius: 8px;
            overflow: hidden;
        }
        .promo-icon-wrap {
            display: flex;
            align-items: center;
            padding: 0 12px;
            background: #fff;
            color: var(--mw-navy);
            border-right: 1px solid var(--mw-border);
        }
        .promo-wrap input {
            flex: 1;
            padding: 11px 12px;
            border: none;
            font-size: 14px;
            outline: none;
        }
        .promo-wrap button {
            padding: 11px 18px;
            background: var(--mw-navy);
            color: #fff;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .promo-applied-box {
            margin-bottom: 10px;
            padding: 10px 12px;
            background: #e8f5e9;
            border-radius: 8px;
            font-size: 14px;
        }
        .promo-applied-box .promo-code-text {
            color: var(--mw-green);
            font-weight: 700;
        }
        .promo-success {
            background: #d4edda;
            color: #155724;
            padding: 8px;
            border-radius: 4px;
            font-size: 13px;
            margin-top: 5px;
        }
        .promo-error {
            background: #f8d7da;
            color: #721c24;
            padding: 8px;
            border-radius: 4px;
            font-size: 13px;
            margin-top: 5px;
        }
        .terms-wrap {
            margin: 18px 0 16px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 12px;
            line-height: 1.5;
            color: var(--mw-navy);
        }
        .terms-wrap label {
            color: var(--mw-navy);
        }
        .terms-wrap input[type="checkbox"] {
            margin-top: 3px;
            width: 16px;
            height: 16px;
            accent-color: var(--mw-navy);
            flex-shrink: 0;
        }
        .terms-wrap a {
            color: var(--mw-navy);
            text-decoration: underline;
            font-weight: 600;
        }
        .proceed-btn {
            width: 100%;
            background: var(--mw-yellow);
            color: #000;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            letter-spacing: 0.5px;
        }
        .proceed-btn:hover {
            background: #e6ac00;
        }
        .proceed-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        .trust-badges {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 18px;
            padding-top: 14px;
        }
        .trust-badge {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 10px;
            color: var(--mw-navy);
            font-weight: 500;
            flex: 1;
            min-width: 90px;
            justify-content: center;
        }
        .trust-badge i {
            font-size: 14px;
            opacity: 0.8;
        }
        @media (max-width: 380px) {
            .trust-badges {
                flex-direction: column;
                align-items: center;
            }
            .trust-badge {
                min-width: auto;
            }
        }
    </style>
</head>
<body>

<?php
$back_url = (isset($_GET['id']) ? '../user/dashboard' : '../franchise_agreement.php');
?>
<div class="back-wrap">
    <a href="<?php echo $back_url; ?>" class="back-button">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;">
            <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
        Back to Dashboard
    </a>
</div>

<?php if ($show_billing_form): ?>
<div class="payment-wrapper">
    <div class="payment-header">
        <div class="payment-header-top">
            <div class="payment-header-icon">
                <i class="fa fa-file-text-o"></i>
            </div>
            <div class="payment-header-text">
                <h1>Billing &amp; GST Details</h1>
                <div class="payment-header-line"></div>
            </div>
        </div>
        <p>Please fill in your details to proceed with the payment.</p>
    </div>

    <div class="payment-card">
        <div class="billing-form">
            <input type="text" id="gst_number" name="gst_number" class="form-input" placeholder="ENTER GST NUMBER (OPTIONAL)"
                   value="<?php echo isset($_SESSION['billing_gst_number']) ? htmlspecialchars($_SESSION['billing_gst_number']) : ''; ?>"
                   onkeyup="updateTaxOnInput()" onchange="updateTaxOnInput()" style="text-transform: uppercase;">

            <input type="text" id="gst_name" name="gst_name" class="form-input" placeholder="Name"
                   value="<?php echo isset($_SESSION['billing_gst_name']) ? htmlspecialchars($_SESSION['billing_gst_name']) : (isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : ''); ?>"
                   required>

            <input type="email" id="gst_email" name="gst_email" class="form-input" placeholder="Email Address"
                   value="<?php echo isset($_SESSION['billing_gst_email']) ? htmlspecialchars($_SESSION['billing_gst_email']) : (isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : ''); ?>"
                   required>

            <input type="tel" id="gst_contact" name="gst_contact" class="form-input" placeholder="Contact Number"
                   value="<?php echo isset($_SESSION['billing_gst_contact']) ? htmlspecialchars($_SESSION['billing_gst_contact']) : (isset($_SESSION['user_contact']) ? htmlspecialchars($_SESSION['user_contact']) : ''); ?>"
                   required>

            <input type="text" id="gst_address" name="gst_address" class="form-input" placeholder="Address"
                   value="<?php echo isset($_SESSION['billing_gst_address']) ? htmlspecialchars($_SESSION['billing_gst_address']) : (isset($_SESSION['address']) ? htmlspecialchars($_SESSION['address']) : ''); ?>"
                   required>

            <div class="form-row">
                <input type="text" id="gst_state" name="gst_state" class="form-input" placeholder="State"
                       value="<?php echo isset($_SESSION['billing_gst_state']) ? htmlspecialchars($_SESSION['billing_gst_state']) : (isset($_SESSION['state']) ? htmlspecialchars($_SESSION['state']) : ''); ?>"
                       onkeyup="updateTaxOnInput()" onchange="updateTaxOnInput()" required style="width: 50%;">
                <input type="text" id="gst_city" name="gst_city" class="form-input" placeholder="City"
                       value="<?php echo isset($_SESSION['billing_gst_city']) ? htmlspecialchars($_SESSION['billing_gst_city']) : (isset($_SESSION['city']) ? htmlspecialchars($_SESSION['city']) : ''); ?>"
                       required style="width: 50%;">
            </div>

            <input type="text" id="gst_pincode" name="gst_pincode" class="form-input" placeholder="Pin Code"
                   value="<?php echo isset($_SESSION['billing_gst_pincode']) ? htmlspecialchars($_SESSION['billing_gst_pincode']) : (isset($_SESSION['pincode']) ? htmlspecialchars($_SESSION['pincode']) : ''); ?>"
                   required>

            <div class="section-divider"><span>CHOOSE YOUR PLAN</span></div>

            <div class="plan-list">
                <?php if (!empty($use_team_500_pricing)): ?>
                <label class="plan-label" id="label_team500">
                    <input type="radio" name="plan_choice" value="plan_team500" id="plan_team500" data-amount="500"
                           <?php echo isset($_GET['id']) ? 'checked' : ''; ?>>
                    <div class="plan-content">
                        <div class="plan-row">
                            <div>
                                <span class="plan-title">6 Months</span>
                                <span class="plan-sub">Rs 83/month approx.</span>
                            </div>
                            <span class="plan-price">₹ 500</span>
                        </div>
                    </div>
                </label>
                <?php elseif (!isset($_GET['id'])): ?>
                <label class="plan-label" id="label_6month">
                    <input type="radio" name="plan_choice" value="plan_6month" id="plan_6month" data-amount="500" checked>
                    <div class="plan-content">
                        <div class="plan-row">
                            <div>
                                <span class="plan-title">6 Months</span>
                                <span class="plan-sub">Rs 83/month approx.</span>
                            </div>
                            <span class="plan-price">₹ 500</span>
                        </div>
                    </div>
                </label>
                <?php endif; ?>

                <label class="plan-label" id="label_1year">
                    <input type="radio" name="plan_choice" value="plan_1year" id="plan_1year" data-amount="847"
                           <?php echo (isset($_GET['id']) && empty($use_team_500_pricing)) ? 'checked' : ''; ?>>
                    <div class="plan-content">
                        <div class="plan-row">
                            <div>
                                <span class="plan-title">1 Year</span>
                                <span class="plan-sub">Rs 71/month approx.</span>
                            </div>
                            <span class="plan-price">₹ 847</span>
                        </div>
                    </div>
                </label>

                <label class="plan-label" id="label_2year">
                    <input type="radio" name="plan_choice" value="plan_2year" id="plan_2year" data-amount="1500">
                    <div class="plan-content">
                        <div class="plan-row">
                            <div>
                                <span class="plan-title">2 Years</span>
                                <span class="plan-sub">Rs 63/month approx.</span>
                                <span class="plan-badge plan-badge-green"><i class="fa fa-star"></i> BEST VALUE</span>
                                <span class="plan-save">✓ Save 11% compared to 1 year</span>
                            </div>
                            <span class="plan-price">₹ 1,500</span>
                        </div>
                    </div>
                </label>

                <label class="plan-label" id="label_3year">
                    <input type="radio" name="plan_choice" value="plan_3year" id="plan_3year" data-amount="2100">
                    <div class="plan-content">
                        <div class="plan-row">
                            <div>
                                <span class="plan-title">3 Years</span>
                                <span class="plan-sub">Rs 58/month approx.</span>
                                <span class="plan-badge plan-badge-orange"><i class="fa fa-star"></i> MAXIMUM SAVINGS</span>
                            </div>
                            <span class="plan-price plan-price-orange">₹ 2,100</span>
                        </div>
                    </div>
                </label>
            </div>
        </div>

        <div class="calculation-display">
            <table>
                <tr>
                    <td class="original-price">Original Price:</td>
                    <td class="original-price">₹ <?php echo number_format($original_amount, 2); ?></td>
                </tr>
                <tr>
                    <td class="discount">Discount:</td>
                    <td class="discount">₹ <?php echo number_format($discount_amount, 2); ?></td>
                </tr>
                <tr>
                    <td class="subtotal">Sub Total:</td>
                    <td class="subtotal">₹ <?php echo number_format($subtotal, 2); ?></td>
                </tr>
                <tr>
                    <td class="cgst">CGST (9%):</td>
                    <td class="cgst">₹ <?php echo number_format($cgst_amount, 2); ?></td>
                </tr>
                <tr>
                    <td class="sgst">SGST (9%):</td>
                    <td class="sgst">₹ <?php echo number_format($sgst_amount, 2); ?></td>
                </tr>
                <tr>
                    <td class="igst">IGST (18%):</td>
                    <td class="igst">₹ <?php echo number_format($igst_amount, 2); ?></td>
                </tr>
                <tr>
                    <td class="final-total"><strong>Final Total:</strong></td>
                    <td class="final-total"><strong>₹ <?php echo number_format($final_amount, 2); ?></strong></td>
                </tr>
            </table>
        </div>

        <div id="promo-section">
            <?php if(!$promo_applied): ?>
            <div class="promo-wrap">
                <div class="promo-icon-wrap"><i class="fa fa-tag"></i></div>
                <input type="text" id="promo_code_input" placeholder="Enter promo code" maxlength="20">
                <button type="button" id="apply_promo_btn">Apply</button>
            </div>
            <?php else: ?>
            <div class="promo-applied-box">
                <span class="promo-code-text"><?php echo htmlspecialchars($_SESSION['promo_code']); ?> Applied</span>
                <?php if(!$is_auto_applied): ?>
                <button type="button" id="remove_promo_btn"
                        style="padding: 3px 8px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px; margin-left: 10px;">
                    Remove
                </button>
                <?php else: ?>
                <span style="color: #6c757d; font-size: 12px; margin-left: 10px; font-style: italic;">(Auto-applied)</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div id="promo-message"><?php echo $promo_message; ?></div>
        </div>

        <div class="terms-wrap">
            <input type="checkbox" id="terms_agree" name="terms_agree">
            <label for="terms_agree">
                I have read and agree to the
                <a href="../terms_conditions.php" target="_blank">Terms &amp; Conditions</a>,
                <a href="../terms_conditions.php" target="_blank">Refund Policy</a> and
                <a href="../privacy_policy.php" target="_blank">Privacy Policy</a>.
            </label>
        </div>

        <button type="button" id="proceed-to-payment" class="proceed-btn">
            <i class="fa fa-lock"></i> PROCEED TO PAY
        </button>

        <div class="trust-badges">
            <div class="trust-badge"><i class="fa fa-shield"></i> 100% Secure Payments</div>
            <div class="trust-badge"><i class="fa fa-shield"></i> SSL Encrypted</div>
            <div class="trust-badge"><i class="fa fa-shield"></i> Your Data is Safe</div>
        </div>
    </div>
</div>

<?php
// Create Razorpay Order
try {
    $orderData = [
        'receipt' => $_SESSION['reference_number'],
        'amount' => $final_amount * 100, // amount in paise
        'currency' => 'INR',
        'payment_capture' => 1
    ];
    
    $razorpayOrder = $api->order->create($orderData);
    $razorpayOrderId = $razorpayOrder['id'];
    $_SESSION['razorpay_order_id'] = $razorpayOrderId;
    
    $data = [
        "key" => $keyId,
        "amount" => $orderData['amount'],
        "name" => "KIROVA SOLUTIONS LLP",
        "description" => (isset($_GET['id']) ? "Mini Website Payment" : "Franchise Registration"),
        "image" => "",
        "prefill" => [
            "name" => $_SESSION['user_name'],
            "email" => $_SESSION['user_email'],
            "contact" => $_SESSION['user_contact'],
        ],
        "notes" => [
            "address" => isset($_SESSION['address']) ? $_SESSION['address'] : 'NA',
            "merchant_order_id" => $_SESSION['reference_number'],
        ],
        "theme" => [
            "color" => "#002169"
        ],
        "order_id" => $razorpayOrderId,
    ];
    
    $json = json_encode($data);
?>

<!-- Hidden form for Razorpay -->
<form action="verify_miniwebsite.php" method="POST" name="razorpayform" style="display:none;">
    <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
    <input type="hidden" name="razorpay_signature" id="razorpay_signature">
    <input type="hidden" name="razorpay_order_id" id="razorpay_order_id" value="<?php echo $razorpayOrderId; ?>">
    <input type="hidden" name="shopping_order_id" value="<?php echo $_SESSION['reference_number']; ?>">
</form>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
function updatePlanBorderColor() {
    document.querySelectorAll('.plan-label').forEach(function(label) {
        label.classList.remove('plan-selected');
    });
    var checkedRadio = document.querySelector('input[name="plan_choice"]:checked');
    if (checkedRadio) {
        var labelId = 'label_' + checkedRadio.id.replace('plan_', '');
        var labelEl = document.getElementById(labelId);
        if (labelEl) {
            labelEl.classList.add('plan-selected');
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updatePlanBorderColor();
    document.querySelectorAll('input[name="plan_choice"]').forEach(function(radio) {
        radio.addEventListener('change', updatePlanBorderColor);
    });

    var appliedPromoDiscount = <?php echo ($promo_applied && isset($promo_discount)) ? floatval($promo_discount) : 0; ?>;
    var hasActivePromo = <?php echo (!empty($promo_applied) && !empty($_SESSION['promo_code'])) ? 'true' : 'false'; ?>;
    var planPricingSeq = 0;
    var serviceType = '<?php echo isset($_SESSION['service_type']) ? addslashes($_SESSION['service_type']) : 'card_payment'; ?>';

    var checkedPlan = document.querySelector('input[name="plan_choice"]:checked');
    var originalAmount = 0;
    var currentDiscount = 0;

    function effectiveDiscountForPlan(planAmount) {
        var d = appliedPromoDiscount;
        if (d <= 0) return 0;
        return Math.min(d, planAmount);
    }

    function applyPlanAndDiscountToUI(planAmount, discount) {
        originalAmount = planAmount;
        currentDiscount = discount;
        updatePriceDisplay(planAmount, discount);
        updateTaxCalculation(planAmount, discount);
    }

    /** Recalculate promo discount for new plan (fixed or % coupons) and refresh totals */
    function refreshPricingForPlan(planAmount) {
        if (!(planAmount > 0)) return;

        var seq = ++planPricingSeq;

        if (!hasActivePromo) {
            appliedPromoDiscount = 0;
            applyPlanAndDiscountToUI(planAmount, 0);
            return;
        }

        applyPlanAndDiscountToUI(planAmount, effectiveDiscountForPlan(planAmount));

        var fd = new FormData();
        fd.append('action', 'recalc_promo_for_plan');
        fd.append('plan_amount', String(planAmount));
        fd.append('service_type', serviceType);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'apply_promo_ajax.php', true);
        xhr.onload = function() {
            if (seq !== planPricingSeq) return;
            var disc = effectiveDiscountForPlan(planAmount);
            if (xhr.status === 200) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success && typeof res.discount_amount !== 'undefined') {
                        disc = parseFloat(res.discount_amount);
                        if (isNaN(disc) || disc < 0) disc = 0;
                        appliedPromoDiscount = disc;
                    }
                } catch (e) { /* use fallback below */ }
            }
            applyPlanAndDiscountToUI(planAmount, disc);
        };
        xhr.onerror = function() {
            if (seq !== planPricingSeq) return;
            var disc = effectiveDiscountForPlan(planAmount);
            applyPlanAndDiscountToUI(planAmount, disc);
        };
        xhr.send(fd);
    }
    
    if (checkedPlan) {
        var selectedPlanAmount = parseFloat(checkedPlan.getAttribute('data-amount'));
        if (selectedPlanAmount > 0) {
            refreshPricingForPlan(selectedPlanAmount);
        }
    } else {
        originalAmount = <?php echo isset($original_amount) ? floatval($original_amount) : 0; ?>;
        currentDiscount = effectiveDiscountForPlan(originalAmount);
        if (originalAmount > 0) {
            if (hasActivePromo) {
                refreshPricingForPlan(originalAmount);
            } else {
                applyPlanAndDiscountToUI(originalAmount, 0);
            }
        }
    }
    
    var planRadios = document.querySelectorAll('input[name="plan_choice"]');
    planRadios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            var selectedPlanAmount = parseFloat(this.getAttribute('data-amount'));
            if (selectedPlanAmount > 0) {
                refreshPricingForPlan(selectedPlanAmount);
            }
        });
    });
    
    // Function to update tax calculation
    function updateTaxCalculation(originalAmount, discountAmount) {
        var subtotal = originalAmount - discountAmount;
        
        // Get GST number and state
        var gstNumber = document.getElementById('gst_number') ? document.getElementById('gst_number').value.trim() : '';
        var state = document.getElementById('gst_state') ? document.getElementById('gst_state').value.trim().toLowerCase() : '';
        var companyStateCode = '06'; // Haryana
        
        var isInterstate = false;
        var cgst = 0, sgst = 0, igst = 0;
        
        // Determine if interstate
        if (gstNumber && gstNumber.length === 15 && /^\d{2}[A-Z0-9]{13}$/.test(gstNumber)) {
            var customerStateCode = gstNumber.substring(0, 2);
            isInterstate = (customerStateCode !== companyStateCode);
        } else {
            var stateLower = state.toLowerCase().trim();
            isInterstate = !['haryana', 'hariyana'].includes(stateLower);
        }
        
        // Calculate GST
        if (isInterstate) {
            igst = Math.round(subtotal * 0.18 * 100) / 100;
            cgst = 0;
            sgst = 0;
        } else {
            cgst = Math.round(subtotal * 0.09 * 100) / 100;
            sgst = Math.round(subtotal * 0.09 * 100) / 100;
            igst = 0;
        }
        
        var finalAmount = subtotal + cgst + sgst + igst;
        
        // Update display
        var cgstElements = document.querySelectorAll('.cgst');
        var sgstElements = document.querySelectorAll('.sgst');
        var igstElements = document.querySelectorAll('.igst');
        var finalTotalElements = document.querySelectorAll('.final-total');
        
        if (cgstElements.length >= 2) {
            cgstElements[1].textContent = '₹ ' + cgst.toFixed(2);
        }
        if (sgstElements.length >= 2) {
            sgstElements[1].textContent = '₹ ' + sgst.toFixed(2);
        }
        if (igstElements.length >= 2) {
            igstElements[1].textContent = '₹ ' + igst.toFixed(2);
        }
        if (finalTotalElements.length >= 2) {
            finalTotalElements[1].textContent = '₹ ' + finalAmount.toFixed(2);
        }
        
        // Update subtotal
        var subtotalElements = document.querySelectorAll('.subtotal');
        if (subtotalElements.length >= 2) {
            subtotalElements[1].textContent = '₹ ' + subtotal.toFixed(2);
        }
        
        return finalAmount;
    }
    
    // Update price display when plan changes
    function updatePriceDisplay(planAmount, discountAmount) {
        var subtotal = planAmount - discountAmount;
        
        // Update original price display
        var originalPriceElements = document.querySelectorAll('.original-price');
        if (originalPriceElements.length >= 2) {
            originalPriceElements[1].textContent = '₹ ' + planAmount.toFixed(2);
        }
        
        // Update discount display
        var discountElements = document.querySelectorAll('.discount');
        if (discountElements.length >= 2) {
            discountElements[1].textContent = '₹ ' + discountAmount.toFixed(2);
        }
        
        // Update subtotal display
        var subtotalElements = document.querySelectorAll('.subtotal');
        if (subtotalElements.length >= 2) {
            subtotalElements[1].textContent = '₹ ' + subtotal.toFixed(2);
        }
    }
    
    // Global function for inline events (use selected plan + applied promo, not stale PHP original)
    window.updateTaxOnInput = function() {
        var r = document.querySelector('input[name="plan_choice"]:checked');
        var oa = r ? parseFloat(r.getAttribute('data-amount')) : originalAmount;
        if (!(oa > 0)) {
            oa = <?php echo isset($original_amount) ? floatval($original_amount) : 0; ?>;
        }
        var disc = (typeof appliedPromoDiscount !== 'undefined' && appliedPromoDiscount > 0)
            ? Math.min(appliedPromoDiscount, oa) : 0;
        currentDiscount = disc;
        originalAmount = oa;
        updatePriceDisplay(oa, disc);
        updateTaxCalculation(oa, disc);
    };
    
    // Add event listeners
    var gstInput = document.getElementById('gst_number');
    var stateInput = document.getElementById('gst_state');
    
    if (gstInput) {
        gstInput.addEventListener('input', function() {
            setTimeout(updateTaxOnInput, 300);
        });
        gstInput.addEventListener('change', updateTaxOnInput);
    }
    
    if (stateInput) {
        stateInput.addEventListener('input', function() {
            setTimeout(updateTaxOnInput, 300);
        });
        stateInput.addEventListener('change', updateTaxOnInput);
    }
    
    // Promo code application
    function applyPromoCode() {
        var promoCode = document.getElementById('promo_code_input').value.trim();
        if (!promoCode) {
            alert('Please enter a promo code');
            return;
        }
        
        var applyBtn = document.getElementById('apply_promo_btn');
        applyBtn.disabled = true;
        applyBtn.textContent = 'Applying...';
        
        var formData = new FormData();
        formData.append('action', 'apply_promo');
        formData.append('promo_code', promoCode);
        formData.append('original_amount', originalAmount);
        formData.append('service_type', '<?php echo isset($_SESSION['service_type']) ? $_SESSION['service_type'] : 'card_payment'; ?>');
        
        // Preserve billing details
        formData.append('gst_number', document.getElementById('gst_number').value);
        formData.append('gst_name', document.getElementById('gst_name').value);
        formData.append('gst_email', document.getElementById('gst_email').value);
        formData.append('gst_contact', document.getElementById('gst_contact').value);
        formData.append('gst_address', document.getElementById('gst_address').value);
        formData.append('gst_state', document.getElementById('gst_state').value);
        formData.append('gst_city', document.getElementById('gst_city').value);
        formData.append('gst_pincode', document.getElementById('gst_pincode').value);
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'apply_promo_ajax.php', true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.message);
                    }
                } catch (e) {
                    alert('Error processing response');
                }
            } else {
                alert('Error applying promo code');
            }
            applyBtn.disabled = false;
            applyBtn.textContent = 'Apply';
        };
        xhr.send(formData);
    }
    
    function removePromoCode() {
        var removeBtn = document.getElementById('remove_promo_btn');
        removeBtn.disabled = true;
        removeBtn.textContent = 'Removing...';
        
        var formData = new FormData();
        formData.append('action', 'remove_promo');
        formData.append('original_amount', originalAmount);
        formData.append('service_type', '<?php echo isset($_SESSION['service_type']) ? $_SESSION['service_type'] : 'card_payment'; ?>');
        
        // Preserve billing details
        formData.append('gst_number', document.getElementById('gst_number').value);
        formData.append('gst_name', document.getElementById('gst_name').value);
        formData.append('gst_email', document.getElementById('gst_email').value);
        formData.append('gst_contact', document.getElementById('gst_contact').value);
        formData.append('gst_address', document.getElementById('gst_address').value);
        formData.append('gst_state', document.getElementById('gst_state').value);
        formData.append('gst_city', document.getElementById('gst_city').value);
        formData.append('gst_pincode', document.getElementById('gst_pincode').value);
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'apply_promo_ajax.php', true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.message);
                    }
                } catch (e) {
                    alert('Error processing response');
                }
            }
            removeBtn.disabled = false;
            removeBtn.textContent = 'Remove';
        };
        xhr.send(formData);
    }
    
    // Event listeners for promo buttons
    var applyPromoBtn = document.getElementById('apply_promo_btn');
    if (applyPromoBtn) {
        applyPromoBtn.addEventListener('click', applyPromoCode);
    }
    
    var removePromoBtn = document.getElementById('remove_promo_btn');
    if (removePromoBtn) {
        removePromoBtn.addEventListener('click', removePromoCode);
    }
    
    var promoCodeInput = document.getElementById('promo_code_input');
    if (promoCodeInput) {
        promoCodeInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyPromoCode();
            }
        });
    }
    
    // Proceed to payment button
    var payBtn = document.getElementById('proceed-to-payment');
    if(payBtn) {
        payBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Validation
            var isValid = true;
            var errorMessage = "";
            
            if (!document.getElementById('gst_name').value.trim()) {
                isValid = false;
                errorMessage += "Name is required\n";
                document.getElementById('gst_name').style.border = "2px solid red";
            }
            if (!document.getElementById('gst_email').value.trim()) {
                isValid = false;
                errorMessage += "Email is required\n";
                document.getElementById('gst_email').style.border = "2px solid red";
            }
            if (!document.getElementById('gst_contact').value.trim()) {
                isValid = false;
                errorMessage += "Contact is required\n";
                document.getElementById('gst_contact').style.border = "2px solid red";
            }
            if (!document.getElementById('gst_address').value.trim()) {
                isValid = false;
                errorMessage += "Address is required\n";
                document.getElementById('gst_address').style.border = "2px solid red";
            }
            if (!document.getElementById('gst_state').value.trim()) {
                isValid = false;
                errorMessage += "State is required\n";
                document.getElementById('gst_state').style.border = "2px solid red";
            }
            if (!document.getElementById('gst_city').value.trim()) {
                isValid = false;
                errorMessage += "City is required\n";
                document.getElementById('gst_city').style.border = "2px solid red";
            }
            if (!document.getElementById('gst_pincode').value.trim()) {
                isValid = false;
                errorMessage += "Pin Code is required\n";
                document.getElementById('gst_pincode').style.border = "2px solid red";
            }

            var termsCheckbox = document.getElementById('terms_agree');
            if (termsCheckbox && !termsCheckbox.checked) {
                isValid = false;
                errorMessage += "Please agree to the Terms & Conditions\n";
            }
            
            var selectedPlan = document.querySelector('input[name="plan_choice"]:checked');
            if (!selectedPlan) {
                isValid = false;
                errorMessage += "Please select a plan\n";
            }
            
            if (!isValid) {
                alert(errorMessage);
                return false;
            }
            
            // Save billing details first
            payBtn.disabled = true;
            payBtn.textContent = 'Processing...';
            
            var formData = new FormData();
            formData.append('card_id', '<?php echo isset($_GET['id']) ? $_GET['id'] : ''; ?>');
            formData.append('gst_number', document.getElementById('gst_number').value);
            formData.append('gst_name', document.getElementById('gst_name').value);
            formData.append('gst_email', document.getElementById('gst_email').value);
            formData.append('gst_contact', document.getElementById('gst_contact').value);
            formData.append('gst_address', document.getElementById('gst_address').value);
            formData.append('gst_state', document.getElementById('gst_state').value);
            formData.append('gst_city', document.getElementById('gst_city').value);
            formData.append('gst_pincode', document.getElementById('gst_pincode').value);
            formData.append('plan_choice', document.querySelector('input[name="plan_choice"]:checked').value);
            formData.append('plan_amount', originalAmount); // Send selected plan amount
            
            // Recalculate tax before saving
            var finalAmount = updateTaxCalculation(originalAmount, currentDiscount);
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'save_billing_details.php', true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var responseText = xhr.responseText.trim();
                        if (!responseText) {
                            alert('Error: Empty response from server');
                            payBtn.disabled = false;
                            payBtn.textContent = 'PROCEED TO PAY';
                            return;
                        }
                        var response = JSON.parse(responseText);
                        if (response.success) {
                            // Use final amount from response
                            var finalAmount = response.final_amount || updateTaxCalculation(originalAmount, currentDiscount);
                            payBtn.textContent = 'Opening Payment...';
                            initializeRazorpayWithAmount(finalAmount);
                        } else {
                            alert('Error saving billing details: ' + (response.message || 'Unknown error'));
                            payBtn.disabled = false;
                            payBtn.textContent = 'PROCEED TO PAY';
                        }
                    } catch (e) {
                        console.error('JSON Parse Error:', e);
                        console.error('Response Text:', xhr.responseText);
                        alert('Error processing response: ' + e.message + '\n\nResponse: ' + xhr.responseText.substring(0, 200));
                        payBtn.disabled = false;
                        payBtn.textContent = 'PROCEED TO PAY';
                    }
                } else {
                    alert('Error saving billing details. Status: ' + xhr.status);
                    payBtn.disabled = false;
                    payBtn.textContent = 'PROCEED TO PAY';
                }
            };
            xhr.onerror = function() {
                alert('Network error while saving billing details');
                payBtn.disabled = false;
                payBtn.textContent = 'PROCEED TO PAY';
            };
            xhr.send(formData);
        });
    }
    
    // Initialize Razorpay - create new order with updated amount
    function initializeRazorpayWithAmount(finalAmount) {
        // Create new Razorpay order with updated amount via AJAX
        var createOrderXhr = new XMLHttpRequest();
        createOrderXhr.open('POST', 'create_razorpay_order.php', true);
        createOrderXhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        createOrderXhr.onload = function() {
            if (createOrderXhr.status === 200) {
                try {
                    var responseText = createOrderXhr.responseText.trim();
                    if (!responseText) {
                        alert('Error: Empty response from server');
                        var payBtn = document.getElementById('proceed-to-payment');
                        if (payBtn) {
                            payBtn.disabled = false;
                            payBtn.textContent = 'PROCEED TO PAY';
                        }
                        return;
                    }
                    var orderResponse = JSON.parse(responseText);
                    if (orderResponse.success) {
                        var options = {
                            key: "<?php echo $keyId; ?>",
                            amount: Math.round(orderResponse.amount * 100), // Use payment amount from response
                            name: "KIROVA SOLUTIONS LLP",
                            description: "<?php echo (isset($_GET['id']) ? 'Mini Website Payment' : 'Franchise Registration'); ?>",
                            image: "",
                            order_id: orderResponse.order_id,
                            handler: function (response) {
                                document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id;
                                document.getElementById('razorpay_signature').value = response.razorpay_signature;
                                document.getElementById('razorpay_order_id').value = orderResponse.order_id;
                                document.razorpayform.submit();
                            },
                            prefill: {
                                name: "<?php echo isset($_SESSION['user_name']) ? addslashes($_SESSION['user_name']) : ''; ?>",
                                email: "<?php echo isset($_SESSION['user_email']) ? addslashes($_SESSION['user_email']) : ''; ?>",
                                contact: "<?php echo isset($_SESSION['user_contact']) ? addslashes($_SESSION['user_contact']) : ''; ?>"
                            },
                            notes: {
                                address: "NA",
                                merchant_order_id: "<?php echo $_SESSION['reference_number']; ?>"
                            },
                            theme: {
                                color: "#002169"
                            },
                            modal: {
                                ondismiss: function() {
                                    var payBtn = document.getElementById('proceed-to-payment');
                                    if (payBtn) {
                                        payBtn.disabled = false;
                                        payBtn.textContent = 'PROCEED TO PAY';
                                    }
                                }
                            }
                        };
                        
                        // Update the hidden form field with new order ID
                        var orderIdInput = document.getElementById('razorpay_order_id');
                        if (orderIdInput) {
                            orderIdInput.value = orderResponse.order_id;
                        } else {
                            // Create the input if it doesn't exist
                            var form = document.forms['razorpayform'];
                            if (form) {
                                var newInput = document.createElement('input');
                                newInput.type = 'hidden';
                                newInput.name = 'razorpay_order_id';
                                newInput.id = 'razorpay_order_id';
                                newInput.value = orderResponse.order_id;
                                form.appendChild(newInput);
                            }
                        }
                        
                        var rzp = new Razorpay(options);
                        rzp.on('payment.failed', function (response){
                            alert('Payment failed: ' + response.error.description);
                            var payBtn = document.getElementById('proceed-to-payment');
                            if (payBtn) {
                                payBtn.disabled = false;
                                payBtn.textContent = 'PROCEED TO PAY';
                            }
                        });
                        rzp.open();
                    } else {
                        alert('Error creating payment order: ' + orderResponse.message);
                        var payBtn = document.getElementById('proceed-to-payment');
                        if (payBtn) {
                            payBtn.disabled = false;
                            payBtn.textContent = 'PROCEED TO PAY';
                        }
                    }
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    console.error('Response Text:', createOrderXhr.responseText);
                    alert('Error processing order response: ' + e.message + '\n\nResponse: ' + createOrderXhr.responseText.substring(0, 200));
                    var payBtn = document.getElementById('proceed-to-payment');
                    if (payBtn) {
                        payBtn.disabled = false;
                        payBtn.textContent = 'PROCEED TO PAY';
                    }
                }
            } else {
                alert('Error creating payment order. Status: ' + createOrderXhr.status);
                var payBtn = document.getElementById('proceed-to-payment');
                if (payBtn) {
                    payBtn.disabled = false;
                    payBtn.textContent = 'PROCEED TO PAY';
                }
            }
        };
        createOrderXhr.onerror = function() {
            alert('Network error while creating payment order');
            var payBtn = document.getElementById('proceed-to-payment');
            if (payBtn) {
                payBtn.disabled = false;
                payBtn.textContent = 'PROCEED TO PAY';
            }
        };
        createOrderXhr.send('amount=' + finalAmount);
    }
});
</script>

<?php
} catch (Exception $e) {
    $back_url = (isset($_GET['id']) ? '../user/dashboard' : '../franchise_agreement.php');
    echo '<div style="color: red; padding: 20px; background: #ffeeee; border: 1px solid #ffcccc; margin: 20px; border-radius: 5px;">
        <h2>Payment Error</h2>
        <p>Error creating payment order: ' . htmlspecialchars($e->getMessage()) . '</p>
        <p><a href="' . $back_url . '" style="display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-top: 15px;">Go Back</a></p>
    </div>';
}
?>

<?php else: ?>
    <!-- Payment already completed message -->
    <div style="color: green; padding: 20px; background: #eeffee; border: 1px solid #ccffcc; margin: 20px auto; max-width: 500px; border-radius: 5px; font-family: Arial, sans-serif; text-align: center;">
        <h2>Payment Already Completed</h2>
        <p>This payment has already been processed successfully.</p>
        <p><a href="../user/dashboard" style="display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-top: 15px;">Go to Dashboard</a></p>
    </div>
<?php endif; ?>

</body>
</html>









