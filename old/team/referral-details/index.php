
<?php
// Start session and include database connection first
require_once('../../common/config.php');

// Get user's email from session
$user_email = $_SESSION['user_email'] ?? '';

// For team members, no need to check collaboration_enabled - they have direct access

// Now include the header after all potential redirects
include '../header.php';
?>
<?php
// Get user's referral code and basic info from team_members table using prepared statement
$user_stmt = $connect->prepare("SELECT referral_code, member_name FROM team_members WHERE member_email = ?");
$user_stmt->bind_param("s", $user_email);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_referral_code = $user_data['referral_code'] ?? '';
$user_stmt->close();

// Generate referral code if it doesn't exist
if(empty($user_referral_code)) {
    $new_referral_code = strtoupper(substr(md5($user_email . time()), 0, 8));
    $update_stmt = $connect->prepare("UPDATE team_members SET referral_code = ? WHERE member_email = ?");
    $update_stmt->bind_param("ss", $new_referral_code, $user_email);
    $update_stmt->execute();
    $update_stmt->close();
    $user_referral_code = $new_referral_code;
    // Update session as well
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
$total_referrals_stmt = $connect->prepare("SELECT COUNT(DISTINCT COALESCE(ud_referred.email, re.referred_email)) as total_count
    FROM user_details ud_referred
    LEFT JOIN referral_earnings re 
        ON CONVERT(re.referred_email USING utf8mb4) = CONVERT(ud_referred.email USING utf8mb4)
        AND CONVERT(re.referrer_email USING utf8mb4) = CONVERT(? USING utf8mb4)
    WHERE (CONVERT(ud_referred.referred_by USING utf8mb4) = CONVERT(? USING utf8mb4)
           AND ud_referred.referred_by != ''
           AND ud_referred.referred_by IS NOT NULL)
       OR (re.id IS NOT NULL AND CONVERT(re.referrer_email USING utf8mb4) = CONVERT(? USING utf8mb4))");

$total_referrals_stmt->bind_param("sss", $user_email, $user_email, $user_email);
$total_referrals_stmt->execute();
$total_referrals_result = $total_referrals_stmt->get_result();
$total_referrals_data = $total_referrals_result->fetch_assoc();
$total_referrals = $total_referrals_data['total_count'] ?? 0;
$total_referrals_stmt->close();

// Calculate Total Sales = Count MWs that have "Paid on Date" (payment status = 'Success' and has payment date)
$total_sales = 0;
$sales_stmt = $connect->prepare("
    SELECT COUNT(*) as sales_count
    FROM referral_earnings re
    LEFT JOIN digi_card dc ON CONVERT(re.referred_email USING utf8mb4) = CONVERT(dc.user_email USING utf8mb4)
    WHERE CONVERT(re.referrer_email USING utf8mb4) = CONVERT(? USING utf8mb4)
    AND dc.d_payment_status = 'Success'
    AND dc.d_payment_date IS NOT NULL
    AND dc.d_payment_date != '0000-00-00 00:00:00'
");
if ($sales_stmt) {
    $sales_stmt->bind_param("s", $user_email);
    $sales_stmt->execute();
    $sales_result = $sales_stmt->get_result();
    if ($sales_row = $sales_result->fetch_assoc()) {
        $total_sales = (int)$sales_row['sales_count'];
    }
    $sales_stmt->close();
}

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
                    <span class="heading"><?php echo $page_title; ?></span> 
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
                                <div class="row displayflex">
                                    <div class="col-sm-3">
                                        <div class="card">
                                            <div class="img"><img src="../../Common/assets/img/TotalSales.png" alt=""></div>
                                            <div class="content">
                                                <p>Total Sales</p>
                                                <h4><?php echo $total_sales; ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-3">
                                        <div class="card card2">
                                            <div class="img"><img src="../../Common/assets/img/TotalMWCreated.png" alt=""></div>
                                            <div class="content content2">
                                                <p>Total MW Created</p>
                                                <h4><?php echo $total_referrals; ?></h4>
                                            </div>
                                        </div>
                                    </div>
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
                                <table id="ReferredUsers scrollable" class="display table">
                                    <thead class="bg-secondary">
                                        <tr>
                                            <th class="text-left">User ID</th>
                                            <th class="text-left">MW ID</th>
                                            <th class="text-left">User Email</th>
                                            <th class="text-left">User Name</th>
                                            <th class="text-left">User Number</th>
                                            <th class="text-left">Joined On</th>
                                            <th class="text-left">Date Created</th>
                                            <th class="text-left">Validity Date</th>
                                            <th class="text-left">MW Status</th>
                                            <th class="text-left">User Payment Status</th>
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
                                                // Date Created (card uploaded date)
                                                $date_created = !empty($row['card_uploaded_date']) ? date('d-m-Y', strtotime($row['card_uploaded_date'])) : '-';
                                                echo '<td class="text-left">' . $date_created . '</td>';
                                                
                                                // Determine MW Status first
                                                $mw_status = '7 Day Trial';
                                                if (($row['complimentary_enabled'] ?? '') === 'Yes') {
                                                    $mw_status = 'Active';
                                                } else if (($row['d_payment_status'] ?? '') === 'Success') {
                                                    $mw_status = 'Active';
                                                }
                                                
                                                // Validity Date - Calculate based on MW Status
                                                $validity_display = '-';
                                                if ($mw_status === '7 Day Trial') {
                                                    // For 7-day trial, always show 7 days after creation date
                                                    if (!empty($row['card_uploaded_date'])) {
                                                        $validity_display = date('d-m-Y', strtotime($row['card_uploaded_date'] . ' +7 days'));
                                                    }
                                                } else {
                                                    // For Active status (paid or complimentary)
                                                    if (!empty($row['card_validity_date'])) {
                                                        $validity_display = date('d-m-Y', strtotime($row['card_validity_date']));
                                                    } elseif (!empty($row['card_uploaded_date'])) {
                                                        if (($row['complimentary_enabled'] ?? '') === 'Yes') {
                                                            $validity_display = date('d-m-Y', strtotime($row['card_uploaded_date'] . ' +1 year'));
                                                        } else {
                                                            if (($row['d_payment_status'] ?? '') === 'Success' && !empty($row['d_payment_date'])) {
                                                                $validity_display = date('d-m-Y', strtotime($row['d_payment_date'] . ' +1 year'));
                                                            } else {
                                                                // Fallback: if somehow active but no payment date, use creation + 1 year
                                                                $validity_display = date('d-m-Y', strtotime($row['card_uploaded_date'] . ' +1 year'));
                                                            }
                                                        }
                                                    }
                                                }
                                                echo '<td class="text-left">' . $validity_display . '</td>';
                                                
                                                // Display MW Status
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
                                                echo '</tr>';
                                            }
                                        } else {
                                            echo '<tr><td colspan="10" class="text-left">No referrals found</td></tr>';
                                        }
                                        ?>
                                    </tbody>
                                </table>
                                </div>
                            </div>
                             
                        
                    </div>
                    
                </div>

                
            </main>
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
.ReferredUsers .heading {
        font-size: 22px;
        line-height: 67px;
        position: relative;
    }
}
 .card_area4 .card .img img{
    width: 140% !important;
 }

 .ReferralDetails-head .card .content h4, .FranchiseeDashboard-head .card .content h4{
    margin-top:10px;
 }
 .displayflex{
    display:flex;
    justify-content:space-evenly;
 }
 .displayflex .card2 img{
    width: 143% !important; 
 }
 .card2 .content2{
    width: 285px; 
 }
</style>
           
<?php 
// Close the prepared statement
$referred_users_stmt->close();
include '../footer.php'; 
?>































