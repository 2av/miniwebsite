<?php
require('login-connect.php');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$errors = [];

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
    $email = mysqli_real_escape_string($connect, $_GET['email']);
} elseif(isset($_SESSION['otp_email'])) {
    $email = mysqli_real_escape_string($connect, $_SESSION['otp_email']);
} else {
    // Redirect to forgot password page if no email is provided
    header('Location: forgot-password.php');
    exit;
}

// Check if the email exists and OTP was verified
$query = mysqli_query($connect, "SELECT * FROM customer_login WHERE user_email='$email' AND reset_otp IS NOT NULL");
if(mysqli_num_rows($query) == 0) {
    header('Location: forgot-password.php');
    exit;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password'])) {
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validate passwords
    if (empty($new_password)) {
        $errors[] = "Please enter a new password.";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }

    if ($new_password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT); // Secure Hashing
        $update = mysqli_query($connect, "UPDATE customer_login SET user_password='$hashed_password', reset_otp=NULL, reset_otp_expiry=NULL WHERE user_email='$email'");

        if ($update) {
            // Clear session variables
            unset($_SESSION['otp_email']);
            
            echo '<div class="alert success">Password Reset Successfully! Redirecting to Login...</div>';
            echo '<meta http-equiv="refresh" content="2;URL=login.php">';
            exit();
        } else {
            $errors[] = "Error updating password! Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/layout.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .password-toggle {
            cursor: pointer;
            padding: 10px;
            background-color: transparent;
            border: none;
        }
        .eye-open {
            display: none;
        }
        .visible .eye-closed {
            display: none;
        }
        .visible .eye-open {
            display: inline;
        }
        .input-group-text {
            background-color: transparent;
            border-left: none;
        }
        .form-control {
            border-right: none;
        }
    </style>
</head>
<body>
    <div class="login-wrap">
        <div class="login-container">
            <h2 class="heading">
                <a href="verify-otp.php"><i class="fa fa-angle-left" aria-hidden="true"></i></a> Reset Password
            </h2>
            <p class="text-white">Create a new password for your account</p>
            
            <?php
            if (!empty($errors)) {
                echo '<div class="alert danger error-msg"><ul>';
                foreach ($errors as $error) {
                    echo '<li>' . htmlspecialchars($error) . '</li>';
                }
                echo '</ul></div>';
            }
            ?>
            
            <form action="" method="post">
                <input type="hidden" name="email" value="<?php echo $email; ?>">
                
                <div class="mb-4">
                    <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Enter new password" required>
                    <small class="text-white">Password must be at least 6 characters long.</small>
                </div>
                
                <div class="mb-4 position-relative">
                    <div class="input-group mb-2">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                        <div class="input-group-prepend">
                            <div class="input-group-text password-toggle" id="password-toggle" onclick="showpassword()" title="Show/Hide Password">
                                <i class="fa fa-eye eye-closed" aria-hidden="true"></i>
                                <i class="fa fa-eye-slash eye-open" aria-hidden="true"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <input type="submit" name="reset_password" value="Reset Password" class="btn btn-login">
            </form>
        </div>
    </div>

    <script>
        function showpassword(){
            const passwordField = document.getElementById("new_password");
            const confirmPasswordField = document.getElementById("confirm_password");
            const passwordToggle = document.getElementById("password-toggle");

            if(passwordField.type === "password") {
                // Show password
                passwordField.type = "text";
                confirmPasswordField.type = "text";
                passwordToggle.classList.add("visible");
            } else {
                // Hide password
                passwordField.type = "password";
                confirmPasswordField.type = "password";
                passwordToggle.classList.remove("visible");
            }
        }
    </script>
</body>
</html>
