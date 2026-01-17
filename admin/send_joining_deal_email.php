<?php
// Prevent any HTML output that might interfere with AJAX response
ob_start();

require('connect_ajax.php');
require_once(__DIR__ . '/../app/config/email.php');');

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once('../vendor/autoload.php');

if(isset($_POST['send_joining_deal_email'])) {
    $user_email = mysqli_real_escape_string($connect, $_POST['user_email']);
    $joining_deal = mysqli_real_escape_string($connect, $_POST['joining_deal']);
    
    // Debug logging
    error_log("Joining deal request: user_email=$user_email, joining_deal=$joining_deal");
    
    if(empty($user_email) || empty($joining_deal)) {
        echo "error: Missing required parameters";
        exit;
    }
    
    // Get user details (including original joining date) from user_details
    $user_query = mysqli_query($connect, "SELECT name, created_at FROM user_details WHERE email='$user_email' AND role='CUSTOMER' LIMIT 1");
    if(!$user_query || mysqli_num_rows($user_query) == 0) {
        echo "error: User not found";
        exit;
    }
    
    $user_data = mysqli_fetch_array($user_query);
    // Map user_details fields to old field names for compatibility
    $user_data['user_name'] = $user_data['name'] ?? '';
    $user_data['uploaded_date'] = $user_data['created_at'] ?? '';
    $user_name = $user_data['user_name'] ?? $user_email;
    $user_joining_date = isset($user_data['uploaded_date']) && !empty($user_data['uploaded_date']) && $user_data['uploaded_date'] !== '0000-00-00 00:00:00'
        ? $user_data['uploaded_date']
        : date('Y-m-d H:i:s');
    
    // Get joining deal details from database
    $deal_query = mysqli_query($connect, "SELECT * FROM joining_deals WHERE deal_code='$joining_deal' AND is_active='YES' LIMIT 1");
    if(!$deal_query || mysqli_num_rows($deal_query) == 0) {
        echo "error: Joining deal not found or inactive";
        exit;
    }
    
    $deal_data = mysqli_fetch_array($deal_query);
    $deal_id = $deal_data['id'];
    $deal_name = $deal_data['deal_name'];
    $deal_type = $deal_data['deal_type'];
    
    // Check if user already has an active mapping (not expired)
    $existing_mapping = mysqli_query($connect, "SELECT * FROM user_joining_deals_mapping 
        WHERE user_email='$user_email' 
        AND mapping_status='ACTIVE' 
        AND deal_code='$joining_deal' 
        AND (expiry_date IS NULL OR expiry_date > NOW()) 
        LIMIT 1");
    if(mysqli_num_rows($existing_mapping) > 0) {
        echo "error: User already has an active mapping for this joining deal";
        exit;
    }
    
    // Load email template using master template loader
    require_once('../common/mailtemplate/joining_deal_templates.php');
    
    $email_data = getJoiningDealEmailTemplate($joining_deal, $user_name, $user_email);
    
    if(!$email_data) {
        echo "error: Invalid joining deal type or template not found";
        exit;
    }
    
    $subject = $email_data['subject'];
    $message = $email_data['message'];
    
    // Send email using PHPMailer with SMTP
    $email_sent = false;
    $error_message = '';
    
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
        $mail->addAddress($user_email, $user_name);
        $mail->addReplyTo(SUPPORT_EMAIL, DEFAULT_FROM_NAME);
        
        // Content
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->CharSet = 'UTF-8';
        
        $mail->send();
        $email_sent = true;
        
    } catch (Exception $e) {
        $error_message = "Email sending failed: " . $e->getMessage();
        error_log("Email sending error: " . $error_message);
        error_log("PHPMailer ErrorInfo: " . $mail->ErrorInfo);
        
        // Fallback to basic mail() function
        try {
            $to = $user_email;
            $from = DEFAULT_FROM_EMAIL;
            $headers = "From: $from\r\n";
            $headers .= "Reply-To: $from\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            
            if(mail($to, $subject, $message, $headers)) {
                $email_sent = true;
                $error_message = '';
            } else {
                $error_message = "Both PHPMailer and basic mail() failed";
            }
        } catch (Exception $e2) {
            $error_message = "All email methods failed: " . $e2->getMessage();
        }
    }
    
    // Get admin email for mapping record
    $mapped_by = isset($_SESSION['admin_email']) ? $_SESSION['admin_email'] : 'admin';
    
    // Calculate start and expiry dates
    // Requirement: start_date should equal created_at (mapping time), expiry = +1 year
    $created_at = date('Y-m-d H:i:s');
    $start_date = $created_at;
    $expiry_date = date('Y-m-d H:i:s', strtotime($created_at . ' +1 year'));
    
    // Check if deal requires payment
    $requires_payment = ($deal_data['total_fees'] > 0);
    $payment_status = $requires_payment ? 'PENDING' : 'PAID';
    
    // Create user joining deal mapping record with new fields
    $mapping_query = mysqli_query($connect, "INSERT INTO user_joining_deals_mapping 
        (user_email, joining_deal_id, deal_code, mapping_status, mapped_by, email_sent, email_sent_date, 
         start_date, expiry_date, payment_status, amount_paid, created_at, notes) 
        VALUES ('$user_email', '$deal_id', '$joining_deal', 'ACTIVE', '$mapped_by', 
        " . ($email_sent ? "'YES'" : "'NO'") . ", " . ($email_sent ? "NOW()" : "NULL") . ", 
        '$start_date', '$expiry_date', '$payment_status', " . ($requires_payment ? $deal_data['total_fees'] : 0) . ", 
        '$created_at', 'Mapped via admin panel')");
    
    if(!$mapping_query) {
        echo "error: Failed to create mapping record - " . mysqli_error($connect);
        exit;
    }
    
    // Auto-map deals if they are configured for this joining deal
    $auto_mapping_success = true;
    $auto_mapping_errors = [];
    $auto_mapping_info = [];
    
    // Check if this joining deal has mapped deals
    if(!empty($deal_data['mw_deal_id']) && $deal_data['mw_deal_id'] > 0) {
        // Check if mapping already exists
        $mw_check_query = mysqli_query($connect, "SELECT * FROM deal_customer_mapping 
            WHERE customer_email='$user_email' AND deal_id=" . intval($deal_data['mw_deal_id']) . " LIMIT 1");
        
        if(mysqli_num_rows($mw_check_query) == 0) {
            // Map MiniWebsite deal
            $mw_mapping_query = mysqli_query($connect, "INSERT INTO deal_customer_mapping 
                (customer_email, deal_id, created_by, created_date) 
                VALUES ('$user_email', " . intval($deal_data['mw_deal_id']) . ", '$mapped_by', NOW())");
            
            if(!$mw_mapping_query) {
                $auto_mapping_success = false;
                $auto_mapping_errors[] = "Failed to map MiniWebsite deal: " . mysqli_error($connect);
            } else {
                // Get deal name for info message
                $mw_deal_name_query = mysqli_query($connect, "SELECT deal_name FROM deals WHERE id=" . intval($deal_data['mw_deal_id']) . " LIMIT 1");
                if($mw_deal_name_query && mysqli_num_rows($mw_deal_name_query) > 0) {
                    $mw_deal_name = mysqli_fetch_array($mw_deal_name_query);
                    $auto_mapping_info[] = "MW deal '" . $mw_deal_name['deal_name'] . "' mapped";
                }
            }
        } else {
            // Mapping already exists
            $auto_mapping_info[] = "MW deal already mapped";
        }
    }
    
    if(!empty($deal_data['franchise_deal_id']) && $deal_data['franchise_deal_id'] > 0) {
        // Check if mapping already exists
        $fr_check_query = mysqli_query($connect, "SELECT * FROM deal_customer_mapping 
            WHERE customer_email='$user_email' AND deal_id=" . intval($deal_data['franchise_deal_id']) . " LIMIT 1");
        
        if(mysqli_num_rows($fr_check_query) == 0) {
            // Map Franchise deal
            $franchise_mapping_query = mysqli_query($connect, "INSERT INTO deal_customer_mapping 
                (customer_email, deal_id, created_by, created_date) 
                VALUES ('$user_email', " . intval($deal_data['franchise_deal_id']) . ", '$mapped_by', NOW())");
            
            if(!$franchise_mapping_query) {
                $auto_mapping_success = false;
                $auto_mapping_errors[] = "Failed to map Franchise deal: " . mysqli_error($connect);
            } else {
                // Get deal name for info message
                $fr_deal_name_query = mysqli_query($connect, "SELECT deal_name FROM deals WHERE id=" . intval($deal_data['franchise_deal_id']) . " LIMIT 1");
                if($fr_deal_name_query && mysqli_num_rows($fr_deal_name_query) > 0) {
                    $fr_deal_name = mysqli_fetch_array($fr_deal_name_query);
                    $auto_mapping_info[] = "Franchise deal '" . $fr_deal_name['deal_name'] . "' mapped";
                }
            }
        } else {
            // Mapping already exists
            $auto_mapping_info[] = "Franchise deal already mapped";
        }
    }
    
    // Log the email sent
    $log_query = mysqli_query($connect, "INSERT INTO email_logs (user_email, email_type, subject, sent_date, status) 
        VALUES ('$user_email', 'joining_deal_$joining_deal', '$subject', NOW(), " . ($email_sent ? "'SENT'" : "'FAILED'") . ")");
    
    if($email_sent) {
        ob_clean(); // Clear any unwanted output
        $success_message = "Email sent successfully to $user_email and joining deal mapping created";
        if(!$auto_mapping_success) {
            $success_message .= ". Warning: Auto-mapping failed - " . implode(", ", $auto_mapping_errors);
        } else if(!empty($auto_mapping_info)) {
            $success_message .= ". " . implode(", ", $auto_mapping_info) . ".";
        }
        echo "success: " . $success_message;
    } else {
        ob_clean(); // Clear any unwanted output
        $error_message_final = "Mapping created but email failed to send. Error: " . $error_message;
        if(!empty($auto_mapping_info)) {
            $error_message_final .= ". " . implode(", ", $auto_mapping_info) . ".";
        }
        echo "error: " . $error_message_final;
    }
} else {
    ob_clean(); // Clear any unwanted output
    echo "error: Invalid request";
}

ob_end_flush(); // Send the output
?>



