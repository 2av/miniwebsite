<?php
// Prevent any output before JSON
if (ob_get_level()) {
    ob_clean();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable display_errors to prevent output

require('connect_ajax.php');
require_once('includes/notification_helper.php');

// Include PHPMailer and email configuration
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../common/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Log the incoming request for debugging
error_log('Document verification request: ' . json_encode($_POST));

if(!isset($_POST['user_email']) || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'User email and action are required']);
    exit;
}

$user_email = $_POST['user_email'];
$action = $_POST['action'];
$remarks = isset($_POST['remarks']) ? $_POST['remarks'] : '';

if($action !== 'approve' && $action !== 'reject') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$status = ($action === 'approve') ? 'approved' : 'rejected';

try {
    // Sanitize inputs
    $user_email = mysqli_real_escape_string($connect, $user_email);
    $remarks = mysqli_real_escape_string($connect, $remarks);
    
    // Log the query for debugging
    $update_sql = "UPDATE franchisee_verification SET status = '$status', admin_remarks = '$remarks', reviewed_at = NOW(), reviewed_by = 'admin' WHERE user_email = '$user_email'";
    error_log('Update query: ' . $update_sql);
    
    // Update verification status
    $update_query = mysqli_query($connect, $update_sql);
    
    if($update_query) {
        // Get franchisee details for email from user_details
        $franchisee_query = mysqli_query($connect, "SELECT name FROM user_details WHERE email = '$user_email' AND role='FRANCHISEE'");
        $franchisee_name = 'Franchisee';
        if(mysqli_num_rows($franchisee_query) > 0) {
            $franchisee_row = mysqli_fetch_assoc($franchisee_query);
            $franchisee_name = $franchisee_row['name'] ?: 'Franchisee';
        }
        
        // Send email notification using PHPMailer
        $subject = "MiniWebsite.in – Document verification";
        
        if($action === 'approve') {
            $message = "Hi " . $franchisee_name . ",<br><br>";
            $message .= "Thank you for registering as a franchisee with MiniWebsite.in.<br><br>";
            $message .= "Congratulation! The verification documents are approved by Miniwebsite Team.<br>";
            $message .= "You can access your Franchisee Kit from your Dashboard and start your business immediately.<br><br>";
            $message .= "If you have any questions or need assistance, feel free to reach out to our support team.<br><br>";
            $message .= "Best regards,<br>";
            $message .= "Team MiniWebsite.in<br>";
            $message .= "www.miniwebsite.in";
        } else {
            $message = "Hi " . $franchisee_name . ",<br><br>";
            $message .= "Thank you for registering as a franchisee with MiniWebsite.in.<br><br>";
            $message .= "The documents uploaded for verification is not approved by Miniwebsite Team.<br><br>";
            $message .= "Please check the reason:<br>";
            $message .= (!empty($remarks) ? $remarks : "Please upload clear and valid documents.") . "<br><br>";
            $message .= "If you have any questions or need assistance, feel free to reach out to our support team.<br><br>";
            $message .= "Best regards,<br>";
            $message .= "Team MiniWebsite.in<br>";
            $message .= "www.miniwebsite.in";
        }
        
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
            $mail->addAddress($user_email, $franchisee_name);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->AltBody = strip_tags(str_replace('<br>', "\n", $message));
            
            // Send the email
            $mail->send();
            error_log("Verification email sent successfully to: $user_email");
            
            // Create notification for verification status update
            $notification_type = ($action === 'approve') ? 'verification' : 'verification';
            $notification_title = ($action === 'approve') ? 'Franchisee Documents Approved' : 'Franchisee Documents Rejected';
            $notification_message = ($action === 'approve') 
                ? "Franchisee documents have been approved for: $user_email" 
                : "Franchisee documents have been rejected for: $user_email" . (!empty($remarks) ? " - Reason: $remarks" : "");
            $notification_priority = ($action === 'approve') ? 'medium' : 'high';
            
            createNotification(
                $notification_type,
                $notification_title,
                $notification_message,
                $user_email,
                'franchisee',
                null,
                $notification_priority
            );
            
        } catch (Exception $e) {
            error_log("Verification email failed: " . $e->getMessage());
        }
        
        echo json_encode(['success' => true, 'message' => 'Verification status updated successfully']);
    } else {
        $error_message = mysqli_error($connect);
        error_log('Database update failed: ' . $error_message);
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to update verification status: ' . $error_message,
            'debug' => [
                'user_email' => $user_email,
                'action' => $action,
                'status' => $status,
                'remarks' => $remarks,
                'sql_error' => $error_message
            ]
        ]);
    }
} catch(Exception $e) {
    error_log('Document verification update error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
} catch(Error $e) {
    error_log('Document verification update error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>
