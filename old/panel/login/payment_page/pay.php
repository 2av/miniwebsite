<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Suppress deprecation notices only
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// Create a log file for debugging
$log_file = __DIR__ . '/payment-debug.log';
file_put_contents($log_file, "=== Debug Session Started: " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);

function log_step($message) {
    global $log_file;
    file_put_contents($log_file, date('H:i:s') . " - " . $message . "\n", FILE_APPEND);
}
 

// Use original manual include
$razorpay_path = __DIR__ . '/razorpay-php/Razorpay.php';
if (file_exists($razorpay_path)) {
    require_once($razorpay_path);
}

// Add the use statement for the Api class
use Razorpay\Api\Api;

// Database connection - adjust these settings to match your database

$db_host = "p004.bom1.mysecurecloudhost.com";
$db_user = "wwwmoody_miniweb_vcard";
$db_pass = "miniweb_vcard";
$db_name = "miniweb_vcard";



// Create database connection
try {
    $connect = new mysqli($db_host, $db_user, $db_pass, $db_name);

} catch (Exception $e) {
    log_step("ERROR: Database exception: " . $e->getMessage());
}

// Initialize promo variables at the start
$promo_discount = 0;
$promo_message = '';
$promo_applied = false;

// Add Razorpay API credentials
$keyId = 'rzp_live_xU57a1JhH7To1G';
$keySecret = 'VHJzQnCxqF5HTNsE3LUTZtnI';
$displayCurrency = 'INR';

// Create Razorpay API instance
$api = new \Razorpay\Api\Api($keyId, $keySecret);

// Include coupon functions
require_once('../../../admin/coupon_functions.php');

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

?>

<meta name='viewport' content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0' />
<link rel="icon" href="pay.png" type="../images/pay.png" />
<link rel="stylesheet" href="../css.css">
<link rel="stylesheet" href="../mobile_css.css">
<script src="../master_js.js"></script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
 
 <!--------font family--------->
<link href='https://fonts.googleapis.com/css?family=Aclonica' rel='stylesheet'>



<style>
html {
    overflow-x: hidden;
    background: repeating-linear-gradient(45deg, black, transparent 100px), repeating-linear-gradient(-45deg, black, transparent 100px);
}
.application_pro  h3:nth-child(2), h3:nth-child(1) {
    background:var(--color2);color:white
}
.application_pro h3:nth-child(2):after {
    content: '';
    position: absolute;
    border: 20px solid transparent;
    border-left: 20px solid var(--color2) !important;
    z-index: 17 !important;
    right: -37px;
}

.header {display:none}
.application_form {
    margin: -59px 0px 0px 0px;
    width: auto;
    border: 0px solid;
    padding: 75px 0px;
}


 .form_preview {
     color: #3c3b3b;
    margin: 68px auto;
    padding: 23px;
    border-radius: 7px;
    font-family: sans-serif;
    box-shadow: 1px 1px 20px 0px #000000a8;
    width: 537px;
    position: relative;
    text-transform: capitalize;
    background: white;
}
.form_preview p {
    font-size: 14px;
    margin: 5px;
    width: 200px;
    padding: 10px;
    border: 0px solid;
}

.form_preview p img {width: 101px;
    border-radius: 52px;
    border: 2px solid #db3cd1;
    padding: 7px;}

.form_preview h3, p {
    font-size: 14px;
    margin: 5px;
    width: 200px;
    padding: 10px;
    color: black;
}

.containerPrv {font-weight: 100;
    display: grid;
    grid-template-columns: auto auto;
    font-family: Didact Gothic !important;
}

.offer_50_off {
    width: auto;
}

.offer_50_off img {width: 100%;}
.offer_50_off h3 {    width: -webkit-fill-available;
    font-size: 25px;
    text-align: center;
    color: #eb1c24;}
@media screen and (max-width:700px){
    
    .form_preview {
    color: #3c3b3b;
    margin: 68px auto;
    padding: 7px;
    width: auto;
    border-radius: 7px;
    font-family: sans-serif;
    box-shadow: 0px 0px 0px 0px green;
    width: -webkit-fill-available;
    position: relative;
    text-transform: capitalize;
}
}
</style>

<div class="clippath1" style="display: none;"></div>

<div class="application_form">

<!-- Back Button -->
<div style="text-align: center; margin-bottom: 20px;">
    <?php
    // Determine back URL based on source parameter
    $source = isset($_GET['source']) ? $_GET['source'] : 'customer'; // Default to customer if not specified
    if ($source == 'team') {
        $back_url = '../../../team/dashboard';
    } else {
        $back_url = '../../../customer/dashboard';
    }
    ?>
    <a href="<?php echo $back_url; ?>" class="back-button" style="display: inline-flex; align-items: center; background: #002169; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 16px; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0,33,105,0.3);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;">
            <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
        Back to Dashboard
    </a>
</div>

<div class="form_previews">
    
<?php

if (!isset($connect) || $connect->connect_error) {
   
    echo "<div style='color: red; padding: 20px; background: #ffeeee; border: 1px solid #ffcccc;'>
        Database connection failed. Please check your database settings.
    </div>";
} else {
   
    if (isset($_GET['id'])) {
        $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="' . $_GET['id'] . '" ');
       
       
        
        if (mysqli_num_rows($query) == 1) {
            $row = mysqli_fetch_array($query);
            $status=$row['d_payment_status'];
            $user_email_lower = strtolower(trim($row['user_email']));
            $customer = mysqli_query($connect, "SELECT phone as user_contact, referred_by FROM user_details WHERE LOWER(TRIM(email))='$user_email_lower' LIMIT 1");
            $contactno="";
            $referred_by="";
                $row1 = mysqli_fetch_array($customer);
                $contactno=$row1['user_contact'];
                $referred_by=$row1['referred_by'];
                
            // Check if customer was created from referral and this is first payment
            $is_referral_customer = !empty($referred_by);
            $is_first_payment = ($status == "Created" || $status != "Success");
            
            // Debug logging
            error_log("Payment Debug - Customer: " . $row['user_email'] . ", Referred By: " . $referred_by . ", Is Referral: " . ($is_referral_customer ? 'Yes' : 'No') . ", Is First Payment: " . ($is_first_payment ? 'Yes' : 'No'));
            
            // Additional debug for session variables
            error_log("Session Debug - Promo Applied: " . ($promo_applied ? 'Yes' : 'No') . ", Promo Code: " . (isset($_SESSION['promo_code']) ? $_SESSION['promo_code'] : 'None') . ", Auto Applied: " . (isset($_SESSION['auto_applied_promo']) ? ($_SESSION['auto_applied_promo'] ? 'Yes' : 'No') : 'None'));
            
            // Auto promo will check on every page load for changes
            
            // Set up refresh check key for tracking
            $refresh_check_key = 'promo_check_' . $row['user_email'] . '_' . $_GET['id'];

            
            // check if card is active and payment is done 
            if ($status == "Created" || $status!= "Success") {

?>
            <!------------form for paying and activating the account---------------->
           
                
                    
                    <?php 
                        // Get the actual payment amount from the database
                        if (isset($row['user_email']) && $row['user_email'] == 'ajeetcreative93@gmail.com' || $row['user_email'] == 'akhilesh@yopmail.com'  ) {
                            // Test account - set amount to Rs 1
                            $original_amount = 3;
                        } else if (isset($row['d_payment_amount']) && $row['d_payment_amount'] > 0) {
                            $original_amount = $row['d_payment_amount'];
                        } else {
                            // Default amount if not set in database
                            $original_amount = 847; // Updated to match new design
                        }
                        
                        // Set session variables
                        $_SESSION['reference_number'] = rand(100, 9000) . date('dhsi');
                        $_SESSION['user_name'] = $row['d_f_name'] . ' ' . $row['d_l_name'];
                        $_SESSION['user_contact'] = $contactno;
                        
                        // Initial discount calculation (will be updated after auto promo)
                        $discount_amount = $promo_applied ? $promo_discount : 0;
                        
                        // Auto-apply referral promocode if conditions are met
                        error_log("Auto Promo Check - Referral Customer: " . ($is_referral_customer ? 'Yes' : 'No') . ", First Payment: " . ($is_first_payment ? 'Yes' : 'No') . ", Promo Applied: " . ($promo_applied ? 'Yes' : 'No'));
                        
                        // Always check for auto promo on every page load/refresh
                        if ($is_referral_customer && $is_first_payment) {
                            error_log("Auto Promo - Checking for promo changes on page load");
                            $auto_promo_code = '';
                            $is_new_deal = false;
                            $current_promo_in_session = isset($_SESSION['promo_code']) ? $_SESSION['promo_code'] : '';
                            
                            // Check for ANY mapped deals for this referral (get the latest one)
                            $referral_deal_sql = "SELECT d.*, dcm.created_date as mapping_created_date FROM deals d 
                                INNER JOIN deal_customer_mapping dcm ON d.id = dcm.deal_id 
                                WHERE dcm.customer_email = '" . mysqli_real_escape_string($connect, $referred_by) . "' 
                                AND d.deal_status = 'Active' 
                                AND d.plan_type = 'MiniWebsite'
                                ORDER BY dcm.created_date DESC LIMIT 1";
                            
                            error_log("Deal mapping query: " . $referral_deal_sql);
                            $referral_deal_query = mysqli_query($connect, $referral_deal_sql);
                            
                            if (mysqli_num_rows($referral_deal_query) > 0) {
                                // Use mapped deal
                                $referral_deal = mysqli_fetch_array($referral_deal_query);
                                $auto_promo_code = $referral_deal['coupon_code'];
                                
                                // Check if this is a newly created mapping (within last hour)
                                $mapping_created_time = strtotime($referral_deal['mapping_created_date']);
                                $one_hour_ago = time() - 3600;
                                $is_new_deal = ($mapping_created_time > $one_hour_ago);
                                
                                // Debug logging
                                error_log("Found mapped deal for referral: " . $referred_by . " - Deal: " . $auto_promo_code . " (New: " . ($is_new_deal ? 'Yes' : 'No') . ")");
                            } else {
                                // Debug logging
                                error_log("No mapped deals found for referral: " . $referred_by . " - Using default DMW001");
                                // Use default promocode for referrals
                                $auto_promo_code = 'DMW001';
                                
                                // Check if DMW001 deal exists, create it if it doesn't
                                $dmw_check = mysqli_query($connect, "SELECT * FROM deals WHERE coupon_code='DMW001'");
                                if (mysqli_num_rows($dmw_check) == 0) {
                                    // Create DMW001 deal for default referral discount
                                    $create_dmw = mysqli_query($connect, "INSERT INTO deals (
                                        plan_name, plan_type, deal_name, coupon_code, bonus_amount, 
                                        discount_amount, discount_percentage, validity_date, max_usage, 
                                        deal_status, created_by, uploaded_date
                                    ) VALUES (
                                        'MiniWebsite', 'MiniWebsite', 'Default Referral Discount', 'DMW001', 
                                        0, 100, 0, '2025-12-31', 0, 'Active', 'system', NOW()
                                    )");
                                }
                            }
                            
                            // Check if promo code has changed or needs to be applied
                            error_log("Auto Promo - Final promo code: " . $auto_promo_code . ", Current in session: " . $current_promo_in_session);
                            
                            // Always try to apply the detected promo code (even if different from session)
                            if (!empty($auto_promo_code)) {
                                // If we have a different promo code than what's in session, clear the session first
                                if ($auto_promo_code !== $current_promo_in_session && !empty($current_promo_in_session)) {
                                    error_log("Auto Promo - Clearing session to apply new promo: " . $auto_promo_code);
                                    unset($_SESSION['promo_code']);
                                    unset($_SESSION['promo_discount']);
                                    unset($_SESSION['auto_applied_promo']);
                                    $promo_applied = false;
                                    $promo_discount = 0;
                                    $promo_message = '';
                                    $is_auto_applied = false;
                                }
                                
                                error_log("Auto Promo - Validating promo code: " . $auto_promo_code);
                                $validation = validateCoupon($auto_promo_code, $connect, 'card_payment');
                                error_log("Auto Promo - Validation result: " . ($validation['valid'] ? 'Valid' : 'Invalid'));
                                if ($validation['valid']) {
                                    $auto_discount = getCouponDiscount($original_amount, $validation['deal']);
                                    error_log("Auto Promo - Discount calculated: " . $auto_discount . " for amount: " . $original_amount);
                                    if ($auto_discount > 0 && $auto_discount <= $original_amount) {
                                        // Store in session with proper keys
                                        $_SESSION['promo_code'] = $auto_promo_code;
                                        $_SESSION['promo_discount'] = $auto_discount;
                                        $_SESSION['auto_applied_promo'] = true; // Mark as auto-applied
                                        $_SESSION['auto_promo_customer'] = $row['user_email']; // Track which customer
                                        $_SESSION['auto_promo_payment_id'] = $_GET['id']; // Track which payment
                                        
                                        // Update local variables
                                        $promo_applied = true;
                                        $promo_discount = $auto_discount;
                                        $is_auto_applied = true;
                                        
                                        // Auto promo applied successfully
                                        
                                        // Set appropriate message based on whether it's a new deal or changed
                                        if ($is_new_deal) {
                                            $promo_message = '<div class="promo-success">New referral promocode ' . $auto_promo_code . ' applied automatically! Discount: ₹' . $auto_discount . '</div>';
                                        } else if ($auto_promo_code !== $current_promo_in_session) {
                                            $promo_message = '<div class="promo-success">Referral promocode updated to ' . $auto_promo_code . ' automatically! Discount: ₹' . $auto_discount . '</div>';
                                        } else {
                                            $promo_message = '<div class="promo-success">Referral promocode ' . $auto_promo_code . ' applied automatically! Discount: ₹' . $auto_discount . '</div>';
                                        }
                                        
                                        // Apply the coupon (increment usage count)
                                        applyCoupon($auto_promo_code, $connect, 'card_payment');
                                        
                                        // Mark as checked to prevent duplicate applications
                                        $_SESSION[$refresh_check_key] = true;
                                        
                                        // Log the auto-application for debugging
                                        $log_message = $is_new_deal ? "Auto-applied new referral promocode on refresh" : "Auto-applied referral promocode";
                                        error_log($log_message . ": " . $auto_promo_code . " for customer: " . $row['user_email'] . " with discount: " . $auto_discount);
                                        error_log("🎉 AUTO PROMO SUCCESSFULLY APPLIED! Code: " . $auto_promo_code . ", Discount: ₹" . $auto_discount);
                                        
                                    } else {
                                        error_log("Auto Promo - Discount validation failed: " . $auto_discount . " (must be > 0 and <= " . $original_amount . ")");
                                    }
                                } else {
                                    error_log("Auto Promo - Coupon validation failed for: " . $auto_promo_code);
                                    
                                    // If mapped deal validation fails, try DMW001 as fallback
                                    if ($auto_promo_code !== 'DMW001' && 'DMW001' !== $current_promo_in_session) {
                                        error_log("Auto Promo - Trying DMW001 as fallback");
                                        $dmw_validation = validateCoupon('DMW001', $connect, 'card_payment');
                                        if ($dmw_validation['valid']) {
                                            $dmw_discount = getCouponDiscount($original_amount, $dmw_validation['deal']);
                                            if ($dmw_discount > 0 && $dmw_discount <= $original_amount) {
                                                // Store DMW001 in session
                                                $_SESSION['promo_code'] = 'DMW001';
                                                $_SESSION['promo_discount'] = $dmw_discount;
                                                $_SESSION['auto_applied_promo'] = true;
                                                $_SESSION['auto_promo_customer'] = $row['user_email'];
                                                $_SESSION['auto_promo_payment_id'] = $_GET['id'];
                                                
                                                // Update local variables
                                                $promo_applied = true;
                                                $promo_discount = $dmw_discount;
                                                $is_auto_applied = true;
                                                $promo_message = '<div class="promo-success">Referral promocode DMW001 applied automatically (fallback)! Discount: ₹' . $dmw_discount . '</div>';
                                                
                                                // Fallback auto promo applied successfully
                                                
                                                error_log("🎉 AUTO PROMO FALLBACK SUCCESS! DMW001 applied with discount: ₹" . $dmw_discount);
                                            }
                                        }
                                    }
                                }
                            } else {
                                error_log("Auto Promo - No promo code found to apply");
                            }
                        } else {
                            error_log("Auto Promo - Conditions not met - Referral: " . ($is_referral_customer ? 'Yes' : 'No') . ", First Payment: " . ($is_first_payment ? 'Yes' : 'No') . ", Promo Applied: " . ($promo_applied ? 'Yes' : 'No'));
                        }
                        
                        // NOW calculate final amounts with tax calculation (after auto promo is applied)
                        $discount_amount = $promo_applied ? $promo_discount : 0;
                        $subtotal = $original_amount - $discount_amount;
                        
                        // Tax calculation - GST is calculated AFTER discount deduction
                        $cgst_rate = 9;  // 9% CGST
                        $sgst_rate = 9;  // 9% SGST
                        $igst_rate = 18; // 18% IGST
                        
                        // Company state code (Haryana = 06)
                        $company_state_code = '06';
                        
                        // Get GST number and state from billing details (if available)
                        $gst_number = isset($_SESSION['billing_gst_number']) ? $_SESSION['billing_gst_number'] : '';
                        $billing_state = isset($_SESSION['billing_gst_state']) ? $_SESSION['billing_gst_state'] : '';
                        
                        // Determine if interstate transaction
                        $is_interstate = false;
                        
                        if (!empty($gst_number) && strlen($gst_number) == 15 && preg_match('/^\d{2}[A-Z0-9]{13}$/', $gst_number)) {
                            // Extract state code from GST number (positions 1-2)
                            $customer_state_code = substr($gst_number, 0, 2);
                            $is_interstate = ($customer_state_code !== $company_state_code);
                        } else {
                            // GST not filled or invalid: use state field instead
                            $billing_state_lower = strtolower(trim($billing_state));
                            $is_interstate = !in_array($billing_state_lower, ['haryana', 'hariyana']);
                        }
                        
                        // Calculate GST based on interstate/intrastate - ALWAYS on subtotal
                        if ($is_interstate) {
                            // IGST (18%) - calculated on subtotal
                            $igst_amount = round($subtotal * 0.18, 2);
                            $cgst_amount = 0;
                            $sgst_amount = 0;
                        } else {
                            // CGST + SGST (9% each) - calculated on subtotal
                            $cgst_amount = round($subtotal * 0.09, 2);
                            $sgst_amount = round($subtotal * 0.09, 2);
                            $igst_amount = 0;
                        }
                        
                        $total_tax = $cgst_amount + $sgst_amount + $igst_amount;
                        $final_amount = $subtotal + $total_tax;
                        
                        // Debug logging for tax calculation
                        error_log("Tax Calculation Debug - Original: ₹" . $original_amount . ", Discount: ₹" . $discount_amount . ", Subtotal: ₹" . $subtotal . ", IGST: ₹" . $igst_amount . ", Final: ₹" . $final_amount);
                        
                        // Set session amount to final amount (with tax)
                        $_SESSION['amount'] = $final_amount;
                        
                        // Store all calculated tax values in session for verify.php
                        $_SESSION['subtotal_amount'] = $subtotal;
                        $_SESSION['cgst_amount'] = $cgst_amount;
                        $_SESSION['sgst_amount'] = $sgst_amount;
                        $_SESSION['igst_amount'] = $igst_amount;
                        $_SESSION['final_total'] = $final_amount;
                        $_SESSION['is_interstate'] = $is_interstate;
                        $_SESSION['gst_state_code'] = isset($customer_state_code) ? $customer_state_code : '';
                    ?>
                    
                <!-- Billing Details Section -->
               
                 
                    <div style="max-width: 450px; margin: 0 auto; background: #002169; padding: 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                        <h4 style="color: white; text-align: center; margin-bottom: 10px; font-size: 20px; font-weight: 600;">Billing/GST Details</h4>
                        
                        <!-- Add the line below header -->
                        <div style="width: 35%; height: 2px; background: #ffc107; margin: 0 auto 25px auto; border-radius: 1px;"></div>
                        
                        <!-- 1. Billing Details Title (already above) -->
                        
                        <!-- 2. Forms (Input Fields) -->
                        <div class="billing-form">
                            <input type="text" id="gst_number" name="gst_number" placeholder="Enter GST Number (Optional)" value="<?php echo isset($_SESSION['billing_gst_number']) ? htmlspecialchars($_SESSION['billing_gst_number']) : ''; ?>" onkeyup="updateTaxOnInput()" onchange="updateTaxOnInput()" style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box; text-transform: uppercase;">
                            
                            <input type="text" id="gst_name" name="gst_name" placeholder="Name" value="<?php echo isset($_SESSION['billing_gst_name']) ? htmlspecialchars($_SESSION['billing_gst_name']) : ''; ?>" required style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                            
                            <input type="email" id="gst_email" name="gst_email" placeholder="Email Address" value="<?php echo isset($_SESSION['billing_gst_email']) ? htmlspecialchars($_SESSION['billing_gst_email']) : ''; ?>" required style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                            
                            <input type="tel" id="gst_contact" name="gst_contact" placeholder="Contact Number" value="<?php echo isset($_SESSION['billing_gst_contact']) ? htmlspecialchars($_SESSION['billing_gst_contact']) : ''; ?>" required style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                            
                            <input type="text" id="gst_address" name="gst_address" placeholder="Address" value="<?php echo isset($_SESSION['billing_gst_address']) ? htmlspecialchars($_SESSION['billing_gst_address']) : ''; ?>" required style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                            
                            <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                                <input type="text" id="gst_state" name="gst_state" placeholder="State" value="<?php echo isset($_SESSION['billing_gst_state']) ? htmlspecialchars($_SESSION['billing_gst_state']) : ''; ?>" onkeyup="updateTaxOnInput()" onchange="updateTaxOnInput()" required style="width: 50%; padding: 12px 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                                <input type="text" id="gst_city" name="gst_city" placeholder="City" value="<?php echo isset($_SESSION['billing_gst_city']) ? htmlspecialchars($_SESSION['billing_gst_city']) : ''; ?>" required style="width: 50%; padding: 12px 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                            </div>
                            
                            <input type="text" id="gst_pincode" name="gst_pincode" placeholder="Pin Code" value="<?php echo isset($_SESSION['billing_gst_pincode']) ? htmlspecialchars($_SESSION['billing_gst_pincode']) : ''; ?>" required style="width: 100%; padding: 12px 15px; margin-bottom: 25px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                        </div>
                        
                        <!-- 3. Price Details (Price Breakdown) -->
                        <div class="calculation-display" style="margin: 20px 0; color: white; background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px;">
                            <table style="width: 100%; color: white; font-size: 16px;">
                                <tr>
                                    <td class="original-price" style="padding: 5px 0; text-align: left; width: 50%;"><strong>Original Price:</strong></td>
                                    <td class="original-price" style="text-align: right; padding: 5px 0; width: 50%;"><strong>₹ <?php echo number_format($original_amount, 2); ?></strong></td>
                                </tr>
                                <tr>
                                    <td class="discount" style="padding: 5px 0; text-align: left; width: 50%;"><strong>Discount:</strong></td>
                                    <td class="discount" style="text-align: right; padding: 5px 0; width: 50%;"><strong>₹ <?php echo number_format($promo_discount, 2); ?></strong></td>
                                </tr>
                                <tr>
                                    <td class="subtotal" style="padding: 5px 0; text-align: left; width: 50%;"><strong>Sub Total:</strong></td>
                                    <td class="subtotal" style="text-align: right; padding: 5px 0; width: 50%;"><strong>₹ <?php echo number_format($original_amount - $promo_discount, 2); ?></strong></td>
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
                        
                        <!-- 4. Promo Code Section -->
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
                        
                        <!-- 5. Payment Button -->
                        <button id="proceed-to-payment" style="width: 100%; background: #ffc107; color: #000; padding: 15px; border: none; border-radius: 8px; font-weight: bold; font-size: 16px; cursor: pointer; transition: all 0.3s ease; margin-top: 10px;">
                            PROCEED TO PAY
                        </button>
                    </div>
                 
           
            <!------------form for paying and activating the account---------------->		
        
<?php
              
            } else {
               
                echo '<div class="alert success">Thanks! Your card is active. if your card is not showing then please contact us: admin@unbreakable.co.in</div>';
                echo '<style> input {display:none !important;} </style>';
            }
        } else {
           
            echo '<div class="alert success">Thanks! You have already paid for this account if your card is not showing then please contact us: admin@unbreakable.co.in </div>';
            echo '<style> input {display:none !important;} </style>';
        }
    } else {
        
        echo '<div class="alert error">Error: No ID parameter provided. Please use the correct payment link.</div>';
    }
}
?>

<?php

// Create the Razorpay Order
if (isset($_SESSION['amount']) && class_exists('Razorpay\Api\Api')) {
    try {
        // Create Razorpay API instance with proper configuration
        $api = new \Razorpay\Api\Api($keyId, $keySecret);
        
        $payment_currency = 'INR';
        $_SESSION['payment_currency'] = $payment_currency;
        $_SESSION['id'] = isset($_GET['id']) ? $_GET['id'] : '';

        $orderData = [
            'receipt'         => (isset($_SESSION['user_contact']) ? $_SESSION['user_contact'] : '') . date('dhsi'),
            'amount'          => $_SESSION['amount'] * 100, // amount in paise
            'currency'        => 'INR',
            'payment_capture' => 1 // auto capture
        ];
        
        $razorpayOrder = $api->order->create($orderData);
        $razorpayOrderId = $razorpayOrder['id'];
        $_SESSION['razorpay_order_id'] = $razorpayOrderId;
        
        $displayAmount = $amount = $orderData['amount'];
        
        if ($displayCurrency !== 'INR') {
            $url = "https://api.fixer.io/latest?symbols=$displayCurrency&base=INR";
            $exchange = json_decode(file_get_contents($url), true);
        
            $displayAmount = $exchange['rates'][$displayCurrency] * $amount / 100;
            $_SESSION['payment_amount_c'] = $displayAmount;
        }
        
        $data = [
            "key"               => $keyId,
            "amount"            => $amount,
            "name"              => "KIROVA SOLUTIONS LLP",
            "description"       => "Payment",
            "image"             => "favicon.png",
            "prefill"           => [
                "name"          => isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '',
                "email"         => isset($row['user_email']) ? $row['user_email'] : '',
                "contact"       => isset($_SESSION['user_contact']) ? $_SESSION['user_contact'] : '',
            ],
            "notes"             => [
                "address"       => "NA",
                "merchant_order_id" => isset($_SESSION['reference_number']) ? $_SESSION['reference_number'] : '',
            ],
            "theme"             => [
                "color"         => "#002169"
            ],
            "order_id"          => $razorpayOrderId,
        ];
        
        $json = json_encode($data);
?>
        <!-- Hidden form for Razorpay -->
        <form action="verify.php" method="POST" name="razorpayform" style="display:none;">
            <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
            <input type="hidden" name="razorpay_signature" id="razorpay_signature">
            <input type="hidden" name="razorpay_order_id" value="<?php echo $razorpayOrderId; ?>">
            <input type="hidden" name="shopping_order_id" value="<?php echo isset($data['notes']['merchant_order_id']) ? $data['notes']['merchant_order_id'] : ''; ?>">
        </form>
<?php
    } catch (Exception $e) {
        echo "<div style='color: red; padding: 20px; background: #ffeeee; border: 1px solid #ffcccc;'>
            Error creating Razorpay order: " . htmlspecialchars($e->getMessage()) . "
        </div>";
    }
} else {
    if (!isset($_SESSION['amount'])) {
    }
    if (!class_exists('Razorpay\Api\Api')) {
    }
}

?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check for new deals on page load/refresh
    function checkForNewDeals() {
        var cardId = '<?php echo isset($_GET["id"]) ? $_GET["id"] : ""; ?>';
        var userEmail = '<?php echo isset($row["user_email"]) ? $row["user_email"] : ""; ?>';
        
        if (cardId && userEmail) {
            var formData = new FormData();
            formData.append('action', 'check_new_deals');
            formData.append('card_id', cardId);
            formData.append('user_email', userEmail);
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'check_new_deals_ajax.php', true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success && response.new_deal_applied) {
                            // Reload the page to show the new promocode
                            location.reload();
                        }
                    } catch (e) {
                        console.log('No new deals found or error parsing response');
                    }
                }
            };
            xhr.send(formData);
        }
    }
    
    // Check for new deals on page load
    checkForNewDeals();
    
    // Function to update tax calculation display (matching franchise_agreement.php logic)
    function updateTaxCalculation(originalAmount, discountAmount) {
        var subtotal = originalAmount - discountAmount;
        
        // Get GST number and state to determine interstate/intrastate
        var gstNumber = document.querySelector('input[name="gst_number"]') ? 
                       document.querySelector('input[name="gst_number"]').value.trim() : '';
        var state = document.querySelector('input[name="gst_state"]') ? 
                   document.querySelector('input[name="gst_state"]').value.trim().toLowerCase() : '';
        var companyStateCode = '06'; // Haryana state code
        
        var isInterstate = false;
        var cgst = 0, sgst = 0, igst = 0;
        
        // Determine if interstate transaction
        console.log('Debug - GST Number:', gstNumber, 'State:', state);
        
        // Check if GST number is valid (15 characters, starts with 2 digits)
        if (gstNumber && gstNumber.length === 15 && /^\d{2}[A-Z0-9]{13}$/.test(gstNumber)) {
            // Extract state code from GST number (positions 1-2)
            var customerStateCode = gstNumber.substring(0, 2);
            isInterstate = (customerStateCode !== companyStateCode);
            console.log('Debug - Using GST number, customer state code:', customerStateCode, 'isInterstate:', isInterstate);
        } else {
            // GST not filled or invalid: use state field instead
            var stateLower = state.toLowerCase().trim();
            isInterstate = !['haryana', 'hariyana'].includes(stateLower);
            console.log('Debug - Using state field, stateLower:', stateLower, 'isInterstate:', isInterstate);
        }
        
        // Calculate GST based on interstate/intrastate
        if (isInterstate) {
            // IGST (18%)
            igst = Math.round(subtotal * 0.18 * 100) / 100;
            cgst = 0;
            sgst = 0;
        } else {
            // CGST + SGST (9% each)
            cgst = Math.round(subtotal * 0.09 * 100) / 100;
            sgst = Math.round(subtotal * 0.09 * 100) / 100;
            igst = 0;
        }
        
        var finalAmount = subtotal + cgst + sgst + igst;
        
        // Update the tax breakdown display in the table
        var cgstElements = document.querySelectorAll('.cgst');
        var sgstElements = document.querySelectorAll('.sgst');
        var igstElements = document.querySelectorAll('.igst');
        var finalTotalElements = document.querySelectorAll('.final-total');
        
        console.log('Debug - Found elements:', {
            cgstElements: cgstElements.length,
            sgstElements: sgstElements.length,
            igstElements: igstElements.length,
            finalTotalElements: finalTotalElements.length
        });
        
        // Update CGST display (second TD with cgst class)
        if (cgstElements.length >= 2) {
            console.log('Debug - Updating CGST from', cgstElements[1].innerHTML, 'to ₹' + cgst.toFixed(2));
            cgstElements[1].innerHTML = '<strong>₹ ' + cgst.toFixed(2) + '</strong>';
        }
        
        // Update SGST display (second TD with sgst class)
        if (sgstElements.length >= 2) {
            console.log('Debug - Updating SGST from', sgstElements[1].innerHTML, 'to ₹' + sgst.toFixed(2));
            sgstElements[1].innerHTML = '<strong>₹ ' + sgst.toFixed(2) + '</strong>';
        }
        
        // Update IGST display (second TD with igst class)
        if (igstElements.length >= 2) {
            console.log('Debug - Updating IGST from', igstElements[1].innerHTML, 'to ₹' + igst.toFixed(2));
            igstElements[1].innerHTML = '<strong>₹ ' + igst.toFixed(2) + '</strong>';
        }
        
        // Update Final Total display (second TD with final-total class)
        if (finalTotalElements.length >= 2) {
            console.log('Debug - Updating Final Total from', finalTotalElements[1].innerHTML, 'to ₹' + finalAmount.toFixed(2));
            finalTotalElements[1].innerHTML = '<strong>₹ ' + finalAmount.toFixed(2) + '</strong>';
        }
        
        console.log('Tax Calculation Updated:', {
            originalAmount: originalAmount,
            discountAmount: discountAmount,
            subtotal: subtotal,
            cgstAmount: cgst,
            sgstAmount: sgst,
            igstAmount: igst,
            totalTax: cgst + sgst + igst,
            finalAmount: finalAmount,
            isInterstate: isInterstate
        });
        
        // Visual indicator that function is working
        console.log('🎯 TAX CALCULATION COMPLETED - Check the display above!');
        
        return finalAmount;
    }
    
    // Function to update tax display in real-time (matching franchise_agreement.php)
    function updateTaxDisplay() {
        var originalAmount = <?php echo $original_amount; ?>;
        var discountAmount = <?php echo $discount_amount; ?>;
        var subtotal = originalAmount - discountAmount;
        
        // Get GST number and state to determine interstate/intrastate
        var gstNumber = document.querySelector('input[name="gst_number"]') ? 
                       document.querySelector('input[name="gst_number"]').value.trim() : '';
        var state = document.querySelector('input[name="gst_state"]') ? 
                   document.querySelector('input[name="gst_state"]').value.trim().toLowerCase() : '';
        var companyStateCode = '06'; // Haryana state code
        
        var isInterstate = false;
        var cgst = 0, sgst = 0, igst = 0;
        
        // Determine if interstate transaction
        if (gstNumber && gstNumber.length === 15 && /^\d{2}[A-Z0-9]{13}$/.test(gstNumber)) {
            // Extract state code from GST number (positions 1-2)
            var customerStateCode = gstNumber.substring(0, 2);
            isInterstate = (customerStateCode !== companyStateCode);
            console.log('GST Number detected:', gstNumber, 'State Code:', customerStateCode, 'Is Interstate:', isInterstate);
        } else {
            // GST not filled or invalid: use state field instead
            var stateLower = state.toLowerCase().trim();
            isInterstate = !['haryana', 'hariyana'].includes(stateLower);
            console.log('Using state field:', state, 'Is Interstate:', isInterstate);
        }
        
        // Calculate GST based on interstate/intrastate
        if (isInterstate) {
            // IGST (18%)
            igst = Math.round(subtotal * 0.18 * 100) / 100;
            cgst = 0;
            sgst = 0;
        } else {
            // CGST + SGST (9% each)
            cgst = Math.round(subtotal * 0.09 * 100) / 100;
            sgst = Math.round(subtotal * 0.09 * 100) / 100;
            igst = 0;
        }
        
        var finalAmount = subtotal + cgst + sgst + igst;
        
        // Update the tax breakdown display
        var cgstElements = document.querySelectorAll('.tax-breakdown .detail-row:nth-child(1) .detail-value');
        var sgstElements = document.querySelectorAll('.tax-breakdown .detail-row:nth-child(2) .detail-value');
        var igstElements = document.querySelectorAll('.tax-breakdown .detail-row:nth-child(3) .detail-value');
        var finalTotalElements = document.querySelectorAll('.total-row .detail-value');
        
        if (cgstElements.length > 0) {
            cgstElements[0].textContent = '₹' + cgst;
        }
        if (sgstElements.length > 0) {
            sgstElements[0].textContent = '₹' + sgst;
        }
        if (igstElements.length > 0) {
            igstElements[0].textContent = '₹' + igst;
        }
        if (finalTotalElements.length > 0) {
            finalTotalElements[0].textContent = '₹' + finalAmount;
        }
        
        // Update session amount
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'update_tax_amount_ajax.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send('final_amount=' + finalAmount + '&cgst=' + cgst + '&sgst=' + sgst + '&igst=' + igst);
        
        console.log('Tax Display Updated:', {
            subtotal: subtotal,
            cgst: cgst,
            sgst: sgst,
            igst: igst,
            finalAmount: finalAmount,
            isInterstate: isInterstate
        });
    }
    
    // Add event listeners to GST number and state fields for automatic calculation update
    document.addEventListener('DOMContentLoaded', function() {
        const gstInput = document.querySelector('input[name="gst_number"]');
        const stateInput = document.querySelector('input[name="gst_state"]');
        
        // Debounce function to limit calculation frequency
        let taxUpdateTimeout;
        function debouncedUpdateTax() {
            console.log('Debug - debouncedUpdateTax called');
            clearTimeout(taxUpdateTimeout);
            taxUpdateTimeout = setTimeout(function() {
                console.log('Debug - Executing tax update after delay');
                var originalAmount = <?php echo isset($original_amount) ? $original_amount : 3; ?>;
                var discountAmount = <?php echo isset($discount_amount) ? $discount_amount : 0; ?>;
                console.log('Debug - Original Amount:', originalAmount, 'Discount Amount:', discountAmount);
                updateTaxDisplay();
                updateTaxCalculation(originalAmount, discountAmount);
            }, 300); // Wait 300ms after user stops typing
        }
        
        if (gstInput) {
            // Update calculation on every keystroke, paste, and blur
            gstInput.addEventListener('input', debouncedUpdateTax);
            gstInput.addEventListener('keyup', debouncedUpdateTax);
            gstInput.addEventListener('paste', function() {
                // Wait for paste to complete, then update
                setTimeout(debouncedUpdateTax, 10);
            });
            gstInput.addEventListener('blur', function() {
                var originalAmount = <?php echo isset($original_amount) ? $original_amount : 3; ?>;
                var discountAmount = <?php echo isset($discount_amount) ? $discount_amount : 0; ?>;
                updateTaxDisplay();
                updateTaxCalculation(originalAmount, discountAmount);
            }); // Immediate update on blur
            gstInput.addEventListener('change', function() {
                var originalAmount = <?php echo isset($original_amount) ? $original_amount : 3; ?>;
                var discountAmount = <?php echo isset($discount_amount) ? $discount_amount : 0; ?>;
                updateTaxDisplay();
                updateTaxCalculation(originalAmount, discountAmount);
            });
        }
        
        if (stateInput) {
            console.log('Debug - State input found, adding event listeners');
            
            // Add immediate test on input
            stateInput.addEventListener('input', function() {
                console.log('🔥 STATE INPUT CHANGED:', stateInput.value);
            });
            
            stateInput.addEventListener('input', debouncedUpdateTax);
            stateInput.addEventListener('keyup', debouncedUpdateTax);
            stateInput.addEventListener('paste', function() {
                // Wait for paste to complete, then update
                setTimeout(debouncedUpdateTax, 10);
            });
            stateInput.addEventListener('blur', function() {
                console.log('Debug - State input blur event');
                var originalAmount = <?php echo isset($original_amount) ? $original_amount : 3; ?>;
                var discountAmount = <?php echo isset($discount_amount) ? $discount_amount : 0; ?>;
                updateTaxDisplay();
                updateTaxCalculation(originalAmount, discountAmount);
            }); // Immediate update on blur
            stateInput.addEventListener('change', function() {
                console.log('Debug - State input change event');
                var originalAmount = <?php echo isset($original_amount) ? $original_amount : 3; ?>;
                var discountAmount = <?php echo isset($discount_amount) ? $discount_amount : 0; ?>;
                updateTaxDisplay();
                updateTaxCalculation(originalAmount, discountAmount);
            });
        } else {
            console.log('Debug - State input NOT found!');
        }
        
    });
    
    // Global function for inline onkeyup/onchange events
    window.updateTaxOnInput = function() {
        console.log('🔥 TAX UPDATE ON INPUT TRIGGERED!');
        var originalAmount = <?php echo isset($original_amount) ? $original_amount : 3; ?>;
        var discountAmount = <?php echo isset($discount_amount) ? $discount_amount : 0; ?>;
        
        // Get current state value
        var stateInput = document.querySelector('input[name="gst_state"]');
        var currentState = stateInput ? stateInput.value : '';
        console.log('Debug - Input change - Current state value:', currentState);
        
        if (typeof updateTaxDisplay === 'function') {
            updateTaxDisplay();
        }
        
        if (typeof updateTaxCalculation === 'function') {
            updateTaxCalculation(originalAmount, discountAmount);
        }
    };
    
    // Function to run on page load
    window.runTaxCalculationOnLoad = function() {
        console.log('🔥 PAGE LOAD TAX CALCULATION!');
        var originalAmount = <?php echo isset($original_amount) ? $original_amount : 3; ?>;
        var discountAmount = <?php echo isset($discount_amount) ? $discount_amount : 0; ?>;
        
        // Get current state value
        var stateInput = document.querySelector('input[name="gst_state"]');
        var currentState = stateInput ? stateInput.value : '';
        console.log('Debug - Page load - Current state value:', currentState);
        
        if (typeof updateTaxDisplay === 'function') {
            updateTaxDisplay();
        }
        
        if (typeof updateTaxCalculation === 'function') {
            updateTaxCalculation(originalAmount, discountAmount);
        }
    };
    
    // Run tax calculation on page load
    setTimeout(function() {
        console.log('🔥 RUNNING TAX CALCULATION ON PAGE LOAD');
        runTaxCalculationOnLoad();
    }, 1000); // Wait 1 second for everything to load
    
    // Set up periodic check for new deals every 30 seconds
    var dealCheckInterval = setInterval(function() {
        // Only check if no promo is currently applied
        var promoApplied = document.querySelector('.promo-success') !== null;
        if (!promoApplied) {
            checkForNewDeals();
        } else {
            // Clear interval if promo is already applied
            clearInterval(dealCheckInterval);
        }
    }, 30000); // Check every 30 seconds
    
    // Function to handle promo code application via AJAX
    function applyPromoCode() {
        var promoCode = document.getElementById('promo_code_input').value.trim();
        var originalAmount = <?php echo $original_amount; ?>;
        
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
        
        // Add billing details to preserve them
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
            console.log("AJAX Response Status:", xhr.status);
            console.log("AJAX Response Text:", xhr.responseText);
            
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    console.log("Parsed Response:", response);
                    
                    if (response.success) {
                        console.log("Promo code applied successfully, reloading page...");
                        // Reload the page to update the amount display
                        location.reload();
                    } else {
                        console.log("Promo code application failed:", response.message);
                        alert(response.message);
                    }
                } catch (e) {
                    console.error("Error parsing JSON response:", e);
                    alert('Error processing response');
                }
            } else {
                console.error("AJAX request failed with status:", xhr.status);
                alert('Error applying promo code');
            }
            applyBtn.disabled = false;
            applyBtn.textContent = 'Apply';
        };
        xhr.onerror = function() {
            console.error("AJAX network error occurred");
            alert('Network error');
            applyBtn.disabled = false;
            applyBtn.textContent = 'Apply';
        };
        xhr.send(formData);
    }
    
    // Function to handle promo code removal via AJAX
    function removePromoCode() {
        var originalAmount = <?php echo $original_amount; ?>;
        
        var removeBtn = document.getElementById('remove_promo_btn');
        removeBtn.disabled = true;
        removeBtn.textContent = 'Removing...';
        
        var formData = new FormData();
        formData.append('action', 'remove_promo');
        formData.append('original_amount', originalAmount);
        
        // Add billing details to preserve them
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
                        // Reload the page to update the UI
                        location.reload();
                    } else {
                        alert(response.message);
                    }
                } catch (e) {
                    alert('Error processing response');
                }
            } else {
                alert('Error removing promo code');
            }
            removeBtn.disabled = false;
            removeBtn.textContent = 'Remove';
        };
        xhr.onerror = function() {
            alert('Network error');
            removeBtn.disabled = false;
            removeBtn.textContent = 'Remove';
        };
        xhr.send(formData);
    }
    
    // Add event listeners for promo code buttons
    var applyPromoBtn = document.getElementById('apply_promo_btn');
    if (applyPromoBtn) {
        applyPromoBtn.addEventListener('click', applyPromoCode);
    }
    
    var removePromoBtn = document.getElementById('remove_promo_btn');
    if (removePromoBtn) {
        removePromoBtn.addEventListener('click', removePromoCode);
    }
    
    // Add enter key support for promo code input
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
            // Prevent default form submission
            e.preventDefault();
            e.stopPropagation();
            
            console.log("Proceed to pay button clicked");
            
            // Get form field values
            var cardId = '<?php echo isset($_GET["id"]) ? $_GET["id"] : ""; ?>';
            var gstNumber = document.getElementById('gst_number').value;
            var gstName = document.getElementById('gst_name').value;
            var gstEmail = document.getElementById('gst_email').value;
            var gstContact = document.getElementById('gst_contact').value;
            var gstAddress = document.getElementById('gst_address').value;
            var gstState = document.getElementById('gst_state').value;
            var gstCity = document.getElementById('gst_city').value;
            var gstPincode = document.getElementById('gst_pincode').value;
            
            // Validation
            var isValid = true;
            var errorMessage = "";
            
            // Reset all field borders
            document.getElementById('gst_name').style.border = "1px solid #ccc";
            document.getElementById('gst_email').style.border = "1px solid #ccc";
            document.getElementById('gst_contact').style.border = "1px solid #ccc";
            document.getElementById('gst_address').style.border = "1px solid #ccc";
            document.getElementById('gst_state').style.border = "1px solid #ccc";
            document.getElementById('gst_city').style.border = "1px solid #ccc";
            document.getElementById('gst_pincode').style.border = "1px solid #ccc";
            document.getElementById('gst_number').style.border = "1px solid #ccc";
            
            // Name validation
            if (gstName.trim() === "") {
                isValid = false;
                errorMessage += "Name is required\n";
                document.getElementById('gst_name').style.border = "1px solid red";
            }
            
            // Email validation
            if (gstEmail.trim() === "") {
                isValid = false;
                errorMessage += "Email Address is required\n";
                document.getElementById('gst_email').style.border = "1px solid red";
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(gstEmail)) {
                isValid = false;
                errorMessage += "Please enter a valid email address\n";
                document.getElementById('gst_email').style.border = "1px solid red";
            }
            
            // Contact validation
            if (gstContact.trim() === "") {
                isValid = false;
                errorMessage += "Contact Number is required\n";
                document.getElementById('gst_contact').style.border = "1px solid red";
            } else if (!/^\d{10}$/.test(gstContact)) {
                isValid = false;
                errorMessage += "Contact Number must be 10 digits\n";
                document.getElementById('gst_contact').style.border = "1px solid red";
            }
            
            // Address validation
            if (gstAddress.trim() === "") {
                isValid = false;
                errorMessage += "Address is required\n";
                document.getElementById('gst_address').style.border = "1px solid red";
            }
            
            // State validation
            if (gstState.trim() === "") {
                isValid = false;
                errorMessage += "State is required\n";
                document.getElementById('gst_state').style.border = "1px solid red";
            }
            
            // City validation
            if (gstCity.trim() === "") {
                isValid = false;
                errorMessage += "City is required\n";
                document.getElementById('gst_city').style.border = "1px solid red";
            }
            
            // Pincode validation
            if (gstPincode.trim() === "") {
                isValid = false;
                errorMessage += "Pin Code is required\n";
                document.getElementById('gst_pincode').style.border = "1px solid red";
            } else if (!/^\d{6}$/.test(gstPincode)) {
                isValid = false;
                errorMessage += "Pin Code must be 6 digits\n";
                document.getElementById('gst_pincode').style.border = "1px solid red";
            }
            
            // GST Number validation (if provided)
            if (gstNumber.trim() !== "" && !/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/.test(gstNumber)) {
                isValid = false;
                errorMessage += "Invalid GST Number format\n";
                document.getElementById('gst_number').style.border = "1px solid red";
            }
            
            // If validation fails, show error and return
            if (!isValid) {
                alert(errorMessage);
                console.log("Validation failed, stopping payment flow");
                return false; // Exit the function
            }
            
            // If we get here, validation passed
            console.log("Validation passed, processing payment");
            
            // Create form data for AJAX
            var formData = new FormData();
            formData.append('card_id', cardId);
            formData.append('gst_number', gstNumber);
            formData.append('gst_name', gstName);
            formData.append('gst_email', gstEmail);
            formData.append('gst_contact', gstContact);
            formData.append('gst_address', gstAddress);
            formData.append('gst_state', gstState);
            formData.append('gst_city', gstCity);
            formData.append('gst_pincode', gstPincode);
            
            // Disable the button to prevent multiple clicks
            payBtn.disabled = true;
            payBtn.textContent = 'Processing...';
            
            // Save billing details first
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'save_billing_details.php', true);
            xhr.onload = function() {
                console.log("Billing details response:", xhr.responseText);
                
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        console.log("Parsed response:", response);
                        
                        if (response.success) {
                            console.log("Billing details saved successfully");
                            
                            // Clear session billing details after successful save
                            var clearSessionXhr = new XMLHttpRequest();
                            clearSessionXhr.open('POST', 'clear_billing_session.php', true);
                            clearSessionXhr.send();
                            
                            // Update button text and directly initialize Razorpay
                            payBtn.textContent = 'Opening Payment...';
                            initializeRazorpay();
                            
                        } else {
                            console.error("Error saving billing details:", response.message);
                            alert('Error saving billing details: ' + response.message);
                            payBtn.disabled = false;
                            payBtn.textContent = 'PROCEED TO PAY';
                        }
                    } catch (e) {
                        console.error("Error parsing JSON response:", e);
                        alert('Error processing response. Please try again.');
                        payBtn.disabled = false;
                        payBtn.textContent = 'PROCEED TO PAY';
                    }
                } else {
                    console.error("Error saving billing details:", xhr.responseText);
                    alert('Error saving billing details. Please try again.');
                    payBtn.disabled = false;
                    payBtn.textContent = 'PROCEED TO PAY';
                }
            };
            xhr.onerror = function() {
                console.error("Network error saving billing details");
                alert('Network error. Please try again.');
                payBtn.disabled = false;
                payBtn.textContent = 'PROCEED TO PAY';
            };
            xhr.send(formData);
        });
    } else {
        console.error("Save details button not found");
    }
    
    // Separate function to initialize Razorpay
    function initializeRazorpay() {
        console.log("Initializing Razorpay");
        var options = {
            key: "<?php echo $keyId; ?>",
            amount: "<?php echo isset($final_amount) ? round($final_amount * 100) : 84700; ?>", // amount in paise
            name: "KIROVA SOLUTIONS LLP",
            description: "Payment<?php echo (isset($promo_applied) && $promo_applied && isset($_SESSION['promo_code'])) ? ' (Promo: '.$_SESSION['promo_code'].')' : ''; ?>",
            image: "favicon.png",
            order_id: "<?php echo isset($_SESSION['razorpay_order_id']) ? $_SESSION['razorpay_order_id'] : ''; ?>",
            handler: function (response) {
                console.log("Payment successful", response);
                
                // Set the form values
                var paymentIdField = document.getElementById('razorpay_payment_id');
                var signatureField = document.getElementById('razorpay_signature');
                
                if (paymentIdField) paymentIdField.value = response.razorpay_payment_id;
                if (signatureField) signatureField.value = response.razorpay_signature;
                
                // Find and submit the form
                var form = document.getElementsByName('razorpayform')[0];
                if (form && typeof form.submit === 'function') {
                    form.submit();
                } else {
                    // Fallback: create a form dynamically and submit
                    var dynamicForm = document.createElement('form');
                    dynamicForm.method = 'POST';
                    dynamicForm.action = 'verify.php';
                    
                    var paymentIdInput = document.createElement('input');
                    paymentIdInput.type = 'hidden';
                    paymentIdInput.name = 'razorpay_payment_id';
                    paymentIdInput.value = response.razorpay_payment_id;
                    
                    var signatureInput = document.createElement('input');
                    signatureInput.type = 'hidden';
                    signatureInput.name = 'razorpay_signature';
                    signatureInput.value = response.razorpay_signature;
                    
                    var orderIdInput = document.createElement('input');
                    orderIdInput.type = 'hidden';
                    orderIdInput.name = 'razorpay_order_id';
                    orderIdInput.value = '<?php echo isset($_SESSION['razorpay_order_id']) ? $_SESSION['razorpay_order_id'] : ''; ?>';
                    
                    dynamicForm.appendChild(paymentIdInput);
                    dynamicForm.appendChild(signatureInput);
                    dynamicForm.appendChild(orderIdInput);
                    
                    document.body.appendChild(dynamicForm);
                    dynamicForm.submit();
                }
            },
            prefill: {
                name: "<?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : ''; ?>",
                email: "<?php echo isset($row['user_email']) ? $row['user_email'] : ''; ?>",
                contact: "<?php echo isset($_SESSION['user_contact']) ? $_SESSION['user_contact'] : ''; ?>"
            },
            notes: {
                shopping_order_id: "<?php echo isset($_SESSION['reference_number']) ? $_SESSION['reference_number'] : ''; ?>",
                promo_code: "<?php echo (isset($promo_applied) && $promo_applied && isset($_SESSION['promo_code'])) ? $_SESSION['promo_code'] : ''; ?>",
                discount_amount: "<?php echo isset($discount_amount) ? $discount_amount : '0'; ?>"
            },
            theme: {
                color: "#002169"
            },
            modal: {
                ondismiss: function() {
                    console.log("Payment modal dismissed");
                    // Reset the button when payment is cancelled/closed
                    var payBtn = document.getElementById('proceed-to-payment');
                    if (payBtn) {
                        payBtn.disabled = false;
                        payBtn.textContent = 'PROCEED TO PAY';
                    }
                }
            }
        };
        
        // Log the amount for debugging
        console.log("Payment amount: <?php echo isset($final_amount) ? number_format($final_amount, 2) : 'not set'; ?> Rs");
        
        try {
            var rzp = new Razorpay(options);
            rzp.on('payment.failed', function (response){
                console.error("Payment failed:", response.error);
                alert('Payment failed: ' + response.error.description);
                // Reset the button when payment fails
                var payBtn = document.getElementById('proceed-to-payment');
                if (payBtn) {
                    payBtn.disabled = false;
                    payBtn.textContent = 'PROCEED TO PAY';
                }
            });
            rzp.open();
            console.log("Razorpay opened");
        } catch (e) {
            console.error("Error creating Razorpay instance:", e);
            alert('Error initializing payment: ' + e.message);
            // Reset the button when there's an error
            var payBtn = document.getElementById('proceed-to-payment');
            if (payBtn) {
                payBtn.disabled = false;
                payBtn.textContent = 'PROCEED TO PAY';
            }
        }
    }
});
</script>  
     
<!-- Make sure Razorpay script is loaded -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>



</div>


<style>
body { 
    font-family: Arial, sans-serif; 
    margin: 0; 
    padding: 20px; 
    background: #f5f5f5; 
}

.payment-container { 
    max-width: 500px; 
    margin: 0 auto; 
    background: white; 
    padding: 30px; 
    border-radius: 10px; 
    box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
}

.header { 
    text-align: center; 
    margin-bottom: 30px; 
}

.header h2 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 24px;
}

.header p {
    margin: 0;
    color: #666;
    font-size: 14px;
}

.detail-row { 
    display: flex; 
    justify-content: space-between; 
    padding: 10px 0; 
    border-bottom: 1px solid #eee; 
}

.detail-row:last-child { 
    border-bottom: none; 
}

.amount-box { 
    background: #002169; 
    color: white; 
    padding: 20px; 
    border-radius: 8px; 
    text-align: center; 
    margin: 20px 0; 
    font-size: 18px;
}

.promo-section {
    margin: 15px 0;
}

.promo-form {
    display: flex;
    gap: 10px;
    align-items: center;
}

.promo-form input {
    flex: 1;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.promo-form button {
    padding: 8px 15px;
    background: #28a745;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.promo-form button:hover {
    background: #218838;
}

.promo-message {
    margin-top: 10px;
    padding: 8px;
    border-radius: 4px;
    font-size: 14px;
    text-align: center;
}

.promo-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.promo-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Billing form styles - using inline styles to match franchise design exactly */
.billing-form input:focus {
    outline: none;
    box-shadow: 0 0 0 2px rgba(255, 193, 7, 0.3);
}

.billing-form button:hover {
    background: #e0a800 !important;
    transform: translateY(-1px);
}

/* Back button hover effect */
.back-button:hover {
    background: #001a4d !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,33,105,0.4) !important;
}
</style>


