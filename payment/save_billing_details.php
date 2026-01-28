<?php
// Include centralized configs
require_once(__DIR__ . '/../app/config/database.php');

// Suppress any output before JSON
ob_start();

header('Content-Type: application/json');

// Check if form data is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $card_id = isset($_POST['card_id']) ? mysqli_real_escape_string($connect, $_POST['card_id']) : '';
    $gst_number = isset($_POST['gst_number']) ? mysqli_real_escape_string($connect, $_POST['gst_number']) : '';
    $gst_name = isset($_POST['gst_name']) ? mysqli_real_escape_string($connect, $_POST['gst_name']) : '';
    $gst_email = isset($_POST['gst_email']) ? mysqli_real_escape_string($connect, $_POST['gst_email']) : '';
    $gst_contact = isset($_POST['gst_contact']) ? mysqli_real_escape_string($connect, $_POST['gst_contact']) : '';
    $gst_address = isset($_POST['gst_address']) ? mysqli_real_escape_string($connect, $_POST['gst_address']) : '';
    $gst_state = isset($_POST['gst_state']) ? mysqli_real_escape_string($connect, $_POST['gst_state']) : '';
    $gst_city = isset($_POST['gst_city']) ? mysqli_real_escape_string($connect, $_POST['gst_city']) : '';
    $gst_pincode = isset($_POST['gst_pincode']) ? mysqli_real_escape_string($connect, $_POST['gst_pincode']) : '';
    
    // Save to session for use in payment
    $_SESSION['billing_gst_number'] = $gst_number;
    $_SESSION['billing_gst_name'] = $gst_name;
    $_SESSION['billing_gst_email'] = $gst_email;
    $_SESSION['billing_gst_contact'] = $gst_contact;
    $_SESSION['billing_gst_address'] = $gst_address;
    $_SESSION['billing_gst_state'] = $gst_state;
    $_SESSION['billing_gst_city'] = $gst_city;
    $_SESSION['billing_gst_pincode'] = $gst_pincode;
    
    // Recalculate tax based on billing details
    $original_amount = isset($_SESSION['original_amount']) ? $_SESSION['original_amount'] : 0;
    $discount_amount = isset($_SESSION['promo_discount']) ? $_SESSION['promo_discount'] : 0;
    $subtotal = $original_amount - $discount_amount;
    
    $company_state_code = '06';
    $is_interstate = false;
    
    if (!empty($gst_number) && strlen($gst_number) == 15 && preg_match('/^\d{2}[A-Z0-9]{13}$/', $gst_number)) {
        $customer_state_code = substr($gst_number, 0, 2);
        $is_interstate = ($customer_state_code !== $company_state_code);
    } else {
        $billing_state_lower = strtolower(trim($gst_state));
        $is_interstate = !in_array($billing_state_lower, ['haryana', 'hariyana']);
    }
    
    if ($is_interstate) {
        $igst_amount = round($subtotal * 0.18, 2);
        $cgst_amount = 0;
        $sgst_amount = 0;
    } else {
        $cgst_amount = round($subtotal * 0.09, 2);
        $sgst_amount = round($subtotal * 0.09, 2);
        $igst_amount = 0;
    }
    
    $total_tax = $cgst_amount + $sgst_amount + $igst_amount;
    $final_amount = $subtotal + $total_tax;
    
    $_SESSION['amount'] = $final_amount;
    $_SESSION['subtotal_amount'] = $subtotal;
    $_SESSION['cgst_amount'] = $cgst_amount;
    $_SESSION['sgst_amount'] = $sgst_amount;
    $_SESSION['igst_amount'] = $igst_amount;
    $_SESSION['final_total'] = $final_amount;
    $_SESSION['is_interstate'] = $is_interstate;
    
    // Prepare response data
    $response_data = [
        'success' => true,
        'message' => 'Billing details saved successfully',
        'final_amount' => $final_amount,
        'subtotal' => $subtotal,
        'cgst_amount' => $cgst_amount,
        'sgst_amount' => $sgst_amount,
        'igst_amount' => $igst_amount,
        'is_interstate' => $is_interstate
    ];
    
    // If card_id is provided, update digi_card table
    if (!empty($card_id)) {
        // Check if columns exist, add them if not
        $columns_to_add = [
            'd_gst' => "VARCHAR(50) DEFAULT NULL",
            'd_gst_name' => "VARCHAR(100) DEFAULT NULL",
            'd_gst_email' => "VARCHAR(100) DEFAULT NULL",
            'd_gst_contact' => "VARCHAR(20) DEFAULT NULL",
            'd_gst_address' => "VARCHAR(255) DEFAULT NULL",
            'd_gst_state' => "VARCHAR(50) DEFAULT NULL",
            'd_gst_city' => "VARCHAR(50) DEFAULT NULL",
            'd_gst_pincode' => "VARCHAR(20) DEFAULT NULL"
        ];
        
        // Get existing columns
        $existing_columns = [];
        $check_columns_query = "SHOW COLUMNS FROM digi_card";
        $columns_result = $connect->query($check_columns_query);
        if ($columns_result) {
            while ($row = $columns_result->fetch_assoc()) {
                $existing_columns[] = $row['Field'];
            }
        }
        
        // Add missing columns
        foreach ($columns_to_add as $column_name => $column_def) {
            if (!in_array($column_name, $existing_columns)) {
                $alter_query = "ALTER TABLE digi_card ADD COLUMN $column_name $column_def";
                $connect->query($alter_query);
            }
        }
        
        $update_query = "UPDATE digi_card SET 
            d_gst = '$gst_number',
            d_gst_name = '$gst_name',
            d_gst_email = '$gst_email',
            d_gst_contact = '$gst_contact',
            d_gst_address = '$gst_address',
            d_gst_state = '$gst_state',
            d_gst_city = '$gst_city',
            d_gst_pincode = '$gst_pincode'
            WHERE id = '$card_id'";
        
        if ($connect->query($update_query) === TRUE) {
            $_SESSION['card_id'] = $card_id;
            ob_end_clean(); // Clear any output
            echo json_encode($response_data);
            exit;
        } else {
            ob_end_clean(); // Clear any output
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error updating record: ' . $connect->error]);
            exit;
        }
    } else {
        // For franchise payments, just save to session
        ob_end_clean(); // Clear any output
        echo json_encode($response_data);
        exit;
    }
} else {
    ob_end_clean(); // Clear any output
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}
?>
