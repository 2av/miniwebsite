<?php
require('login-connect.php');

// Include PHPMailer and email configuration
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../common/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if registration data exists in session
if (!isset($_SESSION['registration_data'])) {
    header('Location: create-account.php');
    exit;
}

// Generate new OTP
$otp = rand(100000, 999999);
$_SESSION['registration_otp'] = $otp;
$_SESSION['registration_otp_time'] = time();

// Get user data from session
$user_name = $_SESSION['registration_data']['user_name'];
$user_email = $_SESSION['registration_data']['user_email'];

// Send OTP email using PHPMailer
try {
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);
    
    // Server settings
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = SMTP_AUTH;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';
    
    // Additional SMTP settings for better compatibility
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    // Recipients
    $mail->setFrom(SMTP_USERNAME, 'MiniWebsite Support');
    $mail->addAddress($user_email, $user_name);
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = $_SERVER['HTTP_HOST'] . " - Your New OTP for Registration";
    $message = '
    Hi ' . $user_name . ',<br><br>
    Your new OTP for registration on ' . $_SERVER['HTTP_HOST'] . ' is: <b>' . $otp . '</b><br><br>
    This OTP is valid for 10 minutes.<br><br>
    Thanks,<br>' . $_SERVER['HTTP_HOST'] . ' Team';
    
    $mail->Body = $message;
    $mail->AltBody = strip_tags(str_replace('<br>', "\n", $message));
    
    // Send the email
    if ($mail->send()) {
        $_SESSION['otp_message'] = "New OTP has been sent to your email.";
    } else {
        $_SESSION['otp_error'] = "Error sending OTP email. Please try again.";
    }
} catch (Exception $e) {
    error_log("Email sending failed: " . $e->getMessage());
    $_SESSION['otp_error'] = "Error sending OTP email. Please try again.";
}

// Redirect back to registration page
header('Location: create-account.php');
exit;

