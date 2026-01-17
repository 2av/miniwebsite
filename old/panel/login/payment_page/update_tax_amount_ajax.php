<?php
session_start();

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the tax amounts from POST data
    $final_amount = isset($_POST['final_amount']) ? (float)$_POST['final_amount'] : 0;
    $cgst = isset($_POST['cgst']) ? (float)$_POST['cgst'] : 0;
    $sgst = isset($_POST['sgst']) ? (float)$_POST['sgst'] : 0;
    $igst = isset($_POST['igst']) ? (float)$_POST['igst'] : 0;
    
    // Update session with new tax amounts
    $_SESSION['amount'] = $final_amount;
    $_SESSION['cgst_amount'] = $cgst;
    $_SESSION['sgst_amount'] = $sgst;
    $_SESSION['igst_amount'] = $igst;
    $_SESSION['final_total'] = $final_amount;
    
    // Return success response
    $response = [
        'success' => true,
        'message' => 'Tax amounts updated successfully',
        'final_amount' => $final_amount,
        'cgst' => $cgst,
        'sgst' => $sgst,
        'igst' => $igst
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
} else {
    // Return error for non-POST requests
    $response = [
        'success' => false,
        'message' => 'Invalid request method'
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>
