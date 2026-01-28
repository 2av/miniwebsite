<?php
// Handle AJAX form submission for creating customer account (FRANCHISEE only) - BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_customer_account') {
    // Include only what we need for database operations
    require_once(__DIR__ . '/../../app/config/database.php');
    require_once(__DIR__ . '/../../app/helpers/role_helper.php');
    
    // Check if user is franchisee
    $current_role = get_current_user_role();
    if ($current_role !== 'FRANCHISEE') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    
    // Ensure we're not including any HTML output before JSON
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        $fullName = trim($_POST['fullName'] ?? '');
        $companyname = trim($_POST['companyname'] ?? '');
        $emailAddress = trim($_POST['emailAddress'] ?? '');
        $mobileNumber = trim($_POST['mobileNumber'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $franchisee_email = trim($_POST['franchisee_email'] ?? '');
        $card_id = str_replace(array(' ','.','&','/','','[',']'), array('-','','','-','',''), $companyname);
        
        // Validation
        if (empty($fullName) || empty($emailAddress) || empty($mobileNumber) || empty($password) || empty($companyname)) {
            throw new Exception('All fields are required');
        }
        
        if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address');
        }
        
        if (strlen($password) < 6) {
            throw new Exception('Password must be at least 6 characters long');
        }
        
        // Check if email already exists
        $email_check_query = "SELECT role, email FROM user_details WHERE email = ? LIMIT 1";
        $check_email = mysqli_prepare($connect, $email_check_query);
        mysqli_stmt_bind_param($check_email, "s", $emailAddress);
        mysqli_stmt_execute($check_email);
        $result = mysqli_stmt_get_result($check_email);
        if ($result && mysqli_num_rows($result) > 0) {
            $email_data = mysqli_fetch_array($result);
            $source = ucfirst(strtolower($email_data['role'] ?? 'user'));
            throw new Exception("This email address is already registered as a $source. Please use a different email.");
        }
        mysqli_stmt_close($check_email);
        
        // Check if mobile number already exists
        $mobile_check_query = "SELECT role, phone FROM user_details WHERE phone = ? LIMIT 1";
        $check_mobile = mysqli_prepare($connect, $mobile_check_query);
        mysqli_stmt_bind_param($check_mobile, "s", $mobileNumber);
        mysqli_stmt_execute($check_mobile);
        $result = mysqli_stmt_get_result($check_mobile);
        if ($result && mysqli_num_rows($result) > 0) {
            $mobile_data = mysqli_fetch_array($result);
            $source = ucfirst(strtolower($mobile_data['role'] ?? 'user'));
            throw new Exception("This mobile number is already registered as a $source. Please use a different mobile number.");
        }
        mysqli_stmt_close($check_mobile);
        
        // Verify wallet balance (must be >= 236)
        $wallet_balance = 0;
        $wallet_query = mysqli_query($connect, "SELECT w_balance FROM wallet WHERE f_user_email = '".$franchisee_email."' ORDER BY ID DESC LIMIT 1");
        if ($wallet_query && mysqli_num_rows($wallet_query) > 0) {
            $wallet_row = mysqli_fetch_array($wallet_query);
            $wallet_balance = floatval($wallet_row['w_balance'] ?? 0);
        }
        if ($wallet_balance < 236) {
            throw new Exception('Insufficient wallet balance. Please recharge at least Rs. 236. Current balance: ' . number_format($wallet_balance, 2));
        }
        
        // Generate sender token and hash password
        $sender_token   = rand(1000000000, 99999999999999999);
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert into legacy customer_login table
        $insert_query = "INSERT INTO customer_login (user_name, user_email, user_contact, user_password, user_active, sender_token, uploaded_date) 
                        VALUES (?, ?, ?, ?, 'YES', ?, NOW())";
        $stmt = mysqli_prepare($connect, $insert_query);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . mysqli_error($connect));
        }
        mysqli_stmt_bind_param($stmt, "sssss", $fullName, $emailAddress, $mobileNumber, $hashed_password, $sender_token);
        
        if (mysqli_stmt_execute($stmt)) {
            $customer_id = mysqli_insert_id($connect);
            mysqli_stmt_close($stmt);
            
            // Also create/update entry in unified user_details table with referral info (franchisee as referrer)
            $ip = mysqli_real_escape_string($connect, $_SERVER['REMOTE_ADDR'] ?? '');
            $status = 'ACTIVE';
            $safeFullName = mysqli_real_escape_string($connect, $fullName);
            $safeEmail = mysqli_real_escape_string($connect, $emailAddress);
            $safeMobile = mysqli_real_escape_string($connect, $mobileNumber);
            $safeFranchiseeEmail = mysqli_real_escape_string($connect, $franchisee_email);
            
            // Insert basic record if it doesn't exist yet
            mysqli_query($connect, "
                INSERT IGNORE INTO user_details
                    (role, email, phone, name, password, password_hash, ip, status, created_at, legacy_customer_id, referred_by)
                VALUES
                    ('CUSTOMER', '$safeEmail', '$safeMobile', '$safeFullName', '$hashed_password', '$hashed_password', '$ip', '$status', NOW(), ".(int)$customer_id.", '$safeFranchiseeEmail')
            ");
            
            // Ensure referred_by is set to franchisee for this customer (even if record already existed)
            mysqli_query($connect, "
                UPDATE user_details 
                SET referred_by = '$safeFranchiseeEmail'
                WHERE email = '$safeEmail' AND role = 'CUSTOMER'
            ");
            
            // Create a basic digi_card entry
            $card_insert_query = "INSERT INTO digi_card (user_email, f_user_email, d_comp_name, card_id, d_payment_status, d_card_status, uploaded_date, validity_date) 
                                 VALUES (?, ?, ?, ?, 'Success','Active', NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR))";
            $card_stmt = mysqli_prepare($connect, $card_insert_query);
            if (!$card_stmt) {
                throw new Exception('Card prepare failed: ' . mysqli_error($connect));
            }
            mysqli_stmt_bind_param($card_stmt, "ssss", $emailAddress, $franchisee_email, $companyname, $card_id);
            
            if (mysqli_stmt_execute($card_stmt)) {
                $new_card_auto_id = mysqli_insert_id($connect);
                mysqli_stmt_close($card_stmt);
                
                // Deduct Rs. 236 from wallet
                $new_balance = $wallet_balance - 236;
                $withdraw_amount_str = '-236';
                $order_id_for_wallet = (string)$new_card_auto_id;
                $wallet_insert = mysqli_prepare($connect, "INSERT INTO wallet (f_user_email, w_withdraw, w_order_id, w_balance, uploaded_date) VALUES (?, ?, ?, ?, NOW())");
                if ($wallet_insert) {
                    mysqli_stmt_bind_param($wallet_insert, "sssd", $franchisee_email, $withdraw_amount_str, $order_id_for_wallet, $new_balance);
                    mysqli_stmt_execute($wallet_insert);
                    mysqli_stmt_close($wallet_insert);
                }
                
                // Send welcome email (if function exists)
                $email_sent = false;
                if (file_exists(__DIR__ . '/../../common/mailtemplate/send_customer_welcome_email.php')) {
                    require_once(__DIR__ . '/../../common/mailtemplate/send_customer_welcome_email.php');
                    $franchisee_name = 'Franchisee';
                    $franchisee_query = mysqli_query($connect, "SELECT f_user_name FROM franchisee_login WHERE f_user_email = '$franchisee_email'");
                    if ($franchisee_query && mysqli_num_rows($franchisee_query) > 0) {
                        $franchisee_data = mysqli_fetch_array($franchisee_query);
                        $franchisee_name = $franchisee_data['f_user_name'] ?? 'Franchisee';
                    }
                    $email_sent = sendCustomerWelcomeEmail($fullName, $emailAddress, $password, $franchisee_name);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Account created successfully' . ($email_sent ? ' and welcome email sent' : ' (email sending failed)'),
                    'customer_id' => $customer_id,
                    'email_sent' => $email_sent
                ]);
            } else {
                mysqli_stmt_close($card_stmt);
                throw new Exception('Failed to create card: ' . mysqli_stmt_error($card_stmt));
            }
        } else {
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to create account: ' . mysqli_stmt_error($stmt));
        }
        
    } catch (Exception $e) {
        error_log("Customer account creation error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Regular page load - include header and other files
include __DIR__ . '/../includes/header.php';

// Get current role
$current_role = get_current_user_role();
$user_email = get_user_email();

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

// Get user's cards based on role
$query = null;
$user_referral_code = '';
$user_email = get_user_email(); // Get email for all roles

if ($current_role == 'CUSTOMER' || $current_role == 'TEAM') {
    $user_referral_code = $_SESSION['user_referral_code'] ?? '';
    
    // For TEAM, simply generate a referral code and keep it in session (no separate team_members table)
    if ($current_role == 'TEAM' && empty($user_referral_code)) {
        $user_referral_code = strtoupper(substr(md5($user_email . time()), 0, 8));
        $_SESSION['user_referral_code'] = $user_referral_code;
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
    
    // Get wallet balance for franchisee
    $wallet_balance = 0;
    $wallet_query = mysqli_query($connect, "SELECT w_balance FROM wallet WHERE f_user_email = '$franchisee_email' ORDER BY ID DESC LIMIT 1");
    if ($wallet_query && mysqli_num_rows($wallet_query) > 0) {
        $wallet_row = mysqli_fetch_array($wallet_query);
        $wallet_balance = floatval($wallet_row['w_balance'] ?? 0);
    }
    $has_sufficient_balance = $wallet_balance >= 236;
    
    // Get total cards created by this franchisee
    $total_cards_query = mysqli_query($connect, "SELECT COUNT(*) as total_cards FROM digi_card WHERE f_user_email = '$franchisee_email'");
    $total_cards = 0;
    if ($total_cards_query && mysqli_num_rows($total_cards_query) > 0) {
        $total_cards_row = mysqli_fetch_array($total_cards_query);
        $total_cards = intval($total_cards_row['total_cards'] ?? 0);
    }
    
    // Query for "Manage Users" table - users created by this franchisee
    $manage_users_query = mysqli_query($connect, "
        SELECT 
            cl.id,
            cl.user_name,
            cl.user_contact,
            cl.user_email,
            cl.uploaded_date,
            cl.referral_code,
            cl.referred_by,
            dc.id as card_id,
            dc.d_comp_name,
            dc.d_payment_status,
            dc.uploaded_date as card_created_date,
            dc.d_payment_date,
            dc.validity_date
        FROM customer_login cl
        LEFT JOIN digi_card dc ON cl.user_email = dc.user_email
        WHERE dc.f_user_email = '$franchisee_email'
        ORDER BY cl.uploaded_date DESC
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

// Get MW Referral ID status from user_details table
$mw_referral_id = 0;
$mw_referral_query = mysqli_query($connect, "SELECT mw_referral_id FROM user_details WHERE LOWER(TRIM(email))='$user_email_lower' LIMIT 1");
if ($mw_referral_query && mysqli_num_rows($mw_referral_query) > 0) {
    $mw_referral_data = mysqli_fetch_array($mw_referral_query);
    $mw_referral_id = intval($mw_referral_data['mw_referral_id'] ?? 0);
}
?>

<main class="Dashboard">
    <div class="container-fluid  customer_content_area">
        <div class="main-top">
            <span class="heading"><?php echo $page_title; ?></span> 
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Mini Website</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo $page_title; ?></li>
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
                    <?php if(!$is_verified): ?>
                        <?php 
                        require_once(__DIR__ . '/../../app/helpers/verification_helper.php');
                        showVerificationWarning(); 
                        ?>
                    <?php endif; ?>
                    
                    <div class="FranchiseeDashboard-head">
                        <div class="d-flex flex-wrap w-100 grid row-items-3" data-itemcount="3">
                            <!-- Create New Mini Website Card -->
                            <div class="card_area">
                                <?php if($is_verified): ?>
                                    <?php if($has_sufficient_balance): ?>
                                        <a href="#" onclick="openCreateAccountModal(); return false;">
                                            <div class="card">
                                                <div class="img">
                                                    <img class="img-fluid" style="height:auto" src="<?php echo $assets_base; ?>/assets/images/Edit-icon.png" alt="">
                                                </div>
                                                <div class="content">
                                                    <p> Create New<br>Mini Website</p>
                                                </div>
                                            </div>
                                        </a>
                                    <?php else: ?>
                                        <div style="position: relative;">
                                            <div class="card" style="opacity: 0.6; cursor: not-allowed;">
                                                <div class="img">
                                                    <img class="img-fluid" style="height:auto" src="<?php echo $assets_base; ?>/assets/images/Edit-icon.png" alt="">
                                                </div>
                                                <div class="content">
                                                    <p> Create New<br>Mini Website</p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="card" style="opacity: 0.6; cursor: not-allowed;" title="Document verification required">
                                        <div class="img">
                                            <img class="img-fluid" style="height:auto" src="<?php echo $assets_base; ?>/assets/images/Edit-icon.png" alt="">
                                        </div>
                                        <div class="content">
                                            <p> Create New<br>Mini Website</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- MW Created Card -->
                            <div class="card_area">
                                <a href="">
                                    <div class="card">
                                        <div class="img">
                                            <img class="img-fluid" style="height:auto" src="<?php echo $assets_base; ?>/assets/images/check.png" alt="">
                                        </div>
                                        <div class="content">
                                            <p> MW Created</p>
                                            <h4 class="marginbottom5"><?php echo $total_cards; ?></h4>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            
                            <!-- Wallet Balance Card -->
                            <div class="card_area" style="position: relative;">
                                <a href="<?php echo $nav_base; ?>/wallet">
                                    <div class="card">
                                        <div class="img">
                                            <img class="img-fluid" style="height:auto" src="<?php echo $assets_base; ?>/assets/images/wallet-bl.png" alt="">
                                        </div>
                                        <div class="content">
                                            <p>Wallet Balance</p>
                                            <h4><i class="fa fa-inr" aria-hidden="true"></i> <?php echo number_format($wallet_balance, 2); ?></h4>
                                        </div>
                                    </div>
                                </a>
                                <?php if(!$has_sufficient_balance): ?>
                                    <p class="low_balance_title">Your balance is low. Please recharge wallet.</p>
                                <?php endif; ?> 
                            </div>
                        </div>
                    </div>
                    
                    <!-- Manage Users Section -->
                    <div class="ManageUsers">
                        <h4 class="heading">Manage Users: </h4>
                        <div class="table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                            <table id="ReferredUsers" class="display table" style="text-align: center; min-width: 600px;">
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
                                                $status_text = 'Trial';
                                            }
                                            
                                            $has_cards = !empty($user['card_id']);
                                    ?>
                                    <tr>
                                        <td class="text-left"><?php echo $user['id']; ?></td>
                                        <td class="text-left"><?php echo $user['card_id'] ?? '-'; ?></td>
                                        <td class="text-left" style="display:flex; align-items: center; gap: 5px;">
                                            <?php if($has_cards): ?>
                                                <a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/n.php?n=<?php echo $user['card_id']; ?>" target="_blank" style="text-decoration: none; color: inherit; margin-right:6px;">
                                                    <span class="view_icon_style"><i class="fa-regular fa-eye"></i></span>
                                                </a>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($user['user_email']); ?>
                                        </td>
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
                    <div class="row">
                        <div class="col-sm-3 top_section">
                            <a href="../website/business-name.php?new=1">
                                <div class="card">
                                    <div class="img">
                                        <img class="img-fluid" src="../../assets/images/Edit-icon.png" alt="">
                                    </div>
                                    <div class="content">
                                        <p> Create New <br>Mini Website</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                    <!-- CUSTOMER/TEAM TABLE -->
                    <div class="table-container">
                        <table id="ReferredUsers" class="display table" style="text-align: center;">
                            <thead class="bg-secondary">
                                <tr>
                                    <th>MW ID</th>
                                    <th>Company Name</th>
                                    <th>Date Created</th>
                                    <th>Validity Date</th>
                                    <th style="text-align: left;">MW Status</th>
                                    <th>View/Edit/Share</th>
                                    <th style="text-align: left;">User Payment Status</th>
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
                                     
                                    // Check if user has collaboration enabled
                                    if($row['complimentary_enabled'] == 'Yes') {
                                        $status_class = 'bg-success';
                                        $status_text = 'Active';
                                    } else if ($payment_status == 'Success') {
                                        // Paid: check validity_date for expiry
                                        $is_expired = (!empty($row['validity_date']) && $row['validity_date'] != '0000-00-00 00:00:00') ? (strtotime($row['validity_date']) < time()) : false;
                                        if ($is_expired) {
                                            $status_class = 'bg-secondary lightGray';
                                            $status_text = 'Expired <br/>on ' . date('d-m-Y', strtotime($row['validity_date']));
                                        } else {
                                            $status_class = 'bg-success';
                                            $status_text = 'Active';
                                        }
                                    } else {
                                        // Trial logic: show 7 Day Trial or Inactive after 7 days
                                        $trial_end = date('Y-m-d H:i:s', strtotime($row['uploaded_date'] . ' +7 days'));
                                        if (strtotime($trial_end) < time()) {
                                            $status_class = 'bg-secondary lightGray';
                                            $status_text = 'Inactive';
                                        } else {
                                            $status_class = 'bg-pending';
                                            $status_text = '7 Day Trial';
                                        }
                                    }
                            ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td>
                                     <?php echo $row['d_comp_name']; ?>
                                </td>
                                <td><?php echo date('d-m-Y', strtotime($row['uploaded_date'])); ?></td>
                                <td><?php echo $validity_date; ?></td>
                                <td><span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                <td style="display:flex; align-items: center; gap: 5px;">
                                    <?php 
                                    // Check if user is akhilesh@yopmail.com for new flow, otherwise use old flow
                                   // if($_SESSION['user_email'] == 'akhilesh@yopmail.com') {
                                        $edit_link = "../website/business-name.php?card_number=" . $row['id'];
                                   // } else {
                                      //  $edit_link = "../../panel/login/create_card.php?card_number=" . $row['id'];
                                   // }
                                    ?>
                                    <span class="view"> <a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/n.php?n=<?php echo $row['card_id']; ?>" target="_blank" style="text-decoration: none; color: inherit;">
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
                                </td>
                                <td>
                                    <?php if($row['complimentary_enabled'] == 'Yes') { ?>
                                        <span class="badge bg-info">Complimentary</span>
                                    <?php } else if($payment_status != 'Success') { ?>
                                        <button class="btn btn-primary paynow_btn" onclick="window.location.href='<?php echo $assets_base; ?>/payment/pay_miniwebsite.php?id=<?php echo $row['id']; ?>&source=<?php echo strtolower($current_role); ?>'">Pay Now</button>
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
                                    // Only show invoice options for cards created by the user themselves
                                    if(empty($row['f_user_email'])) {
                                        // Check if invoice details exist for this card
                                        $invoice_check_query = mysqli_query($connect, "SELECT COUNT(*) as invoice_count FROM invoice_details WHERE card_id = '" . mysqli_real_escape_string($connect, $row['id']) . "'");
                                        $invoice_check_result = mysqli_fetch_array($invoice_check_query);
                                        $has_invoices = $invoice_check_result['invoice_count'] > 0;
                                        
                                        if($payment_status == 'Success') { 
                                        ?>
                                           <?php if($has_invoices) { ?>
                                                <div class="d-flex  align-items-center">
                                                    <button class="btn btn-info btn-sm view_btn" onclick="viewInvoiceHistory(<?php echo $row['id']; ?>)" title="View Invoice History">
                                                          View
                                                    </button>
                                                </div>
                                            <?php } else { ?>
                                                 <div class="d-flex align-items-center">
                                                 <span class="download"><a target="_blank" href="download_invoice_new.php?id=<?php echo $row['id']; ?>" title="Download Invoice">
                                                    <span class="download_icon_style"><i class="fa-solid fa-arrow-down"></i></span>
                                                </a></span>  </div>
                                             <?php } ?>
                                        <?php } else { ?>
                                            <div class="d-flex  align-items-center">
                                                <span class="download"  title="Payment required to download invoice">
                                                    <span class="download_icon_style" style="filter: grayscale(100%); opacity: 0.5;"><i class="fa-solid fa-arrow-down"></i></span>
                                                    <!-- <img src="../../../assets/images/download.png" alt="" > -->
                                                </span>
                                                
                                            </div>
                                        <?php } 
                                    } else {
                                        // For franchisee-created cards, show hyphens
                                        echo '<span style="color: #6c757d; font-size: 18px;">-</span>';
                                    } ?>
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
                                        <p>https://miniwebsite.in/registration/customer-registration.php?ref=<?php echo $user_referral_code; ?></p>
                                        <button class="copy-btn" onclick="copyToClipboard('regular_link')">COPY LINK</button>
                                    </div>
                                    <div class="referral-box col-md-6">
                                        <p><?php echo $user_referral_code; ?></p>
                                        <button class="copy-btn" onclick="copyToClipboard('regular_code')">COPY CODE</button>
                                    </div>
                                </div>

                                <div class="social-icons">
                                    <p>Refer Mini Website</p>
                                    <ul>
                                        <li><a href="https://api.whatsapp.com/send?text=<?php echo urlencode('Join using my referral link: https://miniwebsite.in/registration/customer-registration.php?ref='.$user_referral_code); ?>" target="_blank"><img src="../../assets/images/whatsapp.png" alt=""></a></li>
                                        <li><a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('https://miniwebsite.in/registration/customer-registration.php?ref='.$user_referral_code); ?>" target="_blank"><img src="../../assets/images/facebook.png" alt=""></a></li>
                                        <li><a href="https://www.instagram.com/share?url=<?php echo urlencode('https://miniwebsite.in/registration/customer-registration.php?ref='.$user_referral_code); ?>" target="_blank"><img src="../../assets/images/instagram.png" alt=""></a></li>
                                        <li><a href="https://twitter.com/intent/tweet?text=<?php echo urlencode('Join using my referral link: https://miniwebsite.in/registration/customer-registration.php?ref='.$user_referral_code); ?>&url=<?php echo urlencode('https://miniwebsite.in/registration/customer-registration.php?ref='.$user_referral_code); ?>" target="_blank"><img src="../../assets/images/twitter.png" alt=""></a></li>
                                        <li><a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode('https://miniwebsite.in/registration/customer-registration.php?ref='.$user_referral_code); ?>" target="_blank"><img src="../../assets/images/linkedin.png" alt=""></a></li>
                                    </ul>
                                </div>
                    <?php endif; ?>


                                <?php if($collaboration_enabled): ?>
<?php if($mw_referral_id == 1): ?>
<hr/>
<?php endif; ?>

                            <div class="referral-id">Franchise Referral ID</div>
                                <div class="referral-container">
                                    <div class="referral-box col-md-6">
                                        <p>https://miniwebsite.in/registration/franchisee-registration.php?ref=<?php echo $user_referral_code; ?></p>
                                        <button class="copy-btn" onclick="copyToClipboard('collab_link')">COPY LINK</button>
                                    </div>
                                    <div class="referral-box col-md-6">
                                        <p><?php echo $user_referral_code; ?></p>
                                        <button class="copy-btn" onclick="copyToClipboard('collab_code')">COPY CODE</button>
                                    </div>
                                </div>

                                <div class="social-icons">
                                    <p>Refer Franchise</p>
                                    <ul>
                                        <li><a href="https://api.whatsapp.com/send?text=<?php echo urlencode('Join using my collaboration link: https://miniwebsite.in/registration/franchisee-registration.php?ref='.$user_referral_code); ?>" target="_blank"><img src="../../assets/images/whatsapp.png" alt=""></a></li>
                                        <li><a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('https://miniwebsite.in/registration/franchisee-registration.php?ref='.$user_referral_code); ?>" target="_blank"><img src="../../assets/images/facebook.png" alt=""></a></li>
                                        <li><a href="https://www.instagram.com/share?url=<?php echo urlencode('https://miniwebsite.in/registration/franchisee-registration.php?ref='.$user_referral_code); ?>" target="_blank"><img src="../../assets/images/instagram.png" alt=""></a></li>
                                        <li><a href="https://twitter.com/intent/tweet?text=<?php echo urlencode('Join using my collaboration link: https://miniwebsite.in/registration/franchisee-registration.php?ref='.$user_referral_code); ?>&url=<?php echo urlencode('https://miniwebsite.in/registration/franchisee-registration.php?ref='.$user_referral_code); ?>" target="_blank"><img src="../../assets/images/twitter.png" alt=""></a></li>
                                        <li><a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode('https://miniwebsite.in/registration/franchisee-registration.php?ref='.$user_referral_code); ?>" target="_blank"><img src="../../assets/images/linkedin.png" alt=""></a></li>
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
            <p class="modal-description">Create an account to create your Mini Website</p>
            <form id="createAccountForm" method="POST" action="">
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
                <button type="submit" class="btn-create-account">CREATE ACCOUNT</button>
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

/* Franchisee Dashboard Styles - Matching old dashboard */
.FranchiseeDashboard-head .row-items-3 {
    justify-content: center;
    align-items: center;
}

.FranchiseeDashboard-head a {
    color: #262626;
    text-decoration: none;
}

.FranchiseeDashboard-head .card {
    display: flex;
    flex-direction: row;
    justify-content: space-around;
    align-items: center;
    background: #eff3f7;
    border: none;
    padding: 20px;
    font-weight: 600;
    margin: 30px auto;
}

.FranchiseeDashboard-head .card .img img {
    min-width: 70px;
}

.FranchiseeDashboard-head .card_area .img img {
    width: 80%;
}

.FranchiseeDashboard-head .card .content {
    padding-left: 20px;
}

.FranchiseeDashboard-head .card .content p {
    font-size: 32px;
    line-height: 37px;
    text-align: center;
    margin: 0;
}

.FranchiseeDashboard-head .card .content h4 {
    font-size: 30px;
    line-height: 0px;
    margin: 0;
}

.FranchiseeDashboard-head .card .content h4.marginbottom5 {
    margin-bottom: 5px;
}

.low_balance_title {
    position: absolute;
    bottom: -25px;
    left: 0;
    right: 0;
    text-align: center;
    color: #ff6b6b;
    font-size: 15px;
    font-weight: 600;
    margin: 2px auto;
}

@media (max-width: 768px) {
    .FranchiseeDashboard-head .row-items-3 {
        flex-direction: column;
        justify-content: space-between;
        align-items: center;
    }
    .FranchiseeDashboard-head .card {
        width: 31rem !important;
        margin: 10px auto !important;
        padding: 10px 15px;
        height: 16vh;
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: space-evenly;
        gap: 3px;
    }
    .FranchiseeDashboard-head .card .img img {
        min-width: 53px;
        max-width: 50px;
    }
    .FranchiseeDashboard-head .card .content {
        padding-left: 0px;
        padding-top: 10px;
        width: 20rem;
    }
    .low_balance_title {
        color: #ff6b6b;
        font-size: 15px;
        margin: 2px auto;
    }
}
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
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const confirmed = confirm('Please make sure your information is correct before proceeding. Click OK to finish creating the account.');
            if (!confirmed) {
                return;
            }
            
            const submitBtn = document.querySelector('.btn-create-account');
            const originalText = submitBtn.textContent;
            
            submitBtn.textContent = 'CREATING...';
            submitBtn.classList.add('loading');
            
            const formData = new FormData(this);
            formData.append('action', 'create_customer_account');
            formData.append('franchisee_email', '<?php echo $franchisee_email; ?>');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        throw new Error('Server returned non-JSON response: ' + text.substring(0, 200));
                    });
                }
            })
            .then(data => {
                if (data.success) {
                    let message = 'Account created successfully!';
                    if (data.email_sent !== undefined) {
                        message += data.email_sent ? '\n✅ Welcome email sent to customer' : '\n⚠️ Account created but email sending failed';
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
                alert('An error occurred while creating the account: ' + error.message);
            })
            .finally(() => {
                submitBtn.textContent = originalText;
                submitBtn.classList.remove('loading');
            });
        });
    }
});
</script>
<?php endif; ?>

<!-- Invoice History Modal -->
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
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>



