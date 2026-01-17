<?php
require('login-connect.php');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include PHPMailer and email configuration
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../common/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$errors = [];
$success_msg = "";

// Function to send email using PHPMailer
function sendEmail($to, $subject, $message, $name = '') {
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
        $mail->addAddress($to, $name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags(str_replace('<br>', "\n", $message));
        
        // Send the email
        return $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

// Check if reset_otp column exists, if not create it
function ensureResetOtpColumnsExist($connect) {
    // Check if reset_otp column exists
    $result = mysqli_query($connect, "SHOW COLUMNS FROM franchisee_login LIKE 'reset_otp'");
    $exists = (mysqli_num_rows($result) > 0);
    
    if (!$exists) {
        // Add reset_otp column
        mysqli_query($connect, "ALTER TABLE franchisee_login ADD COLUMN reset_otp VARCHAR(10) NULL");
    }
    
    // Check if reset_otp_expiry column exists
    $result = mysqli_query($connect, "SHOW COLUMNS FROM franchisee_login LIKE 'reset_otp_expiry'");
    $exists = (mysqli_num_rows($result) > 0);
    
    if (!$exists) {
        // Add reset_otp_expiry column
        mysqli_query($connect, "ALTER TABLE franchisee_login ADD COLUMN reset_otp_expiry BIGINT NULL");
    }
}

// Ensure the required columns exist
ensureResetOtpColumnsExist($connect);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['forgot_password'])) {
    if (!empty($_POST['user_email'])) {
        $user_email = trim($_POST['user_email']);

        // Prevent SQL Injection with Prepared Statement
        $stmt = $connect->prepare("SELECT * FROM franchisee_login WHERE f_user_email = ?");
        $stmt->bind_param("s", $user_email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Generate OTP
            $otp = rand(100000, 999999);
            $expiry = time() + (10 * 60); // 10 minutes from now
            
            // Update OTP in database
            $update_stmt = $connect->prepare("UPDATE franchisee_login SET reset_otp = ?, reset_otp_expiry = ? WHERE f_user_email = ?");
            $update_stmt->bind_param("sis", $otp, $expiry, $user_email);
            $update_result = $update_stmt->execute();
            
            if ($update_result) {
                // Send OTP email
                $subject = "Password Reset OTP - " . $_SERVER['HTTP_HOST'];
                $message = "
                Hi,<br><br>
                Your OTP for password reset is: <b>$otp</b>.<br>
                Please use this OTP to reset your password on " . $_SERVER['HTTP_HOST'] . ".<br><br>
                This OTP is valid for 10 minutes.<br><br>
                Thanks,<br>
                " . $_SERVER['HTTP_HOST'] . " Team
                ";
                
                if (sendEmail($user_email, $subject, $message)) {
                    // Store email in session for OTP verification
                    $_SESSION['otp_email'] = $user_email;
                    
                    $success_msg = "OTP has been sent to your email. Please check your inbox (or spam folder).";
                    echo '<meta http-equiv="refresh" content="2;URL=verify-otp.php">';
                } else {
                    $errors[] = "Error sending email. Please try again later.";
                }
            } else {
                $errors[] = "Error generating OTP. Please try again.";
            }
            
            $update_stmt->close();
        } else {
            $errors[] = "User does not exist. Contact us on westandalone@gmail.com for new account request.";
        }

        $stmt->close();
    } else {
        $errors[] = "Please enter your email.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/layout.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
</head>
<body>
    <div class="login-wrap">
        <div class="login-container">
            <h2 class="heading">
                <a href="login.php"><i class="fa fa-angle-left" aria-hidden="true"></i></a> Forgot Password?
            </h2>
            <p class="text-white">Enter your email address, and we will send you an OTP to reset your password.</p>

            <?php
            if (!empty($errors)) {
                echo '<div class="alert danger error-msg"><ul>';
                foreach ($errors as $error) {
                    echo '<li>' . htmlspecialchars($error) . '</li>';
                }
                echo '</ul></div>';
            }
            if (!empty($success_msg)) {
                echo '<div class="alert success">' . htmlspecialchars($success_msg) . '</div>';
            }
            ?>

            <form action="" method="post" autocomplete="off">
                <div class="mb-4">
                    <input type="email" class="form-control" placeholder="Enter Email id" name="user_email" required>
                </div>

                <input type="submit" name="forgot_password" value="Send OTP" class="btn btn-login">
            </form>
            
            <div class="Reset-foot mt-3">
                <p class="text-white">Remember your password? <a href="login.php" class="resend-code">Login</a></p>
            </div>
        </div>
    </div>
</body>
</html>
