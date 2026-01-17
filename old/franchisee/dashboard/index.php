<?php 
// Handle AJAX form submission for creating customer account FIRST (before any HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'create_customer_account') {
        // Include only what we need for database operations
        require_once('../../common/config.php');
    
    // Ensure we're not including any HTML output before JSON
    ob_clean();
    header('Content-Type: application/json');
    
    // Debug: Log the request
    error_log("AJAX request received: " . print_r($_POST, true));
    
    try {
        // Check database connection
        if (!$connect) {
            throw new Exception('Database connection failed: ' . mysqli_connect_error());
        }
        $fullName = trim($_POST['fullName'] ?? '');
        $companyname = trim($_POST['companyname'] ?? '');
        $emailAddress = trim($_POST['emailAddress'] ?? '');
        $mobileNumber = trim($_POST['mobileNumber'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $franchisee_email = trim($_POST['franchisee_email'] ?? '');
        $card_id =str_replace(array(' ','.','&','/','','[',']'),array('-','','','-','',''), $companyname );        
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
        
        // Check if email already exists in unified user_details table (any role)
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
        
        // Check if mobile number already exists in unified user_details table (any role)
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
        
        // Generate sender token
        $sender_token = rand(1000000000, 99999999999999999);
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert into legacy customer_login table using prepared statement (for backward compatibility)
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
            
            // Also create entry in unified user_details table (role = CUSTOMER)
            $ip = mysqli_real_escape_string($connect, $_SERVER['REMOTE_ADDR'] ?? '');
            $status = 'ACTIVE';
            $safeFullName = mysqli_real_escape_string($connect, $fullName);
            $safeEmail = mysqli_real_escape_string($connect, $emailAddress);
            $safeMobile = mysqli_real_escape_string($connect, $mobileNumber);

            mysqli_query($connect, "
                INSERT IGNORE INTO user_details
                    (role, email, phone, name, password, ip, status, created_at, legacy_customer_id)
                VALUES
                    ('CUSTOMER', '$safeEmail', '$safeMobile', '$safeFullName', '$hashed_password', '$ip', '$status', NOW(), ".(int)$customer_id.")
            ");
            
            // Create a basic digi_card entry for the customer with 1-year validity
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

                // Deduct Rs. 236 from wallet and record transaction
                $new_balance = $wallet_balance - 236;
                $withdraw_amount_str = '-236';
                $order_id_for_wallet = (string)$new_card_auto_id;
                $wallet_insert = mysqli_prepare($connect, "INSERT INTO wallet (f_user_email, w_withdraw, w_order_id, w_balance, uploaded_date) VALUES (?, ?, ?, ?, NOW())");
                $wallet_txn_id = null;
                if ($wallet_insert) {
                    mysqli_stmt_bind_param($wallet_insert, "sssd", $franchisee_email, $withdraw_amount_str, $order_id_for_wallet, $new_balance);
                    mysqli_stmt_execute($wallet_insert);
                    // Capture wallet transaction auto-increment ID for invoice mapping
                    $wallet_txn_id = mysqli_insert_id($connect);
                    mysqli_stmt_close($wallet_insert);
                }

                // Get franchisee billing details for invoice
                $franchisee_billing_query = mysqli_query($connect, "SELECT f_user_name, f_user_contact, f_user_address, f_user_state, f_user_city, f_user_pincode, f_user_gst FROM franchisee_login WHERE f_user_email = '$franchisee_email'");
                $franchisee_billing = mysqli_fetch_array($franchisee_billing_query);
                
                // Set default values if franchisee billing info is not available
                $billing_name = $franchisee_billing['f_user_name'] ?? $franchisee_email;
                $billing_contact = $franchisee_billing['f_user_contact'] ?? '';
                $billing_address = $franchisee_billing['f_user_address'] ?? '';
                $billing_state = $franchisee_billing['f_user_state'] ?? '';
                $billing_city = $franchisee_billing['f_user_city'] ?? '';
                $billing_pincode = $franchisee_billing['f_user_pincode'] ?? '';
                $billing_gst = $franchisee_billing['f_user_gst'] ?? '';

                // Insert invoice record for wallet deduction (treated as a payment)
                $wallet_charge = 200.00;
                $current_timestamp = date('Y-m-d H:i:s');
                $invoice_date = date('Y-m-d');

                // Generate next invoice number like KIR/00001
                $last_invoice_query = mysqli_query($connect, "SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number, '/', -1) AS UNSIGNED)) as last_number FROM invoice_details WHERE invoice_number LIKE 'KIR/%'");
                $last_invoice_result = $last_invoice_query ? mysqli_fetch_array($last_invoice_query) : null;
                $next_number = ($last_invoice_result['last_number'] ?? 0) + 1;
                $invoice_number = 'KIR/' . str_pad($next_number, 5, '0', STR_PAD_LEFT);

                // Prepare values (no GST split for wallet deduction)
                $original_amount = $wallet_charge;
                $discount_amount = 0;
                $sub_total = $wallet_charge;
                $cgst_amount = 18;
                $sgst_amount = 18;
                $igst_amount = 0;
                $gst_percentage = 0;
                $final_total = $wallet_charge+$cgst_amount+$sgst_amount;
                $hsn_sac_code = '998314';
                $service_name = 'MW- For '.$fullName.' (1 Year)';
                $payment_type = 'Wallet';
                // Prefer mapping to actual wallet transaction id if available
                $reference_number = 'WALLET-' . (!empty($wallet_txn_id) ? $wallet_txn_id : $order_id_for_wallet);
                
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
                    '" . mysqli_real_escape_string($connect, (string)$new_card_auto_id) . "',
                    '" . mysqli_real_escape_string($connect, $emailAddress) . "',
                    '" . mysqli_real_escape_string($connect, $fullName) . "',
                    '" . mysqli_real_escape_string($connect, $mobileNumber) . "',
                    '" . mysqli_real_escape_string($connect, $billing_name) . "',
                    '" . mysqli_real_escape_string($connect, $franchisee_email) . "',
                    '" . mysqli_real_escape_string($connect, $billing_contact) . "',
                    '" . mysqli_real_escape_string($connect, $billing_address) . "',
                    '" . mysqli_real_escape_string($connect, $billing_state) . "',
                    '" . mysqli_real_escape_string($connect, $billing_city) . "',
                    '" . mysqli_real_escape_string($connect, $billing_pincode) . "',
                    '" . mysqli_real_escape_string($connect, $billing_gst) . "',
                    '" . mysqli_real_escape_string($connect, (string)$original_amount) . "',
                    '" . mysqli_real_escape_string($connect, (string)$discount_amount) . "',
                    '" . mysqli_real_escape_string($connect, (string)$final_total) . "',
                    '', '0',
                    '', '', '',
                    'Success',
                    '" . mysqli_real_escape_string($connect, $current_timestamp) . "',
                    '" . mysqli_real_escape_string($connect, $service_name) . "',
                    '" . mysqli_real_escape_string($connect, $service_name) . "',
                    '" . mysqli_real_escape_string($connect, $hsn_sac_code) . "',
                    '1',
                    '" . mysqli_real_escape_string($connect, (string)$sub_total) . "',
                    '" . mysqli_real_escape_string($connect, (string)$final_total) . "',
                    '" . mysqli_real_escape_string($connect, (string)$sub_total) . "',
                    '" . mysqli_real_escape_string($connect, (string)$gst_percentage) . "',
                    '" . mysqli_real_escape_string($connect, (string)$igst_amount) . "',
                    '" . mysqli_real_escape_string($connect, (string)$cgst_amount) . "',
                    '" . mysqli_real_escape_string($connect, (string)$sgst_amount) . "',
                    '" . mysqli_real_escape_string($connect, (string)$final_total) . "',
                    '" . mysqli_real_escape_string($connect, $payment_type) . "',
                    '" . mysqli_real_escape_string($connect, $reference_number) . "',
                    '" . mysqli_real_escape_string($connect, $current_timestamp) . "',
                    '" . mysqli_real_escape_string($connect, $current_timestamp) . "'
                )";

                $invoice_result = mysqli_query($connect, $invoice_insert_query);
                if (!$invoice_result) {
                    error_log("Invoice creation failed: " . mysqli_error($connect));
                    error_log("Query: " . $invoice_insert_query);
                    throw new Exception('Failed to create invoice: ' . mysqli_error($connect));
                }
                
                // Send welcome email to customer
                require_once('../../common/mailtemplate/send_customer_welcome_email.php');
                
                // Get franchisee name for email
                $franchisee_name = 'Franchisee'; // Default name
                $franchisee_query = mysqli_query($connect, "SELECT f_user_name FROM franchisee_login WHERE f_user_email = '$franchisee_email'");
                if ($franchisee_query && mysqli_num_rows($franchisee_query) > 0) {
                    $franchisee_data = mysqli_fetch_array($franchisee_query);
                    $franchisee_name = $franchisee_data['f_user_name'] ?? 'Franchisee';
                }
                
                // Send welcome email
                error_log("Attempting to send welcome email to: " . $emailAddress);
                $email_sent = sendCustomerWelcomeEmail($fullName, $emailAddress, $password, $franchisee_name);
                error_log("Email sending result: " . ($email_sent ? 'SUCCESS' : 'FAILED'));
                
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
        // Log the error for debugging
        error_log("Customer account creation error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
    }
}

// Regular page load - include header and other files
include '../header.php';
require_once('../../common/verification_check.php');

// Check if franchisee is verified
$franchisee_email = $_SESSION['f_user_email'] ?? '';
$is_verified = isFranchiseeVerified($franchisee_email);
$verification_status = getVerificationStatus($franchisee_email);
?>
 
  
    <main class="Dashboard">
        <div class="container-fluid customer_content_area">
            <div class="main-top">
            <span class="heading">Franchise Dashboard</span> 
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                      <li class="breadcrumb-item"><a href="#">Mini Website </a></li>
                      <li class="breadcrumb-item active" aria-current="page">Franchisee Dashboard</li>
                    </ol>
                </nav>                              
            </div>
           
            <div class="card mb-4">
                <div class="card-body">
                    <?php if(!$is_verified): ?>
                        <?php showVerificationWarning(); ?>
                    <?php endif; ?>
                    
                    <?php 
                    // Check wallet balance for button state (moved to higher scope)
                    $button_wallet_balance = 0;
                    $button_wallet_query = mysqli_query($connect, "SELECT w_balance FROM wallet WHERE f_user_email = '$franchisee_email' ORDER BY ID DESC LIMIT 1");
                    if ($button_wallet_query && mysqli_num_rows($button_wallet_query) > 0) {
                        $button_wallet_row = mysqli_fetch_array($button_wallet_query);
                        $button_wallet_balance = floatval($button_wallet_row['w_balance'] ?? 0);
                    }
                    $has_sufficient_balance = $button_wallet_balance >= 236;
                    ?>
                    
                    <div class="FranchiseeDashboard-head">
                        <div class="d-flex flex-wrap w-100 grid row-items-3" data-itemcount="3">
                            <div class="card_area">
                                <?php if($is_verified): ?>
                                    <?php if($has_sufficient_balance): ?>
                                        <a href="#" onclick="openCreateAccountModal(); return false;">
                                            <div class="card">
                                                <div class="img">
                                                    <img class="img-fluid" style="height:auto" src="../../common/assets/img/Edit-icon.png" alt="">
                                                </div>
                                                <div class="content">
                                                    <p> Create New<br>
                                                        Mini Website</p>
                                                </div>
                                            </div>
                                        </a>
                                    <?php else: ?>
                                        <div style="position: relative;">
                                            <div class="card" style="opacity: 0.6; cursor: not-allowed;">
                                                <div class="img">
                                                    <img class="img-fluid" style="height:auto" src="../../common/assets/img/Edit-icon.png" alt="">
                                                </div>
                                                <div class="content">
                                                    <p> Create New<br>
                                                        Mini Website</p>
                                                    
                                                </div>
                                            </div>
                                           
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="card" style="opacity: 0.6; cursor: not-allowed;" title="Document verification required">
                                        <div class="img">
                                            <img class="img-fluid" style="height:auto" src="../../common/assets/img/Edit-icon.png" alt="">
                                        </div>
                                        <div class="content">
                                            <p> Create New<br>
                                                Mini Website</p>
                                            <h4></h4>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php
                            // Get franchisee email
                            $franchisee_email = $_SESSION['f_user_email'] ?? '';
                            
                            // Get total cards created by this franchisee
                            $total_cards_query = mysqli_query($connect, "SELECT COUNT(*) as total_cards FROM digi_card WHERE f_user_email = '$franchisee_email'");
                            $total_cards = mysqli_fetch_array($total_cards_query)['total_cards'];
                            
                            // Use the wallet balance already calculated above
                            $wallet_balance = $button_wallet_balance;
                            
                            ?>
                            
                            <div class="card_area">
                                <a href="">
                                    <div class="card">
                                        <div class="img">
                                            <img class="img-fluid" style="height:auto" src="../../common/assets/img/check.png" alt="">
                                        </div>
                                        <div class="content">
                                            <p> MW Created</p>
                                            <h4 class="marginbottom5"><?php echo $total_cards; ?></h4>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="card_area" style="position: relative;">
                                <a href="../wallet">
                                    <div class="card">
                                        <div class="img">
                                            <img class="img-fluid" style="height:auto" src="../../common/assets/img/wallet-bl.png" alt="">
                                        </div>
                                        <div class="content">
                                            <p>Wallet Balance</p>
                                            <h4><i class="fa fa-inr" aria-hidden="true"></i> <?php echo number_format($wallet_balance, 2); ?></h4>
                                        </div>
                                            
                                    </div>
                                </a>
                                <?php if($has_sufficient_balance): ?>
                                        <?php else: ?>
                                            <p class="low_balance_title">Your balance is low. Please recharge wallet.</p>
                                           
                                        <?php endif; ?> 
                            </div>
                           
                            
                        </div>
                    </div>
                    <div class="ManageUsers">
                        <h4 class="heading">Manage Users: </h4>
                        <?php
                        // Get the logged-in franchisee's email
                        $franchisee_email = $_SESSION['f_user_email'] ?? '';
                        
                         
                        // Fetch users referred by this franchisee
                        $referred_users_query = mysqli_query($connect, "
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
                                dc.d_payment_date
                            FROM customer_login cl
                            LEFT JOIN digi_card dc ON cl.user_email = dc.user_email
                            WHERE dc.f_user_email= '$franchisee_email'
                            ORDER BY cl.uploaded_date DESC
                        ");
                        
                        $total_referred_users = mysqli_num_rows($referred_users_query);
                        
                        // Fallback query to get all users if no referred users found (for debugging)
                        $all_users_query = mysqli_query($connect, "
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
                                dc.d_payment_date
                            FROM customer_login cl
                            LEFT JOIN digi_card dc ON cl.user_email = dc.user_email
                            WHERE dc.f_user_email= '$franchisee_email'
                            ORDER BY cl.uploaded_date DESC
                            LIMIT 10
                        ");
                        
                        $total_all_users = mysqli_num_rows($all_users_query);
                          
                        ?>
                        
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
                                    // Use the appropriate query based on whether we found referred users
                                    $display_query = ($total_referred_users > 0) ? $referred_users_query : $all_users_query;
                                    $display_count = ($total_referred_users > 0) ? $total_referred_users : $total_all_users;
                                    
                                    if($display_count > 0) {
                                        while($user = mysqli_fetch_array($display_query)) {
                                            // Calculate validity date (1 year from creation)
                                            $validity_date = date('d-m-Y', strtotime($user['uploaded_date'] . ' +1 year'));
                                            
                                            // Determine status based on payment status
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
                                            
                                            // Check if user has any cards
                                            $has_cards = !empty($user['card_id']);
                                    ?>
                                    <tr>
                                        <td class="text-left"><?php echo $user['id']; ?></td>
                                        <td class="text-left"><?php echo $user['card_id']; ?></td>
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
                                        <td colspan="7" class="text-center">
                                             
                                                <?php if($total_referred_users == 0): ?>
                                                    No referred users found. 
                                               
                                                <?php endif; ?>
                                            
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        
                      
                    </div>
                    
                    

                </div>
            </div>
        </div>
    </main>

    <!-- Create Account Modal -->
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
    /* Modal Styles */
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

    .FranchiseeDashboard-head .row-items-3 {
    justify-content: space-evenly;
}
    /* Responsive Design */
    @media (max-width: 480px) {
        .modal-content {
            margin: 10px;
            max-width: calc(100% - 20px);
        }
        
        .modal-header {
            padding: 15px 20px 10px;
        }
        
        .modal-body {
            padding: 0 20px 20px;
        }
        
        .form-control {
            padding: 12px 15px;
            font-size: 14px;
        }
        
        .btn-create-account {
            padding: 12px 15px;
            font-size: 14px;
        }
    }

    /* Loading state */
    .btn-create-account.loading {
        background: #ccc;
        cursor: not-allowed;
        transform: none;
    }

    .btn-create-account.loading:hover {
        background: #ccc;
        transform: none;
        box-shadow: none;
    }
    @media screen and (max-width: 768px) {
        .sb-topnav .navbar-brand img {
        max-height: 60px;
    }
    .FranchiseeDashboard-head .row-items-3 {
    justify-content: space-between;
    align-items: center;
}
.FranchiseeDashboard-head .card {
        width: 31rem !important;
    }
    .card-body {
    padding: 20px !important;
    padding-bottom: 100px !important;
}
.FranchiseeDashboard-head .card {
    
    padding: 10px 15px;
    font-weight: 600;
    margin: 30px auto;
}
 .FranchiseeDashboard-head .card {
    
    margin: 10px 0px !important;
    gap:3px;
}
.FranchiseeDashboard-head .card .img img {
        min-width: 53px;
        max-width: 50px;
    }
    .FranchiseeDashboard-head .card .content {
        
        padding-top: 0px;
    }
    .ReferredUsers .heading, .ManageUsers .heading {
    font-size: 24px;

}
.Copyright-left,
.Copyright-right{
    padding:0px;
}
    }
    
    </style>
   
    
    <script>
    
    // Modal Functions
    function openCreateAccountModal() {
        document.getElementById('createAccountModal').style.display = 'flex';
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
    }
    
    function closeCreateAccountModal() {
        document.getElementById('createAccountModal').style.display = 'none';
        document.body.style.overflow = 'auto'; // Restore scrolling
        // Reset form
        document.getElementById('createAccountForm').reset();
    }
    
    // Close modal when clicking outside
    document.getElementById('createAccountModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeCreateAccountModal();
        }
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeCreateAccountModal();
        }
    });
    
    // Form submission handling
    document.getElementById('createAccountForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Show confirmation dialog
        const confirmed = confirm('Please make sure your information is correct before proceeding. Click OK to finish creating the account.');
        
        if (!confirmed) {
            return; // User clicked "No" or cancelled
        }
        
        const submitBtn = document.querySelector('.btn-create-account');
        const originalText = submitBtn.textContent;
        
        // Show loading state
        submitBtn.textContent = 'CREATING...';
        submitBtn.classList.add('loading');
        
        // Get form data
        const formData = new FormData(this);
        formData.append('action', 'create_customer_account');
        formData.append('franchisee_email', '<?php echo $_SESSION['f_user_email'] ?? ''; ?>');
        
        // Submit via AJAX
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            } else {
                // If not JSON, get text to see what we got
                return response.text().then(text => {
                    console.error('Non-JSON response:', text);
                    throw new Error('Server returned non-JSON response: ' + text.substring(0, 200));
                });
            }
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                // Show success message with email status
                let message = 'Account created successfully!';
                if (data.email_sent !== undefined) {
                    message += data.email_sent ? '\n✅ Welcome email sent to customer' : '\n⚠️ Account created but email sending failed';
                }
                alert(message);
                closeCreateAccountModal();
                // Refresh the page to show new user
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
            // Reset button
            submitBtn.textContent = originalText;
            submitBtn.classList.remove('loading');
        });
    });
    
    function copyReferralLink() {
        var copyText = document.getElementById("referralLink");
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(copyText.value);
        
        // Show success message
        var button = event.target.closest('button');
        var originalText = button.innerHTML;
        button.innerHTML = '<i class="fa fa-check"></i> Copied!';
        button.classList.remove('btn-outline-secondary');
        button.classList.add('btn-success');
        
        setTimeout(function() {
            button.innerHTML = originalText;
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-secondary');
        }, 2000);
    }
    
    function copyReferralCode() {
        var copyText = document.getElementById("referralCode");
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(copyText.value);
        
        // Show success message
        var button = event.target.closest('button');
        var originalText = button.innerHTML;
        button.innerHTML = '<i class="fa fa-check"></i> Copied!';
        button.classList.remove('btn-outline-secondary');
        button.classList.add('btn-success');
        
        setTimeout(function() {
            button.innerHTML = originalText;
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-secondary');
        }, 2000);
    }
    </script>

<?php include '../footer.php'; ?>
