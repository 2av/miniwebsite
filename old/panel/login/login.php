<title>Customer Login</title>
<?php
require('login-connect.php');
if(isset($_SESSION['sender_token'])){
	$sender_token=$_SESSION['sender_token'];
}else {
	$sender_token='';
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

        // NEW: Try login from unified user_details table (role = CUSTOMER)
        $userSql = "
            SELECT 
                u.id,
                u.email      AS user_email,
                u.phone      AS user_contact,
                u.name       AS user_name,
                u.password   AS user_password,
                u.status,
                -- join to old table to reuse extra flags if present
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
                $_SESSION['user_id'] = isset($row['id']) ? (int)$row['id'] : 0; // Save primary key for FK usage
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
                echo '<meta http-equiv="refresh" content="2;URL=../../customer/dashboard/">';
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

<!-- Include custom styles for password toggle -->
<link rel="stylesheet" href="login-styles.css">

<div class="login-wrap">

    <div class="login-container">
        <h2 class="heading"><a href="https://miniwebsite.in"><i class="fa fa-angle-left" aria-hidden="true"></i></a>Customer Login</h2>
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
                            <i class="fa fa-eye eye-closed" aria-hidden="true" ></i>
                            <i class="fa fa-eye-slash eye-open" aria-hidden="true" ></i>
                        </div>
                    </div>
                </div>
                <a href="forgot-password.php" class="forgot-password">Forgot Password?</a>
            </div>
            <input type="submit" name="login_user" value="LOG IN" class="btn btn-login">
        </form>
		<?php echo $login_error; // Display error messages ?>
        <div class="CreateAccount-wrap">
            <span class="text-white">Don't have an account? &nbsp;
                <a href="create-account.php" class="create-account">Create Account</a>
            </span>
        </div>
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
