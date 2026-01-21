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
        $hashed_password = $user_data['hashed_password'];
        $referrer_email = !empty($user_data['referrer_email']) ? mysqli_real_escape_string($connect, trim($user_data['referrer_email'])) : '';

        // Final check: Verify email and mobile are still available (safety check) in unified table
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

            // Insert user with referral code into legacy customer_login table (for backward compatibility)
            // Ensure referred_by is properly set (referrer_email is already escaped above)
            $insert = mysqli_query($connect, "INSERT INTO customer_login 
            (user_email, user_name, user_password, user_contact, user_active, sender_token, referral_code, referred_by) 
            VALUES ('$user_email', '$user_name', '$hashed_password', '$user_contact', 'YES', '$sender_token', '$referral_code', " . (!empty($referrer_email) ? "'$referrer_email'" : "''") . ")");

            if ($insert) {
            // Also insert into unified user_details table (role = CUSTOMER)
            $customer_id = mysqli_insert_id($connect);
            $ip_address = mysqli_real_escape_string($connect, $_SERVER['REMOTE_ADDR'] ?? '');
            $status = 'ACTIVE';

            // Insert into user_details with referred_by field
            // Use ON DUPLICATE KEY UPDATE to ensure referred_by is set even if record exists
            $referred_by_escaped = !empty($referrer_email) ? mysqli_real_escape_string($connect, $referrer_email) : '';
            $referred_by_value = !empty($referred_by_escaped) ? "'$referred_by_escaped'" : "''";
            $insert_user_details = mysqli_query($connect, "
                INSERT INTO user_details 
                    (role, email, phone, name, password, ip, status, created_at, legacy_customer_id, referred_by)
                VALUES
                    ('CUSTOMER', '$user_email', '$user_contact', '$user_name', '$hashed_password', '$ip_address', '$status', NOW(), ".(int)$customer_id.", $referred_by_value)
                ON DUPLICATE KEY UPDATE 
                    referred_by = $referred_by_value,
                    phone = '$user_contact',
                    name = '$user_name',
                    password = '$hashed_password',
                    updated_at = NOW()
            ");

            // If there's a referrer, create referral record with dynamic amount
            if(!empty($referrer_email)) {
                $deal_amount = 250.00; // Default amount
                
                // First, check if there are any deals mapped to the referrer (referred_by field)
                $mapped_deal_query = mysqli_query($connect, "SELECT d.bonus_amount FROM deals d 
                    INNER JOIN deal_customer_mapping dcm ON d.id = dcm.deal_id 
                    WHERE dcm.customer_email = '" . mysqli_real_escape_string($connect, $referrer_email) . "' 
                    AND d.deal_status = 'Active' 
                    AND d.plan_type = 'MiniWebsite'
                    ORDER BY dcm.created_date DESC LIMIT 1");
                
                if(mysqli_num_rows($mapped_deal_query) > 0) {
                    // Use mapped deal amount
                    $mapped_deal_data = mysqli_fetch_array($mapped_deal_query);
                    $deal_amount = $mapped_deal_data['bonus_amount'] > 0 ? $mapped_deal_data['bonus_amount'] : 250.00;
                } else {
                    // Check for default deal DMW001
                    $default_deal_query = mysqli_query($connect, "SELECT bonus_amount FROM deals WHERE coupon_code='DMW001' AND deal_status='Active'");
                    if(mysqli_num_rows($default_deal_query) > 0) {
                        $default_deal_data = mysqli_fetch_array($default_deal_query);
                        $deal_amount = $default_deal_data['bonus_amount'] > 0 ? $default_deal_data['bonus_amount'] : 250.00;
                    }
                }
                
                mysqli_query($connect, "INSERT INTO referral_earnings 
                    (referrer_email, referred_email, referral_date, status, amount) 
                    VALUES ('$referrer_email', '$user_email', NOW(), 'Pending', '$deal_amount')");
            }
            
            // Clear all registration session data
            unset($_SESSION['registration_otp']);
            unset($_SESSION['registration_data']);
            unset($_SESSION['registration_otp_time']);
            
            // Auto-login the user after successful registration
            $_SESSION['user_email'] = $user_email;
            $_SESSION['is_logged_in'] = true;
            $_SESSION['login_time'] = time();
            $_SESSION['user_name'] = $user_name;
            $_SESSION['user_contact'] = $user_contact;
            $_SESSION['referral_code'] = $referral_code; // Set the generated referral code
            $_SESSION['just_registered'] = true; // Flag to show welcome message
            
            // Send welcome email
            $subject = "Welcome to " . $_SERVER['HTTP_HOST'];
            $message = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                <p style="color: #333; font-size: 16px; line-height: 1.6;">Hi <strong>' . htmlspecialchars($user_name) . '</strong>,</p>
                
                <p style="color: #333; font-size: 16px; line-height: 1.6;">Thank you for registering on ' . $_SERVER['HTTP_HOST'] . '.</p>
                
                <p style="color: #333; font-size: 16px; line-height: 1.6;">Your account has been created successfully and you have been automatically logged in!</p>
                
                <div style="background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h3 style="color: #333; font-size: 18px; margin-top: 0; margin-bottom: 15px;">🔐 Your Login Details:</h3>
                    <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 10px 0;"><strong>Email ID:</strong> ' . htmlspecialchars($user_email) . '</p>
                    <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 10px 0;"><strong>Password:</strong> ' . htmlspecialchars($user_password) . '</p>
                    <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 10px 0;">👉 <a href="https://' . $_SERVER['HTTP_HOST'] . '/customer/dashboard/index.php" style="color: #007bff; text-decoration: none;">Click here to access your dashboard</a></p>
                </div>
                
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
                
                <p style="color: #333; font-size: 16px; line-height: 1.6;">You can now start creating your Mini Website from your dashboard.</p>
                
                <br>
                
                <p style="color: #333; font-size: 16px; line-height: 1.6;">Thanks,<br>' . $_SERVER['HTTP_HOST'] . ' Team</p>
            </div>';

            sendEmail($user_email, $subject, $message, $user_name);
            
            // Redirect to customer dashboard
            header('Location: ../../customer/dashboard/index.php');
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
        // Check if email already exists in unified users table
        $safe_email = mysqli_real_escape_string($connect, $user_email);
        $safe_contact = mysqli_real_escape_string($connect, $user_contact);

        $email_check = mysqli_query($connect, "SELECT role, email FROM user_details WHERE email='$safe_email' LIMIT 1");
        if ($email_check && mysqli_num_rows($email_check) > 0) {
            $email_data = mysqli_fetch_array($email_check);
            $source = ucfirst(strtolower($email_data['role'] ?? 'user'));
            $errors[] = "This email address is already registered as a $source. Please use a different email.";
        }
        
        // Check if mobile number already exists in unified users table
        $mobile_check = mysqli_query($connect, "SELECT role, phone FROM user_details WHERE phone='$safe_contact' LIMIT 1");
        if ($mobile_check && mysqli_num_rows($mobile_check) > 0) {
            $mobile_data = mysqli_fetch_array($mobile_check);
            $source = ucfirst(strtolower($mobile_data['role'] ?? 'user'));
            $errors[] = "This mobile number is already registered as a $source. Please use a different mobile number.";
        }
        
        if (empty($errors)) {
            // Generate OTP
            $otp = rand(100000, 999999);
            $hashed_password = password_hash($user_password, PASSWORD_DEFAULT);
            
            // Get referrer information from URL or form input
            $referrer_code = '';
            if(isset($_GET['ref']) && !empty($_GET['ref'])) {
                $referrer_code = mysqli_real_escape_string($connect, $_GET['ref']);
            } elseif(isset($_POST['referral_code']) && !empty($_POST['referral_code'])) {
                $referrer_code = mysqli_real_escape_string($connect, $_POST['referral_code']);
            }
            
            $referrer_email = '';
            
            if(!empty($referrer_code)) {
                // Normalize referral code (uppercase, trim)
                $referrer_code = strtoupper(trim($referrer_code));
                
                // First, try to find referrer in user_details table by joining with legacy tables
                // Check if referral code exists in customer_login and get email from user_details
                // Use BINARY to handle collation mismatch
                $referrer_query = mysqli_query($connect, "SELECT ud.email 
                    FROM user_details ud 
                    INNER JOIN customer_login cl ON CAST(ud.email AS BINARY) = CAST(cl.user_email AS BINARY) AND ud.role = 'CUSTOMER'
                    WHERE UPPER(TRIM(cl.referral_code))='$referrer_code' 
                    AND ud.email IS NOT NULL AND ud.email != '' 
                    LIMIT 1");
                
                if(mysqli_num_rows($referrer_query) > 0) {
                    $referrer_data = mysqli_fetch_array($referrer_query);
                    $referrer_email = !empty($referrer_data['email']) ? trim($referrer_data['email']) : '';
                } else {
                    // If not found, check team_members and get email from user_details
                    // Use BINARY to handle collation mismatch
                    $team_referrer_query = mysqli_query($connect, "SELECT ud.email 
                        FROM user_details ud 
                        INNER JOIN team_members tm ON CAST(ud.email AS BINARY) = CAST(tm.member_email AS BINARY) AND ud.role = 'TEAM'
                        WHERE UPPER(TRIM(tm.referral_code))='$referrer_code' 
                        AND ud.email IS NOT NULL AND ud.email != '' 
                        LIMIT 1");
                    
                    if(mysqli_num_rows($team_referrer_query) > 0) {
                        $team_referrer_data = mysqli_fetch_array($team_referrer_query);
                        $referrer_email = !empty($team_referrer_data['email']) ? trim($team_referrer_data['email']) : '';
                    } else {
                        // Fallback: Check legacy tables directly (for backward compatibility)
                        $legacy_customer_query = mysqli_query($connect, "SELECT user_email FROM customer_login WHERE UPPER(TRIM(referral_code))='$referrer_code' AND user_email IS NOT NULL AND user_email != '' LIMIT 1");
                        if(mysqli_num_rows($legacy_customer_query) > 0) {
                            $legacy_data = mysqli_fetch_array($legacy_customer_query);
                            $referrer_email = !empty($legacy_data['user_email']) ? trim($legacy_data['user_email']) : '';
                        } else {
                            $legacy_team_query = mysqli_query($connect, "SELECT member_email FROM team_members WHERE UPPER(TRIM(referral_code))='$referrer_code' AND member_email IS NOT NULL AND member_email != '' LIMIT 1");
                            if(mysqli_num_rows($legacy_team_query) > 0) {
                                $legacy_team_data = mysqli_fetch_array($legacy_team_query);
                                $referrer_email = !empty($legacy_team_data['member_email']) ? trim($legacy_team_data['member_email']) : '';
                            }
                        }
                    }
                }
                
                // Debug logging (can be removed in production)
                if(empty($referrer_email) && !empty($referrer_code)) {
                    error_log("Referral code lookup failed for code: " . $referrer_code);
                }
            }
            
            // Store registration data in session
            $_SESSION['registration_otp'] = $otp;
            $_SESSION['registration_data'] = [
                'user_name' => $user_name,
                'user_email' => $user_email,
                'user_contact' => $user_contact,
                'hashed_password' => $hashed_password,
                'referrer_email' => $referrer_email
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

<div class="login-wrap">
    <div class="login-container">
        <h2 class="heading"> <a href="login.php"><i class="fa fa-angle-left" aria-hidden="true"></i></a> Create An Account</h2>
        <p class="text-white">Create an account to create your Mini Website</p>

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
                <p class="text-white small">After verification, you'll be automatically logged in and redirected to your dashboard.</p>
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
            <div class="mb-3">
                <input type="password" class="form-control" placeholder="Password" name="user_password" required>
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
