<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Login</title>
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

// Check if team member is already logged in
if (!empty($_SESSION['user_email']) && !empty($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
    header('Location: ' . $base_path . '/user/dashboard');
    exit;
}

$login_messages = [];

if (isset($_GET['session']) && $_GET['session'] === 'expired') {
    $login_messages[] = ['type' => 'info', 'message' => 'Your session has expired. Please log in again.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $login_messages[] = ['type' => 'danger', 'message' => 'Please enter a valid email address.'];
    }
    if ($password === '') {
        $login_messages[] = ['type' => 'danger', 'message' => 'Please enter your password.'];
    }

    if (empty($login_messages)) {
        // Authenticate against unified user_details table for TEAM role
        $stmt = $connect->prepare('SELECT id, name, email, phone, password, password_hash, status FROM user_details WHERE email = ? AND role = "TEAM" LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $member = $result->fetch_assoc();
            $stmt->close();

            if ($member) {
                $storedHash    = $member['password_hash'] ?? '';
                $storedPlain   = $member['password'] ?? '';
                $passwordValid = false;

                // Prefer secure hash if present, otherwise fall back to legacy plain-text comparison
                if (!empty($storedHash) && password_verify($password, $storedHash)) {
                    $passwordValid = true;
                } elseif (!empty($storedPlain) && $password === $storedPlain) {
                    $passwordValid = true;
                }

                if ($passwordValid) {
                    if (($member['status'] ?? 'INACTIVE') !== 'ACTIVE') {
                        $login_messages[] = ['type' => 'info', 'message' => 'This team member account is currently inactive. Please contact the administrator.'];
                    } else {
                        // Set generic user session variables (for existing code paths)
                        $_SESSION['user_email'] = $member['email'];
                        $_SESSION['user_name'] = $member['name'] ?? '';
                        $_SESSION['user_referral_code'] = ''; // no separate referral code column in user_details
                        $_SESSION['is_logged_in'] = true;
                        $_SESSION['login_time'] = time();
                        $_SESSION['team_member_id'] = $member['id'];

                        // TEAM-specific markers
                        $_SESSION['t_user_email'] = $member['email'];
                        $_SESSION['t_user_name']  = $member['name'] ?? '';
                        $_SESSION['t_user_id']    = $member['id'];
                        $_SESSION['t_is_logged_in'] = true;

                        // Redirect to dashboard on success
                        header('Location: ' . $base_path . '/user/dashboard');
                        exit;
                    }
                } else {
                    $login_messages[] = ['type' => 'danger', 'message' => 'Invalid login credentials. Please try again.'];
                }
            } else {
                $login_messages[] = ['type' => 'danger', 'message' => 'Invalid login credentials. Please try again.'];
            }
        } else {
            error_log('Team login prepare failed: ' . $connect->error);
            $login_messages[] = ['type' => 'danger', 'message' => 'Unable to process login right now. Please try again later.'];
        }
    }
}

$login_error = '';
if (!empty($login_messages)) {
    foreach ($login_messages as $alert) {
        $message = htmlspecialchars($alert['message'], ENT_QUOTES, 'UTF-8');
        $type = htmlspecialchars($alert['type'], ENT_QUOTES, 'UTF-8');
        $login_error .= '<div class="alert ' . $type . ' error-msg">' . $message . '</div>';
    }
}
?>
<div class="login-wrap">
    <div class="login-container">
        <h2 class="heading"><a href="<?php echo $base_path; ?>/"><i class="fa fa-angle-left" aria-hidden="true"></i></a>Team Login</h2>
        <p class="text-white">Please enter your team credentials</p>

        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" autocomplete="off" id="team-login">
            <div class="mb-4">
                <input type="email" class="form-control" name="email" id="email" placeholder="Enter Team Email*" required>
            </div>
            <div class="mb-5 position-relative">
                <div class="input-group mb-2">
                    <input type="password" class="form-control" name="password" id="password" placeholder="Password" required>
                    <div class="input-group-prepend">
                        <div class="input-group-text password-toggle" id="password-toggle" onclick="showpassword()" title="Show/Hide Password">
                            <i class="fa fa-eye eye-closed" aria-hidden="true"></i>
                            <i class="fa fa-eye-slash eye-open" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>
                <a href="forgot-password.php?role=TEAM" class="forgot-password">Forgot Password?</a>
            </div>
            <input type="submit" value="LOG IN" class="btn btn-login">
        </form>
        <?php echo $login_error; ?>
    </div>
</div>

<script src="../assets/js/jquery.slim.min.js"></script>
<script>
  if (!window.jQuery) {
    document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
  }
</script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
  if (!window.bootstrap && !(window.jQuery && window.jQuery.fn && window.jQuery.fn.dropdown)) {
    document.write('<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"><\/script>');
  }
</script>
<script>
    function showpassword(){
        const passwordField = document.getElementById("password");
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