<?php
/**
 * Process Deal Upgrade
 * Handles upgrading a user from one joining deal to another
 */

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
    $current_mapping_query = mysqli_query($connect, "SELECT ujdm.*, jd.deal_name, jd.total_fees 
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
    
    // Calculate new dates (extend from current expiry)
    $current_expiry = $current_mapping['expiry_date'];
    $new_start_date = $current_expiry; // Start from current expiry
    $new_expiry_date = date('Y-m-d H:i:s', strtotime($current_expiry . ' +1 year'));
    
    // Check if deal requires payment
    $requires_payment = ($new_deal['total_fees'] > 0);
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
        " . ($requires_payment ? $new_deal['total_fees'] : 0) . ", 
        NOW(), 
        'Upgraded from " . mysqli_real_escape_string($connect, $current_deal_code) . "')");
    
    if(!$mapping_query) {
        throw new Exception('Failed to create new mapping record: ' . mysqli_error($connect));
    }
    
    $new_mapping_id = mysqli_insert_id($connect);
    
    // Auto-map deals if they are configured for the new joining deal
    if(!empty($new_deal['mw_deal_id']) && $new_deal['mw_deal_id'] > 0) {
        $mw_mapping_query = mysqli_query($connect, "INSERT INTO deal_customer_mapping 
            (customer_email, deal_id, created_by, created_date) 
            VALUES ('" . mysqli_real_escape_string($connect, $user_email) . "', 
            " . intval($new_deal['mw_deal_id']) . ", 
            '" . mysqli_real_escape_string($connect, $mapped_by) . "', 
            NOW())");
        
        if(!$mw_mapping_query) {
            error_log("Failed to map MiniWebsite deal during upgrade: " . mysqli_error($connect));
        }
    }
    
    if(!empty($new_deal['franchise_deal_id']) && $new_deal['franchise_deal_id'] > 0) {
        $franchise_mapping_query = mysqli_query($connect, "INSERT INTO deal_customer_mapping 
            (customer_email, deal_id, created_by, created_date) 
            VALUES ('" . mysqli_real_escape_string($connect, $user_email) . "', 
            " . intval($new_deal['franchise_deal_id']) . ", 
            '" . mysqli_real_escape_string($connect, $mapped_by) . "', 
            NOW())");
        
        if(!$franchise_mapping_query) {
            error_log("Failed to map Franchise deal during upgrade: " . mysqli_error($connect));
        }
    }
    
    // Log the upgrade (optional - don't fail if logging fails)
    $log_query = mysqli_query($connect, "INSERT INTO email_logs (user_email, email_type, subject, sent_date, status) 
        VALUES ('" . mysqli_real_escape_string($connect, $user_email) . "', 
        'deal_upgrade', 
        'Upgraded from " . mysqli_real_escape_string($connect, $current_deal_code) . " to " . mysqli_real_escape_string($connect, $new_deal_code) . "', 
        NOW(), 
        'SENT')");
    
    if(!$log_query) {
        error_log("Failed to log deal upgrade: " . mysqli_error($connect));
    }
    
    // Commit transaction
    mysqli_commit($connect);
    
    echo json_encode([
        'success' => true,
        'message' => 'Deal upgraded successfully from ' . $current_deal_code . ' to ' . $new_deal_code,
        'new_mapping_id' => $new_mapping_id,
        'payment_required' => $requires_payment,
        'amount' => $requires_payment ? $new_deal['total_fees'] : 0
    ]);
    
} catch(Exception $e) {
    // Rollback transaction
    mysqli_rollback($connect);
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    // Restore autocommit
    mysqli_autocommit($connect, true);
}
?>



