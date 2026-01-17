
<?php include '../header.php'; ?>
<?php
// Get user's email from session
$user_email = $_SESSION['user_email'] ?? '';

// Handle bank details form submission
if(isset($_POST['submit_bank_details'])) {
    $bank_name = trim(mysqli_real_escape_string($connect, $_POST['bank_name']));
    $account_holder = trim(mysqli_real_escape_string($connect, $_POST['account_holder']));
    $account_number = trim(mysqli_real_escape_string($connect, $_POST['account_number']));
    $ifsc_code = trim(mysqli_real_escape_string($connect, $_POST['ifsc_code']));
    $upi_id = trim(mysqli_real_escape_string($connect, $_POST['upi_id']));
    $upi_name = trim(mysqli_real_escape_string($connect, $_POST['upi_name']));
    
    // Validate all fields are mandatory
    if(empty($bank_name) || empty($account_holder) || empty($account_number) || empty($ifsc_code) || empty($upi_id) || empty($upi_name)) {
        $bank_message = '<div class="alert alert-danger">All fields are mandatory. Please fill all the required fields.</div>';
    } else {
        // Check if bank details already exist
        $check_bank = mysqli_query($connect, "SELECT id FROM user_bank_details WHERE user_email='$user_email'");
        
        if(mysqli_num_rows($check_bank) > 0) {
            // Update existing bank details
            $update_bank = mysqli_query($connect, "UPDATE user_bank_details SET 
                bank_name='$bank_name',
                account_holder_name='$account_holder',
                account_number='$account_number',
                ifsc_code='$ifsc_code',
                upi_id='$upi_id',
                upi_name='$upi_name',
                updated_at=NOW()
                WHERE user_email='$user_email'");
            
            if($update_bank) {
                $bank_message = '<div class="alert alert-success">Bank details updated successfully!</div>';
            }
        } else {
            // Insert new bank details
            $insert_bank = mysqli_query($connect, "INSERT INTO user_bank_details 
                (user_email, bank_name, account_holder_name, account_number, ifsc_code, upi_id, upi_name, created_at) 
                VALUES ('$user_email', '$bank_name', '$account_holder', '$account_number', '$ifsc_code', '$upi_id', '$upi_name', NOW())");
            
            if($insert_bank) {
                $bank_message = '<div class="alert alert-success">Bank details saved successfully!</div>';
            }
        }
    }
}

// Get existing bank details
$bank_query = mysqli_query($connect, "SELECT * FROM user_bank_details WHERE user_email='$user_email'");
$bank_data = mysqli_fetch_array($bank_query);

// Get user's referral code from session, or generate if not available
$user_referral_code = $_SESSION['user_referral_code'] ?? '';

// Get user name from user_details
$user_email_lower = strtolower(trim($user_email));
$user_query = mysqli_query($connect, "SELECT name FROM user_details WHERE LOWER(TRIM(email))='$user_email_lower' LIMIT 1");
$user_data = mysqli_fetch_array($user_query);
$user_name = $user_data['name'] ?? '';

// Generate referral code if it doesn't exist in session
if(empty($user_referral_code)) {
    $user_referral_code = strtoupper(substr(md5($user_email . time()), 0, 8));
    // Store in session for future use
    $_SESSION['user_referral_code'] = $user_referral_code;
}

// Get referral earnings summary with actual deal amounts
// Also count from user_details where referred_by matches
$earnings_query = mysqli_query($connect, "SELECT 
    SUM(CASE WHEN re.status = 'Pending' THEN re.amount ELSE 0 END) as pending_amount,
    SUM(CASE WHEN re.status = 'Paid' THEN re.amount ELSE 0 END) as total_earning,
    COUNT(DISTINCT re.id) as referral_earnings_count
    FROM referral_earnings re 
    WHERE CONVERT(re.referrer_email USING utf8mb4) = CONVERT('$user_email' USING utf8mb4)");

$earnings_data = mysqli_fetch_array($earnings_query);
$pending_amount = $earnings_data['pending_amount'] ?? 0;
$total_earning = $earnings_data['total_earning'] ?? 0;
$referral_earnings_count = $earnings_data['referral_earnings_count'] ?? 0;

// Also count from user_details where referred_by matches
$referred_by_query = mysqli_query($connect, "SELECT COUNT(*) as referred_by_count
    FROM user_details ud
    WHERE CONVERT(ud.referred_by USING utf8mb4) = CONVERT('$user_email' USING utf8mb4)
    AND ud.referred_by != ''
    AND ud.referred_by IS NOT NULL");

$referred_by_data = mysqli_fetch_array($referred_by_query);
$referred_by_count = $referred_by_data['referred_by_count'] ?? 0;

// Use the higher count to ensure all referrals are included
$total_referrals = max($referral_earnings_count, $referred_by_count);


// Get referred users details with deal information (using unified user_details)
// Also include records from user_details where referred_by matches (even if no referral_earnings entry)
$referred_users_query = mysqli_query($connect, "SELECT DISTINCT
    re.id as referral_id,
    COALESCE(re.referred_email, ud_referred.email) as referred_email,
    COALESCE(re.referral_date, ud_referred.created_at) as referral_date,
    COALESCE(re.amount, 0) as amount,
    COALESCE(re.status, 'Pending') as status,
    COALESCE(re.payment_date, NULL) as payment_date,
    ud_referred.id AS user_id,
    ud_referred.email AS user_email,
    ud_referred.phone AS user_contact,
    ud_referred.name AS user_name,
    dc.id as card_id,
    dc.uploaded_date as card_uploaded_date,
    dc.validity_date as card_validity_date,
    dc.complimentary_enabled,
    dc.d_payment_status,
    dc.d_payment_date
    FROM user_details ud_referred
    LEFT JOIN referral_earnings re 
        ON CONVERT(re.referred_email USING utf8mb4) = CONVERT(ud_referred.email USING utf8mb4)
        AND CONVERT(re.referrer_email USING utf8mb4) = CONVERT('$user_email' USING utf8mb4)
    LEFT JOIN digi_card dc 
        ON CONVERT(dc.user_email USING utf8mb4) = CONVERT(ud_referred.email USING utf8mb4)
    WHERE (CONVERT(ud_referred.referred_by USING utf8mb4) = CONVERT('$user_email' USING utf8mb4)
           AND ud_referred.referred_by != ''
           AND ud_referred.referred_by IS NOT NULL)
       OR (re.id IS NOT NULL AND CONVERT(re.referrer_email USING utf8mb4) = CONVERT('$user_email' USING utf8mb4))
    ORDER BY COALESCE(re.id, 0) DESC, ud_referred.created_at DESC");

// Define the sharing message
$sharing_message = "🚀 Create Your Own MiniWebsite (Digital Business Card) Today!

Say goodbye to paper visiting cards ✋
Create your own Mini Website in just a few minutes – simple, smart & eco-friendly 🌱

👉 Click here to get started: https://miniwebsite.in/panel/login/create-account.php?ref=" . $user_referral_code . "
💸 Use Referral Code: " . $user_referral_code . " to get additional discount!

✅ Professional & stylish digital card
✅ Easy to share on WhatsApp & social media
✅ Grow your business online

Don't miss out – create yours now!";

$image_url = "YOUR_IMAGE_LINK_HERE"; // Add your image link here
?>
<meta property="og:title" content="Create Your Own MiniWebsite (Digital Business Card) Today!" />
<meta property="og:description" content="Say goodbye to paper visiting cards ✋ Create your own Mini Website in just a few minutes – simple, smart & eco-friendly 🌱" />
<meta property="og:image" content="<?php echo $image_url; ?>" />
<meta property="og:url" content="https://miniwebsite.in/panel/login/create-account.php?ref=<?php echo $user_referral_code; ?>" />
<meta property="og:type" content="website" />
            <main class="Dashboard">
                <div class="container-fluid px-4">
                    <div class="main-top">
                        <!-- <h1 class="heading">Referral Details</h1> -->
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
                                <div class="row">
                                    <div class="col-sm-4">
                                        <div class="card">
                                            <div class="img"><img src="../assets/img/PendingAmt.png" alt=""></div>
                                            <div class="content">
                                                <p>Pending Amount</p>
                                                <h4>
                                                    <i class="fa fa-inr" aria-hidden="true"></i>
                                                    <?php echo number_format($pending_amount, 0); ?>/-
                                                </h4>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-4">
                                        <div class="card">
                                            <div class="img"><img src="../assets/img/TotalEarning.png" alt=""></div>
                                            <div class="content">
                                                <p>Total Refferal earning</p>
                                                <h4>
                                                    <i class="fa fa-inr" aria-hidden="true"></i>
                                                    <?php echo number_format($total_earning, 0); ?>/-
                                                </h4>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-4">
                                        <div class="card">
                                            <div class="img"><img src="../assets/img/ReferredUsers.png" alt=""></div>
                                            <div class="content">
                                                <p>Referred MW</p>
                                                <h4><?php echo $total_referrals; ?></h4>
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
                                    $bank_details_exist = !empty($bank_data['bank_name']) && !empty($bank_data['account_holder_name']) && !empty($bank_data['account_number']) && !empty($bank_data['ifsc_code']) && !empty($bank_data['upi_id']) && !empty($bank_data['upi_name']);
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
                                                    <input type="text" name="account_holder" placeholder="Account Holder Name" class="form-control" value="<?php echo $bank_data['account_holder_name'] ?? ''; ?>" required <?php echo $bank_details_exist ? 'readonly' : ''; ?>>
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
                                            <div class="">
                                                <i class="fa fa-info-circle"></i> Bank details have been saved successfully. Contact support if you need to make changes.
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
                                <table id="ReferredUsers" class="display table">
                                    <thead>
                                        <tr>
                                            <th class="text-center">User ID</th>
                                            <th class="text-center">MW ID</th>
                                            <th class="text-center">User Email</th>
                                            <th class="text-center">User Name</th>
                                            <th class="text-center">User Number</th>
                                            <th class="text-center">Joined On</th>
                                            <th class="text-center">Referral Source</th> 
                                            <th class="text-center">Date Created</th>
                                            <th class="text-center">Validity Date</th>
                                            <th class="text-center">MW Status</th>
                                            <th class="text-center">User Payment Status</th>
                                            <th class="text-center">Referral Amt.</th>
                                            <th class="text-center">MW Payment Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if(mysqli_num_rows($referred_users_query) > 0) {
                                            while($row = mysqli_fetch_array($referred_users_query)) {
                                                echo '<tr>';
                                                // User ID from unified table, N/A if not a registered customer yet
                                                echo '<td class="text-center">' . (!empty($row['user_id']) ? (int)$row['user_id'] : 'N/A') . '</td>';
                                                
                                                // MW ID with view icon (card_id from digi_card)
                                                echo '<td class="text-center">';
                                                if (!empty($row['card_id'])) {
                                                    $cardId = (int)$row['card_id'];
                                                    echo '<a href="https://' . $_SERVER['HTTP_HOST'] . '/n.php?n=' . $cardId . '" target="_blank" style="text-decoration:none;color:inherit;">';
                                                    echo '<img src="../assets/img/eye.png" class="img-fluid" width="28px" alt=""> ' . $cardId;
                                                    echo '</a>';
                                                } else {
                                                    echo 'N/A';
                                                }
                                                echo '</td>';
                                                
                                                // User Email / Name / Number
                                                echo '<td class="text-center">' . htmlspecialchars($row['referred_email'] ?? $row['user_email'] ?? 'N/A') . '</td>';
                                                echo '<td class="text-center">' . htmlspecialchars($row['user_name'] ?? 'Unknown') . '</td>';
                                                echo '<td class="text-center">' . htmlspecialchars($row['user_contact'] ?? 'N/A') . '</td>';
                                                
                                                // Joined On (referral date)
                                                $joined_on = !empty($row['referral_date']) ? date('d-m-Y', strtotime($row['referral_date'])) : '-';
                                                echo '<td class="text-center">' . $joined_on . '</td>';
                                                
                                                // Referral Source (always "Referral" for customer referrals)
                                                echo '<td class="text-center">Referral</td>';
                                                
                                                // Date Created (card uploaded date)
                                                $date_created = !empty($row['card_uploaded_date']) ? date('d-m-Y', strtotime($row['card_uploaded_date'])) : '-';
                                                echo '<td class="text-center">' . $date_created . '</td>';
                                                
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
                                                echo '<td class="text-center">' . $validity_display . '</td>';
                                                
                                                // MW Status
                                                $mw_status = '7 Day Trial';
                                                if (($row['complimentary_enabled'] ?? '') === 'Yes') {
                                                    $mw_status = 'Active';
                                                } else if (($row['d_payment_status'] ?? '') === 'Success') {
                                                    $mw_status = 'Active';
                                                }
                                                echo '<td class="text-center"><span class="' . ($mw_status === 'Active' ? 'bg-success' : 'bg-pending') . '">' . $mw_status . '</span></td>';
                                                
                                                // User Payment Status
                                                echo '<td class="text-center">';
                                                if($row['d_payment_status'] == 'Success') {
                                                    $payment_date = $row['d_payment_date'] ? date('d-m-Y', strtotime($row['d_payment_date'])) : date('d-m-Y');
                                                    echo '<span class="bg-success">Paid on ' . $payment_date . '</span>';
                                                } else {
                                                    echo '<span class="bg-unpaid">Unpaid</span>';
                                                }
                                                echo '</td>';
                                                
                                                // Referral Amount
                                                echo '<td class="text-center">₹ ' . number_format($row['amount'], 0) . '</td>';
                                                
                                                // MW Payment Status (referral earnings status)
                                                echo '<td class="text-center">';
                                                if($row['status'] == 'Paid') {
                                                    $mw_payment_date = $row['payment_date'] ? date('d-m-Y', strtotime($row['payment_date'])) : 'N/A';
                                                    echo '<span class="bg-success">Paid on ' . $mw_payment_date . '</span>';
                                                } elseif($row['status'] == 'Partial' && $row['d_payment_status'] == 'Success') {
                                                    echo '<span class="bg-pending">Partial Payment</span>';
                                                } elseif($row['status'] == 'Pending' && $row['d_payment_status'] == 'Success') {
                                                    echo '<span class="bg-pending">Pending</span>';
                                                } else {
                                                    echo '<span class="not-eligible">Not Eligible</span>';
                                                }
                                                echo '</td>';
                                                echo '</tr>';
                                            }
                                        } else {
                                            echo '<tr><td colspan="6" class="text-center">No referrals found</td></tr>';
                                        }
                                        ?>
                                    </tbody>
                                </table>
                                </div>
                                

                              
     
   </div>

<script>
function copyToClipboard(elementId) {
    var copyText = document.getElementById(elementId);
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    document.execCommand("copy");
    alert("Content copied! You can now paste it on Instagram.");
}

function validateBankForm() {
    var bankName = document.getElementsByName('bank_name')[0].value.trim();
    var accountHolder = document.getElementsByName('account_holder')[0].value.trim();
    var accountNumber = document.getElementsByName('account_number')[0].value.trim();
    var ifscCode = document.getElementsByName('ifsc_code')[0].value.trim();
    var upiId = document.getElementsByName('upi_id')[0].value.trim();
    var upiName = document.getElementsByName('upi_name')[0].value.trim();
    
    if (bankName === '' || accountHolder === '' || accountNumber === '' || ifscCode === '' || upiId === '' || upiName === '') {
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

                        </div>
                        
                    </div>
                    
                </div>

                
            </main>

           
<?php include '../footer.php'; ?>

