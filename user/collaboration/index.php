
<?php
// Start session and include database connection first
require_once(__DIR__ . '/../../app/config/database.php');
require_once(__DIR__ . '/../../app/helpers/access_control.php');

// Check page access - redirects to dashboard if unauthorized
require_page_access('/collaboration');

// Get user's email from session
$user_email = $_SESSION['user_email'] ?? '';

// Now include the header after all potential redirects
include '../includes/header.php';
?>
<?php
// Handle bank details submission
if(isset($_POST['submit_bank_details'])) {
    $account_holder_name = trim($_POST['account_holder_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $ifsc_code = trim($_POST['ifsc_code'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $upi_id = trim($_POST['upi_id'] ?? '');
    $upi_name = trim($_POST['upi_name'] ?? '');
    
    // Validate all fields are mandatory
    if(empty($account_holder_name) || empty($account_number) || empty($ifsc_code) || empty($bank_name) || empty($upi_id) || empty($upi_name)) {
        $bank_message = '<div class="alert alert-danger">All fields are mandatory. Please fill all the required fields.</div>';
    } else {
        // Check if bank details already exist using prepared statement
        $check_stmt = $connect->prepare("SELECT * FROM user_bank_details WHERE user_email = ?");
        $check_stmt->bind_param("s", $user_email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if($check_result->num_rows > 0) {
            // Update existing record using prepared statement
            $update_stmt = $connect->prepare("UPDATE user_bank_details SET 
                account_holder_name = ?, account_number = ?, ifsc_code = ?, bank_name = ?, 
                upi_id = ?, upi_name = ? WHERE user_email = ?");
            $update_stmt->bind_param("sssssss", $account_holder_name, $account_number, $ifsc_code, $bank_name, $upi_id, $upi_name, $user_email);
            
            if($update_stmt->execute()) {
                $bank_message = '<div class="alert alert-success">Bank details updated successfully!</div>';
            } else {
                $bank_message = '<div class="alert alert-danger">Failed to update bank details. Please try again.</div>';
            }
            $update_stmt->close();
        } else {
            // Insert new record using prepared statement
            $insert_stmt = $connect->prepare("INSERT INTO user_bank_details 
                (user_email, account_holder_name, account_number, ifsc_code, bank_name, upi_id, upi_name) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("sssssss", $user_email, $account_holder_name, $account_number, $ifsc_code, $bank_name, $upi_id, $upi_name);
            
            if($insert_stmt->execute()) {
                $bank_message = '<div class="alert alert-success">Bank details saved successfully!</div>';
            } else {
                $bank_message = '<div class="alert alert-danger">Failed to save bank details. Please try again.</div>';
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

// Get existing bank details using prepared statement
$bank_stmt = $connect->prepare("SELECT * FROM user_bank_details WHERE user_email = ?");
$bank_stmt->bind_param("s", $user_email);
$bank_stmt->execute();
$bank_result = $bank_stmt->get_result();
$bank_data = $bank_result->fetch_assoc();
$bank_stmt->close();

// Get user's referral code from session, or generate if not available
$user_referral_code = $_SESSION['user_referral_code'] ?? '';

// Get user name from user_details
$user_email_lower = strtolower(trim($user_email));
$user_stmt = $connect->prepare("SELECT name FROM user_details WHERE LOWER(TRIM(email)) = ? LIMIT 1");
$user_stmt->bind_param("s", $user_email);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_name = $user_data['name'] ?? '';
$user_stmt->close();

// Generate referral code if it doesn't exist in session
if(empty($user_referral_code)) {
    $user_referral_code = strtoupper(substr(md5($user_email . time()), 0, 8));
    // Store in session for future use
    $_SESSION['user_referral_code'] = $user_referral_code;
}



// Fixed calculation - only include referrals where user payment status is 'Success'
$earnings_stmt = $connect->prepare("SELECT 
    SUM(re.amount) as total_referral_amount,
    (SELECT COALESCE(SUM(rph.amount), 0) FROM referral_payment_history rph 
     WHERE rph.referral_id IN (SELECT id FROM referral_earnings WHERE CONVERT(referrer_email USING utf8mb4) = CONVERT(? USING utf8mb4))) as total_paid_amount
    FROM referral_earnings re 
    LEFT JOIN digi_card dc ON CONVERT(re.referred_email USING utf8mb4) = CONVERT(dc.user_email USING utf8mb4)
    WHERE CONVERT(re.referrer_email USING utf8mb4) = CONVERT(? USING utf8mb4) 
    AND dc.d_payment_status = 'Success'");

$earnings_stmt->bind_param("ss", $user_email, $user_email);
$earnings_stmt->execute();
$earnings_result = $earnings_stmt->get_result();
$earnings_data = $earnings_result->fetch_assoc();
$earnings_stmt->close();

$total_referral_amount = $earnings_data['total_referral_amount'] ?? 0;
$total_paid_amount = $earnings_data['total_paid_amount'] ?? 0;
$pending_amount = $total_referral_amount - $total_paid_amount;



// Count based on the same logic as the referred users table display
// This should match the actual number of rows shown in "Referred Users" table
// Count distinct users from user_details where referred_by matches OR from referral_earnings
// Filter by is_collaboration status to separate MW and Franchise referrals

// Count for "Referred MW" (regular referrals - is_collaboration IS NULL OR 'NO')
$regular_referrals_stmt = $connect->prepare("SELECT COUNT(DISTINCT COALESCE(ud_referred.email, re.referred_email)) as total_count
    FROM user_details ud_referred
    LEFT JOIN referral_earnings re 
        ON CONVERT(re.referred_email USING utf8mb4) = CONVERT(ud_referred.email USING utf8mb4)
        AND CONVERT(re.referrer_email USING utf8mb4) = CONVERT(? USING utf8mb4)
    WHERE ((CONVERT(ud_referred.referred_by USING utf8mb4) = CONVERT(? USING utf8mb4)
           AND ud_referred.referred_by != ''
           AND ud_referred.referred_by IS NOT NULL)
       OR (re.id IS NOT NULL AND CONVERT(re.referrer_email USING utf8mb4) = CONVERT(? USING utf8mb4)))
    AND (COALESCE(re.is_collaboration, 'NO') = 'NO' OR re.is_collaboration IS NULL)
    AND (ud_referred.role = 'CUSTOMER' OR ud_referred.role IS NULL OR (ud_referred.role = 'FRANCHISEE' AND COALESCE(re.is_collaboration, 'NO') = 'NO'))");

$regular_referrals_stmt->bind_param("sss", $user_email, $user_email, $user_email);
$regular_referrals_stmt->execute();
$regular_referrals_result = $regular_referrals_stmt->get_result();
$regular_referrals_data = $regular_referrals_result->fetch_assoc();
$regular_referrals = $regular_referrals_data['total_count'] ?? 0;
$regular_referrals_stmt->close();

// Count for "Referred Franchise" (collaboration referrals - is_collaboration = 'YES')
$collaboration_referrals_stmt = $connect->prepare("SELECT COUNT(DISTINCT COALESCE(ud_referred.email, re.referred_email)) as total_count
    FROM user_details ud_referred
    LEFT JOIN referral_earnings re 
        ON CONVERT(re.referred_email USING utf8mb4) = CONVERT(ud_referred.email USING utf8mb4)
        AND CONVERT(re.referrer_email USING utf8mb4) = CONVERT(? USING utf8mb4)
    WHERE ((CONVERT(ud_referred.referred_by USING utf8mb4) = CONVERT(? USING utf8mb4)
           AND ud_referred.referred_by != ''
           AND ud_referred.referred_by IS NOT NULL)
       OR (re.id IS NOT NULL AND CONVERT(re.referrer_email USING utf8mb4) = CONVERT(? USING utf8mb4)))
    AND COALESCE(re.is_collaboration, 'NO') = 'YES'
    AND ud_referred.role = 'FRANCHISEE'");

$collaboration_referrals_stmt->bind_param("sss", $user_email, $user_email, $user_email);
$collaboration_referrals_stmt->execute();
$collaboration_referrals_result = $collaboration_referrals_stmt->get_result();
$collaboration_referrals_data = $collaboration_referrals_result->fetch_assoc();
$collaboration_referrals = $collaboration_referrals_data['total_count'] ?? 0;
$collaboration_referrals_stmt->close();



// Update the referred users query to include both customer and franchisee data using prepared statement
// Note: This shows ALL referrals in the table, but calculations above only include paid users
// Get User ID from user_details table instead of legacy tables
// Also include records from user_details where referred_by matches (even if no referral_earnings entry)
$referred_users_stmt = $connect->prepare("SELECT DISTINCT
    re.id as referral_id,
    COALESCE(re.referred_email, ud_referred.email) as referred_email,
    COALESCE(re.referral_date, ud_referred.created_at) as referral_date,
    COALESCE(re.amount, 0) as amount,
    COALESCE(re.is_collaboration, 'NO') as is_collaboration,
    ud_referred.id as user_id,
    cl.id as customer_id,
    fl.id as franchisee_id,
    COALESCE(ud_referred.name,
        CASE 
            WHEN re.is_collaboration = 'YES' THEN fl.f_user_name
            ELSE cl.user_name 
        END
    ) as user_name,
    COALESCE(ud_referred.phone,
        CASE 
            WHEN re.is_collaboration = 'YES' THEN fl.f_user_contact
            ELSE cl.user_contact 
        END
    ) as user_contact,
    dc.id as card_id,
    dc.uploaded_date as card_uploaded_date,
    dc.validity_date as card_validity_date,
    dc.complimentary_enabled,
    dc.d_payment_status,
    dc.d_payment_date
    FROM user_details ud_referred
    LEFT JOIN referral_earnings re 
        ON CONVERT(re.referred_email USING utf8mb4) = CONVERT(ud_referred.email USING utf8mb4)
        AND CONVERT(re.referrer_email USING utf8mb4) = CONVERT(? USING utf8mb4)
    LEFT JOIN customer_login cl ON CONVERT(ud_referred.email USING utf8mb4) = CONVERT(cl.user_email USING utf8mb4) AND (COALESCE(re.is_collaboration, 'NO') = 'NO')
    LEFT JOIN franchisee_login fl ON CONVERT(ud_referred.email USING utf8mb4) = CONVERT(fl.f_user_email USING utf8mb4) AND COALESCE(re.is_collaboration, 'NO') = 'YES'
    LEFT JOIN digi_card dc ON CONVERT(dc.user_email USING utf8mb4) = CONVERT(ud_referred.email USING utf8mb4)
    WHERE (CONVERT(ud_referred.referred_by USING utf8mb4) = CONVERT(? USING utf8mb4)
           AND ud_referred.referred_by != ''
           AND ud_referred.referred_by IS NOT NULL)
       OR (re.id IS NOT NULL AND CONVERT(re.referrer_email USING utf8mb4) = CONVERT(? USING utf8mb4))
    ORDER BY COALESCE(re.id, 0) DESC, ud_referred.created_at DESC");

$referred_users_stmt->bind_param("sss", $user_email, $user_email, $user_email);
$referred_users_stmt->execute();
$referred_users_result = $referred_users_stmt->get_result();
?>
             <main class="Dashboard">
                <div class="container-fluid customer_content_area">
                    <div class="main-top">
                        <!-- <h1 class="heading">Referral Details</h1> -->
                        <span class="heading">Referral Details</span> 
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="#">Mini Website </a></li>
                                <li class="breadcrumb-item active" aria-current="page">Referral Details</li>
                            </ol>
                        </nav>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="ReferralDetails-head">
                                <div class="row d_flex">
                                    <div class="card_area">
                                        <div class="card">
                                            <div class="img"><img src="../../assets/images/PendingAmt.png" alt=""></div>
                                            <div class="content">
                                                <p>Pending Amt</p>
                                                <h4>
                                                    <i class="fa fa-inr" aria-hidden="true"></i>
                                                    <?php echo number_format($pending_amount, 0); ?>/-
                                                </h4>
                                                 
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card_area">
                                        <div class="card">
                                            <div class="img"><img src="../../assets/images/TotalEarning.png" alt=""></div>
                                            <div class="content">
                                                <p>Total Earning</p>
                                                <h4>
                                                    <i class="fa fa-inr" aria-hidden="true"></i>
                                                    <?php echo number_format($total_referral_amount, 0); ?>/-
                                                </h4>
                                                
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card_area">
                                        <div class="card">
                                            <div class="img"><img src="../../assets/images/ReferredUsers.png" alt=""></div>
                                            <div class="content without-icon">
                                                <p>Referred MW</p>
                                                
                                                <h4>
                                                <i class="fa fa-inr rupee_icon" aria-hidden="true" style="visibility:hidden;"></i>
                                                    <?php echo $regular_referrals; ?>
                                                </h4>
                                               
                                            </div>
                                        </div>
                                    </div>
                                     <div class="card_area card_area4">
                                        <div class="card">
                                            <div class="img"><img src="../../assets/images/ReferredFranchisee.png" alt=""></div>
                                            <div class="content width200">
                                                <p>Referred Franchise</p>
                                                <h4><?php echo $collaboration_referrals; ?></h4>
                                                
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="Bank-Details">
                                <div class="card">
                                    <h4 class="heading">Bank Account Details:</h4>
                                    <p>Please submit the bank details where you want us to transfer your referral earnings.</p>
                                    
                                    <?php if(isset($bank_message)) echo $bank_message; ?>
                                    
                                    <?php 
                                    // Check if bank details exist
                                    $bank_details_exist = !empty($bank_data['account_holder_name']) && !empty($bank_data['account_number']) && !empty($bank_data['ifsc_code']) && !empty($bank_data['bank_name']) && !empty($bank_data['upi_id']) && !empty($bank_data['upi_name']);
                                    ?>
                                    
                                    <form method="POST" id="bankDetailsForm" onsubmit="return validateBankForm()">
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <div class="form-group">
                                                    <label for="">Bank Name <span class="text-danger">*</span></label>
                                                    <input type="text" name="bank_name" placeholder="Enter Bank Name" class="form-control" value="<?php echo $bank_data['bank_name'] ?? ''; ?>" required <?php echo $bank_details_exist ? 'readonly' : ''; ?>>
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="form-group">
                                                    <label for="">Account Holder Name <span class="text-danger">*</span></label>
                                                    <input type="text" name="account_holder_name" placeholder="Account Holder Name" class="form-control" value="<?php echo $bank_data['account_holder_name'] ?? ''; ?>" required <?php echo $bank_details_exist ? 'readonly' : ''; ?>>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <div class="form-group">
                                                    <label for="">Bank Account Number <span class="text-danger">*</span></label>
                                                    <input type="text" name="account_number" placeholder="Enter Your Bank Account Number" class="form-control" value="<?php echo $bank_data['account_number'] ?? ''; ?>" required <?php echo $bank_details_exist ? 'readonly' : ''; ?>>
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="form-group">
                                                    <label for="">Bank IFSC Code <span class="text-danger">*</span></label>
                                                    <input type="text" name="ifsc_code" placeholder="Enter IFSC Code" class="form-control" value="<?php echo $bank_data['ifsc_code'] ?? ''; ?>" required <?php echo $bank_details_exist ? 'readonly' : ''; ?>>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <div class="form-group">
                                                    <label for="">UPI ID <span class="text-danger">*</span></label>
                                                    <input type="text" name="upi_id" placeholder="Enter UPI ID (e.g., user@paytm)" class="form-control" value="<?php echo $bank_data['upi_id'] ?? ''; ?>" required <?php echo $bank_details_exist ? 'readonly' : ''; ?>>
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="form-group">
                                                    <label for="">UPI Name <span class="text-danger">*</span></label>
                                                    <input type="text" name="upi_name" placeholder="Enter UPI Name" class="form-control" value="<?php echo $bank_data['upi_name'] ?? ''; ?>" required <?php echo $bank_details_exist ? 'readonly' : ''; ?>>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if($bank_details_exist): ?>
                                            <div class="verfication_message">
                                                <i class="fa fa-info-circle"></i> <span>Bank details have been saved successfully. Contact support if you need to make changes.</span>
                                            </div>
                                            <button type="button" class="btn btn-secondary" disabled>BANK DETAILS SAVED</button>
                                        <?php else: ?>
                                            <button type="submit" name="submit_bank_details" class="btn btn-primary">SUBMIT</button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                            <div class="ReferredUsers">
                                <h4 class="heading">Referred Users:</h4>
                                <style>
                                    /* Make headers single-line and enable horizontal scroll */
                                    div.ref-users-scroll { overflow-x: auto; }
                                    table[id^="ReferredUsers"] { min-width: 1200px; }
                                    table[id^="ReferredUsers"] th { white-space: nowrap; }
                                    table[id^="ReferredUsers"] td { white-space: nowrap; }
                                </style>
                                <div class="ref-users-scroll">
                                <table id="ReferredUsers scrollable" class="display table ReferredUsers">
                                    <thead class="bg-secondary">
                                        <tr>
                                            <th class="text-left">User ID</th>
                                            <th class="text-left">MW ID</th>
                                            <th class="text-left">User Email</th>
                                            <th class="text-left">User Name</th>
                                            <th class="text-left">User Number</th>
                                            <th class="text-left">Joined On</th>
                                            <th class="text-left">Referral Source</th>
                                            <th class="text-left">Date Created</th>
                                            <th class="text-left">Validity Date</th>
                                            <th class="text-left">MW Status</th>
                                            <th class="text-left">User Payment Status</th>
                                          
                                            <th class="text-left">Referral Amt.</th>
                                          
                                            <th class="text-left">MW Payment Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if($referred_users_result->num_rows > 0) {
                                            while($row = $referred_users_result->fetch_assoc()) {
                                                echo '<tr>';
                                                // User ID: from user_details table (new unified table)
                                                $user_id_display = !empty($row['user_id']) ? (int)$row['user_id'] : 'N/A';
                                                echo '<td class="text-left">' . htmlspecialchars((string)$user_id_display) . '</td>';
                                                // MW ID
                                                echo '<td class="text-left">' . htmlspecialchars($row['card_id'] ?? '-') . '</td>';
                                                // User Email
                                                echo '<td class="text-left">' . htmlspecialchars($row['referred_email'] ?? '-') . '</td>';
                                                // User Name
                                                echo '<td class="text-left">' . htmlspecialchars($row['user_name'] ?? 'Unknown') . '</td>';
                                                // User Number
                                                echo '<td class="text-left">' . htmlspecialchars($row['user_contact'] ?? 'N/A') . '</td>';
                                                // Joined On
                                                $joined_on = !empty($row['referral_date']) ? date('d-m-Y', strtotime($row['referral_date'])) : '-';
                                                echo '<td class="text-left">' . $joined_on . '</td>';
                                                // Referral Source
                                                $ref_source = (($row['is_collaboration'] ?? 'NO') === 'YES') ? 'Franchisee' : 'MiniWebsite';
                                                echo '<td class="text-left">' . $ref_source . '</td>';
                                                // Date Created (card uploaded date)
                                                $date_created = !empty($row['card_uploaded_date']) ? date('d-m-Y', strtotime($row['card_uploaded_date'])) : '-';
                                                echo '<td class="text-left">' . $date_created . '</td>';
                                                // Validity Date
                                                $validity_display = '-';
                                                if (!empty($row['card_validity_date'])) {
                                                    $validity_display = date('d-m-Y', strtotime($row['card_validity_date']));
                                                } elseif (!empty($row['card_uploaded_date'])) {
                                                    if (($row['complimentary_enabled'] ?? '') === 'Yes') {
                                                        $validity_display = date('d-m-Y', strtotime($row['card_uploaded_date'] . ' +1 year'));
                                                    } else {
                                                        if (($row['d_payment_status'] ?? '') === 'Success' && !empty($row['d_payment_date'])) {
                                                            $validity_display = date('d-m-Y', strtotime($row['d_payment_date'] . ' +1 year'));
                                                        } else {
                                                            $validity_display = date('d-m-Y', strtotime($row['card_uploaded_date'] . ' +7 days'));
                                                        }
                                                    }
                                                }
                                                echo '<td class="text-left">' . $validity_display . '</td>';
                                                // MW Status
                                                $mw_status = '7 Day Trial';
                                                if (($row['complimentary_enabled'] ?? '') === 'Yes') {
                                                    $mw_status = 'Active';
                                                } else if (($row['d_payment_status'] ?? '') === 'Success') {
                                                    $mw_status = 'Active';
                                                }
                                                echo '<td class="text-left"><span class="' . ($mw_status === 'Active' ? 'bg-success' : 'bg-pending') . '">' . $mw_status . '</span></td>';
                                                // User Payment Status with date
                                                echo '<td class="text-left">';
                                                if(($row['d_payment_status'] ?? '') === 'Success') {
                                                    $payment_date = !empty($row['d_payment_date']) ? date('d-m-Y', strtotime($row['d_payment_date'])) : date('d-m-Y');
                                                    echo '<span class="bg-success">Paid on ' . $payment_date . '</span>';
                                                } else {
                                                    echo '<span class="bg-unpaid">Unpaid</span>';
                                                }
                                                echo '</td>';
                                                // Referral Amt.
                                                echo '<td class="text-left">₹' . number_format((float)($row['amount'] ?? 0), 0) . '</td>';
                                                // MW Payment Status (from digi_card)
                                                echo '<td class="text-left">' . ((($row['d_payment_status'] ?? '') === 'Success') ? '<span class="bg-success">Paid</span>' : '<span class="bg-unpaid">Pending</span>') . '</td>';
                                                echo '</tr>';
                                            }
                                        } else {
                                            echo '<tr><td colspan="13" class="text-left">No referrals found</td></tr>';
                                        }
                                        ?>
                                    </tbody>
                                </table>
                                </div>
                            </div>
                             
                        
                    </div>
                    
                </div>

                
            </main>

           
<script>
function validateBankForm() {
    var bankName = document.getElementsByName('bank_name')[0].value.trim();
    var accountHolderName = document.getElementsByName('account_holder_name')[0].value.trim();
    var accountNumber = document.getElementsByName('account_number')[0].value.trim();
    var ifscCode = document.getElementsByName('ifsc_code')[0].value.trim();
    var upiId = document.getElementsByName('upi_id')[0].value.trim();
    var upiName = document.getElementsByName('upi_name')[0].value.trim();
    
    if (bankName === '' || accountHolderName === '' || accountNumber === '' || ifscCode === '' || upiId === '' || upiName === '') {
        alert('All fields are mandatory. Please fill all the required fields.');
        return false;
    }
    
    // Additional validation for account number (should be numeric)
    if (!/^\d+$/.test(accountNumber)) {
        alert('Account number should contain only numbers.');
        return false;
    }
    
    // Additional validation for IFSC code (should be 11 characters)
    if (ifscCode.length !== 11) {
        alert('IFSC code should be exactly 11 characters.');
        return false;
    }
    
    // Additional validation for UPI ID (should contain @)
    if (!upiId.includes('@')) {
        alert('Please enter a valid UPI ID (e.g., user@paytm).');
        return false;
    }
    
    return true;
}
</script>
<style>
    @media screen and (max-width: 768px) {
    .ReferralDetails-head .card, .FranchiseeDashboard-head .card {
        width: 31rem !important;
    }
    .card-body {
    padding: 20px !important;
    padding-bottom: 100px !important;
}
.ReferralDetails-head .card {
    
    padding: 10px 15px;
    font-weight: 600;
    margin: 30px auto;
}
.ReferralDetails-head .card, .FranchiseeDashboard-head .card {
    
    margin: 10px 0px !important;
    
}
.ReferralDetails-head .card  .width200{
        width: 200px;
    }
    .Bank-Details{
        margin-top:30px;
    }
    .Bank-Details .card .heading {
        font-size: 22px;
        line-height: 67px;
        position: relative;
    }
    .heading2 {
    font-size: 22px !important;
}
    .Bank-Details .card p {
        font-size: 16px;
        color: #00000075;
        line-height: 21px;
    }
    .Bank-Details .card label {
        font-size: 20px !important;
        
    }
    #bankDetailsForm input {
    font-size: 16px;
}
.verfication_message{
    font-size:14px;
}
.verfication_message span{
    font-size:16px;
    line-height:2px;
}
#bankDetailsForm .btn {
    padding: 7px 20px;
    font-size: 20px !important;
}
.Copyright-left,
.Copyright-right{
    padding:0px;
}
}
 .card_area4 .card .img img{
    width: 140% !important;
 }
 
</style>
<?php 
// Close the prepared statement
$referred_users_stmt->close();
include '../includes/footer.php'; 
?>


































