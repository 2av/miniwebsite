<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear billing details from session
unset($_SESSION['billing_gst_number']);
unset($_SESSION['billing_gst_name']);
unset($_SESSION['billing_gst_email']);
unset($_SESSION['billing_gst_contact']);
unset($_SESSION['billing_gst_address']);
unset($_SESSION['billing_gst_state']);
unset($_SESSION['billing_gst_city']);
unset($_SESSION['billing_gst_pincode']);

// Return success response
http_response_code(200);
echo "Session cleared successfully";
?>
