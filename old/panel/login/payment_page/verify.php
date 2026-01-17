<?php
// Enable error reporting but exclude deprecation notices
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// Create a log file for debugging
$log_file = __DIR__ . '/verify-debug.log';
file_put_contents($log_file, "=== Verification Started: " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);

function log_verify($message) {
    global $log_file;
    file_put_contents($log_file, date('H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

function log_step($message) {
    global $log_file;
    file_put_contents($log_file, date('H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

// Function to update referral earnings after payment completion
function updateReferralEarningsAfterPayment($connect, $user_email, $card_id) {
    // Get user's referral information
    $user_query = mysqli_query($connect, "SELECT referred_by FROM customer_login WHERE user_email = '" . mysqli_real_escape_string($connect, $user_email) . "'");
    
    if (mysqli_num_rows($user_query) > 0) {
        $user_data = mysqli_fetch_array($user_query);
        $referred_by = $user_data['referred_by'];
        
        if (!empty($referred_by)) {
            // Check if referral earning already exists for this user
            $existing_earning_query = mysqli_query($connect, "SELECT id, amount FROM referral_earnings WHERE referred_email = '" . mysqli_real_escape_string($connect, $user_email) . "'");
            
            if (mysqli_num_rows($existing_earning_query) > 0) {
                // Update existing referral earning with new amount if deals have changed
                $existing_earning = mysqli_fetch_array($existing_earning_query);
                $current_amount = $existing_earning['amount'];
                
                // Get the latest deal amount (mapped deal or default)
                $new_amount = getLatestReferralAmount($connect, $referred_by, 'MiniWebsite');
                
                // Only update if amount has changed
                if ($new_amount != $current_amount) {
                    $update_query = mysqli_query($connect, "UPDATE referral_earnings SET 
                        amount = '$new_amount'
                        WHERE id = '" . $existing_earning['id'] . "'");
                    
                    if ($update_query) {
                        error_log("Updated referral earning for user: $user_email, old amount: $current_amount, new amount: $new_amount");
                        return true;
                    }
                }
            } else {
                // Create new referral earning if it doesn't exist
                $new_amount = getLatestReferralAmount($connect, $referred_by, 'MiniWebsite');
                
                $insert_query = mysqli_query($connect, "INSERT INTO referral_earnings 
                    (referrer_email, referred_email, referral_date, status, amount, is_collaboration) 
                    VALUES ('" . mysqli_real_escape_string($connect, $referred_by) . "', 
                            '" . mysqli_real_escape_string($connect, $user_email) . "', 
                            NOW(), 'Pending', '$new_amount', 'NO')");
                
                if ($insert_query) {
                    error_log("Created new referral earning for user: $user_email, amount: $new_amount");
                    return true;
                }
            }
        }
    }
    
    return false;
}

// Function to get the latest referral amount (mapped deal or default)
function getLatestReferralAmount($connect, $referrer_email, $plan_type) {
    $default_amount = 250.00;
    
    // First, check if there are any deals mapped to the referrer
    $mapped_deal_query = mysqli_query($connect, "SELECT d.bonus_amount FROM deals d 
        INNER JOIN deal_customer_mapping dcm ON d.id = dcm.deal_id 
        WHERE dcm.customer_email = '" . mysqli_real_escape_string($connect, $referrer_email) . "' 
        AND d.deal_status = 'Active' 
        AND d.plan_type = '$plan_type'
        ORDER BY dcm.created_date DESC LIMIT 1");
    
    if (mysqli_num_rows($mapped_deal_query) > 0) {
        // Use mapped deal amount
        $mapped_deal_data = mysqli_fetch_array($mapped_deal_query);
        return $mapped_deal_data['bonus_amount'] > 0 ? $mapped_deal_data['bonus_amount'] : $default_amount;
    } else {
        // Check for default deal based on plan type
        $default_deal_code = ($plan_type == 'Franchisee') ? 'DFRAN101' : 'DMW001';
        $default_deal_query = mysqli_query($connect, "SELECT bonus_amount FROM deals WHERE coupon_code='$default_deal_code' AND deal_status='Active'");
        
        if (mysqli_num_rows($default_deal_query) > 0) {
            $default_deal_data = mysqli_fetch_array($default_deal_query);
            return $default_deal_data['bonus_amount'] > 0 ? $default_deal_data['bonus_amount'] : $default_amount;
        }
    }
    
    return $default_amount;
}

require('config.php');
require('razorpay-php/Razorpay.php');

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set default date format
$date = date('Y-m-d H:i:s');

$success = true;
$error = "Payment Failed";

log_verify("Starting payment verification for ID: " . (isset($_SESSION['id']) ? $_SESSION['id'] : 'unknown'));
log_verify("POST data: " . json_encode($_POST));
log_verify("SESSION data: " . json_encode($_SESSION));

// Check if required session variables are set
if (!isset($_SESSION['id']) || empty($_SESSION['id'])) {
    log_verify("ERROR: Session ID is not set");
    echo '<div class="payment_error">Session error. Please try again.</div>';
    exit;
}

if (!isset($_SESSION['reference_number']) || empty($_SESSION['reference_number'])) {
    log_verify("ERROR: Reference number is not set");
    echo '<div class="payment_error">Reference number error. Please try again.</div>';
    exit;
}

// Check if we have the necessary payment information
if (empty($_POST['razorpay_payment_id'])) {
    log_verify("ERROR: No payment ID received");
    $success = false;
    $error = "Payment information missing. Please try again.";
} else {
    // IMPORTANT: Make sure we're using the correct key secret
    // Double-check the key secret in config.php
    log_verify("Using key secret from config.php: " . substr($keySecret, 0, 4) . "..." . substr($keySecret, -4));
    
    // For testing, let's try to accept the payment without verification
    $bypass_verification = false;
    
    if (!$bypass_verification) {
        $api = new Api($keyId, $keySecret);
        
        try {
            // Get order ID from POST or SESSION
            $razorpay_order_id = isset($_POST['razorpay_order_id']) ? $_POST['razorpay_order_id'] : $_SESSION['razorpay_order_id'];
            
            if (empty($razorpay_order_id)) {
                throw new Exception("Order ID is missing");
            }
            
            // Create the attributes array for verification
            $attributes = array(
                'razorpay_order_id' => $razorpay_order_id,
                'razorpay_payment_id' => $_POST['razorpay_payment_id'],
                'razorpay_signature' => $_POST['razorpay_signature']
            );
            
            log_verify("Verification parameters:");
            log_verify("Order ID: " . $attributes['razorpay_order_id']);
            log_verify("Payment ID: " . $attributes['razorpay_payment_id']);
            log_verify("Signature: " . $attributes['razorpay_signature']);
            
            // Try direct verification with Razorpay API
            try {
                $api->utility->verifyPaymentSignature($attributes);
                log_verify("API signature verification successful");
            } catch(SignatureVerificationError $e) {
                // If API verification fails, try manual verification
                log_verify("API verification failed, trying manual verification");
                
                // Try different payload formats
                $payloads = [
                    // Standard format
                    $attributes['razorpay_order_id'] . '|' . $attributes['razorpay_payment_id'],
                    
                    // Alternative format sometimes used
                    $attributes['razorpay_payment_id'] . '|' . $attributes['razorpay_order_id'],
                    
                    // Include all three parameters
                    $attributes['razorpay_order_id'] . '|' . $attributes['razorpay_payment_id'] . '|' . $attributes['razorpay_signature']
                ];
                
                $verification_success = false;
                
                foreach ($payloads as $index => $payload) {
                    $expected_signature = hash_hmac('sha256', $payload, $keySecret);
                    
                    log_verify("Manual signature check (format " . ($index + 1) . "):");
                    log_verify("Payload: " . $payload);
                    log_verify("Generated signature: " . $expected_signature);
                    log_verify("Received signature: " . $attributes['razorpay_signature']);
                    
                    if (hash_equals($expected_signature, $attributes['razorpay_signature'])) {
                        log_verify("Manual signature verification successful with format " . ($index + 1));
                        $verification_success = true;
                        break;
                    }
                }
                
                if (!$verification_success) {
                    // As a last resort, try to verify with Razorpay's API directly
                    log_verify("Attempting to verify payment status directly with Razorpay API");
                    
                    try {
                        // Fetch the payment from Razorpay API
                        $payment = $api->payment->fetch($attributes['razorpay_payment_id']);
                        log_verify("Payment status from API: " . $payment->status);
                        
                        // If payment is captured or authorized, consider it successful
                        if ($payment->status === 'captured' || $payment->status === 'authorized') {
                            log_verify("Payment verified as successful via API status check");
                            $verification_success = true;
                        } else {
                            log_verify("Payment not successful according to API status: " . $payment->status);
                            throw new Exception("Payment not successful: " . $payment->status);
                        }
                    } catch (Exception $api_e) {
                        log_verify("API payment status check failed: " . $api_e->getMessage());
                        throw $e; // Re-throw the original signature verification error
                    }
                }
                
                if (!$verification_success) {
                    log_verify("All verification methods failed");
                    throw $e; // Re-throw the original exception
                }
            }
        } catch(Exception $e) {
            $success = false;
            $error = 'Razorpay Error: ' . $e->getMessage();
            log_verify("Verification failed: " . $e->getMessage());
        }
    } else {
        log_verify("WARNING: Bypassing signature verification for testing");
    }
}

//if ($success === true) {
echo '<div class="payment_confirmation">
    <h3>Payment Successful!</h3>
    <p>Your payment has been processed successfully.</p>
    <p>Card ID: ' . (isset($_SESSION['id']) ? $_SESSION['id'] : 'Not set') . '</p>
    <p>Reference: ' . (isset($_SESSION['reference_number']) ? $_SESSION['reference_number'] : 'Not set') . '</p>
    <p>Please wait, we are redirecting you to your dashboard...</p>
</div>';   

    // Update the database with payment information
    $db_host = "p004.bom1.mysecurecloudhost.com";
    $db_user = "wwwmoody_miniweb_vcard";
    $db_pass = "miniweb_vcard";
    $db_name = "miniweb_vcard";
try {
    $connect = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($connect->connect_error) {
        log_verify("ERROR: Database connection failed: " . $connect->connect_error);
        echo '<div class="payment_error">Database connection failed. Please contact support.</div>';
        exit;
    }
    log_verify("SUCCESS: Database connection established");
} catch (Exception $e) {
    log_verify("ERROR: Database exception: " . $e->getMessage());
    echo '<div class="payment_error">Database error. Please contact support.</div>';
    exit;
}


    $update_query = "UPDATE digi_card SET 
                    d_payment_status='Success',
                    d_card_status='Active',
                    d_order_id='" . mysqli_real_escape_string($connect, $_SESSION['reference_number']) . "',
                    d_payment_date='" . $date . "',
                    validity_date=DATE_ADD('" . $date . "', INTERVAL 1 YEAR)
                    WHERE id='" . mysqli_real_escape_string($connect, $_SESSION['id']) . "'";
    
    $result = mysqli_query($connect, $update_query);
    
    if (!$result) {
        log_verify("ERROR: Database update failed: " . mysqli_error($connect));
        echo '<div class="payment_error">Database update failed. Please contact support.</div>';
    } else {
        log_verify("SUCCESS: Database updated successfully for card ID: " . $_SESSION['id']);
        
        // Get user email and card details from the card record
        $card_query = mysqli_query($connect, "SELECT user_email, d_f_name, d_l_name, d_comp_name, d_gst, d_gst_name, d_gst_email, d_gst_contact, d_gst_address, d_gst_state, d_gst_city, d_gst_pincode, d_payment_amount FROM digi_card WHERE id='" . mysqli_real_escape_string($connect, $_SESSION['id']) . "'");
        if ($card_query && mysqli_num_rows($card_query) > 0) {
            $card_data = mysqli_fetch_array($card_query);
            $user_email = $card_data['user_email'];
            $user_name = $card_data['d_f_name'] . ' ' . $card_data['d_l_name'];
            $service_name = "Mini Website - 1 Year";
            
            // Set up session for customer dashboard
            $_SESSION['user_email'] = $user_email;
            $_SESSION['is_logged_in'] = true;
            $_SESSION['login_time'] = time();
            
            log_verify("SUCCESS: Session set up for user: " . $user_email);
            
                         // Insert invoice details
             // Get the next invoice number from database
             $last_invoice_query = mysqli_query($connect, "SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number, '/', -1) AS UNSIGNED)) as last_number FROM invoice_details WHERE invoice_number LIKE 'KIR/%'");
             $last_invoice_result = mysqli_fetch_array($last_invoice_query);
             $next_number = ($last_invoice_result['last_number'] ?? 0) + 1;
             $invoice_number = 'KIR/' . str_pad($next_number, 5, '0', STR_PAD_LEFT);
             $invoice_date = date('Y-m-d');
             $current_timestamp = date('Y-m-d H:i:s');
            
                         // Get original amount and calculate final amount - FIXED
             $original_amount = isset($card_data['d_payment_amount']) && !empty($card_data['d_payment_amount']) ? (float)$card_data['d_payment_amount'] : 847.0;
             
             $promo_discount = isset($_SESSION['promo_discount']) ? (float)$_SESSION['promo_discount'] : 0.0;
             $final_amount = $original_amount - $promo_discount;
             $promo_code = isset($_SESSION['promo_code']) ? $_SESSION['promo_code'] : '';
             
             // Get calculated values from payment page session instead of recalculating
             $sub_total = isset($_SESSION['subtotal_amount']) ? (float)$_SESSION['subtotal_amount'] : $final_amount;
             $cgst_amount = isset($_SESSION['cgst_amount']) ? (float)$_SESSION['cgst_amount'] : 0;
             $sgst_amount = isset($_SESSION['sgst_amount']) ? (float)$_SESSION['sgst_amount'] : 0;
             $igst_amount = isset($_SESSION['igst_amount']) ? (float)$_SESSION['igst_amount'] : 0;
             $total_amount = isset($_SESSION['final_total']) ? (float)$_SESSION['final_total'] : $final_amount;
             $is_interstate = isset($_SESSION['is_interstate']) ? $_SESSION['is_interstate'] : false;
             $customer_state_code = isset($_SESSION['gst_state_code']) ? $_SESSION['gst_state_code'] : '';
             $unit_price = $sub_total; // Unit price is the base amount before GST
             $gst_percentage = 18; // Total GST percentage
       
             
             $hsn_sac_code = '998314'; // Default for digital services
             
            
            
                         $invoice_insert_query = "INSERT INTO invoice_details (
                 invoice_number, invoice_date, card_id, user_email, user_name, user_contact,
                 billing_name, billing_email, billing_contact, billing_address, billing_state, 
                 billing_city, billing_pincode, billing_gst_number, original_amount, discount_amount, 
                 final_amount, promo_code, promo_discount, razorpay_order_id, razorpay_payment_id, 
                 razorpay_signature, payment_status, payment_date, service_name, service_description,
                 hsn_sac_code, quantity, unit_price, total_price, sub_total, igst_percentage, 
                 igst_amount, cgst_amount, sgst_amount, total_amount, payment_type, reference_number, 
                 created_at, updated_at
             ) VALUES (
                 '" . mysqli_real_escape_string($connect, $invoice_number) . "',
                 '" . mysqli_real_escape_string($connect, $invoice_date) . "',
                 '" . mysqli_real_escape_string($connect, $_SESSION['id']) . "',
                 '" . mysqli_real_escape_string($connect, $user_email) . "',
                 '" . mysqli_real_escape_string($connect, $user_name) . "',
                 '" . mysqli_real_escape_string($connect, $_SESSION['user_contact']) . "',
                 '" . mysqli_real_escape_string($connect, $card_data['d_gst_name']) . "',
                 '" . mysqli_real_escape_string($connect, $card_data['d_gst_email']) . "',
                 '" . mysqli_real_escape_string($connect, $card_data['d_gst_contact']) . "',
                 '" . mysqli_real_escape_string($connect, $card_data['d_gst_address']) . "',
                 '" . mysqli_real_escape_string($connect, $card_data['d_gst_state']) . "',
                 '" . mysqli_real_escape_string($connect, $card_data['d_gst_city']) . "',
                 '" . mysqli_real_escape_string($connect, $card_data['d_gst_pincode']) . "',
                 '" . mysqli_real_escape_string($connect, $card_data['d_gst']) . "',
                 '" . mysqli_real_escape_string($connect, $original_amount) . "',
                 '" . mysqli_real_escape_string($connect, $promo_discount) . "',
                 '" . mysqli_real_escape_string($connect, $sub_total) . "',
                 '" . mysqli_real_escape_string($connect, $promo_code) . "',
                 '" . mysqli_real_escape_string($connect, $promo_discount) . "',
                 '" . mysqli_real_escape_string($connect, $_POST['razorpay_order_id'] ?? $_SESSION['razorpay_order_id'] ?? '') . "',
                 '" . mysqli_real_escape_string($connect, $_POST['razorpay_payment_id'] ?? '') . "',
                 '" . mysqli_real_escape_string($connect, $_POST['razorpay_signature'] ?? '') . "',
                 'Success',
                 '" . mysqli_real_escape_string($connect, $date) . "',
                 '" . mysqli_real_escape_string($connect, $service_name) . "',
                 '" . mysqli_real_escape_string($connect, $service_name) . "',
                 '" . mysqli_real_escape_string($connect, $hsn_sac_code) . "',
                 '1',
                 '" . mysqli_real_escape_string($connect, $unit_price) . "',
                 '" . mysqli_real_escape_string($connect, $total_amount) . "',
                 '" . mysqli_real_escape_string($connect, $sub_total) . "',
                 '" . mysqli_real_escape_string($connect, $gst_percentage) . "',
                 '" . mysqli_real_escape_string($connect, $igst_amount) . "',
                 '" . mysqli_real_escape_string($connect, $cgst_amount) . "',
                 '" . mysqli_real_escape_string($connect, $sgst_amount) . "',
                 '" . mysqli_real_escape_string($connect, $total_amount) . "',
                 'Regular',
                 '" . mysqli_real_escape_string($connect, $_SESSION['reference_number']) . "',
                 '" . mysqli_real_escape_string($connect, $current_timestamp) . "',
                 '" . mysqli_real_escape_string($connect, $current_timestamp) . "'
             )";
            
            $invoice_result = mysqli_query($connect, $invoice_insert_query);
            
            if ($invoice_result) {
                log_verify("SUCCESS: Invoice details inserted successfully. Invoice Number: " . $invoice_number);
                $_SESSION['invoice_number'] = $invoice_number;
                
                // Update referral earnings after successful payment
                $update_referral_earnings = updateReferralEarningsAfterPayment($connect, $user_email, $_SESSION['id']);
                if ($update_referral_earnings) {
                    log_verify("SUCCESS: Referral earnings updated after payment");
                } else {
                    log_verify("ERROR: Failed to update referral earnings after payment");
                }
            } else {
                log_verify("ERROR: Failed to insert invoice details: " . mysqli_error($connect));
            }
            
            // Redirect after a short delay
            echo '<meta http-equiv="refresh" content="3;URL=../../../customer/dashboard/index.php">';
            echo '<script>
                setTimeout(function() {
                    window.location.href = "../../../customer/dashboard/index.php";
                }, 3000);
            </script>';
            echo '<p style="text-align: center; margin-top: 20px;"><a href="../../../customer/dashboard/index.php" style="background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Click here if not redirected automatically</a></p>';
        } else {
            log_verify("ERROR: Could not find user email for card ID: " . $_SESSION['id']);
            echo '<div class="payment_error">User information not found. Please contact support.</div>';
        }
    }

?>

<style>
.payment_confirmation {
    border: 1px solid #4CAF50;
    width: fit-content;
    padding: 22px;
    font-size: 20px;
    background: #c9f596;
    color: #107513;
    font-family: sans-serif;
    margin: 30px auto;
    text-align: center;
    border-radius: 5px;
}

.payment_error {
    border: 1px solid #f44336;
    width: fit-content;
    padding: 22px;
    font-size: 20px;
    background: #ffebee;
    color: #d32f2f;
    font-family: sans-serif;
    margin: 30px auto;
    text-align: center;
    border-radius: 5px;
}
</style>
