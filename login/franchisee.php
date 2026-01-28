<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Franchisee Login</title>
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
if (isset($_SESSION['f_is_logged_in']) && $_SESSION['f_is_logged_in'] === true) {
    header('Location: ' . $base_path . '/user/dashboard');
    exit;
}

$login_error = '';

// Check if redirected due to session expiration
if (isset($_GET['session']) && $_GET['session'] === 'expired') {
    $login_error = '<div class="alert info error-msg">Your session has expired. Please log in again.</div>';
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login_user'])) {
    if (!empty($_POST['user_id']) && !empty($_POST['user_password'])) {
        $user_id = trim($_POST['user_id']);
        $user_password = trim($_POST['user_password']);

        // Basic validation: must be valid email or 10‑digit mobile
        if (!filter_var($user_id, FILTER_VALIDATE_EMAIL) && !preg_match('/^[0-9]{10}$/', $user_id)) {
            $login_error = '<div class="alert info error-msg">Enter a valid Email ID or 10-digit Mobile Number.</div>';
        } else {
            // Authenticate against unified user_details table (role = FRANCHISEE)
            $stmt = $connect->prepare("
                SELECT 
                    id,
                    email,
                    phone,
                    name,
                    password,
                    password_hash,
                    status
                FROM user_details
                WHERE role = 'FRANCHISEE'
                  AND (email = ? OR phone = ?)
                LIMIT 1
            ");

            if ($stmt) {
                $stmt->bind_param("ss", $user_id, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();

                    $storedPlain = $row['password'] ?? '';
                    $storedHash  = $row['password_hash'] ?? '';
                    $passwordOk  = false;

                    // Prefer secure hash if available, otherwise fall back to legacy plain text
                    if (!empty($storedHash) && password_verify($user_password, $storedHash)) {
                        $passwordOk = true;
                    } elseif (!empty($storedPlain) && $user_password === $storedPlain) {
                        $passwordOk = true;
                    }

                    if ($passwordOk) {
                        if (($row['status'] ?? 'INACTIVE') === 'ACTIVE') {
                            $safeEmail   = htmlspecialchars($row['email'] ?? '');
                            $safeName    = htmlspecialchars($row['name'] ?? '');
                            $safePhone   = htmlspecialchars($row['phone'] ?? '');
                            $safeUserId  = (int)($row['id'] ?? 0);

                            // Franchisee-specific session markers (used by role_helper)
                            $_SESSION['f_user_email']   = $safeEmail;
                            $_SESSION['f_user_name']    = $safeName;
                            $_SESSION['f_user_contact'] = $safePhone;
                            $_SESSION['f_user_id']      = $safeUserId;
                            $_SESSION['f_is_logged_in'] = true;
                            $_SESSION['f_login_time']   = time();

                            // Also set generic user_* sessions for shared dashboard code
                            $_SESSION['user_email']   = $safeEmail;
                            $_SESSION['user_name']    = $safeName;
                            $_SESSION['user_contact'] = $safePhone;
                            $_SESSION['user_id']      = $safeUserId;
                            $_SESSION['is_logged_in'] = true;
                            $_SESSION['login_time']   = time();

                            echo '<div class="alert Success">Login Successful, Redirecting...</div>';
                            echo '<meta http-equiv="refresh" content="1;URL=' . $base_path . '/user/dashboard">';
                            exit();
                        } else {
                            $login_error = '<div class="alert info error-msg"><strong>Sorry!</strong> Your account is not Active/Verified. Please contact our support.<br><a href="/#contact"><b>Click here </b></a></div>';
                        }
                    } else {
                        $login_error = '<div class="alert info error-msg">Incorrect Password! Try Again. <br><a href="#"><b>Reset Password</b></a></div>';
                    }
                } else {
                    $login_error = '<div class="alert info error-msg">Invalid user id or password.</div>';
                }

                $stmt->close();
            } else {
                $login_error = '<div class="alert danger error-msg">Unable to process login right now. Please try again later.</div>';
            }
        }
    } else {
        $login_error = '<div class="alert info error-msg">All fields are required.</div>';
    }
}
?>

<div class="login-wrap">
    <div class="login-container">
        <h2 class="heading"><a href="<?php echo $base_path; ?>/"><i class="fa fa-angle-left" aria-hidden="true"></i></a>Franchisee Login</h2>
        <p class="text-white">Please enter your details</p>

        <form action="" method="post" autocomplete="off" id="login">
            <div class="mb-4">
                <input type="text" class="form-control" name="user_id" id="user_id" placeholder="Enter Email ID or Mobile Number*" required>
            </div>
            <div class="mb-5 position-relative">
                <div class="input-group mb-2">
                    <input type="password" class="form-control" name="user_password" id="user_password" placeholder="Password" required>
                    <div class="input-group-prepend">
                        <div class="input-group-text password-toggle" id="password-toggle" onclick="togglePassword()" title="Show/Hide Password">
                            <i class="fa fa-eye eye-closed" aria-hidden="true"></i>
                            <i class="fa fa-eye-slash eye-open" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>
                <a href="forgot-password.php?role=FRANCHISEE" class="forgot-password">Forgot Password?</a>
            </div>
            <input type="submit" name="login_user" value="LOG IN" class="btn btn-login">
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
function togglePassword() {
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