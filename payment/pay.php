<?php
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

// Check if Razorpay SDK exists
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

// Get Razorpay credentials
$keyId = 'rzp_live_xU57a1JhH7To1G';
$keySecret = 'VHJzQnCxqF5HTNsE3LUTZtnI';
$displayCurrency = 'INR';

// Create Razorpay API instance
$api = new Api($keyId, $keySecret);

// Check if this is a customer payment (with id parameter)
if (isset($_GET['id']) && !empty($_GET['id'])) {
    // Customer payment flow
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
        if ($customer_query && mysqli_num_rows($customer_query) > 0) {
            $customer_row = mysqli_fetch_array($customer_query);
            $contactno = $customer_row['user_contact'] ?? '';
            $referred_by = $customer_row['referred_by'] ?? '';
        }
        
        // Determine payment amount
        if (isset($row['user_email']) && ($row['user_email'] == 'ajeetcreative93@gmail.com' || $row['user_email'] == 'akhilesh@yopmail.com')) {
            $original_amount = 3; // Test account
        } else if (isset($row['d_payment_amount']) && $row['d_payment_amount'] > 0) {
            $original_amount = $row['d_payment_amount'];
        } else {
            $original_amount = 847; // Default amount
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
        
        // Check for referral auto-promo (simplified version)
        $is_referral_customer = !empty($referred_by);
        $is_first_payment = ($status == "Created" || $status != "Success");
        
        if ($is_referral_customer && $is_first_payment && !$promo_applied) {
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
    if ($_POST) {
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
    }
    
    if (!isset($_SESSION['reference_number'])) {
        $_SESSION['reference_number'] = 'FRAN'.rand(1000,9999).date('dmYHis');
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

// Only show billing form if payment is not already completed (for customer payments)
$show_billing_form = true;
if (isset($_GET['id']) && isset($status) && $status == "Success") {
    $show_billing_form = false;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta name='viewport' content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0' />
    <title><?php echo (isset($_GET['id']) ? 'Customer Payment' : 'Franchise Registration Payment'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        html {
            overflow-x: hidden;
            background: #f5f5f5;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .back-button {
            display: inline-flex;
            align-items: center;
            background: #002169;
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
        .billing-form input:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(255, 193, 7, 0.3);
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
    </style>
</head>
<body>

<div style="text-align: center; margin-bottom: 20px;">
    <?php
    $back_url = (isset($_GET['id']) ? '../user/dashboard' : '../franchise_agreement.php');
    ?>
    <a href="<?php echo $back_url; ?>" class="back-button">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;">
            <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
        Back to Dashboard
    </a>
</div>

<?php if ($show_billing_form): ?>
<!-- Billing Details Section -->
<div style="max-width: 450px; margin: 0 auto; background: #002169; padding: 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
    <h4 style="color: white; text-align: center; margin-bottom: 10px; font-size: 20px; font-weight: 600;">Billing/GST Details</h4>
    
    <!-- Yellow line below header -->
    <div style="width: 35%; height: 2px; background: #ffc107; margin: 0 auto 25px auto; border-radius: 1px;"></div>
    
    <!-- Billing Form -->
    <div class="billing-form">
        <input type="text" id="gst_number" name="gst_number" placeholder="ENTER GST NUMBER (OPTIONAL)" 
               value="<?php echo isset($_SESSION['billing_gst_number']) ? htmlspecialchars($_SESSION['billing_gst_number']) : ''; ?>" 
               onkeyup="updateTaxOnInput()" onchange="updateTaxOnInput()" 
               style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box; text-transform: uppercase;">
        
        <input type="text" id="gst_name" name="gst_name" placeholder="Name" 
               value="<?php echo isset($_SESSION['billing_gst_name']) ? htmlspecialchars($_SESSION['billing_gst_name']) : (isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : ''); ?>" 
               required style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
        
        <input type="email" id="gst_email" name="gst_email" placeholder="Email Address" 
               value="<?php echo isset($_SESSION['billing_gst_email']) ? htmlspecialchars($_SESSION['billing_gst_email']) : (isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : ''); ?>" 
               required style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
        
        <input type="tel" id="gst_contact" name="gst_contact" placeholder="Contact Number" 
               value="<?php echo isset($_SESSION['billing_gst_contact']) ? htmlspecialchars($_SESSION['billing_gst_contact']) : (isset($_SESSION['user_contact']) ? htmlspecialchars($_SESSION['user_contact']) : ''); ?>" 
               required style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
        
        <input type="text" id="gst_address" name="gst_address" placeholder="Address" 
               value="<?php echo isset($_SESSION['billing_gst_address']) ? htmlspecialchars($_SESSION['billing_gst_address']) : (isset($_SESSION['address']) ? htmlspecialchars($_SESSION['address']) : ''); ?>" 
               required style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
        
        <div style="display: flex; gap: 15px; margin-bottom: 15px;">
            <input type="text" id="gst_state" name="gst_state" placeholder="State" 
                   value="<?php echo isset($_SESSION['billing_gst_state']) ? htmlspecialchars($_SESSION['billing_gst_state']) : (isset($_SESSION['state']) ? htmlspecialchars($_SESSION['state']) : ''); ?>" 
                   onkeyup="updateTaxOnInput()" onchange="updateTaxOnInput()" 
                   required style="width: 50%; padding: 12px 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
            <input type="text" id="gst_city" name="gst_city" placeholder="City" 
                   value="<?php echo isset($_SESSION['billing_gst_city']) ? htmlspecialchars($_SESSION['billing_gst_city']) : (isset($_SESSION['city']) ? htmlspecialchars($_SESSION['city']) : ''); ?>" 
                   required style="width: 50%; padding: 12px 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
        </div>
        
        <input type="text" id="gst_pincode" name="gst_pincode" placeholder="Pin Code" 
               value="<?php echo isset($_SESSION['billing_gst_pincode']) ? htmlspecialchars($_SESSION['billing_gst_pincode']) : (isset($_SESSION['pincode']) ? htmlspecialchars($_SESSION['pincode']) : ''); ?>" 
               required style="width: 100%; padding: 12px 15px; margin-bottom: 25px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
    </div>
    
    <!-- Price Breakdown -->
    <div class="calculation-display" style="margin: 20px 0; color: white; background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px;">
        <table style="width: 100%; color: white; font-size: 16px;">
            <tr>
                <td class="original-price" style="padding: 5px 0; text-align: left; width: 50%;"><strong>Original Price:</strong></td>
                <td class="original-price" style="text-align: right; padding: 5px 0; width: 50%;"><strong>₹ <?php echo number_format($original_amount, 2); ?></strong></td>
            </tr>
            <tr>
                <td class="discount" style="padding: 5px 0; text-align: left; width: 50%;"><strong>Discount:</strong></td>
                <td class="discount" style="text-align: right; padding: 5px 0; width: 50%;"><strong>₹ <?php echo number_format($discount_amount, 2); ?></strong></td>
            </tr>
            <tr>
                <td class="subtotal" style="padding: 5px 0; text-align: left; width: 50%;"><strong>Sub Total:</strong></td>
                <td class="subtotal" style="text-align: right; padding: 5px 0; width: 50%;"><strong>₹ <?php echo number_format($subtotal, 2); ?></strong></td>
            </tr>
            <tr>
                <td class="cgst" style="padding: 5px 0; text-align: left; width: 50%;"><strong>CGST (9%):</strong></td>
                <td class="cgst" style="text-align: right; padding: 5px 0; width: 50%;"><strong>₹ <?php echo number_format($cgst_amount, 2); ?></strong></td>
            </tr>
            <tr>
                <td class="sgst" style="padding: 5px 0; text-align: left; width: 50%;"><strong>SGST (9%):</strong></td>
                <td class="sgst" style="text-align: right; padding: 5px 0; width: 50%;"><strong>₹ <?php echo number_format($sgst_amount, 2); ?></strong></td>
            </tr>
            <tr>
                <td class="igst" style="padding: 5px 0; text-align: left; width: 50%;"><strong>IGST (18%):</strong></td>
                <td class="igst" style="text-align: right; padding: 5px 0; width: 50%;"><strong>₹ <?php echo number_format($igst_amount, 2); ?></strong></td>
            </tr>
            <tr>
                <td class="final-total" style="padding: 5px 0; border-top: 1px solid rgba(255,255,255,0.3); text-align: left;"><strong>Final Total:</strong></td>
                <td class="final-total" style="text-align: right; padding: 5px 0; border-top: 1px solid rgba(255,255,255,0.3);"><strong>₹ <?php echo number_format($final_amount, 2); ?></strong></td>
            </tr>
        </table>
    </div>
    
    <!-- Promo Code Section -->
    <div id="promo-section" style="margin-top: 15px;">
        <?php if(!$promo_applied): ?>
            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                <input type="text" id="promo_code_input" placeholder="Enter promo code" 
                       style="flex: 1; padding: 8px 12px; border: none; border-radius: 5px; font-size: 14px;" maxlength="20">
                <button type="button" id="apply_promo_btn" 
                        style="padding: 8px 15px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px;">
                    Apply
                </button>
            </div>
        <?php else: ?>
            <div style="margin-bottom: 10px; padding: 8px; background: rgba(40, 167, 69, 0.2); border-radius: 5px;">
                <span style="color: #28a745; font-weight: bold;"><?php echo $_SESSION['promo_code']; ?> Applied</span>
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
        <div id="promo-message" style="font-size: 13px; margin-top: 5px;"><?php echo $promo_message; ?></div>
    </div>
    
    <!-- Payment Button -->
    <button id="proceed-to-payment" style="width: 100%; background: #ffc107; color: #000; padding: 15px; border: none; border-radius: 8px; font-weight: bold; font-size: 16px; cursor: pointer; transition: all 0.3s ease; margin-top: 10px;">
        PROCEED TO PAY
    </button>
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
<form action="verify.php" method="POST" name="razorpayform" style="display:none;">
    <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
    <input type="hidden" name="razorpay_signature" id="razorpay_signature">
    <input type="hidden" name="razorpay_order_id" id="razorpay_order_id" value="<?php echo $razorpayOrderId; ?>">
    <input type="hidden" name="shopping_order_id" value="<?php echo $_SESSION['reference_number']; ?>">
</form>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var originalAmount = <?php echo isset($original_amount) ? $original_amount : 0; ?>;
    var currentDiscount = <?php echo isset($discount_amount) ? $discount_amount : 0; ?>;
    
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
            cgstElements[1].innerHTML = '<strong>₹ ' + cgst.toFixed(2) + '</strong>';
        }
        if (sgstElements.length >= 2) {
            sgstElements[1].innerHTML = '<strong>₹ ' + sgst.toFixed(2) + '</strong>';
        }
        if (igstElements.length >= 2) {
            igstElements[1].innerHTML = '<strong>₹ ' + igst.toFixed(2) + '</strong>';
        }
        if (finalTotalElements.length >= 2) {
            finalTotalElements[1].innerHTML = '<strong>₹ ' + finalAmount.toFixed(2) + '</strong>';
        }
        
        // Update subtotal
        var subtotalElements = document.querySelectorAll('.subtotal');
        if (subtotalElements.length >= 2) {
            subtotalElements[1].innerHTML = '<strong>₹ ' + subtotal.toFixed(2) + '</strong>';
        }
        
        return finalAmount;
    }
    
    // Global function for inline events
    window.updateTaxOnInput = function() {
        var originalAmount = <?php echo $original_amount; ?>;
        var discountAmount = <?php echo $discount_amount; ?>;
        updateTaxCalculation(originalAmount, discountAmount);
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
            
            // Recalculate tax before saving
            var finalAmount = updateTaxCalculation(originalAmount, currentDiscount);
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'save_billing_details.php', true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            // Use final amount from response
                            var finalAmount = response.final_amount || updateTaxCalculation(originalAmount, currentDiscount);
                            payBtn.textContent = 'Opening Payment...';
                            initializeRazorpayWithAmount(finalAmount);
                        } else {
                            alert('Error saving billing details: ' + response.message);
                            payBtn.disabled = false;
                            payBtn.textContent = 'PROCEED TO PAY';
                        }
                    } catch (e) {
                        alert('Error processing response');
                        payBtn.disabled = false;
                        payBtn.textContent = 'PROCEED TO PAY';
                    }
                } else {
                    alert('Error saving billing details');
                    payBtn.disabled = false;
                    payBtn.textContent = 'PROCEED TO PAY';
                }
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
                    var orderResponse = JSON.parse(createOrderXhr.responseText);
                    if (orderResponse.success) {
                        var options = {
                            key: "<?php echo $keyId; ?>",
                            amount: Math.round(finalAmount * 100),
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
                    alert('Error processing order response');
                    var payBtn = document.getElementById('proceed-to-payment');
                    if (payBtn) {
                        payBtn.disabled = false;
                        payBtn.textContent = 'PROCEED TO PAY';
                    }
                }
            } else {
                alert('Error creating payment order');
                var payBtn = document.getElementById('proceed-to-payment');
                if (payBtn) {
                    payBtn.disabled = false;
                    payBtn.textContent = 'PROCEED TO PAY';
                }
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
