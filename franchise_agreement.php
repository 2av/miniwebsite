<?php
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/role_access_helper.php';

// Get email from URL parameter
$prefill_email = isset($_GET['email']) ? trim((string) $_GET['email']) : '';

$fr_profile_key = null;
if (!empty($_SESSION['user_email'])) {
    if (!function_exists('get_current_user_role_access_settings')) {
        require_once __DIR__ . '/app/helpers/role_helper.php';
    }
    $ras_fr = get_current_user_role_access_settings($connect);
    $fr_profile_key = $ras_fr['profile_key'] ?? null;
} elseif ($prefill_email !== '') {
    $fr_profile_key = resolve_role_access_profile_for_email($connect, $prefill_email);
}

$fr_rules = get_franchise_payment_role_rules($connect, $fr_profile_key);
$franchise_plan_visibility = $fr_rules['plan_visibility'];
$franchise_agreement_label_html = $fr_rules['agreement_label_html'];
$default_franchise_plan = $fr_rules['default_plan'];

// Database connection and fetch user details
$user_data = null;

try {
    // Get franchisee agreement content
    $content_query = mysqli_query($connect, "SELECT * FROM content_management WHERE content_type='franchisee_agreement' AND is_active=1");
    
    if(mysqli_num_rows($content_query) > 0) {
        $content = mysqli_fetch_array($content_query);
        $page_title = $content['title'];
        $page_content = $content['content'];
        $meta_description = $content['meta_description'];
        $meta_keywords = $content['meta_keywords'];
    } else {
        // Fallback to default content
        $page_title = "Franchisee Agreement";
        $page_content = "<p>Content not available. Please contact administrator.</p>";
        $meta_description = "Franchisee Agreement for MiniWebsite";
        $meta_keywords = "franchisee, agreement, partnership";
    }
    
    // Get user data if email is provided
    if (!empty($prefill_email)) {
        $query = "SELECT f_user_name as name, f_user_email as email, f_user_contact as contact, 
                     f_user_address as address, f_user_state as state, f_user_city as city, 
                     f_user_pincode as pincode, f_user_gst as gst_number, referred_by
              FROM franchisee_login WHERE f_user_email = '" . mysqli_real_escape_string($connect, $prefill_email) . "'";
        $result = mysqli_query($connect, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $user_data = mysqli_fetch_assoc($result);
        }
    }
    
} catch (Exception $e) {
    // Handle database connection error silently
    $user_data = null;
    $page_title = "Franchisee Agreement";
    $page_content = "<p>Content not available. Please contact administrator.</p>";
    $meta_description = "Franchisee Agreement for MiniWebsite";
    $meta_keywords = "franchisee, agreement, partnership";
}

// Initialize promo variables
$promo_applied = false;
$promo_discount = 0;
$promo_message = '';
$is_auto_applied = false;

// Include coupon functions for auto-promocode functionality
if ($connect) {
    require_once('admin/coupon_functions.php');
    
    // Check if this is a referral franchisee and auto-apply promocode
    if (!empty($user_data) && !empty($user_data['referred_by'])) {
        $referred_by = $user_data['referred_by'];
        $is_referral_franchisee = !empty($referred_by);
        
        // Always check for the latest mapped deal on every page load
        if ($is_referral_franchisee) {
            $auto_promo_code = '';
            $is_new_deal = false;
            
            // First, check for newly mapped deals (created in last hour) - mapped to referral code
            $new_deal_query = mysqli_query($connect, "SELECT d.* FROM deals d 
                INNER JOIN deal_customer_mapping dcm ON d.id = dcm.deal_id 
                WHERE dcm.customer_email = '" . mysqli_real_escape_string($connect, $referred_by) . "' 
                AND d.deal_status = 'Active' 
                AND d.plan_type = 'Franchise'
                AND dcm.created_date > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY dcm.created_date DESC LIMIT 1");
            
            if (mysqli_num_rows($new_deal_query) > 0) {
                // Use newly mapped deal
                $new_deal = mysqli_fetch_array($new_deal_query);
                $auto_promo_code = $new_deal['coupon_code'];
                $is_new_deal = true;
            } else {
                // Check for any existing mapped deals - mapped to referral code
                $referral_deal_sql = "SELECT d.* FROM deals d 
                    INNER JOIN deal_customer_mapping dcm ON d.id = dcm.deal_id 
                    WHERE dcm.customer_email = '" . mysqli_real_escape_string($connect, $referred_by) . "' 
                    AND d.deal_status = 'Active' 
                    AND d.plan_type = 'Franchise'
                    ORDER BY d.id DESC LIMIT 1";
                
                error_log("Franchise Deal mapping query: " . $referral_deal_sql);
                $referral_deal_query = mysqli_query($connect, $referral_deal_sql);
                
                if (mysqli_num_rows($referral_deal_query) > 0) {
                    // Use existing mapped deal
                    $referral_deal = mysqli_fetch_array($referral_deal_query);
                    $auto_promo_code = $referral_deal['coupon_code'];
                    
                    // Debug logging
                    error_log("Found mapped franchise deal for referral: " . $referred_by . " - Deal: " . $auto_promo_code);
                } else {
                    // Debug logging
                    error_log("No mapped franchise deals found for referral: " . $referred_by . " - Using default DFRAN101");
                    // Use default promocode for franchise referrals
                    $auto_promo_code = 'DFRAN101';
                    
                    // Check if DFRAN101 deal exists, create it if it doesn't
                    $dfran_check = mysqli_query($connect, "SELECT * FROM deals WHERE coupon_code='DFRAN101'");
                    if (mysqli_num_rows($dfran_check) == 0) {
                        // Create DFRAN101 deal for default franchise referral discount
                        $create_dfran = mysqli_query($connect, "INSERT INTO deals (
                            plan_name, plan_type, deal_name, coupon_code, bonus_amount, 
                            discount_amount, discount_percentage, validity_date, max_usage, 
                            deal_status, created_by, uploaded_date
                        ) VALUES (
                            'Franchise Registration', 'Franchise', 'Default Franchise Referral Discount', 'DFRAN101', 
                            0, 500, 0, '2025-12-31', 0, 'Active', 'system', NOW()
                        )");
                    }
                }
            }
            
            // Validate and apply the auto promocode
            if (!empty($auto_promo_code)) {
                $validation = validateCoupon($auto_promo_code, $connect, 'franchise_registration');
                if ($validation['valid']) {
                    $original_amount = 30000; // Franchise registration amount before GST
                    $auto_discount = getCouponDiscount($original_amount, $validation['deal']);
                    if ($auto_discount > 0 && $auto_discount <= $original_amount) {
                        // Check if this is a different promocode than what's currently in session
                        $current_session_promo = isset($_SESSION['promo_code']) ? $_SESSION['promo_code'] : '';
                        $current_session_discount = isset($_SESSION['promo_discount']) ? $_SESSION['promo_discount'] : 0;
                        
                        // Always update session with the latest deal (even if same promocode, in case discount changed)
                        $_SESSION['promo_code'] = $auto_promo_code;
                        $_SESSION['promo_discount'] = $auto_discount;
                        $_SESSION['auto_applied_promo'] = true; // Mark as auto-applied
                        $promo_applied = true;
                        $promo_discount = $auto_discount;
                        $is_auto_applied = true;
                        
                        // Set appropriate message based on whether it's a new deal or updated deal
                        if ($is_new_deal) {
                            $promo_message = '<div class="promo-success">New referral promocode ' . $auto_promo_code . ' applied automatically! Discount: ₹' . number_format($auto_discount, 2) . '</div>';
                        } else if ($current_session_promo !== $auto_promo_code || $current_session_discount !== $auto_discount) {
                            $promo_message = '<div class="promo-success">Referral promocode updated to ' . $auto_promo_code . '! Discount: ₹' . number_format($auto_discount, 2) . '</div>';
                        } else {
                            $promo_message = '<div class="promo-success">Referral promocode ' . $auto_promo_code . ' applied automatically! Discount: ₹' . number_format($auto_discount, 2) . '</div>';
                        }
                        
                        // Apply the coupon (increment usage count) - only if it's a new promocode
                        if ($current_session_promo !== $auto_promo_code) {
                            applyCoupon($auto_promo_code, $connect, 'franchise_registration');
                        }
                        
                        // Log the auto-application for debugging
                        $log_message = $is_new_deal ? "Auto-applied new franchise referral promocode" : 
                                      ($current_session_promo !== $auto_promo_code ? "Updated franchise referral promocode" : "Refreshed franchise referral promocode");
                        error_log($log_message . ": " . $auto_promo_code . " for franchisee: " . $prefill_email . " with discount: " . $auto_discount);
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - MiniWebsite</title>
    <meta name="description" content="<?php echo htmlspecialchars($meta_description); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($meta_keywords); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        :root {
            --mw-navy: #002169;
            --mw-yellow: #ffc107;
            --mw-light-blue: #f0f5ff;
            --mw-green: #4CAF50;
            --mw-orange: #FF9800;
            --mw-text: #333333;
            --mw-text-muted: #666666;
            --mw-border: #e0e0e0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            line-height: 1.4;
            margin: 0;
            background: #f5f5f5;
            color: var(--mw-text);
        }
        .container {
            margin: auto;
            padding: 20px;
            max-width: 1100px;
        }
        input[readonly] {
            background-color: #f8f9fa !important;
            border-color: #dee2e6 !important;
            cursor: not-allowed !important;
            opacity: 0.8;
        }
        .promo-message {
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 13px;
            margin-top: 5px;
        }
        .promo-message.success {
            background: #d4edda;
            color: #155724;
        }
        .promo-message.error {
            background: #f8d7da;
            color: #721c24;
        }
        .promo-message.info {
            background: #d1ecf1;
            color: #0c5460;
        }
        .franchise-payment-section {
            margin-top: 40px;
            padding: 10px 0 30px;
        }
        .page-payment-title {
            text-align: center;
            color: var(--mw-navy);
            margin: 0 0 8px;
            font-size: 28px;
            font-weight: 700;
        }
        .page-payment-subtitle {
            text-align: center;
            margin: 0 0 14px;
            color: var(--mw-text-muted);
            font-size: 15px;
        }
        .trust-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #fff;
            border: 1px solid #c5d4f0;
            border-radius: 999px;
            padding: 7px 16px;
            color: var(--mw-navy);
            font-size: 13px;
            font-weight: 600;
        }
        .trust-pill-wrap {
            text-align: center;
            margin-bottom: 28px;
        }
        .payment-layout {
            display: flex;
            gap: 24px;
            align-items: flex-start;
            justify-content: center;
            flex-wrap: wrap;
            max-width: 100%;
            margin: 0 auto;
        }
        @media (min-width: 992px) {
            .payment-layout {
                flex-wrap: nowrap;
                align-items: stretch;
            }
        }
        .plan-panel {
            width: 340px;
            max-width: 100%;
            flex-shrink: 0;
        }
        .plan-card {
            border-radius: 14px;
            background: #fff;
            border: 2px solid #d5dbe7;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .plan-card.active {
            border-color: var(--mw-navy);
            box-shadow: 0 0 0 3px rgba(0, 33, 105, 0.12);
        }
        .plan-card-header {
            font-size: 18px;
            font-weight: 700;
            color: #fff;
            text-align: center;
            padding: 14px 12px;
        }
        .plan-card-header.blue { background: var(--mw-navy); }
        .plan-card-header.yellow {
            background: var(--mw-yellow);
            color: #222;
        }
        .plan-card-body { padding: 16px 18px 18px; }
        .plan-card .plan-price {
            text-align: center;
            font-size: 36px;
            font-weight: 700;
            margin: 10px 0 4px;
            color: #111;
            line-height: 1;
        }
        .plan-subtext {
            text-align: center;
            color: #555;
            margin-bottom: 14px;
            font-size: 15px;
        }
        .plan-features {
            list-style: none;
            margin: 0 0 16px;
            padding: 0;
        }
        .plan-features li {
            font-size: 14px;
            margin: 9px 0;
            color: #202124;
            position: relative;
            padding-left: 22px;
            line-height: 1.4;
        }
        .plan-features li::before {
            content: "✓";
            color: var(--mw-navy);
            font-weight: 700;
            position: absolute;
            left: 0;
            top: 0;
        }
        .plan-features.orange li::before { color: var(--mw-orange); }
        .plan-select-btn {
            width: 100%;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .plan-select-btn.green {
            background: var(--mw-green);
            color: #fff;
        }
        .plan-select-btn.yellow {
            background: var(--mw-yellow);
            color: #111;
        }
        .wallet-info-box {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            background: var(--mw-light-blue);
            border: 1px solid rgba(0, 33, 105, 0.15);
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 14px;
            color: var(--mw-navy);
            line-height: 1.5;
        }
        .wallet-info-box i {
            font-size: 22px;
            color: var(--mw-navy);
            margin-top: 2px;
            flex-shrink: 0;
        }
        .billing-panel {
            width: 400px;
            max-width: 100%;
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.1);
            flex: 1;
            min-width: 320px;
        }
        .billing-panel-header {
            background: var(--mw-navy);
            padding: 22px 20px 28px;
        }
        .billing-header-top {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            width: fit-content;
            max-width: 100%;
            margin: 0 auto;
        }
        .billing-header-icon {
            width: 48px;
            height: 48px;
            background: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .billing-header-icon i {
            font-size: 22px;
            color: var(--mw-navy);
        }
        .billing-header-text h4 {
            color: #fff;
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 8px;
            text-align: left;
        }
        .billing-header-line {
            width: 48px;
            height: 3px;
            background: var(--mw-yellow);
            border-radius: 2px;
        }
        .billing-panel-body {
            padding: 20px 18px 18px;
            margin-top: -12px;
            background: #fff;
            border-radius: 16px 16px 0 0;
        }
        .input-icon-wrap {
            display: flex;
            align-items: center;
            border: 1px solid var(--mw-border);
            border-radius: 8px;
            margin-bottom: 12px;
            background: #fff;
            overflow: hidden;
        }
        .input-icon-wrap:focus-within {
            border-color: var(--mw-navy);
            box-shadow: 0 0 0 2px rgba(0, 33, 105, 0.1);
        }
        .input-icon-wrap > i {
            width: 40px;
            text-align: center;
            color: var(--mw-navy);
            font-size: 15px;
            flex-shrink: 0;
        }
        .input-icon-wrap input {
            flex: 1;
            border: none;
            padding: 13px 12px 13px 0;
            font-size: 14px;
            outline: none;
            background: transparent;
            min-width: 0;
        }
        .form-row {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
        }
        .form-row .input-icon-wrap {
            flex: 1;
            margin-bottom: 0;
            min-width: 0;
        }
        .billing-input-uppercase { text-transform: uppercase; }
        .calculation-display {
            margin: 18px 0;
            background: var(--mw-light-blue);
            padding: 16px;
            border-radius: 10px;
            border: 1px solid rgba(0, 33, 105, 0.15);
        }
        .calculation-display table {
            width: 100%;
            font-size: 14px;
            color: var(--mw-text);
            border-collapse: collapse;
        }
        .calculation-display td { padding: 6px 0; }
        .calculation-display td:last-child {
            text-align: right;
            font-weight: 600;
        }
        .calculation-display tr:last-child td {
            border-top: 1px dashed rgba(0, 33, 105, 0.25);
        }
        .calculation-display td.final-total {
            padding-top: 12px;
            font-size: 18px;
            font-weight: 800;
            color: var(--mw-navy);
        }
        .promo-wrap {
            display: flex;
            border: 1px solid var(--mw-border);
            border-radius: 8px;
            overflow: hidden;
            margin-top: 4px;
        }
        .promo-icon-wrap {
            display: flex;
            align-items: center;
            padding: 0 12px;
            color: var(--mw-navy);
            border-right: 1px solid var(--mw-border);
        }
        .promo-wrap input {
            flex: 1;
            padding: 11px 12px;
            border: none;
            font-size: 14px;
            outline: none;
        }
        .promo-wrap button {
            padding: 11px 18px;
            background: var(--mw-navy);
            color: #fff;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .promo-applied-box {
            margin-bottom: 10px;
            padding: 10px 12px;
            background: #e8f5e9;
            border-radius: 8px;
            font-size: 14px;
        }
        .promo-applied-box .promo-code-text {
            color: var(--mw-green);
            font-weight: 700;
        }
        .terms-wrap {
            margin: 18px 0 16px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 12px;
            line-height: 1.55;
            color: var(--mw-navy);
        }
        .terms-wrap label { color: var(--mw-navy); }
        .terms-wrap input[type="checkbox"] {
            margin-top: 3px;
            width: 16px;
            height: 16px;
            accent-color: var(--mw-navy);
            flex-shrink: 0;
        }
        .terms-wrap a {
            color: var(--mw-navy);
            text-decoration: underline;
            font-weight: 600;
        }
        .proceed-btn {
            width: 100%;
            background: var(--mw-yellow);
            color: #000;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            letter-spacing: 0.5px;
        }
        .proceed-btn:hover { background: #e6ac00; }
        .proceed-btn:disabled { opacity: 0.7; cursor: not-allowed; }
        .trust-badges {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px solid var(--mw-border);
        }
        .trust-badge {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 10px;
            color: var(--mw-navy);
            font-weight: 500;
            flex: 1;
            min-width: 90px;
            justify-content: center;
        }
        .trust-badge i { font-size: 14px; }
        .payment-footer {
            text-align: center;
            margin-top: 28px;
            padding-top: 10px;
        }
        .payment-footer .trust-badges {
            border-top: none;
            justify-content: center;
            gap: 24px;
            margin-top: 0;
            padding-top: 0;
        }
        .payment-footer .trust-badge {
            flex: 0 1 auto;
            font-size: 12px;
        }
        .payment-copyright {
            margin-top: 16px;
            font-size: 13px;
            color: #9ca3af;
        }
        #remove_promo_btn:hover { background: #c82333 !important; }
    </style>
</head>
<body>
       <div class="container">
        <?php if(isset($content['last_updated'])): ?>
        <div style="font-size: 0.9em; color: #6c757d; font-style: italic; margin-bottom: 20px;">
            Last updated: <?php echo date('F j, Y', strtotime($content['last_updated'])); ?>
        </div>
        <?php endif; ?>
        
        <div class="content">
            <?php echo $page_content; ?>
        </div>
        

<!-- Payment Form -->
<div id="paymentSection" class="franchise-payment-section">
    <h2 class="page-payment-title">Franchise Registration Payment</h2>
    <p class="page-payment-subtitle">Choose your plan &amp; start your MiniWebsite franchise journey</p>
    <div class="trust-pill-wrap">
        <div class="trust-pill">
            <i class="fa fa-shield"></i>
            Trusted by 1000+ Partners Across India
        </div>
    </div>

    <div class="payment-layout">
        <div class="plan-panel">
            <?php if (!empty($franchise_plan_visibility['starter'])): ?>
            <div class="plan-card<?php echo ($default_franchise_plan === 'starter') ? ' active' : ''; ?>" id="starterPlanCard" data-plan="starter">
                <div class="plan-card-header blue">Starter Franchise Plan</div>
                <div class="plan-card-body">
                    <div class="plan-price">₹6,000</div>
                    <div class="plan-subtext">for 4 Months</div>
                    <ul class="plan-features">
                        <li>Trial Franchise of 4 Months</li>
                        <li>100% Commission Earning for 4 Months</li>
                        <li>₹413/website creation from wallet</li>
                    </ul>
                    <button type="button" class="plan-select-btn green" data-plan="starter">SELECT ₹6,000</button>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($franchise_plan_visibility['full'])): ?>
            <div class="plan-card<?php echo ($default_franchise_plan === 'full') ? ' active' : ''; ?>" id="fullPlanCard" data-plan="full">
                <div class="plan-card-header yellow">Full Franchise Plan</div>
                <div class="plan-card-body">
                    <div class="plan-price">₹30,000</div>
                    <div class="plan-subtext">(One-time Fees)</div>
                    <ul class="plan-features orange">
                        <li>Lifetime Unlimited MiniWebsite rights</li>
                        <li>100% Commission Earning Lifelong</li>
                        <li>₹413/website creation from wallet</li>
                    </ul>
                    <button type="button" class="plan-select-btn yellow" data-plan="full">SELECT ₹30,000</button>
                </div>
            </div>
            <?php endif; ?>
            <div class="wallet-info-box">
                <i class="fa fa-gift"></i>
                <span>₹413 will be deducted from your wallet for each MiniWebsite you create for your customers.</span>
            </div>
        </div>

        <div class="billing-panel">
            <div class="billing-panel-header">
                <div class="billing-header-top">
                    <div class="billing-header-icon">
                        <i class="fa fa-file-text-o"></i>
                    </div>
                    <div class="billing-header-text">
                        <h4>Billing &amp; GST Details</h4>
                        <div class="billing-header-line"></div>
                    </div>
                </div>
            </div>
            <div class="billing-panel-body">
                <form id="franchisePaymentForm" action="payment/pay.php" method="POST">
                    <div class="input-icon-wrap">
                        <i class="fa fa-file-text-o"></i>
                        <input type="text" name="gst_number" placeholder="ENTER GST NUMBER (OPTIONAL)" class="billing-input-uppercase">
                    </div>
                    <div class="input-icon-wrap">
                        <i class="fa fa-user"></i>
                        <input type="text" name="name" placeholder="Name" required>
                    </div>
                    <div class="form-row">
                        <div class="input-icon-wrap">
                            <i class="fa fa-envelope"></i>
                            <input type="email" name="email" placeholder="Email Address"
                                   value="<?php echo !empty($prefill_email) ? htmlspecialchars($prefill_email) : ''; ?>"
                                   <?php echo !empty($prefill_email) ? 'readonly' : ''; ?> required>
                        </div>
                        <div class="input-icon-wrap">
                            <i class="fa fa-phone"></i>
                            <input type="tel" name="contact" placeholder="Contact Number" required>
                        </div>
                    </div>
                    <div class="input-icon-wrap">
                        <i class="fa fa-map-marker"></i>
                        <input type="text" name="address" placeholder="Address" required>
                    </div>
                    <div class="form-row">
                        <div class="input-icon-wrap">
                            <i class="fa fa-building-o"></i>
                            <input type="text" name="city" placeholder="City" required>
                        </div>
                        <div class="input-icon-wrap">
                            <i class="fa fa-map"></i>
                            <input type="text" name="state" placeholder="State" required>
                        </div>
                    </div>
                    <div class="input-icon-wrap">
                        <i class="fa fa-th"></i>
                        <input type="text" name="pincode" placeholder="Pin Code" required>
                    </div>

                    <?php
                    $subtotal = 30000 - $promo_discount;
                    $is_interstate = false;
                    if ($is_interstate) {
                        $igst_amount = round($subtotal * 0.18, 2);
                        $cgst_amount = 0;
                        $sgst_amount = 0;
                    } else {
                        $cgst_amount = round($subtotal * 0.09, 2);
                        $sgst_amount = round($subtotal * 0.09, 2);
                        $igst_amount = 0;
                    }
                    $final_total = round($subtotal + $cgst_amount + $sgst_amount + $igst_amount, 2);
                    ?>

                    <div class="calculation-display">
                        <table>
                            <tr>
                                <td class="original-price">Original Price:</td>
                                <td class="original-price">₹ <?php echo number_format(30000, 2); ?></td>
                            </tr>
                            <tr>
                                <td class="discount">Discount:</td>
                                <td class="discount">₹ <?php echo number_format($promo_discount, 2); ?></td>
                            </tr>
                            <tr>
                                <td class="subtotal">Sub Total:</td>
                                <td class="subtotal">₹ <?php echo number_format($subtotal, 2); ?></td>
                            </tr>
                            <tr>
                                <td class="cgst">CGST (9%):</td>
                                <td class="cgst">₹ <?php echo number_format($cgst_amount, 2); ?></td>
                            </tr>
                            <tr>
                                <td class="sgst">SGST (9%):</td>
                                <td class="sgst">₹ <?php echo number_format($sgst_amount, 2); ?></td>
                            </tr>
                            <tr>
                                <td class="igst">IGST (18%):</td>
                                <td class="igst">₹ <?php echo number_format($igst_amount, 2); ?></td>
                            </tr>
                            <tr>
                                <td class="final-total"><strong>Final Total:</strong></td>
                                <td class="final-total"><strong>₹ <?php echo number_format($final_total, 2); ?></strong></td>
                            </tr>
                        </table>
                    </div>

                    <div id="promo-section">
                        <?php if(!$promo_applied): ?>
                        <div class="promo-wrap">
                            <div class="promo-icon-wrap"><i class="fa fa-tag"></i></div>
                            <input type="text" id="promo_code_input" placeholder="Enter promo code" maxlength="20">
                            <button type="button" id="apply_promo_btn">Apply</button>
                        </div>
                        <?php else: ?>
                        <div class="promo-applied-box">
                            <span class="promo-code-text"><?php echo htmlspecialchars($_SESSION['promo_code']); ?> Applied</span>
                            <?php if(!$is_auto_applied): ?>
                            <button type="button" id="remove_promo_btn"
                                    style="padding: 3px 8px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px; margin-left: 10px;">
                                Remove
                            </button>
                            <?php else: ?>
                            <span style="color: #6c757d; font-size: 12px; margin-left: 10px; font-style: italic;">(Auto-applied)</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <div id="promo-message"><?php echo $promo_message; ?></div>
                    </div>

                    <div id="discount-section" style="display: none;">
                        <div class="promo-applied-box">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span>Discount:</span>
                                <span id="discount-amount" style="color: #28a745; font-weight: bold;">- ₹0</span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span>Final Amount:</span>
                                <span id="final-amount" style="font-weight: bold;">₹6018</span>
                            </div>
                            <button type="button" id="remove_promo_btn_discount"
                                    style="padding: 4px 8px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px; margin-top: 8px;">
                                Remove Promo
                            </button>
                        </div>
                    </div>

                    <div class="terms-wrap">
                        <input type="checkbox" id="terms_agree" name="terms_agree">
                        <label for="terms_agree">
                            <?php echo $franchise_agreement_label_html; ?>
                        </label>
                    </div>

                    <input type="hidden" name="selected_plan" id="selected_plan_hidden" value="<?php echo htmlspecialchars($default_franchise_plan, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="plan_total_with_gst" id="plan_total_with_gst_hidden" value="30000">
                    <input type="hidden" name="amount" value="<?php echo $final_total; ?>">
                    <input type="hidden" name="original_amount" value="30000">
                    <input type="hidden" name="discount_amount" id="discount_amount_hidden" value="<?php echo $promo_discount; ?>">
                    <input type="hidden" name="subtotal_amount" id="subtotal_amount_hidden" value="<?php echo $subtotal; ?>">
                    <input type="hidden" name="cgst_amount" id="cgst_amount_hidden" value="<?php echo $cgst_amount; ?>">
                    <input type="hidden" name="sgst_amount" id="sgst_amount_hidden" value="<?php echo $sgst_amount; ?>">
                    <input type="hidden" name="igst_amount" id="igst_amount_hidden" value="<?php echo $igst_amount; ?>">
                    <input type="hidden" name="final_total" id="final_total_hidden" value="<?php echo $final_total; ?>">
                    <input type="hidden" name="promo_code" id="promo_code_hidden" value="<?php echo isset($_SESSION['promo_code']) ? $_SESSION['promo_code'] : ''; ?>">
                    <input type="hidden" name="promo_discount" id="promo_discount_hidden" value="<?php echo $promo_discount; ?>">
                    <input type="hidden" name="service_type" value="franchise_registration">
                    <input type="hidden" name="auto_start_payment" value="1">

                    <button type="submit" class="proceed-btn">
                        <i class="fa fa-lock"></i> PROCEED TO PAY
                    </button>
 
                </form>
            </div>
        </div>
    </div>

    <div class="payment-footer">
        <div class="trust-badges">
            <div class="trust-badge"><i class="fa fa-shield"></i> 100% Secure Payments</div>
            <div class="trust-badge"><i class="fa fa-lock"></i> SSL Encrypted</div>
            <div class="trust-badge"><i class="fa fa-shield"></i> Your Data is Safe</div>
        </div>
        <p class="payment-copyright">© <?php echo date('Y'); ?> MiniWebsite.in | All rights reserved.</p>
    </div>
</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-fill form fields on load
        <?php if ($user_data): ?>
        const form = document.getElementById('franchisePaymentForm');
        if (form) {
            <?php if (!empty($user_data['gst_number'])): ?>
            form.querySelector('input[name="gst_number"]').value = '<?php echo addslashes($user_data['gst_number']); ?>';
            <?php endif; ?>
            
            <?php if (!empty($user_data['name'])): ?>
            form.querySelector('input[name="name"]').value = '<?php echo addslashes($user_data['name']); ?>';
            <?php endif; ?>
            
            <?php if (!empty($user_data['email'])): ?>
            const emailInput = form.querySelector('input[name="email"]');
            emailInput.value = '<?php echo addslashes($user_data['email']); ?>';
            emailInput.setAttribute('readonly', true);
            <?php endif; ?>
            
            <?php if (!empty($user_data['contact'])): ?>
            form.querySelector('input[name="contact"]').value = '<?php echo addslashes($user_data['contact']); ?>';
            <?php endif; ?>
            
            <?php if (!empty($user_data['address'])): ?>
            form.querySelector('input[name="address"]').value = '<?php echo addslashes($user_data['address']); ?>';
            <?php endif; ?>
            
            <?php if (!empty($user_data['state'])): ?>
            form.querySelector('input[name="state"]').value = '<?php echo addslashes($user_data['state']); ?>';
            <?php endif; ?>
            
            <?php if (!empty($user_data['city'])): ?>
            form.querySelector('input[name="city"]').value = '<?php echo addslashes($user_data['city']); ?>';
            <?php endif; ?>
            
            <?php if (!empty($user_data['pincode'])): ?>
            form.querySelector('input[name="pincode"]').value = '<?php echo addslashes($user_data['pincode']); ?>';
            <?php endif; ?>
        }
        <?php elseif (!empty($prefill_email)): ?>
        // If no user data found, at least fill the email
        document.querySelector('input[name="email"]').value = '<?php echo $prefill_email; ?>';
        <?php endif; ?>

        // Initialize promo code functionality
        initializePromoCode();
        
        // Initial calculation update
        updateAmountDisplay();
        
        // Check for updated deals on page load and periodically
        checkForUpdatedDeals();
        
        // Set up periodic check for updated deals every 30 seconds
        var dealCheckInterval = setInterval(function() {
            checkForUpdatedDeals();
        }, 30000); // Check every 30 seconds
    });
    
    // Function to check for updated deals
    function checkForUpdatedDeals() {
        var email = '<?php echo $prefill_email; ?>';
        
        if (email) {
            var formData = new FormData();
            formData.append('action', 'check_updated_deals');
            formData.append('email', email);
            formData.append('service_type', 'franchise_registration');
            
            fetch('panel/login/payment_page/check_updated_deals_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.deal_updated) {
                    console.log('Deal updated, reloading page...');
                    // Reload the page to show the updated deal
                    location.reload();
                }
            })
            .catch(error => {
                console.log('No deal updates found or error checking deals');
            });
        }
    }
    
         // Promo code functionality
     function initializePromoCode() {
const planConfig = {
    starter: { label: 'Starter Franchise Plan', baseAmount: 6000, totalWithGst: 6000 },
    full: { label: 'Full Franchise Plan', baseAmount: 30000, totalWithGst: 30000 }
};
let selectedPlan = '<?php echo htmlspecialchars($default_franchise_plan, ENT_QUOTES, 'UTF-8'); ?>';
let originalAmount = planConfig[selectedPlan].baseAmount;
        let currentDiscount = <?php echo $promo_discount; ?>; // Initialize with auto-applied discount
        let currentPromoCode = '<?php echo isset($_SESSION['promo_code']) ? $_SESSION['promo_code'] : ''; ?>'; // Initialize with auto-applied promo code
        
        // Apply promo code function
        function applyPromoCode() {
            const promoCode = document.getElementById('promo_code_input').value.trim();
            const applyBtn = document.getElementById('apply_promo_btn');
            const messageDiv = document.getElementById('promo-message');
            
            if (!promoCode) {
                showMessage('Please enter a promo code', 'error');
                return;
            }
            
            // Disable button and show loading
            applyBtn.disabled = true;
            applyBtn.textContent = 'Applying...';
            showMessage('Applying promo code...', 'info');
            
            // Create form data
            const formData = new FormData();
            formData.append('action', 'apply_promo');
            formData.append('promo_code', promoCode);
            formData.append('original_amount', originalAmount);
            formData.append('service_type', 'franchise_registration');
            formData.append('selected_plan', selectedPlan);
            
            // Send AJAX request
            
            fetch('payment/apply_promo_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const discountAmount = parseFloat(data.discount_amount) || 0;
                    const originalAmount = parseFloat(planConfig[selectedPlan].baseAmount) || 0;
                    
                    // Validate that discount amount is not greater than original amount
                    if (discountAmount > originalAmount) {
                        showMessage('Error: Discount amount (₹' + discountAmount + ') cannot be greater than original amount (₹' + originalAmount + ')', 'error');
                        return;
                    }
                    
                    currentDiscount = discountAmount;
                    currentPromoCode = promoCode;
                    updateAmountDisplay();
                    showMessage('Promo code applied successfully! Discount: ₹' + discountAmount.toFixed(2), 'success');
                    document.getElementById('promo-section').style.display = 'none';
                    document.getElementById('discount-section').style.display = 'block';
                    
                    // Update hidden fields
                    document.getElementById('promo_code_hidden').value = promoCode;
                    document.getElementById('promo_discount_hidden').value = currentDiscount;
                    
                    // Update amount display which will recalculate GST properly
                    updateAmountDisplay();
                    
                    // Get the final amount from the updated display
                    const finalAmount = document.getElementById('final_total_hidden').value;
                    document.querySelector('input[name="amount"]').value = finalAmount;
                } else {
                    showMessage(data.message || 'Invalid promo code', 'error');
                }
            })
            .catch(error => {
                showMessage('Error applying promo code. Please try again.', 'error');
            })
            .finally(() => {
                applyBtn.disabled = false;
                applyBtn.textContent = 'Apply';
            });
        }
        
        // Remove promo code function
        function removePromoCode() {
            // Only allow removal if it's not auto-applied
            <?php if($is_auto_applied): ?>
            showMessage('Auto-applied promo codes cannot be removed', 'error');
            return;
            <?php endif; ?>
            
            currentDiscount = 0;
            currentPromoCode = '';
            showMessage('Promo code removed', 'info');
            document.getElementById('promo-section').style.display = 'block';
            document.getElementById('discount-section').style.display = 'none';
            document.getElementById('promo_code_input').value = '';
            
            // Update hidden fields
            document.getElementById('promo_code_hidden').value = '';
            document.getElementById('promo_discount_hidden').value = '0';
            document.getElementById('discount_amount_hidden').value = '0';

            // Recalculate totals from active plan + current GST rules
            updateAmountDisplay();
        }
        
        // Update amount display
        function updateAmountDisplay() {
            // Ensure currentDiscount is a number
            const discountValue = parseFloat(currentDiscount) || 0;
            const subtotal = originalAmount - discountValue;
            
            // Get GST number and state to determine interstate/intrastate
            const gstNumber = document.querySelector('input[name="gst_number"]').value.trim();
            const state = document.querySelector('input[name="state"]').value.trim().toLowerCase();
            const companyStateCode = '06'; // Haryana
            
            let isInterstate = false;
            let cgst = 0, sgst = 0, igst = 0;
            
            // Determine if interstate transaction
            if (gstNumber && gstNumber.length === 15) {
                const customerStateCode = gstNumber.substring(0, 2);
                isInterstate = (customerStateCode !== companyStateCode);
            } else if (state) {
                isInterstate = (state !== 'haryana');
            }

            // Calculate GST based on interstate/intrastate
            if (isInterstate) {
                igst = Math.round((subtotal * 0.18) * 100) / 100;
            } else {
                cgst = Math.round((subtotal * 0.09) * 100) / 100;
                sgst = Math.round((subtotal * 0.09) * 100) / 100;
            }

            const finalAmount = Math.round((subtotal + cgst + sgst + igst) * 100) / 100;
            
            // Update the calculation display (table structure - only update the right column values)
            document.querySelectorAll('.calculation-display .original-price')[1].textContent = '₹ ' + originalAmount.toFixed(2);
            document.querySelectorAll('.calculation-display .discount')[1].textContent = '₹ ' + discountValue.toFixed(2);
            document.querySelectorAll('.calculation-display .subtotal')[1].textContent = '₹ ' + subtotal.toFixed(2);
            
            if (isInterstate) {
                document.querySelectorAll('.calculation-display .cgst')[1].textContent = '₹ 0.00';
                document.querySelectorAll('.calculation-display .sgst')[1].textContent = '₹ 0.00';
                document.querySelectorAll('.calculation-display .igst')[1].textContent = '₹ ' + igst.toFixed(2);
            } else {
                document.querySelectorAll('.calculation-display .cgst')[1].textContent = '₹ ' + cgst.toFixed(2);
                document.querySelectorAll('.calculation-display .sgst')[1].textContent = '₹ ' + sgst.toFixed(2);
                document.querySelectorAll('.calculation-display .igst')[1].textContent = '₹ 0.00';
            }
            
            document.querySelectorAll('.calculation-display .final-total')[1].textContent = '₹ ' + finalAmount.toFixed(2);
            
            // Update the discount section
            document.getElementById('discount-amount').textContent = '- ₹' + discountValue.toFixed(2);
            document.getElementById('final-amount').textContent = '₹' + finalAmount.toFixed(2);
            
            // Update all hidden fields for database storage
            document.getElementById('discount_amount_hidden').value = discountValue;
            document.getElementById('subtotal_amount_hidden').value = subtotal;
            document.getElementById('cgst_amount_hidden').value = cgst;
            document.getElementById('sgst_amount_hidden').value = sgst;
            document.getElementById('igst_amount_hidden').value = igst;
            document.getElementById('final_total_hidden').value = finalAmount;
            document.getElementById('plan_total_with_gst_hidden').value = finalAmount;
            document.getElementById('selected_plan_hidden').value = selectedPlan;
            document.querySelector('input[name="original_amount"]').value = originalAmount;
            document.querySelector('input[name="amount"]').value = finalAmount;
        }

        function updatePlanCardsUi() {
            const starterCard = document.getElementById('starterPlanCard');
            const fullCard = document.getElementById('fullPlanCard');

            if (!starterCard || !fullCard) return;

            starterCard.classList.toggle('active', selectedPlan === 'starter');
            fullCard.classList.toggle('active', selectedPlan === 'full');
        }

        function selectPlan(planKey) {
            if (!planConfig[planKey]) return;

            selectedPlan = planKey;
            originalAmount = planConfig[selectedPlan].baseAmount;

            // Starter plan does not support higher old discounts from full plan
            if (currentDiscount > originalAmount) {
                currentDiscount = 0;
                currentPromoCode = '';
                document.getElementById('promo_code_hidden').value = '';
                document.getElementById('promo_discount_hidden').value = '0';
            }

            updatePlanCardsUi();
            updateAmountDisplay();
        }
        
        // Show message function
        function showMessage(message, type) {
            const messageDiv = document.getElementById('promo-message');
            if (messageDiv) {
                messageDiv.textContent = message;
                messageDiv.className = 'promo-message ' + type;
            }
            
            // Auto-hide success messages after 3 seconds
            if (type === 'success') {
                setTimeout(() => {
                    messageDiv.textContent = '';
                    messageDiv.className = 'promo-message';
                }, 3000);
            }
        }
        
        // Add event listeners
        const applyBtn = document.getElementById('apply_promo_btn');
        const promoInput = document.getElementById('promo_code_input');
        
        if (applyBtn) {
            applyBtn.addEventListener('click', applyPromoCode);
        }
        
        document.querySelectorAll('#remove_promo_btn, #remove_promo_btn_discount').forEach(function(btn) {
            btn.addEventListener('click', removePromoCode);
        });
        
        if (promoInput) {
            promoInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    applyPromoCode();
                }
            });
        }

        document.querySelectorAll('.plan-select-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const plan = this.getAttribute('data-plan');
                selectPlan(plan);
            });
        });

        document.querySelectorAll('.plan-card').forEach(function(card) {
            card.addEventListener('click', function(e) {
                if (e.target.classList.contains('plan-select-btn')) return;
                const plan = this.getAttribute('data-plan');
                selectPlan(plan);
            });
        });
        
        // Add event listeners to GST number and state fields for automatic calculation update
        const gstInput = document.querySelector('input[name="gst_number"]');
        const stateInput = document.querySelector('input[name="state"]');
        
        if (gstInput) {
            // Update calculation on every keystroke, paste, and blur
            gstInput.addEventListener('input', updateAmountDisplay);
            gstInput.addEventListener('keyup', updateAmountDisplay);
            gstInput.addEventListener('paste', function() {
                // Wait for paste to complete, then update
                setTimeout(updateAmountDisplay, 10);
            });
            gstInput.addEventListener('blur', updateAmountDisplay);
            gstInput.addEventListener('change', updateAmountDisplay);
        }
        
        if (stateInput) {
            stateInput.addEventListener('input', updateAmountDisplay);
            stateInput.addEventListener('blur', updateAmountDisplay);
            stateInput.addEventListener('change', updateAmountDisplay);
        }

        updatePlanCardsUi();
    }
</script>
<form action="payment/verify_miniwebsite.php" method="POST" name="franchiseRazorpayForm" style="display:none;">
    <input type="hidden" name="razorpay_payment_id" id="franchise_razorpay_payment_id">
    <input type="hidden" name="razorpay_signature" id="franchise_razorpay_signature">
    <input type="hidden" name="razorpay_order_id" id="franchise_razorpay_order_id">
    <input type="hidden" name="shopping_order_id" id="franchise_shopping_order_id" value="<?php echo 'FRAN' . date('dmYHis'); ?>">
</form>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('franchisePaymentForm');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        var termsCheckbox = document.getElementById('terms_agree');
        if (termsCheckbox && !termsCheckbox.checked) {
            alert('Please agree to the Terms & Conditions before proceeding.');
            return;
        }

        var payBtn = form.querySelector('button[type="submit"]');
        if (!payBtn) return;

        payBtn.disabled = true;
        payBtn.textContent = 'Processing...';

        // Step 1: initialize payment/franchise session (same as redirect flow)
        var initData = new FormData(form);
        fetch('payment/pay.php', {
            method: 'POST',
            body: initData,
            credentials: 'same-origin'
        })
        .then(function() {
            // Step 2: persist billing details + server-side tax recomputation
            var billingData = new FormData();
            billingData.append('card_id', '');
            billingData.append('gst_number', form.querySelector('input[name="gst_number"]').value || '');
            billingData.append('gst_name', form.querySelector('input[name="name"]').value || '');
            billingData.append('gst_email', form.querySelector('input[name="email"]').value || '');
            billingData.append('gst_contact', form.querySelector('input[name="contact"]').value || '');
            billingData.append('gst_address', form.querySelector('input[name="address"]').value || '');
            billingData.append('gst_state', form.querySelector('input[name="state"]').value || '');
            billingData.append('gst_city', form.querySelector('input[name="city"]').value || '');
            billingData.append('gst_pincode', form.querySelector('input[name="pincode"]').value || '');
            billingData.append('plan_amount', form.querySelector('input[name="original_amount"]').value || '30000');
            return fetch('payment/save_billing_details.php', {
                method: 'POST',
                body: billingData,
                credentials: 'same-origin'
            });
        })
        .then(function(response) { return response.json(); })
        .then(function(saveResult) {
            if (!saveResult.success) {
                throw new Error(saveResult.message || 'Unable to save billing details');
            }

            // Keep hidden totals synced with server
            if (typeof saveResult.final_amount !== 'undefined') {
                var finalAmountInput = form.querySelector('#final_total_hidden');
                var amountInput = form.querySelector('input[name="amount"]');
                if (finalAmountInput) finalAmountInput.value = saveResult.final_amount;
                if (amountInput) amountInput.value = saveResult.final_amount;
            }

            // Step 3: create Razorpay order
            var orderData = new FormData();
            orderData.append('amount', form.querySelector('#final_total_hidden').value || form.querySelector('input[name="amount"]').value || '0');
            return fetch('payment/create_razorpay_order.php', {
                method: 'POST',
                body: orderData,
                credentials: 'same-origin'
            });
        })
        .then(function(response) { return response.json(); })
        .then(function(orderResult) {
            if (!orderResult.success) {
                throw new Error(orderResult.message || 'Unable to create payment order');
            }

            var options = {
                key: 'rzp_live_xU57a1JhH7To1G',
                amount: Math.round((parseFloat(orderResult.amount) || 0) * 100),
                name: 'KIROVA SOLUTIONS LLP',
                description: 'Franchise Registration',
                order_id: orderResult.order_id,
                prefill: {
                    name: form.querySelector('input[name="name"]').value || '',
                    email: form.querySelector('input[name="email"]').value || '',
                    contact: form.querySelector('input[name="contact"]').value || ''
                },
                theme: { color: '#002169' },
                handler: function (response) {
                    document.getElementById('franchise_razorpay_payment_id').value = response.razorpay_payment_id;
                    document.getElementById('franchise_razorpay_signature').value = response.razorpay_signature;
                    document.getElementById('franchise_razorpay_order_id').value = orderResult.order_id;
                    document.getElementById('franchise_shopping_order_id').value = 'FRAN' + Date.now();
                    document.forms['franchiseRazorpayForm'].submit();
                },
                modal: {
                    ondismiss: function() {
                        payBtn.disabled = false;
                        payBtn.innerHTML = '<i class="fa fa-lock"></i> PROCEED TO PAY';
                    }
                }
            };

            var rzp = new Razorpay(options);
            rzp.on('payment.failed', function (response) {
                alert('Payment failed: ' + (response.error && response.error.description ? response.error.description : 'Please try again.'));
                payBtn.disabled = false;
                payBtn.innerHTML = '<i class="fa fa-lock"></i> PROCEED TO PAY';
            });
            rzp.open();
        })
        .catch(function(error) {
            alert(error && error.message ? error.message : 'Unable to initiate payment. Please try again.');
            payBtn.disabled = false;
            payBtn.innerHTML = '<i class="fa fa-lock"></i> PROCEED TO PAY';
        });
    });
});
</script>
    </div>
</body>
</html>



