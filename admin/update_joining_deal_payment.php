<?php
/**
 * Update Joining Deal Payment Status
 * Handles payment updates and invoice linking for joining deals
 */

require('connect_ajax.php');

if(isset($_POST['update_joining_deal_payment'])) {
    $mapping_id = mysqli_real_escape_string($connect, $_POST['mapping_id']);
    $transaction_id = mysqli_real_escape_string($connect, $_POST['transaction_id']);
    $invoice_id = mysqli_real_escape_string($connect, $_POST['invoice_id']);
    $payment_status = mysqli_real_escape_string($connect, $_POST['payment_status']);
    $amount_paid = floatval($_POST['amount_paid']);
    
    // Validate required fields
    if(empty($mapping_id) || empty($payment_status)) {
        echo "error: Missing required parameters";
        exit;
    }
    
    // Validate payment status
    $valid_statuses = ['PENDING', 'PAID', 'FAILED', 'REFUNDED'];
    if(!in_array($payment_status, $valid_statuses)) {
        echo "error: Invalid payment status";
        exit;
    }
    
    // Get current mapping details
    $mapping_query = mysqli_query($connect, "SELECT * FROM user_joining_deals_mapping WHERE id='$mapping_id' LIMIT 1");
    if(!$mapping_query || mysqli_num_rows($mapping_query) == 0) {
        echo "error: Mapping not found";
        exit;
    }
    
    $mapping_data = mysqli_fetch_array($mapping_query);
    
    // Prepare update query
    $update_fields = array();
    $update_fields[] = "payment_status = '$payment_status'";
    $update_fields[] = "amount_paid = $amount_paid";
    
    if(!empty($transaction_id)) {
        $update_fields[] = "transaction_id = '$transaction_id'";
    }
    
    if(!empty($invoice_id)) {
        $update_fields[] = "invoice_id = $invoice_id";
    }
    
    if($payment_status == 'PAID') {
        $update_fields[] = "payment_date = NOW()";
    }
    
    $update_query = "UPDATE user_joining_deals_mapping SET " . implode(', ', $update_fields) . " WHERE id='$mapping_id'";
    
    if(mysqli_query($connect, $update_query)) {
        // Log the payment update
        $log_query = mysqli_query($connect, "INSERT INTO email_logs (user_email, email_type, subject, sent_date, status) 
            VALUES ('" . $mapping_data['user_email'] . "', 'joining_deal_payment_update', 'Payment status updated to $payment_status', NOW(), 'SUCCESS')");
        
        echo "success: Payment status updated successfully";
    } else {
        echo "error: Failed to update payment status - " . mysqli_error($connect);
    }
    
} elseif(isset($_POST['get_joining_deal_details'])) {
    $mapping_id = mysqli_real_escape_string($connect, $_POST['mapping_id']);
    
    $query = mysqli_query($connect, "SELECT ujdm.*, jd.deal_name, jd.deal_code, jd.total_fees, jd.commission_amount,
        CASE 
            WHEN ujdm.expiry_date < NOW() THEN 'EXPIRED'
            WHEN ujdm.payment_status = 'PENDING' AND jd.total_fees > 0 THEN 'PENDING_PAYMENT'
            WHEN ujdm.payment_status = 'PAID' THEN 'ACTIVE'
            WHEN ujdm.payment_status = 'FAILED' THEN 'PAYMENT_FAILED'
            ELSE 'ACTIVE'
        END as deal_status,
        DATEDIFF(ujdm.expiry_date, NOW()) as days_remaining
        FROM user_joining_deals_mapping ujdm
        JOIN joining_deals jd ON ujdm.joining_deal_id = jd.id
        WHERE ujdm.id = '$mapping_id'");
    
    if($query && mysqli_num_rows($query) > 0) {
        $data = mysqli_fetch_array($query);
        echo json_encode($data);
    } else {
        echo "error: Mapping not found";
    }
    
} elseif(isset($_POST['update_joining_deal_dates'])) {
    $mapping_id = mysqli_real_escape_string($connect, $_POST['mapping_id'] ?? '');
    $start_date = mysqli_real_escape_string($connect, $_POST['start_date'] ?? '');
    $expiry_date = mysqli_real_escape_string($connect, $_POST['expiry_date'] ?? '');
    
    if(empty($mapping_id) || empty($start_date) || empty($expiry_date)){
        echo "error: Missing parameters";
        exit;
    }
    
    // Ensure expiry is after start
    if(strtotime($expiry_date) <= strtotime($start_date)){
        echo "error: Expiry must be after start date";
        exit;
    }
    
    $q = mysqli_query($connect, "UPDATE user_joining_deals_mapping 
        SET start_date = '$start_date', expiry_date = '$expiry_date' 
        WHERE id = '".$mapping_id."'");
    if($q){
        echo "success: dates updated";
    } else {
        echo "error: " . mysqli_error($connect);
    }
    
} elseif(isset($_POST['update_joining_deal_field'])) {
    $deal_id = mysqli_real_escape_string($connect, $_POST['deal_id'] ?? '');
    $field = mysqli_real_escape_string($connect, $_POST['field'] ?? '');
    $value = mysqli_real_escape_string($connect, $_POST['value'] ?? '');
    
    if(empty($deal_id) || empty($field) || $value === ''){
        echo "error: Missing parameters";
        exit;
    }
    
    // Validate field name
    $allowed_fields = ['deal_name', 'total_fees', 'commission_amount', 'discount_amount', 'fees'];
    if(!in_array($field, $allowed_fields)) {
        echo "error: Invalid field name";
        exit;
    }
    
    // Validate numeric fields
    if($field !== 'deal_name' && !is_numeric($value)) {
        echo "error: Invalid numeric value";
        exit;
    }
    
    // Update the joining_deals table
    $update_query = "UPDATE joining_deals SET $field = '$value' WHERE id = $deal_id";
    
    if(mysqli_query($connect, $update_query)) {
        // If updating fees, also recalculate gst_amount and total_fees
        if($field === 'fees') {
            $gst_amount = $value * 0.18;
            $total_fees = $value + $gst_amount;
            $recalc_query = "UPDATE joining_deals SET gst_amount = $gst_amount, total_fees = $total_fees WHERE id = $deal_id";
            mysqli_query($connect, $recalc_query);
        }
        
        echo "success: Field updated successfully";
    } else {
        echo "error: Failed to update field - " . mysqli_error($connect);
    }
    
} elseif(isset($_POST['get_deal_details'])) {
    $deal_id = mysqli_real_escape_string($connect, $_POST['deal_id'] ?? '');
    
    if(empty($deal_id)){
        echo "error: Missing deal ID";
        exit;
    }
    
    $query = mysqli_query($connect, "SELECT * FROM joining_deals WHERE id = '$deal_id'");
    
    if($query && mysqli_num_rows($query) > 0) {
        $data = mysqli_fetch_array($query);
        echo json_encode($data);
    } else {
        echo "error: Deal not found";
    }
    
} elseif(isset($_POST['update_deal_details'])) {
    $deal_id = mysqli_real_escape_string($connect, $_POST['deal_id'] ?? '');
    $deal_name = mysqli_real_escape_string($connect, $_POST['deal_name'] ?? '');
    $fees = floatval($_POST['fees'] ?? 0);
    $total_fees = floatval($_POST['total_fees'] ?? 0);
    $commission_amount = floatval($_POST['commission_amount'] ?? 0);
    $discount_amount = floatval($_POST['discount_amount'] ?? 0);
    $mw_deal_id = intval($_POST['mw_deal_id'] ?? 0);
    $franchise_deal_id = intval($_POST['franchise_deal_id'] ?? 0);
    
    // Debug logging
    error_log("Deal Update Debug - ID: $deal_id, Name: $deal_name, Fees: $fees, Total: $total_fees, Commission: $commission_amount, Discount: $discount_amount, MW Deal: $mw_deal_id, Franchise Deal: $franchise_deal_id");
    
    if(empty($deal_id) || empty($deal_name) || $fees < 0 || $total_fees < 0 || $commission_amount < 0){
        echo "error: Missing or invalid parameters - ID: $deal_id, Name: '$deal_name', Fees: $fees, Total: $total_fees, Commission: $commission_amount";
        exit;
    }
    
    // Calculate GST amount
    $gst_amount = $total_fees - $fees;
    
    // Update the joining_deals table with mapped deal IDs
    $update_query = "UPDATE joining_deals SET 
        deal_name = '$deal_name',
        fees = $fees,
        gst_amount = $gst_amount,
        total_fees = $total_fees,
        commission_amount = $commission_amount,
        discount_amount = $discount_amount,
        mw_deal_id = " . ($mw_deal_id > 0 ? $mw_deal_id : 'NULL') . ",
        franchise_deal_id = " . ($franchise_deal_id > 0 ? $franchise_deal_id : 'NULL') . "
        WHERE id = $deal_id";
    
    if(mysqli_query($connect, $update_query)) {
        echo "success: Deal updated successfully";
    } else {
        echo "error: Failed to update deal - " . mysqli_error($connect);
    }
    
} else {
    echo "error: Invalid request";
}
?>



