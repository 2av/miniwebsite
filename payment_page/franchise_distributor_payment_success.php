<?php
// Start session at the very beginning
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
$db_host = "p004.bom1.mysecurecloudhost.com";
$db_user = "wwwmoody_miniweb_vcard";
$db_pass = "miniweb_vcard";
$db_name = "miniweb_vcard";

$connect = null;

try {
    $connect = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($connect->connect_error) {
        throw new Exception("Connection failed: " . $connect->connect_error);
    }
    
    // Get payment details from POST data
    $user_email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $transaction_id = isset($_POST['transaction_id']) ? trim($_POST['transaction_id']) : '';
    $amount_paid = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $joining_deal_id = isset($_POST['joining_deal_id']) ? (int)$_POST['joining_deal_id'] : 0;
    $mapping_id = isset($_POST['mapping_id']) ? (int)$_POST['mapping_id'] : 0;
    
    if (empty($user_email) || empty($transaction_id) || $amount_paid <= 0) {
        throw new Exception("Missing required payment data");
    }
    
    // Update the joining deal mapping with payment details
    $update_query = "UPDATE user_joining_deals_mapping 
                     SET payment_status = 'PAID', 
                         payment_date = NOW(), 
                         amount_paid = ?, 
                         transaction_id = ?,
                         updated_at = NOW()
                     WHERE user_email = ? AND id = ?";
    
    $stmt = $connect->prepare($update_query);
    $stmt->bind_param("dssi", $amount_paid, $transaction_id, $user_email, $mapping_id);
    
    if ($stmt->execute()) {
        // Log the successful payment
        error_log("Franchise distributor payment successful: User: $user_email, Amount: $amount_paid, Transaction: $transaction_id");
        
        // 1. Create invoice entry in invoice_details table
        $invoice_query = "INSERT INTO invoice_details (
            user_email, 
            transaction_id, 
            amount, 
            payment_status, 
            payment_date, 
            invoice_type,
            joining_deal_id,
            mapping_id,
            created_at
        ) VALUES (?, ?, ?, 'PAID', NOW(), 'FRANCHISE_DISTRIBUTOR', ?, ?, NOW())";
        
        $invoice_stmt = $connect->prepare($invoice_query);
        $invoice_stmt->bind_param("ssdii", $user_email, $transaction_id, $amount_paid, $joining_deal_id, $mapping_id);
        
        if ($invoice_stmt->execute()) {
            $invoice_id = $connect->insert_id;
            error_log("Invoice created with ID: $invoice_id for user: $user_email");
            
            // 2. Update start_date and expiry_date in user_joining_deals_mapping
            $date_update_query = "UPDATE user_joining_deals_mapping 
                                 SET start_date = NOW(),
                                     expiry_date = DATE_ADD(NOW(), INTERVAL 1 YEAR),
                                     invoice_id = ?,
                                     updated_at = NOW()
                                 WHERE user_email = ? AND id = ?";
            
            $date_stmt = $connect->prepare($date_update_query);
            $date_stmt->bind_param("isi", $invoice_id, $user_email, $mapping_id);
            
            if ($date_stmt->execute()) {
                error_log("Dates updated: start_date = NOW(), expiry_date = NOW() + 1 YEAR for user: $user_email");
                
                // Send success response
                echo json_encode([
                    'success' => true,
                    'message' => 'Payment processed successfully',
                    'user_email' => $user_email,
                    'amount_paid' => $amount_paid,
                    'transaction_id' => $transaction_id,
                    'invoice_id' => $invoice_id,
                    'start_date' => date('Y-m-d H:i:s'),
                    'expiry_date' => date('Y-m-d H:i:s', strtotime('+1 year'))
                ]);
            } else {
                throw new Exception("Failed to update dates: " . $date_stmt->error);
            }
            
            $date_stmt->close();
        } else {
            throw new Exception("Failed to create invoice: " . $invoice_stmt->error);
        }
        
        $invoice_stmt->close();
    } else {
        throw new Exception("Failed to update payment status: " . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Franchise distributor payment error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Payment processing failed: ' . $e->getMessage()
    ]);
} finally {
    if ($connect) {
        $connect->close();
    }
}
?>


