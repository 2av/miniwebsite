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

// Check if email is provided in the URL
if(isset($_GET['email'])) {
    $_SESSION['otp_email'] = $_GET['email'];
}

// Handle OTP verification
if(isset($_POST['verify_otp'])){
    $email = mysqli_real_escape_string($connect, $_POST['email']);
    $otp = mysqli_real_escape_string($connect, $_POST['otp']);

    $query = mysqli_query($connect, "SELECT * FROM customer_login WHERE user_email='$email' AND reset_otp='$otp'");

    if(mysqli_num_rows($query) > 0){
        $row = mysqli_fetch_array($query);

        if(time() > $row['reset_otp_expiry']){
            echo '<div class="alert danger">OTP Expired! Request a new OTP.</div>';
        } else {
            echo '<div class="alert success">OTP Verified! Redirecting to reset password page...</div>';
            echo '<meta http-equiv="refresh" content="2;URL=reset-password.php?email='.$email.'">';
            exit;
        }
    } else {
        echo '<div class="alert danger">Invalid OTP! Try again.</div>';
    }
}

// Handle resend OTP
if(isset($_POST['resend_otp']) && isset($_SESSION['otp_email'])) {
    $email = mysqli_real_escape_string($connect, $_SESSION['otp_email']);
    
    // Check if email exists
    $query = mysqli_query($connect, "SELECT * FROM customer_login WHERE user_email='$email'");
    
    if(mysqli_num_rows($query) > 0) {
        // Generate new OTP
        $otp = rand(100000, 999999);
        $expiry = time() + (10 * 60); // 10 minutes from now
        
        // Update OTP in database
        $update = mysqli_query($connect, "UPDATE customer_login SET reset_otp='$otp', reset_otp_expiry='$expiry' WHERE user_email='$email'");
        
        if($update) {
            // Send OTP email using PHPMailer
            $subject = "Password Reset OTP - " . $_SERVER['HTTP_HOST'];
            $message = "
            Hi,<br><br>
            Your new OTP for password reset is: <b>$otp</b>.<br>
            Please use this OTP to reset your password on " . $_SERVER['HTTP_HOST'] . ".<br><br>
            This OTP is valid for 10 minutes.<br><br>
            Thanks,<br>
            " . $_SERVER['HTTP_HOST'] . " Team
            ";
            
            if(sendEmail($email, $subject, $message)) {
                echo '<div class="alert success">New OTP has been sent to your email. Please check your inbox (or spam folder).</div>';
            } else {
                echo '<div class="alert danger">Error sending email. Please try again later.</div>';
            }
        } else {
            echo '<div class="alert danger">Error generating new OTP. Please try again.</div>';
        }
    } else {
        echo '<div class="alert danger">Email not found. Please check your email address.</div>';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Verify OTP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/layout.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
</head>
<body>
    <div class="login-wrap">
        <div class="login-container">
            <h2 class="heading"><a href="forgot-password.php"><i class="fa fa-angle-left" aria-hidden="true"></i></a> Verify OTP</h2>
            <p class="text-white">Enter the OTP sent to your email address</p>
            
            <form action="" method="post">
                <div class="mb-4">
                    <input type="hidden" name="email" value="<?php echo isset($_SESSION['otp_email']) ? $_SESSION['otp_email'] : (isset($_GET['email']) ? $_GET['email'] : ''); ?>" required>
                    <input type="text" class="form-control" name="otp" placeholder="Enter OTP" required>
                </div>
                <input type="submit" name="verify_otp" value="Verify OTP" class="btn btn-login">
            </form>
            
            <form action="" method="post" class="mt-3">
                <div class="Reset-foot">
                    <p>Didn't receive OTP? <input type="submit" name="resend_otp" value="Resend OTP" class="resend-code"></p>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
