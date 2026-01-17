<?php
require('login-connect.php');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$errors = [];

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
$query = mysqli_query($connect, "SELECT * FROM franchisee_login WHERE f_user_email='$email' AND reset_otp IS NOT NULL");
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
        // Update password in database
        $update = mysqli_query($connect, "UPDATE franchisee_login SET f_user_password='$new_password', reset_otp=NULL, reset_otp_expiry=NULL WHERE f_user_email='$email'");

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
                    <input type="password" class="form-control" id="new_password" name="