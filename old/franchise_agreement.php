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
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.4;
          
            
        }

        .container {
           
            margin: auto;
            
            padding: 20px;
            
            
        }

        input[readonly] {
            background-color: #f8f9fa !important;
            border-color: #dee2e6 !important;
            cursor: not-allowed !important;
            opacity: 0.8;
        }

        /* Promo code message styles */
        .promo-message {
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 13px;
            margin-top: 5px;
        }
        
        .promo-message.success {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .promo-message.error {
            background: wheat;
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        
        .promo-message.info {
            background: rgba(23, 162, 184, 0.2);
            color: #17a2b8;
            border: 1px solid rgba(23, 162, 184, 0.3);
        }
        
        /* Button hover effects */
        #apply_promo_btn:hover {
            background: #218838 !important;
        }
        
        #remove_promo_btn:hover {
            background: #c82333 !important;
        }
        
        /* Input focus effect */
        #promo_code_input:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(255, 193, 7, 0.3);
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
        

<!-- Add Agreement Button -->
<div style="text-align: center; margin: 30px 0;">
    <button id="agreeButton" style="background: #002169; color: white; padding: 15px 30px; border: none; border-radius: 5px; font-size: 16px; font-weight: bold; cursor: pointer;">
        AGREE & CONTINUE
    </button>
    <p style="font-size: 14px; color: #666; margin-top: 10px;">
    Press "Agree & Continue", if you want to proceed with the payment.
    </p>
</div>

<!-- Payment Form (Initially Hidden) -->
<div id="paymentSection" style="display: none; margin-top: 40px; padding: 30px 20px; background: #f8f9fa; border-radius: 15px;">
    <h3 style="text-align: center; color: #002169; margin-bottom: 15px; font-size: 24px; font-weight: 600;">Franchise Registration Payment</h3>    
    <div style="max-width: 450px; margin: 0 auto; background: #002169; padding: 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h4 style="color: white; text-align: center; margin-bottom: 10px; font-size: 20px; font-weight: 600;">Billing/GST Details</h4>
        
        <!-- Add the line below header -->
        <div style="width: 35%; height: 2px; background: #ffc107; margin: 0 auto 25px auto; border-radius: 1px;"></div>
        
        <form id="franchisePaymentForm" action="payment_page/pay.php" method="POST">
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
            
                         <!-- Promo Code Section -->
             <div style="margin: 20px 0; color: white; background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px;">
                <div class="calculation-display" style="margin-bottom: 10px;">
                    <table style="width: 100%; color: white; font-size: 16px;">
                        <tr>
                            <td class="original-price" style="padding: 5px 0; text-align: left; width: 50%;"><strong>Original Price:</strong></td>
                            <td class="original-price" style="text-align: right; padding: 5px 0; width: 50%;"><strong>₹ <?php echo number_format(30000, 2); ?></strong></td>
                        </tr>
                        <tr>
                            <td class="discount" style="padding: 5px 0; text-align: left; width: 50%;"><strong>Discount:</strong></td>
                            <td class="discount" style="text-align: right; padding: 5px 0; width: 50%;"><strong>₹ <?php echo number_format($promo_discount, 2); ?></strong></td>
                        </tr>
                        <tr>
                            <td class="subtotal" style="padding: 5px 0; text-align: left; width: 50%;"><strong>Sub Total:</strong></td>
                            <td class="subtotal" style="text-align: right; padding: 5px 0; width: 50%;"><strong>₹ <?php echo number_format(30000 - $promo_discount, 2); ?></strong></td>
                        </tr>
                        <?php
                        // Calculate proper GST amounts with decimal precision
                        $subtotal = 30000 - $promo_discount;
                        
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
                        ?>
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
                
                <div id="promo-section" style="margin-top: 15px;">
                    <?php if(!$promo_applied): ?>
                        <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                            <input type="text" id="promo_code_input" placeholder="Enter promo code" 
                                   style="flex: 1; padding: 8px 12px; border: none; border-radius: 5px; font-size: 14px;" maxlength="20">
                            <button type="button" id="apply_promo_btn" 
                                    style="padding: 8px 15px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px;">
                                Apply
                            </button>
                        </div>
                    <?php else: ?>
                        <div style="margin-bottom: 10px; padding: 8px; background: rgba(40, 167, 69, 0.2); border-radius: 5px;">
                            <span style="color: #28a745; font-weight: bold;"><?php echo $_SESSION['promo_code']; ?> Applied</span>
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
                    <div id="promo-message" style="font-size: 13px; margin-top: 5px;"><?php echo $promo_message; ?></div>
                </div>
                
                <div id="discount-section" style="display: none; margin-top: 10px; padding: 8px; background: rgba(40, 167, 69, 0.2); border-radius: 5px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>Discount:</span>
                        <span id="discount-amount" style="color: #28a745; font-weight: bold;">- ₹0</span>
                    </div>
                                         <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                         <span>Final Amount:</span>
                         <span id="final-amount" style="font-weight: bold;">₹6018</span>
                     </div>
                    <button type="button" id="remove_promo_btn" 
                            style="padding: 4px 8px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px; margin-top: 5px;">
                        Remove Promo
                    </button>
                </div>
            </div>
            
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
            
            <button type="submit" style="width: 100%; background: #ffc107; color: #000; padding: 15px; border: none; border-radius: 8px; font-weight: bold; font-size: 16px; cursor: pointer; transition: all 0.3s ease; margin-top: 10px;">
                PROCEED TO PAY
            </button>
        </form>
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
    
    // Scroll to payment section
    document.getElementById('paymentSection').scrollIntoView({ behavior: 'smooth' });
});

    // Also auto-fill on page load if payment section is already visible
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($user_data): ?>
        const form = document.getElementById('franchisePaymentForm');
        if (form) {
            <?php if (!empty($user_data['email'])): ?>
            const emailInput = form.querySelector('input[name="email"]');
            if (emailInput) {
                emailInput.value = '<?php echo addslashes($user_data['email']); ?>';
            }
            <?php endif; ?>
        }
        <?php elseif (!empty($prefill_email)): ?>
        const emailInput = document.querySelector('input[name="email"]');
        if (emailInput) {
            emailInput.value = '<?php echo $prefill_email; ?>';
        }
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
const originalAmount = 30000; // Rs 30000 + 18% GST
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
            
            // Send AJAX request
            
            fetch('panel/login/payment_page/apply_promo_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const discountAmount = parseFloat(data.discount_amount) || 0;
                    const originalAmount = 30000;
                    
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
            updateAmountDisplay();
            showMessage('Promo code removed', 'info');
            document.getElementById('promo-section').style.display = 'block';
            document.getElementById('discount-section').style.display = 'none';
            document.getElementById('promo_code_input').value = '';
            
            // Update hidden fields
            document.getElementById('promo_code_hidden').value = '';
            document.getElementById('promo_discount_hidden').value = '0';
            document.getElementById('discount_amount_hidden').value = '0';
            document.getElementById('subtotal_amount_hidden').value = '30000';
            document.getElementById('cgst_amount_hidden').value = '2700';
            document.getElementById('sgst_amount_hidden').value = '2700';
            document.getElementById('igst_amount_hidden').value = '0';
            document.getElementById('final_total_hidden').value = '35400';
            document.querySelector('input[name="amount"]').value = 35400; // Reset to original total with GST
        }
        
        // Update amount display
        function updateAmountDisplay() {
            // Ensure currentDiscount is a number
            const discountValue = parseFloat(currentDiscount) || 0;
            const subtotal = 30000 - discountValue;
            
            // Get GST number and state to determine interstate/intrastate
            const gstNumber = document.querySelector('input[name="gst_number"]').value.trim();
            const state = document.querySelector('input[name="state"]').value.trim().toLowerCase();
            const companyStateCode = '06'; // Haryana state code
            
            // Show calculation is updating
            
            let isInterstate = false;
            let cgst = 0, sgst = 0, igst = 0;
            
            // Determine if interstate transaction
            if (gstNumber && gstNumber.length === 15 && /^\d{15}$/.test(gstNumber)) {
                // Extract state code from GST number (positions 1-2)
                const customerStateCode = gstNumber.substring(0, 2);
                isInterstate = (customerStateCode !== companyStateCode);
                console.log('GST Number detected:', gstNumber, 'State Code:', customerStateCode, 'Is Interstate:', isInterstate);
            } else {
                // GST not filled or invalid: use state field instead
                isInterstate = (state !== 'haryana');
                console.log('Using state field:', state, 'Is Interstate:', isInterstate);
            }
            
            // Calculate GST based on interstate/intrastate
            if (isInterstate) {
                // IGST (18%) for interstate
                igst = Math.round((subtotal * 0.18) * 100) / 100;
                cgst = 0;
                sgst = 0;
            } else {
                // CGST + SGST (9% each) for intrastate - split equally
                cgst = Math.round((subtotal * 0.09) * 100) / 100;
                sgst = Math.round((subtotal * 0.09) * 100) / 100;
                igst = 0;
            }
            
            const finalAmount = Math.round((subtotal + cgst + sgst + igst) * 100) / 100;
            
            // Update the calculation display (table structure - only update the right column values)
            document.querySelectorAll('.calculation-display .discount')[1].textContent = '₹ ' + discountValue.toFixed(2);
            document.querySelectorAll('.calculation-display .subtotal')[1].textContent = '₹ ' + subtotal.toFixed(2);
            
            // Show only relevant GST components
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
        const removeBtn = document.getElementById('remove_promo_btn');
        const promoInput = document.getElementById('promo_code_input');
        
        if (applyBtn) {
            applyBtn.addEventListener('click', applyPromoCode);
        }
        
        if (removeBtn) {
            removeBtn.addEventListener('click', removePromoCode);
        }
        
        if (promoInput) {
            promoInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    applyPromoCode();
                }
            });
        }
        
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
    }
</script>
    </div>
</body>
</html>
