<?php
// Start session and include database connection FIRST - before any HTML output
require_once(__DIR__ . '/../app/config/database.php');
require_once(__DIR__ . '/../app/helpers/password_helper.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - MiniWebsite</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Animated background shapes */
        body::before,
        body::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 20s infinite ease-in-out;
        }

        body::before {
            width: 400px;
            height: 400px;
            top: -100px;
            left: -100px;
        }

        body::after {
            width: 300px;
            height: 300px;
            bottom: -50px;
            right: -50px;
            animation-delay: -10s;
        }

        @keyframes float {
            0%, 100% {
                transform: translate(0, 0) rotate(0deg);
            }
            33% {
                transform: translate(30px, -30px) rotate(120deg);
            }
            66% {
                transform: translate(-20px, 20px) rotate(240deg);
            }
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 50px 40px;
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 1;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .login-header .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .login-header .logo-icon i {
            font-size: 40px;
            color: white;
        }

        .login-header h1 {
            color: #2d3748;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }

        .login-header p {
            color: #718096;
            font-size: 14px;
            font-weight: 400;
            line-height: 1.6;
        }

        .login-form {
            margin-top: 30px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            color: #2d3748;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            font-size: 16px;
        }

        .form-group input {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            background: #f7fafc;
            color: #2d3748;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .form-group input::placeholder {
            color: #a0aec0;
        }

        .login-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            margin-top: 10px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.5);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            animation: slideDown 0.4s ease-out;
            border: none;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert.Success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert.info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }

        .alert.danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .footer-text {
            text-align: center;
            margin-top: 30px;
            color: #a0aec0;
            font-size: 12px;
        }

        .footer-text a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .footer-text a:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                padding: 40px 30px;
            }

            .login-header h1 {
                font-size: 24px;
            }

            .login-header .logo-icon {
                width: 70px;
                height: 70px;
            }

            .login-header .logo-icon i {
                font-size: 35px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1>Admin Login</h1>
            <p>Welcome back! Please login to access the admin dashboard</p>
        </div>

        <?php
        if(isset($_POST['login_user'])){
            // Query user_details table for admin
            $admin_email_input = mysqli_real_escape_string($connect, $_POST['admin_email']);
            $admin_password_input = trim($_POST['admin_password'] ?? '');
            
            // Fetch both password and password_hash columns for verification
            $query = mysqli_query($connect, 'SELECT id, email, phone, name, password, password_hash, status FROM user_details WHERE role="ADMIN" AND (email="'.$admin_email_input.'" OR phone="'.$admin_email_input.'") LIMIT 1');
            
            if(mysqli_num_rows($query) > 0){
                $row = mysqli_fetch_assoc($query);
                
                // Use centralized password verification helper
                $storedPasswordHash = $row['password_hash'] ?? '';
                $storedPassword = $row['password'] ?? '';
                $passwordValid = mw_verify_stored_password($admin_password_input, $storedPasswordHash, $storedPassword);
                
                if($passwordValid){
                    // Check if account is active
                    if(($row['status'] ?? 'INACTIVE') === 'ACTIVE'){
                        $_SESSION['admin_email'] = htmlspecialchars($row['email'] ?? '');
                        $_SESSION['admin_name'] = htmlspecialchars($row['name'] ?? '');
                        $_SESSION['admin_contact'] = htmlspecialchars($row['phone'] ?? '');
                        $_SESSION['admin_id'] = (int)($row['id'] ?? 0);
                        $_SESSION['admin_is_logged_in'] = true;
                        $_SESSION['admin_login_time'] = time();
                        
                        echo '<div class="alert Success"><i class="fas fa-check-circle"></i> Login successful! Redirecting...</div>';
                        echo '<meta http-equiv="refresh" content="1;URL=index.php">';
                    } else {
                        echo '<div class="alert info"><i class="fas fa-exclamation-circle"></i> Your account is inactive. Please contact administrator.</div>';
                    }
                } else {
                    echo '<div class="alert info"><i class="fas fa-exclamation-circle"></i> Incorrect password. Please try again.</div>';
                }
            } else {
                echo '<div class="alert info"><i class="fas fa-user-times"></i> User does not exist. Please check your credentials.</div>';
            }
        }
        ?>

        <form action="" method="post" autocomplete="off" class="login-form" id="login">
            <div class="form-group">
                <label for="admin_email"><i class="fas fa-user"></i> Email or Mobile Number</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input type="text" 
                           id="admin_email" 
                           name="admin_email" 
                           placeholder="Enter your email or mobile number" 
                           autocomplete="off" 
                           autofocus 
                           required>
                </div>
            </div>

            <div class="form-group">
                <label for="admin_password"><i class="fas fa-lock"></i> Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-key"></i>
                    <input type="password" 
                           id="admin_password" 
                           name="admin_password" 
                           placeholder="Enter your password" 
                           autocomplete="off" 
                           required>
                </div>
            </div>

            <button type="submit" name="login_user" class="login-btn">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>

        <div class="footer-text">
            <p>&copy; <?php echo date('Y'); ?> MiniWebsite. All rights reserved.</p>
        </div>
    </div>
</body>
</html>



