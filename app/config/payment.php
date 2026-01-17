<?php
/**
 * Centralized Payment Configuration
 * Single source of truth for Razorpay and payment settings
 */

// Razorpay API Keys
$keyId = 'rzp_live_xU57a1JhH7To1G'; // Your Razorpay Key ID
$keySecret = 'VHJzQnCxqF5HTNsE3LUTZtnI'; // Your Razorpay Key Secret
$displayCurrency = 'INR';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Store the keys in session for consistency
$_SESSION['razorpay_key_id'] = $keyId;
$_SESSION['razorpay_key_secret'] = $keySecret;

// Error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set default timezone
date_default_timezone_set("Asia/Kolkata");

?>
