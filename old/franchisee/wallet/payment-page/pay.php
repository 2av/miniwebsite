<?php
// Include database connection for local environment
require('../../../common/config.php');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if form was submitted from wallet page
if(isset($_POST['add_money']) && isset($_POST['recharge_amount'])) {
    $deposit_amount = $_POST['recharge_amount'];
    
    // Also set the deposit field that the payment page expects
    $_POST['deposit'] = $deposit_amount;
    
    // Get user details from POST or fallback to session
    $f_name = isset($_POST['f_name']) ? $_POST['f_name'] : (isset($_SESSION['f_name']) ? $_SESSION['f_name'] : '');
    $l_name = isset($_POST['l_name']) ? $_POST['l_name'] : (isset($_SESSION['l_name']) ? $_SESSION['l_name'] : '');
    $f_contact = isset($_POST['f_contact']) ? $_POST['f_contact'] : (isset($_SESSION['f_contact']) ? $_SESSION['f_contact'] : '');
    
    // Store in session for later use
    $_SESSION['f_name'] = $f_name;
    $_SESSION['l_name'] = $l_name;
    $_SESSION['f_contact'] = $f_contact;
    
    // Validate amount
    if($deposit_amount < 500) {
        echo '<div class="alert danger">Invalid amount. Minimum amount is 500 Rs.</div>';
        echo '<meta http-equiv="refresh" content="3;URL=../index.php">';
        exit;
    }
    
    // Set session variables for payment
    $_SESSION['amount'] = $deposit_amount;
    $_SESSION['reference_number'] = 'WAL'.rand(1000,9999).date('dmYHis');
    $_SESSION['user_name'] = $f_name . ' ' . $l_name;
    $_SESSION['user_contact'] = $f_contact;
}

// If no amount is set, redirect back to wallet
if(!isset($_SESSION['amount'])) {
    echo '<div class="alert danger">No amount specified for payment.</div>';
    echo '<meta http-equiv="refresh" content="3;URL=../index.php">';
    exit;
}

// Redirect to the original payment system
header("Location: ../../../panel/franchisee-login/payment_page/pay.php");
exit();
?>
