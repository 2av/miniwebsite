<?php
// Start session at the very beginning, before any HTML output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get email from URL parameter
$prefill_email = isset($_GET['email']) ? htmlspecialchars($_GET['email']) : '';

// Database connection and fetch user details
$user_data = null;
$connect = null;

// Database connection
$db_host = "p004.bom1.mysecurecloudhost.com";
$db_user = "wwwmoody_miniweb_vcard";
$db_pass = "miniweb_vcard";
$db_name = "miniweb_vcard";

try {
    $connect = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    // Get franchisee distributor agreement content
    $content_query = mysqli_query($connect, "SELECT * FROM content_management WHERE content_type='franchisee_distributer' AND is_active=1");
    
    
    if(mysqli_num_rows($content_query) > 0) {
        $content = mysqli_fetch_array($content_query);
        $page_title = $content['title'];
        $page_content = $content['content'];
        $meta_description = $content['meta_description'];
        $meta_keywords = $content['meta_keywords'];
    } else {
        // Fallback to default content
        $page_title = "Franchisee Distributor Agreement";
        $page_content = "<p>Content not available. Please contact administrator.</p>";
        $meta_description = "Franchisee Distributor Agreement for MiniWebsite";
        $meta_keywords = "franchisee, distributor, agreement, partnership";
    }
    
    // Get user data if email is provided
    if (!empty($prefill_email)) {
        $query = "SELECT   user_email  
              FROM customer_login WHERE user_email = '" . mysqli_real_escape_string($connect, $prefill_email) . "'";
       
       
       $result = mysqli_query($connect, $query);
        
          
        if ($result && mysqli_num_rows($result) > 0) {
            $user_data = mysqli_fetch_assoc($result);
            error_log("User found: " . print_r($user_data, true));
        } else {
            error_log("User NOT found in customer_login for email: " . $prefill_email);
        }
    }
    
    // Get joining deal amount for this user
    $joining_deal_amount = 0;
    $joining_deal_name = '';
    $joining_deal_code = '';
    $joining_deal_data = null;
    $is_upgrade = false;
    $upgrade_details = '';
    
    if (!empty($prefill_email)) {
        $joining_deal_query = "SELECT ujdm.*, jd.deal_name, jd.deal_code, jd.total_fees 
                              FROM user_joining_deals_mapping ujdm 
                              JOIN joining_deals jd ON ujdm.joining_deal_id = jd.id 
                              WHERE ujdm.user_email = '" . mysqli_real_escape_string($connect, $prefill_email) . "' 
                              AND ujdm.mapping_status = 'ACTIVE' 
                              AND ujdm.payment_status = 'PENDING'
                              ORDER BY ujdm.created_at DESC LIMIT 1";
        
        $joining_deal_result = mysqli_query($connect, $joining_deal_query);
        
        if ($joining_deal_result && mysqli_num_rows($joining_deal_result) > 0) {
            $joining_deal_data = mysqli_fetch_assoc($joining_deal_result);
            
            // Check if this is an upgrade (has notes about upgrade)
            if (strpos($joining_deal_data['notes'], 'Upgraded from') !== false) {
                $is_upgrade = true;
                $upgrade_details = $joining_deal_data['notes'];
                
                // For upgrades, use the amount_paid field which contains the remaining amount
                $joining_deal_amount = floatval($joining_deal_data['amount_paid']);
            } else {
                // For new deals, calculate base amount from total_fees
                $total_with_gst = (float)$joining_deal_data['total_fees'];
                $joining_deal_amount = round($total_with_gst / 1.18, 2); // Remove 18% GST to get base amount
            }
            
            $joining_deal_name = $joining_deal_data['deal_name'];
            $joining_deal_code = $joining_deal_data['deal_code'];
        }
    }
    
} catch (Exception $e) {
    // Handle database connection error silently
    $user_data = null;
    $joining_deal_amount = 0;
    $joining_deal_name = '';
    $joining_deal_code = '';
    $joining_deal_data = null;
    $page_title = "Franchisee Distributor Agreement";
    $page_content = "<p>Content not available. Please contact administrator.</p>";
    $meta_description = "Franchisee Distributor Agreement for MiniWebsite";
    $meta_keywords = "franchisee, distributor, agreement, partnership";
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
                    $original_amount = $joining_deal_amount; // Use actual joining deal amount
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
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: auto;
            padding: 20px;
        }

        input[readonly] {
            background-color: #f8f9fa !important;
            border-color: #dee2e6 !important;
            cursor: not-allowed !important;
            opacity: 0.8;
        }

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
        
        
        
        <?php if (!empty($prefill_email) && $joining_deal_amount == 0): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: center;">
                <strong>No Pending Payment Found</strong><br>
                You don't have any pending joining deal payments. Please contact support if you believe this is an error.
                <br><br>
                <small style="color: #666;">
                    Debug Info:<br>
                    Email: <?php echo htmlspecialchars($prefill_email); ?><br>
                    Joining Deal Amount: <?php echo $joining_deal_amount; ?><br>
                    Joining Deal Name: <?php echo htmlspecialchars($joining_deal_name); ?><br>
                    Joining Deal Code: <?php echo htmlspecialchars($joining_deal_code); ?><br>
                    User Data: <?php echo $user_data ? 'Found' : 'Not Found'; ?>
                </small>
            </div>
        <?php else: ?>
            <?php if ($is_upgrade): ?>
            <div style="background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: center;">
                <strong>🔄 Deal Upgrade Payment</strong><br>
                You're upgrading your joining deal. Please pay the remaining amount to complete the upgrade.
                <br><br>
                <small style="color: #666;">
                    <?php echo htmlspecialchars($upgrade_details); ?><br>
                    <strong>Note: Remaining amount already includes 18% GST</strong>
                </small>
            </div>
            <?php endif; ?>
            <!-- Add Agreement Button -->
            <div style="text-align: center; margin: 30px 0;">
                <button id="agreeButton" style="background: #002169; color: white; padding: 15px 30px; border: none; border-radius: 5px; font-size: 16px; font-weight: bold; cursor: pointer;">
                    AGREE & CONTINUE
                </button>
                <p style="font-size: 14px; color: #666; margin-top: 10px;">
                    Press "Agree & Continue", if you want to proceed with the payment.
                </p>
            </div>
        <?php endif; ?>

        <!-- Payment Form (Initially Hidden) -->
        <div id="paymentSection" style="display: none; margin-top: 40px; padding: 30px 20px; background: #f8f9fa; border-radius: 15px;">
           
    
            <div style="max-width: 450px; margin: 0 auto; background: #002169; padding: 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <h4 style="color: white; text-align: center; margin-bottom: 10px; font-size: 20px; font-weight: 600;">Billing/GST Details</h4>
                
                <!-- Add the line below header -->
                <div style="width: 35%; height: 2px; background: #ffc107; margin: 0 auto 25px auto; border-radius: 1px;"></div>
                
                <form id="franchisePaymentForm" action="payment_page/joining_deal_pay.php" method="POST">
                    <input type="text" name="gst_number" placeholder="Enter GST Number (Optional)" style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box; text-transform: uppercase;">
                    
                    <input type="text" name="name" placeholder="Name" required style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                    
                    <input type="email" name="email" placeholder="Email Address" 
                           value="<?php echo !empty($prefill_email) ? htmlspecialchars($prefill_email) : ''; ?>" 
                           <?php echo !empty($prefill_email) ? 'readonly' : ''; ?> 
                           required style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                    
                    <input type="tel" name="contact" placeholder="Contact Number" required style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                    
                    <input type="text" name="address" placeholder="Address" required style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                    
                    <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                        <input type="text" name="state" placeholder="State" required style="width: 50%; padding: 12px 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                        <input type="text" name="city" placeholder="City" required style="width: 50%; padding: 12px 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                    </div>
                    
                    <input type="text" name="pincode" placeholder="Pin Code" required style="width: 100%; padding: 12px 15px; margin-bottom: 25px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                    
                    <!-- Payment Summary Section -->
                    <div style="margin: 20px 0; color: white; background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px;">
                        <?php if ($is_upgrade): ?>
                        <div style="text-align: center; margin-bottom: 15px; padding: 10px; background: rgba(255,193,7,0.2); border-radius: 5px;">
                            <strong style="color: #ffc107;">🔄 Upgrade Payment</strong>
                        </div>
                        <?php endif; ?>
                        <?php
                        // Calculate proper GST amounts with decimal precision
                        if ($is_upgrade) {
                            // For upgrades, remaining amount already includes GST
                            // Calculate base amount by removing GST (18%)
                            $total_with_gst = $joining_deal_amount;
                            $subtotal = round($total_with_gst / 1.18, 2); // Remove 18% GST to get base amount
                            
                            // Determine if interstate transaction (for now, assume intrastate for franchise)
                            $is_interstate = false; // Franchise registration is typically intrastate
                            
                            if ($is_interstate) {
                                // IGST (18%) for interstate
                                $igst_amount = round($subtotal * 0.18, 2);
                                $cgst_amount = 0;
                                $sgst_amount = 0;
                            } else {
                                // CGST + SGST (9% each) for intrastate - split equally
                                $cgst_amount = round($subtotal * 0.09, 2);
                                $sgst_amount = round($subtotal * 0.09, 2);
                                $igst_amount = 0;
                            }
                            
                            $final_total = $total_with_gst; // Final total is the remaining amount (includes GST)
                        } else {
                            $subtotal = $joining_deal_amount;
                            
                            // Determine if interstate transaction (for now, assume intrastate for franchise)
                            $is_interstate = false; // Franchise registration is typically intrastate
                            
                            if ($is_interstate) {
                                // IGST (18%) for interstate
                                $igst_amount = round($subtotal * 0.18, 2);
                                $cgst_amount = 0;
                                $sgst_amount = 0;
                            } else {
                                // CGST + SGST (9% each) for intrastate - split equally
                                $cgst_amount = round($subtotal * 0.09, 2);
                                $sgst_amount = round($subtotal * 0.09, 2);
                                $igst_amount = 0;
                            }
                            
                            $final_total = round($subtotal + $cgst_amount + $sgst_amount + $igst_amount, 2);
                        }
                        ?>
                        <div class="calculation-display" style="margin-bottom: 10px;">
                            <table style="width: 100%; color: white; font-size: 16px;">
                                <?php if ($is_upgrade): ?>
                                <tr>
                                    <td style="padding: 5px 0; text-align: left; width: 50%;"><strong>Base Amount:</strong></td>
                                    <td style="text-align: right; padding: 5px 0; width: 50%;"><strong>₹ <?php echo number_format($subtotal, 2); ?></strong></td>
                                </tr>
                                <?php else: ?>
                                <tr>
                                    <td class="original-price" style="padding: 5px 0; text-align: left; width: 50%;"><strong>Amount:</strong></td>
                                    <td class="original-price" style="text-align: right; padding: 5px 0; width: 50%;"><strong>₹ <?php echo number_format($joining_deal_amount, 2); ?></strong></td>
                                </tr>
                                <?php endif; ?>
                                
                                <tr>
                                    <td class="cgst" style="padding: 5px 0; text-align: left; width: 50%;"><strong>CGST (9%):</strong></td>
                                    <td class="cgst" style="text-align: right; padding: 5px 0; width: 50%;"><strong>₹ <?php echo number_format($cgst_amount, 2); ?></strong></td>
                                </tr>
                                <tr>
                                    <td class="sgst" style="padding: 5px 0; text-align: left; width: 50%;"><strong>SGST (9%):</strong></td>
                                    <td class="sgst" style="text-align: right; padding: 5px 0; width: 50%;"><strong>₹ <?php echo number_format($sgst_amount, 2); ?></strong></td>
                                </tr>
                                <tr>
                                    <td class="igst" style="padding: 5px 0; text-align: left; width: 50%;"><strong>IGST (18%):</strong></td>
                                    <td class="igst" style="text-align: right; padding: 5px 0; width: 50%;"><strong>₹ <?php echo number_format($igst_amount, 2); ?></strong></td>
                                </tr>
                                
                                <tr>
                                    <td class="final-total" style="padding: 5px 0; border-top: 1px solid rgba(255,255,255,0.3); text-align: left;"><strong>Final Total:</strong></td>
                                    <td class="final-total" style="text-align: right; padding: 5px 0; border-top: 1px solid rgba(255,255,255,0.3);"><strong>₹ <?php echo number_format($final_total, 2); ?></strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <input type="hidden" name="amount" value="<?php echo $final_total; ?>">
                    <input type="hidden" name="original_amount" value="<?php echo $joining_deal_amount; ?>">
                    <input type="hidden" name="discount_amount" value="0">
                    <input type="hidden" name="subtotal_amount" value="<?php echo $subtotal; ?>">
                    <input type="hidden" name="cgst_amount" value="<?php echo $cgst_amount; ?>">
                    <input type="hidden" name="sgst_amount" value="<?php echo $sgst_amount; ?>">
                    <input type="hidden" name="igst_amount" value="<?php echo $igst_amount; ?>">
                    <input type="hidden" name="final_total" value="<?php echo $final_total; ?>">
                    <input type="hidden" name="promo_code" value="">
                    <input type="hidden" name="promo_discount" value="0">
                    <input type="hidden" name="service_type" value="franchise_registration">
                    <input type="hidden" name="joining_deal_id" value="<?php echo ($joining_deal_data && isset($joining_deal_data['joining_deal_id'])) ? $joining_deal_data['joining_deal_id'] : ''; ?>">
                    <input type="hidden" name="mapping_id" value="<?php echo ($joining_deal_data && isset($joining_deal_data['id'])) ? $joining_deal_data['id'] : ''; ?>">
                    
                    <button type="submit" style="width: 100%; background: #ffc107; color: #000; padding: 15px; border: none; border-radius: 8px; font-weight: bold; font-size: 16px; cursor: pointer; transition: all 0.3s ease; margin-top: 10px;">
                        PROCEED TO PAY
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('agreeButton').addEventListener('click', function() {
            // Hide the agreement button
            this.style.display = 'none';
            
            // Show the payment section
            document.getElementById('paymentSection').style.display = 'block';
            
            // Auto-fill form fields if user data is available
            <?php if ($user_data): ?>
            const form = document.getElementById('franchisePaymentForm');
            if (form) {
                <?php if (!empty($user_data['user_gst'])): ?>
                form.querySelector('input[name="gst_number"]').value = '<?php echo addslashes($user_data['user_gst']); ?>';
                <?php endif; ?>
                
                <?php if (!empty($user_data['user_name'])): ?>
                form.querySelector('input[name="name"]').value = '<?php echo addslashes($user_data['user_name']); ?>';
                <?php endif; ?>
                
                <?php if (!empty($user_data['user_email'])): ?>
                const emailInput = form.querySelector('input[name="email"]');
                emailInput.value = '<?php echo addslashes($user_data['user_email']); ?>';
                emailInput.setAttribute('readonly', true);
                <?php endif; ?>
                
                <?php if (!empty($user_data['user_contact'])): ?>
                form.querySelector('input[name="contact"]').value = '<?php echo addslashes($user_data['user_contact']); ?>';
                <?php endif; ?>
                
                <?php if (!empty($user_data['user_address'])): ?>
                form.querySelector('input[name="address"]').value = '<?php echo addslashes($user_data['user_address']); ?>';
                <?php endif; ?>
                
                <?php if (!empty($user_data['user_state'])): ?>
                form.querySelector('input[name="state"]').value = '<?php echo addslashes($user_data['user_state']); ?>';
                <?php endif; ?>
                
                <?php if (!empty($user_data['user_city'])): ?>
                form.querySelector('input[name="city"]').value = '<?php echo addslashes($user_data['user_city']); ?>';
                <?php endif; ?>
                
                <?php if (!empty($user_data['user_pincode'])): ?>
                form.querySelector('input[name="pincode"]').value = '<?php echo addslashes($user_data['user_pincode']); ?>';
                <?php endif; ?>
            }
            <?php elseif (!empty($prefill_email)): ?>
            // If no user data found, at least fill the email
            document.querySelector('input[name="email"]').value = '<?php echo $prefill_email; ?>';
            <?php endif; ?>
            
            // Scroll to payment section
            document.getElementById('paymentSection').scrollIntoView({ behavior: 'smooth' });
        });

        // Also auto-fill on page load if payment section is already visible
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($user_data): ?>
            const form = document.getElementById('franchisePaymentForm');
            if (form) {
                <?php if (!empty($user_data['user_email'])): ?>
                const emailInput = form.querySelector('input[name="email"]');
                if (emailInput) {
                    emailInput.value = '<?php echo addslashes($user_data['user_email']); ?>';
                }
                <?php endif; ?>
            }
            <?php elseif (!empty($prefill_email)): ?>
            const emailInput = document.querySelector('input[name="email"]');
            if (emailInput) {
                emailInput.value = '<?php echo $prefill_email; ?>';
            }
            <?php endif; ?>
            
            // No promo code functionality needed
        });
        
    </script>
</body>

</html>


