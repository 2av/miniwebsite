<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if this page was accessed directly without completing registration
if (!isset($_SESSION['registration_success']) || $_SESSION['registration_success'] !== true) {
    header('Location: create-account.php');
    exit;
}

// Clear the success flag
unset($_SESSION['registration_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Successful</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .success-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        .success-icon {
            font-size: 60px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .countdown {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">✓</div>
        <h2>Registration Successful!</h2>
        <p>Your account has been created successfully.</p>
        <p>You can now log in with your email and password.</p>
        <div class="countdown" id="countdown">3</div>
        <p>Redirecting to login page...</p>
        <a href="login.php" class="btn btn-primary">Go to Login Now</a>
    </div>

    <script>
        // Countdown timer
        let seconds = 3;
        const countdownElement = document.getElementById('countdown');
        
        const countdownTimer = setInterval(function() {
            seconds--;
            countdownElement.textContent = seconds;
            
            if (seconds <= 0) {
                clearInterval(countdownTimer);
                window.location.href = 'login.php';
            }
        }, 1000);
    </script>
</body>
</html>
