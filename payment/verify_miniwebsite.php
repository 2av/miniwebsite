<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../app/config/database.php';

// Log session data for debugging
error_log("Verify.php - Session data: " . print_r($_SESSION, true));
error_log("Verify.php - POST data: " . print_r($_POST, true));

// Use Razorpay SDK from local razorpay-php folder
$razorpay_path = __DIR__ . '/razorpay-php/Razorpay.php';

if (!file_exists($razorpay_path)) {
    die('Payment SDK Error: Razorpay library not found. Please contact administrator.');
}

require_once($razorpay_path);
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

// Include PHPMailer and email configuration
require_once __DIR__ . '/../common/email_config.php';
require_once __DIR__ . '/../common/mailtemplate/franchisee_email_templates.php';

// Check if PHPMailer is available, if not use basic mail function
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    $phpmailer_available = true;
    error_log("PHPMailer loaded successfully from vendor/autoload.php");
} else {
    $phpmailer_available = false;
    error_log("PHPMailer not found, email functionality will be limited");
}

// Function to update referral earnings after payment completion
function updateReferralEarningsAfterPayment($connect, $user_email, $card_id) {
    // Get user's referral information
    $user_query = mysqli_query($connect, "SELECT referred_by FROM franchisee_login WHERE f_user_email = '" . mysqli_real_escape_string($connect, $user_email) . "'");
    
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
                $new_amount = getLatestReferralAmount($connect, $referred_by, 'Franchisee');
                
                // Only update if amount has changed
                if ($new_amount != $current_amount) {
                    $update_query = mysqli_query($connect, "UPDATE referral_earnings SET 
                        amount = '$new_amount',
                        updated_date = NOW()
                        WHERE id = '" . $existing_earning['id'] . "'");
                    
                    if ($update_query) {
                        error_log("Updated referral earning for franchisee: $user_email, old amount: $current_amount, new amount: $new_amount");
                        return true;
                    }
                }
            } else {
                // Create new referral earning if it doesn't exist
                $new_amount = getLatestReferralAmount($connect, $referred_by, 'Franchisee');
                
                $insert_query = mysqli_query($connect, "INSERT INTO referral_earnings 
                    (referrer_email, referred_email, referral_date, status, amount, is_collaboration) 
                    VALUES ('" . mysqli_real_escape_string($connect, $referred_by) . "', 
                            '" . mysqli_real_escape_string($connect, $user_email) . "', 
                            NOW(), 'Pending', '$new_amount', 'YES')");
                
                if ($insert_query) {
                    error_log("Created new referral earning for franchisee: $user_email, amount: $new_amount");
                    return true;
                }
            }
        }
    }
    
    return false;
}

function ensureInvoicePlanColumns($connect) {
    $required = [
        'plan_name' => "VARCHAR(120) DEFAULT NULL",
        'plan_validity' => "VARCHAR(80) DEFAULT NULL",
    ];
    foreach ($required as $col => $ddl) {
        $res = @mysqli_query($connect, "SHOW COLUMNS FROM invoice_details LIKE '" . mysqli_real_escape_string($connect, $col) . "'");
        if (!$res || mysqli_num_rows($res) === 0) {
            @mysqli_query($connect, "ALTER TABLE invoice_details ADD COLUMN $col $ddl");
        }
    }
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

// Function to send email using PHPMailer or basic mail
function sendEmail($to, $subject, $message, $name = '') {
    global $phpmailer_available;
    
    if ($phpmailer_available && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            // Create a new PHPMailer instance
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = SMTP_AUTH;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';
            
            // Additional SMTP settings for better compatibility
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Recipients
            $mail->setFrom(SMTP_USERNAME, 'MiniWebsite Support');
            $mail->addAddress($to, $name);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->AltBody = strip_tags(str_replace('<br>', "\n", $message));
            
            // Send the email
            return $mail->send();
        } catch (\Exception $e) {
            error_log("PHPMailer failed: " . $e->getMessage());
            // Fallback to basic mail
            return sendBasicEmail($to, $subject, $message);
        }
    } else {
        // Use basic mail function as fallback
        return sendBasicEmail($to, $subject, $message);
    }
}

// Check if basic mail function is properly configured
function isBasicMailConfigured() {
    // Check if sendmail is configured
    $sendmail_path = ini_get('sendmail_path');
    return !empty($sendmail_path) && $sendmail_path !== 'sendmail -t -i';
}

// Fallback email function using basic PHP mail
function sendBasicEmail($to, $subject, $message) {
    // Check if basic mail is properly configured
    if (!isBasicMailConfigured()) {
        error_log("Basic mail function not properly configured, skipping email to: $to");
        return false;
    }
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . SMTP_USERNAME . "\r\n";
    
    try {
        // Suppress warnings for mail function since we're using it as fallback
        $result = @mail($to, $subject, $message, $headers);
        
        if (!$result) {
            error_log("Basic mail function failed for: $to");
        }
        
        return $result;
    } catch (\Exception $e) {
        error_log("Basic mail failed: " . $e->getMessage());
        return false;
    }
}

// Razorpay credentials
$keyId = 'rzp_live_xU57a1JhH7To1G';
$keySecret = 'VHJzQnCxqF5HTNsE3LUTZtnI';

$success = true;
$error = "Payment Failed";

// TEST MODE - Bypass payment verification for testing
$test_mode = false; // Set to false for production
$test_emails = ['akhileshfr@yopmail.com', 'test@example.com']; // Add test emails

if ($test_mode && isset($_SESSION['user_email']) && in_array($_SESSION['user_email'], $test_emails)) {
    // Force success for test emails
    $success = true;
    error_log("TEST MODE: Payment verification bypassed for " . $_SESSION['user_email']);
} else {
    // Normal payment verification
    if (empty($_POST['razorpay_payment_id'])) {
        $success = false;
        $error = "Payment information missing. Please try again.";
    } else {
        $api = new Api($keyId, $keySecret);

        try {
            $order_id = $_POST['razorpay_order_id'] ?? $_SESSION['razorpay_order_id'] ?? '';
            if ($order_id !== '') {
                $_SESSION['razorpay_order_id'] = $order_id;
            }
            // Verify the payment signature (order_id must match the one used at checkout; POST is authoritative)
            $attributes = array(
                'razorpay_order_id' => $order_id,
                'razorpay_payment_id' => $_POST['razorpay_payment_id'],
                'razorpay_signature' => $_POST['razorpay_signature']
            );

            $api->utility->verifyPaymentSignature($attributes);
        } catch(SignatureVerificationError $e) {
            $success = false;
            $error = 'Razorpay Error: ' . $e->getMessage();
        }
    }
}

if ($success === true) {
    $date = date('Y-m-d H:i:s');
    ensureInvoicePlanColumns($connect);

    if (!isset($_SESSION['reference_number']) || $_SESSION['reference_number'] === '') {
        if (!empty($_POST['shopping_order_id'])) {
            $_SESSION['reference_number'] = preg_replace('/[^A-Za-z0-9\/\-_.]/', '', (string) $_POST['shopping_order_id']);
        }
    }

    // Mini website customer/card payment (?id= on pay_miniwebsite.php) — not franchise
    if (($_SESSION['service_type'] ?? '') === 'card_payment' && !empty($_SESSION['card_id'])) {
        if (!isset($_SESSION['reference_number']) || $_SESSION['reference_number'] === '') {
            error_log('ERROR: reference_number missing (card payment)');
            die('Payment verification failed: Missing reference number. Please try again.');
        }
        $card_id_int = (int) $_SESSION['card_id'];
        if ($card_id_int < 1) {
            die('Payment verification failed: Invalid card session. Please try again.');
        }
        $cid_esc = mysqli_real_escape_string($connect, (string) $card_id_int);
        $amount_paid = isset($_SESSION['final_total']) ? (float) $_SESSION['final_total'] : (float) ($_SESSION['amount'] ?? 0);
        $amt_esc = mysqli_real_escape_string($connect, (string) $amount_paid);
        $date_esc = mysqli_real_escape_string($connect, $date);
        $upd_card = mysqli_query($connect, "UPDATE digi_card SET 
            d_payment_status = 'Success',
            d_payment_date = '$date_esc',
            d_card_status = 'Active',
            d_payment_amount = '$amt_esc',
            validity_date = DATE_ADD(NOW(), INTERVAL 1 YEAR)
            WHERE id = '$cid_esc' LIMIT 1");
        if (!$upd_card) {
            error_log('verify_miniwebsite card: digi_card update failed: ' . mysqli_error($connect));
            die('Payment verification failed: Could not activate your website. Please contact support with your Razorpay payment ID.');
        }

        $last_invoice_query = mysqli_query($connect, "SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number, '/', -1) AS UNSIGNED)) as last_number FROM invoice_details WHERE invoice_number LIKE 'KIR/%'");
        $last_invoice_result = mysqli_fetch_array($last_invoice_query);
        $next_number = ($last_invoice_result['last_number'] ?? 0) + 1;
        $invoice_number = 'KIR/' . str_pad((string) $next_number, 5, '0', STR_PAD_LEFT);
        $invoice_date = date('Y-m-d');
        $current_timestamp = date('Y-m-d H:i:s');

        $original_amount = isset($_SESSION['original_amount']) ? (float) $_SESSION['original_amount'] : $amount_paid;
        $discount_amount = isset($_SESSION['discount_amount']) ? (float) $_SESSION['discount_amount'] : 0.0;
        $subtotal_amount = isset($_SESSION['subtotal_amount']) ? (float) $_SESSION['subtotal_amount'] : max(0.0, $original_amount - $promo_discount);
        $cgst_amount = isset($_SESSION['cgst_amount']) ? (float) $_SESSION['cgst_amount'] : 0.0;
        $sgst_amount = isset($_SESSION['sgst_amount']) ? (float) $_SESSION['sgst_amount'] : 0.0;
        $igst_amount = isset($_SESSION['igst_amount']) ? (float) $_SESSION['igst_amount'] : 0.0;
        $final_total = isset($_SESSION['final_total']) ? (float) $_SESSION['final_total'] : $amount_paid;
        $promo_code = $_SESSION['promo_code'] ?? '';
        $promo_discount = isset($_SESSION['promo_discount']) ? (float) $_SESSION['promo_discount'] : 0.0;

        $bill_name = $_SESSION['billing_gst_name'] ?? $_SESSION['user_name'] ?? '';
        $bill_email = $_SESSION['billing_gst_email'] ?? $_SESSION['user_email'] ?? '';
        $bill_contact = $_SESSION['billing_gst_contact'] ?? $_SESSION['user_contact'] ?? '';
        $bill_addr = $_SESSION['billing_gst_address'] ?? '';
        $bill_state = $_SESSION['billing_gst_state'] ?? '';
        $bill_city = $_SESSION['billing_gst_city'] ?? '';
        $bill_pin = $_SESSION['billing_gst_pincode'] ?? '';
        $bill_gst = $_SESSION['billing_gst_number'] ?? '';

        $u_email = $_SESSION['user_email'] ?? '';
        $u_name = $_SESSION['user_name'] ?? '';
        $u_contact = $_SESSION['user_contact'] ?? '';

        $rz_order = $_POST['razorpay_order_id'] ?? $_SESSION['razorpay_order_id'] ?? $_SESSION['reference_number'];
        $rz_pay = $_POST['razorpay_payment_id'] ?? '';
        $rz_sig = $_POST['razorpay_signature'] ?? '';
        $ref_num = $_SESSION['reference_number'];

        $unit_price = $final_total;
        $total_price = $final_total;
        $sub_total = $subtotal_amount;
        $total_amount = $final_total;
        $gst_percentage = 18;
        $hsn_sac_code = '998314';
        $service_name = 'Mini Website Subscription';
        $plan_name = isset($_SESSION['invoice_plan_name']) ? trim((string)$_SESSION['invoice_plan_name']) : '';
        $plan_validity = isset($_SESSION['invoice_plan_validity']) ? trim((string)$_SESSION['invoice_plan_validity']) : '';
        if ($plan_name === '' || $plan_validity === '') {
            $plan_from_amount = [
                500 => ['Mini Website Plan', '6 Months'],
                847 => ['Mini Website Plan', '1 Year'],
                1500 => ['Mini Website Plan', '2 Years'],
                2100 => ['Mini Website Plan', '3 Years'],
            ];
            $k = (int)round($original_amount);
            if (isset($plan_from_amount[$k])) {
                $plan_name = $plan_name === '' ? $plan_from_amount[$k][0] : $plan_name;
                $plan_validity = $plan_validity === '' ? $plan_from_amount[$k][1] : $plan_validity;
            }
        }

        $invoice_insert_query = "INSERT INTO invoice_details (
            invoice_number, invoice_date, card_id, user_email, user_name, user_contact,
            billing_name, billing_email, billing_contact, billing_address, billing_state, 
            billing_city, billing_pincode, billing_gst_number, original_amount, discount_amount, 
            final_amount, promo_code, promo_discount, razorpay_order_id, razorpay_payment_id, 
            razorpay_signature, payment_status, payment_date, service_name, service_description,
            hsn_sac_code, quantity, unit_price, total_price, sub_total, igst_percentage, 
            igst_amount, cgst_amount, sgst_amount, total_amount, payment_type, reference_number, created_at, updated_at
            , plan_name, plan_validity
        ) VALUES (
            '" . mysqli_real_escape_string($connect, $invoice_number) . "',
            '" . mysqli_real_escape_string($connect, $invoice_date) . "',
            '" . $cid_esc . "',
            '" . mysqli_real_escape_string($connect, $u_email) . "',
            '" . mysqli_real_escape_string($connect, $u_name) . "',
            '" . mysqli_real_escape_string($connect, $u_contact) . "',
            '" . mysqli_real_escape_string($connect, $bill_name) . "',
            '" . mysqli_real_escape_string($connect, $bill_email) . "',
            '" . mysqli_real_escape_string($connect, $bill_contact) . "',
            '" . mysqli_real_escape_string($connect, $bill_addr) . "',
            '" . mysqli_real_escape_string($connect, $bill_state) . "',
            '" . mysqli_real_escape_string($connect, $bill_city) . "',
            '" . mysqli_real_escape_string($connect, $bill_pin) . "',
            '" . mysqli_real_escape_string($connect, $bill_gst) . "',
            '" . mysqli_real_escape_string($connect, $original_amount) . "',
            '" . mysqli_real_escape_string($connect, $discount_amount) . "',
            '" . mysqli_real_escape_string($connect, $final_total) . "',
            '" . mysqli_real_escape_string($connect, $promo_code) . "',
            '" . mysqli_real_escape_string($connect, $promo_discount) . "',
            '" . mysqli_real_escape_string($connect, $rz_order) . "',
            '" . mysqli_real_escape_string($connect, $rz_pay) . "',
            '" . mysqli_real_escape_string($connect, $rz_sig) . "',
            'Success',
            '" . mysqli_real_escape_string($connect, $date) . "',
            '" . mysqli_real_escape_string($connect, $service_name) . "',
            '" . mysqli_real_escape_string($connect, $service_name) . "',
            '" . mysqli_real_escape_string($connect, $hsn_sac_code) . "',
            '1',
            '" . mysqli_real_escape_string($connect, $unit_price) . "',
            '" . mysqli_real_escape_string($connect, $total_price) . "',
            '" . mysqli_real_escape_string($connect, $sub_total) . "',
            '" . mysqli_real_escape_string($connect, $gst_percentage) . "',
            '" . mysqli_real_escape_string($connect, $igst_amount) . "',
            '" . mysqli_real_escape_string($connect, $cgst_amount) . "',
            '" . mysqli_real_escape_string($connect, $sgst_amount) . "',
            '" . mysqli_real_escape_string($connect, $total_amount) . "',
            'Regular',
            '" . mysqli_real_escape_string($connect, $ref_num) . "',
            '" . mysqli_real_escape_string($connect, $current_timestamp) . "',
            '" . mysqli_real_escape_string($connect, $current_timestamp) . "',
            '" . mysqli_real_escape_string($connect, $plan_name) . "',
            '" . mysqli_real_escape_string($connect, $plan_validity) . "'
        )";

        $invoice_result = mysqli_query($connect, $invoice_insert_query);
        if (!$invoice_result) {
            error_log('verify_miniwebsite card: invoice insert failed: ' . mysqli_error($connect));
        }

        $_SESSION['invoice_number'] = $invoice_number;
        $payment_id = $rz_pay !== '' ? $rz_pay : ('pay_' . time());
        echo '<div class="payment_confirmation">Your Payment Successful. Please wait we are redirecting...</div>';
        echo '<meta http-equiv="refresh" content="3;URL=../user/invoice/download_receipt.php?ref=' . rawurlencode($ref_num) . '&payment_id=' . rawurlencode($payment_id) . '">';
        exit;
    }

    $ref = $_SESSION['reference_number'] ?? '';
    $franchise_flow = ($_SESSION['service_type'] ?? '') === 'franchise_registration'
        || (is_string($ref) && strncmp($ref, 'FRAN', 4) === 0);

    if (!isset($_SESSION['franchise_registration_data']) && $franchise_flow) {
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
            error_log("verify_miniwebsite: rebuilt franchise_registration_data from session (flat keys)");
        }
    }
    
    // Check if required session variables exist
    if (!isset($_SESSION['franchise_registration_data'])) {
        error_log("ERROR: franchise_registration_data not found in session");
        die("Payment verification failed: Missing registration data. Please try again.");
    }
    
    if (!isset($_SESSION['reference_number']) || $_SESSION['reference_number'] === '') {
        error_log("ERROR: reference_number not found in session");
        die("Payment verification failed: Missing reference number. Please try again.");
    }
    
    // Insert franchisee account after successful payment
    if (isset($_SESSION['franchise_registration_data'])) {
        $reg_data = $_SESSION['franchise_registration_data'];
        
        // Debug log
        error_log("Processing franchisee registration for: " . $reg_data['email']);
        
        // Check if franchisee already exists
        $check_query = "SELECT * FROM franchisee_login WHERE f_user_email = '" . mysqli_real_escape_string($connect, $reg_data['email']) . "'";
        $check_result = mysqli_query($connect, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            // Update existing franchisee record
            $update_franchisee = "UPDATE franchisee_login SET 
                f_user_name = '" . mysqli_real_escape_string($connect, $reg_data['name']) . "',
                f_user_password = '" . mysqli_real_escape_string($connect, $reg_data['password']) . "',
                f_user_contact = '" . mysqli_real_escape_string($connect, $reg_data['contact']) . "',
                f_user_address = '" . mysqli_real_escape_string($connect, $reg_data['address']) . "',
                f_user_state = '" . mysqli_real_escape_string($connect, $reg_data['state']) . "',
                f_user_city = '" . mysqli_real_escape_string($connect, $reg_data['city']) . "',
                f_user_pincode = '" . mysqli_real_escape_string($connect, $reg_data['pincode']) . "',
                f_user_gst = '" . mysqli_real_escape_string($connect, $reg_data['gst_number']) . "',
                f_user_active = 'YES'
                WHERE f_user_email = '" . mysqli_real_escape_string($connect, $reg_data['email']) . "'";
            
            $franchisee_result = mysqli_query($connect, $update_franchisee);
            error_log("Updated existing franchisee: " . ($franchisee_result ? "Success" : "Failed - " . mysqli_error($connect)));
        } else {
            // Insert new franchisee record
            $token = rand(1000000000, 99999999999999999);
            
            // Check if required columns exist in franchisee_login table
            $columns_check = mysqli_query($connect, "SHOW COLUMNS FROM franchisee_login LIKE 'f_user_address'");
            if (mysqli_num_rows($columns_check) == 0) {
                // Add missing columns
                mysqli_query($connect, "ALTER TABLE franchisee_login ADD COLUMN f_user_address VARCHAR(255) DEFAULT ''");
                mysqli_query($connect, "ALTER TABLE franchisee_login ADD COLUMN f_user_state VARCHAR(100) DEFAULT ''");
                mysqli_query($connect, "ALTER TABLE franchisee_login ADD COLUMN f_user_city VARCHAR(100) DEFAULT ''");
                mysqli_query($connect, "ALTER TABLE franchisee_login ADD COLUMN f_user_pincode VARCHAR(20) DEFAULT ''");
                mysqli_query($connect, "ALTER TABLE franchisee_login ADD COLUMN f_user_gst VARCHAR(50) DEFAULT ''");
                mysqli_query($connect, "ALTER TABLE franchisee_login ADD COLUMN referral_code VARCHAR(50) DEFAULT ''");
                mysqli_query($connect, "ALTER TABLE franchisee_login ADD COLUMN referred_by VARCHAR(255) DEFAULT ''");
            }
            
            $insert_franchisee = "INSERT INTO franchisee_login (
                f_user_email,
                f_user_name, 
                f_user_password,
                f_user_contact,
                f_user_address,
                f_user_state,
                f_user_city,
                f_user_pincode,
                f_user_gst,
                f_user_active,
                f_user_token,
                referral_code,
                referred_by
            ) VALUES (
                '" . mysqli_real_escape_string($connect, $reg_data['email']) . "',
                '" . mysqli_real_escape_string($connect, $reg_data['name']) . "',
                '" . mysqli_real_escape_string($connect, $reg_data['password']) . "',
                '" . mysqli_real_escape_string($connect, $reg_data['contact']) . "',
                '" . mysqli_real_escape_string($connect, $reg_data['address']) . "',
                '" . mysqli_real_escape_string($connect, $reg_data['state']) . "',
                '" . mysqli_real_escape_string($connect, $reg_data['city']) . "',
                '" . mysqli_real_escape_string($connect, $reg_data['pincode']) . "',
                '" . mysqli_real_escape_string($connect, $reg_data['gst_number']) . "',
                'YES',
                '" . $token . "',
                '" . ($reg_data['referral_code'] ?? '') . "',
                '" . mysqli_real_escape_string($connect, $reg_data['referred_by'] ?? '') . "'
            )";
            
            $franchisee_result = mysqli_query($connect, $insert_franchisee);
            error_log("Inserted new franchisee: " . ($franchisee_result ? "Success" : "Failed - " . mysqli_error($connect)));
            
            if (!$franchisee_result) {
                error_log("SQL Error: " . mysqli_error($connect));
                error_log("SQL Query: " . $insert_franchisee);
            }
        }

        // Insert payment record into franchise_payments table
        if ($franchisee_result) {
            $payment_insert = "INSERT INTO franchise_payments (
                franchise_email,
                reference_number,
                razorpay_payment_id,
                razorpay_order_id,
                amount,
                currency,
                payment_status,
                payment_method,
                payment_date,
                ip_address,
                user_agent
            ) VALUES (
                '" . mysqli_real_escape_string($connect, $reg_data['email']) . "',
                '" . $_SESSION['reference_number'] . "',
                '" . ($_POST['razorpay_payment_id'] ?? 'test_payment') . "',
                '" . ($_SESSION['razorpay_order_id'] ?? $_SESSION['reference_number']) . "',
                '" . $_SESSION['amount'] . "',
                'INR',
                'Success',
                'Razorpay',
                '" . $date . "',
                '" . $_SERVER['REMOTE_ADDR'] . "',
                '" . mysqli_real_escape_string($connect, $_SERVER['HTTP_USER_AGENT'] ?? '') . "'
            )";
            
            $payment_result = mysqli_query($connect, $payment_insert);
            error_log("Payment record inserted: " . ($payment_result ? "Success" : "Failed - " . mysqli_error($connect)));
            
            // Insert invoice details for franchisee
            if ($payment_result) {
                // Get the next invoice number from database
                $last_invoice_query = mysqli_query($connect, "SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number, '/', -1) AS UNSIGNED)) as last_number FROM invoice_details WHERE invoice_number LIKE 'KIR/%'");
                $last_invoice_result = mysqli_fetch_array($last_invoice_query);
                $next_number = ($last_invoice_result['last_number'] ?? 0) + 1;
                $invoice_number = 'KIR/' . str_pad($next_number, 5, '0', STR_PAD_LEFT);
                $invoice_date = date('Y-m-d');
                $current_timestamp = date('Y-m-d H:i:s');
                
                // Get pre-calculated amounts from session (no calculation needed)
                $original_amount = isset($_SESSION['original_amount']) ? (float)$_SESSION['original_amount'] : 30000.0;
                $discount_amount = isset($_SESSION['discount_amount']) ? (float)$_SESSION['discount_amount'] : 0.0;
                $subtotal_amount = isset($_SESSION['subtotal_amount']) ? (float)$_SESSION['subtotal_amount'] : 30000.0;
                $cgst_amount = isset($_SESSION['cgst_amount']) ? (float)$_SESSION['cgst_amount'] : 2700.0;
                $sgst_amount = isset($_SESSION['sgst_amount']) ? (float)$_SESSION['sgst_amount'] : 2700.0;
                $igst_amount = isset($_SESSION['igst_amount']) ? (float)$_SESSION['igst_amount'] : 0.0;
                $final_total = isset($_SESSION['final_total']) ? (float)$_SESSION['final_total'] : 35400.0;
                $promo_code = isset($_SESSION['promo_code']) ? $_SESSION['promo_code'] : '';
                $promo_discount = isset($_SESSION['promo_discount']) ? (float)$_SESSION['promo_discount'] : 0.0;
                
                // Debug the payment amounts
                error_log("Franchisee Payment Amounts from Session:");
                error_log("Original Amount: " . $original_amount);
                error_log("Discount Amount: " . $discount_amount);
                error_log("Subtotal Amount: " . $subtotal_amount);
                error_log("CGST Amount: " . $cgst_amount);
                error_log("SGST Amount: " . $sgst_amount);
                error_log("IGST Amount: " . $igst_amount);
                error_log("Final Total: " . $final_total);
                
                // Use pre-calculated values directly (already calculated with proper GST logic)
                $unit_price = $final_total;
                $total_price = $final_total;
                $sub_total = $subtotal_amount;
                $total_amount = $final_total;
                $gst_percentage = 18; // Total GST percentage
                
                $hsn_sac_code = '998314'; // Default for digital services
                $service_name = 'Franchisee Registration Fees';
                $plan_name = isset($_SESSION['invoice_plan_name']) ? trim((string)$_SESSION['invoice_plan_name']) : '';
                $plan_validity = isset($_SESSION['invoice_plan_validity']) ? trim((string)$_SESSION['invoice_plan_validity']) : '';
                if ($plan_name === '' || $plan_validity === '') {
                    $plan_from_amount = [
                        6000 => ['Starter Franchise Plan', '4 Months'],
                        30000 => ['Full Franchise Plan', 'Lifetime'],
                    ];
                    $k = (int)round($original_amount);
                    if (isset($plan_from_amount[$k])) {
                        $plan_name = $plan_name === '' ? $plan_from_amount[$k][0] : $plan_name;
                        $plan_validity = $plan_validity === '' ? $plan_from_amount[$k][1] : $plan_validity;
                    }
                }
                
                $invoice_insert_query = "INSERT INTO invoice_details (
                    invoice_number, invoice_date, card_id, user_email, user_name, user_contact,
                    billing_name, billing_email, billing_contact, billing_address, billing_state, 
                    billing_city, billing_pincode, billing_gst_number, original_amount, discount_amount, 
                    final_amount, promo_code, promo_discount, razorpay_order_id, razorpay_payment_id, 
                    razorpay_signature, payment_status, payment_date, service_name, service_description,
                    hsn_sac_code, quantity, unit_price, total_price, sub_total, igst_percentage, 
                    igst_amount, cgst_amount, sgst_amount, total_amount, payment_type, reference_number, created_at, updated_at
                    , plan_name, plan_validity
                ) VALUES (
                    '" . mysqli_real_escape_string($connect, $invoice_number) . "',
                    '" . mysqli_real_escape_string($connect, $invoice_date) . "',
                    '0', /* No card ID for franchisee */
                    '" . mysqli_real_escape_string($connect, $reg_data['email']) . "',
                    '" . mysqli_real_escape_string($connect, $reg_data['name']) . "',
                    '" . mysqli_real_escape_string($connect, $reg_data['contact']) . "',
                    '" . mysqli_real_escape_string($connect, $reg_data['name']) . "',
                    '" . mysqli_real_escape_string($connect, $reg_data['email']) . "',
                    '" . mysqli_real_escape_string($connect, $reg_data['contact']) . "',
                    '" . mysqli_real_escape_string($connect, $reg_data['address']) . "',
                    '" . mysqli_real_escape_string($connect, $reg_data['state']) . "',
                    '" . mysqli_real_escape_string($connect, $reg_data['city']) . "',
                    '" . mysqli_real_escape_string($connect, $reg_data['pincode']) . "',
                    '" . mysqli_real_escape_string($connect, $reg_data['gst_number']) . "',
                    '" . mysqli_real_escape_string($connect, $original_amount) . "',
                    '" . mysqli_real_escape_string($connect, $discount_amount) . "',
                    '" . mysqli_real_escape_string($connect, $final_total) . "',
                    '" . mysqli_real_escape_string($connect, $promo_code) . "',
                    '" . mysqli_real_escape_string($connect, $promo_discount) . "',
                    '" . mysqli_real_escape_string($connect, $_SESSION['razorpay_order_id'] ?? $_SESSION['reference_number']) . "',
                    '" . mysqli_real_escape_string($connect, $_POST['razorpay_payment_id'] ?? 'test_payment') . "',
                    '" . mysqli_real_escape_string($connect, $_POST['razorpay_signature'] ?? '') . "',
                    'Success',
                    '" . mysqli_real_escape_string($connect, $date) . "',
                    '" . mysqli_real_escape_string($connect, $service_name) . "',
                    '" . mysqli_real_escape_string($connect, $service_name) . "',
                    '" . mysqli_real_escape_string($connect, $hsn_sac_code) . "',
                    '1',
                    '" . mysqli_real_escape_string($connect, $unit_price) . "',
                    '" . mysqli_real_escape_string($connect, $total_price) . "',
                    '" . mysqli_real_escape_string($connect, $sub_total) . "',
                    '" . mysqli_real_escape_string($connect, $gst_percentage) . "',
                    '" . mysqli_real_escape_string($connect, $igst_amount) . "',
                    '" . mysqli_real_escape_string($connect, $cgst_amount) . "',
                    '" . mysqli_real_escape_string($connect, $sgst_amount) . "',
                    '" . mysqli_real_escape_string($connect, $total_amount) . "',
                    'Franchisee',
                    '" . mysqli_real_escape_string($connect, $_SESSION['reference_number']) . "',
                    '" . mysqli_real_escape_string($connect, $current_timestamp) . "',
                    '" . mysqli_real_escape_string($connect, $current_timestamp) . "',
                    '" . mysqli_real_escape_string($connect, $plan_name) . "',
                    '" . mysqli_real_escape_string($connect, $plan_validity) . "'
                )";
                
                $invoice_result = mysqli_query($connect, $invoice_insert_query);
                
                if ($invoice_result) {
                    error_log("SUCCESS: Invoice details inserted successfully for franchisee. Invoice Number: " . $invoice_number);
                    $_SESSION['invoice_number'] = $invoice_number;
                    
                    // Update referral earnings after successful payment
                    $update_referral_earnings = updateReferralEarningsAfterPayment($connect, $reg_data['email'], '0');
                    if ($update_referral_earnings) {
                        error_log("SUCCESS: Referral earnings updated after franchisee payment");
                    } else {
                        error_log("ERROR: Failed to update referral earnings after franchisee payment");
                    }
                    
                    // Send welcome email to new franchisee after successful payment
                    $user_email = $reg_data['email'];
                    $user_name = $reg_data['name'] ?? $reg_data['email']; // Use name if available, otherwise email
                    
                    $email_template = buildFranchiseeWelcomeEmail(
                        $user_name,
                        $reg_data['email'],
                        $reg_data['password'],
                        ['include_payment_processed_line' => true]
                    );
                    $subject = $email_template['subject'];
                    $message = $email_template['message'];

                    // Try to send email, but don't let email failures stop the process
                    $email_sent = sendEmail($user_email, $subject, $message, $user_name);
                    
                    if ($email_sent) {
                        error_log("SUCCESS: Welcome email sent to new franchisee: " . $user_email);
                    } else {
                        error_log("WARNING: Failed to send welcome email to franchisee: " . $user_email . " (Payment still successful)");
                    }
                } else {
                    error_log("ERROR: Failed to insert invoice details for franchisee: " . mysqli_error($connect));
                }
            }
            
            // Store user email in session for receipt
            $_SESSION['user_email'] = $reg_data['email'];
            // Clear registration data
            unset($_SESSION['franchise_registration_data']);
        }
    }
    
    echo '<div class="payment_confirmation">Your Payment Successful. Please wait we are redirecting...</div>';
    
    // Redirect to receipt page with both reference number and payment ID
    $payment_id = $_POST['razorpay_payment_id'] ?? 'test_payment_' . time();
    $ref_out = rawurlencode((string) $_SESSION['reference_number']);
    $pay_out = rawurlencode((string) $payment_id);
    echo '<meta http-equiv="refresh" content="3;URL=../user/invoice/download_receipt.php?ref=' . $ref_out . '&payment_id=' . $pay_out . '">';
} else {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Payment Failed</title>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 0; }
            .payment_error { border: 1px solid #f44336; width: 80%; max-width: 500px; padding: 30px; font-size: 16px; background: #ffebee; color: #c62828; font-family: sans-serif; margin: 50px auto; text-align: center; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .btn1 { display: inline-block; padding: 12px 25px; background: #f44336; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="payment_error">
            <h2>Payment Failed</h2>
            <p><?php echo $error; ?></p>
            <a href="../franchise_agreement.php" class="btn1">Try Again</a>
        </div>
        <script>
            setTimeout(function() {
                window.location.href = '../franchise_agreement.php';
            }, 5000);
        </script>
    </body>
    </html>
    <?php
}
?>







