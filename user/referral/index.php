
<?php
// Start session and include database connection first
require_once(__DIR__ . '/../../app/config/database.php');
require_once(__DIR__ . '/../../app/helpers/access_control.php');
require_once(__DIR__ . '/../../app/helpers/role_helper.php');

// Check page access - redirects to dashboard if unauthorized
require_page_access('/referral');

// Get user's email (works for CUSTOMER, TEAM, etc.)
$user_email = get_user_email() ?? '';
$current_role = get_current_user_role();

// Now include the header
include '../includes/header.php';
require_once(__DIR__ . '/../../app/helpers/mw_card_status_helper.php');
?>
<?php

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
        $bank_alert = ['type' => 'danger', 'message' => 'All fields are mandatory. Please fill all the required fields.'];
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
                $bank_alert = ['type' => 'success', 'message' => 'Bank details updated successfully!'];
            }
        } else {
            // Insert new bank details
            $insert_bank = mysqli_query($connect, "INSERT INTO user_bank_details 
                (user_email, bank_name, account_holder_name, account_number, ifsc_code, upi_id, upi_name, created_at) 
                VALUES ('$user_email', '$bank_name', '$account_holder', '$account_number', '$ifsc_code', '$upi_id', '$upi_name', NOW())");
            
            if($insert_bank) {
                $bank_alert = ['type' => 'success', 'message' => 'Bank details saved successfully!'];
            }
        }
    }
}

// Get existing bank details
$bank_alert = null;
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
    COALESCE(re.is_collaboration, 'NO') as is_collaboration,
    ud_referred.id AS user_id,
    ud_referred.role AS referred_role,
    ud_referred.email AS user_email,
    ud_referred.phone AS user_contact,
    ud_referred.name AS user_name,
    dc.id as card_id,
    dc.uploaded_date as card_uploaded_date,
    dc.validity_date as card_validity_date,
    dc.complimentary_enabled,
    dc.d_payment_status,
    dc.d_payment_date,
    dc.f_user_email
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

// For TEAM: Total Sales (referred users with successful payment) and Total MW Created (count of digi_card)
$total_sales = 0;
$total_mw_created = 0;
if ($current_role === 'TEAM' && $user_email !== '') {
    $ref_cond = "(CONVERT(ud_referred.referred_by USING utf8mb4) = CONVERT('" . mysqli_real_escape_string($connect, $user_email) . "' USING utf8mb4) AND ud_referred.referred_by != '' AND ud_referred.referred_by IS NOT NULL)
        OR EXISTS (SELECT 1 FROM referral_earnings re WHERE CONVERT(re.referred_email USING utf8mb4) = CONVERT(ud_referred.email USING utf8mb4) AND CONVERT(re.referrer_email USING utf8mb4) = CONVERT('" . mysqli_real_escape_string($connect, $user_email) . "' USING utf8mb4))";
    $ts_q = mysqli_query($connect, "SELECT COUNT(DISTINCT ud_referred.email) AS total_sales FROM user_details ud_referred
        INNER JOIN digi_card dc ON CONVERT(dc.user_email USING utf8mb4) = CONVERT(ud_referred.email USING utf8mb4) AND dc.d_payment_status = 'Success'
        WHERE $ref_cond");
    if ($ts_q && $r = mysqli_fetch_array($ts_q)) {
        $total_sales = (int)($r['total_sales'] ?? 0);
    }
    $tm_q = mysqli_query($connect, "SELECT COUNT(dc.id) AS total_mw FROM user_details ud_referred
        LEFT JOIN digi_card dc ON CONVERT(dc.user_email USING utf8mb4) = CONVERT(ud_referred.email USING utf8mb4)
        WHERE $ref_cond");
    if ($tm_q && $r = mysqli_fetch_array($tm_q)) {
        $total_mw_created = (int)($r['total_mw'] ?? 0);
    }
}

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

$page_heading = ($current_role === 'TEAM') ? 'Sales Details' : 'Referral Details';
$breadcrumb_active = $page_heading;

if ($current_role === 'TEAM') {
    $referral_stat_cards = [
        ['label' => 'Total Sales', 'icon' => 'cart-shopping', 'icon_variant' => 'blue', 'value' => (string)(int)$total_sales, 'is_currency' => false],
        ['label' => 'Total MW Created', 'icon' => 'circle-check', 'icon_variant' => 'blue', 'value' => (string)(int)$total_mw_created, 'is_currency' => false],
    ];
} else {
    $referral_stat_cards = [
        ['label' => 'Pending Amount', 'icon' => 'clock', 'icon_variant' => 'blue', 'value' => number_format($pending_amount, 0), 'is_currency' => true],
        ['label' => 'Total Referral Earning', 'icon' => 'indian-rupee-sign', 'icon_variant' => 'blue', 'value' => number_format($total_earning, 0), 'is_currency' => true],
        ['label' => 'Referred MW', 'icon' => 'users', 'icon_variant' => 'blue', 'value' => (string)(int)$total_referrals, 'is_currency' => false],
    ];
}

$bank_details_exist = !empty($bank_data['bank_name']) && !empty($bank_data['account_holder_name']) && !empty($bank_data['account_number']) && !empty($bank_data['ifsc_code']) && !empty($bank_data['upi_id']) && !empty($bank_data['upi_name']);
$is_team_view = ($current_role === 'TEAM');
?>
<meta property="og:title" content="Create Your Own MiniWebsite (Digital Business Card) Today!" />
<meta property="og:description" content="Say goodbye to paper visiting cards ✋ Create your own Mini Website in just a few minutes – simple, smart & eco-friendly 🌱" />
<meta property="og:image" content="<?php echo $image_url; ?>" />
<meta property="og:url" content="https://miniwebsite.in/panel/login/create-account.php?ref=<?php echo $user_referral_code; ?>" />
<meta property="og:type" content="website" />
<main class="Dashboard mw-page">
    <div class="customer_content_area mw-container">
        <div class="main-top mw-page-header">
            <h1 class="heading mw-page-title"><?php echo htmlspecialchars($page_heading, ENT_QUOTES, 'UTF-8'); ?></h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mw-breadcrumb">
                    <li class="breadcrumb-item mw-breadcrumb-item"><a href="#">Mini Website</a></li>
                    <li class="breadcrumb-item mw-breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($breadcrumb_active, ENT_QUOTES, 'UTF-8'); ?></li>
                </ol>
            </nav>
        </div>

        <div class="mw-card mb-4">
            <div class="mw-card-body">
                <div class="ReferralDetails-head">
                    <div class="mw-dash-card-grid">
                        <?php foreach ($referral_stat_cards as $stat):
                            $icon_variant = ($stat['icon_variant'] ?? 'blue') === 'gold' ? 'gold' : 'blue';
                        ?>
                        <div class="card_area">
                            <div class="card">
                                <div class="img mw-dash-card-icon mw-dash-card-icon--<?php echo $icon_variant; ?>" aria-hidden="true">
                                    <i class="fa-solid fa-<?php echo htmlspecialchars($stat['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                                </div>
                                <div class="content">
                                    <p><?php echo htmlspecialchars($stat['label'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <h4>
                                        <?php if (!empty($stat['is_currency'])): ?>
                                            <i class="fa fa-inr" aria-hidden="true"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($stat['value'], ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($stat['is_currency']) ? '/-' : ''; ?>
                                    </h4>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (!$is_team_view): ?>
                <section class="mw-bank-details-section mb-8 pt-6 border-t border-border">
                    <h2 class="heading mw-section-title">Bank Account Details</h2>
                    <p class="mw-helper-text mb-4">
                        <i class="fa fa-info-circle" aria-hidden="true"></i>
                        <span>Please submit the bank details where you want us to transfer your referral earnings.</span>
                    </p>

                    <?php if (!empty($bank_alert)): ?>
                    <div class="mw-alert mw-alert-<?php echo $bank_alert['type'] === 'success' ? 'success' : 'danger'; ?>" role="alert">
                        <i class="fa fa-<?php echo $bank_alert['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?> mw-alert-icon" aria-hidden="true"></i>
                        <div class="mw-alert-body"><?php echo htmlspecialchars($bank_alert['message'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <?php endif; ?>

                    <form method="POST" id="bankDetailsForm" class="mw-form" onsubmit="return validateBankForm()">
                        <div class="mw-form-grid-2">
                            <div class="mw-form-group">
                                <label class="mw-label" for="bank_name">Bank Name <span class="req">*</span></label>
                                <input type="text" name="bank_name" id="bank_name" placeholder="Enter Bank Name" class="form-control mw-input" value="<?php echo htmlspecialchars($bank_data['bank_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required <?php echo $bank_details_exist ? 'readonly' : ''; ?>>
                            </div>
                            <div class="mw-form-group">
                                <label class="mw-label" for="account_holder">Account Holder Name <span class="req">*</span></label>
                                <input type="text" name="account_holder" id="account_holder" placeholder="Account Holder Name" class="form-control mw-input" value="<?php echo htmlspecialchars($bank_data['account_holder_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required <?php echo $bank_details_exist ? 'readonly' : ''; ?>>
                            </div>
                            <div class="mw-form-group">
                                <label class="mw-label" for="account_number">Bank Account Number <span class="req">*</span></label>
                                <input type="text" name="account_number" id="account_number" placeholder="Enter Your Bank Account Number" class="form-control mw-input" value="<?php echo htmlspecialchars($bank_data['account_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required <?php echo $bank_details_exist ? 'readonly' : ''; ?>>
                            </div>
                            <div class="mw-form-group">
                                <label class="mw-label" for="ifsc_code">Bank IFSC Code <span class="req">*</span></label>
                                <input type="text" name="ifsc_code" id="ifsc_code" placeholder="Enter IFSC Code" class="form-control mw-input" value="<?php echo htmlspecialchars($bank_data['ifsc_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required <?php echo $bank_details_exist ? 'readonly' : ''; ?>>
                            </div>
                            <div class="mw-form-group">
                                <label class="mw-label" for="upi_id">UPI ID <span class="req">*</span></label>
                                <input type="text" name="upi_id" id="upi_id" placeholder="Enter UPI ID (e.g., user@paytm)" class="form-control mw-input" value="<?php echo htmlspecialchars($bank_data['upi_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required <?php echo $bank_details_exist ? 'readonly' : ''; ?>>
                            </div>
                            <div class="mw-form-group">
                                <label class="mw-label" for="upi_name">UPI Name <span class="req">*</span></label>
                                <input type="text" name="upi_name" id="upi_name" placeholder="Enter UPI Name" class="form-control mw-input" value="<?php echo htmlspecialchars($bank_data['upi_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required <?php echo $bank_details_exist ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <?php if ($bank_details_exist): ?>
                            <p class="mw-helper-text !mb-4">
                                <i class="fa fa-info-circle" aria-hidden="true"></i>
                                <span>Bank details have been saved successfully. Contact support if you need to make changes.</span>
                            </p>
                            <button type="button" class="mw-btn mw-btn-secondary" disabled>Bank Details Saved</button>
                        <?php else: ?>
                            <button type="submit" name="submit_bank_details" class="mw-btn mw-btn-save">Submit</button>
                        <?php endif; ?>
                    </form>
                </section>
                <?php endif; ?>

                <section class="<?php echo $is_team_view ? '' : 'pt-6 border-t border-border'; ?>">
                    <h2 class="heading mw-section-title mb-4">Referred Users</h2>
                    <div class="mw-table-scroll mw-table-scroll-xl">
                        <table id="ReferredUsers" class="display table w-full mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-center">User ID</th>
                                            <th class="text-center">MW ID/FR ID</th>
                                            <th class="text-center">User Email</th>
                                            <th class="text-center">User Type</th>
                                            <th class="text-center">User Name</th>
                                            <th class="text-center">User Number</th>
                                            <th class="text-center">Joined On</th>
                                            <?php if (!$is_team_view): ?>
                                            <th class="text-center">Referral Source</th>
                                            <?php endif; ?>
                                            <th class="text-center">Date Created</th>
                                            <th class="text-center">Validity Date</th>
                                            <th class="text-center">MW Status</th>
                                            <th class="text-center">User Payment Status</th>
                                            <?php if (!$is_team_view): ?>
                                            <th class="text-center">Referral Amt.</th>
                                            <th class="text-center">MW Payment Status</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if(mysqli_num_rows($referred_users_query) > 0) {
                                            while($row = mysqli_fetch_array($referred_users_query)) {
                                                echo '<tr>';
                                                // User ID from unified table, N/A if not a registered customer yet
                                                echo '<td class="text-center">' . (!empty($row['user_id']) ? (int)$row['user_id'] : 'N/A') . '</td>';
                                                
                                                // Determine referral type (used by MW ID/FR ID and User Type columns)
                                                $is_franchise_referral = (($row['is_collaboration'] ?? 'NO') === 'YES')
                                                    || strtoupper($row['referred_role'] ?? '') === 'FRANCHISEE';

                                                // MW ID / FR ID based on referral type
                                                echo '<td class="text-center">';
                                                if ($is_franchise_referral && !empty($row['user_id'])) {
                                                    echo 'FR - ' . str_pad((int)$row['user_id'], 3, '0', STR_PAD_LEFT);
                                                } elseif (!empty($row['card_id'])) {
                                                    $cardId = (int)$row['card_id'];
                                                    echo '<a href="https://' . $_SERVER['HTTP_HOST'] . '/n.php?n=' . $cardId . '" target="_blank" rel="noopener noreferrer" class="mw-table-cell-inline text-primary hover:text-primary-dark !no-underline">';
                                                    echo '<i class="fa fa-eye" aria-hidden="true"></i> ' . $cardId;
                                                    echo '</a>';
                                                } else {
                                                    echo 'N/A';
                                                }
                                                echo '</td>';
                                                
                                                // User Email / Type / Name / Number
                                                echo '<td class="text-center">' . htmlspecialchars($row['referred_email'] ?? $row['user_email'] ?? 'N/A') . '</td>';
                                                echo '<td class="text-center">' . ($is_franchise_referral ? 'Franchise' : 'MW') . '</td>';
                                                echo '<td class="text-center">' . htmlspecialchars($row['user_name'] ?? 'Unknown') . '</td>';
                                                echo '<td class="text-center">' . htmlspecialchars($row['user_contact'] ?? 'N/A') . '</td>';
                                                
                                                // Joined On (referral date)
                                                $joined_on = !empty($row['referral_date']) ? date('d-m-Y', strtotime($row['referral_date'])) : '-';
                                                echo '<td class="text-center">' . $joined_on . '</td>';
                                                
                                                if (!$is_team_view) {
                                                    echo '<td class="text-center">Referral</td>';
                                                }
                                                
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
                                                $mw_status_row = [
                                                    'complimentary_enabled' => $row['complimentary_enabled'] ?? '',
                                                    'd_payment_status' => $row['d_payment_status'] ?? '',
                                                    'validity_date' => $row['card_validity_date'] ?? '',
                                                    'uploaded_date' => $row['card_uploaded_date'] ?? '',
                                                    'f_user_email' => $row['f_user_email'] ?? '',
                                                ];
                                                $mw_status = mw_card_resolve_short_status($mw_status_row);
                                                echo '<td class="text-center"><span class="' . ($mw_status === 'Active' ? 'bg-success' : 'bg-pending') . '">' . htmlspecialchars($mw_status) . '</span></td>';
                                                
                                                // User Payment Status
                                                echo '<td class="text-center">';
                                                if(($row['d_payment_status'] ?? '') == 'Success') {
                                                    $payment_date = !empty($row['d_payment_date']) ? date('d-m-Y', strtotime($row['d_payment_date'])) : date('d-m-Y');
                                                    echo '<span class="bg-success">Paid on ' . $payment_date . '</span>';
                                                } else {
                                                    echo '<span class="bg-unpaid">Unpaid</span>';
                                                }
                                                echo '</td>';
                                                
                                                if (!$is_team_view) {
                                                    // Referral Amount
                                                    echo '<td class="text-center">₹ ' . number_format($row['amount'] ?? 0, 0) . '</td>';
                                                    // MW Payment Status (referral earnings status)
                                                    echo '<td class="text-center">';
                                                    if(($row['status'] ?? '') == 'Paid') {
                                                        $mw_payment_date = !empty($row['payment_date']) ? date('d-m-Y', strtotime($row['payment_date'])) : 'N/A';
                                                        echo '<span class="bg-success">Paid on ' . $mw_payment_date . '</span>';
                                                    } elseif(($row['status'] ?? '') == 'Partial' && ($row['d_payment_status'] ?? '') == 'Success') {
                                                        echo '<span class="bg-pending">Partial Payment</span>';
                                                    } elseif(($row['status'] ?? '') == 'Pending' && ($row['d_payment_status'] ?? '') == 'Success') {
                                                        echo '<span class="bg-pending">Pending</span>';
                                                    } else {
                                                        echo '<span class="not-eligible">Not Eligible</span>';
                                                    }
                                                    echo '</td>';
                                                }
                                                echo '</tr>';
                                            }
                                        } else {
                                            $colspan = $is_team_view ? 11 : 14;
                                            echo '<tr><td colspan="' . $colspan . '" class="text-center">No referrals found</td></tr>';
                                        }
                                        ?>
                                    </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>

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

<?php include '../includes/footer.php'; ?>




