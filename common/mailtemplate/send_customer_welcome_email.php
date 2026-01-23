<?php
/**
 * Send Customer Welcome Email Function
 * This function sends a welcome email to newly created customers
 */

// Include PHPMailer and email configuration (same as create-account.php)
require_once __DIR__ . '/../../vendor/autoload.php';
require_once(__DIR__ . '/../email_config.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once('customer_account_created.php');

function sendCustomerWelcomeEmail($customer_name, $customer_email, $customer_password, $franchisee_name) {
    try {
        // Check if PHPMailer is available
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return sendCustomerWelcomeEmailWithPHPMailer($customer_name, $customer_email, $customer_password, $franchisee_name);
        }
        
        // Fallback to simple mail function
        $login_url = 'https://' . $_SERVER['HTTP_HOST'] . '/panel/login/login.php';
        $subject = "Welcome to miniwebsite.in";
        
        // Simple email message
        $message = '
        
        <p>Hi <strong>' . htmlspecialchars($customer_name) . '</strong>,</p>
        <p>Thanks you for registering on miniwebsite.in</p>
        <p>Your account has been created successfully. You can now login using your email and password.</p>
        
        <ul>
            <li><strong>Email:</strong> ' . htmlspecialchars($customer_email) . '</li>
            <li><strong>Password:</strong> ' . htmlspecialchars($customer_password) . '</li>
            <li><strong>Status:</strong> Active</li>
        </ul>

        <h3 style="color: #d32f2f; background: #ffebee; padding: 10px; border-left: 4px solid #d32f2f; margin: 20px 0;">⚠️ IMPORTANT: Bank Account Details Required</h3>
        <p><strong>It is mandatory to update your bank account details from your dashboard.</strong></p>
        <p>To receive payments and commissions, you must add your bank account information:</p>
        <ul>
            <li>Account Holder Name</li>
            <li>Bank Name</li>
            <li>Account Number</li>
            <li>IFSC Code</li>
            <li>Account Type</li>
        </ul>
        <p>Please log in to your account and update your bank details immediately to ensure smooth transactions.</p>

        <strong>SECURITY REMINDER:</strong>
        <p>For your security, please change your password after your first login. Keep your login credentials safe and never share them with anyone.</p>

        
        <p><a href="' . htmlspecialchars($login_url) . '" style="background:rgb(226, 213, 37); color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Access Your Account</a></p>
        
        <p>Best regards,<br>The MiniWebsite Team</p>';
        
        $headers = "From: " . DEFAULT_FROM_NAME . " <" . DEFAULT_FROM_EMAIL . ">\r\n";
        $headers .= "Reply-To: " . SUPPORT_EMAIL . "\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        $mail_sent = mail($customer_email, $subject, $message, $headers);
        
        if ($mail_sent) {
            error_log("Welcome email sent successfully via mail() to: " . $customer_email);
            return true;
        } else {
            error_log("Failed to send welcome email to: " . $customer_email);
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Error sending welcome email: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email via SMTP using socket connection
 */
function sendEmailViaSMTP($to_email, $subject, $html_content, $text_content) {
    try {
        $smtp_host = SMTP_HOST;
        $smtp_port = SMTP_PORT;
        $smtp_username = SMTP_USERNAME;
        $smtp_password = SMTP_PASSWORD;
        $from_email = DEFAULT_FROM_EMAIL;
        $from_name = DEFAULT_FROM_NAME;
        
        // Create socket connection
        $socket = fsockopen($smtp_host, $smtp_port, $errno, $errstr, 30);
        if (!$socket) {
            error_log("SMTP Connection failed: $errstr ($errno)");
            return false;
        }
        
        // Read initial response
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '220') {
            error_log("SMTP Initial response error: $response");
            fclose($socket);
            return false;
        }
        
        // Send EHLO
        fputs($socket, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
        $response = fgets($socket, 515);
        
        // Start TLS if using SSL
        if (SMTP_SECURE == 'ssl') {
            fputs($socket, "STARTTLS\r\n");
            $response = fgets($socket, 515);
            if (substr($response, 0, 3) != '220') {
                error_log("STARTTLS failed: $response");
                fclose($socket);
                return false;
            }
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            
            // Send EHLO again after TLS
            fputs($socket, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
            $response = fgets($socket, 515);
        }
        
        // Authenticate
        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '334') {
            error_log("AUTH LOGIN failed: $response");
            fclose($socket);
            return false;
        }
        
        fputs($socket, base64_encode($smtp_username) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '334') {
            error_log("Username authentication failed: $response");
            fclose($socket);
            return false;
        }
        
        fputs($socket, base64_encode($smtp_password) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '235') {
            error_log("Password authentication failed: $response");
            fclose($socket);
            return false;
        }
        
        // Send MAIL FROM
        fputs($socket, "MAIL FROM: <$from_email>\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '250') {
            error_log("MAIL FROM failed: $response");
            fclose($socket);
            return false;
        }
        
        // Send RCPT TO
        fputs($socket, "RCPT TO: <$to_email>\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '250') {
            error_log("RCPT TO failed: $response");
            fclose($socket);
            return false;
        }
        
        // Send DATA
        fputs($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '354') {
            error_log("DATA command failed: $response");
            fclose($socket);
            return false;
        }
        
        // Send email headers and content
        $email_data = "From: $from_name <$from_email>\r\n";
        $email_data .= "To: $to_email\r\n";
        $email_data .= "Subject: $subject\r\n";
        $email_data .= "MIME-Version: 1.0\r\n";
        $email_data .= "Content-Type: text/html; charset=UTF-8\r\n";
        $email_data .= "Reply-To: " . SUPPORT_EMAIL . "\r\n";
        $email_data .= "\r\n";
        $email_data .= $html_content . "\r\n";
        $email_data .= ".\r\n";
        
        fputs($socket, $email_data);
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '250') {
            error_log("Email sending failed: $response");
            fclose($socket);
            return false;
        }
        
        // Quit
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        error_log("Welcome email sent successfully via SMTP to: " . $to_email);
        return true;
        
    } catch (Exception $e) {
        error_log("SMTP error: " . $e->getMessage());
        return false;
    }
}

/**
 * Alternative function using PHPMailer (if available)
 */
function sendCustomerWelcomeEmailWithPHPMailer($customer_name, $customer_email, $customer_password, $franchisee_name) {
    try {
        // Include PHPMailer (same path as create-account.php)
        require_once __DIR__ . '/../../vendor/autoload.php';
        
         
        
        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);
        
        // Server settings (same as create-account.php)
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = SMTP_AUTH;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        // Additional SMTP settings for better compatibility (same as create-account.php)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom(SMTP_USERNAME, 'MiniWebsite Support');
        $mail->addAddress($customer_email, $customer_name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "🎉 Welcome to MiniWebsite - Your Account is Ready!";
        
        // Generate login URL
        $login_url = 'https://' . $_SERVER['HTTP_HOST'] . '/panel/login/login.php';
        
        // Simple email message (similar to create-account.php style)
        $message = '
        <h2>Welcome to MiniWebsite!</h2>
        <p>Hello <strong>' . htmlspecialchars($customer_name) . '</strong>,</p>
        <p>Great news! Your MiniWebsite account has been successfully created by <strong>' . htmlspecialchars($franchisee_name) . '</strong>.</p>
        
        <h3>Your Account Details:</h3>
        <ul>
            <li><strong>Email:</strong> ' . htmlspecialchars($customer_email) . '</li>
            <li><strong>Password:</strong> ' . htmlspecialchars($customer_password) . '</li>
            <li><strong>Status:</strong> Active</li>
        </ul>
        
        <div style="background: #ffebee; border: 1px solid #d32f2f; border-radius: 5px; padding: 15px; margin: 20px 0;">
            <h3 style="color: #d32f2f; margin: 0 0 10px 0;">⚠️ IMPORTANT: Bank Account Details Required</h3>
            <p style="margin: 5px 0;"><strong>It is mandatory to update your bank account details from your dashboard.</strong></p>
            <p style="margin: 5px 0;">To receive payments and commissions, you must add your bank account information:</p>
            <ul style="margin: 10px 0;">
                <li>Account Holder Name</li>
                <li>Bank Name</li>
                <li>Account Number</li>
                <li>IFSC Code</li>
                <li>Account Type</li>
            </ul>
            <p style="margin: 5px 0;">Please log in to your account and update your bank details immediately to ensure smooth transactions.</p>
        </div>
        
        <p><a href="' . htmlspecialchars($login_url) . '" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Access Your Account</a></p>
        
        <p>Best regards,<br>The MiniWebsite Team</p>';
        
        $mail->Body = $message;
        $mail->AltBody = strip_tags(str_replace('<br>', "\n", $message));
        
        // Send the email
        $mail->send();
        error_log("Welcome email sent successfully via PHPMailer to: " . $customer_email);
        return true;
        
    } catch (Exception $e) {
        error_log("PHPMailer error: " . $e->getMessage());
        return false;
    }
}
?>
