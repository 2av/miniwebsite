<?php
/**
 * Process Deal Upgrade (Simplified Version)
 * Handles upgrading a user from one joining deal to another
 * This version removes the problematic email_logs dependency
 */

// Prevent any HTML output that might interfere with JSON response
ob_start();

require('connect_ajax.php');

header('Content-Type: application/json');

try {
    $user_email = $_POST['user_email'] ?? '';
    $current_deal_code = $_POST['current_deal_code'] ?? '';
    $new_deal_code = $_POST['new_deal_code'] ?? '';
    
    if(empty($user_email) || empty($current_deal_code) || empty($new_deal_code)) {
        throw new Exception('Missing required parameters');
    }
    
    // Get both deals and validate hierarchy using database upgrade_order
    $deals_query = mysqli_query($connect, "SELECT deal_code, upgrade_order FROM joining_deals 
        WHERE deal_code IN ('" . mysqli_real_escape_string($connect, $current_deal_code) . "', '" . mysqli_real_escape_string($connect, $new_deal_code) . "') 
        AND is_active = 'YES'");
    
    if(!$deals_query || mysqli_num_rows($deals_query) != 2) {
        throw new Exception('One or both deals not found or inactive');
    }
    
    $deals = [];
    while($row = mysqli_fetch_array($deals_query)) {
        $deals[$row['deal_code']] = intval($row['upgrade_order']);
    }
    
    if(!isset($deals[$current_deal_code]) || !isset($deals[$new_deal_code])) {
        throw new Exception('Invalid deal codes');
    }
    
    if($deals[$new_deal_code] <= $deals[$current_deal_code]) {
        throw new Exception('Cannot downgrade deals. Only upgrades are allowed.');
    }
    
    // Start transaction
    mysqli_autocommit($connect, false);
    
    // Get current deal mapping
    $current_mapping_query = mysqli_query($connect, "SELECT ujdm.*, jd.deal_name, jd.total_fees, jd.mw_deal_id, jd.franchise_deal_id
        FROM user_joining_deals_mapping ujdm 
        JOIN joining_deals jd ON ujdm.joining_deal_id = jd.id 
        WHERE ujdm.user_email = '" . mysqli_real_escape_string($connect, $user_email) . "' 
        AND ujdm.mapping_status = 'ACTIVE' 
        AND jd.deal_code = '" . mysqli_real_escape_string($connect, $current_deal_code) . "'
        AND (ujdm.expiry_date IS NULL OR ujdm.expiry_date > NOW()) 
        ORDER BY ujdm.created_at DESC LIMIT 1");
    
    if(!$current_mapping_query || mysqli_num_rows($current_mapping_query) == 0) {
        throw new Exception('Current active deal mapping not found');
    }
    
    $current_mapping = mysqli_fetch_array($current_mapping_query);
    
    // Get current deal details to find its mapped deals
    $current_deal_query = mysqli_query($connect, "SELECT mw_deal_id, franchise_deal_id FROM joining_deals 
        WHERE deal_code = '" . mysqli_real_escape_string($connect, $current_deal_code) . "' 
        AND is_active = 'YES' LIMIT 1");
    
    $current_deal_mw_id = null;
    $current_deal_fr_id = null;
    if($current_deal_query && mysqli_num_rows($current_deal_query) > 0) {
        $current_deal_data = mysqli_fetch_array($current_deal_query);
        $current_deal_mw_id = !empty($current_deal_data['mw_deal_id']) ? intval($current_deal_data['mw_deal_id']) : null;
        $current_deal_fr_id = !empty($current_deal_data['franchise_deal_id']) ? intval($current_deal_data['franchise_deal_id']) : null;
    }
    
    // Get new deal details
    $new_deal_query = mysqli_query($connect, "SELECT * FROM joining_deals 
        WHERE deal_code = '" . mysqli_real_escape_string($connect, $new_deal_code) . "' 
        AND is_active = 'YES' LIMIT 1");
    
    if(!$new_deal_query || mysqli_num_rows($new_deal_query) == 0) {
        throw new Exception('New deal not found or inactive');
    }
    
    $new_deal = mysqli_fetch_array($new_deal_query);
    
    // Get admin email for mapping record
    $mapped_by = isset($_SESSION['admin_email']) ? $_SESSION['admin_email'] : 'admin';
    
    // Calculate dates - for upgrades, keep the same dates as current deal
    $new_start_date = $current_mapping['start_date']; // Keep same start date
    $new_expiry_date = $current_mapping['expiry_date']; // Keep same expiry date
    
    // Calculate payment amounts
    $current_deal_fees = floatval($current_mapping['total_fees'] ?? 0);
    $new_deal_fees = floatval($new_deal['total_fees']);
    
    // Calculate remaining amount to pay
    $remaining_amount = $new_deal_fees - $current_deal_fees;
    $requires_payment = ($remaining_amount > 0);
    $payment_status = $requires_payment ? 'PENDING' : 'PAID';
    
    // Create new mapping record
    $mapping_query = mysqli_query($connect, "INSERT INTO user_joining_deals_mapping 
        (user_email, joining_deal_id, deal_code, mapping_status, mapped_by, email_sent, email_sent_date, 
         start_date, expiry_date, payment_status, amount_paid, created_at, notes) 
        VALUES ('" . mysqli_real_escape_string($connect, $user_email) . "', 
        '" . intval($new_deal['id']) . "', 
        '" . mysqli_real_escape_string($connect, $new_deal_code) . "', 
        'ACTIVE', 
        '" . mysqli_real_escape_string($connect, $mapped_by) . "', 
        'NO', 
        NULL, 
        '$new_start_date', 
        '$new_expiry_date', 
        '$payment_status', 
        " . ($requires_payment ? $remaining_amount : 0) . ", 
        NOW(), 
        'Upgraded from " . mysqli_real_escape_string($connect, $current_deal_code) . " (Remaining: ₹" . number_format($remaining_amount, 2) . ")')");
    
    if(!$mapping_query) {
        throw new Exception('Failed to create new mapping record: ' . mysqli_error($connect));
    }
    
    $new_mapping_id = mysqli_insert_id($connect);
    
    // Handle deal mappings: Keep old deals until new ones are mapped
    $customer_email_esc = mysqli_real_escape_string($connect, $user_email);
    
    if ($requires_payment) {
        // Payment required: Keep old deals active, store new deal info in notes for later processing
        // Store old deal IDs and new deal IDs in notes for payment verification to process
        $deferred_notes = [];
        
        // Store old deal IDs to remove after payment
        if($current_deal_mw_id !== null && $current_deal_mw_id > 0) {
            $deferred_notes[] = 'remove_mw:' . $current_deal_mw_id;
        }
        if($current_deal_fr_id !== null && $current_deal_fr_id > 0) {
            $deferred_notes[] = 'remove_fr:' . $current_deal_fr_id;
        }
        
        // Store new deal IDs to map after payment
        if(!empty($new_deal['mw_deal_id']) && $new_deal['mw_deal_id'] > 0) {
            $deferred_notes[] = 'map_mw:' . intval($new_deal['mw_deal_id']);
        }
        if(!empty($new_deal['franchise_deal_id']) && $new_deal['franchise_deal_id'] > 0) {
            $deferred_notes[] = 'map_fr:' . intval($new_deal['franchise_deal_id']);
        }
        
        if (!empty($deferred_notes)) {
            $append_notes = ' | ' . implode(',', $deferred_notes);
            mysqli_query($connect, "UPDATE user_joining_deals_mapping SET notes = CONCAT(IFNULL(notes,''), '" . mysqli_real_escape_string($connect, $append_notes) . "') WHERE id = " . intval($new_mapping_id));
        }
    } else {
        // No payment required: Remove old deals first, then add new ones immediately
        // Remove old MW deal mapping if it exists and is different from new one
        if($current_deal_mw_id !== null && $current_deal_mw_id > 0) {
            // Only remove if new deal has different MW mapping
            if(empty($new_deal['mw_deal_id']) || intval($new_deal['mw_deal_id']) != $current_deal_mw_id) {
                $old_mw_remove = mysqli_query($connect, "DELETE FROM deal_customer_mapping 
                    WHERE customer_email='".$customer_email_esc."' AND deal_id=".$current_deal_mw_id);
                if(!$old_mw_remove) {
                    error_log("Warning: Failed to remove old MW deal mapping during upgrade: " . mysqli_error($connect));
                }
            }
        }
        
        // Remove old Franchise deal mapping if it exists and is different from new one
        if($current_deal_fr_id !== null && $current_deal_fr_id > 0) {
            // Only remove if new deal has different Franchise mapping
            if(empty($new_deal['franchise_deal_id']) || intval($new_deal['franchise_deal_id']) != $current_deal_fr_id) {
                $old_fr_remove = mysqli_query($connect, "DELETE FROM deal_customer_mapping 
                    WHERE customer_email='".$customer_email_esc."' AND deal_id=".$current_deal_fr_id);
                if(!$old_fr_remove) {
                    error_log("Warning: Failed to remove old Franchise deal mapping during upgrade: " . mysqli_error($connect));
                }
            }
        }
        
        // Now add new deals
        if(!empty($new_deal['mw_deal_id']) && $new_deal['mw_deal_id'] > 0) {
            $mw_id = intval($new_deal['mw_deal_id']);
            // Check if already exists (might exist if same as old deal)
            $exists_mw = mysqli_query($connect, "SELECT 1 FROM deal_customer_mapping WHERE customer_email='".$customer_email_esc."' AND deal_id=".$mw_id." LIMIT 1");
            if(!$exists_mw || mysqli_num_rows($exists_mw) === 0){
                $mw_mapping_query = mysqli_query($connect, "INSERT INTO deal_customer_mapping (customer_email, deal_id, created_by, created_date) VALUES ('".$customer_email_esc."', ".$mw_id.", '" . mysqli_real_escape_string($connect, $mapped_by) . "', NOW())");
                if(!$mw_mapping_query) { 
                    error_log("Failed to map MiniWebsite deal during upgrade: " . mysqli_error($connect)); 
                }
            }
        }
        
        if(!empty($new_deal['franchise_deal_id']) && $new_deal['franchise_deal_id'] > 0) {
            $fr_id = intval($new_deal['franchise_deal_id']);
            // Check if already exists (might exist if same as old deal)
            $exists_fr = mysqli_query($connect, "SELECT 1 FROM deal_customer_mapping WHERE customer_email='".$customer_email_esc."' AND deal_id=".$fr_id." LIMIT 1");
            if(!$exists_fr || mysqli_num_rows($exists_fr) === 0){
                $franchise_mapping_query = mysqli_query($connect, "INSERT INTO deal_customer_mapping (customer_email, deal_id, created_by, created_date) VALUES ('".$customer_email_esc."', ".$fr_id.", '" . mysqli_real_escape_string($connect, $mapped_by) . "', NOW())");
                if(!$franchise_mapping_query) { 
                    error_log("Failed to map Franchise deal during upgrade: " . mysqli_error($connect)); 
                }
            }
        }
    }
    
    // Commit transaction
    mysqli_commit($connect);
    
    // Send upgrade email after successful upgrade
    $email_sent = false;
    $email_error = '';
    
    try {
        require_once('../common/mailtemplate/send_upgrade_email.php');
        
        // Get user name from user_details
        $user_name_query = mysqli_query($connect, "SELECT name FROM user_details WHERE email = '" . mysqli_real_escape_string($connect, $user_email) . "' AND role='CUSTOMER' LIMIT 1");
        $user_name = $user_email; // Default to email if name not found
        if($user_name_query && mysqli_num_rows($user_name_query) > 0) {
            $user_name_data = mysqli_fetch_array($user_name_query);
            $user_name = $user_name_data['name'] ?? $user_email;
        }
        
        // Determine plan names based on deal codes
        $current_plan_name = ucfirst(str_replace('_', ' ', $current_deal_code)) . ' Plan';
        $upgrade_plan_name = ucfirst(str_replace('_', ' ', $new_deal_code)) . ' Plan';
        
        // Send upgrade email
        error_log("Sending upgrade email to: $user_email");
        
        if($requires_payment) {
            // Send upgrade email with payment details
            $email_sent = sendUpgradeEmail($user_name, $user_email, 'remaining_amount', [
                'current_plan' => $current_plan_name,
                'upgrade_plan' => $upgrade_plan_name,
                'remaining_amount' => $remaining_amount
            ]);
        } else {
            // Send upgrade confirmation email (no payment required)
            $email_sent = sendUpgradeEmail($user_name, $user_email, 'confirmation', [
                'current_plan' => $current_plan_name,
                'upgrade_plan' => $upgrade_plan_name
            ]);
        }
        
        error_log("Upgrade email result: " . ($email_sent ? 'SUCCESS' : 'FAILED'));
        
        // Update mapping record with email status
        if($email_sent) {
            mysqli_query($connect, "UPDATE user_joining_deals_mapping SET email_sent = 'YES', email_sent_date = NOW() WHERE id = $new_mapping_id");
        }
        
    } catch (Exception $emailException) {
        $email_error = $emailException->getMessage();
        error_log("Upgrade email sending failed: " . $email_error);
        error_log("Exception details: " . print_r($emailException, true));
    }
    
    $response_message = 'Deal upgraded successfully from ' . $current_deal_code . ' to ' . $new_deal_code;
    if($email_sent) {
        $response_message .= ' and email sent successfully';
    } else {
        // Don't fail the upgrade if email fails - just log it
        $response_message .= ' (email sending failed - check logs)';
        error_log("Upgrade completed but email failed for user: $user_email");
    }
    
    ob_clean(); // Clear any unwanted output
    echo json_encode([
        'success' => true,
        'message' => $response_message,
        'new_mapping_id' => $new_mapping_id,
        'payment_required' => $requires_payment,
        'remaining_amount' => $remaining_amount,
        'current_deal_fees' => $current_deal_fees,
        'new_deal_fees' => $new_deal_fees,
        'amount' => $requires_payment ? $remaining_amount : 0,
        'email_sent' => $email_sent,
        'email_error' => $email_error
    ]);
    
} catch(Exception $e) {
    // Rollback transaction
    mysqli_rollback($connect);
    
    ob_clean(); // Clear any unwanted output
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    // Restore autocommit
    mysqli_autocommit($connect, true);
}

ob_end_flush(); // Send the output
?>



