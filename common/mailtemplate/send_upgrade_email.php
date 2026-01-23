<?php
/**
 * Send Upgrade Email Helper
 * Helper functions to send upgrade emails
 */

require_once(__DIR__ . '/upgrade_email_templates.php');
require_once(__DIR__ . '/../email_config.php');

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer autoloader from project root
require_once(__DIR__ . '/../../vendor/autoload.php');

/**
 * Send email using PHPMailer with fallback to basic mail()
 */
function sendEmail($to, $subject, $message) {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = SMTP_AUTH;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        
        // Add SSL options for better compatibility
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom(DEFAULT_FROM_EMAIL, DEFAULT_FROM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(SUPPORT_EMAIL, DEFAULT_FROM_NAME);
        
        // Content
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->CharSet = 'UTF-8';
        
        return $mail->send();
        
    } catch (Exception $e) {
        error_log("Email sending failed via PHPMailer: " . $e->getMessage());
        error_log("PHPMailer ErrorInfo: " . $mail->ErrorInfo);
        
        // Fallback to basic mail() function
        try {
            $headers = "From: " . DEFAULT_FROM_EMAIL . "\r\n";
            $headers .= "Reply-To: " . SUPPORT_EMAIL . "\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            
            return @mail($to, $subject, $message, $headers);
        } catch (Exception $e2) {
            error_log("Basic mail() also failed: " . $e2->getMessage());
            return false;
        }
    }
}

/**
 * Send upgrade plan details email
 */
function sendUpgradePlanDetailsEmail($user_name, $user_email, $current_plan, $upgrade_plan, $amount, $gst_amount, $total_amount) {
    $email_data = getGenericUpgradeEmail($user_name, $user_email, $current_plan, $upgrade_plan, $amount, $gst_amount, $total_amount, false);
    
    return sendEmail($user_email, $email_data['subject'], $email_data['message']);
}

/**
 * Send upgrade confirmation email
 */
function sendUpgradeConfirmationEmail($user_name, $user_email, $current_plan, $upgrade_plan) {
    $email_data = getGenericUpgradeEmail($user_name, $user_email, $current_plan, $upgrade_plan, 0, 0, 0, true);
    
    return sendEmail($user_email, $email_data['subject'], $email_data['message']);
}

/**
 * Send upgrade with remaining amount email
 */
function sendUpgradeWithRemainingAmountEmail($user_name, $user_email, $current_plan, $upgrade_plan, $remaining_amount) {
    $email_data = getUpgradeWithRemainingAmountEmail($user_name, $user_email, $current_plan, $upgrade_plan, $remaining_amount);
    
    return sendEmail($user_email, $email_data['subject'], $email_data['message']);
}

/**
 * Send specific upgrade emails based on plan types
 */
function sendSpecificUpgradeEmail($user_name, $user_email, $current_plan, $upgrade_plan) {
    $email_data = null;
    
    // Handle specific upgrade combinations
    if ($current_plan === 'Basic Free Plan' && $upgrade_plan === 'Standard Plan') {
        $email_data = getBasicToStandardUpgradeEmail($user_name, $user_email);
    } elseif ($current_plan === 'Standard Plan' && $upgrade_plan === 'Premium Plan') {
        $email_data = getStandardToPremiumUpgradeEmail($user_name, $user_email);
    } elseif ($current_plan === 'Basic Free Plan' && $upgrade_plan === 'Creator Plan') {
        $email_data = getBasicToCreatorConfirmationEmail($user_name, $user_email);
    } else {
        // Use generic template for other combinations
        $email_data = getGenericUpgradeEmail($user_name, $user_email, $current_plan, $upgrade_plan, 0, 0, 0, false);
    }
    
    if ($email_data) {
        return sendEmail($user_email, $email_data['subject'], $email_data['message']);
    }
    
    return false;
}

/**
 * Send upgrade email based on upgrade type
 */
function sendUpgradeEmail($user_name, $user_email, $upgrade_type, $data = []) {
    switch ($upgrade_type) {
        case 'plan_details':
            return sendUpgradePlanDetailsEmail(
                $user_name, 
                $user_email, 
                $data['current_plan'], 
                $data['upgrade_plan'], 
                $data['amount'], 
                $data['gst_amount'], 
                $data['total_amount']
            );
            
        case 'confirmation':
            return sendUpgradeConfirmationEmail(
                $user_name, 
                $user_email, 
                $data['current_plan'], 
                $data['upgrade_plan']
            );
            
        case 'remaining_amount':
            return sendUpgradeWithRemainingAmountEmail(
                $user_name, 
                $user_email, 
                $data['current_plan'], 
                $data['upgrade_plan'], 
                $data['remaining_amount']
            );
            
        case 'specific':
            return sendSpecificUpgradeEmail(
                $user_name, 
                $user_email, 
                $data['current_plan'], 
                $data['upgrade_plan']
            );
            
        default:
            return false;
    }
}
?>
