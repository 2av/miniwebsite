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

$errors = [];

if (isset($_SESSION['sender_token'])) {
    $sender_token = $_SESSION['sender_token'];
} else {
    $sender_token = '';
}

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
    $result = mysqli_query($connect, "SHOW COLUMNS FROM customer_login LIKE 'reset_otp'");
    $exists = (mysqli_num_rows($result) > 0);
    
    if (!$exists) {
        // Add reset_otp column
        mysqli_query($connect, "ALTER TABLE customer_login ADD COLUMN reset_otp VARCHAR(10) NULL");
    }
    
    // Check if reset_otp_expiry column exists
    $result = mysqli_query($connect, "SHOW COLUMNS FROM customer_login LIKE 'reset_otp_expiry'");
    $exists = (mysqli_num_rows($result) > 0);
    
    if (!$exists) {
        // Add reset_otp_expiry column
        mysqli_query($connect, "ALTER TABLE customer_login ADD COLUMN reset_otp_expiry BIGINT NULL");
    }
}

// Ensure the required columns exist
ensureResetOtpColumnsExist($connect);

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['forgot_password'])) {
    $user_email = trim($_POST['user_email']);

    // Validate email
    if (empty($user_email) || !filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (empty($errors)) {
        $user_email = mysqli_real_escape_string($connect, $user_email);
        $query = mysqli_query($connect, "SELECT * FROM customer_login WHERE user_email='$user_email'");
        
        if ($query && mysqli_num_rows($query) > 0) {
            $row = mysqli_fetch_assoc($query);
            $otp = rand(100000, 999999); // Generate OTP
            $expiry = time() + (10 * 60); // 10 minutes from now

            // Save OTP to database
            $update = mysqli_query($connect, "UPDATE customer_login SET reset_otp='$otp', reset_otp_expiry='$expiry' WHERE user_email='$user_email'");
            
            if ($update) {
                // Store email in session for OTP verification page
                $_SESSION['otp_email'] = $user_email;

                // Email script using PHPMailer
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
                    echo '<div class="alert success">OTP has been sent to your email. Please check your inbox (or spam folder).</div>';
                    echo '<meta http-equiv="refresh" content="2;URL=verify-otp.php?email='.$user_email.'">';
                } else {
                    echo '<div class="alert danger">Error sending email. Please try again later.</div>';
                }
            } else {
                echo '<div class="alert danger">Error generating OTP. Please try again.</div>';
            }
        } else {
            echo '<div class="alert info">Account does not exist! Please check your email or create a new account.</div>';
        }
    }
}
?>

<div class="login-wrap">
    <div class="login-container">
        <h2 class="heading">
            <a href="login.php"><i class="fa fa-angle-left" aria-hidden="true"></i></a> Forgot Password?
        </h2>
        <p class="text-white">Enter the email address associated with your account, and we will send you an OTP for verification.</p>

        <?php
        if (!empty($errors)) {
            echo '<div class="alert danger"><ul>';
            foreach ($errors as $error) {
                echo '<li>' . htmlspecialchars($error) . '</li>';
            }
            echo '</ul></div>';
        }
        ?>

        <form action="" method="post" autocomplete="off">
            <p class="text-white">Please enter your Email Id</p>
            <div class="mb-4">
                <input type="email" class="form-control" placeholder="Enter Email id" name="user_email" required>
            </div>

            <input type="submit" name="forgot_password" value="Send OTP" class="btn btn-login">
        </form>
    </div>
</div>
