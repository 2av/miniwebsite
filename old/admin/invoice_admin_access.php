<?php
require('connect.php');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Only allow if admin is logged in (tolerate different entry points)
$isAdmin = false;
if (isset($_SESSION['admin_email']) && !empty($_SESSION['admin_email'])) { $isAdmin = true; }
// Fallback: if referrer is from admin area, consider it admin-origin request
if (!$isAdmin && isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], '/admin/') !== false) { $isAdmin = true; }
if (!$isAdmin) {
    http_response_code(403);
    echo 'Access denied: admin session not found';
    exit;
}

// Accept either card id (?id=) or invoice id (?invoice_id=)
$invoiceId = '';
if (isset($_GET['invoice_id']) && ctype_digit($_GET['invoice_id'])) {
    $invoiceId = (int) $_GET['invoice_id'];
} else {
    $cardId = isset($_GET['id']) ? trim($_GET['id']) : '';
    if ($cardId === '' || !ctype_digit($cardId)) {
        http_response_code(400);
        echo 'Invalid request';
        exit;
    }
    // Find latest invoice id for this card
    $cardIdEsc = mysqli_real_escape_string($connect, $cardId);
    $invRes = mysqli_query($connect, "SELECT id FROM invoice_details WHERE card_id='$cardIdEsc' ORDER BY id DESC LIMIT 1");
    if (!$invRes || mysqli_num_rows($invRes) === 0) {
        echo 'Invoice not found';
        exit;
    }
    $inv = mysqli_fetch_array($invRes);
    $invoiceId = (int)$inv['id'];
}

// Set a time-limited admin bypass token in session
$_SESSION['invoice_admin_bypass'] = [
    'allowed' => true,
    'expires_at' => time() + 120, // 2 minutes
    'invoice_id' => $invoiceId,
];

// Redirect to customer page with invoice id
header('Location: ../customer/dashboard/download_invoice_new.php?id=' . $invoiceId . '&admin=1');
exit;
