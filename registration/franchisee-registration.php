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

if (isset($_SESSION['sender_token'])) {
    $sender_token = $_SESSION['sender_token'];
} else {
    $sender_token = '';
}

// Define error messages
$errors = [];

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

// Check for OTP expiration (10 minutes)
if (isset($_SESSION['registration_otp_time']) && (time() - $_SESSION['registration_otp_time'] > 600)) {
    unset($_SESSION['registration_otp']);
    unset($_SESSION['registration_data']);
    unset($_SESSION['registration_otp_time']);
    $errors[] = "OTP has expired. Please try again.";
}

// Handle OTP resend request
if (isset($_GET['resend']) && $_GET['resend'] == 'true' && isset($_SESSION['registration_data'])) {
    // Generate new OTP
    $otp = rand(100000, 999999);
    $_SESSION['registration_otp'] = $otp;
    $_SESSION['registration_otp_time'] = time();
    
    // Get user data from session
    $user_name = $_SESSION['registration_data']['user_name'];
    $user_email = $_SESSION['registration_data']['user_email'];
    
    // Send OTP email
    $subject = $_SERVER['HTTP_HOST'] . " - Your New OTP for Registration";
    $message = '
    Hi ' . $user_name . ',<br><br>
    Your new OTP for registration on ' . $_SERVER['HTTP_HOST'] . ' is: <b>' . $otp . '</b><br><br>
    This OTP is valid for 10 minutes.<br><br>
    Thanks,<br>' . $_SERVER['HTTP_HOST'] . ' Team';

    if (sendEmail($user_email, $subject, $message, $user_name)) {
        echo '<div class="alert text-success">New OTP has been sent to your email.</div>';
    } else {
        $errors[] = "Error sending OTP email. Please try again.";
    }
}

// Handle OTP verification
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify_otp'])) {
    $entered_otp = trim($_POST['otp']);
    
    if (empty($entered_otp)) {
        $errors[] = "Please enter the OTP.";
    } elseif (!isset($_SESSION['registration_otp']) || !isset($_SESSION['registration_data'])) {
        $errors[] = "OTP session expired. Please try again.";
    } elseif ($_SESSION['registration_otp'] != $entered_otp) {
        $errors[] = "Invalid OTP. Please try again.";
    } else {
        // OTP is valid, proceed with registration
        $user_data = $_SESSION['registration_data'];

        $user_name = mysqli_real_escape_string($connect, $user_data['user_name']);
        $user_email = mysqli_real_escape_string($connect, $user_data['user_email']);
        $user_contact = mysqli_real_escape_string($connect, $user_data['user_contact']);
        $plain_password = $user_data['plain_password'];
        $referrer_email = $user_data['referrer_email'];
        $referrer_code = $user_data['referrer_code'] ?? '';

        // Final check: global uniqueness in unified user_details table (any role)
        $safe_email = mysqli_real_escape_string($connect, $user_email);
        $safe_contact = mysqli_real_escape_string($connect, $user_contact);

        $final_email_check = mysqli_query($connect, "SELECT role, email FROM user_details WHERE email='$safe_email' LIMIT 1");
        if ($final_email_check && mysqli_num_rows($final_email_check) > 0) {
            $email_data = mysqli_fetch_array($final_email_check);
            $source = ucfirst(strtolower($email_data['role'] ?? 'user'));
            $errors[] = "This email address is already registered as a $source. Please use a different email.";
        }
        
        $final_mobile_check = mysqli_query($connect, "SELECT role, phone FROM user_details WHERE phone='$safe_contact' LIMIT 1");
        if ($final_mobile_check && mysqli_num_rows($final_mobile_check) > 0) {
            $mobile_data = mysqli_fetch_array($final_mobile_check);
            $source = ucfirst(strtolower($mobile_data['role'] ?? 'user'));
            $errors[] = "This mobile number is already registered as a $source. Please use a different mobile number.";
        }
        
        if (!empty($errors)) {
            // Don't proceed with registration if validation fails
        } else {
            // Generate unique referral code for new user
            $referral_code = strtoupper(substr(md5($user_email . time()), 0, 8));

            // Insert user with referral code into legacy franchisee_login (for backward compatibility)
            $insert = mysqli_query($connect, "INSERT INTO franchisee_login 
            (f_user_email, f_user_name, f_user_password, f_user_contact, f_user_active, f_user_token, referral_code, referred_by,referral_type) 
            VALUES ('$user_email', '$user_name', '$plain_password', '$user_contact', 'YES', '$sender_token', '$referral_code', '$referrer_email','')");

        if ($insert) {
            // If there's a referrer, create referral record with dynamic amount
            if(!empty($referrer_email)) {
                $deal_amount = 250.00; // Default amount
                
                // First, check if there are any deals mapped to the referrer (referred_by field)
                $mapped_deal_query = mysqli_query($connect, "SELECT d.bonus_amount FROM deals d 
                    INNER JOIN deal_customer_mapping dcm ON d.id = dcm.deal_id 
                    WHERE dcm.customer_email = '" . mysqli_real_escape_string($connect, $referrer_email) . "' 
                    AND d.deal_status = 'Active' 
                    AND d.plan_type = 'Franchisee'
                    ORDER BY dcm.created_date DESC LIMIT 1");
                
                if(mysqli_num_rows($mapped_deal_query) > 0) {
                    // Use mapped deal amount
                    $mapped_deal_data = mysqli_fetch_array($mapped_deal_query);
                    $deal_amount = $mapped_deal_data['bonus_amount'] > 0 ? $mapped_deal_data['bonus_amount'] : 250.00;
                } else {
                    // Check for default deal DFRAN101
                    $default_deal_query = mysqli_query($connect, "SELECT bonus_amount FROM deals WHERE coupon_code='DFRAN101' AND deal_status='Active'");
                    if(mysqli_num_rows($default_deal_query) > 0) {
                        $default_deal_data = mysqli_fetch_array($default_deal_query);
                        $deal_amount = $default_deal_data['bonus_amount'] > 0 ? $default_deal_data['bonus_amount'] : 250.00;
                    }
                }
                
                // Insert into referral_earnings with collaboration flag set to YES for franchisee registrations
                mysqli_query($connect, "INSERT INTO referral_earnings 
                    (referrer_email, referred_email, referral_date, status, amount, is_collaboration) 
                    VALUES ('$referrer_email', '$user_email', NOW(), 'Pending', '$deal_amount', 'YES')");
            }
            
            // Clear all registration session data
            unset($_SESSION['registration_otp']);
            unset($_SESSION['registration_data']);
            unset($_SESSION['registration_otp_time']);
            
            // Set success flag for the success page
            $_SESSION['registration_success'] = true;
            
            // Send welcome email
            $subject = "Welcome to MiniWebsite.in – Your Franchisee Account is Ready!";
            $message = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                <p style="color: #333; font-size: 16px; line-height: 1.6;">Hi <strong>' . htmlspecialchars($user_name) . '</strong>,</p>
                
                <p style="color: #333; font-size: 16px; line-height: 1.6;">Thank you for registering as a franchisee with MiniWebsite.in.</p>
                
                <p style="color: #333; font-size: 16px; line-height: 1.6;">We are excited to have you on board! Your franchisee account has been successfully created. You can now log in using your email and password at the link below:</p>
                
                <div style="background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h3 style="color: #333; font-size: 18px; margin-top: 0; margin-bottom: 15px;">🔐 Your Login Details:</h3>
                    <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 10px 0;"><strong>Email ID:</strong> ' . htmlspecialchars($user_email) . '</p>
                    <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 10px 0;"><strong>Password:</strong> ' . htmlspecialchars($user_password) . '</p>
                    <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 10px 0;">👉 <a href="https://' . $_SERVER['HTTP_HOST'] . '/panel/franchisee-login/login.php" style="color: #007bff; text-decoration: none;">Click here to login</a></p>
                </div>
                
                <br>
                
                <p style="color: #333; font-size: 16px; line-height: 1.6;"><strong>Follow these simple steps to activate your franchise:</strong></p>
                
                <p style="color: #333; font-size: 16px; line-height: 1.6;"><strong>1. Pay the One-Time Franchise Fee (Non-Refundable)</strong><br>
                Amount: ₹5,100 + 18% GST = ₹6,018<br>
                <a href="https://' . $_SERVER['HTTP_HOST'] . '/franchise_agreement.php?email=' . urlencode($user_email) . '" style="color: #007bff; text-decoration: none;">(Click to Pay)</a></p>
                
                <p style="color: #333; font-size: 16px; line-height: 1.6;"><strong>2. After payment, complete your document Verification from your Dashboard.</strong></p>
                
                <p style="color: #333; font-size: 16px; line-height: 1.6;"><strong>3. After the documents get verified, you can access your Franchise Kit and Onboarding Material from your dashboard only.</strong></p>
                
                <br>
                
                <p style="color: #333; font-size: 16px; line-height: 1.6;">That\'s it! Once these steps are completed, you are officially part of the MiniWebsite.in franchise network. You can begin building your business and start earning right away.</p>
                
                <p style="color: #333; font-size: 16px; line-height: 1.6;">If you have any questions or need assistance, feel free to reach out to our support team.</p>
                
                <br>
                
                <p style="color: #333; font-size: 16px; line-height: 1.6;">Best regards,<br>
                Team MiniWebsite.in<br>
                www.miniwebsite.in</p>
            </div>';

            sendEmail($user_email, $subject, $message, $user_name);
            
            // Redirect to franchisee registration success page
            header('Location: franchisee-registration-success.php');
            exit();
            } else {
                $errors[] = "Error in registration. Please try again.";
            }
        }
    }
}

// Handle initial registration form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $user_name = trim($_POST['user_name']);
    $user_email = trim($_POST['user_email']);
    $user_contact = trim($_POST['user_contact']);
    $user_password = trim($_POST['user_password']);

    // Validation
    if (empty($user_name)) {
        $errors[] = "Name is required.";
    }
    
    if (empty($user_email) || !filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    
    if (empty($user_contact) || !preg_match("/^[0-9]{10}$/", $user_contact)) {
        $errors[] = "Valid 10-digit mobile number is required.";
    }
    
    if (empty($user_password) || strlen($user_password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }

    if (empty($errors)) {
        // Global uniqueness: check email and mobile in unified user_details table (any role)
        $safe_email = mysqli_real_escape_string($connect, $user_email);
        $safe_contact = mysqli_real_escape_string($connect, $user_contact);

        $email_check = mysqli_query($connect, "SELECT role, email FROM user_details WHERE email='$safe_email' LIMIT 1");
        if ($email_check && mysqli_num_rows($email_check) > 0) {
            $email_data = mysqli_fetch_array($email_check);
            $source = ucfirst(strtolower($email_data['role'] ?? 'user'));
            $errors[] = "This email address is already registered as a $source. Please use a different email.";
        }
        
        $mobile_check = mysqli_query($connect, "SELECT role, phone FROM user_details WHERE phone='$safe_contact' LIMIT 1");
        if ($mobile_check && mysqli_num_rows($mobile_check) > 0) {
            $mobile_data = mysqli_fetch_array($mobile_check);
            $source = ucfirst(strtolower($mobile_data['role'] ?? 'user'));
            $errors[] = "This mobile number is already registered as a $source. Please use a different mobile number.";
        }
        
        if (empty($errors)) {
            // Generate OTP
            $otp = rand(100000, 999999);
            // Don't hash password - store as plain text to match existing functionality
            $plain_password = $user_password;
            
            // Get referrer information from URL or form input
            $referrer_code = '';
            if(isset($_GET['ref']) && !empty($_GET['ref'])) {
                $referrer_code = mysqli_real_escape_string($connect, $_GET['ref']);
            } elseif(isset($_POST['referral_code']) && !empty($_POST['referral_code'])) {
                $referrer_code = mysqli_real_escape_string($connect, $_POST['referral_code']);
            }
            
            $referrer_email = '';
            
            if(!empty($referrer_code)) {
                // First, try to find referrer in customer_login table
                $referrer_query = mysqli_query($connect, "SELECT user_email FROM customer_login WHERE referral_code='$referrer_code'");
                if(mysqli_num_rows($referrer_query) > 0) {
                    $referrer_data = mysqli_fetch_array($referrer_query);
                    $referrer_email = $referrer_data['user_email'];
                }
            }
            
            // Store registration data in session
            $_SESSION['registration_otp'] = $otp;
            $_SESSION['registration_data'] = [
                'user_name' => $user_name,
                'user_email' => $user_email,
                'user_contact' => $user_contact,
                'plain_password' => $plain_password,
                'referrer_email' => $referrer_email,
                'referrer_code' => $referrer_code
            ];
            $_SESSION['registration_otp_time'] = time();
            
            // Send OTP email
            $subject = $_SERVER['HTTP_HOST'] . " - Your OTP for Registration";
            $message = '
            Hi ' . $user_name . ',<br><br>
            Your OTP for registration on ' . $_SERVER['HTTP_HOST'] . ' is: <b>' . $otp . '</b><br><br>
            This OTP is valid for 10 minutes.<br><br>
            Thanks,<br>' . $_SERVER['HTTP_HOST'] . ' Team';

            if (sendEmail($user_email, $subject, $message, $user_name)) {
                //echo '<div class="alert text-success">OTP has been sent to your email. Please verify to complete registration.</div>';
            } else {
                $errors[] = "Error sending OTP email. Please try again.";
                unset($_SESSION['registration_otp']);
                unset($_SESSION['registration_data']);
            }
        } else {
            $errors[] = "Account already exists! Please log in.";
        }
    }
}
?>

<!-- Include custom styles for password toggle -->
<link rel="stylesheet" href="login-styles.css">

<div class="login-wrap">
    <div class="login-container">
        <h2 class="heading"> <a href="franchisee-login/login.php"><i class="fa fa-angle-left" aria-hidden="true"></i></a> Create A franchisee  Account</h2>
        <p class="text-white"> Fill this form to register as a franchisee</p>

        <?php if (isset($_SESSION['registration_otp']) && isset($_SESSION['registration_data'])): ?>
        <!-- OTP Verification Form -->
        <form method="post" autocomplete="off">
            <div class="mb-4">
                <h4 class="text-white">Enter OTP sent to your email</h4>
                <p class="text-white"><?php echo substr($_SESSION['registration_data']['user_email'], 0, 3) . '****' . substr($_SESSION['registration_data']['user_email'], strpos($_SESSION['registration_data']['user_email'], '@')); ?></p>
                <input type="text" class="form-control" placeholder="Enter OTP" name="otp" required>
            </div>
            <input type="submit" name="verify_otp" class="btn btn-login" value="VERIFY OTP">
            <div class="mt-3">
                <p class="text-white">Didn't receive OTP? <a href="create-account.php?resend=true" class="text-primary">Resend OTP</a></p>
            </div>
            <div class="mt-3">
                <p class="text-white small">After verification, you'll be redirected to the login page.</p>
            </div>
        </form>
        <?php else: ?>
        <!-- Registration Form -->
        <form method="post" autocomplete="off">
            <div class="mb-3">
                <input type="text" class="form-control" placeholder="Full Name" name="user_name" value="<?php echo isset($_POST['user_name']) ? htmlspecialchars($_POST['user_name']) : ''; ?>" required>
            </div>
            <div class="mb-3">
                <input type="email" class="form-control" placeholder="Email Address" name="user_email" value="<?php echo isset($_POST['user_email']) ? htmlspecialchars($_POST['user_email']) : ''; ?>" required>
            </div>
            <div class="mb-3">
                <input type="text" class="form-control" placeholder="Mobile Number" name="user_contact" value="<?php echo isset($_POST['user_contact']) ? htmlspecialchars($_POST['user_contact']) : ''; ?>" required>
            </div>
            <div class="mb-3 position-relative">
                <div class="input-group mb-2">
                    <input type="password" class="form-control" placeholder="Password" name="user_password" id="user_password" required>
                    <div class="input-group-prepend">
                        <div class="input-group-text password-toggle" id="password-toggle" onclick="showpassword()" title="Show/Hide Password">
                            <i class="fa fa-eye eye-closed" aria-hidden="true"></i>
                            <i class="fa fa-eye-slash eye-open" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <?php 
                $ref_code = isset($_GET['ref']) ? htmlspecialchars($_GET['ref']) : '';
                $is_readonly = !empty($ref_code) ? 'readonly' : '';
                ?>
                <input type="text" class="form-control" placeholder="Referral Code (Optional)" name="referral_code" value="<?php echo $ref_code; ?>" <?php echo $is_readonly; ?>>
               
            </div>
            <input type="submit" name="register" class="btn btn-login" value="CREATE ACCOUNT">
        </form>
        <?php endif; ?>

        <?php 
        if (!empty($errors)) {
            echo '<div class="alert danger text-danger"><ul>';
            foreach ($errors as $error) {
                echo '<li>' . $error . '</li>';
            }
            echo '</ul></div>';
        }
        ?>
    </div>
</div>

<script>
    function showpassword(){
        const passwordField = document.getElementById("user_password");
        const passwordToggle = document.getElementById("password-toggle");

        if(passwordField.type === "password") {
            // Show password
            passwordField.type = "text";
            passwordToggle.classList.add("visible");
        } else {
            // Hide password
            passwordField.type = "password";
            passwordToggle.classList.remove("visible");
        }
    }
</script>














