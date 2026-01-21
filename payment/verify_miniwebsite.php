<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

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

// Database connection
$db_host = "p004.bom1.mysecurecloudhost.com";
$db_user = "wwwmoody_miniweb_vcard";
$db_pass = "miniweb_vcard";
$db_name = "miniweb_vcard";

try {
    $connect = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    // Check connection
    if ($connect->connect_error) {
        throw new Exception("Connection failed: " . $connect->connect_error);
    }
    
    // Set charset to utf8
    $connect->set_charset("utf8");
    
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Database connection error: " . $e->getMessage());
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
            // Verify the payment signature
            $attributes = array(
                 'razorpay_order_id' => $_SESSION['razorpay_order_id'],
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
    
    // Check if required session variables exist
    if (!isset($_SESSION['franchise_registration_data'])) {
        error_log("ERROR: franchise_registration_data not found in session");
        die("Payment verification failed: Missing registration data. Please try again.");
    }
    
    if (!isset($_SESSION['reference_number'])) {
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
                
                $invoice_insert_query = "INSERT INTO invoice_details (
                    invoice_number, invoice_date, card_id, user_email, user_name, user_contact,
                    billing_name, billing_email, billing_contact, billing_address, billing_state, 
                    billing_city, billing_pincode, billing_gst_number, original_amount, discount_amount, 
                    final_amount, promo_code, promo_discount, razorpay_order_id, razorpay_payment_id, 
                    razorpay_signature, payment_status, payment_date, service_name, service_description,
                    hsn_sac_code, quantity, unit_price, total_price, sub_total, igst_percentage, 
                    igst_amount, cgst_amount, sgst_amount, total_amount, payment_type, reference_number, created_at, updated_at
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
                    '" . mysqli_real_escape_string($connect, $current_timestamp) . "'
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
                    
                    $subject = "Welcome to MiniWebsite.in – Your Franchisee Account is Ready!";
                    $message = '
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                        <p style="color: #333; font-size: 16px; line-height: 1.6;">Hi <strong>' . htmlspecialchars($user_name) . '</strong>,</p>
                        
                        <p style="color: #333; font-size: 16px; line-height: 1.6;">Thank you for registering as a franchisee with MiniWebsite.in.</p>
                        
                        <p style="color: #333; font-size: 16px; line-height: 1.6;">We are excited to have you on board! Your franchisee account has been successfully created and your payment has been processed. You can now log in using your email and password at the link below:</p>
                        
                        <div style="background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin: 20px 0;">
                            <h3 style="color: #333; font-size: 18px; margin-top: 0; margin-bottom: 15px;">🔐 Your Login Details:</h3>
                            <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 10px 0;"><strong>Email ID:</strong> ' . htmlspecialchars($reg_data['email']) . '</p>
                            <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 10px 0;"><strong>Password:</strong> ' . htmlspecialchars($reg_data['password']) . '</p>
                            <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 10px 0;">👉 <a href="https://' . $_SERVER['HTTP_HOST'] . '/panel/franchisee-login/login.php" style="color: #007bff; text-decoration: none;">Click here to login</a></p>
                        </div>
                        
                        <br>
                        
                        <p style="color: #333; font-size: 16px; line-height: 1.6;"><strong>Follow these simple steps to activate your franchise:</strong></p>
                        
                        <p style="color: #333; font-size: 16px; line-height: 1.6;"><strong>1. Complete your document Verification from your Dashboard.</strong></p>
                        
                        <p style="color: #333; font-size: 16px; line-height: 1.6;"><strong>2. After the documents get verified, you can access your Franchise Kit and Onboarding Material from your dashboard only.</strong></p>
                        
                        <br>
                        
                        <p style="color: #333; font-size: 16px; line-height: 1.6;">That is it! Once these steps are completed, you are officially part of the MiniWebsite.in franchise network. You can begin building your business and start earning right away.</p>
                        
                        <p style="color: #333; font-size: 16px; line-height: 1.6;">If you have any questions or need assistance, feel free to reach out to our support team.</p>
                        
                        <br>
                        
                        <p style="color: #333; font-size: 16px; line-height: 1.6;">Best regards,<br>
                        Team MiniWebsite.in<br>
                        www.miniwebsite.in</p>
                    </div>';

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
    echo '<meta http-equiv="refresh" content="3;URL=download_receipt.php?ref=' . $_SESSION['reference_number'] . '&payment_id=' . $payment_id . '">';
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







