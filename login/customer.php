<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Login</title>
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon.ico">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Baloo+Bhai+2:wght@400..800&family=Baloo+Bhaina+2:wght@400..800&family=Barlow:wght@400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="../assets/css/font-awesome.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/layout.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <style>
        /* Password toggle button styling */
        .password-toggle {
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 0;
            height: 100%;
            margin: 0;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            background: transparent;
            border: none;
        }
        .password-toggle i {
            position: absolute;
            transition: opacity 0.2s ease, transform 0.2s ease;
            font-size: 18px;
            color: #6c757d;
        }
        .password-toggle .eye-open {
            opacity: 1;
        }
        .password-toggle .eye-closed {
            opacity: 0;
        }
        .password-toggle.visible .eye-open {
            opacity: 0;
        }
        .password-toggle.visible .eye-closed {
            opacity: 1;
        }
        .password-toggle:hover i {
            color: #007bff;
            transform: scale(1.05);
        }
        .input-group-prepend {
            display: flex;
            position: absolute;
            right: 0;
            top: 0;
            height: 100%;
            z-index: 5;
        }
        .input-group-text {
            background-color: transparent;
            border: none;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .form-control {
            padding-right: 40px;
            width: 100%;
        }
    </style>
</head>
<body>
<?php
// Use centralized database config (sessions + DB already initialized)
require_once __DIR__ . '/../app/config/database.php';

// Get base path (works for both localhost subfolder and production root)
function get_base_path() {
    $script_name = $_SERVER['SCRIPT_NAME'];
    $script_dir = dirname($script_name);
    // Remove /login from path to get base
    $base = str_replace('/login', '', $script_dir);
    // Ensure it ends with / or is empty for root
    return $base === '/' ? '' : $base;
}
$base_path = get_base_path();

// Check if already logged in
if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
    header('Location: ' . $base_path . '/user/dashboard');
    exit;
}

$login_error = '';

// Check if redirected due to session expiration
if (isset($_GET['session']) && $_GET['session'] === 'expired') {
    $login_error = '<div class="alert info error-msg">Your session has expired. Please log in again.</div>';
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login_user'])) {
    $email_or_contact = trim($_POST['user_id']);
    $password = trim($_POST['user_password']);

    // Check if fields are empty
    if (empty($email_or_contact) || empty($password)) {
        $login_error = '<div class="alert danger error-msg">Please enter both Email/Mobile and Password.</div>';
    }
    // Validate email or mobile number
    elseif (!filter_var($email_or_contact, FILTER_VALIDATE_EMAIL) && !preg_match('/^[0-9]{10}$/', $email_or_contact)) {
        $login_error = '<div class="alert danger error-msg">Enter a valid Email ID or 10-digit Mobile Number.</div>';
    }
    else {
        $email_or_contact = mysqli_real_escape_string($connect, $email_or_contact);

        // Try login from unified user_details table (role = CUSTOMER)
        $userSql = "
            SELECT 
                u.id,
                u.email      AS user_email,
                u.phone      AS user_contact,
                u.name       AS user_name,
                u.password   AS user_password,
                u.status,
                cl.referral_code,
                cl.collaboration_enabled,
                cl.saleskit_enabled
            FROM user_details u
            LEFT JOIN customer_login cl ON cl.user_email = u.email
            WHERE u.role = 'CUSTOMER'
              AND (u.email = '$email_or_contact' OR u.phone = '$email_or_contact')
            LIMIT 1
        ";

        $query = mysqli_query($connect, $userSql);
        $row = $query ? mysqli_fetch_assoc($query) : null;

        if ($row) {
            $storedPassword = $row['user_password'] ?? '';

            // Check password hash or plain text match (supports old accounts)
            if (!empty($storedPassword) && (password_verify($password, $storedPassword) || $password === $storedPassword)) {
                // Store user data in session with proper sanitization
                $_SESSION['user_id'] = isset($row['id']) ? (int)$row['id'] : 0;
                $_SESSION['user_email'] = htmlspecialchars($row['user_email']);
                $_SESSION['user_name'] = htmlspecialchars($row['user_name'] ?? '');
                $_SESSION['user_contact'] = htmlspecialchars($row['user_contact'] ?? '');
                $_SESSION['user_referral_code'] = htmlspecialchars($row['referral_code'] ?? '');
                $_SESSION['collaboration_enabled'] = (($row['collaboration_enabled'] ?? '') === 'YES');
                $_SESSION['saleskit_enabled'] = (isset($row['saleskit_enabled']) && $row['saleskit_enabled'] === 'YES');

                // Set a session marker to track login status
                $_SESSION['is_logged_in'] = true;
                $_SESSION['login_time'] = time();

                echo '<div class="alert success">Login Successful! Redirecting...</div>';
                echo '<meta http-equiv="refresh" content="2;URL=' . $base_path . '/user/dashboard">';
                exit;
            } else {
                $login_error = '<div class="alert danger error-msg">Incorrect password! Please try again.</div>';
            }
        } else {
            $login_error = '<div class="alert info error-msg">User does not exist. Please create an account.</div>';
        }
    }
}
?>

<div class="login-wrap">
    <div class="login-container">
        <h2 class="heading"><a href="/"><i class="fa fa-angle-left" aria-hidden="true"></i></a>Customer Login</h2>
        <p class="text-white">Please enter your details</p>

        <form action="" method="post" autocomplete="off" id="login">
            <div class="mb-4">
                <input type="text" class="form-control" name="user_id" id="user_id" placeholder="Enter Email ID or Mobile Number*" required>
            </div>
            <div class="mb-5 position-relative">
                <div class="input-group mb-2">
                    <input type="password" class="form-control" name="user_password" id="user_password" placeholder="Password" required>
                    <div class="input-group-prepend">
                        <div class="input-group-text password-toggle" id="password-toggle" onclick="showpassword()" title="Show/Hide Password">
                            <i class="fa fa-eye eye-closed" aria-hidden="true"></i>
                            <i class="fa fa-eye-slash eye-open" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>
                <a href="#" class="forgot-password">Forgot Password?</a>
            </div>
            <input type="submit" name="login_user" value="LOG IN" class="btn btn-login">
        </form>
        <?php echo $login_error; ?>
        <div class="CreateAccount-wrap">
            <span class="text-white">Don't have an account? &nbsp;
                <a href="#" class="create-account">Create Account</a>
            </span>
        </div>
    </div>
</div>

<script src="../assets/js/jquery.min.js"></script>
<script src="../assets/js/bootstrap.min.js"></script>
<script>
    function showpassword(){
        const passwordField = document.getElementById("user_password");
        const passwordToggle = document.getElementById("password-toggle");

        if(passwordField.type === "password") {
            passwordField.type = "text";
            passwordToggle.classList.add("visible");
        } else {
            passwordField.type = "password";
            passwordToggle.classList.remove("visible");
        }
    }
</script>
</body>
</html>