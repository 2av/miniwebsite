<?php
// Handle AJAX for franchise Create MW (OTP + account) — BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])
    && in_array($_POST['action'], ['create_customer_account', 'franchise_send_create_otp'], true)) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    require_once(__DIR__ . '/../../app/config/database.php');
    require_once(__DIR__ . '/../../app/helpers/role_helper.php');

    $current_role = get_current_user_role();
    if ($current_role !== 'FRANCHISEE') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }

    ob_clean();
    header('Content-Type: application/json');

    $action = $_POST['action'];

    try {
        $fullName = trim($_POST['fullName'] ?? '');
        $companyname = trim($_POST['companyname'] ?? '');
        $emailAddress = trim($_POST['emailAddress'] ?? '');
        $mobileNumber = trim($_POST['mobileNumber'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $franchisee_email = trim($_POST['franchisee_email'] ?? '');
        $card_id = str_replace([' ', '.', '&', '/', '', '[', ']'], ['-', '', '', '-', '', ''], $companyname);

        if ($fullName === '' || $emailAddress === '' || $mobileNumber === '' || $password === '' || $companyname === '') {
            throw new Exception('All fields are required');
        }
        if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address');
        }
        if (strlen($password) < 6) {
            throw new Exception('Password must be at least 6 characters long');
        }

        $email_check_query = 'SELECT role, email FROM user_details WHERE email = ? LIMIT 1';
        $check_email = mysqli_prepare($connect, $email_check_query);
        mysqli_stmt_bind_param($check_email, 's', $emailAddress);
        mysqli_stmt_execute($check_email);
        $result = mysqli_stmt_get_result($check_email);
        if ($result && mysqli_num_rows($result) > 0) {
            $email_data = mysqli_fetch_array($result);
            $source = ucfirst(strtolower($email_data['role'] ?? 'user'));
            mysqli_stmt_close($check_email);
            throw new Exception("This email address is already registered as a $source. Please use a different email.");
        }
        mysqli_stmt_close($check_email);

        $mobile_check_query = 'SELECT role, phone FROM user_details WHERE phone = ? LIMIT 1';
        $check_mobile = mysqli_prepare($connect, $mobile_check_query);
        mysqli_stmt_bind_param($check_mobile, 's', $mobileNumber);
        mysqli_stmt_execute($check_mobile);
        $result = mysqli_stmt_get_result($check_mobile);
        if ($result && mysqli_num_rows($result) > 0) {
            $mobile_data = mysqli_fetch_array($result);
            $source = ucfirst(strtolower($mobile_data['role'] ?? 'user'));
            mysqli_stmt_close($check_mobile);
            throw new Exception("This mobile number is already registered as a $source. Please use a different mobile number.");
        }
        mysqli_stmt_close($check_mobile);

        $wallet_balance = 0.0;
        $wallet_query = mysqli_query($connect, "SELECT w_balance FROM wallet WHERE f_user_email = '" . mysqli_real_escape_string($connect, $franchisee_email) . "' ORDER BY ID DESC LIMIT 1");
        if ($wallet_query && mysqli_num_rows($wallet_query) > 0) {
            $wallet_row = mysqli_fetch_array($wallet_query);
            $wallet_balance = floatval($wallet_row['w_balance'] ?? 0);
        }
        if ($wallet_balance < 413) {
            throw new Exception('Insufficient wallet balance. Please recharge at least Rs. 413. Current balance: ' . number_format($wallet_balance, 2));
        }

        if ($action === 'franchise_send_create_otp') {
            $otp = (string) rand(100000, 999999);
            $_SESSION['franchise_create_otp'] = $otp;
            $_SESSION['franchise_create_otp_time'] = time();
            $_SESSION['franchise_create_pending'] = [
                'fullName' => $fullName,
                'companyname' => $companyname,
                'emailAddress' => $emailAddress,
                'mobileNumber' => $mobileNumber,
                'password' => $password,
                'franchisee_email' => $franchisee_email,
                'card_id' => $card_id,
            ];

            $otp_sent = false;
            if (file_exists(__DIR__ . '/../../common/mailtemplate/send_franchise_customer_otp_email.php')) {
                require_once __DIR__ . '/../../common/mailtemplate/send_franchise_customer_otp_email.php';
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $site_base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'www.miniwebsite.in');
                $otp_sent = sendFranchiseCustomerOtpEmail($emailAddress, $fullName, $otp, $site_base);
            }
            if (!$otp_sent) {
                unset($_SESSION['franchise_create_otp'], $_SESSION['franchise_create_otp_time'], $_SESSION['franchise_create_pending']);
                throw new Exception('Failed to send OTP verification email. Please try again.');
            }

            echo json_encode([
                'success' => true,
                'requires_otp' => true,
                'message' => 'OTP sent to customer email. Please enter OTP to complete account creation.',
            ]);
            exit;
        }

        // create_customer_account — verify OTP (MAIL TEMPLATE 04B) then create account
        $entered_otp = trim($_POST['otp'] ?? '');
        if ($entered_otp === '') {
            throw new Exception('Please enter the OTP sent to the customer email.');
        }
        if (!isset($_SESSION['franchise_create_otp'], $_SESSION['franchise_create_otp_time'], $_SESSION['franchise_create_pending'])) {
            throw new Exception('OTP session expired. Please submit the form again to receive a new OTP.');
        }
        if ((time() - (int) $_SESSION['franchise_create_otp_time']) > 600) {
            unset($_SESSION['franchise_create_otp'], $_SESSION['franchise_create_otp_time'], $_SESSION['franchise_create_pending']);
            throw new Exception('OTP has expired. Please submit the form again to receive a new OTP.');
        }
        $pending = $_SESSION['franchise_create_pending'];
        if (strcasecmp($pending['emailAddress'] ?? '', $emailAddress) !== 0
            || ($pending['mobileNumber'] ?? '') !== $mobileNumber
            || ($pending['companyname'] ?? '') !== $companyname) {
            throw new Exception('Form data changed after OTP was sent. Please request a new OTP.');
        }
        if ((string) $_SESSION['franchise_create_otp'] !== $entered_otp) {
            throw new Exception('Invalid OTP. Please try again.');
        }

        unset($_SESSION['franchise_create_otp'], $_SESSION['franchise_create_otp_time'], $_SESSION['franchise_create_pending']);

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $referral_code = strtoupper(substr(md5($emailAddress . time()), 0, 8));
        $ip = mysqli_real_escape_string($connect, $_SERVER['REMOTE_ADDR'] ?? '');
        $safeFullName = mysqli_real_escape_string($connect, $fullName);
        $safeEmail = mysqli_real_escape_string($connect, $emailAddress);
        $safeMobile = mysqli_real_escape_string($connect, $mobileNumber);
        $safeFranchiseeEmail = mysqli_real_escape_string($connect, $franchisee_email);
        $safeReferralCode = mysqli_real_escape_string($connect, $referral_code);

        $insert_ud = mysqli_query($connect, "
            INSERT INTO user_details
                (role, email, phone, name, password, password_hash, ip, status, created_at, referred_by, referral_code)
            VALUES
                ('CUSTOMER', '$safeEmail', '$safeMobile', '$safeFullName', '$hashed_password', '$hashed_password', '$ip', 'ACTIVE', NOW(), '$safeFranchiseeEmail', '$safeReferralCode')
        ");

        if (!$insert_ud) {
            throw new Exception('Failed to create account: ' . mysqli_error($connect));
        }

        $customer_id = mysqli_insert_id($connect);

        $card_insert_query = "INSERT INTO digi_card (user_email, f_user_email, d_comp_name, card_id, d_payment_status, d_card_status, uploaded_date, validity_date)
                             VALUES (?, ?, ?, ?, 'Success','Active', NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR))";
        $card_stmt = mysqli_prepare($connect, $card_insert_query);
        if (!$card_stmt) {
            throw new Exception('Card prepare failed: ' . mysqli_error($connect));
        }
        mysqli_stmt_bind_param($card_stmt, 'ssss', $emailAddress, $franchisee_email, $companyname, $card_id);

        if (!mysqli_stmt_execute($card_stmt)) {
            $err = mysqli_stmt_error($card_stmt);
            mysqli_stmt_close($card_stmt);
            throw new Exception('Failed to create card: ' . $err);
        }

        $new_card_auto_id = mysqli_insert_id($connect);
        mysqli_stmt_close($card_stmt);

        $new_balance = $wallet_balance - 413;
        $withdraw_amount_str = '-413';
        $order_id_for_wallet = (string) $new_card_auto_id;
        $wallet_insert = mysqli_prepare($connect, 'INSERT INTO wallet (f_user_email, w_withdraw, w_order_id, w_balance, uploaded_date) VALUES (?, ?, ?, ?, NOW())');
        if ($wallet_insert) {
            mysqli_stmt_bind_param($wallet_insert, 'sssd', $franchisee_email, $withdraw_amount_str, $order_id_for_wallet, $new_balance);
            $wallet_insert_ok = mysqli_stmt_execute($wallet_insert);
            if (!$wallet_insert_ok) {
                error_log('wallet deduction insert failed: ' . mysqli_stmt_error($wallet_insert));
            }
            $wallet_txn_id = mysqli_insert_id($connect);
            mysqli_stmt_close($wallet_insert);

            if ($wallet_txn_id > 0) {
                $invoice_reference = 'WALLET-' . $wallet_txn_id;
                $check_invoice = mysqli_query($connect, "SELECT id FROM invoice_details WHERE reference_number='" . mysqli_real_escape_string($connect, $invoice_reference) . "' LIMIT 1");
                if (!$check_invoice || mysqli_num_rows($check_invoice) === 0) {
                    $last_invoice_query = mysqli_query($connect, "SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number, '/', -1) AS UNSIGNED)) as last_number FROM invoice_details WHERE invoice_number LIKE 'KIR/%'");
                    $last_invoice_result = $last_invoice_query ? mysqli_fetch_array($last_invoice_query) : null;
                    $next_number = (($last_invoice_result['last_number'] ?? 0) + 1);
                    $invoice_number = 'KIR/' . str_pad((string) $next_number, 5, '0', STR_PAD_LEFT);
                    $current_timestamp = date('Y-m-d H:i:s');
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
                        '" . mysqli_real_escape_string($connect, date('Y-m-d')) . "',
                        '" . mysqli_real_escape_string($connect, (string) $new_card_auto_id) . "',
                        '" . mysqli_real_escape_string($connect, $emailAddress) . "',
                        '" . mysqli_real_escape_string($connect, $fullName) . "',
                        '" . mysqli_real_escape_string($connect, $mobileNumber) . "',
                        '" . mysqli_real_escape_string($connect, $fullName) . "',
                        '" . mysqli_real_escape_string($connect, $emailAddress) . "',
                        '" . mysqli_real_escape_string($connect, $mobileNumber) . "',
                        '', '', '', '', '',
                        '350.00', '0.00', '413.00', '', '0', '', '', '', 'Success',
                        '" . mysqli_real_escape_string($connect, $current_timestamp) . "',
                        'Mini Website Creation', 'Mini Website Creation (Wallet)', '998314', '1',
                        '413.00', '413.00', '350.00', '18', '63.00', '0.00', '0.00', '413.00',
                        'Wallet', '" . mysqli_real_escape_string($connect, $invoice_reference) . "',
                        '" . mysqli_real_escape_string($connect, $current_timestamp) . "',
                        '" . mysqli_real_escape_string($connect, $current_timestamp) . "'
                    )";
                    mysqli_query($connect, $invoice_insert_query);
                }
            }
        }

        $email_sent = false;
        $franchisee_name = 'Franchisee';
        $franchisee_esc = mysqli_real_escape_string($connect, $franchisee_email);
        $franchisee_query = mysqli_query($connect, "SELECT f_user_name FROM franchisee_login WHERE f_user_email = '$franchisee_esc' LIMIT 1");
        if ($franchisee_query && mysqli_num_rows($franchisee_query) > 0) {
            $franchisee_data = mysqli_fetch_array($franchisee_query);
            $franchisee_name = $franchisee_data['f_user_name'] ?? 'Franchisee';
        }
        if (file_exists(__DIR__ . '/../../common/mailtemplate/send_customer_welcome_email.php')) {
            require_once __DIR__ . '/../../common/mailtemplate/send_customer_welcome_email.php';
            $email_sent = sendCustomerWelcomeEmail($fullName, $emailAddress, $password, $franchisee_name);
        }

        $franchise_ack_email_sent = false;
        if (file_exists(__DIR__ . '/../../common/mailtemplate/send_franchise_customer_created_ack_email.php')) {
            require_once __DIR__ . '/../../common/mailtemplate/send_franchise_customer_created_ack_email.php';
            $franchise_ack_email_sent = sendFranchiseCustomerCreatedAckEmail(
                $franchisee_email,
                $franchisee_name,
                $fullName,
                $emailAddress,
                $companyname,
                $mobileNumber
            );
        }

        echo json_encode([
            'success' => true,
            'message' => 'Account created successfully' . ($email_sent ? ' and welcome email sent' : ' (welcome email sending failed)'),
            'customer_id' => $customer_id,
            'email_sent' => $email_sent,
            'franchise_ack_email_sent' => $franchise_ack_email_sent,
        ]);
    } catch (Exception $e) {
        error_log('Customer account creation error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Regular page load - include header and other files
include __DIR__ . '/../includes/header.php';

require_once(__DIR__ . '/../../app/helpers/role_access_helper.php');
require_once(__DIR__ . '/../../app/helpers/mw_card_status_helper.php');

// Get current role
$current_role = get_current_user_role();
$user_email = get_user_email();

$ras_dash = get_current_user_role_access_settings($connect);
$show_grow_with_mw = is_role_access_feature_visible_for_user(
    $connect,
    $ras_dash['profile_key'] ?? null,
    'grow_with_mw',
    'yes_no',
    $user_email,
    $current_role
);
$show_create_mw_button = is_role_access_feature_visible(
    $connect,
    $ras_dash['profile_key'] ?? null,
    'create_mw_button',
    'textarea'
);
if ($current_role === 'FRANCHISEE') {
    $show_create_mw_button = true;
}

// Clear any applied promocodes when accessing dashboard
if (isset($_SESSION['promo_code'])) {
    unset($_SESSION['promo_code']);
}
if (isset($_SESSION['promo_discount'])) {
    unset($_SESSION['promo_discount']);
}
if (isset($_SESSION['auto_applied_promo'])) {
    unset($_SESSION['auto_applied_promo']);
}

// Clear any promo check keys to reset the auto-apply logic
foreach ($_SESSION as $key => $value) {
    if (strpos($key, 'promo_check_') === 0) {
        unset($_SESSION[$key]);
    }
}

// Check if user just registered and show welcome message
$show_welcome = false;
if (isset($_SESSION['just_registered']) && $_SESSION['just_registered'] === true) {
    $show_welcome = true;
    unset($_SESSION['just_registered']);
}

// Ensure unified user_details has a referral_code column for all roles (CUSTOMER/TEAM/FRANCHISEE)
try {
    $colCheckDash = mysqli_query($connect, "SHOW COLUMNS FROM user_details LIKE 'referral_code'");
    if ($colCheckDash && mysqli_num_rows($colCheckDash) === 0) {
        @mysqli_query($connect, "ALTER TABLE user_details ADD COLUMN referral_code VARCHAR(50) DEFAULT ''");
    }
} catch (Throwable $e) {
    // Silent fallback; if this fails, referral code features may not work but dashboard should still render
}

// Get user's cards based on role
$query = null;
$user_referral_code = '';
$user_email = get_user_email(); // Get email for all roles

if ($current_role == 'CUSTOMER' || $current_role == 'TEAM') {
    // 1) Try to get referral code from session
    $user_referral_code = $_SESSION['user_referral_code'] ?? '';

    // 2) If still empty, try to load from user_details table
    if (empty($user_referral_code)) {
        $user_email_lower = strtolower(trim($user_email));
        $ref_q = mysqli_query($connect, "SELECT referral_code FROM user_details WHERE LOWER(TRIM(email))='$user_email_lower' LIMIT 1");
        if ($ref_q && mysqli_num_rows($ref_q) > 0) {
            $ref_row = mysqli_fetch_array($ref_q);
            if (!empty($ref_row['referral_code'])) {
                $user_referral_code = trim($ref_row['referral_code']);
                $_SESSION['user_referral_code'] = $user_referral_code;
            }
        }
    }

    // 3) If still empty (old users without code), generate and persist
    if (empty($user_referral_code)) {
        $user_referral_code = strtoupper(substr(md5($user_email . time()), 0, 8));
        $_SESSION['user_referral_code'] = $user_referral_code;
        // Persist into user_details so it can be used later for referrals
        $user_email_lower = strtolower(trim($user_email));
        mysqli_query($connect, "UPDATE user_details SET referral_code='" . mysqli_real_escape_string($connect, $user_referral_code) . "' WHERE LOWER(TRIM(email))='$user_email_lower' LIMIT 1");
    }

    $query = mysqli_query($connect, "SELECT * FROM digi_card WHERE user_email='$user_email' ORDER BY id DESC");
    // Check if query failed
    if (!$query) {
        error_log("Dashboard query failed: " . mysqli_error($connect));
        $query = null;
    }
} elseif ($current_role == 'FRANCHISEE') {
    // For franchisee, user_email is the franchisee email
    $franchisee_email = $user_email;
    
    // Get franchisee verification status
    require_once(__DIR__ . '/../../app/helpers/verification_helper.php');
    $is_verified = isFranchiseeVerified($franchisee_email);
    $franchise_agreement_paid = isFranchiseeRegistrationAgreementPaid($franchisee_email);
    $franchise_agreement_url = ($assets_base !== '' && $assets_base !== null ? $assets_base : '') . '/franchise_agreement.php?email=' . rawurlencode($franchisee_email);
    
    // Get wallet balance for franchisee
    $wallet_balance = 0;
    $wallet_query = mysqli_query($connect, "SELECT w_balance FROM wallet WHERE f_user_email = '$franchisee_email' ORDER BY ID DESC LIMIT 1");
    if ($wallet_query && mysqli_num_rows($wallet_query) > 0) {
        $wallet_row = mysqli_fetch_array($wallet_query);
        $wallet_balance = floatval($wallet_row['w_balance'] ?? 0);
    }
    $has_sufficient_balance = $wallet_balance >= 413;
    
    // Get total cards created by this franchisee
    $total_cards_query = mysqli_query($connect, "SELECT COUNT(*) as total_cards FROM digi_card WHERE f_user_email = '$franchisee_email'");
    $total_cards = 0;
    if ($total_cards_query && mysqli_num_rows($total_cards_query) > 0) {
        $total_cards_row = mysqli_fetch_array($total_cards_query);
        $total_cards = intval($total_cards_row['total_cards'] ?? 0);
    }
    
    // Query for "Manage Users" table - users created by this franchisee (from user_details)
    $manage_users_query = mysqli_query($connect, "
        SELECT 
            ud.id,
            ud.name AS user_name,
            ud.phone AS user_contact,
            ud.email AS user_email,
            ud.created_at AS uploaded_date,
            ud.referral_code,
            ud.referred_by,
            dc.id as card_id,
            dc.d_comp_name,
            dc.d_payment_status,
            dc.uploaded_date as card_created_date,
            dc.d_payment_date,
            dc.validity_date
        FROM user_details ud
        INNER JOIN digi_card dc ON ud.email = dc.user_email AND dc.f_user_email = '$franchisee_email'
        WHERE ud.role = 'CUSTOMER'
        ORDER BY ud.created_at DESC
    ");
    
    // For franchisee, we don't use the regular $query for the main table
    $query = null;
}

// Refund status for this user (controls conditional column)
// For franchisee, use franchisee email; for others, use user email
$email_for_refund = ($current_role == 'FRANCHISEE') ? $franchisee_email : $user_email;
$user_email_lower = strtolower(trim($email_for_refund));
$refund_meta = mysqli_query($connect, "SELECT refund_status, refund_status_date FROM user_details WHERE LOWER(TRIM(email))='$user_email_lower' LIMIT 1");
$refund_status = 'None';
$refund_status_date = '';
if ($refund_meta && mysqli_num_rows($refund_meta) > 0) {
    $rm = mysqli_fetch_array($refund_meta);
    $refund_status = $rm['refund_status'] ?? 'None';
    $refund_status_date = $rm['refund_status_date'] ?? '';
}
$show_refund_status_col = ($refund_status !== 'None');

// Check if any cards were created by the user themselves (not by franchisees)
// For franchisee, this doesn't apply (they see cards they created for others)
$show_invoice_column = false;
if ($current_role != 'FRANCHISEE' && isset($user_email)) {
    $temp_query = mysqli_query($connect, "SELECT COUNT(*) as self_created_count FROM digi_card WHERE user_email='$user_email' AND (f_user_email IS NULL OR f_user_email = '')");
    if ($temp_query && mysqli_num_rows($temp_query) > 0) {
        $temp_result = mysqli_fetch_array($temp_query);
        $show_invoice_column = $temp_result['self_created_count'] > 0;
    }
}

// Get MW Referral ID status and influencer flag from user_details table
$mw_referral_id = 0;
$user_influencer = 'NO';
$mw_referral_query = mysqli_query($connect, "SELECT mw_referral_id, influencer FROM user_details WHERE LOWER(TRIM(email))='$user_email_lower' LIMIT 1");
if ($mw_referral_query && mysqli_num_rows($mw_referral_query) > 0) {
    $mw_referral_data = mysqli_fetch_array($mw_referral_query);
    $mw_referral_id = intval($mw_referral_data['mw_referral_id'] ?? 0);
    $user_influencer = strtoupper(trim($mw_referral_data['influencer'] ?? 'NO'));
}
?>

<main class="Dashboard">
    <div class="container-fluid  customer_content_area">
        <div class="main-top mw-page-header">
            <h1 class="heading mw-page-title"><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mw-breadcrumb">
                    <li class="breadcrumb-item mw-breadcrumb-item"><a href="#">Mini Website</a></li>
                    <li class="breadcrumb-item mw-breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></li>
                </ol>
            </nav>
        </div>
        
        <?php if ($show_welcome): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <p>Welcome! Your account is active. Create your Mini Website to get started.</p>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <?php if ($current_role == 'FRANCHISEE'): ?>
                    <!-- FRANCHISEE DASHBOARD LAYOUT -->
                    <?php if (!$franchise_agreement_paid): ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                           Complete your franchisee plan payment to activate your account verification and wallet access.
                            <a href="<?php echo htmlspecialchars($franchise_agreement_url, ENT_QUOTES, 'UTF-8'); ?>" class="alert-link">Complete payment</a>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php elseif(!$is_verified): ?>
                        <?php 
                        require_once(__DIR__ . '/../../app/helpers/verification_helper.php');
                        showVerificationWarning(); 
                        ?>
                    <?php endif; ?>
                    
                    <div class="FranchiseeDashboard-head">
                        <div class="mw-dash-card-grid">
                            <!-- Create Your MW -->
                            <div class="card_area">
                                <?php if ($is_verified && $has_sufficient_balance): ?>
                                    <a href="#" onclick="openCreateAccountModal(); return false;">
                                        <div class="card">
                                            <div class="img mw-dash-card-icon mw-dash-card-icon--blue" aria-hidden="true">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </div>
                                            <div class="content">
                                                <p>Create New<br>Mini Website</p>
                                            </div>
                                        </div>
                                    </a>
                                <?php else: ?>
                                    <div class="card is-disabled" title="<?php echo !$is_verified ? 'Document verification required' : 'Insufficient wallet balance'; ?>">
                                        <div class="img mw-dash-card-icon mw-dash-card-icon--blue" aria-hidden="true">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </div>
                                        <div class="content">
                                            <p>Create New<br>Mini Website</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- MW Created -->
                            <div class="card_area">
                                <div class="card">
                                    <div class="img mw-dash-card-icon mw-dash-card-icon--blue" aria-hidden="true">
                                        <i class="fa-solid fa-circle-check"></i>
                                    </div>
                                    <div class="content">
                                        <p>MW Created</p>
                                        <h4 class="marginbottom5"><?php echo (int) $total_cards; ?></h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Wallet Balance -->
                            <div class="card_area card_area-wallet">
                                <a href="<?php echo htmlspecialchars($nav_base . '/wallet', ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="card">
                                        <div class="img mw-dash-card-icon mw-dash-card-icon--gold" aria-hidden="true">
                                            <i class="fa-solid fa-wallet"></i>
                                        </div>
                                        <div class="content">
                                            <p>Wallet Balance</p>
                                            <h4><i class="fa fa-inr" aria-hidden="true"></i> <?php echo number_format($wallet_balance, 2); ?></h4>
                                        </div>
                                    </div>
                                </a>
                                <?php if (!$has_sufficient_balance): ?>
                                    <p class="low_balance_title">Your balance is low. Please recharge wallet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Manage Users Section -->
                    <div class="ManageUsers">
                        <h4 class="heading">Manage Users: </h4>
                        <div class="table-responsive mw-table-scroll mw-table-scroll-wide mw-dashboard-table-wrap" id="dashboardTableWrap">
                            <table id="ReferredUsers" class="display table mb-0" style="text-align: center;">
                                <thead class="bg-secondary">
                                    <tr>
                                        <th class="text-left">User ID</th>
                                        <th class="text-left">MW ID</th>
                                        <th class="text-left">User Email</th>
                                        <th class="text-left">User Name</th>
                                        <th class="text-left">User Number</th>
                                        <th class="text-left">Date Created</th>
                                        <th class="text-left">Validity Date</th>
                                        <th class="text-left">MW Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if($manage_users_query && mysqli_num_rows($manage_users_query) > 0) {
                                        while($user = mysqli_fetch_array($manage_users_query)) {
                                            // Calculate validity date
                                            if(!empty($user['validity_date']) && $user['validity_date'] != '0000-00-00 00:00:00') {
                                                $validity_date = date('d-m-Y', strtotime($user['validity_date']));
                                            } else {
                                                $validity_date = date('d-m-Y', strtotime($user['uploaded_date'] . ' +1 year'));
                                            }
                                            
                                            // Determine status
                                            $status_class = '';
                                            $status_text = '';
                                            
                                            if($user['d_payment_status'] == 'Success') {
                                                $status_class = 'bg-success';
                                                $status_text = 'Active';
                                            } elseif($user['d_payment_status'] == 'Failed') {
                                                $status_class = 'not-eligible';
                                                $status_text = 'InActive';
                                            } else {
                                                $status_class = 'not-eligible';
                                                $status_text = 'Pending Payment';
                                            }
                                            
                                            $has_cards = !empty($user['card_id']);
                                    ?>
                                    <tr>
                                        <td class="text-left"><?php echo $user['id']; ?></td>
                                        <td class="text-left"><?php echo $user['card_id'] ?? '-'; ?></td>
                                        <td class="text-left"><div class="mw-table-cell-inline">
                                            <?php if($has_cards): ?>
                                                <a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/n.php?n=<?php echo $user['card_id']; ?>" target="_blank" style="text-decoration: none; color: inherit; margin-right:6px;">
                                                    <span class="view_icon_style"><i class="fa-regular fa-eye"></i></span>
                                                </a>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($user['user_email']); ?>
                                        </div></td>
                                        <td class="text-left"><?php echo htmlspecialchars($user['user_name']); ?></td>
                                        <td class="text-left"><?php echo htmlspecialchars($user['user_contact']); ?></td>
                                        <td class="text-left"><?php echo date('d-m-Y', strtotime($user['uploaded_date'])); ?></td>
                                        <td class="text-left"><?php echo $validity_date; ?></td>
                                        <td class="text-left"><span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                    </tr>
                                    <?php
                                        }
                                    } else {
                                    ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No users found.</td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- CUSTOMER/TEAM DASHBOARD LAYOUT -->
                    <div class="CustomerDashboard-head">
                        <div class="mw-dash-card-grid">
                            <?php if ($show_create_mw_button): ?>
                            <div class="card_area top_section">
                                <a href="../website/business-name.php?new=1">
                                    <div class="card">
                                        <div class="img mw-dash-card-icon mw-dash-card-icon--blue" aria-hidden="true">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </div>
                                        <div class="content">
                                            <p>Create New<br>Mini Website</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                         <?php endif; ?>                        
                        </div>
                    </div>

                    <!-- CUSTOMER/TEAM TABLE -->
                    <div class="table-responsive mw-table-scroll mw-dashboard-table-wrap" id="dashboardTableWrap">
                        <table id="ReferredUsers" class="display table mb-0" style="text-align: center;">
                            <thead class="bg-secondary">
                                <tr>
                                    <th>MW ID</th>
                                    <th>Company Name</th>
                                    <th>Date Created</th>
                                    <th>Validity Date</th>
                                    <th>MW Status</th>
                                    <th>View/Edit/Share</th>
                                    <th>User Payment Status</th>
                                    <?php if ($show_invoice_column): ?>
                                    <th>Invoice</th>
                                    <?php endif; ?>
                                    <?php if ($show_refund_status_col): ?>
                                    <th>Refund Status</th>
                                    <?php endif; ?>
                                    
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            if($query !== null && mysqli_num_rows($query) > 0) {
                                while($row = mysqli_fetch_array($query)) {
                                    // Use the validity_date field if available, otherwise calculate based on payment status
                                    if(!empty($row['validity_date'])) {
                                        $validity_date = date('d-m-Y', strtotime($row['validity_date']));
                                    } else {
                                        // Fallback for old records without validity_date
                                        if($row['d_payment_status'] == 'Success') {
                                            $validity_date = date('d-m-Y', strtotime($row['d_payment_date'] . ' +1 year'));
                                        } else {
                                            $validity_date = date('d-m-Y', strtotime($row['uploaded_date'] . ' +7 days'));
                                        }
                                    }
                                    $payment_status = $row['d_payment_status'];
                                    $mw_status = mw_card_resolve_display_status($row);
                                    $status_class = $mw_status['class'];
                                    $status_text = $mw_status['text'];
                            ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td>
                                     <?php echo $row['d_comp_name']; ?>
                                </td>
                                <td><?php echo date('d-m-Y', strtotime($row['uploaded_date'])); ?></td>
                                <td><?php echo $validity_date; ?></td>
                                <td><span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                <td><div class="mw-table-cell-inline">
                                    <?php 
                                    // Check if user is akhilesh@yopmail.com for new flow, otherwise use old flow
                                   // if($_SESSION['user_email'] == 'akhilesh@yopmail.com') {
                                        $edit_link = "../website/business-name.php?card_number=" . $row['id'];
                                   // } else {
                                      //  $edit_link = "../../panel/login/create_card.php?card_number=" . $row['id'];
                                   // }
                                    ?>
                                    <span class="view"> <a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/<?php echo $row['card_id']; ?>" target="_blank" style="text-decoration: none; color: inherit;">
                                     <!-- <img src="../../../assets/images/eye.png" class="img-fluid" width="30px" alt="">      -->
                                     <span class="view_icon_style"><i class="fa-regular fa-eye"></i></span>
                                   
                                    </a></span>
                                    <span class="edit">
                                        <a href="<?php echo $edit_link; ?>">
                                        <!-- <img src="../../../assets/images/edit.png" width="30px" alt=""> -->
                                        <span class="edit_icon_style"><i class="fa-solid fa-pen"></i></span>
                                    </a>
                                </span>
                                    <span class="share"><a href="https://api.whatsapp.com/send?text=<?php echo urlencode('https://'.$_SERVER['HTTP_HOST'].'/'.$row['card_id']); ?>" target="_blank">
                                        <!-- <img src="../../../assets/images/share.png" width="30px" alt=""> -->
                                        <span class="share_icon_style"><i class="fa-solid fa-share-nodes"></i></span>
                                    </a></span>
                                </div></td>
                                <td>
                                    <?php if($row['complimentary_enabled'] == 'Yes') { ?>
                                        <span class="badge bg-info">Complimentary</span>
                                    <?php } else if($payment_status != 'Success') { ?>
                                        <button type="button" class="btn btn-primary paynow_btn" onclick="window.open('<?php echo htmlspecialchars($assets_base . '/payment/pay_miniwebsite.php?id=' . (int) $row['id'] . '&source=' . rawurlencode(strtolower($current_role)), ENT_QUOTES, 'UTF-8'); ?>', '_blank', 'noopener,noreferrer')">Pay Now</button>
                                    <?php } else { 
                                        $paid_on = !empty($row['d_payment_date']) ? date('d-m-Y', strtotime($row['d_payment_date'])) : '';
                                        if ($paid_on) { ?>
                                            <span class="badge bg-success paidOn_text">Paid on <?php echo $paid_on; ?></span>
                                        <?php } else { ?>
                                            <span class="badge bg-success">Paid</span>
                                        <?php } 
                                    } ?>
                                </td>
                                <?php if ($show_invoice_column): ?>
                                <td style="text-align: left;">
                                    <?php
                                    // Lock invoice until payment verification; allow for all paid cards (including franchise-created cards)
                                    $invoice_check_query = mysqli_query($connect, "SELECT COUNT(*) as invoice_count FROM invoice_details WHERE card_id = '" . mysqli_real_escape_string($connect, $row['id']) . "'");
                                    $invoice_check_result = mysqli_fetch_array($invoice_check_query);
                                    $has_invoices = ($invoice_check_result && isset($invoice_check_result['invoice_count'])) ? ((int)$invoice_check_result['invoice_count'] > 0) : false;

                                    if ($payment_status == 'Success') {
                                        if ($has_invoices) {
                                    ?>
                                            <div class="d-flex  align-items-center">
                                                <button class="btn btn-info btn-sm view_btn" onclick="viewInvoiceHistory(<?php echo $row['id']; ?>)" title="View Invoice History">
                                                      View
                                                </button>
                                            </div>
                                        <?php } else { ?>
                                             <div class="d-flex align-items-center">
                                                <span class="download" title="Invoice will be available after payment verification">
                                                    <span class="download_icon_style" style="filter: grayscale(100%); opacity: 0.5;"><i class="fa-solid fa-arrow-down"></i></span>
                                                </span>
                                             </div>
                                         <?php }
                                    } else { ?>
                                        <div class="d-flex  align-items-center">
                                            <span class="download"  title="Payment required to download invoice">
                                                <span class="download_icon_style" style="filter: grayscale(100%); opacity: 0.5;"><i class="fa-solid fa-arrow-down"></i></span>
                                                <!-- <img src="../../../assets/images/download.png" alt="" > -->
                                            </span>
                                        </div>
                                    <?php } ?>
                                </td>
                                <?php endif; ?>
                                <?php if ($show_refund_status_col): ?>
                                <td style="text-align: left;">
                                    <?php 
                                        if ($refund_status !== 'None') {
                                            $label = ($refund_status === 'Refund Settled') ? 'Refund Settled' : 'Refund Claimed';
                                            $date_text = '';
                                            if (!empty($refund_status_date) && $refund_status_date !== '0000-00-00 00:00:00') {
                                                $date_text = date('d-m-Y', strtotime($refund_status_date));
                                            }
                                            echo '<span class="badge '.($refund_status === 'Refund Settled' ? 'bg-success' : 'bg-warning').'">'.$label.($date_text ? ' <br/>on '.$date_text : '').'</span>';
                                        }
                                    ?>
                                </td>
                                <?php endif; ?>
                                
                            </tr>
                            <?php 
                                }
                            } else {
                            ?>
                            <tr>
                                <?php 
                                    $base_cols = $show_invoice_column ? 8 : 7; 
                                    if ($show_refund_status_col) { $base_cols += 1; }
                                ?>
                                <td colspan="<?php echo $base_cols; ?>" class="text-center">No mini websites found. Create your first one!</td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <br/><br/>
                <?php if($collaboration_enabled!='Yes' || (isset($mw_referral_id) && $mw_referral_id == 1)): ?>
                        <div class="referral-id">Mini websites Referral ID</div>                
                                <div class="referral-container">
                                    <div class="referral-box col-md-6">
                                        <p id="regular_link_value"><?php echo htmlspecialchars($site_base_url); ?>/registration/customer-registration.php?ref=<?php echo htmlspecialchars($user_referral_code); ?></p>
                                        <button class="copy-btn" onclick="copyToClipboard('regular_link')">COPY LINK</button>
                                    </div>
                                    <div class="referral-box col-md-6">
                                        <p id="regular_code_value"><?php echo $user_referral_code; ?></p>
                                        <button class="copy-btn" onclick="copyToClipboard('regular_code')">COPY CODE</button>
                                    </div>
                                </div>

                                <div class="social-icons">
                                    <p>Refer Mini Website</p>
                                    <?php
                                    $customer_ref_url = $site_base_url . '/registration/customer-registration.php?ref=' . $user_referral_code;

                                    // Choose MW referral WhatsApp template based on role:
                                    // Template 07A -> FSE Team OR Franchisee Distributor freelancer (collaboration, not influencer)
                                    // Template 07  -> regular MW user OR Franchisee Distributor Influencer/Creator
                                    $is_freelancer_share = ($current_role == 'TEAM') || ($collaboration_enabled && $user_influencer !== 'YES');

                                    if ($is_freelancer_share) {
                                        // WHATSAPP MESSAGE TEMPLATE 07A
                                        $mw_share_message = "🚀 Grow Your Business with MiniWebsite\n\n"
                                            . "Hi 👋\n\n"
                                            . "In today's digital world, every business needs a professional online presence.\n\n"
                                            . "With MiniWebsite, you can showcase your complete business at one place and make it easier for customers to connect with you.\n\n"
                                            . "✅ Professional Business Website\n"
                                            . "✅ WhatsApp Inquiry System\n"
                                            . "✅ Products / Services Catalog with Price\n"
                                            . "✅ Dynamic QR Code\n"
                                            . "✅ Special Offers\n"
                                            . "✅ Photo Gallery & Videos\n"
                                            . "✅ Call, WhatsApp, Google Map & Email Buttons\n"
                                            . "✅ Customer Management Tools\n"
                                            . "✅ Purane Customers se Dobara Business (Repeat Sales)\n\n"
                                            . "💰 Original Price ₹1299/- for 1 Year (GST Included)\n"
                                            . "💰 [NEW DISCOUNTED PRICE] is Just ₹999/- for 1 Year (GST Included)\n"
                                            . "(Only for FIRST 500 CUSTOMERS)\n\n"
                                            . "👉 Less than ₹3 per day to bring your business online and grow your sales.\n\n"
                                            . "No technical knowledge required.\n"
                                            . "Manage and update your MiniWebsite anytime from your own dashboard.\n\n"
                                            . "👇 Create Your MiniWebsite Today\n\n"
                                            . $customer_ref_url . "\n\n"
                                            . "🌐 www.MiniWebsite.in\n\n"
                                            . "Your business deserves a professional online identity!";
                                    } else {
                                        // WHATSAPP MESSAGE TEMPLATE 07
                                        $mw_share_message = "🚀 Grow Your Business with MiniWebsite\n\n"
                                            . "In today's digital world, every business needs a professional online presence.\n\n"
                                            . "With MiniWebsite, you can showcase your complete business at one place and make it easier for customers to connect with you.\n\n"
                                            . "✅ Professional Business Website\n"
                                            . "✅ WhatsApp Inquiry System\n"
                                            . "✅ Product / Service Catalog with Price\n"
                                            . "✅ Dynamic QR Code\n"
                                            . "✅ Special Offers\n"
                                            . "✅ Photo Gallery & Videos\n"
                                            . "✅ Call, WhatsApp, Google Map & Email Buttons\n"
                                            . "✅ Customer Management Tools\n"
                                            . "✅ Purane Customers se Dobara Business (Repeat Sales)\n\n"
                                            . "💰 Original Price ₹1299/- for 1 Year (GST Included)\n"
                                            . "💰 [NEW DISCOUNTED PRICE] is Just ₹999/- for 1 Year (GST Included)\n\n"
                                            . "👉 Less than ₹3 per day to bring your business online & boost your sales.\n\n"
                                            . "No technical knowledge required.\n"
                                            . "Manage and update your MiniWebsite anytime from your own dashboard.\n\n"
                                            . "🎁 Exclusive ADDITIONAL REFERRAL DISCOUNT for FIRST 500 CUSTOMERS\n\n"
                                            . "✅ ₹100 OFF on 1 Year Plan\n"
                                            . "✅ ₹200 OFF on 2 Year Plan\n"
                                            . "✅ ₹300 OFF on 3 Year Plan\n\n"
                                            . "👇 Create Your MiniWebsite Today\n\n"
                                            . $customer_ref_url . "\n\n"
                                            . "🌐 www.MiniWebsite.in\n\n"
                                            . "Your business deserves a professional online identity!";
                                    }
                                    ?>
                                    <ul>
                                        <li><a href="https://api.whatsapp.com/send?text=<?php echo urlencode($mw_share_message); ?>" target="_blank"><img src="../../assets/images/whatsapp.png" alt=""></a></li>
                                        <li><a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($customer_ref_url); ?>" target="_blank"><img src="../../assets/images/facebook.png" alt=""></a></li>
                                        <li><a href="https://www.instagram.com/share?url=<?php echo urlencode($customer_ref_url); ?>" target="_blank"><img src="../../assets/images/instagram.png" alt=""></a></li>
                                        <li><a href="https://twitter.com/intent/tweet?text=<?php echo urlencode('Join using my referral link: ' . $customer_ref_url); ?>&url=<?php echo urlencode($customer_ref_url); ?>" target="_blank"><img src="../../assets/images/twitter.png" alt=""></a></li>
                                        <li><a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode($customer_ref_url); ?>" target="_blank"><img src="../../assets/images/linkedin.png" alt=""></a></li>
                                    </ul>
                                </div>
                    <?php endif; ?>


                                <?php if($collaboration_enabled || $current_role == 'TEAM'): ?>
<?php if($mw_referral_id == 1 || $current_role == 'TEAM'): ?>
<hr/>
<?php endif; ?>

                            <div class="referral-id">Franchise Referral ID</div>
                                <div class="referral-container">
                                    <div class="referral-box col-md-6">
                                        <p id="collab_link_value"><?php echo htmlspecialchars($site_base_url); ?>/registration/franchisee-registration.php?ref=<?php echo htmlspecialchars($user_referral_code); ?></p>
                                        <button class="copy-btn" onclick="copyToClipboard('collab_link')">COPY LINK</button>
                                    </div>
                                    <div class="referral-box col-md-6">
                                        <p id="collab_code_value"><?php echo $user_referral_code; ?></p>
                                        <button class="copy-btn" onclick="copyToClipboard('collab_code')">COPY CODE</button>
                                    </div>
                                </div>

                                <div class="social-icons">
                                    <p>Refer Franchise</p>
                                    <?php
                                    $franchisee_ref_url = $site_base_url . '/registration/franchisee-registration.php?ref=' . $user_referral_code;

                                    // Choose Franchise referral WhatsApp template based on role:
                                    // Template 08  -> Influencer/Creator (has ₹3,000 instant discount benefit)
                                    // Template 09  -> FSE Team OR Franchisee Distributor freelancer (All Freelancers)
                                    if ($user_influencer === 'YES') {
                                        // WHATSAPP MESSAGE TEMPLATE 08 (Influencer/Creator)
                                        $franchise_share_message = "🚀 Start Your Own Digital Business with MiniWebsite\n\n"
                                            . "Hi 👋\n\n"
                                            . "If you're looking for a genuine business opportunity with low investment and high earning potential, this is worth checking out.\n\n"
                                            . "MiniWebsite.in is expanding its Franchise Network across India.\n\n"
                                            . "Become a MiniWebsite Franchise Partner and help businesses go online while building your own profitable business.\n\n"
                                            . "💼 Why Become a MiniWebsite Franchise Partner?\n\n"
                                            . "✅ High-demand digital product\n"
                                            . "✅ Every business is your potential customer\n"
                                            . "✅ No technical knowledge required\n"
                                            . "✅ Work from anywhere\n"
                                            . "✅ Dedicated Franchise Dashboard\n"
                                            . "✅ Complete Training & Marketing Support\n"
                                            . "✅ Long-term business opportunity\n\n"
                                            . "⭐ MW Full Franchise Plan\n"
                                            . "₹35,400 (GST Included | One-Time)\n\n"
                                            . "🎁 EXCLUSIVE REFERRAL BENEFIT\n\n"
                                            . "Use my referral link and get\n"
                                            . "🎉 ₹3,000 INSTANT DISCOUNT\n"
                                            . "on Franchise Registration.*\n"
                                            . "(Limited Time Offer)\n\n"
                                            . "💵 Approx. Franchise Profit Per MiniWebsite Sale\n\n"
                                            . "🟢 1 Year Plan → ₹586\n"
                                            . "🔵 2 Year Plan → ₹1,003\n"
                                            . "🟣 3 Year Plan → ₹1,416\n\n"
                                            . "Additional Renewal Earning - 20% of the total renewal amount (without  GST)\n\n"
                                            . "📈 Join early and grow with MiniWebsite as we expand across India.\n\n"
                                            . "👇 Apply Now\n\n"
                                            . $franchisee_ref_url . "\n\n"
                                            . "🌐 www.MiniWebsite.in";
                                    } else {
                                        // WHATSAPP MESSAGE TEMPLATE 09 (FSE Team / Franchise Distributor freelancer)
                                        $franchise_share_message = "🚀 Start Your Own Digital Business with MiniWebsite\n\n"
                                            . "Hi 👋\n\n"
                                            . "If you're looking for a genuine business opportunity with low investment and long-term earning potential, this is worth checking out.\n\n"
                                            . "MiniWebsite.in is expanding its Franchise Network across India.\n\n"
                                            . "Become a MiniWebsite Franchise Partner and help businesses go online while building your own profitable business.\n\n"
                                            . "💼 Why Become a MiniWebsite Franchise Partner?\n\n"
                                            . "✅ High-demand digital product\n"
                                            . "✅ Every business is your potential customer\n"
                                            . "✅ No technical knowledge required\n"
                                            . "✅ Work from anywhere\n"
                                            . "✅ Dedicated Franchise Dashboard\n"
                                            . "✅ Complete Training & Marketing Support\n"
                                            . "✅ Long-term business opportunity\n\n"
                                            . "💰 Franchise Plans\n\n"
                                            . "⭐ MW Full Franchise\n"
                                            . "Only ₹35,400 (GST Included | One-Time)\n\n"
                                            . "💵 Approx.  Franchise Profit Per MiniWebsite Sale\n\n"
                                            . "🟢 1 Year Plan → ₹586\n"
                                            . "🔵 2 Year Plan → ₹1,003\n"
                                            . "🟣 3 Year Plan → ₹1,416\n\n"
                                            . "Additional Renewal Earning - 20% of the total renewal amount (without  GST)\n\n"
                                            . "📈 With active selling, many partners recover their investment early and continue building a profitable business.\n\n"
                                            . "👇 Apply Now\n\n"
                                            . $franchisee_ref_url . "\n\n"
                                            . "🌐 www.MiniWebsite.in";
                                    }
                                    ?>
                                    <ul>
                                        <li><a href="https://api.whatsapp.com/send?text=<?php echo urlencode($franchise_share_message); ?>" target="_blank"><img src="../../assets/images/whatsapp.png" alt=""></a></li>
                                        <li><a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($franchisee_ref_url); ?>" target="_blank"><img src="../../assets/images/facebook.png" alt=""></a></li>
                                        <li><a href="https://www.instagram.com/share?url=<?php echo urlencode($franchisee_ref_url); ?>" target="_blank"><img src="../../assets/images/instagram.png" alt=""></a></li>
                                        <li><a href="https://twitter.com/intent/tweet?text=<?php echo urlencode('Join using my collaboration link: ' . $franchisee_ref_url); ?>&url=<?php echo urlencode($franchisee_ref_url); ?>" target="_blank"><img src="../../assets/images/twitter.png" alt=""></a></li>
                                        <li><a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode($franchisee_ref_url); ?>" target="_blank"><img src="../../assets/images/linkedin.png" alt=""></a></li>
                                    </ul>
                                </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php if ($current_role == 'FRANCHISEE'): ?>
<!-- Create Account Modal for Franchisee -->
<div id="createAccountModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="back-btn" onclick="closeCreateAccountModal()">
                <i class="fa fa-arrow-left"></i>
            </button>
            <h4 class="modal-title">Create An Account</h4>
        </div>
        <div class="modal-body">
            <p class="modal-description">Create an account to create your Mini Website. Customer will receive an OTP email with Terms &amp; Privacy Policy (MAIL 04B).</p>
            <form id="createAccountForm" method="POST" action="">
                <div id="createAccountFields">
                <div class="form-group">
                    <input type="text" class="form-control" id="fullName" name="fullName" placeholder="Full Name" required>
                </div>
                <div class="form-group">
                    <input type="email" class="form-control" id="emailAddress" name="emailAddress" placeholder="Email Address" required>
                </div>
                <div class="form-group">
                    <input type="text" class="form-control" id="companyname" name="companyname" placeholder="Company Name for mini website" required>
                </div>
                <div class="form-group">
                    <input type="tel" class="form-control" id="mobileNumber" name="mobileNumber" placeholder="Mobile Number" required>
                </div>
                <div class="form-group">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                </div>
                </div>
                <div id="createAccountOtpStep" style="display:none;">
                    <p class="modal-description">Enter the OTP sent to the customer's email to verify and complete account creation.</p>
                    <div class="form-group">
                        <input type="text" class="form-control" id="customerOtp" name="customerOtp" placeholder="Enter OTP" inputmode="numeric" maxlength="6" autocomplete="one-time-code">
                    </div>
                </div>
                <button type="submit" class="btn-create-account" id="createAccountSubmitBtn">SEND OTP</button>
            </form>
        </div>
    </div>
</div>

<style>
/* Modal Styles for Franchisee */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.modal-content {
    background: #1e3a8a;
    border-radius: 15px;
    width: 100%;
    max-width: 400px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px) scale(0.9);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    padding: 20px 25px 10px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.back-btn {
    background: none;
    border: none;
    color: white;
    font-size: 18px;
    cursor: pointer;
    padding: 5px;
    border-radius: 50%;
    transition: background-color 0.2s;
}

.back-btn:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

.modal-title {
    color: white;
    font-size: 18px;
    font-weight: 600;
    margin: 0;
    font-family: 'Barlow', sans-serif;
}

.modal-body {
    padding: 0 25px 25px;
}

.modal-description {
    color: white;
    font-size: 14px;
    margin-bottom: 25px;
    opacity: 0.9;
    line-height: 1.4;
}

.form-group {
    margin-bottom: 20px;
}

.form-control {
    width: 100%;
    padding: 15px 20px;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    background-color: white;
    color: #333;
    box-sizing: border-box;
    transition: all 0.2s ease;
}

.form-control:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2);
    transform: translateY(-1px);
}

.form-control::placeholder {
    color: #999;
    font-size: 16px;
}

.btn-create-account {
    width: 100%;
    background: #ffbe17;
    color: white;
    border: none;
    padding: 15px 20px;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    cursor: pointer;
    transition: all 0.2s ease;
    margin-top: 10px;
}

.btn-create-account:hover {
    background: #e55a2b;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255, 107, 53, 0.3);
}

.btn-create-account:active {
    transform: translateY(0);
}

.btn-create-account.loading {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
}

/* Action card + icon styles: assets/css/common.css (.mw-dash-card-*) */
</style>

<script>
// Modal Functions for Franchisee
function openCreateAccountModal() {
    document.getElementById('createAccountModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeCreateAccountModal() {
    document.getElementById('createAccountModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    document.getElementById('createAccountForm').reset();
    var otpStep = document.getElementById('createAccountOtpStep');
    var fields = document.getElementById('createAccountFields');
    var submitBtn = document.getElementById('createAccountSubmitBtn');
    if (otpStep) otpStep.style.display = 'none';
    if (fields) fields.style.display = 'block';
    if (submitBtn) submitBtn.textContent = 'SEND OTP';
    window.franchiseOtpStepActive = false;
}

// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('createAccountModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeCreateAccountModal();
            }
        });
    }
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal && modal.style.display === 'flex') {
            closeCreateAccountModal();
        }
    });
    
    // Form submission handling
    var form = document.getElementById('createAccountForm');
    if (form) {
        window.franchiseOtpStepActive = false;
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('createAccountSubmitBtn');
            const originalText = submitBtn.textContent;
            const otpStep = document.getElementById('createAccountOtpStep');
            const fields = document.getElementById('createAccountFields');

            if (!window.franchiseOtpStepActive) {
                const confirmed = confirm('Please make sure your information is correct before proceeding. An OTP will be sent to the customer email. Click OK to continue.');
                if (!confirmed) return;
            }

            submitBtn.textContent = window.franchiseOtpStepActive ? 'CREATING...' : 'SENDING OTP...';
            submitBtn.classList.add('loading');

            const formData = new FormData(this);
            formData.append('franchisee_email', '<?php echo $franchisee_email; ?>');

            if (window.franchiseOtpStepActive) {
                formData.append('action', 'create_customer_account');
                formData.append('otp', document.getElementById('customerOtp').value.trim());
            } else {
                formData.append('action', 'franchise_send_create_otp');
            }

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                }
                return response.text().then(text => {
                    throw new Error('Server returned non-JSON response: ' + text.substring(0, 200));
                });
            })
            .then(data => {
                if (data.success && data.requires_otp) {
                    window.franchiseOtpStepActive = true;
                    if (otpStep) otpStep.style.display = 'block';
                    if (fields) fields.style.display = 'none';
                    submitBtn.textContent = 'VERIFY & CREATE ACCOUNT';
                    alert(data.message || 'OTP sent to customer email.');
                    return;
                }
                if (data.success) {
                    let message = 'Account created successfully!';
                    if (data.email_sent !== undefined) {
                        message += data.email_sent ? '\n✅ Welcome email sent to customer' : '\n⚠️ Account created but welcome email failed';
                    }
                    alert(message);
                    closeCreateAccountModal();
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to create account'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred: ' + error.message);
            })
            .finally(() => {
                if (!window.franchiseOtpStepActive || submitBtn.textContent === 'CREATING...') {
                    submitBtn.textContent = window.franchiseOtpStepActive ? 'VERIFY & CREATE ACCOUNT' : originalText;
                }
                submitBtn.classList.remove('loading');
            });
        });
    }
});
</script>
<?php endif; ?>

<!-- Invoice History Modal -->
<style>
    #invoiceHistoryModal .modal-body {
        overflow-x: auto;
        max-width: 100%;
    }
    #invoiceHistoryModal .mw-table-scroll {
        max-width: 100%;
    }
</style>
<div class="modal fade" id="invoiceHistoryModal" tabindex="-1" aria-labelledby="invoiceHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title" id="invoiceHistoryModalLabel">Invoice History</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="invoiceHistoryContent">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewInvoiceHistory(cardId) {
    // Show loading
    document.getElementById('invoiceHistoryContent').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    
    // Show modal
    var modal = new bootstrap.Modal(document.getElementById('invoiceHistoryModal'));
    modal.show();
    
    // Fetch invoice history via AJAX
    fetch('view_invoice_history.php?card_id=' + cardId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('invoiceHistoryContent').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('invoiceHistoryContent').innerHTML = '<div class="alert alert-danger">Error loading invoice history: ' + error.message + '</div>';
        });
}
</script>
<style>
    .paynow_btn{
        color: #212529 !important;
    }
    .lightGray{
        color: #666666 !important;
    }
    /* Mobile table scroll — 320px / 375px / 425px (loads after common.css) */
    @media screen and (max-width: 767.98px) {
        main.Dashboard #dashboardTableWrap {
            display: block;
            width: 100%;
            max-width: 100%;
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch;
        }
        main.Dashboard #dashboardTableWrap table#ReferredUsers {
            width: auto !important;
            min-width: 52rem !important;
            max-width: none !important;
        }
        main.Dashboard #dashboardTableWrap table#ReferredUsers th,
        main.Dashboard #dashboardTableWrap table#ReferredUsers td {
            white-space: nowrap !important;
        }
    }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>



