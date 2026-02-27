<?php
session_start();

require_once(__DIR__ . '/../app/config/database.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get data from AJAX request
$recharge_amount = isset($_POST['recharge_amount']) ? floatval($_POST['recharge_amount']) : 0;
$gst_name = isset($_POST['gst_name']) ? trim($_POST['gst_name']) : '';
$gst_email = isset($_POST['gst_email']) ? trim($_POST['gst_email']) : '';
$gst_contact = isset($_POST['gst_contact']) ? trim($_POST['gst_contact']) : '';
$gst_address = isset($_POST['gst_address']) ? trim($_POST['gst_address']) : '';
$gst_state = isset($_POST['gst_state']) ? trim($_POST['gst_state']) : '';
$gst_city = isset($_POST['gst_city']) ? trim($_POST['gst_city']) : '';
$gst_pincode = isset($_POST['gst_pincode']) ? trim($_POST['gst_pincode']) : '';

// Validate required fields
if (empty($gst_name) || empty($gst_email) || empty($gst_contact) || empty($gst_address) || 
    empty($gst_state) || empty($gst_city) || empty($gst_pincode) || $recharge_amount < 350) {
    echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
    exit;
}

// Store in session
$_SESSION['billing_gst_name'] = $gst_name;
$_SESSION['billing_gst_email'] = $gst_email;
$_SESSION['billing_gst_contact'] = $gst_contact;
$_SESSION['billing_gst_address'] = $gst_address;
$_SESSION['billing_gst_state'] = $gst_state;
$_SESSION['billing_gst_city'] = $gst_city;
$_SESSION['billing_gst_pincode'] = $gst_pincode;
$_SESSION['original_amount'] = $recharge_amount;

// Calculate GST (18% for wallet recharge)
$subtotal = $recharge_amount;
$igst = round($subtotal * 0.18, 2);
$final_amount = $subtotal + $igst;

// Update session with calculated amounts
$_SESSION['subtotal_amount'] = $subtotal;
$_SESSION['cgst_amount'] = 0;
$_SESSION['sgst_amount'] = 0;
$_SESSION['igst_amount'] = $igst;
$_SESSION['final_total'] = $final_amount;
$_SESSION['amount'] = $final_amount;

echo json_encode([
    'success' => true,
    'message' => 'Billing details saved successfully',
    'final_amount' => $final_amount,
    'subtotal' => $subtotal,
    'gst' => $igst
]);
?>
